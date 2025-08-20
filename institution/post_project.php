<?php
// Set the content type to JSON so the JavaScript understands the response
header('Content-Type: application/json');

// Start session only if one isn't already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Security Check: Ensure an institution is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'institution') {
    // Not authorized, send an error response and exit
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

// Include your database connection file
include('../includes/db_connect.php');

// Get the raw JSON data sent from the JavaScript fetch request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

// --- Data Validation ---
// Check if the required data is present and not empty
if (!isset($data->title) || empty(trim($data->title))) {
    echo json_encode(['success' => false, 'error' => 'Project title is required.']);
    exit;
}
if (!isset($data->description) || empty(trim($data->description))) {
    echo json_encode(['success' => false, 'error' => 'Project description is required.']);
    exit;
}

// --- Database Insertion ---
// Prepare the SQL statement to prevent SQL injection
$sql = "INSERT INTO projects (institution_id, title, description, required_skills, deadline) VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Bind the parameters from the JSON data to the SQL statement
    // The institution_id comes from the session for security
    $institution_id = $_SESSION['user_id'];
    
    // Use null for the deadline if it's empty
    $deadline = !empty($data->deadline) ? $data->deadline : null;

    $stmt->bind_param(
        "issss",
        $institution_id,
        $data->title,
        $data->description,
        $data->required_skills,
        $deadline
    );

    // Execute the statement and check for success
    if ($stmt->execute()) {
        // Success! Send a success response.
        echo json_encode(['success' => true]);
    } else {
        // Failed to execute. Send a database error response.
        echo json_encode(['success' => false, 'error' => 'Database error: Could not save the project.']);
    }
    
    $stmt->close();
} else {
    // Failed to prepare the statement.
    echo json_encode(['success' => false, 'error' => 'Database error: Could not prepare the statement.']);
}

$conn->close();
?>