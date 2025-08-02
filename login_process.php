<?php
session_start();
include('includes/db_connect.php');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $errorMessage = '';

    if (empty($email) || empty($password)) {
        $errorMessage = "Please enter both email and password.";
    } else {
        // === Check in students table ===
        $stmt = $conn->prepare("SELECT id, name, password FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $name, $hashedPassword);
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {
                $_SESSION['user_type'] = 'student';
                $_SESSION['user_id'] = $id;
                $_SESSION['name'] = htmlspecialchars($name);
                header("Location: student_dashboard.php");
                exit;
            }
        }
        $stmt->close();

        // === Check in institutions table ===
        $stmt = $conn->prepare("SELECT id, institution_name, password FROM institutions WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $institution_name, $hashedPassword);
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {
                $_SESSION['user_type'] = 'institution';
                $_SESSION['user_id'] = $id;
                $_SESSION['name'] = htmlspecialchars($institution_name);
                header("Location: institution_dashboard.php");
                exit;
            }
        }
        $stmt->close();

        // If no match found in either table
        $errorMessage = "Invalid email or password.";
    }

    $_SESSION['login_error'] = $errorMessage;
    header("Location: login.php");
    exit;
} else {
    echo "Invalid request.";
}
?>
