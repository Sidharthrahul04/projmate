<?php
header('Content-Type: text/html');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$student_id = $_SESSION['user_id'];

// UPDATED: Added application status check for current student
$stmt = $conn->prepare("
    SELECT p.*, 
           COALESCE(i.institution_name, 'Institution') as institution_name, 
           i.email as inst_email,
           (SELECT COUNT(*) FROM applications WHERE student_id = ? AND project_id = p.id) as already_applied
    FROM projects p 
    LEFT JOIN institutions i ON p.institution_id = i.id 
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$projects = $stmt->get_result();

// Debug: Show count
echo "<script>console.log('Total projects found: " . $projects->num_rows . "');</script>";
?>

<h2><i class="fas fa-search"></i> Available Projects</h2>

<?php if ($projects->num_rows === 0): ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem; margin-bottom: 8px;">No Available Projects</p>
        <p style="font-size: 0.9rem;">Check back later for new project opportunities.</p>
    </div>
<?php else: ?>
    <div class="projects-grid">
        <?php while ($project = $projects->fetch_assoc()): ?>
            <div class="project-card">
                <div class="project-header">
                    <h4><?= htmlspecialchars($project['title']) ?></h4>
                    <span class="institution-name"><?= htmlspecialchars($project['institution_name']) ?></span>
                </div>
                
                <div class="project-content">
                    <p><?= nl2br(htmlspecialchars(substr($project['description'], 0, 200))) ?><?= strlen($project['description']) > 200 ? '...' : '' ?></p>
                    
                    <?php if (!empty($project['required_skills'])): ?>
                        <div class="skills-section">
                            <strong>Required Skills:</strong>
                            <div class="skills-tags">
                                <?php 
                                $skills = explode(',', $project['required_skills']);
                                foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-tag"><?= htmlspecialchars(trim($skill)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['deadline'])): ?>
                        <p><strong>Deadline:</strong> <?= date('M d, Y', strtotime($project['deadline'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="project-actions">
                    <?php if ($project['already_applied'] > 0): ?>
                        <button class="btn applied-btn" disabled>
                            <i class="fas fa-check"></i> Applied
                        </button>
                    <?php else: ?>
                        <button class="btn" onclick="applyToProject(<?= $project['id'] ?>, this)">
                            <i class="fas fa-paper-plane"></i> Apply Now
                        </button>
                    <?php endif; ?>
                    
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
.institution-name { font-size: 0.9rem; color: var(--text-secondary); font-weight: 500; }
.project-content p { margin: 8px 0; color: var(--text-primary); }
.skills-section { margin: 12px 0; }
.skills-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.skill-tag { background: #f3f4f6; color: #374151; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; }
.project-actions { margin-top: 16px; display: flex; gap: 12px; }

/* ADDED: Styling for applied button */
.applied-btn {
    background-color: #10b981 !important;
    border-color: #10b981 !important;
    cursor: not-allowed !important;
    opacity: 0.8 !important;
}

.applied-btn:hover {
    background-color: #10b981 !important;
    transform: none !important;
}
</style>

<script>
function applyToProject(projectId, button) {
    if (!confirm('Are you sure you want to apply to this project?')) return;
    
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
    
    fetch('apply_projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: projectId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // UPDATED: Apply the applied button styling permanently
            button.innerHTML = '<i class="fas fa-check"></i> Applied';
            button.className = 'btn applied-btn';
            button.disabled = true;
            button.onclick = null; // Remove click handler
            
            // Show success notification if available
            if (typeof showNotification === 'function') {
                showNotification('Application submitted successfully!', 'success');
            } else {
                alert('Application submitted successfully!');
            }
            
            // Optional: Navigate to My Projects after a delay
            setTimeout(() => {
                if (typeof showSection === 'function') {
                    showSection('my_projects');
                }
            }, 2000);
        } else {
            // Restore button on error
            button.disabled = false;
            button.innerHTML = originalText;
            
            if (typeof showNotification === 'function') {
                showNotification('Failed to apply: ' + data.error, 'error');
            } else {
                alert('Failed to apply: ' + data.error);
            }
        }
    })
    .catch(error => {
        // Restore button on network error
        button.disabled = false;
        button.innerHTML = originalText;
        
        if (typeof showNotification === 'function') {
            showNotification('Network error, please try again.', 'error');
        } else {
            alert('Network error, please try again.');
        }
    });
}
</script>