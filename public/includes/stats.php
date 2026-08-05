<?php
/**
 * Deterministic card stats (0–100), biased by kind and physical scale.
 * Massive ships outrank small bots/humans on STR/HP unless a weapon exception applies.
 */

function cardobot_hash_u32(string $seed): int {
    $h = hash('sha256', $seed, true);
    $n = unpack('N', substr($h, 0, 4));
    return (int)($n[1] ?? 0);
}

/**
 * @return array{0:int,1:int} min/max inclusive
 */
function cardobot_stat_band(string $kind, string $stat): array {
    $k = strtoupper(trim($kind));
    // Default balanced band
    $bands = [
        'hp' => [35, 90],
        'npo' => [20, 85],
        'att' => [20, 85],
        'str' => [20, 85],
        'los' => [15, 80],
        'con' => [20, 85],
    ];

    if ($k === 'BOT' || $k === 'ROBOT') {
        $bands = [
            'hp' => [40, 95],
            'npo' => [45, 100],
            'att' => [25, 80],
            'str' => [30, 90],
            'los' => [10, 60],
            'con' => [40, 100],
        ];
    } elseif ($k === 'ANDROID') {
        $bands = [
            'hp' => [45, 95],
            'npo' => [40, 95],
            'att' => [40, 100],
            'str' => [35, 95],
            'los' => [15, 70],
            'con' => [35, 90],
        ];
    } elseif ($k === 'CRITTER' || $k === 'CREATURE') {
        $bands = [
            'hp' => [20, 75],
            'npo' => [10, 70],
            'att' => [15, 80],
            'str' => [10, 65],
            'los' => [35, 100],
            'con' => [20, 85],
        ];
    } elseif ($k === 'HUMAN' || $k === 'PERSON') {
        $bands = [
            'hp' => [35, 90],
            'npo' => [25, 85],
            'att' => [25, 85],
            'str' => [25, 85],
            'los' => [20, 80],
            'con' => [25, 85],
        ];
    }

    $band = $bands[$stat] ?? [0, 100];
    $min = max(0, min(100, (int)$band[0]));
    $max = max(0, min(100, (int)$band[1]));
    if ($max < $min) {
        $max = $min;
    }
    return [$min, $max];
}

/**
 * Kind-biased height (m) / mass (kg) for the under-art strip.
 *
 * @return array{height:string,mass:string}
 */
function cardobot_generate_measures(array $concept, string $seed): array {
    $kind = strtoupper(trim((string)($concept['type'] ?? 'BOT')));
    $blob = strtolower(implode(' ', [
        $concept['nickname'] ?? '',
        $concept['subject'] ?? '',
        $concept['details'] ?? '',
        $concept['bio'] ?? '',
    ]));
    $shipLike = (bool)preg_match(
        '/\b(ship|spaceship|starship|freighter|vessel|cruiser|hauler|warship|battleship|destroyer|frigate|dreadnought|carrier)\b/',
        $blob
    );

    if ($shipLike) {
        $hh = cardobot_hash_u32($seed . ':height');
        $mh = cardobot_hash_u32($seed . ':mass');
        $height = 80 + (($hh % 1000) / 1000.0) * 420; // 80–500 m
        $massT = 800 + (($mh % 1000) / 1000.0) * 199200; // 800–200000 t
        return [
            'height' => (string)(int)round($height) . ' m',
            'mass' => (string)(int)round($massT) . ' t',
        ];
    }

    $bands = [
        'BOT' => ['h' => [0.8, 2.4], 'm' => [12, 180]],
        'ROBOT' => ['h' => [0.8, 2.4], 'm' => [12, 180]],
        'ANDROID' => ['h' => [1.4, 2.1], 'm' => [45, 120]],
        'HUMAN' => ['h' => [1.4, 2.0], 'm' => [40, 110]],
        'PERSON' => ['h' => [1.4, 2.0], 'm' => [40, 110]],
        'CRITTER' => ['h' => [0.2, 1.2], 'm' => [2, 45]],
        'CREATURE' => ['h' => [0.2, 1.2], 'm' => [2, 45]],
    ];
    $band = $bands[$kind] ?? ['h' => [0.6, 2.2], 'm' => [8, 140]];
    $hh = cardobot_hash_u32($seed . ':height');
    $mh = cardobot_hash_u32($seed . ':mass');
    $hMin = (float)$band['h'][0];
    $hMax = (float)$band['h'][1];
    $mMin = (float)$band['m'][0];
    $mMax = (float)$band['m'][1];
    $height = $hMin + (($hh % 1000) / 1000.0) * ($hMax - $hMin);
    $mass = $mMin + (($mh % 1000) / 1000.0) * ($mMax - $mMin);
    return [
        'height' => number_format($height, 1) . ' m',
        'mass' => (string)(int)round($mass) . ' kg',
    ];
}

/** @return float|null mass in kilograms */
function cardobot_parse_mass_kg(string $mass): ?float {
    $s = trim($mass);
    if ($s === '' || !preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(t|tonnes?|tons?|kg|g|lb|lbs)?\b/i', $s, $m)) {
        return null;
    }
    $n = (float)$m[1];
    $u = strtolower($m[2] ?? 'kg');
    if ($u === '' || $u === 'kg') {
        return $n;
    }
    if ($u === 'g') {
        return $n / 1000.0;
    }
    if ($u === 'lb' || $u === 'lbs') {
        return $n * 0.453592;
    }
    // tonnes
    return $n * 1000.0;
}

/** @return float|null height in metres */
function cardobot_parse_height_m(string $height): ?float {
    $s = trim($height);
    if ($s === '' || !preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(km|m|cm|mm|ft|in)?\b/i', $s, $m)) {
        return null;
    }
    $n = (float)$m[1];
    $u = strtolower($m[2] ?? 'm');
    if ($u === '' || $u === 'm') {
        return $n;
    }
    if ($u === 'km') {
        return $n * 1000.0;
    }
    if ($u === 'cm') {
        return $n / 100.0;
    }
    if ($u === 'mm') {
        return $n / 1000.0;
    }
    if ($u === 'ft') {
        return $n * 0.3048;
    }
    if ($u === 'in') {
        return $n * 0.0254;
    }
    return $n;
}

/**
 * Physical / role scale for combat stats.
 *
 * @return array{tier:string,scale:float,attack_role:bool,weapon_exception:bool,ship_like:bool}
 */
function cardobot_size_profile(array $concept, string $height, string $mass): array {
    $blob = strtolower(implode(' ', [
        $concept['nickname'] ?? '',
        $concept['subject'] ?? '',
        $concept['details'] ?? '',
        $concept['bio'] ?? '',
        $concept['power_name'] ?? '',
        $concept['ability_name'] ?? '',
        $concept['ability_line'] ?? '',
        $concept['type'] ?? '',
    ]));

    $shipLike = (bool)preg_match(
        '/\b(ship|spaceship|starship|freighter|vessel|cruiser|hauler|warship|battleship|destroyer|frigate|dreadnought|carrier|station)\b/',
        $blob
    );
    $attackRole = (bool)preg_match(
        '/\b(warship|battleship|destroyer|frigate|dreadnought|fighter|gunship|bomber|assault|attack|cannon|turret|missile|railgun|laser\s*battery|weapons?\s*platform)\b/',
        $blob
    );
    $weaponException = (bool)preg_match(
        '/\b(nuclear|nuke|atom\s*bomb|thermonuclear|planet[\s-]?killer|antimatter|doomsday|omega\s*weapon)\b/',
        $blob
    );

    $kg = cardobot_parse_mass_kg($mass);
    $metres = cardobot_parse_height_m($height);

    // Heuristic scale from mass (primary) and height (backup).
    $scale = 0.7;
    $tier = 'person';
    if ($kg !== null) {
        if ($kg >= 1000000) { // >= 1000 t
            $tier = 'capital';
            $scale = 1.35;
        } elseif ($kg >= 50000) { // >= 50 t
            $tier = 'vehicle';
            $scale = 1.18;
        } elseif ($kg >= 2000) { // >= 2 t
            $tier = 'heavy';
            $scale = 1.05;
        } elseif ($kg >= 200) {
            $tier = 'large';
            $scale = 0.92;
        } elseif ($kg >= 20) {
            $tier = 'person';
            $scale = 0.72;
        } else {
            $tier = 'micro';
            $scale = 0.55;
        }
    } elseif ($metres !== null) {
        if ($metres >= 80) {
            $tier = 'capital';
            $scale = 1.3;
        } elseif ($metres >= 12) {
            $tier = 'vehicle';
            $scale = 1.12;
        } elseif ($metres >= 3.5) {
            $tier = 'heavy';
            $scale = 1.0;
        } elseif ($metres >= 0.5) {
            $tier = 'person';
            $scale = 0.72;
        } else {
            $tier = 'micro';
            $scale = 0.55;
        }
    }

    if ($shipLike && $tier !== 'capital' && $tier !== 'vehicle') {
        $tier = 'vehicle';
        $scale = max($scale, 1.15);
    }
    if ($shipLike && (($kg !== null && $kg >= 100000) || ($metres !== null && $metres >= 50))) {
        $tier = 'capital';
        $scale = max($scale, 1.32);
    }

    return [
        'tier' => $tier,
        'scale' => $scale,
        'attack_role' => $attackRole,
        'weapon_exception' => $weaponException,
        'ship_like' => $shipLike,
    ];
}

/**
 * Rebalance rolled stats so physical scale and role read correctly on the face.
 *
 * @param array<string,int> $stats
 * @return array<string,int>
 */
function cardobot_apply_size_balance(array $stats, array $concept, string $height, string $mass): array {
    $p = cardobot_size_profile($concept, $height, $mass);
    $scale = (float)$p['scale'];
    $tier = (string)$p['tier'];
    $clamp = static function (int $v): int {
        return max(1, min(100, $v));
    };

    // Body / durability track mass. Mind (NPO/LOS) stays closer to the type roll.
    foreach (['str', 'hp', 'con'] as $key) {
        $stats[$key] = $clamp((int)round(((int)$stats[$key]) * $scale));
    }

    if ($p['attack_role'] || $tier === 'capital' || $tier === 'vehicle') {
        $attScale = $p['attack_role'] ? max($scale, 1.12) : ($scale * 0.92);
        $stats['att'] = $clamp((int)round(((int)$stats['att']) * $attScale));
    } elseif ($tier === 'person' || $tier === 'micro') {
        $stats['att'] = $clamp((int)round(((int)$stats['att']) * min($scale + 0.08, 0.85)));
    }

    // Floors for capital / vehicle hulls.
    if ($tier === 'capital') {
        $stats['str'] = max((int)$stats['str'], 78);
        $stats['hp'] = max((int)$stats['hp'], 72);
        $stats['con'] = max((int)$stats['con'], 68);
        if ($p['attack_role']) {
            $stats['att'] = max((int)$stats['att'], 70);
        } else {
            // Cargo / ferry hulls: strong, not necessarily sharp.
            $stats['att'] = $clamp(min((int)$stats['att'], 62));
            $stats['att'] = max((int)$stats['att'], 28);
        }
    } elseif ($tier === 'vehicle') {
        $stats['str'] = max((int)$stats['str'], 62);
        $stats['hp'] = max((int)$stats['hp'], 58);
        if ($p['attack_role']) {
            $stats['att'] = max((int)$stats['att'], 58);
        }
    }

    // Person-scale caps: a human/small bot cannot out-muscle a capital hull
    // unless they literally bring a nuke-class exception (ATT only).
    if ($tier === 'person' || $tier === 'micro') {
        $stats['str'] = min((int)$stats['str'], $tier === 'micro' ? 42 : 52);
        $stats['hp'] = min((int)$stats['hp'], 72);
        $stats['con'] = min((int)$stats['con'], 70);
        if ($p['weapon_exception']) {
            $stats['att'] = max((int)$stats['att'], 82);
            $stats['att'] = min((int)$stats['att'], 98);
        } else {
            $stats['att'] = min((int)$stats['att'], 58);
        }
    } elseif ($tier === 'large' || $tier === 'heavy') {
        if (!$p['weapon_exception']) {
            $stats['str'] = min((int)$stats['str'], $tier === 'heavy' ? 78 : 68);
        }
    }

    foreach (['hp', 'npo', 'att', 'str', 'los', 'con'] as $key) {
        $stats[$key] = $clamp((int)($stats[$key] ?? 1));
    }
    return $stats;
}

/**
 * @return array{hp:int,npo:int,att:int,str:int,los:int,con:int,height:string,mass:string}
 */
function cardobot_generate_stats(array $concept): array {
    $kind = (string)($concept['type'] ?? 'BOT');
    $seed = strtolower(trim(implode('|', [
        $concept['nickname'] ?? '',
        $concept['subject'] ?? '',
        $kind,
        $concept['vibe'] ?? '',
    ])));
    if ($seed === '' || preg_match('/^\|+/', $seed)) {
        $seed = 'unnamed|' . ($concept['subject'] ?? 'card');
    }

    $range = static function (int $h, int $min, int $max): int {
        if ($max <= $min) {
            return $min;
        }
        return $min + ($h % ($max - $min + 1));
    };

    $out = [];
    foreach (['hp', 'npo', 'att', 'str', 'los', 'con'] as $i => $stat) {
        [$min, $max] = cardobot_stat_band($kind, $stat);
        $h = cardobot_hash_u32($seed . ':' . $stat . ':' . $i);
        $out[$stat] = $range($h, $min, $max);
    }
    // Prefer model-authored measures on the concept; fall back to deterministic bands.
    $height = trim((string)($concept['height'] ?? ''));
    $mass = trim((string)($concept['mass'] ?? ''));
    if ($height === '' || $mass === '') {
        $measures = cardobot_generate_measures($concept, $seed);
        $height = $height !== '' ? $height : $measures['height'];
        $mass = $mass !== '' ? $mass : $measures['mass'];
    }
    $out = cardobot_apply_size_balance($out, $concept, $height, $mass);
    $out['height'] = $height;
    $out['mass'] = $mass;
    return $out;
}
