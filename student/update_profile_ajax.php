<?php
// update_profile_ajax.php
header('Content-Type: application/json');
include('../includes/db_connect.php');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$student_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get current student data
    $stmt = $conn->prepare("SELECT name, email, phone, resume_path FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $current_student = $stmt->get_result()->fetch_assoc();
    
    if (!$current_student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }
    
    // Validate input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Name is required']);
        exit;
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Valid email is required']);
        exit;
    }
    
    // Handle file upload
    $resume_path = $current_student['resume_path'];
    if (!empty($_FILES['resume']['name'])) {
        $file = $_FILES['resume'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        if ($file_error !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'File upload error']);
            exit;
        }
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($ext !== 'pdf') {
            echo json_encode(['success' => false, 'error' => 'Only PDF files are allowed']);
            exit;
        }
        
        if ($file_size > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'error' => 'File size must be less than 5MB']);
            exit;
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = "../uploads/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $new_filename = "resume_" . $student_id . "_" . time() . ".pdf";
        $new_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $new_path)) {
            // Delete old resume if exists - handle both path formats
            if ($resume_path) {
                $old_file_path = '';
                if (strpos($resume_path, 'uploads/') === 0) {
                    $old_file_path = '../' . $resume_path;
                } else {
                    $old_file_path = '../uploads/' . $resume_path;
                }
                
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            
            // Store path consistently (without uploads/ prefix for new uploads)
            $resume_path = $new_filename;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload resume']);
            exit;
        }
    }
    
    // Update database
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE students SET name=?, email=?, phone=?, password=?, resume_path=? WHERE id=?");
        $stmt->bind_param("sssssi", $name, $email, $phone, $hashed_password, $resume_path, $student_id);
    } else {
        $stmt = $conn->prepare("UPDATE students SET name=?, email=?, phone=?, resume_path=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $resume_path, $student_id);
    }
    
    if ($stmt->execute()) {
        // Update session if needed
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        // Get updated student data
        $stmt = $conn->prepare("SELECT name, email, phone, resume_path FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $updated_student = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile updated successfully',
            'student' => $updated_student
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update profile in database']);
    }
    
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}

$conn->close();
?>