<?php
$pageTitle = 'Sign Up';
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/home.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayName = trim($_POST['display_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $isFreelancer = isset($_POST['is_freelancer']) ? 1 : 0;

    if (empty($displayName) || empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } else {
        $db = getDB();

        // Check if email or username exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->bind_param("ss", $email, $username);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $error = 'Email or username already taken';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $db->prepare("INSERT INTO users (email, username, password_hash, display_name, is_freelancer) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssssi", $email, $username, $hashedPassword, $displayName, $isFreelancer);

            if ($insertStmt->execute()) {
                $_SESSION['user_id'] = $insertStmt->insert_id;
                $insertStmt->close();
                setFlash('success', 'Account created successfully! Welcome aboard 🐬');
                header("Location: " . SITE_URL . "/home.php");
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $insertStmt->close();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card register-card">
            <div class="auth-header">
                <img src="<?= SITE_URL ?>/assets/images/Dolphin-cove.png" alt="Dolphin Cove" class="auth-logo">
                <h1>Join the Pod!</h1>
                <p>Start your journey in the freelance ocean</p>
            </div>

            <form method="POST" class="auth-form">
                <?php if ($error): ?>
                    <div class="error-message">⚠️ <?= e($error) ?></div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="display_name">Full Name</label>
                        <div class="input-wrapper">
                            <svg width="18" height="18" class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" id="display_name" name="display_name" placeholder="Enter your full name" value="<?= e($_POST['display_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon at-symbol">@</span>
                            <input type="text" id="username" name="username" placeholder="Choose a username" value="<?= e($_POST['username'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <svg width="18" height="18" class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="email" name="email" placeholder="your@email.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <svg width="18" height="18" class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="password" name="password" placeholder="Min. 6 characters" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <svg width="18" height="18" class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>

                <!-- Freelancer Toggle -->
                <div class="freelancer-toggle">
                    <div class="toggle-content">
                        <span class="toggle-icon">&#x1F4BB;</span>
                        <div class="toggle-text">
                            <h4>I'm a Freelancer</h4>
                            <p>Enable this to showcase your skills and receive job offers</p>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="is_freelancer" <?= isset($_POST['is_freelancer']) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button type="submit" class="auth-btn">🌊 Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="<?= SITE_URL ?>/login.php">Dive in 🌊</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
