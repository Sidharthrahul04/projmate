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

// Start transaction to ensure both operations complete
$conn->begin_transaction();

try {
    // First, delete all applications for this project
    $delete_apps_stmt = $conn->prepare("DELETE FROM applications WHERE project_id = ?");
    $delete_apps_stmt->bind_param("i", $project_id);
    $delete_apps_stmt->execute();
    $delete_apps_stmt->close();
    
    // Then, delete the project
    $delete_project_stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND institution_id = ?");
    $delete_project_stmt->bind_param("ii", $project_id, $institution_id);
    $delete_project_stmt->execute();
    $delete_project_stmt->close();
    
    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Failed to delete project: ' . $e->getMessage()]);
}

$verify_stmt->close();
$conn->close();
?>