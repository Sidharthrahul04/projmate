<?php
header('Content-Type: application/json');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$student_id = $_SESSION['user_id'];

// Get student's resume path
$stmt = $conn->prepare("SELECT resume_path FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || empty($result['resume_path'])) {
    echo json_encode(['success' => false, 'error' => 'No resume found. Please upload a resume first.']);
    exit;
}

// For now, return mock analysis (later you can add Python integration)
echo json_encode([
    'success' => true,
    'analysis' => [
        'skills' => ['Python', 'JavaScript', 'MongoDB', 'Machine Learning'],
        'experience_years' => 1,
        'education_level' => 'Masters',
        'confidence_score' => 85.5
    ]
]);

$stmt->close();
$conn->close();
?>
