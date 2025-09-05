<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$institution_id = (int) $_SESSION['user_id'];
$project_id = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;

// Verify project belongs to this institution
$verify_stmt = $conn->prepare("SELECT title FROM projects WHERE id = ? AND institution_id = ?");
$verify_stmt->bind_param("ii", $project_id, $institution_id);
$verify_stmt->execute();
$project = $verify_stmt->get_result()->fetch_assoc();

if (!$project) {
    echo '<p>Project not found.</p>';
    exit;
}

// Fetch applications for this project
$stmt = $conn->prepare("
    SELECT a.id, a.status, a.applied_at, s.name, s.email, s.phone, s.resume_path 
    FROM applications a 
    JOIN students s ON a.student_id = s.id 
    WHERE a.project_id = ? 
    ORDER BY a.applied_at DESC
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<div style="margin-bottom: 20px;">
    <button class="btn secondary" onclick="showSection('my_projects')">
        <i class="fas fa-arrow-left"></i> Back to Projects
    </button>
</div>

<h2><i class="fas fa-users"></i> Applications for "<?= htmlspecialchars($project['title']) ?>"</h2>

<?php if ($applications->num_rows === 0): ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem;">No Applications Yet</p>
        <p style="font-size: 0.9rem;">Students will appear here when they apply to this project.</p>
    </div>
<?php else: ?>
    <div class="applications-list">
        <?php while ($app = $applications->fetch_assoc()): ?>
            <div class="application-card">
                <div class="app-header">
                    <div class="student-info">
                        <h4><?= htmlspecialchars($app['name']) ?></h4>
                        <p style="margin: 4px 0; color: var(--text-secondary);">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($app['email']) ?>
                            <?php if (!empty($app['phone'])): ?>
                                | <i class="fas fa-phone"></i> <?= htmlspecialchars($app['phone']) ?>
                            <?php endif; ?>
                        </p>
                        <p style="margin: 4px 0; font-size: 0.9rem; color: var(--text-secondary);">
                            Applied on <?= date('M d, Y', strtotime($app['applied_at'])) ?>
                        </p>
                    </div>
                    <div class="app-actions">
                        <?php if (!empty($app['resume_path'])): ?>
                            <a href="../uploads/<?= htmlspecialchars($app['resume_path']) ?>" target="_blank" class="btn secondary">
                                <i class="fas fa-file-pdf"></i> View Resume
                            </a>
                        <?php endif; ?>
                        <a href="mailto:<?= htmlspecialchars($app['email']) ?>?subject=Regarding Your Project Application" class="btn">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                        <?php if ($app['status'] === 'pending'): ?>
                            <button class="btn success" onclick="updateApplicationStatus(<?= $app['id'] ?>, 'accepted')">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="btn delete-btn" onclick="updateApplicationStatus(<?= $app['id'] ?>, 'rejected')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        <?php else: ?>
                            <span class="status-badge status-<?= $app['status'] ?>">
                                <?= ucfirst($app['status']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<style>
.applications-list { margin-top: 20px; }
.application-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
.app-header { display: flex; justify-content: space-between; align-items: flex-start; }
.student-info h4 { margin: 0; color: var(--primary-color); }
.app-actions { display: flex; gap: 8px; align-items: center; }
.status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
.status-accepted { background: #dcfce7; color: #166534; }
.status-rejected { background: #fef2f2; color: #dc2626; }
.status-pending { background: #fef3c7; color: #92400e; }
.success { background: #10b981 !important; color: white; }
</style>

<script>
function updateApplicationStatus(applicationId, status) {
    if (confirm('Are you sure you want to ' + status + ' this application?')) {
        fetch('update_application_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({ application_id: applicationId, status: status })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Application ' + status + ' successfully!', 'success');
                location.reload();
            } else {
                showNotification('Failed to update application: ' + data.error, 'error');
            }
        })
        .catch(() => showNotification('Network error, please try again.', 'error'));
    }
}
</script>
