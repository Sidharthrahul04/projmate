<?php
// debug_matching.php - Debug script to test the matching system
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    die('Please login as student first');
}

$student_id = $_SESSION['user_id'];

echo "<h2>Debug: Project Matching System</h2>";

// 1. Check student data
echo "<h3>1. Student Data Check</h3>";
$stmt = $conn->prepare("SELECT id, name, skills, experience_years, education_level FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

echo "<pre>";
echo "Student ID: " . $student['id'] . "\n";
echo "Name: " . $student['name'] . "\n";
echo "Skills JSON: " . ($student['skills'] ?? 'NULL') . "\n";
echo "Experience: " . ($student['experience_years'] ?? 'NULL') . " years\n";
echo "Education: " . ($student['education_level'] ?? 'NULL') . "\n";

$student_skills = [];
if (!empty($student['skills'])) {
    $decoded_skills = json_decode($student['skills'], true);
    $student_skills = is_array($decoded_skills) ? $decoded_skills : [];
}
echo "Parsed Skills: " . print_r($student_skills, true) . "\n";
echo "</pre>";

// 2. Check available projects
echo "<h3>2. Available Projects</h3>";
$projects_stmt = $conn->prepare("
    SELECT p.id, p.title, p.required_skills, p.description
    FROM projects p 
    WHERE p.id NOT IN (
        SELECT project_id FROM applications WHERE student_id = ?
    )
    LIMIT 3
");
$projects_stmt->bind_param("i", $student_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

while ($project = $projects->fetch_assoc()) {
    echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>";
    echo "<h4>Project: " . htmlspecialchars($project['title']) . "</h4>";
    echo "<p><strong>Required Skills:</strong> " . htmlspecialchars($project['required_skills']) . "</p>";
    echo "<p><strong>Description:</strong> " . htmlspecialchars(substr($project['description'], 0, 100)) . "...</p>";
    
    // Test matching for this project
    if (!empty($student_skills)) {
        $student_data = [
            'skills' => $student_skills,
            'experience_years' => (int)($student['experience_years'] ?? 0),
            'education_level' => $student['education_level'] ?? 'Not specified'
        ];
        
        $project_data = [
            'required_skills' => $project['required_skills'] ?? '',
            'description' => $project['description'] ?? ''
        ];
        
        echo "<h5>Matching Test:</h5>";
        echo "<p><strong>Student Data:</strong> " . json_encode($student_data) . "</p>";
        echo "<p><strong>Project Data:</strong> " . json_encode($project_data) . "</p>";
        
        // Test Python script execution
        $python_script = realpath("../python_scripts/project_matcher.py");
        
        if ($python_script && file_exists($python_script)) {
            $student_json = "'" . json_encode($student_data) . "'";
            $project_json = "'" . json_encode($project_data) . "'";
            
            $commands = [
                "python3 \"$python_script\" $student_json $project_json 2>&1",
                "python \"$python_script\" $student_json $project_json 2>&1",
                "py \"$python_script\" $student_json $project_json 2>&1"
            ];
            
            foreach ($commands as $i => $command) {
                echo "<p><strong>Command " . ($i+1) . ":</strong> " . htmlspecialchars($command) . "</p>";
                $output = shell_exec($command);
                echo "<p><strong>Output:</strong></p>";
                echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;'>";
                echo htmlspecialchars($output);
                echo "</pre>";
                
                $result = json_decode(trim($output), true);
                if ($result && !isset($result['error'])) {
                    echo "<p><strong>Parsed Result:</strong></p>";
                    echo "<pre>" . print_r($result, true) . "</pre>";
                    break;
                } else {
                    echo "<p style='color: red;'>Failed to get valid JSON result</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>Python script not found: $python_script</p>";
        }
    } else {
        echo "<p style='color: orange;'>No student skills available for matching</p>";
    }
    
    echo "</div>";
}

// 3. Check Python environment
echo "<h3>3. Python Environment Check</h3>";
$python_commands = ['python3 --version', 'python --version', 'py --version'];

foreach ($python_commands as $cmd) {
    $output = shell_exec($cmd . ' 2>&1');
    echo "<p><strong>$cmd:</strong> " . htmlspecialchars($output) . "</p>";
}

// Check for required modules
// Check for required modules
echo "<h4>Python Modules Check:</h4>";
$module_checks = [
    'py -c "import sys; print(sys.version)" 2>&1',
    'py -c "import json; print(\'json: OK\')" 2>&1',
    'py -c "import re; print(\'re: OK\')" 2>&1',
    'py -c "import textblob; print(\'textblob: OK\')" 2>&1',
    'py -c "import pypdf; print(\'pypdf: OK\')" 2>&1'
];

foreach ($module_checks as $check) {
    $output = shell_exec($check);
    echo "<p>" . htmlspecialchars($output) . "</p>";
}

// 4. File permissions check
echo "<h3>4. File Permissions Check</h3>";
$paths_to_check = [
    realpath("../python_scripts/"),
    realpath("../python_scripts/project_matcher.py"),
    realpath("../python_scripts/resume_parser.py")
];

foreach ($paths_to_check as $path) {
    if ($path) {
        $perms = fileperms($path);
        $readable = is_readable($path);
        $executable = is_executable($path);
        echo "<p><strong>$path:</strong> Readable: " . ($readable ? 'YES' : 'NO') . 
             ", Executable: " . ($executable ? 'YES' : 'NO') . 
             ", Permissions: " . substr(sprintf('%o', $perms), -4) . "</p>";
    } else {
        echo "<p style='color: red;'>Path not found</p>";
    }
}

$stmt->close();
$conn->close();
?>