<?php
session_start();

require_once 'connect.php'; // your DB connection file\
require_once 'includes/audit_logger.php'; // audit log helper

// For optional timeout message
$timeoutMessage = "";
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $timeoutMessage = "Session expired. Please login again.";
}

// Database connection
$conn = new mysqli("localhost", "root", "", "jl_tracking_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle login
$loginError = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM register WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['admin_id'] = $user['id']; // Store admin ID
            $_SESSION['admin_username'] = $user['First_Names'] ?? $user['email']; // Store admin name
            $_SESSION['is_super_admin'] = $user['is_super_admin'];
            $_SESSION['last_activity'] = time();

            // ✅ Log the successful login
            log_activity(
                $user['id'],
                $_SESSION['admin_username'],
                'ADMIN_LOGIN',
                'Admin logged in.',
                'admin',
                $user['id']
            );

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
                exit;
            } elseif ($user['role'] === 'user') {
                header("Location: Dashboard.php");
                exit;
            } else {
                $loginError = "Unknown role.";
            }
        } else {
            $loginError = "Incorrect password.";
        }
    } else {
        $loginError = "No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JL Tracking System - State Department of ASALs and Regional Development</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

  <img src="logo.jpeg" alt="Government Logo" style="width: 10%; display: block; margin: 0 auto;">

<h2 style="text-align: center; margin-bottom: 5px;">REPUBLIC OF KENYA</h2>
<h2 style="text-align: center; margin-bottom: 5px;">MINISTRY OF EAST AFRICAN COMMUNITY, THE ASALS AND REGIONAL DEVELOPMENT</h3>

<h2 style="text-align: center; margin-bottom: 5px;">STATE DEPARTMENT OF ASALS AND REGIONAL DEVELOPMENT</h1>

<p class="subtitle" style="text-align: center; font-weight: bold;">
  JL TRACKING SYSTEM
</p>

    <div class="login-form">
        <form method="POST" action="loginpage.php">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>

            <button type="submit" name="login">Login</button>

            <?php if (!empty($loginError)): ?>
                <p class="message error-message"><?= htmlspecialchars($loginError) ?></p>
            <?php endif; ?>

            <?php if (!empty($timeoutMessage)): ?>
                <p class="message info-message"><?= htmlspecialchars($timeoutMessage) ?></p>
            <?php endif; ?>
        </form>

        <p>Don't have an account? <a href="register as.php"><strong>Register Here</strong></a></p>
        <p><a href="forgot_password.php">Forgot Password?</a></p>
    </div>

</body>
</html>



