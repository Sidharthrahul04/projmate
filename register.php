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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (rest of the file is unchanged)
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

    // ✅ Email format validation (server-side)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format.'); window.history.back();</script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    if ($user_type == "student") {
        // Check for duplicate email
        $check = $conn->prepare("SELECT id FROM students WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo "<script>alert('Email already registered as student.'); window.history.back();</script>";
            exit();
        }
        $check->close();

        // Validate resume
        $resume_name = time() . '_' . basename($_FILES["resume"]["name"]);
        $resume_path = "uploads/" . $resume_name;
        $file_ext = strtolower(pathinfo($resume_name, PATHINFO_EXTENSION));
        $allowed_types = ['pdf', 'doc', 'docx'];

        if (!in_array($file_ext, $allowed_types)) {
            echo "<script>alert('Invalid resume file type. Only PDF, DOC, DOCX allowed.'); window.history.back();</script>";
            exit();
        }

        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $resume_path)) {
        $stmt = $conn->prepare("INSERT INTO students (name, email, phone, resume_path, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $resume_name, $hashed_password);
            if ($stmt->execute()) {
                echo "<script>alert('Student registration successful!'); window.location.href='login.php';</script>";
            } else {
                echo "<script>alert('Database error. Please try again.'); window.history.back();</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('Failed to upload resume.'); window.history.back();</script>";
        }

    } elseif ($user_type == "institution") {
        // Check for duplicate email
        $check = $conn->prepare("SELECT id FROM institutions WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo "<script>alert('Email already registered as institution.'); window.history.back();</script>";
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO institutions (institution_name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $institution_name, $email, $phone, $hashed_password);
        if ($stmt->execute()) {
            echo "<script>alert('Institution registration successful!'); window.location.href='login.php';</script>";
        } else {
            echo "<script>alert('Database error. Please try again.'); window.history.back();</script>";
        }
        $stmt->close();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- ... (rest of the HTML is unchanged) ... -->
<head>
  <base href="http://localhost:5050/projmate/">
  <meta charset="UTF-8">
  <title>Register | ProjMate</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
  <div class="logo">ProjMate</div>
  <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateRegister()">
    <select id="usertype" name="user_type" onchange="toggleFields()">
      <option value="">I am a...</option>
      <option value="student" <?= (isset($_POST['user_type']) && $_POST['user_type'] == 'student') ? 'selected' : '' ?>>student</option>
      <option value="institution" <?= (isset($_POST['user_type']) && $_POST['user_type'] == 'institution') ? 'selected' : '' ?>>institution</option>
    </select>
    <div id="usertypeError" class="error-msg"></div>

    <!-- Student fields -->
    <div id="studentFields" style="display:<?= (isset($_POST['user_type']) && $_POST['user_type'] == 'student') ? 'block' : 'none' ?>;">
      <input type="text" name="student_name" id="student_name" placeholder="Your Name" value="<?= isset($_POST['student_name']) ? htmlspecialchars($_POST['student_name']) : '' ?>">
      <div class="error-msg" id="studentNameError"></div>

      <input type="email" name="student_email" id="student_email" placeholder="Email" value="<?= isset($_POST['student_email']) ? htmlspecialchars($_POST['student_email']) : '' ?>">
      <div class="error-msg" id="studentEmailError"></div>

      <input type="text" name="student_phone" id="student_phone" placeholder="Phone" value="<?= isset($_POST['student_phone']) ? htmlspecialchars($_POST['student_phone']) : '' ?>">
      <div class="error-msg" id="studentPhoneError"></div>

      <input type="password" name="student_password" id="student_password" placeholder="Password">
      <div class="error-msg" id="studentPasswordError"></div>

      <input type="file" name="resume" id="resume">
      <div class="error-msg" id="resumeError"></div>
    </div>

    <!-- Institution fields -->
    <div id="institutionFields" style="display:<?= (isset($_POST['user_type']) && $_POST['user_type'] == 'institution') ? 'block' : 'none' ?>;">
      <select name="institution_name" id="institution_name">
        <option value="">Select Institution</option>
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
      <div class="error-msg" id="institutionNameError"></div>

      <input type="email" name="institution_email" id="institution_email" placeholder="Email" value="<?= isset($_POST['institution_email']) ? htmlspecialchars($_POST['institution_email']) : '' ?>">
      <div class="error-msg" id="institutionEmailError"></div>

      <input type="password" name="institution_password" id="institution_password" placeholder="Password">
      <div class="error-msg" id="institutionPasswordError"></div>
    </div>

    <button type="submit">Register</button>
<div class="toggle-link">Already registered? <a href="login.php">Login here</a></div></div>

<script>
  function toggleFields() {
    const type = document.getElementById('usertype').value;
    document.getElementById('studentFields').style.display = (type === 'student') ? 'block' : 'none';
    document.getElementById('institutionFields').style.display = (type === 'institution') ? 'block' : 'none';
  }

  function validateRegister() {
    let valid = true;
    const usertype = document.getElementById('usertype').value;
    document.querySelectorAll('.error-msg').forEach(e => e.textContent = '');

    if (!usertype) {
      document.getElementById('usertypeError').textContent = 'Please select user type';
      valid = false;
    }

    if (usertype === 'student') {
      if (!document.getElementById('student_name').value.trim()) {
        document.getElementById('studentNameError').textContent = 'Enter name';
        valid = false;
      }
      if (!document.getElementById('student_email').value.trim()) {
        document.getElementById('studentEmailError').textContent = 'Enter email';
        valid = false;
      }
      if (!document.getElementById('student_phone').value.trim()) {
        document.getElementById('studentPhoneError').textContent = 'Enter phone';
        valid = false;
      }
      if (!document.getElementById('student_password').value.trim()) {
        document.getElementById('studentPasswordError').textContent = 'Enter password';
        valid = false;
      }
      if (document.getElementById('resume').files.length === 0) {
        document.getElementById('resumeError').textContent = 'Upload your resume';
        valid = false;
      }
    }

    if (usertype === 'institution') {
      if (!document.getElementById('institution_name').value) {
        document.getElementById('institutionNameError').textContent = 'Select an institution';
        valid = false;
      }
      if (!document.getElementById('institution_email').value.trim()) {
        document.getElementById('institutionEmailError').textContent = 'Enter email';
        valid = false;
      }
      if (!document.getElementById('institution_password').value.trim()) {
        document.getElementById('institutionPasswordError').textContent = 'Enter password';
        valid = false;
      }
    }

    return valid;
  }

  // Initialize correct fields on load if POST happened
  window.onload = toggleFields;
</script>
</body>
</html>