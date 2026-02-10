<?php
$pageTitle = 'Manage Applicants';
$currentPage = 'freelance';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$jobId = (int)($_GET['job_id'] ?? 0);

if (!$jobId) { header("Location: " . SITE_URL . "/freelance.php"); exit; }

// Get job (must be poster)
$stmt = $db->prepare("SELECT * FROM freelance_jobs WHERE id = ? AND poster_id = ?");
$stmt->bind_param("ii", $jobId, $userId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) { setFlash('error', 'Job not found or access denied'); header("Location: " . SITE_URL . "/freelance.php"); exit; }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appId = (int)($_POST['application_id'] ?? 0);

    if ($action === 'hire' && $appId) {
        // Set this application to hired
        $stmt = $db->prepare("UPDATE job_applications SET status = 'hired' WHERE id = ? AND job_id = ?");
        $stmt->bind_param("ii", $appId, $jobId);
        $stmt->execute();
        $stmt->close();

        // Set others to rejected
        $stmt = $db->prepare("UPDATE job_applications SET status = 'rejected' WHERE job_id = ? AND id != ?");
        $stmt->bind_param("ii", $jobId, $appId);
        $stmt->execute();
        $stmt->close();

        // Update job status
        $db->query("UPDATE freelance_jobs SET status = 'in_progress' WHERE id = $jobId");

        // Notify hired applicant
        $stmt = $db->prepare("SELECT applicant_id FROM job_applications WHERE id = ?");
        $stmt->bind_param("i", $appId);
        $stmt->execute();
        $hired = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($hired) {
            $notifContent = 'hired you for "' . $job['title'] . '"';
            $stmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'job_hired', ?, ?)");
            $link = SITE_URL . '/tasks.php';
            $stmt->bind_param("iiss", $hired['applicant_id'], $userId, $notifContent, $link);
            $stmt->execute();
            $stmt->close();

            // Create a task for tracking
            $stmt = $db->prepare("INSERT INTO tasks (job_id, client_id, freelancer_id, title, description, budget, status) VALUES (?, ?, ?, ?, ?, ?, 'in_progress')");
            $stmt->bind_param("iiissd", $jobId, $userId, $hired['applicant_id'], $job['title'], $job['description'], $job['budget_max']);
            $stmt->execute();
            $stmt->close();
        }

        setFlash('success', 'Applicant hired! A task has been created for tracking.');
        header("Location: " . SITE_URL . "/manage_applicants.php?job_id=" . $jobId);
        exit;
    }

    if ($action === 'reject' && $appId) {
        $stmt = $db->prepare("UPDATE job_applications SET status = 'rejected' WHERE id = ? AND job_id = ?");
        $stmt->bind_param("ii", $appId, $jobId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Application rejected');
        header("Location: " . SITE_URL . "/manage_applicants.php?job_id=" . $jobId);
        exit;
    }
}

// Fetch applicants
$stmt = $db->prepare("SELECT ja.*, u.display_name, u.username, u.avatar_url, u.bio, u.skills, u.hourly_rate, u.freelancer_title
                       FROM job_applications ja JOIN users u ON ja.applicant_id = u.id
                       WHERE ja.job_id = ? ORDER BY ja.created_at DESC");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$applicants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Manage Applicants</h1>
                <p>Job: <strong><?= e($job['title']) ?></strong> — <?= count($applicants) ?> applicant(s)</p>
            </div>
            <a href="<?= SITE_URL ?>/job_detail.php?id=<?= $jobId ?>" class="btn btn-outline">← Back to Job</a>
        </div>

        <?php if (empty($applicants)): ?>
            <div class="empty-state"><p>No applicants yet. Share this job to get more visibility.</p></div>
        <?php else: ?>
        <div class="applicants-list">
            <?php foreach ($applicants as $app): ?>
            <div class="applicant-card card">
                <div class="applicant-header">
                    <div class="applicant-info">
                        <?= userAvatar($app['avatar_url'], $app['display_name'], 'medium') ?>
                        <div>
                            <a href="<?= SITE_URL ?>/profile.php?username=<?= e($app['username']) ?>"><strong><?= e($app['display_name']) ?></strong></a>
                            <p><?= e($app['freelancer_title'] ?? 'Freelancer') ?></p>
                            <?php if ($app['hourly_rate']): ?>
                            <p>$<?= number_format($app['hourly_rate']) ?>/hr</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge status-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
                </div>

                <?php if ($app['bio']): ?>
                <p class="applicant-bio"><?= e(substr($app['bio'], 0, 200)) ?></p>
                <?php endif; ?>

                <?php if ($app['skills']): ?>
                <div class="job-skills">
                    <?php foreach (array_slice(explode(',', $app['skills']), 0, 5) as $sk): $sk = trim($sk); ?>
                        <span class="skill-chip"><?= e($sk) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="applicant-proposal">
                    <h4>Cover Letter</h4>
                    <p><?= nl2br(e($app['cover_letter'])) ?></p>
                    <div class="proposal-details">
                        <span><strong>Proposed Budget:</strong> $<?= number_format($app['proposed_budget']) ?></span>
                        <span><strong>Duration:</strong> <?= e($app['proposed_duration'] ?? 'Not specified') ?></span>
                        <span><strong>Applied:</strong> <?= timeAgo($app['created_at']) ?></span>
                    </div>
                </div>

                <?php if ($app['status'] === 'pending'): ?>
                <div class="applicant-actions">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="hire">
                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Hire this applicant? All other applicants will be rejected.')">✅ Hire</button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this application?')">❌ Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
