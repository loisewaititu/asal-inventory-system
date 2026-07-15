<?php
session_start();
include 'connect.php'; // Your DB connection file
require_once 'includes/audit_logger.php'; // Make sure this file path is correct

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// Initialize variables for errors and old input
$errors = $_SESSION['errors'] ?? [];
$old_input = $_SESSION['old_input'] ?? [];

// Clear session variables after retrieving them
unset($_SESSION['errors']);
unset($_SESSION['old_input']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = []; // Reset errors for new submission
    $input_data = []; // To store validated and trimmed input

    // --- 1. Validate and Sanitize Inputs ---

    // First Names
    $input_data['First_Names'] = trim($_POST['First_Names'] ?? '');
    if (empty($input_data['First_Names'])) {
        $errors['First_Names'] = "First Name(s) are required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $input_data['First_Names'])) {
        $errors['First_Names'] = "First Name(s) can only contain letters and spaces.";
    } elseif (strlen($input_data['First_Names']) > 100) {
        $errors['First_Names'] = "First Name(s) cannot exceed 100 characters.";
    }

    // Last Name
    $input_data['Last_Name'] = trim($_POST['Last_Name'] ?? '');
    if (empty($input_data['Last_Name'])) {
        $errors['Last_Name'] = "Last Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $input_data['Last_Name'])) {
        $errors['Last_Name'] = "Last Name can only contain letters and spaces.";
    } elseif (strlen($input_data['Last_Name']) > 100) {
        $errors['Last_Name'] = "Last Name cannot exceed 100 characters.";
    }

    // Personal Number
    $input_data['Personal_number'] = trim($_POST['Personal_number'] ?? '');
    if (empty($input_data['Personal_number'])) {
        $errors['Personal_number'] = "Personal Number is required.";
    } elseif (!ctype_digit($input_data['Personal_number'])) { // Checks if all characters are digits
        $errors['Personal_number'] = "Personal Number must be numeric.";
    } elseif (strlen($input_data['Personal_number']) < 4 || strlen($input_data['Personal_number']) > 10) { // Example: 4-10 digits
        $errors['Personal_number'] = "Personal Number must be between 4 and 10 digits.";
    } else {
        $input_data['Personal_number'] = intval($input_data['Personal_number']); // Ensure it's an integer
    }

    // Email
    $input_data['email'] = trim($_POST['email'] ?? '');
    if (empty($input_data['email'])) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($input_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } elseif (strlen($input_data['email']) > 255) {
        $errors['email'] = "Email cannot exceed 255 characters.";
    }

    // Password
    $input_data['password'] = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($input_data['password'])) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($input_data['password']) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }

    // Password Confirmation
    if (empty($confirm_password)) {
        $errors['confirm_password'] = "Please confirm your password.";
    } elseif ($input_data['password'] !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // Role
    $input_data['role'] = trim($_POST['role'] ?? '');
    $allowed_roles = ['admin', 'user'];
    if (empty($input_data['role'])) {
        $errors['role'] = "Role is required.";
    } elseif (!in_array($input_data['role'], $allowed_roles)) {
        $errors['role'] = "Invalid role selected.";
    }

    // Designation
    $input_data['designation'] = trim($_POST['designation'] ?? '');
    $allowed_designations = [
        "Deputy Director ICT",
        "Senior ICT Officer",
        "Assistant Director ICT",
        "ICT Officer",
        "Intern",
        "Attachee"
    ];
    if (empty($input_data['designation'])) {
        $errors['designation'] = "Designation is required.";
    } elseif (!in_array($input_data['designation'], $allowed_designations)) {
        $errors['designation'] = "Invalid designation selected.";
    }

    // --- 2. If no basic validation errors, proceed with database checks ---
    if (empty($errors)) {
        // Prevent duplicate email
        $stmt_check_email = $conn->prepare("SELECT id FROM register WHERE email = ?");
        $stmt_check_email->bind_param("s", $input_data['email']);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $errors['email'] = "This email is already registered! Please login.";
        }
        $stmt_check_email->close();
    }

    if (empty($errors)) {
        // Limit total users to 15
        $userCountQuery = "SELECT COUNT(*) AS total_users FROM register";
        $countResult = $conn->query($userCountQuery);
        $totalUsers = $countResult->fetch_assoc()['total_users'];

        if ($totalUsers >= 15) {
            $errors['general'] = "User registration limit reached (15 users only).";
        }
    }

    if (empty($errors) && $input_data['role'] == "admin") {
        // Admin restrictions
        $adminCountQuery = "SELECT COUNT(*) AS total_admins FROM register WHERE role='admin'";
        $countResult = $conn->query($adminCountQuery);
        $adminCount = $countResult->fetch_assoc()['total_admins'];

        if ($adminCount >= 4) {
            $errors['role'] = "Admin limit reached (4 admins only). Contact Super Admin.";
        }
    }

    // --- 3. If any errors exist, store them and redirect back to form ---
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST; // Keep all POST data for re-population
        header("Location: register as.php");
        exit();
    }

    // --- 4. If all validations pass, proceed with registration ---

    $hashed_password = password_hash($input_data['password'], PASSWORD_DEFAULT);
    $is_super_admin = 0; // Default
    if ($input_data['role'] == "admin") {
        $adminCountQuery = "SELECT COUNT(*) AS total_admins FROM register WHERE role='admin'";
        $countResult = $conn->query($adminCountQuery);
        $adminCount = $countResult->fetch_assoc()['total_admins'];
        if ($adminCount < 2) { // Assign super admin if fewer than 2 admins exist
            $is_super_admin = 1;
        }
    }

    // Insert new user using prepared statement
    $insert_stmt = $conn->prepare("INSERT INTO register (First_Names, Last_Name, Personal_number, email, password, role, designation, is_super_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssissssi",
        $input_data['First_Names'],
        $input_data['Last_Name'],
        $input_data['Personal_number'],
        $input_data['email'],
        $hashed_password,
        $input_data['role'],
        $input_data['designation'],
        $is_super_admin
    );

    if ($insert_stmt->execute()) {
        $new_user_id = $insert_stmt->insert_id;
        log_activity($current_admin_id, $current_admin_name, 'USER_REGISTERED',
            "New user registered: " . $input_data['email'] . " with role " . $input_data['role'], 'user', $new_user_id, null, $input_data);

        // Success: Redirect to login page
        $_SESSION['success_message'] = "Registration Successful! Please Login.";
        header("Location: loginpage.php");
        exit();
    } else {
        // Database error
        $errors['database'] = "Database error: " . $conn->error;
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST;
        header("Location: register as.php");
        exit();
    }
    $insert_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - J.L Inventory System</title>
    <link rel="stylesheet" href="register as.css">
</head>
<body>

    <h1>Register - J.L Inventory System</h1>

    <div class="login-form">
        <?php
        // Display general error message if any
        if (isset($errors['general'])) {
            echo "<div class='error' style='text-align: center;'>" . htmlspecialchars($errors['general']) . "</div>";
        }
        // Display database error message if any
        if (isset($errors['database'])) {
            echo "<div class='error' style='text-align: center;'>" . htmlspecialchars($errors['database']) . "</div>";
        }
        ?>
        <form action="register as.php" method="POST">
            <input type="text" name="First_Names" placeholder="First Name(s)"
                   value="<?php echo htmlspecialchars($old_input['First_Names'] ?? ''); ?>" required>
            <?php if (isset($errors['First_Names'])) echo "<span class='error'>" . htmlspecialchars($errors['First_Names']) . "</span>"; ?>

            <input type="text" name="Last_Name" placeholder="Last Name"
                   value="<?php echo htmlspecialchars($old_input['Last_Name'] ?? ''); ?>" required>
            <?php if (isset($errors['Last_Name'])) echo "<span class='error'>" . htmlspecialchars($errors['Last_Name']) . "</span>"; ?>

            <input type="number" name="Personal_number" placeholder="Personal Number"
                   value="<?php echo htmlspecialchars($old_input['Personal_number'] ?? ''); ?>" required>
            <?php if (isset($errors['Personal_number'])) echo "<span class='error'>" . htmlspecialchars($errors['Personal_number']) . "</span>"; ?>

            <input type="email" name="email" placeholder="Email"
                   value="<?php echo htmlspecialchars($old_input['email'] ?? ''); ?>" required>
            <?php if (isset($errors['email'])) echo "<span class='error'>" . htmlspecialchars($errors['email']) . "</span>"; ?>

            <input type="password" name="password" placeholder="Password" required>
            <?php if (isset($errors['password'])) echo "<span class='error'>" . htmlspecialchars($errors['password']) . "</span>"; ?>

            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <?php if (isset($errors['confirm_password'])) echo "<span class='error'>" . htmlspecialchars($errors['confirm_password']) . "</span>"; ?>

            <select name="role" required>
                <option value="">-- Select Your Role --</option>
                <option value="admin" <?php echo (($old_input['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="user" <?php echo (($old_input['role'] ?? '') == 'user') ? 'selected' : ''; ?>>User</option>
            </select>
            <?php if (isset($errors['role'])) echo "<span class='error'>" . htmlspecialchars($errors['role']) . "</span>"; ?>

            <select name="designation" required>
                <option value="">-- Select Your Designation --</option>
                <option value="Deputy Director ICT" <?php echo (($old_input['designation'] ?? '') == 'Deputy Director ICT') ? 'selected' : ''; ?>>Deputy Director ICT</option>
                <option value="Senior ICT Officer" <?php echo (($old_input['designation'] ?? '') == 'Senior ICT Officer') ? 'selected' : ''; ?>>Senior ICT Officer</option>
                <option value="Assistant Director ICT" <?php echo (($old_input['designation'] ?? '') == 'Assistant Director ICT') ? 'selected' : ''; ?>>Assistant Director ICT</option>
                <option value="ICT Officer" <?php echo (($old_input['designation'] ?? '') == 'ICT Officer') ? 'selected' : ''; ?>>ICT Officer</option>
                <option value="Intern" <?php echo (($old_input['designation'] ?? '') == 'Intern') ? 'selected' : ''; ?>>Intern</option>
                <option value="Attachee" <?php echo (($old_input['designation'] ?? '') == 'Attachee') ? 'selected' : ''; ?>>Attachee</option>
            </select>
            <?php if (isset($errors['designation'])) echo "<span class='error'>" . htmlspecialchars($errors['designation']) . "</span>"; ?>

            <button type="submit">Register</button>
        </form>

        <p style="text-align: center; margin-top: 15px;">
            Already have an account?
            <a href="loginpage.php" style="color: #8e44ad; text-decoration: none; font-weight: bold;">Login Here</a>
        </p>
    </div>

</body>
</html>