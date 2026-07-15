<?php
include 'connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT id FROM register WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $token = bin2hex(random_bytes(50));
            $update = $conn->prepare("UPDATE register SET reset_token = ? WHERE email = ?");
            $update->bind_param("ss", $token, $email);
            $update->execute();

            $reset_link = "http://localhost/asals_project/reset_password.php?token=$token";
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'loisewaititu@gmail.com';
                $mail->Password = 'siuhqietwgpzqhdy'; 
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('loisewaititu@gmail.com', 'J.L Track System');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password';
                $mail->Body = "Click this link to reset your password: <a href='$reset_link'>Reset Password</a>";

                if ($mail->send()) {
    $message = "✅ Email sent!";
} else {
    $message = "❌ Error: " . $mail->ErrorInfo;
}

            } catch (Exception $e) {
                $message = "Failed to send email. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $message = "No account found with that email.";
        }
        $stmt->close();
    } else {
        $message = "Invalid email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        /* Consistent gradient background from Akaroa to Tan */
        background: linear-gradient(to right, #D2BCA1, #D5B893); /* Akaroa, Tan */
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
        margin: 0;
        /* Consistent body text color: Coffee */
        color: #6F4D38; /* Coffee */
    }

    .container {
        background-color: white; /* Clean white background */
        padding: 40px 30px;
        border-radius: 12px; /* More rounded corners */
        /* Richer shadow using Walnut */
        box-shadow: 0 0 15px rgba(111, 72, 28, 0.25); /* Walnut shadow */
        max-width: 450px; /* Slightly adjusted max-width */
        width: 100%;
        text-align: center;
        box-sizing: border-box; /* Include padding in total width */
    }

    h2 {
        /* Consistent heading color: Space Cadet */
        color: #25344F; /* Space Cadet */
        margin-bottom: 25px; /* More spacing below heading */
        font-size: 32px; /* Consistent heading font size */
    }

    input[type="email"] {
        width: calc(100% - 24px); /* Account for padding */
        padding: 12px;
        margin-bottom: 20px;
        /* Border using Slate Gray */
        border: 1px solid #617891; /* Slate Gray */
        border-radius: 8px; /* More rounded input fields */
        font-size: 16px;
        /* Input text color: Space Cadet */
        color: #25344F; /* Space Cadet */
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        box-sizing: border-box; /* Crucial for width: 100% with padding */
    }

    input[type="email"]:focus {
        outline: none;
        /* Focus highlight using Desert accent */
        border-color: #A76825; /* Desert */
        box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2); /* Desert shadow for focus */
    }

    button {
        /* Consistent button background: Desert */
        background-color: #A76825; /* Desert */
        color: white;
        border: none;
        padding: 14px 25px; /* More generous padding */
        font-size: 17px; /* Larger font size */
        border-radius: 8px; /* More rounded button */
        cursor: pointer;
        font-weight: bold; /* Make button text bold */
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        display: block; /* Make button take full width to center easier */
        width: 100%;
        max-width: 250px; /* Max width for button */
        margin: 0 auto; /* Center the button */
    }

    button:hover {
        /* Consistent button hover: Walnut */
        background-color: #6F481C; /* Walnut */
        transform: translateY(-2px); /* Slight lift effect */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); /* Deeper shadow on hover */
    }

    .message {
        margin-bottom: 15px;
        /* Using Coffee for a success message that fits the palette */
        color: #6F4D38; /* Coffee */
        font-weight: bold;
        font-size: 15px;
    }

    .error {
        margin-bottom: 15px;
        /* Consistent error color: Caput Mortuum */
        color: #632024; /* Caput Mortuum */
        font-weight: bold;
        font-size: 15px;
    }

    /* Responsive adjustments */
    @media screen and (max-width: 480px) {
        body {
            padding: 15px;
        }
        .container {
            padding: 25px 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(111, 72, 28, 0.15); /* Lighter shadow on small screens */
        }
        h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        input[type="email"] {
            padding: 10px;
            margin-bottom: 15px;
            font-size: 15px;
        }
        button {
            padding: 12px 20px;
            font-size: 16px;
            max-width: 100%; /* Button takes full width on small screens */
        }
        .message, .error {
            font-size: 14px;
        }
    }
</style>
</head>
<body>
  <div class="container">
    <h2>Forgot Password</h2>

    <?php if (!empty($message)): ?>
      <div class="<?= strpos($message, 'sent') !== false ? 'message' : 'error' ?>">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <input type="email" name="email" placeholder="Enter your email" required>
      <button type="submit">Send Reset Link</button>
    </form>
     <a href="loginpage.php" style="
      display: inline-block;
      margin-top: 20px;
      padding: 10px 18px;
      background-color: #8e44ad;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      transition: background-color 0.3s ease;
    " onmouseover="this.style.backgroundColor='#732d91'" onmouseout="this.style.backgroundColor='#8e44ad'">
      ← Back to Login
    </a>
  </div>
</body>
</html>
