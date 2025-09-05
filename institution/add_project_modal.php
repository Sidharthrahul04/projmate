<!-- Modal for adding a new project -->
<div id="projectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-plus-circle"></i> New Project</h3>
      <button class="close" onclick="closeProjectModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="projectForm">
        <div class="form-group">
          <label for="project-title"><i class="fas fa-heading"></i> Title</label>
          <input type="text" id="project-title" name="title" required>
        </div>
        <div class="form-group">
          <label for="project-desc"><i class="fas fa-align-left"></i> Description</label>
          <textarea id="project-desc" name="description" required></textarea>
        </div>
        <div class="form-group">
          <label for="project-skills"><i class="fas fa-tools"></i> Required Skills</label>
          <textarea id="project-skills" name="required_skills"></textarea>
        </div>
        <div class="form-group">
          <label for="project-deadline"><i class="fas fa-calendar-alt"></i> Deadline</label>
          <input type="date" id="project-deadline" name="deadline">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn secondary" onclick="closeProjectModal()">Cancel</button>
      <button class="btn" onclick="postProject()"><i class="fas fa-paper-plane"></i> Post</button>
    </div>
  </div>
</div>

<style>
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
}
.modal-content {
  background: #fff;
  margin: 5% auto;
  border-radius: 12px;
  max-width: 600px;
  overflow: hidden;
}
.modal-header, .modal-footer {
  padding: 16px 24px;
  background: var(--primary-color);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-header h3 { margin: 0; }
.close {
  font-size: 1.5rem;
  background: none;
  border: none;
  color: #fff;
  cursor: pointer;
}
.modal-body { padding: 24px; }
.form-group { margin-bottom: 16px; }
.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
}
.form-group input, .form-group textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-sizing: border-box;
}
.form-group textarea { resize: vertical; min-height: 100px; }
.modal-footer { gap: 12px; }
</style>

<script>
function openProjectModal() {
  document.getElementById('projectModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeProjectModal() {
  document.getElementById('projectModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

function postProject() {
  const form = document.getElementById('projectForm');
  const data = {
    title: form.title.value.trim(),
    description: form.description.value.trim(),
    required_skills: form.required_skills.value.trim(),
    deadline: form.deadline.value
  };

  if (!data.title || !data.description) {
    showNotification('Please fill out the project title and description.', 'error');
    return;
  }

  fetch('post_project.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(data)
  }).then(resp => resp.json())
    .then(resp => {
      if (resp.success) {
        closeProjectModal();
        showNotification('Project posted successfully!', 'success');
        showSection('my_projects');
        form.reset();
      } else {
        showNotification('Failed to post project: ' + resp.error, 'error');
      }
    })
    .catch(() => showNotification('Network error, please try again.', 'error'));
}
</script>
