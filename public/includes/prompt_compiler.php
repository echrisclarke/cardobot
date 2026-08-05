<?php
/**
 * Build image prompts from player-authored visual_concept.
 */

/**
 * Locked Card-o-Bot house art language (medium + line + finish).
 * Personality and cuteness follow the subject; do not force mascot faces.
 */
function cardobot_house_art_style(): string {
    return 'STYLE NAME: Card-o-Bot Gouache (mandatory house look on every card). '
        . 'Opaque gouache / poster paint on warm tooth paper, matte finish, soft vignette, '
        . 'chunky brush tips with visible paint thickness and light handmade quirkiness. '
        . 'Finished collectible-card painting first: readable subject, clear silhouette, complete focal features. '
        . 'Face and expression must fit the type: a cute or friendly robot may have a clear cute face '
        . '(eyes, simple screen face, mouth). A clunky, ugly, industrial, or giant machine '
        . '(crane, hauler, wrecker, etc.) may use a more implied face: a slit, lamp, grille, sensor cluster, '
        . 'or other hardware that reads as presence without a cute mascot face. '
        . 'Do not force sparkle-eyed stock-mascot faces onto subjects that should feel heavy, crude, or inhuman. '
        . 'Light Card-o-Bot mischief only (not a rough sketch): occasional tiny registration wobble, '
        . 'slightly wonky proportion on one limb, a small drip or underdrawing peek. '
        . 'Full color freedom: any hues the scene needs; do not restrict to a fixed or limited palette. '
        . 'LINEWORK: outlines may shift color as they travel; a contour may break into graphite in places, '
        . 'but the piece should still read as finished, not abandoned. '
        . 'Subtle Card-o-Bot tells: light paper grain through the paint, hand wobble, thin teal or copper rim on some silhouettes. '
        . 'No photorealism, no glossy 3D render, no cinematic HDR, no lens blur.';
}

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

    $parts[] = cardobot_house_art_style();
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

    $parts[] = 'Strict 1:1 square FULL-BLEED image. Paint every pixel to the crop edges: no white margins, '
        . 'no letterboxing, no empty paper border, no vignette fade to blank. '
        . 'Background, environment, and subject may fill the frame: space scenes, docks, rooms, landscapes, '
        . 'and other settings are welcome when described. Unfinished linework is fine; bare empty margins are not.';
    $parts[] = 'CRITICAL: the image must contain absolutely no text of any kind. '
        . 'No titles, names, captions, labels, UI, logos, watermarks, letters, digits, or writing anywhere in the art. '
        . 'No card frame. Pure illustration only';

    return implode('. ', array_filter($parts, static fn($p) => trim((string)$p) !== ''));
}
