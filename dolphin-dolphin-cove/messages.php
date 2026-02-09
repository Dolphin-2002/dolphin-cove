<?php
$pageTitle = 'Messages';
$currentPage = 'messages';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $receiverId = (int)$_POST['receiver_id'];
    $content = trim($_POST['content'] ?? '');

    if (!empty($content) && $receiverId) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $userId, $receiverId, $content);
        $stmt->execute();
        $stmt->close();

        // Notify
        $user = getCurrentUser();
        $notifContent = 'sent you a message';
        $nStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'message', ?, ?)");
        $link = SITE_URL . '/messages.php?chat=' . $userId;
        $nStmt->bind_param("iiss", $receiverId, $userId, $notifContent, $link);
        $nStmt->execute();
        $nStmt->close();
    }
    header("Location: " . SITE_URL . "/messages.php?chat=" . $receiverId);
    exit;
}

// Get conversations (unique users we've messaged or who messaged us)
$convSQL = "SELECT u.id, u.display_name, u.username, u.avatar_url, u.is_online,
            (SELECT content FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) AS last_message,
            (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) AS last_message_time,
            (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) AS unread_count
            FROM users u
            WHERE u.id != ? AND (
                u.id IN (SELECT receiver_id FROM messages WHERE sender_id = ?)
                OR u.id IN (SELECT sender_id FROM messages WHERE receiver_id = ?)
            )
            ORDER BY last_message_time DESC";
$stmt = $db->prepare($convSQL);
$stmt->bind_param("iiiiiiii", $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Active chat
$chatUserId = (int)($_GET['chat'] ?? 0);
$chatUser = null;
$chatMessages = [];

if ($chatUserId) {
    // Get chat user info
    $stmt = $db->prepare("SELECT id, display_name, username, avatar_url, is_online FROM users WHERE id = ?");
    $stmt->bind_param("i", $chatUserId);
    $stmt->execute();
    $chatUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($chatUser) {
        // Mark messages as read
        $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt->bind_param("ii", $chatUserId, $userId);
        $stmt->execute();
        $stmt->close();

        // Get messages
        $stmt = $db->prepare("SELECT m.*, u.display_name AS sender_name, u.avatar_url AS sender_avatar
                              FROM messages m JOIN users u ON m.sender_id = u.id
                              WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                              ORDER BY m.created_at ASC");
        $stmt->bind_param("iiii", $userId, $chatUserId, $chatUserId, $userId);
        $stmt->execute();
        $chatMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="messages-layout">
            <!-- Conversations panel -->
            <div class="conversations-panel">
                <div class="conversations-header">
                    <h2>Messages</h2>
                </div>
                <div class="conversations-list">
                    <?php if (empty($conversations)): ?>
                        <p class="empty-text" style="padding:1rem;">No conversations yet</p>
                    <?php endif; ?>
                    <?php foreach ($conversations as $conv): ?>
                    <a href="?chat=<?= $conv['id'] ?>" class="conversation-item <?= $chatUserId == $conv['id'] ? 'active' : '' ?>">
                        <div class="conversation-avatar">
                            <?= userAvatar($conv['avatar_url'], $conv['display_name'], 'small') ?>
                            <?php if ($conv['is_online']): ?>
                            <span class="online-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="conversation-info">
                            <strong><?= e($conv['display_name']) ?></strong>
                            <p class="last-message"><?= e(substr($conv['last_message'] ?? '', 0, 50)) ?></p>
                        </div>
                        <div class="conversation-meta">
                            <span class="time"><?= $conv['last_message_time'] ? timeAgo($conv['last_message_time']) : '' ?></span>
                            <?php if ($conv['unread_count'] > 0): ?>
                            <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chat area -->
            <div class="chat-area">
                <?php if ($chatUser): ?>
                <div class="chat-header">
                    <div class="chat-user-info">
                        <?= userAvatar($chatUser['avatar_url'], $chatUser['display_name'], 'small') ?>
                        <div>
                            <strong><?= e($chatUser['display_name']) ?></strong>
                            <span class="<?= $chatUser['is_online'] ? 'status-online' : 'status-offline' ?>">
                                <?= $chatUser['is_online'] ? 'Online' : 'Offline' ?>
                            </span>
                        </div>
                    </div>
                    <a href="<?= SITE_URL ?>/profile.php?username=<?= e($chatUser['username']) ?>" class="btn btn-sm btn-outline">View Profile</a>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <?php foreach ($chatMessages as $msg): ?>
                    <div class="message <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                        <div class="message-bubble">
                            <p><?= nl2br(e($msg['content'])) ?></p>
                            <span class="message-time"><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <form class="chat-input-form" method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="receiver_id" value="<?= $chatUserId ?>">
                    <input type="text" name="content" placeholder="Type a message..." autocomplete="off" required>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
                <?php else: ?>
                <div class="chat-empty">
                    <h3>💬</h3>
                    <p>Select a conversation or start a new one from someone's profile.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// Auto-scroll to bottom of chat
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
