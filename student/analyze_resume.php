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

$resume_path = realpath('../uploads/' . $result['resume_path']);

if ($resume_path === false || !file_exists($resume_path)) {
    echo json_encode(['success' => false, 'error' => 'Resume file not found.']);
    exit;
}

// Use realpath for script path
$python_script = realpath("../python_scripts/resume_parser.py");

if ($python_script === false || !file_exists($python_script)) {
    echo json_encode(['success' => false, 'error' => 'Python script not found. Please check python_scripts directory.']);
    exit;
}

// Try different Python commands with error capture
$commands = [
    "python3 \"$python_script\" \"$resume_path\" 2>&1",
    "python \"$python_script\" \"$resume_path\" 2>&1",
    "py \"$python_script\" \"$resume_path\" 2>&1"
];

$output = null;
$analysis = null;

foreach ($commands as $command) {
    $output = shell_exec($command);
    if ($output) {
        $analysis = json_decode($output, true);
        if ($analysis && !isset($analysis['error'])) {
            break;
        }
    }
}

if (!$analysis || isset($analysis['error'])) {
    $error_msg = isset($analysis['error']) ? $analysis['error'] : 'Failed to analyze resume. Output: ' . $output;
    echo json_encode(['success' => false, 'error' => $error_msg, 'debug_output' => $output]);
    exit;
}

// Store analyzed data in database
$skills_json = json_encode($analysis['skills']);
$experience_years = $analysis['experience_years'];
$education_level = $analysis['education_level'];

$update_stmt = $conn->prepare("UPDATE students SET skills = ?, experience_years = ?, education_level = ? WHERE id = ?");
$update_stmt->bind_param("sisi", $skills_json, $experience_years, $education_level, $student_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'message' => 'Resume analyzed and profile updated!'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update profile with analysis.']);
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>
