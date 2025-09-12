<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$institution_id = (int) $_SESSION['user_id'];

// Fetch all projects posted by this institution
$stmt = $conn->prepare('SELECT * FROM projects WHERE institution_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $institution_id);
$stmt->execute();
$projects = $stmt->get_result();
?>

<h2><i class="fas fa-clipboard-list"></i> My Posted Projects</h2>

<?php if ($projects->num_rows === 0): ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem; margin-bottom: 8px;">No Projects Posted</p>
        <p style="font-size: 0.9rem;">Start by posting your first project to attract students.</p>
        <button class="btn" onclick="openProjectModal()" style="margin-top: 16px;">
            <i class="fas fa-plus"></i> Post Project
        </button>
    </div>
<?php else: ?>
    <div class="projects-grid">
        <?php while ($project = $projects->fetch_assoc()): ?>
            <div class="project-card" id="project-<?= $project['id'] ?>">
                <div class="project-header">
                    <h4><?= htmlspecialchars($project['title']) ?></h4>
                    <span class="project-date"><?= date('M d, Y', strtotime($project['created_at'])) ?></span>
                </div>
                
                <div class="project-content">
                    <p><?= nl2br(htmlspecialchars(substr($project['description'], 0, 200))) ?><?= strlen($project['description']) > 200 ? '...' : '' ?></p>
                    
                    <?php if (!empty($project['required_skills'])): ?>
                        <p><strong>Required Skills:</strong> <?= htmlspecialchars($project['required_skills']) ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['deadline'])): ?>
                        <p><strong>Deadline:</strong> <?= date('M d, Y', strtotime($project['deadline'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="project-actions">
                    <button class="btn secondary" onclick="viewApplications(<?= $project['id'] ?>)">
                        <i class="fas fa-users"></i> View Applications  
                    </button>
                    <button class="btn delete-btn" onclick="deleteProject(<?= $project['id'] ?>)">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<style>
.projects-grid { display: grid; gap: 20px; margin-top: 20px; }
.project-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.project-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.project-header h4 { margin: 0; color: var(--primary-color); }
.project-date { font-size: 0.8rem; color: var(--text-secondary); }
.project-content p { margin: 8px 0; color: var(--text-primary); }
.project-actions { margin-top: 16px; display: flex; gap: 12px; }
.delete-btn { background: #ef4444 !important; color: white; }
.delete-btn:hover { background: #dc2626 !important; }
</style>

<script>
function viewApplications(projectId) {
    loadFragment('view_applications.php?project_id=' + projectId);
}

function deleteProject(projectId) {
    if (confirm('Are you sure you want to delete this project? All applications will be lost.')) {
        fetch('delete_project.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({ project_id: projectId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Project deleted successfully!', 'success');
                document.getElementById('project-' + projectId).remove();
                
                if (document.querySelectorAll('.project-card').length === 0) {
                    showSection('my_projects');
                }
            } else {
                showNotification('Failed to delete project: ' + data.error, 'error');
            }
        })
        .catch(() => showNotification('Network error, please try again.', 'error'));
    }
}
</script>
