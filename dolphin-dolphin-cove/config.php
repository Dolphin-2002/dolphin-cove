<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dolphin_cove');

// Site configuration
define('SITE_URL', 'http://localhost/dolphin-cove/dolphin-dolphin-cove');
define('SITE_NAME', 'Dolphin Cove');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/login.php");
        exit;
    }
}

// CSRF protection
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messages (toast equivalent)
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// Sanitize output
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Format time ago
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 6) return floor($diff->d / 7) . 'w ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

// Format currency
function formatBudget(float $amount): string {
    return '$' . number_format($amount, 0);
}

// Get avatar URL (returns a URL string, or a placeholder)
function avatarUrl(?string $avatar, string $name = 'U'): string {
    if ($avatar && $avatar !== '') {
        return e($avatar);
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0ea5e9&color=fff&size=128';
}

// Get user avatar HTML
function userAvatar(?string $avatar, string $name, string $size = 'small'): string {
    $sizeMap = ['small' => 'sm', 'medium' => 'md', 'large' => 'lg', 'xl' => 'xl', 'xs' => 'xs',
                'sm' => 'sm', 'md' => 'md', 'lg' => 'lg'];
    $cssSize = $sizeMap[$size] ?? 'sm';
    if ($avatar && $avatar !== '') {
        return '<img src="' . e($avatar) . '" alt="' . e($name) . '" class="avatar avatar-' . $cssSize . '">';
    }
    $url = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0ea5e9&color=fff&size=128';
    return '<img src="' . $url . '" alt="' . e($name) . '" class="avatar avatar-' . $cssSize . '">';
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
