<?php
// student_navbar.php - include this at top of student pages (it's a fragment)
?>
<div class="navbar" style="background:#2f80ed;color:#fff;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;">
  <div style="font-weight:800;letter-spacing:0.6px">ProjMate</div>
  <div class="nav-links" style="display:flex;gap:10px;align-items:center">
    <button onclick="showSection('profile')" style="background:transparent;border:none;color:#fff;padding:8px 12px;cursor:pointer">Profile</button>
    <button onclick="showSection('projects')" style="background:transparent;border:none;color:#fff;padding:8px 12px;cursor:pointer">Projects</button>
    <button onclick="showSection('my_projects')" style="background:transparent;border:none;color:#fff;padding:8px 12px;cursor:pointer">Projects Done</button>
    <button onclick="showSection('notifications')" style="background:transparent;border:none;color:#fff;padding:8px 12px;cursor:pointer">Notifications</button>
    <button onclick="location.href='../logout.php'" style="background:#e05151;border:none;padding:8px 12px;border-radius:6px;color:#fff;cursor:pointer">Logout</button>
  </div>
</div>
