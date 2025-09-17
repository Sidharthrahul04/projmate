<?php
header('Content-Type: application/json');
session_start();
include('../includes/db_connect.php');

// 1. Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}
$student_id = $_SESSION['user_id'];

// 2. Get Student's Resume Path & Analysis Status from Database
// MODIFIED: Fetched the is_resume_analyzed column as well
$stmt = $conn->prepare("SELECT resume_path, is_resume_analyzed FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || empty($result['resume_path'])) {
    echo json_encode(['success' => false, 'error' => 'No resume found. Please upload a resume first.']);
    exit;
}

// ADDED: Check if the resume has already been analyzed before proceeding
if ($result['is_resume_analyzed']) {
    echo json_encode(['success' => false, 'error' => 'This resume has already been analyzed.']);
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
// ... (This entire section remains unchanged)
$commands = [
    "python \"$python_script\" \"$resume_path\" 2>nul",
    "py \"$python_script\" \"$resume_path\" 2>nul"
];
$output = null;
$analysis = null;
$command_used = null;
foreach ($commands as $command) {
    $output = shell_exec($command);
    if ($output && trim($output) !== '') {
        $command_used = $command;
        $analysis = json_decode(trim($output), true);
        if (json_last_error() === JSON_ERROR_NONE && $analysis && !isset($analysis['error'])) {
            break;
        }
    }
}


// 5. Validate the Analysis Output
// ... (This entire section remains unchanged)
if (!$analysis || isset($analysis['error'])) {
    $error_msg = isset($analysis['error']) ? $analysis['error'] : 'Failed to analyze resume. Check if Python and required libraries are installed.';
    echo json_encode([
        'success' => false,
        'error' => $error_msg,
        'debug_info' => [
            'raw_output' => $output,
            'json_error' => json_last_error_msg(),
            'command_used' => $command_used
        ]
    ]);
    exit;
}
if (empty($analysis['skills'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Analysis complete, but no skills were extracted from the resume.',
        'debug_info' => ['analysis_received' => $analysis]
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

    // Step C: Update student details (including education)
    $experience_years = $analysis['experience_years'] ?? 0;
    $education_level = $analysis['education_level'] ?? 'Not specified';

    $update_stmt = $conn->prepare("UPDATE students SET experience_years = ?, education_level = ? WHERE id = ?");
    if ($update_stmt === false) throw new Exception("Prepare failed (update): " . $conn->error);
    $update_stmt->bind_param("isi", $experience_years, $education_level, $student_id);
    $update_stmt->execute();
    $update_stmt->close();

    // ADDED: Step D - Mark the resume as analyzed. This is the crucial final step.
    $mark_analyzed_stmt = $conn->prepare("UPDATE students SET is_resume_analyzed = 1 WHERE id = ?");
    if ($mark_analyzed_stmt === false) throw new Exception("Prepare failed (mark analyzed): " . $conn->error);
    $mark_analyzed_stmt->bind_param("i", $student_id);
    $mark_analyzed_stmt->execute();
    $mark_analyzed_stmt->close();

    // Commit the transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'message' => 'Resume analyzed and profile updated successfully!',
        'debug_info' => [
            'skills_count' => count($skills),
            'education_detected' => $education_level,
            'experience_detected' => $experience_years
        ]
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