<?php
$pageTitle = 'Tasks';
$currentPage = 'tasks';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_task_status') {
        $taskId = (int)$_POST['task_id'];
        $status = $_POST['status'];
        $allowed = ['in_progress','completed','cancelled'];
        if (in_array($status, $allowed)) {
            $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND (client_id = ? OR freelancer_id = ?)");
            $stmt->bind_param("siii", $status, $taskId, $userId, $userId);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Task status updated');
        }
    }

    if ($action === 'add_milestone') {
        $taskId = (int)$_POST['task_id'];
        $title = trim($_POST['milestone_title'] ?? '');
        $desc = trim($_POST['milestone_description'] ?? '');
        $dueDate = $_POST['milestone_due_date'] ?? null;
        $amount = (float)($_POST['milestone_amount'] ?? 0);

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND (client_id = ? OR freelancer_id = ?)");
        $stmt->bind_param("iii", $taskId, $userId, $userId);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($task && !empty($title)) {
            $stmt = $db->prepare("INSERT INTO task_milestones (task_id, title, description, due_date, amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssd", $taskId, $title, $desc, $dueDate, $amount);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Milestone added');
        }
    }

    if ($action === 'complete_milestone') {
        $milestoneId = (int)$_POST['milestone_id'];
        $stmt = $db->prepare("UPDATE task_milestones SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $milestoneId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Milestone completed');
    }

    header("Location: " . SITE_URL . "/tasks.php");
    exit;
}

// Get tasks
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? '';

$where = "(t.client_id = ? OR t.freelancer_id = ?)";
$params = [$userId, $userId];
$types = "ii";

if ($roleFilter === 'client') {
    $where = "t.client_id = ?";
    $params = [$userId];
    $types = "i";
} elseif ($roleFilter === 'freelancer') {
    $where = "t.freelancer_id = ?";
    $params = [$userId];
    $types = "i";
}

if ($statusFilter) {
    $where .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql = "SELECT t.*, 
        uc.display_name AS client_name, uc.username AS client_username,
        uf.display_name AS freelancer_name, uf.username AS freelancer_username,
        fj.title AS job_title
        FROM tasks t
        JOIN users uc ON t.client_id = uc.id
        JOIN users uf ON t.freelancer_id = uf.id
        LEFT JOIN freelance_jobs fj ON t.job_id = fj.id
        WHERE $where ORDER BY t.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$totalTasks = count($tasks);
$activeTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
$completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$totalEarnings = array_sum(array_map(fn($t) => $t['status'] === 'completed' ? $t['budget'] : 0, $tasks));

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Tasks</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?= $totalTasks ?></span>
                <span class="stat-label">Total Tasks</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $activeTasks ?></span>
                <span class="stat-label">Active</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $completedTasks ?></span>
                <span class="stat-label">Completed</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">$<?= number_format($totalEarnings) ?></span>
                <span class="stat-label">Total Earnings</span>
            </div>
        </div>

        <div class="filter-bar">
            <div class="tabs compact">
                <a href="?role=all&status=<?= e($statusFilter) ?>" class="tab <?= $roleFilter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?role=client&status=<?= e($statusFilter) ?>" class="tab <?= $roleFilter === 'client' ? 'active' : '' ?>">As Client</a>
                <a href="?role=freelancer&status=<?= e($statusFilter) ?>" class="tab <?= $roleFilter === 'freelancer' ? 'active' : '' ?>">As Freelancer</a>
            </div>
            <div class="tabs compact">
                <a href="?role=<?= e($roleFilter) ?>&status=" class="tab <?= !$statusFilter ? 'active' : '' ?>">All Status</a>
                <a href="?role=<?= e($roleFilter) ?>&status=in_progress" class="tab <?= $statusFilter === 'in_progress' ? 'active' : '' ?>">In Progress</a>
                <a href="?role=<?= e($roleFilter) ?>&status=completed" class="tab <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
            </div>
        </div>

        <?php if (empty($tasks)): ?>
            <div class="empty-state"><p>No tasks found.</p></div>
        <?php else: ?>
        <div class="tasks-list">
            <?php foreach ($tasks as $task): ?>
            <?php
                // Get milestones
                $mStmt = $db->prepare("SELECT * FROM task_milestones WHERE task_id = ? ORDER BY created_at ASC");
                $mStmt->bind_param("i", $task['id']);
                $mStmt->execute();
                $milestones = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $mStmt->close();

                $completedMilestones = count(array_filter($milestones, fn($m) => $m['status'] === 'completed'));
                $totalMilestones = count($milestones);
                $progress = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100) : 0;
                $isClient = $task['client_id'] == $userId;
            ?>
            <div class="task-card card">
                <div class="task-header">
                    <div>
                        <h3><?= e($task['title']) ?></h3>
                        <p class="task-meta">
                            <?php if ($isClient): ?>
                                Freelancer: <a href="<?= SITE_URL ?>/profile.php?username=<?= e($task['freelancer_username']) ?>"><?= e($task['freelancer_name']) ?></a>
                            <?php else: ?>
                                Client: <a href="<?= SITE_URL ?>/profile.php?username=<?= e($task['client_username']) ?>"><?= e($task['client_name']) ?></a>
                            <?php endif; ?>
                            • Budget: <strong>$<?= number_format($task['budget']) ?></strong>
                        </p>
                    </div>
                    <div class="task-actions-top">
                        <span class="status-badge status-<?= $task['status'] ?>"><?= ucfirst(str_replace('_', ' ', $task['status'])) ?></span>
                        <?php if ($task['status'] === 'in_progress'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="update_task_status">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Mark as completed?')">✅ Complete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($totalMilestones > 0): ?>
                <div class="progress-bar-container">
                    <div class="progress-info">
                        <span>Progress</span>
                        <span><?= $completedMilestones ?>/<?= $totalMilestones ?> milestones (<?= $progress ?>%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $progress ?>%"></div>
                    </div>
                </div>

                <div class="milestones-list">
                    <?php foreach ($milestones as $ms): ?>
                    <div class="milestone-item <?= $ms['status'] === 'completed' ? 'completed' : '' ?>">
                        <div class="milestone-info">
                            <span class="milestone-check"><?= $ms['status'] === 'completed' ? '✅' : '⬜' ?></span>
                            <span><?= e($ms['title']) ?></span>
                            <?php if ($ms['amount']): ?>
                            <span class="milestone-amount">$<?= number_format($ms['amount']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($ms['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="complete_milestone">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <button type="submit" class="btn btn-xs">Mark Done</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Add Milestone Form (collapsed) -->
                <details class="add-milestone-details">
                    <summary class="btn btn-sm btn-outline">+ Add Milestone</summary>
                    <form method="POST" class="milestone-form">
                        <input type="hidden" name="action" value="add_milestone">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <div class="form-row">
                            <input type="text" name="milestone_title" placeholder="Milestone title" required>
                            <input type="number" name="milestone_amount" placeholder="Amount ($)" min="0">
                            <input type="date" name="milestone_due_date">
                            <button type="submit" class="btn btn-sm btn-primary">Add</button>
                        </div>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
