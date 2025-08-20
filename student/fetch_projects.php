<?php
// student/fetch_projects.php
include('../includes/db_connect.php');
session_start();

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$student_id = $_SESSION['student_id'];

// For now, since no projects are posted from institutions, we return an empty list
$projects = [];

// Uncomment this later when institutions start posting projects
/*
$sql = "SELECT * FROM projects ORDER BY created_at DESC";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}
*/

if (empty($projects)) {
    echo json_encode([
        'status' => 'success',
        'projects' => [],
        'message' => 'No projects available at the moment. Please check back later.'
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'projects' => $projects
    ]);
}
?>
