<?php
include 'connect.php';

$token = $_GET['token'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE register SET password = ?, reset_token = NULL WHERE reset_token = ?");
        $stmt->bind_param("ss", $hashed, $token);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "✅ Password reset successful! <a href='loginpage.php'>Login now</a>";
        } else {
            $message = "⛔ Invalid or expired token.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="reset.css">
</head>
<body>
  <div class="container">
    <h2>Reset Your Password</h2>
    <?php if ($message): ?><p><?= $message ?></p><?php endif; ?>
    <form method="POST">
        <input type="password" name="password" placeholder="New password" required><br>
        <input type="password" name="confirm_password" placeholder="Confirm password" required><br>
        <button type="submit">Reset Password</button>
    </form>
  </div>
</body>

</html>
