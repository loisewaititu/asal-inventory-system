<?php
session_start(); // ✅ Only called once, at the top

require_once 'connect.php';
require_once 'includes/audit_logger.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$success = '';
$error = '';

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// Determine dashboard URL based on role
$dashboard_url = 'Dashboard.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_url = 'admin_dashboard.php';
}

// Get super admin email
$superAdminEmail = '';
$check = $conn->query("SELECT email FROM admins WHERE role = 'super_admin' LIMIT 1");
if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    $superAdminEmail = $row['email'];
} else {
    $superAdminEmail = 'luluyt2254@gmail.com'; // fallback if query fails
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST["name"]));
    $email = htmlspecialchars(trim($_POST["email"]));
    $phone = htmlspecialchars(trim($_POST["phone"]));
    $messageText = htmlspecialchars(trim($_POST["message"]));

    if (!empty($name) && !empty($email) && !empty($phone) && !empty($messageText) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Save to database
        $sql = "INSERT INTO contact (name, email, phone, message, submitted_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $phone, $messageText);
        
        if ($stmt->execute()) {
            // Send email to super admin
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'loisewaititu@gmail.com'; // Your Gmail
                $mail->Password = 'siuhqietwgpzqhdy';      // <-- App Password pasted with no spaces
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;


                $mail->setFrom('loisewaititu@gmail.com', 'ASALs ICT System');
                $mail->addAddress($superAdminEmail);
                $mail->isHTML(true);
                $mail->Subject = "New Contact Form Message from $name";
                $mail->Body = "
                    <strong>Name:</strong> $name<br>
                    <strong>Email:</strong> $email<br>
                    <strong>Phone:</strong> $phone<br><br>
                    <strong>Message:</strong><br>$messageText
                ";

                $mail->send();
                $success = "Thank you for contacting us, $name. Your message has been sent and saved.";
            } catch (Exception $e) {
                $error = "Message saved, but email could not be sent. Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Failed to save your message. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Contact Us - ASALs & Regional Dev</title>
    <link rel="stylesheet" href="contact2.css" />
</head>
<body>
    <div class="container">
        <h1>Contact Us</h1>
        <p>Please fill in your details and message below:</p>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="name" placeholder="Your Name" required />
            <input type="email" name="email" placeholder="Your Email" required />
            <input type="tel" name="phone" placeholder="Your Phone Number" required />
            <textarea name="message" placeholder="Your Message" rows="5" required></textarea>
            <button type="submit">Send Message</button>
        </form>

        <div class="contact-info">
            <h2>Our Contact Information</h2>
            <p><strong>Address:</strong> Hazina Trade Centre, 10th floor, ICT Department, Nairobi, Kenya</p>
            <p><strong>Phone:</strong> +254-704-625-250</p>
            <p><strong>Email:</strong> <a href="ICT@asals.go.ke">ICT@asals.go.ke</a></p>
            <p><strong>Website:</strong> <a href="https://www.asalrd.go.ke" target="_blank" rel="noopener noreferrer">www.asalrd.go.ke</a></p>
        </div>
    </div>

    <div style="text-align: center; margin: 40px 0;">
        <a href="<?= htmlspecialchars($dashboard_url) ?>" style="
            display: inline-block;
            background-color:rgb(36, 12, 12);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        ">
            ← Back to Dashboard
        </a>
    </div>
</body>
</html>
