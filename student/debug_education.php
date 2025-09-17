<?php
// debug_education.php - Test script to check education parsing
session_start();
include('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    die("Please log in as a student first");
}

$student_id = $_SESSION['user_id'];

echo "<h2>Education Debug Test</h2>";

// 1. Check current student data
echo "<h3>1. Current Student Data in Database:</h3>";
$stmt = $conn->prepare("SELECT name, education_level, experience_years, resume_path FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

echo "<pre>";
print_r($student);
echo "</pre>";

// 2. Test Python script directly if resume exists
if (!empty($student['resume_path'])) {
    echo "<h3>2. Testing Python Script Directly:</h3>";
    
    $resume_path = realpath('../uploads/' . $student['resume_path']);
    $python_script = realpath("../python_scripts/resume_parser.py");
    
    if ($resume_path && $python_script) {
        echo "<p>Resume file: " . htmlspecialchars($resume_path) . "</p>";
        echo "<p>Python script: " . htmlspecialchars($python_script) . "</p>";
        
        $commands = [
            "python3 \"$python_script\" \"$resume_path\" 2>&1",
            "python \"$python_script\" \"$resume_path\" 2>&1",
            "py \"$python_script\" \"$resume_path\" 2>&1"
        ];
        
        foreach ($commands as $i => $command) {
            echo "<h4>Command " . ($i + 1) . ":</h4>";
            echo "<code>" . htmlspecialchars($command) . "</code><br>";
            
            $output = shell_exec($command);
            echo "<strong>Raw Output:</strong><pre>" . htmlspecialchars($output) . "</pre>";
            
            if ($output) {
                $analysis = json_decode($output, true);
                if ($analysis) {
                    echo "<strong>Parsed JSON:</strong><pre>";
                    print_r($analysis);
                    echo "</pre>";
                    
                    if (isset($analysis['education_level'])) {
                        echo "<p style='color: green;'>✓ Education found: " . htmlspecialchars($analysis['education_level']) . "</p>";
                    } else {
                        echo "<p style='color: red;'>✗ No education_level in result</p>";
                    }
                    break; // Stop after first successful command
                } else {
                    echo "<p style='color: red;'>JSON decode failed: " . json_last_error_msg() . "</p>";
                }
            } else {
                echo "<p style='color: red;'>No output from command</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>File paths not found</p>";
    }
} else {
    echo "<h3>2. No resume file uploaded</h3>";
}

// 3. Test manual education update
echo "<h3>3. Test Manual Education Update:</h3>";
echo "<form method='post'>";
echo "Education Level: <select name='education'>";
echo "<option value='Not specified'>Not specified</option>";
echo "<option value='Diploma'>Diploma</option>";
echo "<option value='Bachelors'>Bachelors</option>";
echo "<option value='Masters'>Masters</option>";
echo "<option value='PhD'>PhD</option>";
echo "</select>";
echo "<input type='submit' value='Update'>";
echo "</form>";

if ($_POST['education'] ?? false) {
    $education = $_POST['education'];
    $update_stmt = $conn->prepare("UPDATE students SET education_level = ? WHERE id = ?");
    $update_stmt->bind_param("si", $education, $student_id);
    
    if ($update_stmt->execute()) {
        echo "<p style='color: green;'>✓ Manual update successful</p>";
        echo "<p>Affected rows: " . $update_stmt->affected_rows . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Manual update failed: " . $update_stmt->error . "</p>";
    }
}

// 4. Check database structure
echo "<h3>4. Database Structure Check:</h3>";
$result = $conn->query("DESCRIBE students");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>