<?php
/**
 * Card management: list, save, delete.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/env.php';

function get_user_cards(int $userId): array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM cardobot_cards
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting user cards: ' . $e->getMessage());
        return [];
    }
}

function get_user_card_count(int $userId): int {
    $pdo = get_auth_db();
    if (!$pdo) {
        return 0;
    }
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cardobot_cards WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function get_user_cards_by_type(int $userId, string $type): array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM cardobot_cards
            WHERE user_id = ? AND type = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function get_card_for_user(int $userId, string $cardId): ?array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM cardobot_cards WHERE user_id = ? AND card_id = ? LIMIT 1');
        $stmt->execute([$userId, $cardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function delete_user_card(int $userId, string $cardId): bool {
    $pdo = get_auth_db();
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM cardobot_cards WHERE user_id = ? AND card_id = ?');
        $stmt->execute([$userId, $cardId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('delete_user_card: ' . $e->getMessage());
        return false;
    }
}

/**
 * Legacy: save raw AI art URL.
 */
function save_image_card(int $userId, string $sessionId, string $imageUrl, array $visualConcept): array {
    return save_finished_card($userId, $sessionId, [
        'image_url' => $imageUrl,
        'visual_concept' => $visualConcept,
    ]);
}

/**
 * Save a finished (composite or art) card with optional drawing + HSL.
 *
 * @param array $opts keys: image_url, drawing_data, hue, saturation, lightness, visual_concept, art_url
 */
function save_finished_card(int $userId, string $sessionId, array $opts): array {
    if ($userId <= 0) {
        return ['success' => false, 'card_id' => null, 'message' => 'Invalid user'];
    }
    $imageUrl = trim((string)($opts['image_url'] ?? ''));
    if ($sessionId === '' || $imageUrl === '') {
        return ['success' => false, 'card_id' => null, 'message' => 'Missing session or image'];
    }

    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'card_id' => null, 'message' => 'Database connection failed'];
    }

    $visualConcept = is_array($opts['visual_concept'] ?? null) ? $opts['visual_concept'] : [];
    $nickname = trim((string)($visualConcept['nickname'] ?? ''));
    if ($nickname === '') {
        $nickname = trim((string)($visualConcept['subject'] ?? ''));
    }
    if ($nickname === '') {
        $nickname = 'Untitled card';
    }
    $nickname = mb_substr($nickname, 0, 100);

    $bio = trim((string)($visualConcept['bio'] ?? ''));
    if ($bio === '') {
        $parts = [];
        if (!empty($visualConcept['subject'])) {
            $parts[] = $visualConcept['subject'] . '.';
        }
        if (!empty($visualConcept['vibe'])) {
            $parts[] = 'Vibe: ' . $visualConcept['vibe'] . '.';
        }
        if (!empty($visualConcept['details'])) {
            $parts[] = $visualConcept['details'];
        }
        $bio = trim(implode(' ', $parts));
    }

    $type = strtoupper(trim((string)($visualConcept['type'] ?? 'BOT')));
    if ($type !== 'CRITTER') {
        $type = 'BOT';
    }

    $power = mb_substr(trim((string)($visualConcept['power_name'] ?? '')), 0, 255);
    $abilityName = trim((string)($visualConcept['ability_name'] ?? ''));
    $abilityLine = trim((string)($visualConcept['ability_line'] ?? ''));
    if ($abilityName !== '' && $abilityLine !== '') {
        $ability = trim($abilityName . ' ' . $abilityLine);
    } else {
        $ability = $abilityName !== '' ? $abilityName : $abilityLine;
    }
    $drawing = $opts['drawing_data'] ?? null;
    if (is_array($drawing)) {
        $drawing = json_encode($drawing);
    }

    $hue = isset($opts['hue']) ? (int)$opts['hue'] : null;
    $sat = isset($opts['saturation']) ? (int)$opts['saturation'] : null;
    $light = isset($opts['lightness']) ? (int)$opts['lightness'] : null;

    $attrs = $visualConcept;
    if (!empty($opts['art_url'])) {
        $attrs['art_url'] = $opts['art_url'];
    }
    if (!empty($opts['back_variant'])) {
        $attrs['back_variant'] = $opts['back_variant'];
    }
    if (isset($opts['back_hue'])) {
        $attrs['back_hue'] = (int)$opts['back_hue'];
        $attrs['back_saturation'] = (int)($opts['back_saturation'] ?? 65);
        $attrs['back_lightness'] = (int)($opts['back_lightness'] ?? 40);
    }

    $stats = is_array($opts['stats'] ?? null) ? $opts['stats'] : [];
    $hp = isset($stats['hp']) ? (int)$stats['hp'] : (isset($opts['hp']) ? (int)$opts['hp'] : null);
    $npo = isset($stats['npo']) ? (int)$stats['npo'] : (isset($opts['npo']) ? (int)$opts['npo'] : null);
    $att = isset($stats['att']) ? (int)$stats['att'] : (isset($opts['att']) ? (int)$opts['att'] : null);
    $str = isset($stats['str']) ? (int)$stats['str'] : (isset($opts['str']) ? (int)$opts['str'] : null);
    $los = isset($stats['los']) ? (int)$stats['los'] : (isset($opts['los']) ? (int)$opts['los'] : null);
    $con = isset($stats['con']) ? (int)$stats['con'] : (isset($opts['con']) ? (int)$opts['con'] : null);
    if (!empty($stats)) {
        $attrs['stats'] = $stats;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO cardobot_cards
                (card_id, user_id, type, image_url, drawing_data, nickname, bio, power, ability,
                 hp, att, str, los, con, npo,
                 hue, saturation, lightness, attributes_json, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                image_url = VALUES(image_url),
                drawing_data = VALUES(drawing_data),
                nickname = VALUES(nickname),
                bio = VALUES(bio),
                power = VALUES(power),
                ability = VALUES(ability),
                hp = VALUES(hp),
                att = VALUES(att),
                str = VALUES(str),
                los = VALUES(los),
                con = VALUES(con),
                npo = VALUES(npo),
                hue = VALUES(hue),
                saturation = VALUES(saturation),
                lightness = VALUES(lightness),
                attributes_json = VALUES(attributes_json),
                modified_at = NOW()
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $type,
            $imageUrl,
            $drawing,
            $nickname,
            $bio,
            $power !== '' ? $power : null,
            $ability !== '' ? $ability : null,
            $hp,
            $att,
            $str,
            $los,
            $con,
            $npo,
            $hue,
            $sat,
            $light,
            json_encode($attrs),
        ]);

        return ['success' => true, 'card_id' => $sessionId, 'message' => 'Saved'];
    } catch (PDOException $e) {
        error_log('save_finished_card failed: ' . $e->getMessage());
        return ['success' => false, 'card_id' => null, 'message' => 'Failed to save card'];
    }
}
