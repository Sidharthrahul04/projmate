<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include('includes/db_connect.php');

$message = '';
$messageClass = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $user_type = $_POST['user_type'];

    $table = ($user_type === "student") ? "students" : "institutions";
    $query = "SELECT * FROM $table WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Remove existing tokens
        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
        }

        // Save new token
        $insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, user_type) VALUES (?, ?, ?, ?)");
        $insert->bind_param("ssss", $email, $token, $expiry, $user_type);
        $insert->execute();

        $reset_link = "http://localhost:5050/projmate/reset_password.php?token=$token&user_type=$user_type";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'projmate@gmail.com';
            $mail->Password   = 'qoeomijbbkkjvxjx';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('projmate@gmail.com', 'ProjMate');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Reset your password - ProjMate';
            $mail->Body    = "
                <div style='font-family: Inter, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;'>
                        <h1 style='color: white; margin: 0; font-size: 2rem; font-weight: 700;'>ProjMate</h1>
                        <p style='color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 1rem;'>Password Reset Request</p>
                    </div>
                    <div style='padding: 40px 30px;'>
                        <h2 style='color: #1e293b; margin: 0 0 16px 0; font-size: 1.5rem; font-weight: 600;'>Reset Your Password</h2>
                        <p style='color: #64748b; margin: 0 0 24px 0; line-height: 1.6;'>We received a request to reset your password. Click the button below to create a new password:</p>
                        <div style='text-align: center; margin: 32px 0;'>
                            <a href='$reset_link' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; padding: 16px 32px; border-radius: 8px; font-weight: 600; font-size: 1rem;'>Reset Password</a>
                        </div>
                        <p style='color: #94a3b8; font-size: 0.875rem; margin: 24px 0 0 0;'>If you didn't request this reset, you can safely ignore this email. This link will expire in 1 hour.</p>
                        <hr style='border: none; height: 1px; background: #e2e8f0; margin: 24px 0;'>
                        <p style='color: #94a3b8; font-size: 0.8rem; margin: 0;'>Or copy and paste this link: <span style='color: #667eea; word-break: break-all;'>$reset_link</span></p>
                    </div>
                </div>
            ";

            $mail->send();
            $message = "Password reset link has been sent to your email address. Please check your inbox and follow the instructions.";
            $messageClass = "success";
        } catch (Exception $e) {
            $message = "Failed to send reset email. Please try again later.";
            $messageClass = "error";
        }
    } else {
        $message = "No account found with this email address.";
        $messageClass = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | ProjMate</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .logo p {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .reset-header h2 {
            color: #1e293b;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .reset-header p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .message.success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-label {
            display: block;
            color: #374151;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-container {
            position: relative;
        }

        .input-container i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        input[type="email"],
        select {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 400;
            background: #f8fafc;
            transition: all 0.3s ease;
            outline: none;
            cursor: pointer;
        }

        input[type="email"]:focus,
        select:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        input[type="email"]:focus + i,
        select:focus + i {
            color: #667eea;
        }

        .user-type-options {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .user-type-option {
            flex: 1;
            position: relative;
        }

        .user-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .user-type-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .user-type-option input[type="radio"]:checked + .user-type-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .send-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .send-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .send-btn:hover::before {
            left: 100%;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .send-btn:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #764ba2;
        }

        .instruction-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: center;
        }

        .instruction-box i {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 12px;
        }

        .instruction-box h3 {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .instruction-box p {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        @media (max-width: 480px) {
            .forgot-container {
                padding: 30px 24px;
                margin: 0 16px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }

            .reset-header h2 {
                font-size: 1.5rem;
            }

            .user-type-options {
                flex-direction: column;
                gap: 8px;
            }
        }

        /* Loading state */
        .send-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .send-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Success animation */
        .success-animation {
            text-align: center;
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(0.9); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <h1>ProjMate</h1>
            <p>Password Recovery</p>
        </div>

        <?php if (empty($message)): ?>
            <div class="instruction-box">
                <i class="fas fa-key"></i>
                <h3>Forgot Your Password?</h3>
                <p>Don't worry! Enter your email address and user type below, and we'll send you a link to reset your password.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageClass; ?> <?php echo $messageClass === 'success' ? 'success-animation' : ''; ?>">
                <i class="fas <?php echo $messageClass === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($messageClass !== 'success'): ?>
        <form method="POST" onsubmit="return handleSubmit()">
            <div class="form-group">
                <label class="form-label">I am a:</label>
                <div class="user-type-options">
                    <div class="user-type-option">
                        <input type="radio" id="student" name="user_type" value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'checked' : ''; ?> required>
                        <label for="student" class="user-type-label">
                            <i class="fas fa-user-graduate"></i>
                            Student
                        </label>
                    </div>
                    <div class="user-type-option">
                        <input type="radio" id="institution" name="user_type" value="institution" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'institution') ? 'checked' : ''; ?> required>
                        <label for="institution" class="user-type-label">
                            <i class="fas fa-university"></i>
                            Institution
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address:</label>
                <div class="input-container">
                    <input type="email" name="email" id="email" placeholder="Enter your registered email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    <i class="fas fa-envelope"></i>
                </div>
            </div>

            <button type="submit" class="send-btn" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
                Send Reset Link
            </button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>
        </div>
    </div>

    <script>
        function handleSubmit() {
            const sendBtn = document.getElementById('sendBtn');
            if (sendBtn) {
                sendBtn.classList.add('loading');
                sendBtn.innerHTML = '';
            }
            return true;
        }

        // Auto-redirect after successful message
        <?php if ($messageClass === 'success'): ?>
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 5000);
        <?php endif; ?>

        // Add hover effects to radio buttons
        document.querySelectorAll('.user-type-label').forEach(label => {
            label.addEventListener('mouseenter', function() {
                if (!this.previousElementSibling.checked) {
                    this.style.borderColor = '#667eea';
                    this.style.background = '#f0f9ff';
                }
            });

            label.addEventListener('mouseleave', function() {
                if (!this.previousElementSibling.checked) {
                    this.style.borderColor = '#e2e8f0';
                    this.style.background = '#f8fafc';
                }
            });
        });

        // Email input focus effects
        document.getElementById('email').addEventListener('focus', function() {
            this.parentElement.querySelector('i').style.color = '#667eea';
        });

        document.getElementById('email').addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.querySelector('i').style.color = '#94a3b8';
            }
        });
    </script>
</body>
</html>