<?php
// student/notifications.php
include('../includes/db_connect.php');
session_start();

// Ensure the student is logged in
if (!isset($_SESSION['student_id'])) {
    echo "<p style='color:red;'>Unauthorized access.</p>";
    exit;
}

$student_id = $_SESSION['student_id'];

// For now, no notifications exist
// Later, fetch from a notifications table like:
// $stmt = $conn->prepare("SELECT message, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC");
// $stmt->bind_param("i", $student_id);
// $stmt->execute();
// $result = $stmt->get_result();

// If no rows, show empty message
?>
<div style="padding: 20px; text-align: center;">
    <h3>Notifications</h3>
    <p>No notifications yet. 📭</p>
    <small>Once institutions start selecting students or inviting them to projects, you'll see updates here.</small>
</div>
