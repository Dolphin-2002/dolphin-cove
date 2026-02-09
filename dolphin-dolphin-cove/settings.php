<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_account') {
        $displayName = trim($_POST['display_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $hourlyRate = $_POST['hourly_rate'] ? (float)$_POST['hourly_rate'] : null;

        if (empty($displayName)) {
            setFlash('error', 'Display name is required');
        } else {
            // Check username/email uniqueness
            $checkStmt = $db->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
            $checkStmt->bind_param("ssi", $email, $username, $userId);
            $checkStmt->execute();
            $dup = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($dup) {
                setFlash('error', 'Email or username already taken');
            } else {
                $stmt = $db->prepare("UPDATE users SET display_name = ?, username = ?, email = ?, bio = ?, location = ?, website = ?, hourly_rate = ? WHERE id = ?");
                $stmt->bind_param("ssssssdi", $displayName, $username, $email, $bio, $location, $website, $hourlyRate, $userId);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Profile updated successfully!');
            }
        }
        header("Location: " . SITE_URL . "/settings.php?tab=account");
        exit;
    }

    if ($action === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPw, $user['password_hash'])) {
            setFlash('error', 'Current password is incorrect');
        } elseif (strlen($newPw) < 6) {
            setFlash('error', 'New password must be at least 6 characters');
        } elseif ($newPw !== $confirmPw) {
            setFlash('error', 'New passwords do not match');
        } else {
            $hashed = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $userId);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Password changed successfully!');
        }
        header("Location: " . SITE_URL . "/settings.php?tab=security");
        exit;
    }

    if ($action === 'update_freelancer') {
        $isFreelancer = isset($_POST['is_freelancer']) ? 1 : 0;
        $freelancerTitle = trim($_POST['freelancer_title'] ?? '');
        $availability = $_POST['freelancer_availability'] ?? 'available';
        $bankName = trim($_POST['bank_name'] ?? '');
        $bankAccount = trim($_POST['bank_account_number'] ?? '');
        $bankHolder = trim($_POST['bank_account_holder'] ?? '');

        $stmt = $db->prepare("UPDATE users SET is_freelancer = ?, freelancer_title = ?, freelancer_availability = ?, bank_name = ?, bank_account_number = ?, bank_account_holder = ? WHERE id = ?");
        $stmt->bind_param("isssssi", $isFreelancer, $freelancerTitle, $availability, $bankName, $bankAccount, $bankHolder, $userId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Freelancer settings updated!');
        header("Location: " . SITE_URL . "/settings.php?tab=freelancer");
        exit;
    }

    if ($action === 'delete_account') {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        session_destroy();
        session_start();
        setFlash('success', 'Account deleted');
        header("Location: " . SITE_URL . "/index.php");
        exit;
    }
}

// Refresh user data
$user = getCurrentUser();
$activeTab = $_GET['tab'] ?? 'account';

require_once __DIR__ . '/includes/header.php';
?>

<div class="settings-page">
    <div class="settings-container">
        <aside class="settings-sidebar">
            <h2>Settings</h2>
            <nav class="settings-nav">
                <a href="?tab=account" class="settings-nav-item <?= $activeTab === 'account' ? 'active' : '' ?>">👤 Account</a>
                <a href="?tab=security" class="settings-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>">🔒 Security</a>
                <a href="?tab=appearance" class="settings-nav-item <?= $activeTab === 'appearance' ? 'active' : '' ?>">🎨 Appearance</a>
                <a href="?tab=freelancer" class="settings-nav-item <?= $activeTab === 'freelancer' ? 'active' : '' ?>">💼 Freelancer</a>
            </nav>
            <a href="<?= SITE_URL ?>/logout.php" class="logout-btn">🚪 Log Out</a>
        </aside>

        <main class="settings-content">
            <?php if ($activeTab === 'account'): ?>
            <div class="settings-section">
                <h3>Account Settings</h3>
                <p class="section-description">Manage your personal information</p>
                <form method="POST">
                    <input type="hidden" name="action" value="update_account">
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="<?= e($user['display_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-with-prefix"><span>@</span><input type="text" name="username" value="<?= e($user['username']) ?>"></div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= e($user['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4"><?= e($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" value="<?= e($user['location'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Website / Portfolio</label>
                            <input type="url" name="website" value="<?= e($user['website'] ?? '') ?>">
                        </div>
                    </div>
                    <?php if ($user['is_freelancer']): ?>
                    <div class="form-group">
                        <label>Hourly Rate ($)</label>
                        <input type="number" name="hourly_rate" value="<?= e($user['hourly_rate'] ?? '') ?>" min="0">
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($activeTab === 'security'): ?>
            <div class="settings-section">
                <h3>Security</h3>
                <p class="section-description">Change your password</p>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">🔒 Change Password</button>
                </form>

                <hr style="margin: 2rem 0; border-color: var(--border-color);">
                <h3 style="color: #ef4444;">Danger Zone</h3>
                <p>Once you delete your account, there is no going back.</p>
                <form method="POST" onsubmit="return confirm('Are you SURE you want to delete your account? This cannot be undone!')">
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn" style="background:#ef4444;color:white;">🗑️ Delete Account</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($activeTab === 'appearance'): ?>
            <div class="settings-section">
                <h3>Appearance</h3>
                <p class="section-description">Customize the look and feel</p>
                <div class="theme-options">
                    <a href="<?= SITE_URL ?>/toggle_theme.php" class="theme-option <?= ($_SESSION['theme'] ?? 'dark') === 'light' ? '' : 'active' ?>">
                        <span>🌙</span><h4>Dark Mode</h4>
                    </a>
                    <a href="<?= SITE_URL ?>/toggle_theme.php" class="theme-option <?= ($_SESSION['theme'] ?? 'dark') === 'light' ? 'active' : '' ?>">
                        <span>☀️</span><h4>Light Mode</h4>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($activeTab === 'freelancer'): ?>
            <div class="settings-section">
                <h3>Freelancer Settings</h3>
                <p class="section-description">Manage your freelancer profile and payment info</p>
                <form method="POST">
                    <input type="hidden" name="action" value="update_freelancer">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Freelancer Mode</h4>
                            <p>Enable to receive job offers</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="is_freelancer" <?= $user['is_freelancer'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Professional Title</label>
                        <input type="text" name="freelancer_title" value="<?= e($user['freelancer_title'] ?? '') ?>" placeholder="e.g., Full Stack Developer">
                    </div>
                    <div class="form-group">
                        <label>Availability</label>
                        <select name="freelancer_availability">
                            <option value="available" <?= ($user['freelancer_availability'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="busy" <?= ($user['freelancer_availability'] ?? '') === 'busy' ? 'selected' : '' ?>>Busy</option>
                            <option value="unavailable" <?= ($user['freelancer_availability'] ?? '') === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                    <h4 style="margin-top:1.5rem;">Bank Information</h4>
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?= e($user['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="bank_account_number" value="<?= e($user['bank_account_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Holder Name</label>
                        <input type="text" name="bank_account_holder" value="<?= e($user['bank_account_holder'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Save Freelancer Settings</button>
                </form>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
