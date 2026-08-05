<?php
/**
 * Durable image generation tasks in MySQL.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

function image_tasks_pdo(): ?PDO {
    return get_auth_db() ?: get_db_connection();
}

function image_task_create(int $userId, string $sessionId, string $prompt, string $source, ?array $visual = null): ?string {
    $pdo = image_tasks_pdo();
    if (!$pdo) {
        return null;
    }
    $taskId = 'img_' . time() . '_' . bin2hex(random_bytes(3));
    try {
        $stmt = $pdo->prepare("
            INSERT INTO cardobot_image_tasks
                (task_id, user_id, session_id, status, source, prompt, visual_json, created_at)
            VALUES (?, ?, ?, 'generating', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $taskId,
            $userId,
            $sessionId,
            $source,
            $prompt,
            $visual ? json_encode($visual) : null,
        ]);
        return $taskId;
    } catch (PDOException $e) {
        error_log('image_task_create: ' . $e->getMessage());
        return null;
    }
}

function image_task_get(string $taskId, int $userId): ?array {
    $pdo = image_tasks_pdo();
    if (!$pdo || $taskId === '') {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM cardobot_image_tasks WHERE task_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$taskId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('image_task_get: ' . $e->getMessage());
        return null;
    }
}

function image_task_update(string $taskId, array $fields): bool {
    $pdo = image_tasks_pdo();
    if (!$pdo || $taskId === '' || empty($fields)) {
        return false;
    }
    $allowed = ['status', 'source', 'prompt', 'image_url', 'error', 'visual_json'];
    $sets = [];
    $vals = [];
    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            continue;
        }
        $sets[] = "`$k` = ?";
        $vals[] = $v;
    }
    if (empty($sets)) {
        return false;
    }
    if (isset($fields['status']) && in_array($fields['status'], ['completed', 'failed'], true)) {
        $sets[] = 'completed_at = NOW()';
    }
    $vals[] = $taskId;
    try {
        $stmt = $pdo->prepare('UPDATE cardobot_image_tasks SET ' . implode(', ', $sets) . ' WHERE task_id = ?');
        $stmt->execute($vals);
        return true;
    } catch (PDOException $e) {
        error_log('image_task_update: ' . $e->getMessage());
        return false;
    }
}

/**
 * Persist remote or b64 art under UPLOAD_ROOT; return public-ish path URL segment.
 */
function persist_generated_art(int $userId, string $sessionId, array $image): ?string {
    $bin = null;
    if (!empty($image['b64_json'])) {
        $bin = base64_decode(str_replace(' ', '+', (string)$image['b64_json']), true);
    } elseif (!empty($image['url'])) {
        $url = (string)$image['url'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $bin = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($bin === false || $code >= 400) {
            $bin = null;
        }
    }
    if ($bin === null || strlen($bin) < 100) {
        return null;
    }

    $root = get_upload_root();
    $dir = $root . '/art/' . $userId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $filename = $sessionId . '_art.png';
    $path = $dir . '/' . $filename;
    if (file_put_contents($path, $bin) === false) {
        return null;
    }

    // Served via download helper or relative uploads route
    $base = get_base_path();
    return $base . '/api/download-card.php?card_id=' . rawurlencode($sessionId) . '&kind=art';
}
