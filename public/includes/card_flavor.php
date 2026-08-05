<?php
/**
 * Ensure card flavor fields before print/confirm:
 * power/ability/height/mass, power value or rule note, brand inks, initial bg tint.
 */

require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/card_brand.php';

// Face-fit caps (must match card-layout.js LIMITS; never rely on ellipsis).
const CARDY_LIMIT_NICKNAME = 22;
const CARDY_LIMIT_POWER = 18;
const CARDY_LIMIT_ABILITY_NAME = 16;
const CARDY_LIMIT_ABILITY = 12;
const CARDY_LIMIT_BIO = 220;
const CARDY_LIMIT_POWER_VALUE = 10;

function cardy_clip(string $text, int $max): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($max <= 0 || mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $sp = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp >= (int)floor($max * 0.55)) {
        $cut = mb_substr($cut, 0, $sp);
    }
    $cut = rtrim($cut, " \t.,;:");
    // Avoid dangling glue words after a hard clip ("BOOSTS DEFENSE IN").
    $cut = preg_replace('/\b(in|on|at|to|for|with|when|and|or|of|the|a|an)\s*$/iu', '', $cut) ?? $cut;
    return rtrim($cut, " \t.,;:");
}

/**
 * Force compact card units: m / cm / kg / t (never "meters", "tonnes", etc).
 */
function cardy_normalize_measure(string $raw, string $kind = ''): string {
    $s = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    $s = preg_replace('/^(height|mass)\s*:\s*/iu', '', $s) ?? $s;
    $s = str_replace(',', '', $s);
    $replacements = [
        '/(\d+(?:\.\d+)?)\s*(kilometers|kilometres)\b/iu' => '$1 km',
        '/(\d+(?:\.\d+)?)\s*(meters|metres)\b/iu' => '$1 m',
        '/(\d+(?:\.\d+)?)\s*(centimeters|centimetres)\b/iu' => '$1 cm',
        '/(\d+(?:\.\d+)?)\s*(millimeters|millimetres)\b/iu' => '$1 mm',
        '/(\d+(?:\.\d+)?)\s*(inches)\b/iu' => '$1 in',
        '/(\d+(?:\.\d+)?)\s*(feet|foot)\b/iu' => '$1 ft',
        '/(\d+(?:\.\d+)?)\s*(tonnes|tons|ton)\b/iu' => '$1 t',
        '/(\d+(?:\.\d+)?)\s*(kilograms)\b/iu' => '$1 kg',
        '/(\d+(?:\.\d+)?)\s*(grams)\b/iu' => '$1 g',
        '/(\d+(?:\.\d+)?)\s*(pounds)\b/iu' => '$1 lb',
    ];
    foreach ($replacements as $re => $rep) {
        $s = preg_replace($re, $rep, $s) ?? $s;
    }
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    if ($s === '') {
        return $s;
    }
    $kind = strtolower($kind);
    if ($kind === 'height' && !preg_match('/\b(m|cm|mm|km|in|ft)\b/i', $s) && preg_match('/^\d/', $s)) {
        $s .= ' m';
    }
    if ($kind === 'mass' && !preg_match('/\b(kg|g|t|lb)\b/i', $s) && preg_match('/^\d/', $s)) {
        $s .= ' kg';
    }
    return $s;
}

/**
 * @return array{
 *   power_name:string,ability_name:string,ability_line:string,power_mode:string,power_value:string,
 *   height:string,mass:string,power_rule_hint:string
 * }
 */
function cardy_fallback_power_ability(array $concept): array {
    $who = trim((string)($concept['nickname'] ?? ''));
    if ($who === '') {
        $who = trim((string)($concept['subject'] ?? 'Sparks'));
    }
    $who = preg_replace('/\s+/', ' ', $who) ?? $who;
    $kind = strtolower(trim((string)($concept['type'] ?? 'bot')));
    $seed = strtolower($who . '|' . $kind . '|' . trim((string)($concept['details'] ?? '')));
    $h = unpack('N', substr(hash('sha256', $seed, true), 0, 4));
    $n = (int)($h[1] ?? 1);

    $statPool = ['ATT', 'STR', 'NPO', 'CON', 'LOS', 'HP'];
    $stat = $statPool[$n % count($statPool)];
    $bonus = 1 + ($n % 3);

    $powerPool = [
        'SPARK CACHE', 'BOLT HUM', 'SOFT OVERRIDE', 'DOCK LUCK', 'TEA STATIC',
        'NIGHT SHIFT', 'CARGO WHISPER', 'INK SURGE', 'QUIET CLOCK', 'HULL GIGGLE',
    ];
    $abilityNamePool = [
        'WARM SOLDER',
        'GALLEY STEAM',
        'MAP FOLDS',
        'PRINTER HUM',
        'BOLTS HOME',
    ];

    $power = $powerPool[$n % count($powerPool)];
    $isRule = (($n % 7) === 0);
    $abilityName = $abilityNamePool[$n % count($abilityNamePool)];
    $abilityLine = $isRule ? '2T SAFE' : ('+' . $bonus . ' ' . $stat);

    $measures = cardy_fallback_measures($concept, $seed);
    $ruleHints = [
        'Safe from the next hit for 1 turn.',
        'Rerolls one failed check once per match.',
        'Opponents cannot steal this card for 2 turns.',
        'Shares +1 NPO with an adjacent ally for 1 turn.',
    ];

    return [
        'power_name' => mb_strtoupper(cardy_clip($power, CARDY_LIMIT_POWER)),
        'ability_name' => mb_strtoupper(cardy_clip($abilityName, CARDY_LIMIT_ABILITY_NAME)),
        'ability_line' => mb_strtoupper(cardy_clip($abilityLine, CARDY_LIMIT_ABILITY)),
        'power_mode' => $isRule ? 'rule' : 'stat',
        'power_value' => $isRule ? 'RULE' : ('+' . $bonus . ' ' . $stat),
        'power_rule_hint' => $isRule ? $ruleHints[$n % count($ruleHints)] : '',
        'height' => $measures['height'],
        'mass' => $measures['mass'],
    ];
}

/**
 * @return array{height:string,mass:string}
 */
function cardy_fallback_measures(array $concept, string $seed = ''): array {
    if ($seed === '') {
        $seed = strtolower(trim(implode('|', [
            $concept['nickname'] ?? '',
            $concept['subject'] ?? '',
            $concept['type'] ?? '',
            $concept['details'] ?? '',
        ])));
    }
    $kind = strtoupper(trim((string)($concept['type'] ?? 'BOT')));
    $details = strtolower((string)($concept['details'] ?? '') . ' ' . (string)($concept['subject'] ?? ''));
    $h = unpack('N', substr(hash('sha256', $seed . ':m', true), 0, 4));
    $n = (int)($h[1] ?? 1);
    $t = ($n % 1000) / 1000.0;

    $giant = (bool)preg_match('/\b(giant|crane|colossal|towering|huge)\b/', $details);
    $tiny = (bool)preg_match('/\b(tiny|mini|pocket|mouse|drone)\b/', $details);
    $lightMat = (bool)preg_match('/\b(carbon|foam|plastic|hollow|kite)\b/', $details);
    $heavyMat = (bool)preg_match('/\b(steel|iron|tungsten|concrete|armor)\b/', $details);

    if ($kind === 'HUMAN' || $kind === 'PERSON') {
        $height = 1.5 + $t * 0.5;
        $mass = 45 + $t * 55;
    } elseif ($kind === 'CRITTER' || $kind === 'CREATURE') {
        $height = $tiny ? (0.1 + $t * 0.3) : (0.3 + $t * 0.9);
        $mass = $tiny ? (1 + $t * 8) : (3 + $t * 40);
    } elseif ($kind === 'ANDROID') {
        $height = $giant ? (2.2 + $t * 1.5) : (1.5 + $t * 0.6);
        $mass = $lightMat ? (25 + $t * 40) : (50 + $t * 70);
    } else {
        if ($giant) {
            $height = 4 + $t * 12;
            $mass = $lightMat ? (200 + $t * 800) : (2000 + $t * 18000);
        } elseif ($tiny) {
            $height = 0.15 + $t * 0.45;
            $mass = $heavyMat ? (8 + $t * 40) : (1 + $t * 12);
        } else {
            $height = 0.9 + $t * 1.4;
            $mass = $lightMat ? (15 + $t * 45) : (40 + $t * 160);
        }
    }

    return [
        'height' => number_format($height, $height >= 10 ? 0 : 1) . ' m',
        'mass' => ($mass >= 1000
            ? number_format($mass / 1000, 1) . ' t'
            : (string)(int)round($mass) . ' kg'),
    ];
}

function cardy_normalize_power_mode(string $mode): string {
    $m = strtolower(trim($mode));
    return ($m === 'rule' || $m === 'rules') ? 'rule' : 'stat';
}

/**
 * If power is a rules effect, make sure the bio carries a short usable note.
 */
function cardy_ensure_rule_in_bio(array $concept, string $hint): array {
    $bio = trim((string)($concept['bio'] ?? ''));
    $hint = cardy_clip(trim($hint), 90);
    if ($hint === '') {
        $hint = 'Bends a ship rule for a turn or two.';
    }
    if ($bio === '') {
        $concept['bio'] = cardy_clip($hint, CARDY_LIMIT_BIO);
        return $concept;
    }
    if (str_contains(strtolower($bio), 'turn') || str_contains(strtolower($bio), 'rule')
        || str_contains(strtolower($bio), strtolower(mb_substr($hint, 0, 12)))) {
        $concept['bio'] = cardy_clip($bio, CARDY_LIMIT_BIO);
        return $concept;
    }
    // Keep the rule note; trim the story body first so clipping cannot drop it.
    $budget = max(0, CARDY_LIMIT_BIO - mb_strlen($hint) - 2);
    $body = cardy_clip(rtrim($bio, ". \t\n\r\0\x0B"), $budget);
    $concept['bio'] = cardy_clip($body . '. ' . $hint, CARDY_LIMIT_BIO);
    return $concept;
}

/**
 * Fill missing power/ability/measures/style via a short AI call, with local fallback.
 */
function cardy_ensure_power_ability(array $concept): array {
    // Spaceships / vessels are Bot-class characters.
    $typeRaw = trim((string)($concept['type'] ?? ''));
    if ($typeRaw !== '' && preg_match('/\b(ship|spaceship|starship|freighter|vessel|cruiser|hauler)\b/i', $typeRaw)) {
        $concept['type'] = 'Bot';
    }

    $power = trim((string)($concept['power_name'] ?? ''));
    $abilityName = trim((string)($concept['ability_name'] ?? ''));
    $ability = trim((string)($concept['ability_line'] ?? ''));
    $height = trim((string)($concept['height'] ?? ''));
    $mass = trim((string)($concept['mass'] ?? ''));
    $powerMode = trim((string)($concept['power_mode'] ?? ''));
    $powerValue = trim((string)($concept['power_value'] ?? ''));
    $nameInk = trim((string)($concept['name_ink'] ?? ''));
    $statsInk = trim((string)($concept['stats_ink'] ?? ''));
    $cardBg = trim((string)($concept['card_bg'] ?? ''));

    $needModel = (
        $power === '' || $abilityName === '' || $ability === '' || $height === '' || $mass === ''
        || $powerMode === '' || $powerValue === ''
        || $nameInk === '' || $statsInk === '' || $cardBg === ''
    );

    $nameKeys = implode(', ', array_keys(cardobot_brand_name_inks()));
    $statKeys = implode(', ', array_keys(cardobot_brand_stat_inks()));
    $bgKeys = implode(', ', array_keys(cardobot_brand_bg_presets()));

    if ($needModel) {
        $who = trim((string)($concept['nickname'] ?? $concept['subject'] ?? 'someone'));
        $kind = trim((string)($concept['type'] ?? ''));
        $details = trim((string)($concept['details'] ?? $concept['vibe'] ?? ''));

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'power_name', 'ability_name', 'ability_line', 'power_mode', 'power_value', 'power_rule_hint',
                'height', 'mass', 'name_ink', 'stats_ink', 'card_bg',
            ],
            'properties' => [
                'power_name' => ['type' => 'string'],
                'ability_name' => ['type' => 'string'],
                'ability_line' => ['type' => 'string'],
                'power_mode' => ['type' => 'string'],
                'power_value' => ['type' => 'string'],
                'power_rule_hint' => ['type' => 'string'],
                'height' => ['type' => 'string'],
                'mass' => ['type' => 'string'],
                'name_ink' => ['type' => 'string'],
                'stats_ink' => ['type' => 'string'],
                'card_bg' => ['type' => 'string'],
            ],
        ];

        $prompt = "You invent trading-card fields for Card-o-Bot.\n"
            . "Subject: {$who}. Kind: {$kind}. Look/vibe: {$details}.\n"
            . "Return JSON only.\n"
            . "Kinds are Bot / Android / Human / Critter only. Intelligent spaceships are Bot.\n"
            . "power_name: inference-power TITLE, MAX 18 characters (hard). Prefer 2-3 short words.\n"
            . "ability_name: special-ability TITLE, MAX 16 characters (hard). Prefer 1-3 short words "
            . "(examples: \"HAUL GUARD\", \"BOLT HUM\", \"VENT HIDE\").\n"
            . "ability_line: short EFFECT for that ability, MAX 12 characters "
            . "(examples: \"+2 STR\", \"2T SAFE\", \"DRAW 1\").\n"
            . "power_mode: exactly \"stat\" or \"rule\".\n"
            . "If power_mode=stat: power_value is a usable numeric bump like \"+2 NPO\" or \"+3 ATT\" "
            . "(MAX 10 chars). power_rule_hint MUST be empty. "
            . "Do not narrate plain +stat / +HP bumps in any bio text.\n"
            . "If power_mode=rule: power_value is \"RULE\" or a short turn tag like \"2T\". "
            . "power_rule_hint is one short sentence for the bio ONLY for special rule effects "
            . "(shield for N turns, cannot be targeted, new ship rule). Keep family-friendly.\n"
            . "height / mass: physics must match body. Units MUST be abbreviated only: "
            . "m, cm, km, kg, g, t (examples: \"1.8 m\", \"68 kg\", \"400 m\", \"150000 t\"). "
            . "Never write meters, metres, kilograms, tonnes, tons, or other full unit words. No commas in numbers.\n"
            . "name_ink: one of [{$nameKeys}] (reads on light name well).\n"
            . "stats_ink: one of [{$statKeys}] (reads on teal art).\n"
            . "card_bg: one of [{$bgKeys}] (initial face tint; player can retune).\n"
            . "No em dashes. No birthdays.";

        $result = openai_chat_responses($prompt, [
            'schema' => $schema,
            'schema_name' => 'cardy_power_style',
            'max_output_tokens' => 220,
            'reasoning_effort' => 'minimal',
            'timeout' => 30,
            'retries' => 0,
        ]);

        if (!empty($result['ok']) && is_array($result['parsed'] ?? null)) {
            $p = $result['parsed'];
            if (trim((string)($p['power_name'] ?? '')) !== '') {
                $power = trim((string)$p['power_name']);
            }
            if (trim((string)($p['ability_name'] ?? '')) !== '') {
                $abilityName = trim((string)$p['ability_name']);
            }
            if (trim((string)($p['ability_line'] ?? '')) !== '') {
                $ability = trim((string)$p['ability_line']);
            }
            if (trim((string)($p['power_mode'] ?? '')) !== '') {
                $powerMode = trim((string)$p['power_mode']);
            }
            if (trim((string)($p['power_value'] ?? '')) !== '') {
                $powerValue = trim((string)$p['power_value']);
            }
            if (trim((string)($p['height'] ?? '')) !== '') {
                $height = trim((string)$p['height']);
            }
            if (trim((string)($p['mass'] ?? '')) !== '') {
                $mass = trim((string)$p['mass']);
            }
            if (trim((string)($p['name_ink'] ?? '')) !== '') {
                $nameInk = trim((string)$p['name_ink']);
            }
            if (trim((string)($p['stats_ink'] ?? '')) !== '') {
                $statsInk = trim((string)$p['stats_ink']);
            }
            if (trim((string)($p['card_bg'] ?? '')) !== '') {
                $cardBg = trim((string)$p['card_bg']);
            }
            if (trim((string)($p['power_rule_hint'] ?? '')) !== '') {
                $concept['power_rule_hint'] = trim((string)$p['power_rule_hint']);
            }
        }
    }

    $fb = cardy_fallback_power_ability($concept);
    $style = cardobot_fallback_card_style($concept);

    if ($power === '') {
        $power = $fb['power_name'];
    }
    if ($abilityName === '') {
        $abilityName = $fb['ability_name'];
    }
    if ($ability === '') {
        $ability = $fb['ability_line'];
    }
    if ($height === '') {
        $height = $fb['height'];
    }
    if ($mass === '') {
        $mass = $fb['mass'];
    }
    if ($powerMode === '') {
        $powerMode = $fb['power_mode'];
    }
    if ($powerValue === '') {
        $powerValue = $fb['power_value'];
    }
    if ($nameInk === '') {
        $nameInk = $style['name_ink'];
    }
    if ($statsInk === '') {
        $statsInk = $style['stats_ink'];
    }
    if ($cardBg === '') {
        $cardBg = $style['card_bg'];
    }

    $powerMode = cardy_normalize_power_mode($powerMode);
    $nameKeysList = array_keys(cardobot_brand_name_inks());
    $statKeysList = array_keys(cardobot_brand_stat_inks());
    $bgKeysList = array_keys(cardobot_brand_bg_presets());
    if (!in_array(strtolower($nameInk), $nameKeysList, true)) {
        $nameInk = $style['name_ink'];
    }
    if (!in_array(strtolower($statsInk), $statKeysList, true)) {
        $statsInk = $style['stats_ink'];
    }
    if (!in_array(strtolower(str_replace('-', '_', $cardBg)), $bgKeysList, true)) {
        $cardBg = $style['card_bg'];
    }

    $bg = cardobot_resolve_bg_preset($cardBg);

    $concept['power_name'] = mb_strtoupper(cardy_clip($power, CARDY_LIMIT_POWER));
    $concept['ability_name'] = mb_strtoupper(cardy_clip($abilityName, CARDY_LIMIT_ABILITY_NAME));
    $concept['ability_line'] = mb_strtoupper(cardy_clip($ability, CARDY_LIMIT_ABILITY));
    $concept['power_mode'] = $powerMode;
    $concept['power_value'] = mb_strtoupper(cardy_clip(
        $powerMode === 'rule' ? ($powerValue !== '' ? $powerValue : 'RULE') : $powerValue,
        CARDY_LIMIT_POWER_VALUE
    ));
    $concept['height'] = cardy_clip(cardy_normalize_measure($height, 'height'), 18);
    $concept['mass'] = cardy_clip(cardy_normalize_measure($mass, 'mass'), 18);
    $concept['name_ink'] = strtolower($nameInk);
    $concept['stats_ink'] = strtolower($statsInk);
    $concept['card_bg'] = strtolower(str_replace('-', '_', $cardBg));
    $concept['card_hue'] = $bg['h'];
    $concept['card_sat'] = $bg['s'];
    $concept['card_light'] = $bg['l'];
    $concept['name_color'] = cardobot_resolve_name_ink($concept['name_ink']);
    $concept['stats_color'] = cardobot_resolve_stat_ink($concept['stats_ink']);

    if (!empty($concept['nickname'])) {
        $concept['nickname'] = cardy_clip((string)$concept['nickname'], CARDY_LIMIT_NICKNAME);
    }
    if (!empty($concept['bio'])) {
        $concept['bio'] = cardy_clip((string)$concept['bio'], CARDY_LIMIT_BIO);
    }

    if ($powerMode === 'rule') {
        $hint = trim((string)($concept['power_rule_hint'] ?? ''));
        if ($hint === '') {
            $hint = $fb['power_rule_hint'];
        }
        // Ignore "hints" that are just restated stat bumps.
        if (preg_match('/^\s*\+?\s*\d+\s*(hp|npo|att|str|los|con)\b/i', $hint)) {
            $hint = '';
        }
        $concept['power_rule_hint'] = cardy_clip($hint, 90);
        if ($concept['power_rule_hint'] !== '') {
            $concept = cardy_ensure_rule_in_bio($concept, $concept['power_rule_hint']);
        }
    } else {
        $concept['power_rule_hint'] = '';
    }

    return $concept;
}
