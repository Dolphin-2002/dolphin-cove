<?php
$pageTitle = 'Welcome';
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/home.php");
    exit;
}

// Get stats
$db = getDB();
$devCount = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'] ?? 0;
$jobCount = $db->query("SELECT COUNT(*) as cnt FROM freelance_jobs")->fetch_assoc()['cnt'] ?? 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="landing-page">
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-bg">
            <div class="ocean-gradient"></div>
        </div>
        <div class="hero-content">
            <div class="hero-badge">🌊 The Future of Freelance</div>
            <h1>
                Dive Into the <span class="gradient-text">Ocean</span> of<br>
                <span class="gradient-text">Tech Opportunities</span>
            </h1>
            <p class="hero-description">
                Connect with top developers, find freelance projects, share your code journey,
                and ride the wave to your next big opportunity. Join the pod today! 🐬
            </p>
            <div class="hero-cta">
                <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary btn-large">Start Your Journey &rarr;</a>
                <a href="<?= SITE_URL ?>/login.php" class="btn btn-secondary btn-large">Sign In</a>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <strong><?= number_format($devCount) ?></strong>
                    <span>Developers</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <strong><?= number_format($jobCount) ?></strong>
                    <span>Projects</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="dolphin-container">
                <img src="<?= SITE_URL ?>/assets/images/Dolphin-cove.png" alt="Dolphin Cove" class="hero-dolphin">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="section-header">
            <h2>Why Swim With Us?</h2>
            <p>Everything you need to thrive in the freelance ocean</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">&#x1F4BB;</div>
                <h3>Code &amp; Connect</h3>
                <p>Share your projects, code snippets, and tech insights with a community that speaks your language.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💼</div>
                <h3>Freelance Hub</h3>
                <p>Post and find freelance opportunities. From quick gigs to long-term projects, we've got you covered.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Build Your Pod</h3>
                <p>Network with like-minded developers, form teams, and collaborate on exciting ventures.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Real-Time Updates</h3>
                <p>Stay current with instant notifications, trending topics, and the latest in tech.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🌍</div>
                <h3>Remote First</h3>
                <p>Work from anywhere in the world. Our platform is built for the global developer community.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Secure &amp; Safe</h3>
                <p>Your data and transactions are protected with industry-leading security measures.</p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Ready to Make a Splash?</h2>
            <p>Join thousands of developers who've found their flow in Dolphin Cove</p>
            <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary btn-large">Join the Pod Today 🌊</a>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
