<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student applications with project and institution details
$stmt = $conn->prepare("
    SELECT a.id as app_id, a.status, a.applied_at,
           p.title, p.description, p.required_skills, p.deadline,
           i.institution_name, i.email as inst_email
    FROM applications a
    JOIN projects p ON a.project_id = p.id  
    JOIN institutions i ON p.institution_id = i.id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<h2><i class="fas fa-folder-open"></i> My Applications</h2>

<?php if ($applications->num_rows === 0): ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem; margin-bottom: 8px;">No Applications Yet</p>
        <p style="font-size: 0.9rem;">Start by browsing and applying to available projects.</p>
        <button class="btn" onclick="showSection('projects')" style="margin-top: 16px;">
            <i class="fas fa-search"></i> Browse Projects
        </button>
    </div>
<?php else: ?>
    <div class="applications-list">
        <?php while ($app = $applications->fetch_assoc()): ?>
            <div class="application-card">
                <div class="app-header">
                    <h4><?= htmlspecialchars($app['title']) ?></h4>
                    <span class="status-badge status-<?= $app['status'] ?>">
                        <?= ucfirst($app['status']) ?>
                    </span>
                </div>
                
                <div class="app-details">
                    <p><strong>Institution:</strong> <?= htmlspecialchars($app['institution_name']) ?></p>
                    <p><strong>Applied:</strong> <?= date('M d, Y', strtotime($app['applied_at'])) ?></p>
                    <p><strong>Description:</strong> <?= htmlspecialchars(substr($app['description'], 0, 150)) ?>...</p>
                    <?php if (!empty($app['required_skills'])): ?>
                        <p><strong>Required Skills:</strong> <?= htmlspecialchars($app['required_skills']) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="app-actions">
                    <?php if ($app['status'] === 'pending'): ?>
                        <button class="btn delete-btn" onclick="withdrawApplication(<?= $app['app_id'] ?>)">
                            <i class="fas fa-times"></i> Withdraw
                        </button>
                    <?php endif; ?>
                    
                    <a href="mailto:<?= htmlspecialchars($app['inst_email']) ?>?subject=Regarding <?= htmlspecialchars($app['title']) ?> Application" class="btn secondary">
                        <i class="fas fa-envelope"></i> Contact Institution
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<style>
.applications-list { margin-top: 20px; }
.application-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.app-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.app-header h4 { margin: 0; color: var(--primary-color); }
.app-details p { margin: 8px 0; color: var(--text-primary); }
.app-actions { margin-top: 16px; display: flex; gap: 12px; }
.status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
.status-accepted { background: #dcfce7; color: #166534; }
.status-rejected { background: #fef2f2; color: #dc2626; }
.status-pending { background: #fef3c7; color: #92400e; }
.delete-btn { background: #ef4444 !important; color: white; }
</style>

<script>
function withdrawApplication(appId) {
    if (confirm('Are you sure you want to withdraw this application?')) {
        fetch('withdraw_application.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ application_id: appId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Application withdrawn successfully!');
                showSection('my_projects'); // Refresh the view
            } else {
                alert('Failed to withdraw application: ' + data.error);
            }
        })
        .catch(() => alert('Network error, please try again.'));
    }
}
</script>
