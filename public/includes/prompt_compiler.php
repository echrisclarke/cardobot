<?php
/**
 * Build image prompts from player-authored visual_concept.
 */

function build_render_prompt(array $concept, array $memoryHints = []): string {
    $kind = trim((string)($concept['type'] ?? ''));
    $subject = trim((string)($concept['subject'] ?? ''));
    $nickname = trim((string)($concept['nickname'] ?? ''));
    $vibe = trim((string)($concept['vibe'] ?? ''));
    $details = trim((string)($concept['details'] ?? ''));
    $setting = trim((string)($concept['setting'] ?? ''));
    $stake = trim((string)($concept['stake'] ?? ''));
    $signature = trim((string)($concept['signature'] ?? ''));
    $extras = trim((string)($concept['image_prompt_extras'] ?? ''));
    $revision = trim((string)($concept['revision_notes'] ?? ''));
    $palette = $concept['palette'] ?? [];
    if (is_array($palette)) {
        $palette = implode(', ', array_filter($palette, 'is_string'));
    } else {
        $palette = (string)$palette;
    }

    $parts = [];
    if ($revision !== '') {
        $parts[] = 'Revision of the same subject: ' . $revision . '. Keep identity consistent';
    }
    if ($kind !== '') {
        $parts[] = 'They are a ' . $kind;
    }
    if ($subject !== '') {
        $parts[] = ($nickname !== '' ? "{$nickname}, {$subject}" : $subject);
    } elseif ($nickname !== '') {
        $parts[] = $nickname;
    }
    if ($vibe !== '') {
        $parts[] = 'Mood: ' . $vibe;
    }
    if ($details !== '') {
        $parts[] = $details;
    }
    if ($stake !== '') {
        $parts[] = 'Emotional note: ' . $stake;
    }
    if ($palette !== '') {
        $parts[] = 'Color palette: ' . $palette;
    }
    if ($setting !== '') {
        $parts[] = 'Setting: ' . $setting;
    }
    if ($signature !== '') {
        $parts[] = 'Signature detail: ' . $signature;
    }
    if ($extras !== '') {
        $parts[] = $extras;
    }
    if (!empty($memoryHints)) {
        $parts[] = 'Subtle style continuity: ' . implode('; ', array_slice($memoryHints, 0, 2));
    }

    $parts[] = 'Painterly, beautiful illustration in the style of charming sci-fi novel cover art and collectible card art. Rich colors, atmospheric lighting, approachable character design, family-friendly';
    $parts[] = 'Follow the description faithfully: person, robot, android, little ship critter, or stranger as written. No anthropomorphic animal hybrids, no furry costume aesthetic';

    $shipCue = strtolower($kind . ' ' . $subject . ' ' . $details . ' ' . $setting);
    $wantsBoat = (bool)preg_match('/\b(boat|naval|ocean|sea|yacht|ferry|water planet|wet world)\b/', $shipCue);
    $wantsShip = (bool)preg_match('/\b(ship|spaceship|starship|freighter|vessel|cruiser|hauler|hull)\b/', $shipCue);
    if ($wantsShip && !$wantsBoat) {
        $parts[] = 'Portray an intelligent living spaceship character in space or at a star dock: '
            . 'a conscious spacecraft with personality, not an Earth ocean freighter or wet-navy boat';
    } elseif ($wantsBoat) {
        $parts[] = 'Portray a boat-bot character on a water world if that is what they described';
    }

    $parts[] = 'Square 1:1 composition, the subject fills the scene';
    $parts[] = 'CRITICAL: the image must contain absolutely no text of any kind. '
        . 'No titles, names, captions, labels, UI, logos, watermarks, letters, digits, or writing anywhere in the art. '
        . 'No card frame. Pure illustration only';

    return implode('. ', array_filter($parts, static fn($p) => trim((string)$p) !== ''));
}
