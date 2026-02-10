<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Determine if viewing own profile or someone else's
$viewUsername = $_GET['username'] ?? null;
if ($viewUsername) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $viewUsername);
    $stmt->execute();
    $profileUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$profileUser) {
        setFlash('error', 'User not found');
        header("Location: " . SITE_URL . "/home.php");
        exit;
    }
} else {
    $profileUser = getCurrentUser();
}

$isOwnProfile = ($profileUser['id'] == $userId);
$profileUserId = $profileUser['id'];

// Check connection status with this user (if not own profile)
$connectionStatus = null;
$connectionDirection = null; // 'sent' or 'received'
if (!$isOwnProfile) {
    $connCheckStmt = $db->prepare("SELECT * FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?) LIMIT 1");
    $connCheckStmt->bind_param("iiii", $userId, $profileUserId, $profileUserId, $userId);
    $connCheckStmt->execute();
    $connRow = $connCheckStmt->get_result()->fetch_assoc();
    $connCheckStmt->close();
    if ($connRow) {
        $connectionStatus = $connRow['status'];
        $connectionDirection = ($connRow['requester_id'] == $userId) ? 'sent' : 'received';
    }
}

// Handle profile actions (connect, accept, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isOwnProfile) {
    $profileAction = $_POST['action'] ?? '';
    
    if ($profileAction === 'connect') {
        $stmt = $db->prepare("INSERT IGNORE INTO connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $userId, $profileUserId);
        $stmt->execute();
        $stmt->close();
        // Send notification
        $notifMsg = 'sent you a connection request';
        $notifLink = SITE_URL . '/network.php?tab=pending';
        $nStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'connection_request', ?, ?)");
        $nStmt->bind_param("iiss", $profileUserId, $userId, $notifMsg, $notifLink);
        $nStmt->execute();
        $nStmt->close();
        setFlash('success', 'Connection request sent!');
        header("Location: " . SITE_URL . "/profile.php?username=" . urlencode($profileUser['username']));
        exit;
    }
    
    if ($profileAction === 'accept') {
        $stmt = $db->prepare("UPDATE connections SET status = 'accepted' WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $profileUserId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Connection accepted!');
        header("Location: " . SITE_URL . "/profile.php?username=" . urlencode($profileUser['username']));
        exit;
    }
    
    if ($profileAction === 'remove') {
        $stmt = $db->prepare("DELETE FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
        $stmt->bind_param("iiii", $userId, $profileUserId, $profileUserId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Connection removed');
        header("Location: " . SITE_URL . "/profile.php?username=" . urlencode($profileUser['username']));
        exit;
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    if (($_POST['action'] ?? '') === 'update_profile') {
        $displayName = trim($_POST['display_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $hourlyRate = $_POST['hourly_rate'] ? (float)$_POST['hourly_rate'] : null;

        // Handle avatar upload
        $avatarPath = $profileUser['avatar_url'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = UPLOAD_DIR . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                $avatarPath = SITE_URL . '/uploads/' . $filename;
            }
        }

        $stmt = $db->prepare("UPDATE users SET display_name = ?, bio = ?, location = ?, website = ?, skills = ?, hourly_rate = ?, avatar_url = ? WHERE id = ?");
        $stmt->bind_param("sssssdsi", $displayName, $bio, $location, $website, $skills, $hourlyRate, $avatarPath, $userId);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Profile updated successfully!');
        header("Location: " . SITE_URL . "/profile.php");
        exit;
    }
}

// Get user posts
$postStmt = $db->prepare("
    SELECT p.*, u.display_name, u.username, u.avatar_url, u.is_freelancer,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
    FROM posts p JOIN users u ON p.author_id = u.id
    WHERE p.author_id = ?
    ORDER BY p.created_at DESC
");
$postStmt->bind_param("i", $profileUserId);
$postStmt->execute();
$userPosts = $postStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$postStmt->close();

// Get connection count
$connStmt = $db->prepare("SELECT COUNT(*) as cnt FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'");
$connStmt->bind_param("ii", $profileUserId, $profileUserId);
$connStmt->execute();
$connectionCount = $connStmt->get_result()->fetch_assoc()['cnt'];
$connStmt->close();

$activeTab = $_GET['tab'] ?? 'posts';

require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-page">
    <!-- Cover & Header -->
    <div class="profile-header">
        <div class="profile-cover">
            <?php if ($profileUser['cover_photo']): ?>
                <img src="<?= e($profileUser['cover_photo']) ?>" alt="Cover">
            <?php else: ?>
                <div class="default-cover"><div class="cover-pattern"></div></div>
            <?php endif; ?>
        </div>

        <div class="profile-main-info">
            <div class="avatar-section">
                <?php
                    $avatarUrl = avatarUrl($profileUser['avatar_url'], $profileUser['display_name']);
                ?>
                <img src="<?= $avatarUrl ?>" alt="<?= e($profileUser['display_name']) ?>" class="profile-avatar">
            </div>

            <div class="profile-details">
                <div class="name-row">
                    <h1><?= e($profileUser['display_name']) ?></h1>
                    <?php if ($profileUser['is_freelancer']): ?>
                        <span class="freelancer-badge">&#x1F4BB; Freelancer</span>
                    <?php endif; ?>
                </div>
                <p class="username">@<?= e($profileUser['username']) ?></p>
                <?php if ($profileUser['bio']): ?>
                    <p class="bio"><?= e($profileUser['bio']) ?></p>
                <?php elseif ($isOwnProfile): ?>
                    <p class="bio empty">Add a bio to tell others about yourself</p>
                <?php endif; ?>

                <div class="profile-meta">
                    <?php if ($profileUser['location']): ?>
                        <span>📍 <?= e($profileUser['location']) ?></span>
                    <?php endif; ?>
                    <?php if ($profileUser['website']): ?>
                        <a href="<?= e($profileUser['website']) ?>" target="_blank">🔗 Portfolio</a>
                    <?php endif; ?>
                    <span>📅 Joined <?= date('F Y', strtotime($profileUser['created_at'])) ?></span>
                </div>

                <?php if ($profileUser['hourly_rate']): ?>
                <div class="hourly-rate">💼 $<?= number_format($profileUser['hourly_rate'], 0) ?>/hour</div>
                <?php endif; ?>
            </div>

            <div class="profile-actions">
                <?php if ($isOwnProfile): ?>
                    <button class="btn btn-secondary" onclick="document.getElementById('edit-modal').style.display='flex'">✏️ Edit Profile</button>
                <?php else: ?>
                    <?php if ($connectionStatus === 'accepted'): ?>
                        <a href="<?= SITE_URL ?>/messages.php?chat=<?= $profileUserId ?>" class="btn btn-primary">💬 Message</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="remove">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this connection?')">Remove</button>
                        </form>
                    <?php elseif ($connectionStatus === 'pending' && $connectionDirection === 'sent'): ?>
                        <button class="btn btn-outline" disabled>⏳ Request Sent</button>
                    <?php elseif ($connectionStatus === 'pending' && $connectionDirection === 'received'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-primary">✅ Accept Request</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="connect">
                            <button type="submit" class="btn btn-primary">+ Connect</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-stats">
            <div class="stat-item"><strong><?= $connectionCount ?></strong><span>Connections</span></div>
            <div class="stat-item"><strong><?= $profileUser['completed_jobs'] ?? 0 ?></strong><span>Projects</span></div>
            <div class="stat-item"><strong><?= ($profileUser['rating'] ?? 0) > 0 ? number_format($profileUser['rating'], 1) : '-' ?></strong><span>Rating</span></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="profile-content">
        <div class="profile-tabs">
            <a href="?<?= $viewUsername ? 'username='.e($viewUsername).'&' : '' ?>tab=posts" class="tab <?= $activeTab === 'posts' ? 'active' : '' ?>">📄 Posts</a>
            <a href="?<?= $viewUsername ? 'username='.e($viewUsername).'&' : '' ?>tab=about" class="tab <?= $activeTab === 'about' ? 'active' : '' ?>">👤 About</a>
        </div>

        <div class="tab-content">
            <?php if ($activeTab === 'posts'): ?>
            <div class="posts-tab">
                <?php if (empty($userPosts)): ?>
                    <div class="empty-state">
                        <h3>No posts yet</h3>
                        <?php if ($isOwnProfile): ?>
                            <p>Share your first post with the community!</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($userPosts as $post): ?>
                    <div class="post-card-mini">
                        <p><?= nl2br(e(substr($post['content'], 0, 200))) ?><?= strlen($post['content']) > 200 ? '...' : '' ?></p>
                        <div class="post-card-meta">
                            <span>❤️ <?= $post['like_count'] ?></span>
                            <span>💬 <?= $post['comment_count'] ?></span>
                            <span><?= timeAgo($post['created_at']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($activeTab === 'about'): ?>
            <div class="about-tab">
                <div class="about-section">
                    <h3>Skills</h3>
                    <?php $skills = array_filter(explode(',', $profileUser['skills'] ?? '')); ?>
                    <?php if (!empty($skills)): ?>
                        <div class="skills-list">
                            <?php foreach ($skills as $skill): ?>
                                <span class="skill-tag"><?= e(trim($skill)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-text"><?= $isOwnProfile ? 'Add your skills to help clients find you' : 'No skills added yet' ?></p>
                    <?php endif; ?>
                </div>
                <div class="about-section">
                    <h3>Reviews</h3>
                    <p class="empty-text">No reviews yet</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <?php if ($isOwnProfile): ?>
    <div class="modal-overlay" id="edit-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-content">
            <h2>Edit Profile</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="display_name" value="<?= e($profileUser['display_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" rows="3"><?= e($profileUser['bio'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= e($profileUser['location'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Website / Portfolio</label>
                    <input type="url" name="website" value="<?= e($profileUser['website'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Skills (comma separated)</label>
                    <input type="text" name="skills" value="<?= e($profileUser['skills'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Hourly Rate ($)</label>
                    <input type="number" name="hourly_rate" value="<?= e($profileUser['hourly_rate'] ?? '') ?>" min="0" step="1">
                </div>
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="avatar" accept="image/*">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
