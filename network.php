<?php
$pageTitle = 'Network';
$currentPage = 'network';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    if ($action === 'connect' && $targetId) {
        $stmt = $db->prepare("INSERT IGNORE INTO connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $userId, $targetId);
        $stmt->execute();
        $stmt->close();

        $notifContent = 'sent you a connection request';
        $nStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'connection_request', ?, ?)");
        $link = SITE_URL . '/network.php?tab=pending';
        $nStmt->bind_param("iiss", $targetId, $userId, $notifContent, $link);
        $nStmt->execute();
        $nStmt->close();
        setFlash('success', 'Connection request sent');
    }

    if ($action === 'accept' && $targetId) {
        $stmt = $db->prepare("UPDATE connections SET status = 'accepted' WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $targetId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Connection accepted');
    }

    if ($action === 'reject' && $targetId) {
        $stmt = $db->prepare("DELETE FROM connections WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $targetId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Request declined');
    }

    if ($action === 'remove' && $targetId) {
        $stmt = $db->prepare("DELETE FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
        $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Connection removed');
    }

    header("Location: " . SITE_URL . "/network.php?tab=" . urlencode($_GET['tab'] ?? 'connections'));
    exit;
}

$tab = $_GET['tab'] ?? 'connections';
$search = trim($_GET['search'] ?? '');

// My connections (accepted)
$connSQL = "SELECT u.* FROM users u
            JOIN connections c ON (c.requester_id = u.id OR c.receiver_id = u.id)
            WHERE ((c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted' AND u.id != ?)";
$connParams = [$userId, $userId, $userId];
$connTypes = "iii";
if ($search) {
    $connSQL .= " AND (u.display_name LIKE ? OR u.username LIKE ?)";
    $s = "%$search%";
    $connParams[] = $s;
    $connParams[] = $s;
    $connTypes .= "ss";
}
$connSQL .= " ORDER BY u.display_name ASC";
$stmt = $db->prepare($connSQL);
$stmt->bind_param($connTypes, ...$connParams);
$stmt->execute();
$connections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pending requests (received)
$stmt = $db->prepare("SELECT u.*, c.created_at AS request_date FROM users u
                       JOIN connections c ON c.requester_id = u.id
                       WHERE c.receiver_id = ? AND c.status = 'pending' ORDER BY c.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$pendingRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Discover people (not connected and not self)
$discoverSQL = "SELECT u.* FROM users u
                WHERE u.id != ? AND u.id NOT IN (
                    SELECT IF(requester_id = ?, receiver_id, requester_id) FROM connections WHERE requester_id = ? OR receiver_id = ?
                )";
$discoverParams = [$userId, $userId, $userId, $userId];
$discoverTypes = "iiii";
if ($search) {
    $discoverSQL .= " AND (u.display_name LIKE ? OR u.username LIKE ? OR u.skills LIKE ?)";
    $s = "%$search%";
    $discoverParams[] = $s;
    $discoverParams[] = $s;
    $discoverParams[] = $s;
    $discoverTypes .= "sss";
}
$discoverSQL .= " ORDER BY u.created_at DESC LIMIT 50";
$stmt = $db->prepare($discoverSQL);
$stmt->bind_param($discoverTypes, ...$discoverParams);
$stmt->execute();
$discoverUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Network</h1>
        </div>

        <div class="tabs">
            <a href="?tab=connections" class="tab <?= $tab === 'connections' ? 'active' : '' ?>">Connections (<?= count($connections) ?>)</a>
            <a href="?tab=pending" class="tab <?= $tab === 'pending' ? 'active' : '' ?>">Pending (<?= count($pendingRequests) ?>)</a>
            <a href="?tab=discover" class="tab <?= $tab === 'discover' ? 'active' : '' ?>">Discover</a>
        </div>

        <form class="filter-bar" method="GET">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name or username..." class="search-input">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="?tab=<?= e($tab) ?>" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <?php
        // If searching on connections tab and no results found, suggest discover tab
        if ($search && $tab === 'connections' && empty($connections)):
        ?>
        <div class="empty-state">
            <p>No connections found matching "<strong><?= e($search) ?></strong>"</p>
            <a href="?tab=discover&search=<?= urlencode($search) ?>" class="btn btn-primary" style="margin-top:0.5rem">🔍 Search in Discover</a>
        </div>
        <?php endif; ?>

        <?php if ($tab === 'connections'): ?>
        <?php if (empty($connections)): ?>
            <div class="empty-state"><p>No connections yet. Discover new people to connect with!</p></div>
        <?php else: ?>
        <div class="people-grid">
            <?php foreach ($connections as $person): ?>
            <div class="person-card card">
                <?= userAvatar($person['avatar_url'], $person['display_name'], 'large') ?>
                <h4><a href="<?= SITE_URL ?>/profile.php?username=<?= e($person['username']) ?>"><?= e($person['display_name']) ?></a></h4>
                <p>@<?= e($person['username']) ?></p>
                <?php if ($person['freelancer_title']): ?>
                <p class="text-muted"><?= e($person['freelancer_title']) ?></p>
                <?php endif; ?>
                <div class="person-actions">
                    <a href="<?= SITE_URL ?>/messages.php?chat=<?= $person['id'] ?>" class="btn btn-sm btn-outline">Message</a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="target_id" value="<?= $person['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove connection?')">Remove</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'pending'): ?>
        <?php if (empty($pendingRequests)): ?>
            <div class="empty-state"><p>No pending requests.</p></div>
        <?php else: ?>
        <div class="people-grid">
            <?php foreach ($pendingRequests as $person): ?>
            <div class="person-card card">
                <?= userAvatar($person['avatar_url'], $person['display_name'], 'large') ?>
                <h4><a href="<?= SITE_URL ?>/profile.php?username=<?= e($person['username']) ?>"><?= e($person['display_name']) ?></a></h4>
                <p>@<?= e($person['username']) ?></p>
                <p class="text-muted"><?= timeAgo($person['request_date']) ?></p>
                <div class="person-actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="target_id" value="<?= $person['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Accept</button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="target_id" value="<?= $person['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline">Decline</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'discover'): ?>
        <?php if (empty($discoverUsers)): ?>
            <div class="empty-state"><p>No new people to discover right now.</p></div>
        <?php else: ?>
        <div class="people-grid">
            <?php foreach ($discoverUsers as $person): ?>
            <div class="person-card card">
                <?= userAvatar($person['avatar_url'], $person['display_name'], 'large') ?>
                <h4><a href="<?= SITE_URL ?>/profile.php?username=<?= e($person['username']) ?>"><?= e($person['display_name']) ?></a></h4>
                <p>@<?= e($person['username']) ?></p>
                <?php if ($person['freelancer_title']): ?>
                <p class="text-muted"><?= e($person['freelancer_title']) ?></p>
                <?php endif; ?>
                <?php if ($person['skills']): ?>
                <div class="job-skills">
                    <?php foreach (array_slice(explode(',', $person['skills']), 0, 3) as $sk): ?>
                    <span class="skill-chip"><?= e(trim($sk)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="connect">
                    <input type="hidden" name="target_id" value="<?= $person['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-primary btn-block">+ Connect</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
