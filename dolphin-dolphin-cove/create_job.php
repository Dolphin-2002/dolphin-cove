<?php
$pageTitle = 'Post a Job';
$currentPage = 'freelance';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();

$categories = ['Web Development','Mobile Development','UI/UX Design','Data Science','DevOps','Machine Learning','Blockchain','Cloud Computing','Cybersecurity','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $skills = trim($_POST['skills'] ?? '');
    $budgetMin = (float)($_POST['budget_min'] ?? 0);
    $budgetMax = (float)($_POST['budget_max'] ?? 0);
    $duration = trim($_POST['duration'] ?? '');
    $projectType = $_POST['project_type'] ?? 'fixed';

    $errors = [];
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($description)) $errors[] = 'Description is required';
    if (empty($category)) $errors[] = 'Category is required';
    if ($budgetMin <= 0) $errors[] = 'Minimum budget must be greater than 0';
    if ($budgetMax < $budgetMin) $errors[] = 'Maximum budget must be >= minimum';

    if (empty($errors)) {
        // Handle attachment
        $attachmentUrl = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/jobs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $fn = 'job_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fn);
            $attachmentUrl = 'uploads/jobs/' . $fn;
        }

        $stmt = $db->prepare("INSERT INTO freelance_jobs (poster_id, title, description, category, skills_required, budget_min, budget_max, duration, project_type, attachment_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param("issssddsss", $userId, $title, $description, $category, $skills, $budgetMin, $budgetMax, $duration, $projectType, $attachmentUrl);

        if ($stmt->execute()) {
            $jobId = $stmt->insert_id;
            $stmt->close();
            setFlash('success', 'Job posted successfully!');
            header("Location: " . SITE_URL . "/job_detail.php?id=" . $jobId);
            exit;
        }
        $stmt->close();
        setFlash('error', 'Failed to post job');
    } else {
        setFlash('error', implode(', ', $errors));
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Post a Job</h1>
                <p>Describe the project you need help with</p>
            </div>
            <a href="<?= SITE_URL ?>/freelance.php" class="btn btn-outline">← Back to Jobs</a>
        </div>

        <div class="card create-job-form">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Job Title *</label>
                    <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g., Build a React Dashboard" required>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="6" placeholder="Describe the project, requirements, deliverables..." required><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c) ?>" <?= ($_POST['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Project Type</label>
                        <select name="project_type">
                            <option value="fixed" <?= ($_POST['project_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Price</option>
                            <option value="hourly" <?= ($_POST['project_type'] ?? '') === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Required Skills (comma-separated)</label>
                    <input type="text" name="skills" value="<?= e($_POST['skills'] ?? '') ?>" placeholder="e.g., React, Node.js, TypeScript">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Budget Min ($) *</label>
                        <input type="number" name="budget_min" value="<?= e($_POST['budget_min'] ?? '') ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Budget Max ($) *</label>
                        <input type="number" name="budget_max" value="<?= e($_POST['budget_max'] ?? '') ?>" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Duration</label>
                    <input type="text" name="duration" value="<?= e($_POST['duration'] ?? '') ?>" placeholder="e.g., 2 weeks, 1 month">
                </div>

                <div class="form-group">
                    <label>Attachment (optional)</label>
                    <input type="file" name="attachment">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">🚀 Post Job</button>
                    <a href="<?= SITE_URL ?>/freelance.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
