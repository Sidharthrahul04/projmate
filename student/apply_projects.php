<?php
// student/apply_projects.php
include('../includes/db_connect.php');
session_start();

header('Content-Type: application/json');

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$student_id = $_SESSION['student_id'];

// Get project ID from POST
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid project.'
    ]);
    exit;
}

// For now, since no projects are posted, reject all applications
// Later, this will check if the project exists and insert an application
echo json_encode([
    'status' => 'error',
    'message' => 'Project applications are currently closed. Please check back once projects are available.'
]);
?>
