<?php
header('Content-Type: text/html');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$student_id = $_SESSION['user_id'];

// Get student's analyzed skills
$stmt = $conn->prepare("SELECT skills, experience_years, education_level FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$student_skills = json_decode($student['skills'] ?? '[]', true);

// Get available projects
$projects_stmt = $conn->prepare("
    SELECT p.*, i.institution_name, i.email as inst_email
    FROM projects p 
    JOIN institutions i ON p.institution_id = i.id 
    WHERE p.id NOT IN (
        SELECT project_id FROM applications WHERE student_id = ?
    )
    ORDER BY p.created_at DESC
");
$projects_stmt->bind_param("i", $student_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

$recommended_projects = [];

while ($project = $projects->fetch_assoc()) {
    // Prepare data for Python matching script
    $student_data = json_encode([
        'skills' => $student_skills,
        'experience_years' => $student['experience_years'] ?? 0,
        'education_level' => $student['education_level'] ?? 'Not specified'
    ]);
    
    $project_data = json_encode([
        'required_skills' => $project['required_skills'] ?? '',
        'description' => $project['description'] ?? ''
    ]);
    
    // Run Python matching script
    $python_script = "../python_scripts/project_matcher.py";
    $command = "python \"$python_script\" '$student_data' '$project_data'";
    
    $match_output = shell_exec($command);
    $match_result = json_decode($match_output, true);
    
    if ($match_result && !isset($match_result['error'])) {
        $project['match_data'] = $match_result;
        $recommended_projects[] = $project;
    }
}

// Sort by match percentage (highest first)
usort($recommended_projects, function($a, $b) {
    return $b['match_data']['match_percentage'] <=> $a['match_data']['match_percentage'];
});
?>

<div style="margin-bottom: 20px;">
    <button class="btn secondary" onclick="analyzeMyResume()">
        <i class="fas fa-cog"></i> Analyze My Resume
    </button>
</div>

<h2><i class="fas fa-magic"></i> Smart Project Recommendations</h2>

<?php if (empty($recommended_projects)): ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <i class="fas fa-robot" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem; margin-bottom: 8px;">No Recommendations Available</p>
        <p style="font-size: 0.9rem;">Upload a resume and analyze it first to get personalized recommendations.</p>
        <button class="btn" onclick="analyzeMyResume()" style="margin-top: 16px;">
            <i class="fas fa-upload"></i> Analyze Resume
        </button>
    </div>
<?php else: ?>
    <div class="projects-grid">
        <?php foreach ($recommended_projects as $project): ?>
            <?php 
            $match_percentage = $project['match_data']['match_percentage'];
            $match_class = $match_percentage >= 70 ? 'high-match' : 
                          ($match_percentage >= 40 ? 'medium-match' : 'low-match');
            ?>
            <div class="project-card <?= $match_class ?>">
                <div class="project-header">
                    <h4><?= htmlspecialchars($project['title']) ?></h4>
                    <div class="match-score <?= $match_class ?>">
                        <?= round($match_percentage) ?>% Match
                    </div>
                </div>
                
                <div class="institution-info">
                    <p><strong>Institution:</strong> <?= htmlspecialchars($project['institution_name']) ?></p>
                </div>
                
                <div class="project-content">
                    <p><?= nl2br(htmlspecialchars(substr($project['description'], 0, 150))) ?>...</p>
                    
                    <?php if (!empty($project['required_skills'])): ?>
                        <div class="skills-match">
                            <strong>Required Skills:</strong>
                            <div class="skills-tags">
                                <?php 
                                $required_skills = explode(',', $project['required_skills']);
                                $matching_skills = $project['match_data']['matching_skills'] ?? [];
                                
                                foreach ($required_skills as $skill): 
                                    $skill = trim($skill);
                                    $is_matching = in_array($skill, $matching_skills);
                                    $class = $is_matching ? 'skill-tag matched' : 'skill-tag missing';
                                ?>
                                    <span class="<?= $class ?>"><?= htmlspecialchars($skill) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['deadline'])): ?>
                        <p><strong>Deadline:</strong> <?= date('M d, Y', strtotime($project['deadline'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="match-details">
                    <div class="match-breakdown">
                        <span>Skill Match: <?= round($project['match_data']['skill_match']) ?>%</span>
                        <span>Experience Bonus: +<?= round($project['match_data']['experience_bonus']) ?>%</span>
                        <span>Education Bonus: +<?= round($project['match_data']['education_bonus']) ?>%</span>
                    </div>
                </div>
                
                <div class="project-actions">
                    <button class="btn" onclick="applyToProject(<?= $project['id'] ?>, this)">
                        <i class="fas fa-paper-plane"></i> Apply Now
                    </button>
                    <a href="mailto:<?= htmlspecialchars($project['inst_email']) ?>?subject=Inquiry about <?= htmlspecialchars($project['title']) ?>" class="btn secondary">
                        <i class="fas fa-envelope"></i> Contact
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.projects-grid { display: grid; gap: 20px; margin-top: 20px; }
.project-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.project-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.project-header h4 { margin: 0; color: var(--primary-color); }
.project-card.high-match { border-left: 4px solid #10b981; }
.project-card.medium-match { border-left: 4px solid #f59e0b; }
.project-card.low-match { border-left: 4px solid #ef4444; }

.match-score {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.match-score.high-match { background: #d1fae5; color: #065f46; }
.match-score.medium-match { background: #fef3c7; color: #92400e; }
.match-score.low-match { background: #fef2f2; color: #dc2626; }

.skill-tag.matched { background: #d1fae5; color: #065f46; }
.skill-tag.missing { background: #fee2e2; color: #dc2626; }

.match-breakdown {
    display: flex;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 12px 0;
}

.match-breakdown span {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 8px;
}

.skills-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.skill-tag { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; }
.project-content p { margin: 8px 0; color: var(--text-primary); }
.project-actions { margin-top: 16px; display: flex; gap: 12px; }
</style>

<script>
function analyzeMyResume() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    
    fetch('analyze_resume.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Resume analyzed successfully! Refreshing recommendations...', 'success');
                location.reload();
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

function applyToProject(projectId, button) {
    if (!confirm('Are you sure you want to apply to this project?')) return;
    
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
    
    fetch('apply_projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ project_id: projectId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.innerHTML = '<i class="fas fa-check"></i> Applied';
            button.style.background = 'var(--success-color)';
            showNotification('Application submitted successfully!', 'success');
        } else {
            button.disabled = false;
            button.innerHTML = originalText;
            showNotification('Failed to apply: ' + data.error, 'error');
        }
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = originalText;
        showNotification('Network error, please try again.', 'error');
    });
}
</script>
