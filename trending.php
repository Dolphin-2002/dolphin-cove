<?php
$pageTitle = 'Trending';
$currentPage = 'trending';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

$timeFilter = $_GET['time'] ?? 'week';
$dateCondition = '';
if ($timeFilter === 'today') {
    $dateCondition = "AND p.created_at >= CURDATE()";
} elseif ($timeFilter === 'week') {
    $dateCondition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($timeFilter === 'month') {
    $dateCondition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Get trending posts (most liked)
$sql = "SELECT p.*, u.display_name AS author_name, u.username AS author_username, u.avatar_url,
        u.is_freelancer, u.freelancer_title,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS user_liked,
        (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id AND user_id = ?) AS user_saved
        FROM posts p JOIN users u ON p.author_id = u.id
        WHERE 1=1 $dateCondition
        ORDER BY like_count DESC, comment_count DESC
        LIMIT 30";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$trendingPosts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get trending skills/hashtags from posts
$skillSQL = "SELECT p.freelance_skills FROM posts p WHERE p.freelance_skills IS NOT NULL AND p.freelance_skills != '' $dateCondition";
$skillResults = $db->query($skillSQL);
$allSkills = [];
while ($row = $skillResults->fetch_assoc()) {
    foreach (explode(',', $row['freelance_skills']) as $sk) {
        $sk = trim($sk);
        if ($sk) {
            $allSkills[$sk] = ($allSkills[$sk] ?? 0) + 1;
        }
    }
}
arsort($allSkills);
$topHashtags = array_slice($allSkills, 0, 10, true);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);

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

    if ($action === 'save_post' && $postId) {
        $stmt = $db->prepare("SELECT id FROM saved_posts WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $stmt = $db->prepare("DELETE FROM saved_posts WHERE post_id = ? AND user_id = ?");
        } else {
            $stmt = $db->prepare("INSERT INTO saved_posts (post_id, user_id) VALUES (?, ?)");
        }
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: " . SITE_URL . "/trending.php?time=" . $timeFilter);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>🔥 Trending</h1>
        </div>

        <div class="tabs">
            <a href="?time=today" class="tab <?= $timeFilter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="?time=week" class="tab <?= $timeFilter === 'week' ? 'active' : '' ?>">This Week</a>
            <a href="?time=month" class="tab <?= $timeFilter === 'month' ? 'active' : '' ?>">This Month</a>
            <a href="?time=all" class="tab <?= $timeFilter === 'all' ? 'active' : '' ?>">All Time</a>
        </div>

        <div class="trending-layout">
            <div class="trending-feed">
                <?php if (empty($trendingPosts)): ?>
                    <div class="empty-state"><p>No trending posts for this period.</p></div>
                <?php endif; ?>
                <?php foreach ($trendingPosts as $idx => $post): ?>
                <div class="post-card card">
                    <div class="trending-rank">#<?= $idx + 1 ?></div>
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
                            <input type="hidden" name="action" value="save_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="post-action-btn"><?= $post['user_saved'] ? '🔖' : '📌' ?></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <aside class="trending-sidebar">
                <div class="card">
                    <h3>🏷️ Trending Topics</h3>
                    <?php if (empty($topHashtags)): ?>
                    <p class="text-muted">No trending topics</p>
                    <?php else: ?>
                    <div class="hashtag-list">
                        <?php foreach ($topHashtags as $tag => $count): ?>
                        <div class="hashtag-item">
                            <span class="hashtag-name">#<?= e($tag) ?></span>
                            <span class="hashtag-count"><?= $count ?> posts</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
