<?php
function createNotification($conn, $user_type, $user_id, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("sis", $user_type, $user_id, $message);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getUnreadNotifications($conn, $user_type, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->bind_param("si", $user_type, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}
?>
