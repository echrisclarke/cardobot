<?php
/**
 * Optional ML sidecar client. Fails soft when unset or unreachable.
 */

require_once __DIR__ . '/env.php';

function ml_service_configured(): bool {
    $env = load_env();
    return trim((string)($env['ML_SERVICE_URL'] ?? '')) !== '';
}

function ml_request(string $path, array $payload = [], string $method = 'POST'): ?array {
    $env = load_env();
    $base = rtrim((string)($env['ML_SERVICE_URL'] ?? ''), '/');
    if ($base === '') {
        return null;
    }
    $token = (string)($env['ML_SERVICE_TOKEN'] ?? '');
    $url = $base . $path;

    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $code >= 400) {
        error_log('ml_client ' . $path . ' failed: ' . ($err ?: "HTTP {$code}"));
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function ml_health(): bool {
    $res = ml_request('/health', [], 'GET');
    // GET with empty POST opts -- fix: use dedicated GET
    return is_array($res) && !empty($res['ok']);
}

function ml_similar_hints(int $userId, string $text, int $k = 3): array {
    if ($text === '' || $userId <= 0 || !ml_service_configured()) {
        return [];
    }
    $res = ml_request('/similar', [
        'user_id' => $userId,
        'text' => $text,
        'k' => $k,
    ]);
    if (!$res || empty($res['matches']) || !is_array($res['matches'])) {
        return [];
    }
    $hints = [];
    foreach ($res['matches'] as $m) {
        $nick = trim((string)($m['nickname'] ?? ''));
        $score = isset($m['score']) ? round((float)$m['score'], 2) : null;
        if ($nick !== '') {
            $hints[] = $score !== null
                ? "earlier card \"{$nick}\" (similarity {$score})"
                : "earlier card \"{$nick}\"";
        }
    }
    return $hints;
}

function ml_index_card(int $userId, string $cardId, string $text, ?string $imageUrl = null): void {
    if (!ml_service_configured() || $cardId === '' || $text === '') {
        return;
    }
    ml_request('/index_card', [
        'user_id' => $userId,
        'card_id' => $cardId,
        'text' => $text,
        'image_url' => $imageUrl,
        'nickname' => '',
    ]);
}

function ml_safety_check(string $text): array {
    if ($text === '') {
        return ['safe' => true, 'categories' => []];
    }
    if (!ml_service_configured()) {
        return ['safe' => true, 'categories' => [], 'skipped' => true];
    }
    $res = ml_request('/safety_check', ['text' => $text]);
    if (!$res) {
        return ['safe' => true, 'categories' => [], 'skipped' => true];
    }
    return [
        'safe' => !empty($res['safe']),
        'categories' => $res['categories'] ?? [],
    ];
}
