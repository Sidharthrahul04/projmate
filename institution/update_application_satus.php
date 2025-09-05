<?php
header('Content-Type: application/json');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!isset($data->application_id) || !isset($data->status)) {
    echo json_encode(['success' => false, 'error' => 'Missing required data.']);
    exit;
}

$application_id = (int) $data->application_id;
$status = $data->status;

if (!in_array($status, ['accepted', 'rejected'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

// Update application status
$stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $application_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update application.']);
}

$stmt->close();
$conn->close();
?>
