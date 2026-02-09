<?php
$pageTitle = 'Log In';
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/home.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, password_hash, display_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];

            // Update online status
            $updateStmt = $db->prepare("UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();

            setFlash('success', 'Welcome back! Login successful');
            header("Location: " . SITE_URL . "/home.php");
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?= SITE_URL ?>/assets/images/Dolphin-cove.png" alt="Dolphin Cove" class="auth-logo">
                <h1>Welcome Back!</h1>
                <p>Dive back into the ocean of opportunities</p>
            </div>

            <form method="POST" class="auth-form">
                <?php if ($error): ?>
                    <div class="error-message">⚠️ <?= e($error) ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <svg width="18" height="18" class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="email" name="email" placeholder="your@email.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg width="18" height="18" class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="auth-btn">🌊 Dive In</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="<?= SITE_URL ?>/register.php">Join the pod 🐬</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
