<?php
/**
 * Card-o-Bot Console Include
 * Provides reusable console wrapper for all pages
 */

/**
 * Gets a random background image URL path
 * 
 * @return string URL path to random background image
 */
/**
 * Cache-busted URL for a Card-o-Bot asset.
 * Combines the live $assetPath (which switches between '' on cardobot.com
 * and '/cardobot' on herbiecreative.com) with ?v=<filemtime>.
 */
function cardobot_asset_url(string $pathFromCardobotRoot): string {
    $assetPath = get_asset_path();
    $rel       = ltrim($pathFromCardobotRoot, '/');
    $url       = rtrim($assetPath, '/') . '/' . $rel;
    // Resolve from public/ (this file lives in public/includes/)
    $disk = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($disk)) {
        $url .= '?v=' . filemtime($disk);
    } else {
        $url .= '?v=' . time();
    }
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

function get_random_background() {
    $assetPath = get_asset_path();
    $backgrounds = [
        'background1.jpg',
        'background1b.jpg',
        'background2.jpg',
        'background2b.jpg',
        'background3.jpg',
        'background3b.jpg',
        'background4.jpg',
        'background4b.jpg'
    ];
    $randomIndex = array_rand($backgrounds);
    $bgFile = $backgrounds[$randomIndex];
    // Return absolute path from document root
    return $assetPath . '/assets/img/' . $bgFile;
}

/**
 * Renders the console wrapper with the provided content
 * 
 * @param string $pageTitle The page title (for <title> tag)
 * @param callable|null $contentCallback Optional callback function that outputs the page content
 * @param string|null $contentString Optional string content (if callback not provided)
 * @return void
 */
function render_console($pageTitle = 'Card-o-Bot', $contentCallback = null, $contentString = null) {
    $assetPath = get_asset_path();
    $randomBg = get_random_background();
    $showWelcome = !is_logged_in() && !isset($_GET['register']); // Show welcome screen only for non-logged-in users who are not on the register page
    ?>
<!DOCTYPE html>
<html lang="en" style="background: url('<?php echo htmlspecialchars($randomBg); ?>') var(--bg-image-repeat) var(--bg-image-position); background-attachment: fixed; background-size: var(--bg-image-size); -webkit-background-size: var(--bg-image-size); -moz-background-size: var(--bg-image-size); -o-background-size: var(--bg-image-size);">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/png" href="<?php echo cardobot_asset_url('assets/img/CardobotLogo2-21.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo cardobot_asset_url('assets/img/CardobotLogo2-21.png'); ?>">
    <link rel="stylesheet" href="<?php echo cardobot_asset_url('assets/css/base.css'); ?>">
</head>
<body class="console-page">
    <div class="console-wrapper">
        <!-- 3D Profile - Right side only, underneath console -->
        <div class="console-3d-profile"></div>
        
        <div class="cardobot-console">
            <!-- Console Title -->
            <div class="console-title">CARD-O-BOT</div>
            
            <!-- Decorative Screws (4 corners) -->
            <div class="console-screw console-screw-top-left"></div>
            <div class="console-screw console-screw-top-right"></div>
            <div class="console-screw console-screw-bottom-left"></div>
            <div class="console-screw console-screw-bottom-right"></div>
            
            <!-- Screen 3D Profile - Grey duplicate behind screen -->
            <div class="console-screen-3d"></div>
            
            <!-- Screen Area (contains all content) -->
            <div class="console-screen">
                <?php if ($showWelcome): ?>
                <!-- Welcome Screen Overlay -->
                <div class="welcome-screen-overlay" id="welcomeScreen">
                    <img src="<?php echo $assetPath; ?>/assets/img/Title_Card_Start.png" alt="Card-o-Bot Welcome" class="welcome-screen-image">
                </div>
                <?php endif; ?>
                <div class="console-screen-content">
                    <?php
                    // Output content from callback or string
                    if ($contentCallback && is_callable($contentCallback)) {
                        $contentCallback();
                    } elseif ($contentString !== null) {
                        echo $contentString;
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php if ($showWelcome): ?>
    <script>
        // Dismiss welcome screen on click/tap
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeScreen = document.getElementById('welcomeScreen');
            if (welcomeScreen) {
                welcomeScreen.addEventListener('click', function() {
                    welcomeScreen.style.opacity = '0';
                    setTimeout(function() {
                        welcomeScreen.style.display = 'none';
                    }, 300);
                });
                // Also support touch events for mobile
                welcomeScreen.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    welcomeScreen.style.opacity = '0';
                    setTimeout(function() {
                        welcomeScreen.style.display = 'none';
                    }, 300);
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
    <?php
}

/**
 * Alternative: Start console wrapper (for use with output buffering)
 * Use this with console_end() for more flexibility
 */
function console_start($pageTitle = 'Card-o-Bot', $isLoginPage = false) {
    $assetPath = get_asset_path();
    $randomBg = get_random_background();
    $showWelcome = $isLoginPage && !is_logged_in() && !isset($_GET['register']); // Show welcome screen only on login, not public legal pages.
    $bodyClasses = 'console-page';
    if ($isLoginPage) {
        $bodyClasses .= ' login-page';
    }
    ?>
<!DOCTYPE html>
<html lang="en" style="background: url('<?php echo htmlspecialchars($randomBg); ?>') var(--bg-image-repeat) var(--bg-image-position); background-attachment: fixed; background-size: var(--bg-image-size); -webkit-background-size: var(--bg-image-size); -moz-background-size: var(--bg-image-size); -o-background-size: var(--bg-image-size);">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/png" href="<?php echo cardobot_asset_url('assets/img/CardobotLogo2-21.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo cardobot_asset_url('assets/img/CardobotLogo2-21.png'); ?>">
    <link rel="stylesheet" href="<?php echo cardobot_asset_url('assets/css/base.css'); ?>">
</head>
<body class="<?php echo $bodyClasses; ?>">
    <div class="console-wrapper">
        <!-- 3D Profile - Right side only, underneath console -->
        <div class="console-3d-profile"></div>
        
        <div class="cardobot-console">
            <!-- Console Title -->
            <div class="console-title">CARD-O-BOT</div>
            
            <!-- Decorative Screws (4 corners) -->
            <div class="console-screw console-screw-top-left"></div>
            <div class="console-screw console-screw-top-right"></div>
            <div class="console-screw console-screw-bottom-left"></div>
            <div class="console-screw console-screw-bottom-right"></div>
            
            <!-- Screen 3D Profile - Grey duplicate behind screen -->
            <div class="console-screen-3d"></div>
            
            <!-- Screen Area (contains all content) -->
            <div class="console-screen">
                <?php if ($showWelcome): ?>
                <!-- Welcome Screen Overlay -->
                <div class="welcome-screen-overlay" id="welcomeScreen">
                    <img src="<?php echo $assetPath; ?>/assets/img/Title_Card_Start.png" alt="Card-o-Bot Welcome" class="welcome-screen-image">
                </div>
                <?php endif; ?>
                <?php
                // Include header for logged-in users (not on login page) - outside console-screen-content
                if (is_logged_in()) {
                    require_once __DIR__ . '/header.php';
                }
                ?>
                <div class="console-screen-content">
    <?php
}

/**
 * Close console-screen-content early (for pages that need content outside it)
 * Use this before writing content that should be outside console-screen-content
 */
function console_content_end() {
    ?>
                </div>
    <?php
}

/**
 * End console wrapper (use with console_start())
 * @param bool $contentAlreadyClosed Set to true if console_content_end() was called
 */
function console_end($contentAlreadyClosed = false) {
    $assetPath = get_asset_path();
    $showWelcome = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/login.php') !== false && !is_logged_in() && !isset($_GET['register']);
    ?>
                <?php if (!$contentAlreadyClosed): ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($showWelcome): ?>
    <script>
        // Dismiss welcome screen on click/tap
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeScreen = document.getElementById('welcomeScreen');
            if (welcomeScreen) {
                welcomeScreen.addEventListener('click', function() {
                    welcomeScreen.style.opacity = '0';
                    setTimeout(function() {
                        welcomeScreen.style.display = 'none';
                    }, 300);
                });
                // Also support touch events for mobile
                welcomeScreen.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    welcomeScreen.style.opacity = '0';
                    setTimeout(function() {
                        welcomeScreen.style.display = 'none';
                    }, 300);
                });
            }
        });
    </script>
    <?php endif; ?>
    
    <script>
    // Retro emoji styling - Add glow effects to emojis
    document.addEventListener('DOMContentLoaded', function() {
        const consoleScreen = document.querySelector('.console-screen');
        const consoleContent = document.querySelector('.console-screen-content');
        const targetElements = [consoleScreen, consoleContent].filter(el => el !== null);
        
        if (targetElements.length === 0) return;
        
        // Track processed nodes to prevent infinite loops
        const processedNodes = new WeakSet();
        
        // Emoji to glow class mapping
        const emojiGlowMap = {
            '👤': 'emoji-profile',      // Profile/User - secondary light blue
            '🔒': 'emoji-security',     // Security/Lock - primary pink
            '🤖': 'emoji-bot',          // Bot - secondary light blue
            '🐾': 'emoji-critter',      // Critter/Paw - mint green
            '⭐': 'emoji-star',          // Star - beige/yellow
            '⚠️': 'emoji-warning',      // Warning - beige/yellow (original filled version)
            '✅': 'emoji-success',      // Success - mint green
            '❌': 'emoji-error',        // Error - primary pink
            '🔗': 'emoji-link'          // Link - secondary light blue
        };
        
        function addEmojiGlow(element) {
            if (!element) return;
            
            // Skip if already processed
            if (processedNodes.has(element)) {
                return;
            }
            
            // Mark element as processed
            processedNodes.add(element);
            
            // Walk through all text nodes
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: function(node) {
                        // Skip if already processed or inside script/style tags
                        if (node.parentNode.nodeName === 'SCRIPT' || 
                            node.parentNode.nodeName === 'STYLE' ||
                            node.parentNode.classList.contains('emoji-profile') ||
                            node.parentNode.classList.contains('emoji-security') ||
                            node.parentNode.classList.contains('emoji-bot') ||
                            node.parentNode.classList.contains('emoji-critter') ||
                            node.parentNode.classList.contains('emoji-star') ||
                            node.parentNode.classList.contains('emoji-warning') ||
                            node.parentNode.classList.contains('emoji-success') ||
                            node.parentNode.classList.contains('emoji-error') ||
                            node.parentNode.classList.contains('emoji-link') ||
                            node.parentNode.classList.contains('retro-symbol')) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        return NodeFilter.FILTER_ACCEPT;
                    }
                },
                false
            );
            
            const textNodes = [];
            let node;
            while (node = walker.nextNode()) {
                textNodes.push(node);
            }
            
            // Process all text nodes and replace emojis directly
            for (const textNode of textNodes) {
                let text = textNode.textContent;
                
                // Skip if this text node already contains emoji wrappers (already processed)
                if (textNode.parentElement && (
                    textNode.parentElement.classList.contains('emoji-profile') ||
                    textNode.parentElement.classList.contains('emoji-security') ||
                    textNode.parentElement.classList.contains('emoji-bot') ||
                    textNode.parentElement.classList.contains('emoji-critter') ||
                    textNode.parentElement.classList.contains('emoji-star') ||
                    textNode.parentElement.classList.contains('emoji-warning') ||
                    textNode.parentElement.classList.contains('emoji-success') ||
                    textNode.parentElement.classList.contains('emoji-error') ||
                    textNode.parentElement.classList.contains('emoji-link')
                )) {
                    continue;
                }
                
                let newHTML = text;
                let replaced = false;
                
                // Find and replace emojis directly
                Object.keys(emojiGlowMap).forEach(emoji => {
                    if (text.includes(emoji)) {
                        const glowClass = emojiGlowMap[emoji];
                        const regex = new RegExp(emoji.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                        
                        // Replace emojis with wrapped spans (using HTML string)
                        // If emoji is not supported by the system, it simply won't display (fallback: show nothing)
                        newHTML = newHTML.replace(regex, (match) => {
                            // Wrap emoji in span with glow class
                            // If system doesn't support the emoji, browser won't display it - that's the fallback
                            return `<span class="${glowClass}">${match}</span>`;
                        });
                        replaced = true;
                    }
                });
                
                if (replaced) {
                    const wrapper = document.createElement('span');
                    wrapper.innerHTML = newHTML;
                    textNode.parentNode.replaceChild(wrapper, textNode);
                }
            }
        }
        
        // Add glow to emojis in console screen and content
        targetElements.forEach(el => {
            addEmojiGlow(el);
        });
        
        // Watch for dynamically added content
        targetElements.forEach(el => {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Skip if already processed
                            if (processedNodes.has(node)) {
                                return;
                            }
                            
                            // Skip if node is an emoji wrapper we just created
                            if (node.classList && (
                                node.classList.contains('emoji-profile') ||
                                node.classList.contains('emoji-security') ||
                                node.classList.contains('emoji-bot') ||
                                node.classList.contains('emoji-critter') ||
                                node.classList.contains('emoji-star') ||
                                node.classList.contains('emoji-warning') ||
                                node.classList.contains('emoji-success') ||
                                node.classList.contains('emoji-error') ||
                                node.classList.contains('emoji-link')
                            )) {
                                return;
                            }
                            
                            // Skip if node is inside an emoji wrapper
                            if (node.closest && node.closest('.emoji-profile, .emoji-security, .emoji-bot, .emoji-critter, .emoji-star, .emoji-warning, .emoji-success, .emoji-error, .emoji-link')) {
                                return;
                            }
                            
                            // Mark as processed before processing
                            processedNodes.add(node);
                            addEmojiGlow(node);
                        }
                    });
                });
            });
            
            observer.observe(el, {
                childList: true,
                subtree: true
            });
        });
        
        // Add terminal-style blinking cursor to all input fields
        function addTerminalCursorToInputs() {
            const inputs = document.querySelectorAll(
                'body.console-page:not(.admin-page) .console-screen input[type="text"], ' +
                'body.console-page:not(.admin-page) .console-screen input[type="email"], ' +
                'body.console-page:not(.admin-page) .console-screen input[type="password"], ' +
                'body.console-page:not(.admin-page) .console-screen input[type="number"], ' +
                'body.console-page:not(.admin-page) .console-screen input[type="url"], ' +
                'body.console-page:not(.admin-page) .console-screen input[type="search"], ' +
                'body.console-page:not(.admin-page) .console-screen textarea, ' +
                'body.console-page:not(.admin-page) .console-screen-content input[type="text"], ' +
                'body.console-page:not(.admin-page) .console-screen-content input[type="email"], ' +
                'body.console-page:not(.admin-page) .console-screen-content input[type="password"], ' +
                'body.console-page:not(.admin-page) .console-screen-content input[type="number"], ' +
                'body.console-page:not(.admin-page) .console-screen-content input[type="url"], ' +
                'body.console-page:not(.admin-page) .console-screen-content input[type="search"], ' +
                'body.console-page:not(.admin-page) .console-screen-content textarea'
            );
            
            inputs.forEach(input => {
                // Skip if already processed
                if (input.dataset.terminalCursorAdded) return;
                input.dataset.terminalCursorAdded = 'true';
                
                // Hide native caret
                input.style.caretColor = 'transparent';
                
                // Create custom terminal cursor
                const cursor = document.createElement('span');
                cursor.className = 'input-terminal-cursor';
                cursor.style.display = 'none';
                cursor.style.position = 'absolute';
                cursor.style.width = '0.5rem';
                cursor.style.height = '1rem';
                cursor.style.background = 'var(--color-secondary-light)';
                cursor.style.animation = 'blinkCursor 1s infinite';
                cursor.style.pointerEvents = 'none';
                cursor.style.zIndex = '9999';
                cursor.style.margin = '0';
                cursor.style.padding = '0';
                cursor.style.border = '0';
                
                // Append cursor to input's parent (or body if needed)
                let cursorContainer = input.parentElement;
                if (window.getComputedStyle(cursorContainer).position === 'static') {
                    cursorContainer.style.position = 'relative';
                }
                cursorContainer.appendChild(cursor);
                
                // Function to update cursor position - matches native caret exactly
                function updateCursorPosition() {
                    if (document.activeElement !== input) {
                        cursor.style.display = 'none';
                        return;
                    }
                    
                    cursor.style.display = 'block';
                    
                    // Get input's bounding box and computed styles
                    const inputRect = input.getBoundingClientRect();
                    const containerRect = cursorContainer.getBoundingClientRect();
                    const inputStyle = window.getComputedStyle(input);
                    
                    // Get text up to cursor position
                    const text = input.value.substring(0, input.selectionStart || input.value.length);
                    
                    // Create a measurement element that exactly matches the input's text rendering
                    const measureDiv = document.createElement('div');
                    measureDiv.style.position = 'absolute';
                    measureDiv.style.visibility = 'hidden';
                    measureDiv.style.whiteSpace = 'pre';
                    measureDiv.style.font = inputStyle.font || 
                        `${inputStyle.fontWeight} ${inputStyle.fontSize} ${inputStyle.fontFamily}`;
                    measureDiv.style.fontFamily = inputStyle.fontFamily;
                    measureDiv.style.fontSize = inputStyle.fontSize;
                    measureDiv.style.fontWeight = inputStyle.fontWeight;
                    measureDiv.style.fontStyle = inputStyle.fontStyle;
                    measureDiv.style.letterSpacing = inputStyle.letterSpacing;
                    measureDiv.style.textTransform = inputStyle.textTransform;
                    measureDiv.style.padding = '0';
                    measureDiv.style.border = '0';
                    measureDiv.style.margin = '0';
                    measureDiv.style.boxSizing = 'content-box';
                    measureDiv.textContent = text || ' '; // Use space if empty
                    
                    // Temporarily add to body to measure (more reliable)
                    document.body.appendChild(measureDiv);
                    const textWidth = measureDiv.offsetWidth;
                    document.body.removeChild(measureDiv);
                    
                    // Calculate horizontal position: input's left edge + padding + border + text width
                    const paddingLeft = parseFloat(inputStyle.paddingLeft) || 0;
                    const borderLeft = parseFloat(inputStyle.borderLeftWidth) || 0;
                    const leftOffset = inputRect.left - containerRect.left;
                    cursor.style.left = (leftOffset + paddingLeft + borderLeft + textWidth) + 'px';
                    
                    // Calculate vertical position: match the text baseline
                    const paddingTop = parseFloat(inputStyle.paddingTop) || 0;
                    const borderTop = parseFloat(inputStyle.borderTopWidth) || 0;
                    const fontSize = parseFloat(inputStyle.fontSize) || 16;
                    const lineHeight = parseFloat(inputStyle.lineHeight) || fontSize;
                    
                    // Position cursor at text baseline (slightly above bottom of line)
                    // The cursor height is 1rem, so center it on the text baseline
                    const topOffset = inputRect.top - containerRect.top;
                    const cursorTop = topOffset + paddingTop + borderTop + (lineHeight - fontSize) / 2 + fontSize * 0.1;
                    cursor.style.top = cursorTop + 'px';
                }
                
                // Show cursor on focus
                input.addEventListener('focus', function() {
                    cursor.style.display = 'block';
                    updateCursorPosition();
                });
                
                // Hide cursor on blur
                input.addEventListener('blur', function() {
                    cursor.style.display = 'none';
                });
                
                // Update cursor position on input
                input.addEventListener('input', updateCursorPosition);
                input.addEventListener('keyup', updateCursorPosition);
                input.addEventListener('keydown', updateCursorPosition);
                input.addEventListener('click', updateCursorPosition);
                input.addEventListener('select', updateCursorPosition);
                input.addEventListener('selectionchange', updateCursorPosition);
            });
        }
        
        // Initialize terminal cursors
        addTerminalCursorToInputs();
        
        // Also watch for dynamically added inputs
        targetElements.forEach(el => {
            const inputObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            const newInputs = node.querySelectorAll ? node.querySelectorAll('input, textarea') : [];
                            if (newInputs.length > 0 || (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA')) {
                                setTimeout(addTerminalCursorToInputs, 100);
                            }
                        }
                    });
                });
            });
            
            inputObserver.observe(el, {
                childList: true,
                subtree: true
            });
        });
    });
    </script>
</body>
</html>
    <?php
}
