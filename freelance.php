<?php
$pageTitle = 'Freelance Jobs';
$currentPage = 'freelance';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_job') {
        $jobId = (int)$_POST['job_id'];
        $stmt = $db->prepare("DELETE FROM freelance_jobs WHERE id = ? AND poster_id = ?");
        $stmt->bind_param("ii", $jobId, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Job deleted');
    }
}

$tab = $_GET['tab'] ?? 'browse';
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$budgetFilter = $_GET['budget'] ?? '';

// Build browse query
$browseWhere = "WHERE fj.status = 'open'";
$browseParams = [];
$browseTypes = "";

if ($search) {
    $browseWhere .= " AND (fj.title LIKE ? OR fj.description LIKE ?)";
    $s = "%$search%";
    $browseParams[] = $s;
    $browseParams[] = $s;
    $browseTypes .= "ss";
}
if ($category) {
    $browseWhere .= " AND fj.category = ?";
    $browseParams[] = $category;
    $browseTypes .= "s";
}
if ($budgetFilter === 'low') {
    $browseWhere .= " AND fj.budget_max <= 500";
} elseif ($budgetFilter === 'mid') {
    $browseWhere .= " AND fj.budget_min >= 500 AND fj.budget_max <= 5000";
} elseif ($budgetFilter === 'high') {
    $browseWhere .= " AND fj.budget_min >= 5000";
}

$browseSQL = "SELECT fj.*, u.display_name AS poster_name, u.username AS poster_username, u.avatar_url,
              (SELECT COUNT(*) FROM job_applications WHERE job_id = fj.id) AS app_count
              FROM freelance_jobs fj JOIN users u ON fj.poster_id = u.id
              $browseWhere ORDER BY fj.created_at DESC";

if ($browseTypes) {
    $stmt = $db->prepare($browseSQL);
    $stmt->bind_param($browseTypes, ...$browseParams);
    $stmt->execute();
    $browseJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $browseJobs = $db->query($browseSQL)->fetch_all(MYSQLI_ASSOC);
}

// My posted jobs
$stmt = $db->prepare("SELECT fj.*, (SELECT COUNT(*) FROM job_applications WHERE job_id = fj.id) AS app_count
                       FROM freelance_jobs fj WHERE fj.poster_id = ? ORDER BY fj.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// My applications
$stmt = $db->prepare("SELECT ja.*, fj.title AS job_title, fj.budget_min, fj.budget_max, fj.category, fj.status AS job_status,
                       u.display_name AS poster_name, u.username AS poster_username
                       FROM job_applications ja
                       JOIN freelance_jobs fj ON ja.job_id = fj.id
                       JOIN users u ON fj.poster_id = u.id
                       WHERE ja.applicant_id = ? ORDER BY ja.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$categories = ['Web Development','Mobile Development','UI/UX Design','Data Science','DevOps','Machine Learning','Blockchain','Cloud Computing','Cybersecurity','Other'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Freelance Jobs</h1>
                <p>Find opportunities or post your own</p>
            </div>
            <a href="<?= SITE_URL ?>/create_job.php" class="btn btn-primary">+ Post a Job</a>
        </div>

        <div class="tabs">
            <a href="?tab=browse" class="tab <?= $tab === 'browse' ? 'active' : '' ?>">Browse Jobs</a>
            <a href="?tab=my-posts" class="tab <?= $tab === 'my-posts' ? 'active' : '' ?>">My Posts (<?= count($myJobs) ?>)</a>
            <a href="?tab=my-applications" class="tab <?= $tab === 'my-applications' ? 'active' : '' ?>">My Applications (<?= count($myApplications) ?>)</a>
        </div>

        <?php if ($tab === 'browse'): ?>
        <form class="filter-bar" method="GET">
            <input type="hidden" name="tab" value="browse">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search jobs..." class="search-input">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="budget">
                <option value="">Any Budget</option>
                <option value="low" <?= $budgetFilter === 'low' ? 'selected' : '' ?>>Under $500</option>
                <option value="mid" <?= $budgetFilter === 'mid' ? 'selected' : '' ?>>$500 - $5,000</option>
                <option value="high" <?= $budgetFilter === 'high' ? 'selected' : '' ?>>$5,000+</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <?php if (empty($browseJobs)): ?>
            <div class="empty-state"><p>No jobs found matching your criteria.</p></div>
        <?php else: ?>
        <div class="job-grid">
            <?php foreach ($browseJobs as $job): ?>
            <div class="job-card">
                <div class="job-card-header">
                    <span class="job-category"><?= e($job['category']) ?></span>
                    <span class="job-date"><?= timeAgo($job['created_at']) ?></span>
                </div>
                <h3><a href="<?= SITE_URL ?>/job_detail.php?id=<?= $job['id'] ?>"><?= e($job['title']) ?></a></h3>
                <p class="job-description"><?= e(substr($job['description'], 0, 150)) ?>...</p>
                <div class="job-skills">
                    <?php foreach (explode(',', $job['skills_required'] ?? '') as $sk): $sk = trim($sk); if ($sk): ?>
                        <span class="skill-chip"><?= e($sk) ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <div class="job-card-footer">
                    <span class="job-budget">$<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?></span>
                    <span class="job-applicants"><?= $job['app_count'] ?> applicants</span>
                </div>
                <div class="job-poster">
                    <?= userAvatar($job['avatar_url'], $job['poster_name'], 'small') ?>
                    <a href="<?= SITE_URL ?>/profile.php?username=<?= e($job['poster_username']) ?>"><?= e($job['poster_name']) ?></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'my-posts'): ?>
        <?php if (empty($myJobs)): ?>
            <div class="empty-state"><p>You haven't posted any jobs yet.</p><a href="<?= SITE_URL ?>/create_job.php" class="btn btn-primary">Post Your First Job</a></div>
        <?php else: ?>
        <div class="job-grid">
            <?php foreach ($myJobs as $job): ?>
            <div class="job-card">
                <div class="job-card-header">
                    <span class="job-status status-<?= $job['status'] ?>"><?= ucfirst($job['status']) ?></span>
                    <span class="job-date"><?= timeAgo($job['created_at']) ?></span>
                </div>
                <h3><a href="<?= SITE_URL ?>/job_detail.php?id=<?= $job['id'] ?>"><?= e($job['title']) ?></a></h3>
                <p class="job-description"><?= e(substr($job['description'], 0, 120)) ?>...</p>
                <div class="job-card-footer">
                    <span class="job-budget">$<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?></span>
                    <span class="job-applicants"><?= $job['app_count'] ?> applicants</span>
                </div>
                <div class="job-actions">
                    <a href="<?= SITE_URL ?>/manage_applicants.php?job_id=<?= $job['id'] ?>" class="btn btn-sm">Manage Applicants</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this job?')">
                        <input type="hidden" name="action" value="delete_job">
                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'my-applications'): ?>
        <?php if (empty($myApplications)): ?>
            <div class="empty-state"><p>You haven't applied to any jobs yet.</p><a href="?tab=browse" class="btn btn-primary">Browse Jobs</a></div>
        <?php else: ?>
        <div class="applications-list">
            <?php foreach ($myApplications as $app): ?>
            <div class="application-item">
                <div class="application-info">
                    <h4><a href="<?= SITE_URL ?>/job_detail.php?id=<?= $app['job_id'] ?>"><?= e($app['job_title']) ?></a></h4>
                    <p>Posted by <a href="<?= SITE_URL ?>/profile.php?username=<?= e($app['poster_username']) ?>"><?= e($app['poster_name']) ?></a></p>
                    <p>Budget: $<?= number_format($app['budget_min']) ?> – $<?= number_format($app['budget_max']) ?></p>
                </div>
                <div class="application-status">
                    <span class="status-badge status-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
                    <span class="application-date"><?= timeAgo($app['created_at']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
