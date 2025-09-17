<?php
// update_profile.php
session_start();
include('../includes/db_connect.php');

// Ensure user is logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int) $_SESSION['user_id'];

// Fetch student's current info
$stmt = $conn->prepare("SELECT name, email, phone, resume_path FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $error_message = 'Name and email are required fields.';
    } else {
        try {
            // Handle file upload
            $resume_path = $student['resume_path']; // Keep existing by default
            $resume_updated = false; // Track if resume was updated
            
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                $file_name = $_FILES['resume']['name'];
                $file_tmp = $_FILES['resume']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validate file type
                if ($file_ext !== 'pdf') {
                    throw new Exception('Only PDF files are allowed for resume upload.');
                }
                
                // Generate unique filename
                $new_filename = 'resume_' . $student_id . '_' . time() . '.pdf';
                $upload_path = $upload_dir . $new_filename;
                
                // Create upload directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Delete old resume if exists
                    if (!empty($student['resume_path']) && file_exists($upload_dir . $student['resume_path'])) {
                        unlink($upload_dir . $student['resume_path']);
                    }
                    $resume_path = $new_filename;
                    $resume_updated = true; // Mark that resume was updated
                } else {
                    throw new Exception('Failed to upload resume file.');
                }
            }
            
            // Prepare update query
            if (!empty($password)) {
                // Update with password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                if ($resume_updated) {
                    // Reset is_resume_analyzed if resume was updated
                    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, password = ?, resume_path = ?, is_resume_analyzed = 0 WHERE id = ?");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $hashed_password, $resume_path, $student_id);
                } else {
                    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, password = ?, resume_path = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $hashed_password, $resume_path, $student_id);
                }
            } else {
                // Update without password
                if ($resume_updated) {
                    // Reset is_resume_analyzed if resume was updated
                    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, resume_path = ?, is_resume_analyzed = 0 WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $email, $phone, $resume_path, $student_id);
                } else {
                    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, resume_path = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $email, $phone, $resume_path, $student_id);
                }
            }
            
            if ($stmt->execute()) {
                $success_message = 'Profile updated successfully!';
                
                // Update the student array with new data
                $student['name'] = $name;
                $student['email'] = $email;
                $student['phone'] = $phone;
                $student['resume_path'] = $resume_path;
                
                // Redirect to dashboard after successful update
                header("Location: student_dashboard.php?updated=1");
                exit;
            } else {
                throw new Exception('Failed to update profile in database.');
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - ProjMate</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .update-form-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), #5b7cfa);
            color: white;
            padding: 24px;
            text-align: center;
        }

        .form-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .form-body {
            padding: 32px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--primary-color);
            width: 16px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e4e7;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .current-file {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }

        .current-file i {
            color: var(--primary-color);
            margin-right: 8px;
        }

        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-top: 32px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: #5a67d8;
            transform: translateX(-4px);
        }

        .back-link i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="update-form-container">
            <div class="form-header">
                <h2>
                    <i class="fas fa-user-edit"></i>
                    Update Profile
                </h2>
            </div>

            <div class="form-body">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-user"></i>
                            Full Name *
                        </label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($student['name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address *
                        </label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i>
                            Phone Number
                        </label>
                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            New Password (leave blank to keep current)
                        </label>
                        <input type="password" id="password" name="password" placeholder="Enter new password">
                    </div>

                    <div class="form-group">
                        <label for="resume">
                            <i class="fas fa-file-pdf"></i>
                            Resume (PDF only)
                        </label>
                        <?php if (!empty($student['resume_path'])): ?>
                            <div class="current-file">
                                <i class="fas fa-file-pdf"></i>
                                Current: <?= htmlspecialchars(basename($student['resume_path'])) ?>
                                <a href="../uploads/<?= htmlspecialchars($student['resume_path']) ?>" target="_blank" style="margin-left: auto; color: var(--primary-color);">
                                    <i class="fas fa-external-link-alt"></i>
                                    View
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="resume" name="resume" accept=".pdf">
                    </div>

                    <div class="form-actions">
                        <a href="student_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive feedback
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            form.addEventListener('submit', function(e) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                
                // Re-enable after a timeout in case of issues
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }, 10000);
            });

            // File input feedback
            const fileInput = document.getElementById('resume');
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    const file = e.target.files[0];
                    if (file.type !== 'application/pdf') {
                        alert('Please select a PDF file only.');
                        e.target.value = '';
                        return;
                    }
                    
                    if (file.size > 5 * 1024 * 1024) { // 5MB limit
                        alert('File size must be less than 5MB.');
                        e.target.value = '';
                        return;
                    }
                }
            });
        });
    </script>
</body>
</html>