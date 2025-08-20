<?php
session_start();
include('includes/db_connect.php');

$token = $_GET['token'] ?? '';
$user_type = $_GET['user_type'] ?? '';
$error_message = '';
$success_message = '';

if (!$token || !$user_type || !in_array($user_type, ['student', 'institution'])) {
    die('Invalid password reset request.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Check token validity
        $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND user_type = ?");
        $stmt->bind_param("ss", $token, $user_type);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (strtotime($row['expires_at']) < time()) {
                $error_message = "Token expired. Please request a new password reset.";
            } else {
                $email = $row['email'];
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password in user table
                $table = ($user_type === 'student') ? 'students' : 'institutions';
                $update_stmt = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $hashed_password, $email);
                if ($update_stmt->execute()) {
                    // Delete token after successful reset
                    $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                    $del_stmt->bind_param("s", $token);
                    $del_stmt->execute();

                    $success_message = "Password reset successful! You can now login with your new password.";
                } else {
                    $error_message = "Failed to update password. Please try again.";
                }
            }
        } else {
            $error_message = "Invalid token. Please request a new password reset.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | ProjMate</title>
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

        .reset-container {
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
            display: flex;
            align-items: center;
        }

        .input-container .fa-lock {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 1.1rem;
            transition: color 0.3s ease;
            z-index: 2;
        }

        /* Fixed CSS - Handle both password and text input types */
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 16px 48px 16px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 400;
            background: #f8fafc;
            transition: all 0.3s ease;
            outline: none;
        }

        input[type="password"]:focus, input[type="text"]:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        input[type="password"]:focus ~ .fa-lock, input[type="text"]:focus ~ .fa-lock {
            color: #667eea;
        }

        .password-strength {
            margin-top: 8px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #64748b;
            display: none;
        }

        .password-strength.show {
            display: block;
        }

        .strength-indicator {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin: 8px 0 6px 0;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }

        .reset-btn {
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

        .reset-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .reset-btn:hover::before {
            left: 100%;
        }

        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .reset-btn:active {
            transform: translateY(0);
        }

        .reset-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

        .success-animation {
            text-align: center;
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(0.9); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 480px) {
            .reset-container {
                padding: 30px 24px;
                margin: 0 16px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }

            .reset-header h2 {
                font-size: 1.5rem;
            }
        }

        /* Loading state */
        .reset-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .reset-btn.loading::after {
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

        /* Enhanced toggle password styling */
        .toggle-password {
            position: absolute;
            right: 16px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 2;
            user-select: none;
            font-size: 1rem;
            padding: 4px;
        }

        .toggle-password:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <h1>ProjMate</h1>
            <p>Password Reset</p>
        </div>

        <?php if (!$success_message): ?>
            <div class="instruction-box">
                <i class="fas fa-shield-alt"></i>
                <h3>Create New Password</h3>
                <p>Please enter a strong password that you haven't used before on this account.</p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="message success success-animation">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php else: ?>
        <form method="POST" onsubmit="return handleSubmit()">
            <div class="form-group">
                <label for="new_password" class="form-label">New Password:</label>
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="new_password" id="new_password" placeholder="Enter your new password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password', this)" title="Show password"></i>
                </div>
                <div class="password-strength" id="strength-indicator">
                    <div class="strength-indicator">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>
                    <span id="strength-text">Password strength</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password:</label>
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your new password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)" title="Show password"></i>
                </div>
            </div>

            <button type="submit" class="reset-btn" id="resetBtn">
                <i class="fas fa-key"></i>
                Reset Password
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
            const resetBtn = document.getElementById('resetBtn');
            if (resetBtn) {
                resetBtn.classList.add('loading');
                resetBtn.innerHTML = '';
            }
            return true;
        }

        function togglePassword(inputId, toggleIcon) {
            const input = document.getElementById(inputId);
            const currentType = input.getAttribute('type');
            const newType = currentType === 'password' ? 'text' : 'password';
            
            input.setAttribute('type', newType);
            
            // Update toggle icon and title
            if (newType === 'text') {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                toggleIcon.setAttribute('title', 'Hide password');
            } else {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                toggleIcon.setAttribute('title', 'Show password');
            }
        }

        function checkPasswordStrength(password) {
            let strength = 0;
            let text = 'Weak';
            let className = 'strength-weak';

            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            if (strength >= 4) {
                text = 'Strong';
                className = 'strength-strong';
            } else if (strength >= 2) {
                text = 'Medium';
                className = 'strength-medium';
            }

            return { strength, text, className };
        }

        // Event delegation for better handling of dynamic input types
        document.addEventListener('input', function(e) {
            if (e.target.matches('#new_password')) {
                const password = e.target.value;
                const indicator = document.getElementById('strength-indicator');
                const bar = document.getElementById('strength-bar');
                const text = document.getElementById('strength-text');

                if (password.length > 0) {
                    indicator.classList.add('show');
                    const result = checkPasswordStrength(password);
                    
                    bar.className = 'strength-bar ' + result.className;
                    text.textContent = result.text + ' password';
                } else {
                    indicator.classList.remove('show');
                }
            }

            if (e.target.matches('#confirm_password')) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = e.target.value;
                const resetBtn = document.getElementById('resetBtn');

                if (confirmPassword && newPassword !== confirmPassword) {
                    e.target.style.borderColor = '#ef4444';
                    resetBtn.disabled = true;
                } else {
                    e.target.style.borderColor = '#e2e8f0';
                    resetBtn.disabled = false;
                }
            }
        });

        // Focus/blur events with event delegation
        document.addEventListener('focus', function(e) {
            if (e.target.matches('#new_password, #confirm_password')) {
                const lockIcon = e.target.parentElement.querySelector('.fa-lock');
                if (lockIcon) {
                    lockIcon.style.color = '#667eea';
                }
            }
        }, true);

        document.addEventListener('blur', function(e) {
            if (e.target.matches('#new_password, #confirm_password')) {
                if (!e.target.value) {
                    const lockIcon = e.target.parentElement.querySelector('.fa-lock');
                    if (lockIcon) {
                        lockIcon.style.color = '#94a3b8';
                    }
                }
            }
        }, true);

        // Auto-redirect after successful reset
        <?php if ($success_message): ?>
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
