<?php
header('Content-Type: application/json');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$institution_id = $_SESSION['user_id'];
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!isset($data->project_id) || !is_numeric($data->project_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid project ID.']);
    exit;
}

$project_id = (int) $data->project_id;

// Verify project belongs to this institution
$verify_stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND institution_id = ?");
$verify_stmt->bind_param("ii", $project_id, $institution_id);
$verify_stmt->execute();

if ($verify_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Project not found.']);
    exit;
}

// Delete the project
$delete_stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND institution_id = ?");
$delete_stmt->bind_param("ii", $project_id, $institution_id);

if ($delete_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete project.']);
}

$delete_stmt->close();
$verify_stmt->close();
$conn->close();
?>
