<?php if (isLoggedIn()): ?>
<aside class="sidebar">
    <!-- User Profile Card -->
    <div class="sidebar-profile">
        <div class="profile-cover">
            <div class="cover-waves"></div>
        </div>
        <div class="profile-info">
            <?= userAvatar($currentUser['avatar_url'] ?? null, $currentUser['display_name'] ?? 'U', 'medium') ?>
            <h3><?= e($currentUser['display_name'] ?? 'New User') ?></h3>
            <p>@<?= e($currentUser['username'] ?? 'username') ?></p>
            <?php if ($currentUser['is_freelancer'] ?? false): ?>
                <span class="freelancer-badge">&#x1F4BB; Freelancer</span>
            <?php endif; ?>
        </div>
        <div class="profile-stats">
            <div class="stat">
                <strong>
                    <?php
                    $db = getDB();
                    $cStmt = $db->prepare("SELECT COUNT(*) as cnt FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'");
                    $uid = getCurrentUserId();
                    $cStmt->bind_param("ii", $uid, $uid);
                    $cStmt->execute();
                    echo $cStmt->get_result()->fetch_assoc()['cnt'];
                    $cStmt->close();
                    ?>
                </strong>
                <span>Connections</span>
            </div>
            <div class="stat">
                <strong><?= ($currentUser['completed_jobs'] ?? 0) ?></strong>
                <span>Projects</span>
            </div>
            <div class="stat">
                <strong><?= ($currentUser['rating'] ?? 0) > 0 ? number_format($currentUser['rating'], 1) : '-' ?></strong>
                <span>Rating</span>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <a href="<?= SITE_URL ?>/home.php" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Home Feed</span>
        </a>
        <a href="<?= SITE_URL ?>/profile.php" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>My Profile</span>
        </a>
        <a href="<?= SITE_URL ?>/freelance.php" class="nav-link <?= $currentPage === 'freelance' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <span>Freelance Jobs</span>
        </a>
        <a href="<?= SITE_URL ?>/tasks.php" class="nav-link <?= $currentPage === 'tasks' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <span>My Tasks</span>
        </a>
        <a href="<?= SITE_URL ?>/network.php" class="nav-link <?= $currentPage === 'network' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>My Network</span>
        </a>
        <a href="<?= SITE_URL ?>/messages.php" class="nav-link <?= $currentPage === 'messages' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Messages</span>
        </a>
        <a href="<?= SITE_URL ?>/notifications.php" class="nav-link <?= $currentPage === 'notifications' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Notifications</span>
        </a>
        <a href="<?= SITE_URL ?>/trending.php" class="nav-link <?= $currentPage === 'trending' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            <span>Trending</span>
        </a>
        <a href="<?= SITE_URL ?>/saved.php" class="nav-link <?= $currentPage === 'saved' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            <span>Saved Posts</span>
        </a>
        <a href="<?= SITE_URL ?>/settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Settings</span>
        </a>
    </nav>

    <!-- Skills -->
    <div class="sidebar-section">
        <h4>🌊 Your Skills</h4>
        <div class="trending-skills">
            <?php
            $skills = array_filter(explode(',', $currentUser['skills'] ?? ''));
            if (!empty($skills)):
                foreach ($skills as $skill):
            ?>
                <span class="skill-tag"><?= e(trim($skill)) ?></span>
            <?php endforeach; else: ?>
                <p class="empty-state-text">Add skills in your profile</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <p>&copy; 2026 Dolphin Cove</p>
        <p>Ride the wave of freelance 🌊</p>
    </div>
</aside>
<?php endif; ?>
