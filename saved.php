<?php
$pageTitle = 'Saved Posts';
$currentPage = 'saved';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);

    if ($action === 'unsave' && $postId) {
        $stmt = $db->prepare("DELETE FROM saved_posts WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Post removed from saved');
    }

    if ($action === 'like_post' && $postId) {
        $stmt = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        } else {
            $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
        }
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: " . SITE_URL . "/saved.php");
    exit;
}

$filter = $_GET['filter'] ?? '';

$where = "";
if ($filter === 'freelance') {
    $where = " AND p.is_freelance_post = 1";
} elseif ($filter === 'regular') {
    $where = " AND p.is_freelance_post = 0";
}

$sql = "SELECT p.*, u.display_name AS author_name, u.username AS author_username, u.avatar_url,
        u.is_freelancer, sp.created_at AS saved_at,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS user_liked
        FROM saved_posts sp
        JOIN posts p ON sp.post_id = p.id
        JOIN users u ON p.author_id = u.id
        WHERE sp.user_id = ? $where
        ORDER BY sp.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$savedPosts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>🔖 Saved Posts</h1>
                <p><?= count($savedPosts) ?> saved items</p>
            </div>
        </div>

        <div class="tabs">
            <a href="?filter=" class="tab <?= !$filter ? 'active' : '' ?>">All</a>
            <a href="?filter=regular" class="tab <?= $filter === 'regular' ? 'active' : '' ?>">Regular Posts</a>
            <a href="?filter=freelance" class="tab <?= $filter === 'freelance' ? 'active' : '' ?>">Freelance Posts</a>
        </div>

        <?php if (empty($savedPosts)): ?>
            <div class="empty-state">
                <p>No saved posts yet.</p>
                <a href="<?= SITE_URL ?>/home.php" class="btn btn-primary">Browse Feed</a>
            </div>
        <?php else: ?>
        <div class="feed">
            <?php foreach ($savedPosts as $post): ?>
            <div class="post-card card">
                <div class="post-header">
                    <?= userAvatar($post['avatar_url'], $post['author_name'], 'small') ?>
                    <div>
                        <a href="<?= SITE_URL ?>/profile.php?username=<?= e($post['author_username']) ?>"><strong><?= e($post['author_name']) ?></strong></a>
                        <?php if ($post['is_freelancer']): ?>
                        <span class="badge-freelancer">Freelancer</span>
                        <?php endif; ?>
                        <span class="post-time"><?= timeAgo($post['created_at']) ?></span>
                    </div>
                </div>
                <div class="post-content">
                    <p><?= nl2br(e($post['content'])) ?></p>
                    <?php if ($post['image']): ?>
                    <img src="<?= e($post['image']) ?>" alt="" class="post-image">
                    <?php endif; ?>
                </div>
                <?php if ($post['freelance_skills']): ?>
                <div class="job-skills">
                    <?php foreach (explode(',', $post['freelance_skills']) as $sk): $sk = trim($sk); if ($sk): ?>
                    <span class="skill-chip"><?= e($sk) ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="post-actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="like_post">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <button type="submit" class="post-action-btn <?= $post['user_liked'] ? 'liked' : '' ?>">
                            <?= $post['user_liked'] ? '❤️' : '🤍' ?> <?= $post['like_count'] ?>
                        </button>
                    </form>
                    <span class="post-action-btn">💬 <?= $post['comment_count'] ?></span>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="unsave">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <button type="submit" class="post-action-btn" title="Remove from saved">🗑️ Unsave</button>
                    </form>
                </div>
                <div class="saved-info">
                    <small>Saved <?= timeAgo($post['saved_at']) ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
