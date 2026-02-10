<?php
$pageTitle = 'Home';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle new post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_post') {
        $content = trim($_POST['content'] ?? '');
        $isFreelance = isset($_POST['is_freelance']) ? 1 : 0;
        $freelanceTitle = trim($_POST['freelance_title'] ?? '');
        $freelanceBudget = trim($_POST['freelance_budget'] ?? '');
        $freelanceDeadline = trim($_POST['freelance_deadline'] ?? '');
        $freelanceSkills = trim($_POST['freelance_skills'] ?? '');
        $freelanceType = $_POST['freelance_type'] ?? 'fixed';

        if (!empty($content)) {
            // Handle image upload
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('post_') . '.' . $ext;
                $targetPath = UPLOAD_DIR . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = SITE_URL . '/uploads/' . $filename;
                }
            }

            $stmt = $db->prepare("INSERT INTO posts (author_id, content, image, is_freelance_post, freelance_title, freelance_budget, freelance_deadline, freelance_skills, freelance_project_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississsss", $userId, $content, $imagePath, $isFreelance, $freelanceTitle, $freelanceBudget, $freelanceDeadline, $freelanceSkills, $freelanceType);
            $stmt->execute();
            $stmt->close();
            setFlash('success', $isFreelance ? 'Freelance post created!' : 'Post shared successfully!');
        } else {
            setFlash('error', 'Please write something before posting');
        }
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }

    if ($_POST['action'] === 'like_post') {
        $postId = (int)$_POST['post_id'];
        // Toggle like
        $checkStmt = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $checkStmt->bind_param("ii", $postId, $userId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $delStmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
            $delStmt->bind_param("ii", $postId, $userId);
            $delStmt->execute();
            $delStmt->close();
        } else {
            $insStmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
            $insStmt->bind_param("ii", $postId, $userId);
            $insStmt->execute();
            $insStmt->close();

            // Create notification
            $postAuthorStmt = $db->prepare("SELECT author_id FROM posts WHERE id = ?");
            $postAuthorStmt->bind_param("i", $postId);
            $postAuthorStmt->execute();
            $postAuthor = $postAuthorStmt->get_result()->fetch_assoc();
            $postAuthorStmt->close();
            if ($postAuthor && $postAuthor['author_id'] != $userId) {
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'like', 'liked your post', ?)");
                $link = SITE_URL . "/home.php#post-" . $postId;
                $notifStmt->bind_param("iis", $postAuthor['author_id'], $userId, $link);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }

    if ($_POST['action'] === 'add_comment') {
        $postId = (int)$_POST['post_id'];
        $commentContent = trim($_POST['comment_content'] ?? '');
        if (!empty($commentContent)) {
            $stmt = $db->prepare("INSERT INTO comments (post_id, author_id, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $postId, $userId, $commentContent);
            $stmt->execute();
            $stmt->close();

            // Notification
            $postAuthorStmt = $db->prepare("SELECT author_id FROM posts WHERE id = ?");
            $postAuthorStmt->bind_param("i", $postId);
            $postAuthorStmt->execute();
            $postAuthor = $postAuthorStmt->get_result()->fetch_assoc();
            $postAuthorStmt->close();
            if ($postAuthor && $postAuthor['author_id'] != $userId) {
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'comment', 'commented on your post', ?)");
                $link = SITE_URL . "/home.php#post-" . $postId;
                $notifStmt->bind_param("iis", $postAuthor['author_id'], $userId, $link);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }

    if ($_POST['action'] === 'like_comment') {
        $commentId = (int)$_POST['comment_id'];
        $stmt = $db->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $commentId, $userId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            $stmt = $db->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        } else {
            $stmt = $db->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        }
        $stmt->bind_param("ii", $commentId, $userId);
        $stmt->execute();
        $stmt->close();
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }

    if ($_POST['action'] === 'delete_post') {
        $postId = (int)$_POST['post_id'];
        $stmt = $db->prepare("DELETE FROM posts WHERE id = ? AND author_id = ?");
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Post deleted');
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }

    if ($_POST['action'] === 'save_post') {
        $postId = (int)$_POST['post_id'];
        $checkStmt = $db->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
        $checkStmt->bind_param("ii", $userId, $postId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $delStmt = $db->prepare("DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?");
            $delStmt->bind_param("ii", $userId, $postId);
            $delStmt->execute();
            $delStmt->close();
        } else {
            $insStmt = $db->prepare("INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)");
            $insStmt->bind_param("ii", $userId, $postId);
            $insStmt->execute();
            $insStmt->close();
        }
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }
}

// Fetch posts with authors
$posts = $db->query("
    SELECT p.*, u.display_name, u.username, u.avatar_url, u.is_freelancer,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = $userId) as user_liked,
        (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id AND user_id = $userId) as user_saved
    FROM posts p
    JOIN users u ON p.author_id = u.id
    WHERE p.visibility = 'public'
    ORDER BY p.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Get saved post IDs for current user
$savedIds = [];
$savedResult = $db->query("SELECT post_id FROM saved_posts WHERE user_id = $userId");
while ($row = $savedResult->fetch_assoc()) {
    $savedIds[] = $row['post_id'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="home-page authenticated">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="feed">
            <!-- Create Post -->
            <div class="create-post">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_post">
                    <div class="create-post-header">
                        <?= userAvatar($currentUser['avatar_url'] ?? null, $currentUser['display_name'], 'small') ?>
                        <textarea name="content" placeholder="Share your thoughts, code snippets, or opportunities..." rows="3" required></textarea>
                    </div>

                    <div id="freelance-form" class="freelance-form" style="display:none;">
                        <div class="freelance-form-header">
                            <h4>🌊 Freelance Opportunity Details</h4>
                            <button type="button" class="close-btn" onclick="toggleFreelance()">&times;</button>
                        </div>
                        <input type="hidden" name="is_freelance" id="is_freelance" value="0">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Job Title</label>
                                <input type="text" name="freelance_title" placeholder="e.g., Senior React Developer">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>💲 Budget</label>
                                    <input type="text" name="freelance_budget" placeholder="e.g., $5,000 or $50/hr">
                                </div>
                                <div class="form-group">
                                    <label>🕐 Timeline</label>
                                    <input type="text" name="freelance_deadline" placeholder="e.g., 2 weeks">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Required Skills (comma separated)</label>
                                <input type="text" name="freelance_skills" placeholder="e.g., React, TypeScript, Node.js">
                            </div>
                            <div class="form-group">
                                <label>Project Type</label>
                                <div class="project-type-toggle">
                                    <button type="button" class="type-btn active" onclick="setProjectType('fixed', this)">Fixed Price</button>
                                    <button type="button" class="type-btn" onclick="setProjectType('hourly', this)">Hourly Rate</button>
                                </div>
                                <input type="hidden" name="freelance_type" id="freelance_type" value="fixed">
                            </div>
                        </div>
                    </div>

                    <div class="create-post-footer">
                        <div class="post-options">
                            <label class="option-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span>Image</span>
                                <input type="file" name="image" accept="image/*" style="display:none" onchange="this.closest('.option-btn').querySelector('span').textContent = this.files[0]?.name || 'Image'">
                            </label>
                            <button type="button" class="option-btn" onclick="toggleFreelance()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                <span>Freelance</span>
                            </button>
                        </div>
                        <button type="submit" class="btn btn-primary">Post</button>
                    </div>
                </form>
            </div>

            <!-- Posts List -->
            <div class="posts-list">
                <?php if (empty($posts)): ?>
                    <div class="empty-state">
                        <h3>No posts yet</h3>
                        <p>Be the first to share something with the community!</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($posts as $post):
                    // Get comments for this post
                    $commentsResult = $db->query("SELECT c.*, u.display_name, u.username, u.avatar_url,
                        (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS comment_like_count,
                        (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = $userId) AS user_liked_comment
                        FROM comments c JOIN users u ON c.author_id = u.id WHERE c.post_id = {$post['id']} ORDER BY c.created_at ASC");
                    $comments = $commentsResult->fetch_all(MYSQLI_ASSOC);
                    $hasLiked = $post['user_liked'] > 0;
                    $isSaved = $post['user_saved'] > 0;
                    $freelanceSkills = array_filter(explode(',', $post['freelance_skills'] ?? ''));
                ?>
                <article class="post-card <?= $post['is_freelance_post'] ? 'freelance-post' : '' ?>" id="post-<?= $post['id'] ?>">
                    <!-- Post Header -->
                    <div class="post-header">
                        <div class="post-author">
                            <a href="<?= SITE_URL ?>/profile.php?username=<?= e($post['username']) ?>">
                                <?= userAvatar($post['avatar_url'], $post['display_name'], 'small') ?>
                            </a>
                            <div class="author-info">
                                <div class="author-name">
                                    <h4><a href="<?= SITE_URL ?>/profile.php?username=<?= e($post['username']) ?>"><?= e($post['display_name']) ?></a></h4>
                                    <?php if ($post['is_freelancer']): ?>
                                        <span class="freelancer-tag">&#x1F4BB;</span>
                                    <?php endif; ?>
                                </div>
                                <p>@<?= e($post['username']) ?> &bull; <?= timeAgo($post['created_at']) ?></p>
                            </div>
                        </div>
                        <?php if ($post['author_id'] == $userId): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="more-btn" title="Delete post" onclick="return confirm('Delete this post?')">🗑️</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($post['is_freelance_post'] && $post['freelance_title']): ?>
                    <div class="freelance-badge-container">
                        <span class="freelance-label">🌊 Freelance Opportunity</span>
                    </div>
                    <?php endif; ?>

                    <div class="post-content">
                        <p><?= nl2br(e($post['content'])) ?></p>
                    </div>

                    <?php if ($post['is_freelance_post'] && $post['freelance_title']): ?>
                    <div class="freelance-details">
                        <h3><?= e($post['freelance_title']) ?></h3>
                        <div class="freelance-meta">
                            <?php if ($post['freelance_budget']): ?>
                            <span class="meta-item">💲 <?= e($post['freelance_budget']) ?></span>
                            <?php endif; ?>
                            <?php if ($post['freelance_deadline']): ?>
                            <span class="meta-item">🕐 <?= e($post['freelance_deadline']) ?></span>
                            <?php endif; ?>
                            <span class="project-type <?= $post['freelance_project_type'] ?>"><?= $post['freelance_project_type'] === 'fixed' ? 'Fixed Price' : 'Hourly Rate' ?></span>
                        </div>
                        <?php if (!empty($freelanceSkills)): ?>
                        <div class="freelance-skills">
                            <?php foreach ($freelanceSkills as $skill): ?>
                                <span class="skill-chip"><?= e(trim($skill)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($post['image']): ?>
                    <div class="post-image">
                        <img src="<?= e($post['image']) ?>" alt="Post content">
                    </div>
                    <?php endif; ?>

                    <div class="post-stats">
                        <span><?= $post['like_count'] ?> waves</span>
                        <span><?= $post['comment_count'] ?> comments</span>
                    </div>

                    <div class="post-actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="like_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="action-btn <?= $hasLiked ? 'liked' : '' ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= $hasLiked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                <span>Wave</span>
                            </button>
                        </form>
                        <button class="action-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                            <span>Comment</span>
                        </button>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="save_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="action-btn <?= $isSaved ? 'bookmarked' : '' ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= $isSaved ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                                <span>Save</span>
                            </button>
                        </form>
                    </div>

                    <!-- Comments Section -->
                    <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display:none">
                        <form method="POST" class="comment-input">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <div class="avatar-tiny"><?= strtoupper(substr($currentUser['display_name'], 0, 1)) ?></div>
                            <input type="text" name="comment_content" placeholder="Write a comment..." required>
                            <button type="submit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </form>
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <?= userAvatar($comment['avatar_url'], $comment['display_name'], 'xs') ?>
                                <div class="comment-body">
                                    <div class="comment-header">
                                        <strong><?= e($comment['display_name']) ?></strong>
                                        <span class="comment-time"><?= timeAgo($comment['created_at']) ?></span>
                                    </div>
                                    <p><?= e($comment['content']) ?></p>
                                    <div class="comment-actions">
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="like_comment">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <button type="submit" class="comment-like-btn <?= $comment['user_liked_comment'] ? 'liked' : '' ?>">
                                                <?= $comment['user_liked_comment'] ? '❤️' : '🤍' ?>
                                                <?php if ($comment['comment_like_count'] > 0): ?>
                                                <span><?= $comment['comment_like_count'] ?></span>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Right Sidebar -->
    <aside class="right-sidebar">
        <div class="sidebar-card">
            <div class="card-header">
                <span>💼</span>
                <h3>Hot Opportunities</h3>
            </div>
            <?php
            $hotJobs = $db->query("SELECT fj.*, u.display_name as poster_name FROM freelance_jobs fj JOIN users u ON fj.poster_id = u.id WHERE fj.status = 'open' ORDER BY fj.created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
            ?>
            <?php if (!empty($hotJobs)): ?>
                <?php foreach ($hotJobs as $hj): ?>
                <a href="<?= SITE_URL ?>/job_detail.php?id=<?= $hj['id'] ?>" class="sidebar-job-item">
                    <strong><?= e($hj['title']) ?></strong>
                    <span>$<?= number_format($hj['budget_min']) ?> – $<?= number_format($hj['budget_max']) ?></span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-small">
                    <p>No jobs available yet</p>
                </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/freelance.php" class="see-all-link">Browse all jobs &rarr;</a>
        </div>

        <div class="sidebar-card">
            <div class="card-header">
                <span>⚡</span>
                <h3>Developers to Follow</h3>
            </div>
            <?php
            $suggestedUsers = $db->query("SELECT * FROM users WHERE id != $userId ORDER BY RAND() LIMIT 3")->fetch_all(MYSQLI_ASSOC);
            ?>
            <?php if (!empty($suggestedUsers)): ?>
                <?php foreach ($suggestedUsers as $su): ?>
                <div class="sidebar-user-item">
                    <a href="<?= SITE_URL ?>/profile.php?username=<?= e($su['username']) ?>">
                        <?= userAvatar($su['avatar_url'], $su['display_name'], 'small') ?>
                        <span><?= e($su['display_name']) ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-small"><p>No suggestions yet</p></div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/network.php" class="see-all-link">Explore network &rarr;</a>
        </div>

        <div class="sidebar-footer-links">
            <p class="copyright">&copy; 2026 Dolphin Cove</p>
        </div>
    </aside>
</div>

<script>
function toggleFreelance() {
    const form = document.getElementById('freelance-form');
    const input = document.getElementById('is_freelance');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        input.value = '1';
    } else {
        form.style.display = 'none';
        input.value = '0';
    }
}

function setProjectType(type, btn) {
    document.getElementById('freelance_type').value = type;
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function toggleComments(postId) {
    const el = document.getElementById('comments-' + postId);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
