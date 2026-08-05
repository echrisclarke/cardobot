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

if (!function_exists('i18n_t')) {
    require_once __DIR__ . '/i18n.php';
}
$navLoc = function_exists('i18n_session_locale') ? i18n_session_locale() : 'en';
?>
<!-- Mobile Header -->
<div class="chat-header">
    <h2 class="chat-title">Card-o-Bot</h2>
    <button class="hamburger" id="hamburger" aria-label="<?php echo htmlspecialchars(i18n_t('nav.menu', $navLoc), ENT_QUOTES, 'UTF-8'); ?>" data-i18n-aria="nav.menu">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>
</div>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <nav class="mobile-nav">
        <a href="<?php echo $basePath; ?>/index.php" data-i18n="nav.home"><?php echo htmlspecialchars(i18n_t('nav.home', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo $basePath; ?>/profile.php" data-i18n="nav.profile"><?php echo htmlspecialchars(i18n_t('nav.profile', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php if (is_admin()): ?>
            <a href="<?php echo $basePath; ?>/admin/dashboard.php" data-i18n="nav.admin"><?php echo htmlspecialchars(i18n_t('nav.admin', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
        <a href="<?php echo $basePath; ?>/privacy.php" data-i18n="nav.privacy"><?php echo htmlspecialchars(i18n_t('nav.privacy', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo $basePath; ?>/terms.php" data-i18n="nav.terms"><?php echo htmlspecialchars(i18n_t('nav.terms', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo $basePath; ?>/logout.php" data-i18n="nav.logout"><?php echo htmlspecialchars(i18n_t('nav.logout', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
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
