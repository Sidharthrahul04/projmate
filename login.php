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
                $error = " Incorrect password.";
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
                    $error = " Incorrect password.";
                }
            } else {
                $error = " Email not found.";
            }
        }

        $stmt->close();
    } else {
        $error = " All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <base href="http://localhost:5050/projmate/">
  <meta charset="UTF-8">
  <title>Login | ProjMate</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <div class="logo">ProjMate</div>

    <!-- Error message -->
    <?php if (!empty($error)): ?>
      <div class="error-msg" style="color:red; text-align:center; margin-bottom:10px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" onsubmit="return validateLogin()">
      <input type="email" name="email" id="email" placeholder="Email">
      <div id="emailError" class="error-msg"></div>

      <input type="password" name="password" id="password" placeholder="Password">
      <div id="passwordError" class="error-msg"></div>

      <button type="submit">Login</button>
<div class="toggle-link">New user? <a href="register.php">Register now</a></div>
</form>
  </div>

  <script>
    function validateLogin() {
      let valid = true;
      const email = document.getElementById('email');
      const password = document.getElementById('password');
      document.getElementById('emailError').textContent = '';
      document.getElementById('passwordError').textContent = '';

      if (email.value.trim() === '') {
        document.getElementById('emailError').textContent = 'Enter your email';
        email.classList.add('error');
        valid = false;
      } else {
        email.classList.remove('error');
      }

      if (password.value.trim() === '') {
        document.getElementById('passwordError').textContent = 'Enter your password';
        password.classList.add('error');
        valid = false;
      } else {
        password.classList.remove('error');
      }

      return valid;
    }
  </script>
</body>
</html>