<?php
require_once __DIR__ . '/config.php';

// Update online status BEFORE destroying the session
if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['user_id'] = null;
unset($_SESSION['user_id']);
session_destroy();
session_start();
setFlash('success', 'You have been logged out');
header("Location: " . SITE_URL . "/index.php");
exit;
