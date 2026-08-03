<?php
/**
 * Emoji Fallback Generator API
 * Generates and saves emoji images as fallbacks for systems that don't support them
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Get emoji from request
$emoji = $_GET['emoji'] ?? $_POST['emoji'] ?? '';

if (empty($emoji)) {
    http_response_code(400);
    echo json_encode(['error' => 'No emoji provided']);
    exit;
}

// Get the base directory
$baseDir = __DIR__ . '/../assets/img/emoji';
$assetPath = '/cardobot/assets/img/emoji';

// Create emoji directory if it doesn't exist
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create emoji directory']);
        exit;
    }
}

// Convert emoji to Unicode code point(s) for filename
function emoji_to_filename($emoji) {
    $unicode = '';
    $chars = preg_split('//u', $emoji, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char) {
        $code = 0;
        // Use mb_ord if available
        if (function_exists('mb_ord')) {
            $code = mb_ord($char, 'UTF-8');
        } else {
            // Fallback: convert to UCS-4BE and unpack
            try {
                $converted = mb_convert_encoding($char, 'UCS-4BE', 'UTF-8');
                if ($converted !== false && strlen($converted) >= 4) {
                    $unpacked = unpack('N', $converted);
                    $code = $unpacked ? $unpacked[1] : 0;
                }
            } catch (Exception $e) {
                // If conversion fails, try alternative method
                $code = 0;
            }
        }
        if ($code > 0) {
            $unicode .= sprintf('%04x', $code) . '-';
        }
    }
    return rtrim($unicode, '-') . '.svg';
}

$filename = emoji_to_filename($emoji);
$filePath = $baseDir . '/' . $filename;

// If file already exists, return it
if (file_exists($filePath)) {
    $basePath = get_base_path();
    echo json_encode([
        'success' => true,
        'url' => $basePath . $assetPath . '/' . $filename,
        'cached' => true
    ]);
    exit;
}

// Fetch emoji from Twemoji CDN
// Twemoji uses Unicode code points in the filename format
function emoji_to_twemoji_url($emoji) {
    // Get Unicode code points
    $codePoints = [];
    $chars = preg_split('//u', $emoji, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char) {
        $code = 0;
        // Use mb_ord if available
        if (function_exists('mb_ord')) {
            $code = mb_ord($char, 'UTF-8');
        } else {
            // Fallback: convert to UCS-4BE and unpack
            try {
                $converted = mb_convert_encoding($char, 'UCS-4BE', 'UTF-8');
                if ($converted !== false && strlen($converted) >= 4) {
                    $unpacked = unpack('N', $converted);
                    $code = $unpacked ? $unpacked[1] : 0;
                }
            } catch (Exception $e) {
                // If conversion fails, try alternative method
                $code = 0;
            }
        }
        if ($code > 0) {
            $codePoints[] = sprintf('%x', $code);
        }
    }
    if (empty($codePoints)) {
        return null; // Return null if we couldn't convert
    }
    $codePoint = implode('-', $codePoints);
    return 'https://cdn.jsdelivr.net/npm/@twemoji/api@latest/assets/svg/' . $codePoint . '.svg';
}

$twemojiUrl = emoji_to_twemoji_url($emoji);

if (!$twemojiUrl) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to convert emoji to Unicode code points',
        'emoji' => $emoji
    ]);
    exit;
}

// Fetch the SVG from Twemoji CDN
$ch = curl_init($twemojiUrl);
if ($ch === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to initialize cURL',
        'url' => $twemojiUrl
    ]);
    exit;
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Card-o-Bot Emoji Fallback Generator');

$svgContent = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'error' => 'cURL error: ' . $curlError,
        'url' => $twemojiUrl
    ]);
    exit;
}

if ($httpCode !== 200 || empty($svgContent)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch emoji from Twemoji',
        'url' => $twemojiUrl,
        'http_code' => $httpCode
    ]);
    exit;
}

// Save the SVG file
if (file_put_contents($filePath, $svgContent) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save emoji file']);
    exit;
}

// Set proper permissions
chmod($filePath, 0644);

// Return the URL
$basePath = get_base_path();
echo json_encode([
    'success' => true,
    'url' => $basePath . $assetPath . '/' . $filename,
    'cached' => false
]);
