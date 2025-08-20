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
    <button class="nav-btn" onclick="showSection('my_projects')" id="nav-my-projects">
      <i class="fas fa-folder"></i>
      My Projects
    </button>
    <button class="nav-btn" onclick="showSection('notifications')" id="nav-notifications">
      <i class="fas fa-bell"></i>
      Notifications
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
      <button class="btn" onclick="showSection('projects')">
        <i class="fas fa-search"></i>
        Find Projects
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
      
      <div class="stats-container">
        <div id="quick_stats">
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-number">0</div>
              <div class="stat-label">Applied</div>
            </div>
            <div class="stat-item">
              <div class="stat-number">0</div>
              <div class="stat-label">Accepted</div>
            </div>
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
          Welcome to your ProjMate dashboard! Here you can manage your profile, browse available projects, 
          track your applications, and stay updated with notifications.
        </p>
        
        <div style="display: grid; gap: 16px; margin-top: 24px;">
          <div style="background: rgba(102, 126, 234, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(102, 126, 234, 0.2);">
            <h4 style="color: var(--primary-color); margin-bottom: 8px;">
              <i class="fas fa-search"></i>
              Browse Projects
            </h4>
            <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
              Discover exciting project opportunities posted by institutions.
            </p>
            <button class="btn" onclick="showSection('projects')">
              <i class="fas fa-arrow-right"></i>
              Explore Now
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
/* Enhanced Single-page behavior with modern UI feedback */

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
  
  return fetch(url, {cache: 'no-store'})
    .then(resp => {
      if (!resp.ok) throw new Error('Network response not OK');
      return resp.text();
    })
    .then(html => {
      target.innerHTML = html;
      // Add slide-in animation
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
        <div class="empty-msg">
          <i class="fas fa-exclamation-triangle" style="color: var(--warning-color); margin-right: 8px;"></i>
          Failed to load content. Please try again.
        </div>
      `;
      console.error(err);
    });
}

/* Enhanced section navigation */
function showSection(name) {
  setActiveNav(name);
  
  if (name === 'projects') {
    loadFragment('fetch_projects.php');
  } else if (name === 'my_projects') {
    loadFragment('project_analysis.php');
  } else if (name === 'notifications') {
    loadFragment('notifications.php');
  }
}

/* Enhanced project application with better feedback */
function applyProject(projectId, btn) {
  if (!confirm('Are you sure you want to apply for this project?')) return;
  
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
  
  fetch('apply_projects.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project_id: projectId })
  })
  .then(r => r.json())
  .then(json => {
    if (json.success) {
      btn.innerHTML = '<i class="fas fa-check"></i> Applied';
      btn.style.background = 'var(--success-color)';
      loadQuickStats(); // Refresh stats
      
      // Show success feedback
      showNotification('Application submitted successfully!', 'success');
    } else {
      throw new Error(json.error || 'Failed to apply');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = originalText;
    showNotification(err.message || 'Network error occurred', 'error');
    console.error(err);
  });
}

/* Enhanced quick stats loader */
function loadQuickStats() {
  fetch('project_analysis.php?stats_only=1', {cache: 'no-store'})
    .then(r => {
      if (!r.ok) throw new Error('Network error');
      return r.json();
    })
    .then(j => {
      const statsContainer = document.getElementById('quick_stats');
      statsContainer.innerHTML = `
        <div class="stats-grid">
          <div class="stat-item">
            <div class="stat-number">${j.applied || 0}</div>
            <div class="stat-label">Applied</div>
          </div>
          <div class="stat-item">
            <div class="stat-number">${j.accepted || 0}</div>
            <div class="stat-label">Accepted</div>
          </div>
        </div>
      `;
      
      // Add animation
      statsContainer.style.opacity = '0';
      setTimeout(() => {
        statsContainer.style.transition = 'opacity 0.3s ease';
        statsContainer.style.opacity = '1';
      }, 100);
    })
    .catch(() => {
      // Fail silently or show default
    });
}

/* Notification system */
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? 'var(--error-color)' : 'var(--primary-color)'};
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    z-index: 1000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    max-width: 300px;
    font-weight: 500;
  `;
  
  notification.innerHTML = `
    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
    ${message}
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.transform = 'translateX(0)';
  }, 100);
  
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => {
      document.body.removeChild(notification);
    }, 300);
  }, 3000);
}

/* Initialize dashboard */
function initializeDashboard() {
  loadQuickStats();
  
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