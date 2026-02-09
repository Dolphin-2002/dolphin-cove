    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="<?= SITE_URL ?>/assets/images/Dolphin-cove.png" alt="Dolphin Cove" class="footer-logo">
                <span>Dolphin Cove</span>
            </div>
            <p>&copy; 2026 Dolphin Cove. Ride the wave of freelance 🌊</p>
        </div>
    </footer>

    <script>
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.profile-dropdown')) {
                const menu = document.querySelector('.dropdown-menu');
                if (menu) menu.classList.remove('show');
            }
        });
    </script>
</body>
</html>
