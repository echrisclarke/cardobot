<?php
/**
 * Deterministic card stats (0–100), biased by kind.
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
    $out['height'] = $height;
    $out['mass'] = $mass;
    return $out;
}
