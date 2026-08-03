<?php
/**
 * Build OpenAI image prompts from visual_concept.
 */

function build_render_prompt(array $concept, array $memoryHints = []): string {
    $subject = trim((string)($concept['subject'] ?? ''));
    $nickname = trim((string)($concept['nickname'] ?? ''));
    $vibe = trim((string)($concept['vibe'] ?? ''));
    $details = trim((string)($concept['details'] ?? ''));
    $setting = trim((string)($concept['setting'] ?? ''));
    $signature = trim((string)($concept['signature'] ?? ''));
    $extras = trim((string)($concept['image_prompt_extras'] ?? ''));
    $revision = trim((string)($concept['revision_notes'] ?? ''));
    $type = strtoupper(trim((string)($concept['type'] ?? 'BOT')));
    $palette = $concept['palette'] ?? [];
    if (is_array($palette)) {
        $palette = implode(', ', array_filter($palette, 'is_string'));
    } else {
        $palette = (string)$palette;
    }

    $parts = [];
    if ($revision !== '') {
        $parts[] = 'Revision of the same character: ' . $revision . '. Keep identity consistent';
    }
    if ($subject !== '') {
        $parts[] = ($nickname !== '' ? "{$nickname}, a {$subject}" : $subject);
    } elseif ($nickname !== '') {
        $parts[] = $nickname;
    }
    if ($type === 'CRITTER' || $type === 'BOT') {
        $parts[] = $type === 'CRITTER' ? 'alien critter creature' : 'friendly robot character';
    }
    if ($vibe !== '') {
        $parts[] = 'Mood: ' . $vibe;
    }
    if ($details !== '') {
        $parts[] = $details;
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

    $parts[] = 'Painterly, beautiful illustration in the style of cute Magic the Gathering card art and sci-fi novel cover art. Rich colors, atmospheric lighting, charming and approachable character design, family-friendly';
    $parts[] = 'Square 1:1 composition, the character fills the scene, no text, no logos, no card frame, no numbers, no watermark';

    return implode('. ', array_filter($parts, static fn($p) => trim((string)$p) !== ''));
}
