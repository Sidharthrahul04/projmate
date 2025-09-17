<?php
header('Content-Type: text/html');
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo '<p style="color:red;">Unauthorized access.</p>';
    exit;
}

$student_id = $_SESSION['user_id'];

// 1. Get student's profile info and skills from the database
$student_stmt = $conn->prepare("
    SELECT
        s.experience_years,
        s.education_level,
        (SELECT GROUP_CONCAT(sk.skill_name) FROM student_skills sk WHERE sk.student_id = s.id) as skills_csv
    FROM students s
    WHERE s.id = ?
");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();

// Convert the comma-separated string of skills into an array
$student_skills = !empty($student['skills_csv']) ? array_map('trim', explode(',', $student['skills_csv'])) : [];

// 2. Get all projects the student has NOT applied to
$projects_stmt = $conn->prepare("
    SELECT p.*, COALESCE(i.institution_name, 'Institution') as institution_name, i.email as inst_email
    FROM projects p
    LEFT JOIN institutions i ON p.institution_id = i.id
    WHERE p.id NOT IN (
        SELECT project_id FROM applications WHERE student_id = ?
    )
    ORDER BY p.created_at DESC
");
$projects_stmt->bind_param("i", $student_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();
$projects_stmt->close();

// 3. PHP-based matching logic (updated to allow for zero matches)
function calculateProjectMatch($student_skills, $student_experience, $student_education, $project) {
    $project_skills = !empty($project['required_skills']) ? 
        array_map('trim', explode(',', $project['required_skills'])) : [];
    
    // If no skills are required, give a moderate default score
    if (empty($project_skills)) {
        return [
            'match_percentage' => 40, // Reduced from 50 to allow for better filtering
            'skill_match' => 40,
            'matching_skills' => [],
            'experience_bonus' => 0,
            'education_bonus' => 0
        ];
    }
    
    // Calculate skill match percentage
    $matching_skills = [];
    foreach ($project_skills as $required_skill) {
        foreach ($student_skills as $student_skill) {
            if (stripos($student_skill, $required_skill) !== false || 
                stripos($required_skill, $student_skill) !== false) {
                $matching_skills[] = $student_skill;
                break;
            }
        }
    }
    
    $skill_match_percentage = count($project_skills) > 0 ? 
        (count($matching_skills) / count($project_skills)) * 100 : 0;
    
    // Experience bonus (0-20 points)
    $experience_bonus = 0;
    $student_exp = intval($student_experience ?? 0);
    if ($student_exp >= 3) $experience_bonus = 20;
    elseif ($student_exp >= 1) $experience_bonus = 10;
    elseif ($student_exp > 0) $experience_bonus = 5;
    
    // Education bonus (0-15 points)
    $education_bonus = 0;
    $education = strtolower($student_education ?? '');
    if (strpos($education, 'phd') !== false || strpos($education, 'doctorate') !== false) {
        $education_bonus = 15;
    } elseif (strpos($education, 'master') !== false || strpos($education, 'ms') !== false || strpos($education, 'ma') !== false) {
        $education_bonus = 10;
    } elseif (strpos($education, 'bachelor') !== false || strpos($education, 'bs') !== false || strpos($education, 'ba') !== false) {
        $education_bonus = 5;
    }
    
    // Calculate final match percentage (max 100%)
    $total_match = min(100, $skill_match_percentage + $experience_bonus + $education_bonus);
    
    return [
        'match_percentage' => $total_match,
        'skill_match' => $skill_match_percentage,
        'matching_skills' => $matching_skills,
        'experience_bonus' => $experience_bonus,
        'education_bonus' => $education_bonus
    ];
}

// 4. Run the matching logic and collect recommended projects
$recommended_projects = [];
while ($project = $projects->fetch_assoc()) {
    $match_data = calculateProjectMatch(
        $student_skills, 
        $student['experience_years'] ?? 0, 
        $student['education_level'] ?? 'Not specified', 
        $project
    );
    
    // Only add projects with match percentage > 0
    if ($match_data['match_percentage'] > 0) {
        $project['match_data'] = $match_data;
        $recommended_projects[] = $project;
    }
}

// 5. Sort by match percentage (highest first)
if (!empty($recommended_projects)) {
    usort($recommended_projects, function($a, $b) {
        return ($b['match_data']['match_percentage'] ?? 0) <=> ($a['match_data']['match_percentage'] ?? 0);
    });
}
?>

<h2><i class="fas fa-magic"></i> Smart Project Recommendations</h2>
<p class="small-muted" style="margin-bottom: 20px;">Showing best matches based on your resume skills and profile.</p>

<?php if (empty($student_skills)): ?>
    <div style="text-align: center; padding: 40px; color: #64748b; background: #f8fafc; border-radius: 12px;">
        <i class="fas fa-robot" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem; margin-bottom: 8px;">Analyze Your Resume First</p>
        <p style="font-size: 0.9rem;">To get personalized recommendations, you need to analyze your resume first.</p>
        <button class="btn" onclick="analyzeResume()" style="margin-top: 16px;">
            <i class="fas fa-robot"></i> Analyze Resume
        </button>
    </div>

<?php elseif (empty($recommended_projects)): ?>
    <div style="text-align: center; padding: 40px; color: #64748b; background: #f8fafc; border-radius: 12px;">
        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 16px; color: #cbd5e1;"></i>
        <p style="font-size: 1.1rem; margin-bottom: 8px;">No Matching Projects Found</p>
        <p style="font-size: 0.9rem;">No projects match your current profile. Try updating your skills or check back later for new opportunities!</p>
    </div>

<?php else: ?>
    <!-- Display student profile summary -->
    <div class="profile-summary" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="margin: 0 0 8px 0; color: var(--primary-color);">
            <i class="fas fa-user"></i> Your Profile
        </h4>
        <div style="display: flex; gap: 24px; font-size: 0.9rem; color: #64748b;">
            <span><strong>Skills:</strong> <?= implode(', ', array_slice($student_skills, 0, 5)) ?><?= count($student_skills) > 5 ? ' +' . (count($student_skills) - 5) . ' more' : '' ?></span>
            <span><strong>Experience:</strong> <?= $student['experience_years'] ?? 0 ?> years</span>
            <span><strong>Education:</strong> <?= htmlspecialchars($student['education_level'] ?? 'Not specified') ?></span>
        </div>
    </div>

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
                        <i class="fas fa-star"></i>
                        <?= round($match_percentage) ?>% Match
                    </div>
                </div>
                
                <div class="institution-info">
                    <p><strong>Institution:</strong> <?= htmlspecialchars($project['institution_name']) ?></p>
                </div>
                
                <div class="project-content">
                    <p><?= nl2br(htmlspecialchars(substr($project['description'], 0, 150))) ?><?= strlen($project['description']) > 150 ? '...' : '' ?></p>
                    
                    <?php if (!empty($project['required_skills'])): ?>
                        <div class="skills-match">
                            <strong>Required Skills:</strong>
                            <div class="skills-tags">
                                <?php
                                $required_skills = array_map('trim', explode(',', $project['required_skills']));
                                $matching_skills = array_map('strtolower', $project['match_data']['matching_skills'] ?? []);
                                $student_skills_lower = array_map('strtolower', $student_skills);
                                
                                foreach ($required_skills as $skill):
                                    $skill_lower = strtolower(trim($skill));
                                    $is_matching = false;
                                    
                                    // Check if student has this skill (partial match)
                                    foreach ($student_skills_lower as $student_skill) {
                                        if (stripos($student_skill, $skill_lower) !== false || 
                                            stripos($skill_lower, $student_skill) !== false) {
                                            $is_matching = true;
                                            break;
                                        }
                                    }
                                    
                                    $class = $is_matching ? 'skill-tag matched' : 'skill-tag missing';
                                ?>
                                    <span class="<?= $class ?>">
                                        <?= htmlspecialchars($skill) ?>
                                        <?= $is_matching ? ' <i class="fas fa-check"></i>' : '' ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($project['deadline'])): ?>
                        <p style="margin-top: 12px;"><strong>Deadline:</strong> <?= date('M d, Y', strtotime($project['deadline'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="match-details">
                    <div class="match-breakdown">
                        <span>Skills: <?= round($project['match_data']['skill_match']) ?>%</span>
                        <span>Experience: +<?= round($project['match_data']['experience_bonus']) ?>%</span>
                        <span>Education: +<?= round($project['match_data']['education_bonus']) ?>%</span>
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
.project-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
.project-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.project-header h4 { margin: 0; color: var(--primary-color); line-height: 1.3; }
.project-content { flex-grow: 1; }

/* Match level styling */
.project-card.high-match { border-left: 4px solid #10b981; }
.project-card.medium-match { border-left: 4px solid #f59e0b; }
.project-card.low-match { border-left: 4px solid #ef4444; }

.match-score { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; display: flex; align-items: center; gap: 4px; }
.match-score.high-match { background: #d1fae5; color: #065f46; }
.match-score.medium-match { background: #fef3c7; color: #92400e; }
.match-score.low-match { background: #fef2f2; color: #dc2626; }

.institution-info p { margin: 0 0 12px 0; font-size: 0.9rem; color: #64748b; }
.project-content p { margin: 8px 0; color: var(--text-primary); }

.skills-match { margin-top: 12px; }
.skills-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.skill-tag { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; }
.skill-tag.matched { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.skill-tag.missing { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

.match-details { border-top: 1px solid #f1f5f9; margin-top: 16px; padding-top: 12px; }
.match-breakdown { display: flex; flex-wrap: wrap; gap: 8px; font-size: 0.75rem; color: #475569; }
.match-breakdown span { background: #f1f5f9; padding: 3px 8px; border-radius: 8px; }

.project-actions { margin-top: 16px; display: flex; gap: 12px; }

/* Applied button styling */
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
    
    // Get reference to the project card for removal
    const projectCard = button.closest('.project-card');
    
    fetch('apply_projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: projectId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success notification
            if (typeof showNotification === 'function') {
                showNotification('Application submitted successfully!', 'success');
            } else {
                alert('Application submitted successfully!');
            }
            
            // Option 1: Remove the project card with animation
            if (projectCard) {
                projectCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                projectCard.style.opacity = '0';
                projectCard.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    projectCard.remove();
                    
                    // Check if no more projects remain
                    const remainingProjects = document.querySelectorAll('.project-card');
                    if (remainingProjects.length === 0) {
                        showNoProjectsMessage();
                    }
                }, 300);
            }
            
            // Optional: Navigate to my projects after a delay
            setTimeout(() => {
                if (typeof showSection === 'function') {
                    showSection('my_projects');
                }
            }, 2000);
            
        } else {
            // Reset button on error
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
        // Reset button on network error
        button.disabled = false;
        button.innerHTML = originalText;
        
        if (typeof showNotification === 'function') {
            showNotification('Network error, please try again.', 'error');
        } else {
            alert('Network error, please try again.');
        }
    });
}

// Function to show message when no projects remain
function showNoProjectsMessage() {
    const projectsGrid = document.querySelector('.projects-grid');
    if (projectsGrid) {
        projectsGrid.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #64748b; background: #f8fafc; border-radius: 12px;">
                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 16px; color: #10b981;"></i>
                <p style="font-size: 1.1rem; margin-bottom: 8px;">All Done!</p>
                <p style="font-size: 0.9rem;">You've applied to all matching projects. Check back later for new opportunities!</p>
            </div>
        `;
    }
}

// Alternative approach: Reload just the recommendations section
function refreshRecommendations() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector('.projects-grid') || doc.querySelector('.profile-summary').parentNode;
            
            if (newContent) {
                const currentContainer = document.querySelector('.projects-grid')?.parentNode || 
                                      document.querySelector('.profile-summary')?.parentNode;
                if (currentContainer) {
                    currentContainer.innerHTML = newContent.innerHTML;
                }
            }
        })
        .catch(error => {
            console.error('Failed to refresh recommendations:', error);
        });
}
</script>