<?php
/**
 * Thin xAI (Grok Imagine) image client.
 */

require_once __DIR__ . '/env.php';

if (!defined('XAI_API_BASE')) {
    define('XAI_API_BASE', 'https://api.x.ai');
}

/**
 * Generate an image via Grok Imagine.
 *
 * @return array { ok, error, http_code, image: {url|b64_json}, raw_response }
 */
function xai_image(string $prompt, array $opts = []): array {
    $key = get_xai_key(false);
    if ($key === '') {
        return [
            'ok' => false,
            'error' => 'XAI_API_KEY not configured',
            'http_code' => 500,
            'image' => null,
            'raw_response' => null,
        ];
    }

    $model = $opts['model'] ?? get_xai_image_model();
    $payload = [
        'model' => $model,
        'prompt' => $prompt,
        'n' => (int)($opts['n'] ?? 1),
    ];
    if (!empty($opts['size'])) {
        $payload['size'] = $opts['size'];
    }

    $ch = curl_init(XAI_API_BASE . '/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)($opts['timeout'] ?? 120),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'ok' => false,
            'error' => 'cURL error: ' . $curlError,
            'http_code' => 0,
            'image' => null,
            'raw_response' => null,
        ];
    }

    $data = json_decode((string)$raw, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($data)) {
        $msg = is_array($data) && isset($data['error'])
            ? (is_string($data['error']) ? $data['error'] : ($data['error']['message'] ?? 'xAI image error'))
            : 'xAI image error (HTTP ' . $httpCode . ')';
        return [
            'ok' => false,
            'error' => $msg,
            'http_code' => $httpCode,
            'image' => null,
            'raw_response' => is_array($data) ? $data : null,
        ];
    }

    $first = $data['data'][0] ?? null;
    if (!is_array($first)) {
        // Some xAI responses nest differently
        $first = $data['images'][0] ?? null;
    }
    if (!is_array($first)) {
        return [
            'ok' => false,
            'error' => 'No image in xAI response',
            'http_code' => $httpCode,
            'image' => null,
            'raw_response' => $data,
        ];
    }

    $image = [];
    if (!empty($first['url'])) {
        $image['url'] = $first['url'];
    }
    if (!empty($first['b64_json'])) {
        $image['b64_json'] = $first['b64_json'];
    } elseif (!empty($first['b64'])) {
        $image['b64_json'] = $first['b64'];
    }
    if (empty($image)) {
        return [
            'ok' => false,
            'error' => 'xAI image missing url/b64',
            'http_code' => $httpCode,
            'image' => null,
            'raw_response' => $data,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'http_code' => $httpCode,
        'image' => $image,
        'raw_response' => $data,
    ];
}

/**
 * Provider facade for card art generation.
 * Default: xAI Grok Imagine, with automatic OpenAI fallback on failure.
 */
function generate_card_image(string $prompt, array $opts = []): array {
    $provider = get_image_provider();
    $openaiOpts = array_merge([
        'size' => '1024x1024',
        'quality' => 'high',
    ], $opts);

    if ($provider === 'openai') {
        require_once __DIR__ . '/openai.php';
        return openai_image($prompt, $openaiOpts);
    }

    // IMAGE_PROVIDER=xai (default): try Grok Imagine first
    $result = xai_image($prompt, $opts);
    if (!empty($result['ok'])) {
        $result['provider'] = 'xai';
        return $result;
    }

    // Fallback to OpenAI if key is available
    require_once __DIR__ . '/openai.php';
    $openaiKey = get_openai_key(false);
    if ($openaiKey === '') {
        $result['provider'] = 'xai';
        return $result;
    }

    error_log('generate_card_image: xAI failed (' . ($result['error'] ?? 'unknown') . '); falling back to OpenAI');
    $fallback = openai_image($prompt, $openaiOpts);
    $fallback['provider'] = 'openai';
    $fallback['fallback_from'] = 'xai';
    $fallback['xai_error'] = $result['error'] ?? null;
    return $fallback;
}
