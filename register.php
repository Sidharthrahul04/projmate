<?php
// Start session only if one isn't already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include('includes/db_connect.php');

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['user_type'] === 'student' ? 'student_dashboard.php' : 'institution_dashboard.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_type = $_POST["user_type"];

    if ($user_type === "student") {
        $name = $_POST["student_name"];
        $email = trim($_POST["student_email"]);
        $phone = $_POST["student_phone"];
        $password = $_POST["student_password"];

    } elseif ($user_type === "institution") {
        $institution_name = $_POST["institution_name"];
        $email = trim($_POST["institution_email"]);
        $phone = ''; // Not collecting phone separately for institutions
        $password = $_POST["institution_password"];
    }

    // Email format validation (server-side)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($user_type == "student") {
            // Check for duplicate email
            $check = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = 'Email already registered as student.';
            } else {
                $check->close();

                // Validate resume
                $resume_name = time() . '_' . basename($_FILES["resume"]["name"]);
                $resume_path = "uploads/" . $resume_name;
                $file_ext = strtolower(pathinfo($resume_name, PATHINFO_EXTENSION));
                $allowed_types = ['pdf', 'doc', 'docx'];

                if (!in_array($file_ext, $allowed_types)) {
                    $error = 'Invalid resume file type. Only PDF, DOC, DOCX allowed.';
                } else {
                    if (move_uploaded_file($_FILES["resume"]["tmp_name"], $resume_path)) {
                        $stmt = $conn->prepare("INSERT INTO students (name, email, phone, resume_path, password) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $name, $email, $phone, $resume_name, $hashed_password);
                        if ($stmt->execute()) {
                            $success = 'Student registration successful!';
                        } else {
                            $error = 'Database error. Please try again.';
                        }
                        $stmt->close();
                    } else {
                        $error = 'Failed to upload resume.';
                    }
                }
            }

        } elseif ($user_type == "institution") {
            // Check for duplicate email
            $check = $conn->prepare("SELECT id FROM institutions WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = 'Email already registered as institution.';
            } else {
                $check->close();

                $stmt = $conn->prepare("INSERT INTO institutions (institution_name, email, phone, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $institution_name, $email, $phone, $hashed_password);
                if ($stmt->execute()) {
                    $success = 'Institution registration successful!';
                } else {
                    $error = 'Database error. Please try again.';
                }
                $stmt->close();
            }
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <base href="http://localhost:5050/projmate/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | ProjMate</title>
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
            overflow-x: hidden;
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

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
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

        .user-type-selector {
            margin-bottom: 28px;
        }

        .user-type-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 4px;
            position: relative;
        }

        .user-type-tab {
            flex: 1;
            padding: 12px 16px;
            text-align: center;
            background: none;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .user-type-tab.active {
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .form-group {
            margin-bottom: 20px;
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
            z-index: 2;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 400;
            background: #f8fafc;
            transition: all 0.3s ease;
            outline: none;
        }

        select {
            cursor: pointer;
        }

        input[type="file"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: all 0.3s ease;
            outline: none;
            cursor: pointer;
        }

        input[type="file"]:hover {
            border-color: #667eea;
            background: white;
        }

        input:focus,
        select:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        input:focus + i,
        select:focus + i {
            color: #667eea;
        }

        input.error,
        select.error {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .error-msg {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 6px;
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
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            animation: shake 0.5s ease-in-out;
        }

        .server-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .form-fields {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .form-fields.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .register-btn {
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
            margin-top: 8px;
        }

        .register-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .register-btn:hover::before {
            left: 100%;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 24px;
            color: #64748b;
            font-size: 0.95rem;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #764ba2;
        }

        .file-upload-area {
            position: relative;
            text-align: center;
            padding: 24px 16px;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #667eea;
            background: white;
        }

        .file-upload-area.dragover {
            border-color: #667eea;
            background: #f0f9ff;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .file-upload-text {
            color: #64748b;
            font-size: 0.9rem;
        }

        .file-upload-subtext {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        .file-selected {
            color: #059669;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        @media (max-width: 580px) {
            .register-container {
                padding: 30px 24px;
                margin: 0 16px;
                max-height: 95vh;
            }
            
            .logo h1 {
                font-size: 2rem;
            }

            .user-type-tab {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
        }

        /* Loading state */
        .register-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .register-btn.loading::after {
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

        /* Custom scrollbar */
        .register-container::-webkit-scrollbar {
            width: 6px;
        }

        .register-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .register-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .register-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>ProjMate</h1>
            <p>Create your account to get started</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="server-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="server-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
                <script>
                    setTimeout(() => window.location.href = 'login.php', 2000);
                </script>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateRegister()">
            <div class="user-type-selector">
                <div class="user-type-tabs">
                    <!-- <button type="button" class="user-type-tab" data-type="" onclick="selectUserType('')">
                        <i class="fas fa-user-circle"></i> Select Type
                    </button> -->
                    <button type="button" class="user-type-tab" data-type="student" onclick="selectUserType('student')">
                        <i class="fas fa-user-graduate"></i> Student
                    </button>
                    <button type="button" class="user-type-tab" data-type="institution" onclick="selectUserType('institution')">
                        <i class="fas fa-university"></i> Institution
                    </button>
                </div>
                <input type="hidden" name="user_type" id="user_type" value="<?= isset($_POST['user_type']) ? htmlspecialchars($_POST['user_type']) : '' ?>">
                <div id="usertypeError" class="error-msg"></div>
            </div>

            <!-- Student Fields -->
            <div id="studentFields" class="form-fields <?= (isset($_POST['user_type']) && $_POST['user_type'] == 'student') ? 'active' : '' ?>">
                <div class="form-group">
                    <div class="input-container">
                        <input type="text" name="student_name" id="student_name" placeholder="Your Full Name" value="<?= isset($_POST['student_name']) ? htmlspecialchars($_POST['student_name']) : '' ?>">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="error-msg" id="studentNameError"></div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="email" name="student_email" id="student_email" placeholder="Email Address" value="<?= isset($_POST['student_email']) ? htmlspecialchars($_POST['student_email']) : '' ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="error-msg" id="studentEmailError"></div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="text" name="student_phone" id="student_phone" placeholder="Phone Number" value="<?= isset($_POST['student_phone']) ? htmlspecialchars($_POST['student_phone']) : '' ?>">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="error-msg" id="studentPhoneError"></div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="password" name="student_password" id="student_password" placeholder="Create Password">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="error-msg" id="studentPasswordError"></div>
                </div>

                <div class="form-group">
                    <div class="file-upload-area" onclick="document.getElementById('resume').click()">
                        <div class="file-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="file-upload-text" id="file-upload-text">
                            Click to upload your resume
                        </div>
                        <div class="file-upload-subtext">
                            PDF, DOC, DOCX (Max 5MB)
                        </div>
                    </div>
                    <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" style="display: none;">
                    <div class="error-msg" id="resumeError"></div>
                </div>
            </div>

            <!-- Institution Fields -->
            <div id="institutionFields" class="form-fields <?= (isset($_POST['user_type']) && $_POST['user_type'] == 'institution') ? 'active' : '' ?>">
                <div class="form-group">
                    <div class="input-container">
                        <select name="institution_name" id="institution_name">
                            <option value="">Select Your Institution</option>
                            <?php
                            $institutions = [
                                "RIT Kottayam", "MACE Kothamangalam", "GEC Thrissur", "Muthoot Institute",
                                "CUSAT", "College of Engineering Trivandrum", "GEC Kozhikode", "GEC Idukki",
                                "GEC Palakkad", "TKM College Kollam", "FISAT"
                            ];
                            foreach ($institutions as $inst) {
                                $selected = (isset($_POST['institution_name']) && $_POST['institution_name'] == $inst) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($inst) . "\" $selected>" . htmlspecialchars($inst) . "</option>";
                            }
                            ?>
                        </select>
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="error-msg" id="institutionNameError"></div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="email" name="institution_email" id="institution_email" placeholder="Official Email Address" value="<?= isset($_POST['institution_email']) ? htmlspecialchars($_POST['institution_email']) : '' ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="error-msg" id="institutionEmailError"></div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <input type="password" name="institution_password" id="institution_password" placeholder="Create Password">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="error-msg" id="institutionPasswordError"></div>
                </div>
            </div>

            <button type="submit" class="register-btn" id="registerBtn">
                Create Account
            </button>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </form>
    </div>
    <script>
        let currentUserType = '<?= isset($_POST['user_type']) ? $_POST['user_type'] : '' ?>';

        function selectUserType(type) {
            currentUserType = type;
            document.getElementById('user_type').value = type;
            
            // Update tab states
            document.querySelectorAll('.user-type-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.type === type) {
                    tab.classList.add('active');
                }
            });

            // Show/hide form fields
            document.getElementById('studentFields').classList.remove('active');
            document.getElementById('institutionFields').classList.remove('active');
            
            if (type === 'student') {
                document.getElementById('studentFields').classList.add('active');
            } else if (type === 'institution') {
                document.getElementById('institutionFields').classList.add('active');
            }

            // Clear errors
            clearErrors();
        }

        function clearErrors() {
            document.querySelectorAll('.error-msg').forEach(e => e.textContent = '');
            document.querySelectorAll('input, select').forEach(e => e.classList.remove('error'));
        }

        function validateRegister() {
            let valid = true;
            clearErrors();

            const registerBtn = document.getElementById('registerBtn');

            if (!currentUserType) {
                document.getElementById('usertypeError').textContent = 'Please select whether you are a student or institution';
                valid = false;
            }

            if (currentUserType === 'student') {
                const name = document.getElementById('student_name');
                const email = document.getElementById('student_email');
                const phone = document.getElementById('student_phone');
                const password = document.getElementById('student_password');
                const resume = document.getElementById('resume');

                if (!name.value.trim()) {
                    document.getElementById('studentNameError').textContent = 'Please enter your full name';
                    name.classList.add('error');
                    valid = false;
                }

                if (!email.value.trim()) {
                    document.getElementById('studentEmailError').textContent = 'Please enter your email address';
                    email.classList.add('error');
                    valid = false;
                } else if (!isValidEmail(email.value.trim())) {
                    document.getElementById('studentEmailError').textContent = 'Please enter a valid email address';
                    email.classList.add('error');
                    valid = false;
                }

                if (!phone.value.trim()) {
                    document.getElementById('studentPhoneError').textContent = 'Please enter your phone number';
                    phone.classList.add('error');
                    valid = false;
                }

                if (!password.value.trim()) {
                    document.getElementById('studentPasswordError').textContent = 'Please create a password';
                    password.classList.add('error');
                    valid = false;
                } else if (password.value.length < 6) {
                    document.getElementById('studentPasswordError').textContent = 'Password must be at least 6 characters';
                    password.classList.add('error');
                    valid = false;
                }

                if (resume.files.length === 0) {
                    document.getElementById('resumeError').textContent = 'Please upload your resume';
                    valid = false;
                }
            }

            if (currentUserType === 'institution') {
                const institutionName = document.getElementById('institution_name');
                const email = document.getElementById('institution_email');
                const password = document.getElementById('institution_password');

                if (!institutionName.value) {
                    document.getElementById('institutionNameError').textContent = 'Please select your institution';
                    institutionName.classList.add('error');
                    valid = false;
                }

                if (!email.value.trim()) {
                    document.getElementById('institutionEmailError').textContent = 'Please enter email address';
                    email.classList.add('error');
                    valid = false;
                } else if (!isValidEmail(email.value.trim())) {
                    document.getElementById('institutionEmailError').textContent = 'Please enter a valid email address';
                    email.classList.add('error');
                    valid = false;
                }

                if (!password.value.trim()) {
                    document.getElementById('institutionPasswordError').textContent = 'Please create a password';
                    password.classList.add('error');
                    valid = false;
                } else if (password.value.length < 6) {
                    document.getElementById('institutionPasswordError').textContent = 'Password must be at least 6 characters';
                    password.classList.add('error');
                    valid = false;
                }
            }

            // Add loading state if valid
            if (valid) {
                registerBtn.classList.add('loading');
                registerBtn.textContent = '';
            }

            return valid;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // File upload handling
        document.getElementById('resume').addEventListener('change', function() {
            const fileText = document.getElementById('file-upload-text');
            const uploadArea = document.querySelector('.file-upload-area');
            
            if (this.files.length > 0) {
                const file = this.files[0];
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                fileText.innerHTML = `
                    <div class="file-selected">
                        <i class="fas fa-file-alt"></i>
                        <span>${file.name} (${fileSize}MB)</span>
                    </div>
                `;
                uploadArea.style.borderColor = '#10b981';
                uploadArea.style.background = '#f0fdf4';
            } else {
                fileText.innerHTML = 'Click to upload your resume';
                uploadArea.style.borderColor = '#cbd5e1';
                uploadArea.style.background = '#f8fafc';
            }
        });

        // Drag and drop for file upload
        const uploadArea = document.querySelector('.file-upload-area');
        const fileInput = document.getElementById('resume');

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        // Input event listeners for real-time validation
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    this.classList.remove('error');
                    const errorId = this.id + 'Error';
                    const errorElement = document.getElementById(errorId);
                    if (errorElement) {
                        errorElement.textContent = '';
                    }
                }
            });
        });

        // Initialize on page load
        window.addEventListener('load', () => {
            if (currentUserType) {
                selectUserType(currentUserType);
            }
        });

        // Password strength indicator (optional enhancement)
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            return strength;
        }

        // Add password strength indicator for student password
        document.getElementById('student_password').addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            const errorElement = document.getElementById('studentPasswordError');
            
            if (this.value.length > 0 && this.value.length < 6) {
                errorElement.textContent = 'Password too short (minimum 6 characters)';
                errorElement.style.color = '#ef4444';
            } else if (this.value.length >= 6) {
                const strengthTexts = [
                    '', 'Weak password', 'Fair password', 'Good password', 'Strong password', 'Very strong password'
                ];
                const strengthColors = [
                    '', '#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'
                ];
                
                if (strength > 1) {
                    errorElement.textContent = strengthTexts[strength];
                    errorElement.style.color = strengthColors[strength];
                }
            }
        });

        // Add password strength indicator for institution password
        document.getElementById('institution_password').addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            const errorElement = document.getElementById('institutionPasswordError');
            
            if (this.value.length > 0 && this.value.length < 6) {
                errorElement.textContent = 'Password too short (minimum 6 characters)';
                errorElement.style.color = '#ef4444';
            } else if (this.value.length >= 6) {
                const strengthTexts = [
                    '', 'Weak password', 'Fair password', 'Good password', 'Strong password', 'Very strong password'
                ];
                const strengthColors = [
                    '', '#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'
                ];
                
                if (strength > 1) {
                    errorElement.textContent = strengthTexts[strength];
                    errorElement.style.color = strengthColors[strength];
                }
            }
        });
    </script>
</body>
</html>