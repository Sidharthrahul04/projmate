<?php
include('../includes/db_connect.php');
session_start();

if (!isset($_SESSION['student_id'])) {
    echo "<p style='color:red;'>Unauthorized access.</p>";
    exit;
}
?>

<div style="padding: 20px;">
    <h3>Project Analysis</h3>
    <p>No analysis available yet. Once projects are posted and we integrate resume parsing, this section will recommend projects based on your skills.</p>
</div>
