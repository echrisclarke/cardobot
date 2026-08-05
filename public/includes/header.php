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
i18n_seed_presets_if_needed();
$navLoc = function_exists('i18n_session_locale') ? i18n_session_locale() : 'en';
$navUser = get_logged_in_user();
$navUserId = (int)($navUser['id'] ?? 0);
$navIsTest = function_exists('cardobot_is_test_user')
    && cardobot_is_test_user((string)($navUser['username'] ?? ''));
// Test account boots English; don't sticky-select a prior Chinese preferred locale in the menu.
$navPreferred = ($navUserId > 0 && !$navIsTest)
    ? (i18n_user_preferred_locale($navUserId) ?: $navLoc)
    : $navLoc;
$navPreferred = i18n_normalize_code((string)$navPreferred) ?: 'en';
if ($navIsTest) {
    $navLoc = 'en';
    $navPreferred = 'en';
}
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
        <a href="<?php echo $basePath; ?>/profile.php?tab=profile#language" data-i18n="nav.language"><?php echo htmlspecialchars(i18n_t('nav.language', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php if (is_admin()): ?>
            <a href="<?php echo $basePath; ?>/admin/dashboard.php" data-i18n="nav.admin"><?php echo htmlspecialchars(i18n_t('nav.admin', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
        <a href="<?php echo $basePath; ?>/privacy.php" data-i18n="nav.privacy"><?php echo htmlspecialchars(i18n_t('nav.privacy', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo $basePath; ?>/terms.php" data-i18n="nav.terms"><?php echo htmlspecialchars(i18n_t('nav.terms', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo $basePath; ?>/logout.php" data-i18n="nav.logout"><?php echo htmlspecialchars(i18n_t('nav.logout', $navLoc), ENT_QUOTES, 'UTF-8'); ?></a>

        <div class="mobile-lang-picker" id="mobileLangPicker">
            <label for="menuLocaleSelect" data-i18n="nav.language"><?php echo htmlspecialchars(i18n_t('nav.language', $navLoc), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="menuLocaleSelect" class="mobile-lang-select" aria-label="<?php echo htmlspecialchars(i18n_t('nav.language', $navLoc), ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach (I18N_PRESET_LOCALES as $pCode => $meta): ?>
                    <option value="<?php echo htmlspecialchars($pCode); ?>" <?php echo $navPreferred === $pCode ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($meta['name_native'] . ' · ' . $meta['name_en']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="mobile-lang-status" id="menuLocaleStatus" hidden></p>
        </div>
    </nav>
</div>

<style>
.mobile-lang-picker {
  margin-top: 0.5rem;
  padding: 0.75rem;
  border: 1px solid var(--color-border-gray);
  border-radius: 0.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.mobile-lang-picker label {
  color: var(--color-secondary-light);
  font-size: calc(var(--font-size-sm) * 0.85);
}
.mobile-lang-select {
  width: 100%;
  box-sizing: border-box;
  padding: 0.55rem 0.65rem;
  border-radius: 0.25rem;
  border: 1px solid var(--color-border-gray);
  background: rgba(0, 0, 0, 0.45);
  color: var(--color-text-primary);
  font: inherit;
}
.mobile-lang-status {
  margin: 0;
  font-size: 0.8rem;
  color: var(--color-secondary-light);
}
</style>

<script>
// Hamburger menu + quick language switch
(function() {
    if (window.hamburgerMenuInitialized) {
        return;
    }
    window.hamburgerMenuInitialized = true;
    const basePath = <?php echo json_encode($basePath); ?>;

    function initHamburgerMenu() {
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const localeSelect = document.getElementById('menuLocaleSelect');
        const localeStatus = document.getElementById('menuLocaleStatus');

        if (!hamburger || !mobileMenu) {
            return;
        }

        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hamburger.classList.toggle('active');
            mobileMenu.classList.toggle('active');
        });

        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
            });
        });

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

        if (localeSelect) {
            localeSelect.addEventListener('change', async function() {
                const code = localeSelect.value;
                if (!code) return;
                localeSelect.disabled = true;
                if (localeStatus) {
                    localeStatus.hidden = false;
                    localeStatus.textContent = '…';
                }
                try {
                    const body = { action: 'set', code: code };
                    if (window.cardobotSessionId) {
                        body.session_id = window.cardobotSessionId;
                    }
                    const res = await fetch(basePath + '/api/locale.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json();
                    if (!res.ok || data.ok === false) {
                        throw new Error((data && data.message) || 'Language switch failed');
                    }
                    // Reload so intro, menu, and Cardy all match the new language.
                    window.location.reload();
                } catch (err) {
                    if (localeStatus) {
                        localeStatus.hidden = false;
                        localeStatus.textContent = err.message || 'Error';
                    }
                    localeSelect.disabled = false;
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHamburgerMenu);
    } else {
        setTimeout(initHamburgerMenu, 0);
    }
})();
</script>
