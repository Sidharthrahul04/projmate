<?php
// Start session only if one isn't already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <base href="http://localhost:5050/projmate/">
  <meta charset="UTF-8">
  <title>Institution Dashboard | ProjMate</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
    <p>You are logged in as an <strong>Institution</strong>.</p>
    
    <ul>
      <li><a href="institution/post_project.php">Post a New Project</a></li>
      <li><a href="institution/filter_students.php">Filter Suitable Students</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </div>
</body>
</html>