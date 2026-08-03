<?php
/**
 * Twemoji Helper Functions
 * Automatically downloads Twemoji icons on demand and caches them locally
 */

/**
 * Convert emoji to Twemoji SVG URL path
 * Downloads the icon if it doesn't exist locally
 * 
 * @param string $emoji The emoji character (e.g., "👤", "🔒", "🤖")
 * @param int $size Size of the icon (72x72 is default, but we'll use SVG)
 * @return string URL path to the Twemoji SVG, or empty string on error
 */
function get_twemoji_path(string $emoji, int $size = 72): string {
    if (empty($emoji)) {
        return '';
    }
    
    // Get the Unicode code point(s) for the emoji
    $codePoint = get_emoji_code_point($emoji);
    if (empty($codePoint)) {
        return '';
    }
    
    $assetPath = get_asset_path();
    $baseDir = __DIR__ . '/../assets/img/twemoji';
    $baseUrl = $assetPath . '/assets/img/twemoji';
    
    // Create directory if it doesn't exist
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
            error_log("Twemoji: Failed to create directory: $baseDir");
            return '';
        }
    }
    
    // Use get.php endpoint for on-demand downloading
    // The get.php script will handle downloading if the file doesn't exist
    $urlPath = $baseUrl . '/get.php?emoji=' . strtolower($codePoint);
    
    return $urlPath;
}

/**
 * Get Unicode code point for an emoji
 * Handles multi-byte emojis (including skin tone modifiers, etc.)
 * 
 * @param string $emoji The emoji character
 * @return string Code point in lowercase hex (e.g., "1f464" for 👤)
 */
function get_emoji_code_point(string $emoji): string {
    if (empty($emoji)) {
        return '';
    }
    
    // Convert to UTF-32 to get code points
    $codePoints = [];
    $length = mb_strlen($emoji, 'UTF-8');
    
    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($emoji, $i, 1, 'UTF-8');
        $code = mb_ord($char, 'UTF-8');
        if ($code !== false) {
            $codePoints[] = dechex($code);
        }
    }
    
    // For most emojis, we want the first code point
    // For emojis with modifiers (like skin tones), we might need to handle differently
    // Twemoji uses the base code point, so we'll use the first one
    if (!empty($codePoints)) {
        // Join with hyphen for multi-code-point emojis (like flags)
        // But for single emojis, just return the first code point
        return strtolower($codePoints[0]);
    }
    
    return '';
}

/**
 * Download Twemoji SVG from CDN and save locally
 * 
 * @param string $url CDN URL to download from
 * @param string $filePath Local file path to save to
 * @return bool True if successful, false otherwise
 */
function download_twemoji(string $url, string $filePath): bool {
    // Use cURL if available (more reliable)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Card-o-Bot Twemoji Downloader');
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($content)) {
            // Verify it's actually an SVG
            if (strpos($content, '<svg') !== false || strpos($content, '<?xml') !== false) {
                $result = file_put_contents($filePath, $content);
                if ($result !== false) {
                    chmod($filePath, 0644);
                    return true;
                }
            }
        }
    }
    
    // Fallback to file_get_contents if cURL not available
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Card-o-Bot Twemoji Downloader'
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content !== false && !empty($content)) {
        // Verify it's actually an SVG
        if (strpos($content, '<svg') !== false || strpos($content, '<?xml') !== false) {
            $result = file_put_contents($filePath, $content);
            if ($result !== false) {
                chmod($filePath, 0644);
                return true;
            }
        }
    }
    
    error_log("Twemoji: Failed to download from $url");
    return false;
}

/**
 * Replace emojis in text with Twemoji image tags
 * 
 * @param string $text Text containing emojis
 * @param string $class Optional CSS class to add to img tags
 * @return string Text with emojis replaced by img tags
 */
function replace_emojis_with_twemoji(string $text, string $class = 'twemoji-icon'): string {
    // Pattern to match emojis (Unicode emoji ranges)
    // This is a simplified pattern - you might want to expand it
    $pattern = '/([\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}])/u';
    
    return preg_replace_callback($pattern, function($matches) use ($class) {
        $emoji = $matches[1];
        $twemojiPath = get_twemoji_path($emoji);
        
        if (!empty($twemojiPath)) {
            // Return img tag with Twemoji
            $alt = htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8');
            return '<img src="' . htmlspecialchars($twemojiPath, ENT_QUOTES, 'UTF-8') . 
                   '" alt="' . $alt . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . 
                   '" draggable="false" style="height: 1em; width: 1em; vertical-align: middle;">';
        }
        
        // Fallback: return original emoji if download failed
        return $emoji;
    }, $text);
}

/**
 * Get Twemoji image tag for a specific emoji
 * 
 * @param string $emoji The emoji character
 * @param string $class Optional CSS class
 * @param string $alt Optional alt text (defaults to emoji)
 * @return string HTML img tag or original emoji if failed
 */
function twemoji_img(string $emoji, string $class = 'twemoji-icon', string $alt = ''): string {
    if (empty($emoji)) {
        return '';
    }
    
    $twemojiPath = get_twemoji_path($emoji);
    
    if (!empty($twemojiPath)) {
        if (empty($alt)) {
            $alt = $emoji;
        }
        $altEscaped = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $pathEscaped = htmlspecialchars($twemojiPath, ENT_QUOTES, 'UTF-8');
        $classEscaped = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        
        return '<img src="' . $pathEscaped . '" alt="' . $altEscaped . 
               '" class="' . $classEscaped . '" draggable="false" style="height: 1em; width: 1em; vertical-align: middle;">';
    }
    
    // Fallback: return original emoji
    return htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8');
}
