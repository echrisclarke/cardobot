<?php
/**
 * Header Include for Card-o-Bot
 * Provides the mobile-style header with title and hamburger menu
 * Only shows for logged-in users
 */

if (!is_logged_in()) {
    return; // Don't show header on login page
}

$basePath = get_base_path();
?>
<!-- Mobile Header -->
<div class="chat-header">
    <h2 class="chat-title">Card-o-Bot</h2>
    <button class="hamburger" id="hamburger" aria-label="Menu">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>
</div>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <nav class="mobile-nav">
        <a href="<?php echo $basePath; ?>/index.php">Home</a>
        <a href="<?php echo $basePath; ?>/profile.php">Profile</a>
        <?php if (is_admin()): ?>
            <a href="<?php echo $basePath; ?>/admin/dashboard.php">Admin</a>
        <?php endif; ?>
        <a href="<?php echo $basePath; ?>/privacy.php">Privacy</a>
        <a href="<?php echo $basePath; ?>/terms.php">Terms</a>
        <a href="<?php echo $basePath; ?>/logout.php">Logout</a>
    </nav>
</div>

<script>
// Hamburger menu functionality
(function() {
    // Prevent duplicate initialization
    if (window.hamburgerMenuInitialized) {
        return;
    }
    window.hamburgerMenuInitialized = true;
    
    function initHamburgerMenu() {
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (!hamburger || !mobileMenu) {
            return;
        }
        
        // Toggle menu on hamburger click
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hamburger.classList.toggle('active');
            mobileMenu.classList.toggle('active');
        });
        
        // Close menu when clicking on a link
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
            });
        });
        
        // Close menu when clicking outside (only add once)
        if (!window.hamburgerOutsideClickHandler) {
            window.hamburgerOutsideClickHandler = function(e) {
                const h = document.getElementById('hamburger');
                const m = document.getElementById('mobileMenu');
                if (h && m && m.classList.contains('active')) {
                    if (!h.contains(e.target) && !m.contains(e.target)) {
                        h.classList.remove('active');
                        m.classList.remove('active');
                    }
                }
            };
            document.addEventListener('click', window.hamburgerOutsideClickHandler);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHamburgerMenu);
    } else {
        // DOM already loaded, initialize immediately
        setTimeout(initHamburgerMenu, 0);
    }
})();
</script>
