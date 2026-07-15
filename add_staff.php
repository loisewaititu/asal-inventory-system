<?php
include 'connect.php';
session_start();
require_once 'connect.php'; // your DB connection file
require_once 'includes/audit_logger.php';  

// ✅ Prevent cached pages from being accessed after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Auto logout after 1 hour of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// ✅ Restrict access to admins only
if (!isset($_SESSION['admin_username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}
$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

$departments = [
    'Research & Planning Division',
    'Research & Policy Development',
    'Knowledge Management',
    'Partnership & Resource Mobilization',
    'Value Chain & Entrepreneurship',
    'Livelihood Diversification',
    'Special Programs - Human Capital Development',
    'Special Programs - ASALS Programmes',
    'Special Programs - Climate Change Response',
    'Strategic Programs',
    'Community Social Integration Div - Peace & Building Conflict Mgt',
    'Community Social Integration Div - Integrated Cross Border Section',
    'HR',
    'Administration',
    'Finance',
    'Counselling',
    'Accounts',
    'CPPMU',
    'ICT',
    'SCMS',
    'PRO/PCO',
    'Legal',
    'Internal Audit'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capture the new fields from the POST request
    $First_names = trim($_POST['First_names'] ?? ''); // Added null coalescing for robustness against missing keys
    $Last_name = trim($_POST['Last_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $Personal_number = trim($_POST['Personal_number'] ?? ''); 
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');

    // Check if all required fields are present
    // FIX 2: Check the correctly assigned variable $personal_no in the validation condition
    if ($First_names && $Last_name && $designation && $Personal_number && $email && $department) {
        // Prepare the INSERT statement with the new columns
        // Make sure 'staff' table in your database has 'first_name', 'last_name', 'designation', 'phone_no' columns
        $stmt = $conn->prepare("INSERT INTO staff (First_names, Last_name, designation, Personal_number, email, department) VALUES (?, ?, ?, ?, ?, ?)");
        
        // Bind the new parameters (note the 'sssssss' for 7 string parameters)
        $stmt->bind_param("ssssss", $First_names, $Last_name, $designation, $Personal_number, $email, $department);
        
        if ($stmt->execute()) {
            echo "<script>alert('✅ Staff added successfully!'); window.location.href='Staff.php';</script>";
        } else {
            // Provide more detailed error for debugging if insert fails
            echo "❌ Error: " . $stmt->error . " (SQLSTATE: " . $stmt->errno . ")";
        }
        $stmt->close();
    } else {
        echo "<script>alert('❌ Please fill in all fields.');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Employee</title>
   <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        padding: 0;
        /* A gentle background gradient from Akaroa to Tan for a soft, inviting feel */
        background: linear-gradient(to right, #D2BCA1, #D5B893);
        /* Main body text color set to Coffee for good contrast and warmth */
        color: #6F4D38;
        display: flex; /* Use flexbox to easily center the container */
        justify-content: center; /* Center horizontally */
        align-items: center; /* Center vertically */
        min-height: 100vh; /* Ensure it takes full viewport height */
    }

    .container {
        width: 100%; /* Make it responsive */
        max-width: 500px; /* Max width to keep it from getting too wide */
        margin: 50px auto; /* Still keeps margin for spacing, but flexbox handles centering */
        /* Changed: Pure white background for the container */
        background: #FFFFFF; /* White */
        padding: 40px; /* Increased padding */
        border-radius: 15px; /* More rounded corners */
        /* A warmer, richer shadow using Walnut */
        box-shadow: 0 8px 20px rgba(111, 72, 28, 0.3); /* Walnut shadow */
        border: 1px solid rgba(111, 77, 56, 0.1); /* Subtle Coffee border */
    }

    h2 {
        text-align: center;
        /* Space Cadet for the heading, strong and grounding */
        color: #25344F;
        margin-bottom: 35px; /* More space below heading */
        font-size: 32px; /* Larger heading */
        letter-spacing: 1px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1); /* Subtle text shadow */
    }

    label {
        display: block;
        margin-top: 20px; /* More space above labels */
        /* Coffee for labels, readable and harmonious */
        color: #6F4D38;
        font-weight: 600; /* Bolder labels */
        font-size: 16px;
        margin-bottom: 5px; /* Small space between label and input */
    }

    input, select {
        width: calc(100% - 22px); /* Account for padding and border */
        padding: 12px; /* More padding for input fields */
        margin-top: 5px;
        /* Subtle border using Slate Gray */
        border: 1px solid #617891;
        border-radius: 8px; /* Slightly more rounded inputs */
        font-size: 16px;
        color: #25344F; /* Space Cadet for input text */
        background-color: #FDFDFD; /* Near white background for inputs */
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    input:focus, select:focus {
        outline: none;
        /* Highlight focus with Desert accent color */
        border-color: #A76825;
        box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2); /* Desert-colored glow */
    }

    button {
        /* Desert for the primary action button, vibrant and inviting */
        background-color: #A76825;
        color: white;
        padding: 15px; /* Larger button */
        margin-top: 30px; /* More space above button */
        border: none;
        border-radius: 8px; /* Rounded button corners */
        cursor: pointer;
        width: 100%;
        font-size: 18px; /* Larger font on button */
        font-weight: bold;
        letter-spacing: 0.5px;
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
    }

    button:hover {
        /* Darker shade of Desert or Walnut on hover for a strong effect */
        background-color: #6F481C; /* Walnut on hover */
        transform: translateY(-2px); /* Slight lift effect */
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .back-btn {
        display: block;
        margin-top: 25px; /* More space above back button */
        text-align: center;
        /* Rhino for the back button link, a supportive blue */
        color: #273F5B;
        text-decoration: none;
        font-weight: 600;
        font-size: 16px;
        transition: color 0.3s ease;
    }

    .back-btn:hover {
        text-decoration: underline;
        /* Tan on hover for the back button */
        color: #D5B893;
    }

    /* Responsive adjustments */
    @media (max-width: 600px) {
        .container {
            margin: 20px; /* Smaller margin on smaller screens */
            padding: 25px; /* Reduced padding */
        }

        h2 {
            font-size: 28px; /* Smaller heading */
            margin-bottom: 25px;
        }

        input, select {
            padding: 10px; /* Slightly less padding */
            font-size: 15px;
        }

        button {
            padding: 12px;
            font-size: 16px;
        }

        label {
            font-size: 15px;
        }
    }
</style>
</head>
<body>

<div class="container">
    <h2>Add New Employee</h2>
    <form method="post">
        <label for="First_names">First Names</label> <input type="text" name="First_names" required>

        <label for="Last_name">Last Name</label> <input type="text" name="Last_name" required>

        <label for="designation">Designation</label>
        <input type="text" name="designation" required>

        <label for="Personal_number">Personal No</label> <input type="text" name="Personal_number" required>

        <label for="email">Email</label> <input type="email" name="email" required>

       <label for="department">Department:</label>
<select name="department" required>
    <option value="">Select Department</option>
    <?php
    $dept_query = $conn->query("SELECT * FROM departments ORDER BY name ASC");
    while ($row = $dept_query->fetch_assoc()):
    ?>
        <option value="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></option>
    <?php endwhile; ?>
</select>

        <button type="submit">Add Employee</button>
        <a href="Staff.php" class="back-btn">← Back to Employee Dashboard</a>
    </form>
</div>

</body>
</html>