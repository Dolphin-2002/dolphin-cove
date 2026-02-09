<?php
$pageTitle = 'Job Details';
$currentPage = 'freelance';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$jobId = (int)($_GET['id'] ?? 0);

if (!$jobId) { header("Location: " . SITE_URL . "/freelance.php"); exit; }

// Get job
$stmt = $db->prepare("SELECT fj.*, u.display_name AS poster_name, u.username AS poster_username, u.avatar_url AS poster_avatar,
                       u.freelancer_title AS poster_title
                       FROM freelance_jobs fj JOIN users u ON fj.poster_id = u.id WHERE fj.id = ?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) { setFlash('error', 'Job not found'); header("Location: " . SITE_URL . "/freelance.php"); exit; }

// Check if already applied
$stmt = $db->prepare("SELECT * FROM job_applications WHERE job_id = ? AND applicant_id = ?");
$stmt->bind_param("ii", $jobId, $userId);
$stmt->execute();
$myApplication = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get application count
$countRes = $db->query("SELECT COUNT(*) AS cnt FROM job_applications WHERE job_id = $jobId");
$appCount = $countRes->fetch_assoc()['cnt'];

// Handle apply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply' && !$myApplication && $job['poster_id'] != $userId) {
        $coverLetter = trim($_POST['cover_letter'] ?? '');
        $proposedBudget = (float)($_POST['proposed_budget'] ?? 0);
        $proposedDuration = trim($_POST['proposed_duration'] ?? '');

        if (empty($coverLetter)) {
            setFlash('error', 'Cover letter is required');
        } else {
            $stmt = $db->prepare("INSERT INTO job_applications (job_id, applicant_id, cover_letter, proposed_budget, proposed_duration, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iisds", $jobId, $userId, $coverLetter, $proposedBudget, $proposedDuration);
            $stmt->execute();
            $stmt->close();

            // Notify poster
            $notifContent = 'applied to your job "' . $job['title'] . '"';
            $stmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, link) VALUES (?, ?, 'job_application', ?, ?)");
            $link = SITE_URL . '/job_detail.php?id=' . $jobId;
            $stmt->bind_param("iiss", $job['poster_id'], $userId, $notifContent, $link);
            $stmt->execute();
            $stmt->close();

            setFlash('success', 'Application submitted!');
            header("Location: " . SITE_URL . "/job_detail.php?id=" . $jobId);
            exit;
        }
    }

    if ($action === 'withdraw') {
        $stmt = $db->prepare("DELETE FROM job_applications WHERE job_id = ? AND applicant_id = ?");
        $stmt->bind_param("ii", $jobId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Application withdrawn');
        header("Location: " . SITE_URL . "/job_detail.php?id=" . $jobId);
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <a href="<?= SITE_URL ?>/freelance.php" class="btn btn-outline">← Back to Jobs</a>
        </div>

        <div class="job-detail-layout">
            <div class="job-detail-main card">
                <div class="job-detail-header">
                    <div>
                        <h1><?= e($job['title']) ?></h1>
                        <div class="job-meta">
                            <span class="job-category"><?= e($job['category']) ?></span>
                            <span>•</span>
                            <span>Posted <?= timeAgo($job['created_at']) ?></span>
                            <span>•</span>
                            <span class="job-status status-<?= $job['status'] ?>"><?= ucfirst($job['status']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="job-detail-body">
                    <h3>Description</h3>
                    <div class="job-description-full"><?= nl2br(e($job['description'])) ?></div>

                    <?php $skills = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? ''))); ?>
                    <?php if (!empty($skills)): ?>
                    <h3>Required Skills</h3>
                    <div class="job-skills">
                        <?php foreach ($skills as $sk): ?>
                            <span class="skill-chip"><?= e($sk) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($job['attachment_url']): ?>
                    <h3>Attachments</h3>
                    <a href="<?= SITE_URL . '/' . e($job['attachment_url']) ?>" class="btn btn-outline" target="_blank">📎 Download Attachment</a>
                    <?php endif; ?>
                </div>

                <div class="job-detail-info-grid">
                    <div class="info-item">
                        <span class="info-label">Budget</span>
                        <span class="info-value">$<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Project Type</span>
                        <span class="info-value"><?= ucfirst($job['project_type'] ?? 'Fixed') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Duration</span>
                        <span class="info-value"><?= e($job['duration'] ?? 'Not specified') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Applicants</span>
                        <span class="info-value"><?= $appCount ?></span>
                    </div>
                </div>
            </div>

            <div class="job-detail-sidebar">
                <!-- Poster Card -->
                <div class="card">
                    <h3>Posted By</h3>
                    <div class="poster-card">
                        <?= userAvatar($job['poster_avatar'], $job['poster_name'], 'medium') ?>
                        <div>
                            <a href="<?= SITE_URL ?>/profile.php?username=<?= e($job['poster_username']) ?>"><strong><?= e($job['poster_name']) ?></strong></a>
                            <p><?= e($job['poster_title'] ?? '') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Apply / Status Card -->
                <div class="card">
                    <?php if ($job['poster_id'] == $userId): ?>
                        <a href="<?= SITE_URL ?>/manage_applicants.php?job_id=<?= $jobId ?>" class="btn btn-primary btn-block">Manage Applicants (<?= $appCount ?>)</a>
                    <?php elseif ($myApplication): ?>
                        <div class="application-status-card">
                            <h3>Your Application</h3>
                            <p>Status: <span class="status-badge status-<?= $myApplication['status'] ?>"><?= ucfirst($myApplication['status']) ?></span></p>
                            <p>Proposed: $<?= number_format($myApplication['proposed_budget']) ?></p>
                            <?php if ($myApplication['status'] === 'pending'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="withdraw">
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Withdraw application?')">Withdraw</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($job['status'] === 'open'): ?>
                        <h3>Apply for this Job</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="apply">
                            <div class="form-group">
                                <label>Cover Letter *</label>
                                <textarea name="cover_letter" rows="5" placeholder="Explain why you're a great fit..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label>Proposed Budget ($)</label>
                                <input type="number" name="proposed_budget" min="1" value="<?= $job['budget_min'] ?>">
                            </div>
                            <div class="form-group">
                                <label>Proposed Duration</label>
                                <input type="text" name="proposed_duration" placeholder="e.g., 2 weeks">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Submit Application</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">This job is no longer accepting applications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
