<?php
$pageTitle = 'Notifications';
$currentPage = 'notifications';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle mark all read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'All notifications marked as read');
    header("Location: " . SITE_URL . "/notifications.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $nid = (int)$_POST['notification_id'];
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $nid, $userId);
    $stmt->execute();
    $stmt->close();
}

$filter = $_GET['filter'] ?? 'all';

$where = "WHERE n.user_id = ?";
$params = [$userId];
$types = "i";

if ($filter === 'mentions') {
    $where .= " AND n.type = 'mention'";
} elseif ($filter === 'jobs') {
    $where .= " AND n.type IN ('job_application', 'job_hired')";
} elseif ($filter === 'network') {
    $where .= " AND n.type IN ('connection_request', 'connection_accepted')";
}

$sql = "SELECT n.*, u.display_name AS sender_name, u.username AS sender_username, u.avatar_url AS sender_avatar
        FROM notifications n LEFT JOIN users u ON n.from_user_id = u.id
        $where ORDER BY n.created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Notifications</h1>
                <p><?= $unreadCount ?> unread</p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <form method="POST">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline">✓ Mark All Read</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="tabs">
            <a href="?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?filter=mentions" class="tab <?= $filter === 'mentions' ? 'active' : '' ?>">Mentions</a>
            <a href="?filter=jobs" class="tab <?= $filter === 'jobs' ? 'active' : '' ?>">Jobs</a>
            <a href="?filter=network" class="tab <?= $filter === 'network' ? 'active' : '' ?>">Network</a>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state"><p>No notifications yet.</p></div>
        <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif): ?>
            <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                <?php if ($notif['sender_avatar'] || $notif['sender_name']): ?>
                <?= userAvatar($notif['sender_avatar'], $notif['sender_name'] ?? 'User', 'small') ?>
                <?php else: ?>
                <span class="notification-icon">🔔</span>
                <?php endif; ?>
                <div class="notification-content">
                    <p>
                        <?php if ($notif['sender_username']): ?>
                        <a href="<?= SITE_URL ?>/profile.php?username=<?= e($notif['sender_username']) ?>"><strong><?= e($notif['sender_name']) ?></strong></a>
                        <?php endif; ?>
                        <?= e($notif['message']) ?>
                    </p>
                    <span class="notification-time"><?= timeAgo($notif['created_at']) ?></span>
                </div>
                <?php if (!$notif['is_read']): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                    <button type="submit" class="btn btn-xs" title="Mark as read">•</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
