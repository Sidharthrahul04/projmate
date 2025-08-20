<?php
// Debug version - Start session only if one isn't already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    header("Location: ../login.php");
    exit;
}

include('../includes/db_connect.php');
include 'add_project_modal.php';

$institution_id = (int) $_SESSION['user_id'];

// Initialize institution data with defaults
$institution = array(
    'institution_name' => $_SESSION['user_name'] ?? 'Institution',
    'email' => '',
    'phone' => ''
);

// Try to fetch institution's basic info with better error handling
try {
    // First, let's see what columns actually exist in the institutions table
    $show_columns = $conn->query("SHOW COLUMNS FROM institutions");
    $available_columns = [];
    if ($show_columns) {
        while ($column = $show_columns->fetch_assoc()) {
            $available_columns[] = $column['Field'];
        }
    }
    
    // Now try different column combinations
    $queries_to_try = [
        "SELECT institution_name, email, phone FROM institutions WHERE id = ?",
        "SELECT name as institution_name, email, phone FROM institutions WHERE id = ?",
        "SELECT username as institution_name, email, phone FROM institutions WHERE id = ?",
        "SELECT institution_name, email_address as email, phone_number as phone FROM institutions WHERE id = ?",
        "SELECT * FROM institutions WHERE id = ?"
    ];
    
    $data_found = false;
    foreach ($queries_to_try as $query) {
        try {
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $institution_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if ($result) {       
                    // Map the results to our expected format
                    if (isset($result['institution_name'])) {
                        $institution['institution_name'] = $result['institution_name'];
                    } elseif (isset($result['name'])) {
                        $institution['institution_name'] = $result['name'];
                    } elseif (isset($result['username'])) {
                        $institution['institution_name'] = $result['username'];
                    }
                    
                    if (isset($result['email'])) {
                        $institution['email'] = $result['email'];
                    } elseif (isset($result['email_address'])) {
                        $institution['email'] = $result['email_address'];
                    }
                    
                    if (isset($result['phone'])) {
                        $institution['phone'] = $result['phone'];
                    } elseif (isset($result['phone_number'])) {
                        $institution['phone'] = $result['phone_number'];
                    }
                  
                    
                    $data_found = true;
                    break;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
} catch (Exception $e) {
}

// Check if we just posted a project or updated profile
$posted = isset($_GET['posted']) && $_GET['posted'] == '1';
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ProjMate — Institution Dashboard</title>
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
      <i class="fas fa-building"></i>
      Profile
    </button>
    <button class="nav-btn" onclick="showSection('my_projects')" id="nav-my-projects">
      <i class="fas fa-clipboard-list"></i>
      My Projects
    </button>
    <button class="nav-btn" onclick="showSection('students')" id="nav-students">
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
        <i class="fas fa-building" style="margin-right: 12px; color: var(--primary-color);"></i>
        Welcome, <?= htmlspecialchars($institution['institution_name']) ?>
      </h1>
      <div class="small-muted">
        <i class="fas fa-envelope"></i>
        <?= htmlspecialchars($institution['email'] ?: 'No email in database') ?>
      </div>
    </div>
    <div class="action-buttons">
      <button class="btn" id="addProjectBtn" onclick="openProjectModal()">
        <i class="fas fa-plus"></i>
        Add project description 
      </button>
    </div>
  </div>

  <div class="grid">
    <!-- Left Profile Card -->
    <div class="card" id="left_card">
      <h3>
        <i class="fas fa-building" style="margin-right: 8px; color: var(--primary-color);"></i>
        Institution Overview
      </h3>
      
      <div class="profile-info">
        <div class="info-item">
          <div class="info-label">
            <i class="fas fa-building"></i>
            Institution
          </div>
          <div class="info-value" id="profile-name"><?= htmlspecialchars($institution['institution_name']) ?></div>
        </div>
        
        <div class="info-item">
          <div class="info-label">
            <i class="fas fa-envelope"></i>
            Email
          </div>
          <div class="info-value" id="profile-email">
            <?php if (!empty($institution['email'])): ?>
              <?= htmlspecialchars($institution['email']) ?>
            <?php else: ?>
              <span class="empty-msg">No email in database</span>
              <div style="font-size: 0.8rem; color: #999;">
                Raw value: "<?= htmlspecialchars($institution['email']) ?>"
              </div>
            <?php endif; ?>
          </div>
        </div>
        
      </div>
      
      <div class="stats-container">
        <div id="quick_stats">
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-number">0</div>
              <div class="stat-label">Projects</div>
            </div>
          </div>
        </div>
      </div>
      
      <div style="margin-top: 20px;">
        <button class="btn" onclick="location.href='update_institution_profile.php'" style="width: 100%;">
          <i class="fas fa-edit"></i>
          Edit Profile
        </button>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="card" id="main_card">
      <div id="content_area">
        <!-- Default dashboard view -->
        <h2>
          <i class="fas fa-home" style="margin-right: 12px;"></i>
          Dashboard Overview
        </h2>
        <p class="small-muted" style="margin-bottom: 20px;">
          Welcome to your ProjMate institution dashboard! Here you can post projects, manage applications, 
          find suitable students, and track your project portfolio performance.
        </p>
        
        <div style="display: grid; gap: 16px; margin-top: 24px;">
          <div style="background: rgba(102, 126, 234, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(102, 126, 234, 0.2);">
            <h4 style="color: var(--primary-color); margin-bottom: 8px;">
              <i class="fas fa-plus-circle"></i>
              Post New Project
            </h4>
            <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
              Create and publish new project opportunities to attract talented students.
            </p>
            <button class="btn" onclick="showSection('projects')">
              <i class="fas fa-arrow-right"></i>
              Create Project
            </button>
          </div>
          
          <div style="background: rgba(16, 185, 129, 0.1); padding: 20px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2);">
            <h4 style="color: var(--success-color); margin-bottom: 8px;">
              <i class="fas fa-clipboard-list"></i>
              Manage Projects
            </h4>
            <p style="color: var(--text-secondary); margin-bottom: 12px; font-size: 0.9rem;">
              View and manage your posted projects, review applications, and select students.
            </p>
            <button class="btn secondary" onclick="showSection('my_projects')">
              <i class="fas fa-arrow-right"></i>
              View Projects
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* Same JavaScript as before - abbreviated for space */
function setActiveNav(sectionName) {
  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  const navBtn = document.getElementById('nav-' + sectionName);
  if (navBtn) {
    navBtn.classList.add('active');
  }
}

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

function showSection(name) {
  setActiveNav(name);
  
  if (name === 'projects') {
    loadFragment('post_project.php');
  } else if (name === 'my_projects') {
    loadFragment('manage_projects.php');
  } else if (name === 'students') {
    loadFragment('filter_students.php');
  } else if (name === 'analytics') {
    loadFragment('analytics.php');
  }
}

function loadQuickStats() {
  fetch('institution_stats.php?stats_only=1', {cache: 'no-store'})
    .then(r => {
      if (!r.ok) throw new Error('Network error');
      return r.json();
    })
    .then(j => {
      const statsContainer = document.getElementById('quick_stats');
      statsContainer.innerHTML = `
        <div class="stats-grid">
          <div class="stat-item">
            <div class="stat-number">${j.projects || 0}</div>
            <div class="stat-label">Projects</div>
          </div>
          <div class="stat-item">
            <div class="stat-number">${j.applications || 0}</div>
            <div class="stat-label">Applications</div>
          </div>
        </div>
      `;
      
      statsContainer.style.opacity = '0';
      setTimeout(() => {
        statsContainer.style.transition = 'opacity 0.3s ease';
        statsContainer.style.opacity = '1';
      }, 100);
    })
    .catch(() => {
      console.log('Stats loading failed, using defaults');
    });
}

function initializeDashboard() {
  loadQuickStats();
  
  <?php if ($posted): ?>
  setTimeout(() => {
    showNotification('Project posted successfully!', 'success');
  }, 500);
  <?php endif; ?>
  
  <?php if ($updated): ?>
  setTimeout(() => {
    showNotification('Profile updated successfully!', 'success');
  }, 500);
  <?php endif; ?>
}

document.addEventListener('DOMContentLoaded', initializeDashboard);
</script>
</body>
</html>