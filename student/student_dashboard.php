<?php
// student_dashboard.php
session_start();
include('../includes/db_connect.php');

// Ensure user is logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int) $_SESSION['user_id'];

// Fetch student's basic info
$stmt = $conn->prepare("SELECT name, email, phone, resume_path FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if we just updated profile
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ProjMate — Student Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<!-- Navbar -->
<div class="navbar">
  <div class="brand">
    <i class="fas fa-project-diagram"></i>
    ProjMate
  </div>
  <div class="nav-links">
    <button class="nav-btn" onclick="showSection('profile')" id="nav-profile">
      <i class="fas fa-user"></i>
      Profile
    </button>
    <button class="nav-btn" onclick="showSection('projects')" id="nav-projects">
      <i class="fas fa-search"></i>
      Projects
    </button>
    <button class="nav-btn" onclick="showSection('my_projects')" id="nav-my_projects">
      <i class="fas fa-folder"></i>
      My Projects
    </button>
    <button class="nav-btn logout" onclick="location.href='../logout.php'">
      <i class="fas fa-sign-out-alt"></i>
      Logout
    </button>
  </div>
</div>

<!-- Main container -->
<div class="container">
  <div class="topcard">
    <div>
      <h1>
        <i class="fas fa-graduation-cap" style="margin-right: 12px; color: var(--primary-color);"></i>
        Welcome, <?= htmlspecialchars($student['name'] ?? 'Student') ?>
      </h1>
      <div class="small-muted">
        <i class="fas fa-envelope"></i>
        <?= htmlspecialchars($student['email'] ?? '-') ?>
        &nbsp; | &nbsp;
        <i class="fas fa-phone"></i>
        <?= htmlspecialchars($student['phone'] ?? '-') ?>
      </div>
    </div>
    <div class="action-buttons">
      <!-- ADDED: Analyze Resume Button -->
      <button class="btn" onclick="analyzeResume()">
        <i class="fas fa-robot"></i>
        Analyze Resume
      </button>
      <button class="btn secondary" onclick="showSection('my_projects')">
        <i class="fas fa-folder"></i>
        My Projects
      </button>
    </div>
  </div>

  <div class="grid">
    <!-- Left Profile Card -->
    <div class="card" id="left_card">
      <h3>
        <i class="fas fa-user-circle" style="margin-right: 8px; color: var(--primary-color);"></i>
        Quick Profile
      </h3>
      
      <div class="profile-info">
        <div class="info-item">
          <div class="info-label">
            <i class="fas fa-user"></i>
            Name
          </div>
          <div class="info-value" id="profile-name"><?= htmlspecialchars($student['name'] ?? '') ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">
            <i class="fas fa-envelope"></i>
            Email
          </div>
          <div class="info-value" id="profile-email"><?= htmlspecialchars($student['email'] ?? '') ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">
            <i class="fas fa-file-pdf"></i>
            Resume
          </div>
          <div class="info-value" id="profile-resume">
            <?php if (!empty($student['resume_path'])): ?>
              <a href="../uploads/<?= htmlspecialchars($student['resume_path']) ?>" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                View Resume
              </a>
            <?php else: ?>
              <span class="empty-msg">No resume uploaded</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div style="margin-top: 20px;">
        <button class="btn" onclick="location.href='update_profile.php'" style="width: 100%;">
          <i class="fas fa-edit"></i>
          Edit Profile
        </button>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="card" id="main_card">
      <div id="content_area">
        <!-- Default profile view -->
        <h2>
          <i class="fas fa-home" style="margin-right: 12px;"></i>
          Dashboard Overview
        </h2>
        <p class="small-muted" style="margin-bottom: 20px;">
          Welcome to your ProjMate dashboard! Here you can manage your profile and track your academic project portfolio.
        </p>
        
        <div style="display: grid; gap: 16px; margin-top: 24px;">
          <!-- ADDED: AI Recommendations Card -->
          <div style="background: rgba(102, 126, 234, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(102, 126, 234, 0.2);">
            <h4 style="color: var(--primary-color); margin-bottom: 8px;">
              <i class="fas fa-robot"></i>
              AI-Powered Project Matching
            </h4>
            <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
              Get personalized project recommendations based on your resume and skills.
            </p>
            <button class="btn" onclick="showSection('projects')">
              <i class="fas fa-arrow-right"></i>
              View Smart Recommendations
            </button>
          </div>
          
          <div style="background: rgba(16, 185, 129, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2);">
            <h4 style="color: var(--success-color); margin-bottom: 8px;">
              <i class="fas fa-folder-open"></i>
              My Applications
            </h4>
            <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
              Track the status of your project applications and manage your portfolio.
            </p>
            <button class="btn secondary" onclick="showSection('my_projects')">
              <i class="fas fa-arrow-right"></i>
              View Applications
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ADDED: Small notification box function
function showNotification(message, type) {
  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    z-index: 10000;
    opacity: 0;
    transition: all 0.3s ease;
    max-width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    background-color: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
  `;
  
  notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i> ${message}`;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.opacity = '1';
  }, 10);
  
  setTimeout(() => {
    notification.style.opacity = '0';
    setTimeout(() => {
      if (notification.parentNode) {
        document.body.removeChild(notification);
      }
    }, 300);
  }, 3000);
}

// ADDED: Analyze Resume function
function analyzeResume() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    
    fetch('analyze_resume.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Resume analyzed successfully! Check smart recommendations.', 'success');
                // Show projects section to see recommendations
                showSection('projects');
            } else {
                showNotification('Analysis failed: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Network error during analysis.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

/* Active navigation state management */
function setActiveNav(sectionName) {
  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  const navBtn = document.getElementById('nav-' + sectionName);
  if (navBtn) {
    navBtn.classList.add('active');
  }
}

/* Enhanced fragment loader with loading states */
function loadFragment(url) {
  const target = document.getElementById('content_area');
  target.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i> Loading...</div>';
  
  return fetch(url, {cache: 'no-store', credentials: 'same-origin'})
    .then(resp => {
      if (!resp.ok) {
        if (resp.status === 401 || resp.status === 403) {
          throw new Error('Please log in again to access this section.');
        }
        throw new Error('Network response not OK');
      }
      return resp.text();
    })
    .then(html => {
      if (html.includes('Unauthorized access')) {
        throw new Error('This section is not yet available. Please check back later.');
      }
      
      target.innerHTML = html;
      target.style.opacity = '0';
      target.style.transform = 'translateY(20px)';
      setTimeout(() => {
        target.style.transition = 'all 0.3s ease';
        target.style.opacity = '1';
        target.style.transform = 'translateY(0)';
      }, 50);
    })
    .catch(err => {
      target.innerHTML = `
        <div class="empty-msg" style="text-align: center; padding: 40px; color: #64748b;">
          <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
          <p style="font-size: 1.1rem; margin-bottom: 8px;">Section Not Available</p>
          <p style="font-size: 0.9rem;">${err.message}</p>
          <button class="btn" onclick="showSection('profile')" style="margin-top: 16px;">
            <i class="fas fa-home"></i> Back to Dashboard
          </button>
        </div>
      `;
    });
}

/* Enhanced section navigation */
function showSection(name) {
  setActiveNav(name);
  
  if (name === 'profile') {
    // Restore default dashboard overview content
    document.getElementById('content_area').innerHTML = `
      <h2>
        <i class="fas fa-home" style="margin-right: 12px;"></i>
        Dashboard Overview
      </h2>
      <p class="small-muted" style="margin-bottom: 20px;">
        Welcome to your ProjMate dashboard! Here you can manage your profile and track your academic project portfolio.
      </p>
      
      <div style="display: grid; gap: 16px; margin-top: 24px;">
        <div style="background: rgba(102, 126, 234, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(102, 126, 234, 0.2);">
          <h4 style="color: var(--primary-color); margin-bottom: 8px;">
            <i class="fas fa-robot"></i>
            AI-Powered Project Matching
          </h4>
          <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
            Get personalized project recommendations based on your resume and skills.
          </p>
          <button class="btn" onclick="showSection('projects')">
            <i class="fas fa-arrow-right"></i>
            View Smart Recommendations
          </button>
        </div>
        
        <div style="background: rgba(16, 185, 129, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2);">
          <h4 style="color: var(--success-color); margin-bottom: 8px;">
            <i class="fas fa-folder-open"></i>
            My Applications
          </h4>
          <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
            Track the status of your project applications and manage your portfolio.
          </p>
          <button class="btn secondary" onclick="showSection('my_projects')">
            <i class="fas fa-arrow-right"></i>
            View Applications
          </button>
        </div>
      </div>
    `;
  } else if (name === 'projects') {
    loadFragment('smart_project_recommendations.php');
  } else if (name === 'my_projects') {
    loadFragment('project_analysis.php');
  } else if (name === 'notifications') {
    loadFragment('notifications.php');
  }
}

/* Initialize dashboard */
function initializeDashboard() {
  // Set profile as active by default
  setActiveNav('profile');
  
  // Show success notification if profile was just updated
  <?php if ($updated): ?>
  setTimeout(() => {
    showNotification('Profile updated successfully!', 'success');
  }, 500);
  <?php endif; ?>
}

/* Load on DOM ready */
document.addEventListener('DOMContentLoaded', initializeDashboard);
</script>
</body>
</html>
