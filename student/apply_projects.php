<?php
header('Content-Type: application/json');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$student_id = $_SESSION['user_id'];
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!isset($data->project_id) || !is_numeric($data->project_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid project ID.']);
    exit;
}

$project_id = (int) $data->project_id;

// Check if already applied
$check_stmt = $conn->prepare("SELECT id FROM applications WHERE student_id = ? AND project_id = ?");
$check_stmt->bind_param("ii", $student_id, $project_id);
$check_stmt->execute();

if ($check_stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Already applied to this project.']);
    exit;
}

// ADDED - Get project and student info for notification
$project_stmt = $conn->prepare("SELECT title, institution_id FROM projects WHERE id = ?");
$project_stmt->bind_param("i", $project_id);
$project_stmt->execute();
$project_info = $project_stmt->get_result()->fetch_assoc();

$student_stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_info = $student_stmt->get_result()->fetch_assoc();

// Insert application
$insert_stmt = $conn->prepare("INSERT INTO applications(student_id, project_id, status, applied_at) VALUES (?, ?, 'pending', NOW())");
$insert_stmt->bind_param("ii", $student_id, $project_id);

if ($insert_stmt->execute()) {
    // ADDED - Create notification for institution
    if ($project_info && $student_info) {
        $message = "New application from {$student_info['name']} ({$student_info['email']}) for project: {$project_info['title']}";
        $notify_stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, message, is_read, created_at) VALUES ('institution', ?, ?, 0, NOW())");
        $notify_stmt->bind_param("is", $project_info['institution_id'], $message);
        $notify_stmt->execute();
        $notify_stmt->close();
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to apply.']);
}

$check_stmt->close();
$project_stmt->close();
$student_stmt->close();
$insert_stmt->close();
$conn->close();
?>
