<?php
session_start();
include('../includes/db_connect.php');

// Set content type for JSON response
header('Content-Type: application/json');

// Check if user is logged in and is an institution
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['application_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$application_id = (int) $input['application_id'];
$status = $input['status'];
$institution_id = (int) $_SESSION['user_id'];

// Validate status
if (!in_array($status, ['accepted', 'rejected', 'pending'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    // Verify that this application belongs to a project owned by this institution
    $verify_stmt = $conn->prepare("
        SELECT a.id 
        FROM applications a 
        JOIN projects p ON a.project_id = p.id 
        WHERE a.id = ? AND p.institution_id = ?
    ");
    $verify_stmt->bind_param("ii", $application_id, $institution_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Application not found or unauthorized']);
        exit;
    }
    
    // Update the application status
    $update_stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $status, $application_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Application status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>