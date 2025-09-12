<?php
session_start();
include('../includes/db_connect.php');

// Set content type for JSON response
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['application_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing application ID']);
    exit;
}

$application_id = (int) $input['application_id'];
$student_id = (int) $_SESSION['user_id'];

try {
    // Verify that this application belongs to the logged-in student and is still pending
    $verify_stmt = $conn->prepare("
        SELECT id, status 
        FROM applications 
        WHERE id = ? AND student_id = ?
    ");
    $verify_stmt->bind_param("ii", $application_id, $student_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    $application = $result->fetch_assoc();
    
    if (!$application) {
        echo json_encode(['success' => false, 'error' => 'Application not found']);
        exit;
    }
    
    // Check if application can be withdrawn (only pending applications)
    if ($application['status'] !== 'pending') {
        echo json_encode(['success' => false, 'error' => 'Cannot withdraw a ' . $application['status'] . ' application']);
        exit;
    }
    
    // Delete the application
    $delete_stmt = $conn->prepare("DELETE FROM applications WHERE id = ?");
    $delete_stmt->bind_param("i", $application_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Application withdrawn successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>