<?php
// Start session only if one isn't already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include('includes/db_connect.php');

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Check students table
        $stmt = $conn->prepare("SELECT id, name, password FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $name, $hashedPassword);
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_type'] = 'student';
                header("Location: student/student_dashboard.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            // Check institutions table
            $stmt->close();
            $stmt = $conn->prepare("SELECT id, institution_name, password FROM institutions WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $institution_name, $hashedPassword);
                $stmt->fetch();

                if (password_verify($password, $hashedPassword)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['user_name'] = $institution_name;
                    $_SESSION['user_type'] = 'institution';
                    header("Location: institution/institution_dashboard.php");
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "Email not found.";
            }
        }

        $stmt->close();
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <base href="http://localhost:5050/projmate/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ProjMate</title>
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
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
            margin-bottom: 40px;
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

        .form-group {
            margin-bottom: 24px;
            position: relative;
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
        input[type="password"] {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 400;
            background: #f8fafc;
            transition: all 0.3s ease;
            outline: none;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        input[type="email"]:focus + i,
        input[type="password"]:focus + i {
            color: #667eea;
        }

        input.error {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .error-msg {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 8px;
            font-weight: 500;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .error-msg:not(:empty) {
            opacity: 1;
            transform: translateY(0);
        }

        .server-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            text-align: center;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-btn {
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

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .links-container {
            text-align: center;
            margin-top: 24px;
        }

        .forgot-password {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 16px;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: #764ba2;
        }

        .register-link {
            color: #64748b;
            font-size: 0.95rem;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #764ba2;
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 16px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 24px;
                margin: 0 16px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
        }

        /* Loading state */
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .login-btn.loading::after {
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ProjMate</h1>
            <p>Welcome back! Please sign in to continue</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="server-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return validateLogin()">
            <div class="form-group">
                <div class="input-container">
                    <input type="email" name="email" id="email" placeholder="Enter your email">
                    <i class="fas fa-envelope"></i>
                </div>
                <div id="emailError" class="error-msg"></div>
            </div>

            <div class="form-group">
                <div class="input-container">
                    <input type="password" name="password" id="password" placeholder="Enter your password">
                    <i class="fas fa-lock"></i>
                </div>
                <div id="passwordError" class="error-msg"></div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                Sign In
            </button>

            <div class="links-container">
                <a href="forgot_password.php" class="forgot-password">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
                
                <div class="divider">
                    <span>or</span>
                </div>
                
                <div class="register-link">
                    New to ProjMate? <a href="register.php">Create an account</a>
                </div>
            </div>
        </form>
    </div>
        
    <script>
        function validateLogin() {
            let valid = true;
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            
            // Clear previous errors
            document.getElementById('emailError').textContent = '';
            document.getElementById('passwordError').textContent = '';
            email.classList.remove('error');
            password.classList.remove('error');

            // Email validation
            if (email.value.trim() === '') {
                document.getElementById('emailError').textContent = 'Please enter your email address';
                email.classList.add('error');
                valid = false;
            } else if (!isValidEmail(email.value.trim())) {
                document.getElementById('emailError').textContent = 'Please enter a valid email address';
                email.classList.add('error');
                valid = false;
            }

            // Password validation
            if (password.value.trim() === '') {
                document.getElementById('passwordError').textContent = 'Please enter your password';
                password.classList.add('error');
                valid = false;
            }

            // Add loading state if valid
            if (valid) {
                loginBtn.classList.add('loading');
                loginBtn.textContent = '';
            }

            return valid;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Add input event listeners for real-time validation
        document.getElementById('email').addEventListener('input', function() {
            if (this.classList.contains('error')) {
                this.classList.remove('error');
                document.getElementById('emailError').textContent = '';
            }
        });

        document.getElementById('password').addEventListener('input', function() {
            if (this.classList.contains('error')) {
                this.classList.remove('error');
                document.getElementById('passwordError').textContent = '';
            }
        });

        // Add enter key support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>