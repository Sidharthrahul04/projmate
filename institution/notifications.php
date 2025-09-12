<?php
// notifications.php - Create this file in your institution directory
header('Content-Type: text/html');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$institution_id = $_SESSION['user_id'];

// Fetch all notifications for this institution (both read and unread)
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_type = 'institution' AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->bind_param("i", $institution_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Fetch projects for this institution (for linking notifications to projects)
$projects_stmt = $conn->prepare("SELECT id, title FROM projects WHERE institution_id = ?");
$projects_stmt->bind_param("i", $institution_id);
$projects_stmt->execute();
$projects_result = $projects_stmt->get_result();
$projects = [];
while ($project = $projects_result->fetch_assoc()) {
    $projects[$project['id']] = $project;
}
$projects_stmt->close();

// Mark all unread notifications as read when viewing this page
$mark_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'institution' AND user_id = ? AND is_read = 0");
$mark_read->bind_param("i", $institution_id);
$mark_read->execute();
$mark_read->close();
?>

<h2>
  <i class="fas fa-bell" style="margin-right: 12px;"></i>
  Notifications
</h2>
<p class="small-muted" style="margin-bottom: 20px;">
  Stay updated with new student applications and system notifications.
</p>

<?php if ($notifications->num_rows === 0): ?>
<div style="text-align: center; padding: 40px; color: #64748b;">
  <i class="fas fa-bell-slash" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
  <p style="font-size: 1.1rem; margin-bottom: 8px;">No Notifications</p>
  <p style="font-size: 0.9rem;">You'll receive notifications when students apply to your projects.</p>
</div>
<?php else: ?>

<div style="display: grid; gap: 12px; margin-top: 20px;">
  <?php while ($notification = $notifications->fetch_assoc()): ?>
  <div class="notification-card" style="
    background: <?= $notification['is_read'] ? 'white' : '#f0f9ff' ?>;
    border: 1px solid <?= $notification['is_read'] ? '#e5e7eb' : '#3b82f6' ?>;
    border-left: 4px solid <?= $notification['is_read'] ? '#e5e7eb' : '#3b82f6' ?>;
    border-radius: 8px;
    padding: 16px;
    position: relative;
    transition: all 0.2s ease;
  ">
    <?php if (!$notification['is_read']): ?>
    <div style="
      position: absolute;
      top: 12px;
      right: 12px;
      width: 8px;
      height: 8px;
      background: #3b82f6;
      border-radius: 50%;
    "></div>
    <?php endif; ?>
    
    <div style="display: flex; align-items: flex-start; gap: 12px;">
      <div style="
        width: 40px;
        height: 40px;
        background: rgba(59, 130, 246, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      ">
        <i class="fas fa-user-graduate" style="color: #3b82f6; font-size: 1rem;"></i>
      </div>
      
      <div style="flex: 1; min-width: 0;">
        <div style="
          color: var(--text-primary);
          font-size: 0.95rem;
          line-height: 1.5;
          margin-bottom: 6px;
        ">
          <?= nl2br(htmlspecialchars($notification['message'])) ?>
        </div>
        
        <div style="
          color: var(--text-secondary);
          font-size: 0.8rem;
          display: flex;
          align-items: center;
          gap: 8px;
        ">
          <i class="fas fa-clock"></i>
          <?= date('M j, Y \a\t g:i A', strtotime($notification['created_at'])) ?>
          
          <?php if (!$notification['is_read']): ?>
          <span style="
            background: #3b82f6;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
          ">NEW</span>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Action buttons for application notifications -->
      <?php if (strpos($notification['message'], 'New application from') !== false): ?>
      <div style="display: flex; gap: 8px; align-items: center;">
        <?php
        // Extract project info from the message to create action buttons
        if (preg_match('/for project: (.+)$/', $notification['message'], $matches)) {
          $project_title = trim($matches[1]);
        }
        ?>
        <button class="btn secondary" style="font-size: 0.8rem; padding: 6px 12px;" onclick="showSection('my_projects')">
          <i class="fas fa-eye"></i>
          View Applications
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<div style="margin-top: 32px; text-align: center; padding: 20px; background: rgba(59, 130, 246, 0.05); border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.1);">
  <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">
    <i class="fas fa-info-circle" style="margin-right: 8px; color: #3b82f6;"></i>
    Notifications are automatically marked as read when you view this page.
  </p>
</div>

<?php endif; ?>

<style>
.notification-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>