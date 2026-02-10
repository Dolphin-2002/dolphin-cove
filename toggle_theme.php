<?php
require_once __DIR__ . '/config.php';
$current = $_SESSION['theme'] ?? 'dark';
$_SESSION['theme'] = ($current === 'dark') ? 'light' : 'dark';
$referer = $_SERVER['HTTP_REFERER'] ?? SITE_URL . '/index.php';
header("Location: " . $referer);
exit;
