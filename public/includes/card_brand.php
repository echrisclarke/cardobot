<?php
/**
 * Closed brand palettes for AI-picked card inks and initial background tint.
 * Name inks must read on the light name well; stat inks on the teal art plane.
 */

function cardobot_brand_name_inks(): array {
    return [
        'slate' => 'rgba(88,88,88,1)',
        'charcoal' => 'rgba(54,62,70,1)',
        'rose' => 'rgba(196,96,112,1)',
        'teal' => 'rgba(44,127,162,1)',
        'ink' => 'rgba(36,48,58,1)',
        'copper' => 'rgba(168,96,72,1)',
    ];
}

function cardobot_brand_stat_inks(): array {
    return [
        'white' => 'rgba(255,255,255,0.95)',
        'mint' => 'rgba(149,245,227,0.95)',
        'ice' => 'rgba(179,236,250,0.95)',
        'peach' => 'rgba(249,187,170,0.95)',
        'butter' => 'rgba(255,229,192,0.95)',
        'foam' => 'rgba(218,239,237,0.95)',
    ];
}

/**
 * Named initial face tints (users can still retune hue/sat/light).
 *
 * @return array<string, array{h:int,s:int,l:int}>
 */
function cardobot_brand_bg_presets(): array {
    return [
        'dock_teal' => ['h' => 195, 's' => 65, 'l' => 40],
        'rose_mist' => ['h' => 350, 's' => 42, 'l' => 42],
        'mint_hull' => ['h' => 162, 's' => 38, 'l' => 38],
        'night_steel' => ['h' => 210, 's' => 22, 'l' => 32],
        'warm_cargo' => ['h' => 28, 's' => 40, 'l' => 40],
        'deep_cyan' => ['h' => 188, 's' => 55, 'l' => 34],
    ];
}

function cardobot_resolve_name_ink(string $key): string {
    $map = cardobot_brand_name_inks();
    $k = strtolower(trim($key));
    return $map[$k] ?? $map['slate'];
}

function cardobot_resolve_stat_ink(string $key): string {
    $map = cardobot_brand_stat_inks();
    $k = strtolower(trim($key));
    return $map[$k] ?? $map['white'];
}

/**
 * @return array{h:int,s:int,l:int}
 */
function cardobot_resolve_bg_preset(string $key): array {
    $map = cardobot_brand_bg_presets();
    $k = strtolower(trim(str_replace('-', '_', $key)));
    return $map[$k] ?? $map['dock_teal'];
}

/**
 * Deterministic style pick from concept seed (fallback when model skips).
 *
 * @return array{name_ink:string,stats_ink:string,card_bg:string,card_hue:int,card_sat:int,card_light:int}
 */
function cardobot_fallback_card_style(array $concept): array {
    $seed = strtolower(trim(implode('|', [
        $concept['nickname'] ?? '',
        $concept['subject'] ?? '',
        $concept['type'] ?? '',
        $concept['vibe'] ?? '',
        $concept['details'] ?? '',
    ])));
    $h = unpack('N', substr(hash('sha256', $seed . ':style', true), 0, 4));
    $n = (int)($h[1] ?? 1);

    $nameKeys = array_keys(cardobot_brand_name_inks());
    $statKeys = array_keys(cardobot_brand_stat_inks());
    $bgKeys = array_keys(cardobot_brand_bg_presets());

    $nameKey = $nameKeys[$n % count($nameKeys)];
    $statKey = $statKeys[($n >> 3) % count($statKeys)];
    $bgKey = $bgKeys[($n >> 7) % count($bgKeys)];
    $bg = cardobot_resolve_bg_preset($bgKey);

    return [
        'name_ink' => $nameKey,
        'stats_ink' => $statKey,
        'card_bg' => $bgKey,
        'card_hue' => $bg['h'],
        'card_sat' => $bg['s'],
        'card_light' => $bg['l'],
    ];
}
