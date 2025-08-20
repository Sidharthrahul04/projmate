<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

include('includes/db_connect.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $user_type = $_POST['user_type'];

    $table = ($user_type === "student") ? "students" : "institutions";

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        // Generate token & expiry
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Delete any existing tokens for this email
        $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del_stmt->bind_param("s", $email);
        $del_stmt->execute();

        // Insert new token in password_resets table
        $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, user_type, created_at, expires_at) VALUES (?, ?, ?, NOW(), ?)");
        $insert_stmt->bind_param("ssss", $email, $token, $user_type, $expiry);
        $insert_stmt->execute();

        // Prepare reset link
        $reset_link = "http://localhost:5050/projmate/reset_password.php?token=$token&user_type=$user_type";

        // Send email via PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'sandbox.smtp.mailtrap.io'; // Your SMTP host
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dcf462d58aabc8'; // Your SMTP username
            $mail->Password   = 'dcc3e718e40dbc'; // Your SMTP password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 2525;

            $mail->setFrom('noreply@projmate.com', 'ProjMate Support');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body    = "Click the link below to reset your password:<br><a href='$reset_link'>$reset_link</a><br>This link will expire in 1 hour.";

            $mail->send();
            echo "Password reset link sent!";
        } catch (Exception $e) {
            echo "Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        echo "Email not found!";
    }

    $stmt->close();
}
?>
