<?php
header('Content-Type: application/json');
session_start();
include('../includes/db_connect.php');

// Optional: Keep for debugging, but remove for a live production server.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}
$student_id = $_SESSION['user_id'];

// 2. Get Student's Resume Path from Database
$stmt = $conn->prepare("SELECT resume_path FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || empty($result['resume_path'])) {
    echo json_encode(['success' => false, 'error' => 'No resume found. Please upload a resume first.']);
    exit;
}

// 3. Verify File Paths for Resume and Python Script
$resume_path = realpath('../uploads/' . $result['resume_path']);
if ($resume_path === false || !file_exists($resume_path)) {
    echo json_encode(['success' => false, 'error' => 'Resume file could not be found on the server.']);
    exit;
}

$python_script = realpath("../python_scripts/resume_parser.py");
if ($python_script === false || !file_exists($python_script)) {
    echo json_encode(['success' => false, 'error' => 'Python parser script not found. Contact administrator.']);
    exit;
}

// 4. Execute the Python Script
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
        // If decoding is successful and there's no error key, break the loop
        if ($analysis && !isset($analysis['error'])) {
            break;
        }
    }
}

// 5. Validate the Analysis Output
if (!$analysis || isset($analysis['error'])) {
    $error_msg = isset($analysis['error']) ? $analysis['error'] : 'Failed to analyze resume. The Python script might have an error.';
    echo json_encode(['success' => false, 'error' => $error_msg, 'debug_output' => $output]);
    exit;
}

// Crucial Check: Ensure skills were actually found
if (empty($analysis['skills'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Analysis complete, but no skills were extracted from the resume. Please ensure your resume format is readable and contains a skills section.',
        'debug_output' => $output
    ]);
    exit;
}

// 6. Store Analysis Results in the Database via a Transaction
$conn->begin_transaction();
try {
    // Step A: Delete old skills
    $delete_stmt = $conn->prepare("DELETE FROM student_skills WHERE student_id = ?");
    if ($delete_stmt === false) throw new Exception("Prepare failed (delete): " . $conn->error);
    $delete_stmt->bind_param("i", $student_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Step B: Insert new skills
    $skills = $analysis['skills'];
    $insert_skill_stmt = $conn->prepare("INSERT INTO student_skills (student_id, skill_name) VALUES (?, ?)");
    if ($insert_skill_stmt === false) throw new Exception("Prepare failed (insert): " . $conn->error);
    foreach ($skills as $skill) {
        $insert_skill_stmt->bind_param("is", $student_id, $skill);
        $insert_skill_stmt->execute();
    }
    $insert_skill_stmt->close();
    
    // Step C: Update other student details
    $experience_years = $analysis['experience_years'] ?? 0;
    $education_level = $analysis['education_level'] ?? 'Not specified';
    $update_stmt = $conn->prepare("UPDATE students SET experience_years = ?, education_level = ? WHERE id = ?");
    if ($update_stmt === false) throw new Exception("Prepare failed (update): " . $conn->error);
    $update_stmt->bind_param("isi", $experience_years, $education_level, $student_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // If all steps succeed, commit the changes
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'message' => 'Resume analyzed and profile updated!'
    ]);

} catch (Exception $e) {
    // If any step fails, roll back all database changes
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $e->getMessage()]);
} finally {
    // 7. Close remaining connections
    $stmt->close();
    $conn->close();
}
?>