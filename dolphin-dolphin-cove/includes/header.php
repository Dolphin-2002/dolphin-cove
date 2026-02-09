<?php
require_once __DIR__ . '/config.php';
$currentUser = getCurrentUser();
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($_SESSION['theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="icon" href="<?= SITE_URL ?>/assets/images/Dolphin-cove.png">
</head>
<body>
    <?php if ($flash): ?>
    <div class="toast toast-<?= e($flash['type']) ?>" id="toast">
        <span><?= e($flash['message']) ?></span>
        <button onclick="this.parentElement.remove()" class="toast-close">&times;</button>
    </div>
    <script>setTimeout(() => { const t = document.getElementById('toast'); if(t) t.remove(); }, 4000);</script>
    <?php endif; ?>

    <header class="header">
        <div class="header-container">
            <a href="<?= SITE_URL ?>/<?= isLoggedIn() ? 'home.php' : 'index.php' ?>" class="header-logo">
                <img src="<?= SITE_URL ?>/assets/images/Dolphin-cove.png" alt="Dolphin Cove" class="logo-image">
                <span class="logo-text">Dolphin Cove</span>
            </a>

            <?php if (isLoggedIn()): ?>
            <div class="header-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search freelancers, projects, skills..." class="search-input">
            </div>
            <?php endif; ?>

            <nav class="header-nav">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= SITE_URL ?>/toggle_theme.php" class="nav-icon-btn" title="Toggle Theme">
                        <?php if (($_SESSION['theme'] ?? 'dark') === 'dark'): ?>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <?php else: ?>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <?php endif; ?>
                    </a>
                    <a href="<?= SITE_URL ?>/messages.php" class="nav-icon-btn" title="Messages">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    </a>
                    <a href="<?= SITE_URL ?>/notifications.php" class="nav-icon-btn" title="Notifications">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </a>
                    <div class="profile-dropdown">
                        <button class="profile-btn" onclick="document.querySelector('.dropdown-menu').classList.toggle('show')">
                            <?= userAvatar($currentUser['avatar_url'] ?? null, $currentUser['display_name'] ?? 'U', 'small') ?>
                        </button>
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <strong><?= e($currentUser['display_name'] ?? 'User') ?></strong>
                                <span>@<?= e($currentUser['username'] ?? 'username') ?></span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?= SITE_URL ?>/profile.php" class="dropdown-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>My Profile</span>
                            </a>
                            <a href="<?= SITE_URL ?>/settings.php" class="dropdown-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span>Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?= SITE_URL ?>/logout.php" class="dropdown-item logout">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <span>Log Out</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="<?= SITE_URL ?>/toggle_theme.php" class="nav-icon-btn" title="Toggle Theme">
                            <?php if (($_SESSION['theme'] ?? 'dark') === 'dark'): ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            <?php else: ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                            <?php endif; ?>
                        </a>
                        <a href="<?= SITE_URL ?>/login.php" class="btn btn-ghost">Log In</a>
                        <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary">Sign Up</a>
                    </div>
                <?php endif; ?>
            </nav>

            <button class="mobile-menu-btn" onclick="document.querySelector('.header-nav').classList.toggle('show')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </header>
