<?php
/**
 * Stress smoke: intelligent spaceship Bot with HP > 900.
 * Usage: php run-ship-hp-smoke.php
 * Then open: card-ai-smoke.html
 */

if (PHP_SAPI === 'cli' && getenv('ENV_PATH') === false) {
    $siblingEnv = dirname(__DIR__, 4) . '/private/.env';
    if (is_file($siblingEnv)) {
        putenv('ENV_PATH=' . $siblingEnv);
        $_ENV['ENV_PATH'] = $siblingEnv;
    }
}

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/openai.php';
require_once __DIR__ . '/../../includes/xai.php';
require_once __DIR__ . '/../../includes/card_flavor.php';
require_once __DIR__ . '/../../includes/stats.php';
require_once __DIR__ . '/../../includes/prompt_compiler.php';

$outDir = __DIR__ . '/smoke-out';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Could not create smoke-out\n");
    exit(1);
}

function smoke_log(string $line): void {
    echo $line . "\n";
    @ob_flush();
    @flush();
}

function ship_smoke_invent(string $seed): array {
    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => [
            'type', 'subject', 'nickname', 'vibe', 'details', 'setting', 'bio',
            'power_name', 'ability_name', 'ability_line', 'power_mode', 'power_value', 'power_rule_hint',
            'height', 'mass', 'name_ink', 'stats_ink', 'card_bg',
        ],
        'properties' => [
            'type' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'nickname' => ['type' => 'string'],
            'vibe' => ['type' => 'string'],
            'details' => ['type' => 'string'],
            'setting' => ['type' => 'string'],
            'bio' => ['type' => 'string'],
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

    $prompt = "Invent one Card-o-Bot trading-card concept from this spark:\n{$seed}\n"
        . "Return JSON only. Family-friendly ship sci-fi.\n"
        . "The subject is an INTELLIGENT LIVING SPACESHIP character (a conscious spacecraft), not a crew member.\n"
        . "type: MUST be Bot (spaceships are Bot-class). Never type Ship.\n"
        . "Do NOT invent an ocean freighter / wet-navy boat. This is a space vessel with personality.\n"
        . "nickname: short callsign, 1-3 words, max 22 chars.\n"
        . "bio: about the ship-mind, aim 120-180 chars (max 220).\n"
        . "power_name MAX 18 chars (title).\n"
        . "ability_name MAX 16 chars (special ability TITLE).\n"
        . "ability_line MAX 12 chars (short EFFECT like \"+2 CON\" or \"2T SAFE\").\n"
        . "power_mode: stat or rule. power_value: +2 NPO style or RULE/2T (MAX 10).\n"
        . "height/mass: city-scale spacecraft. Units abbreviated ONLY: e.g. \"420 m\", \"180000 t\". "
        . "Never write meters, tonnes, kilograms. No commas in numbers.\n"
        . "name_ink one of: slate, charcoal, rose, teal, ink, copper.\n"
        . "stats_ink one of: white, mint, ice, peach, butter, foam.\n"
        . "card_bg one of: dock_teal, rose_mist, mint_hull, night_steel, warm_cargo, deep_cyan.\n"
        . "No em dashes.";

    $result = openai_chat_responses($prompt, [
        'schema' => $schema,
        'schema_name' => 'card_ship_hp_smoke',
        'max_output_tokens' => 500,
        'reasoning_effort' => 'minimal',
        'timeout' => 60,
        'retries' => 1,
    ]);

    if (empty($result['ok']) || !is_array($result['parsed'] ?? null)) {
        throw new RuntimeException('Concept invent failed: ' . ($result['error'] ?? 'unknown'));
    }
    return $result['parsed'];
}

function ship_smoke_save_image(array $image, string $pathNoExt): ?string {
    if (!empty($image['b64_json'])) {
        $bin = base64_decode((string)$image['b64_json'], true);
        if ($bin !== false) {
            $file = $pathNoExt . '.png';
            file_put_contents($file, $bin);
            return basename($file);
        }
    }
    if (!empty($image['url'])) {
        $url = (string)$image['url'];
        $bin = @file_get_contents($url);
        if ($bin !== false && $bin !== '') {
            $ext = preg_match('/\.jpe?g(\?|$)/i', $url) ? 'jpg' : 'png';
            $file = $pathNoExt . '.' . $ext;
            file_put_contents($file, $bin);
            return basename($file);
        }
        return $url;
    }
    return null;
}

$seed = 'A colossal intelligent spaceship mind the size of a city block, scarred hull from decades of star-cargo runs, '
    . 'warm printer decks glowing inside. Conscious spacecraft character in orbit, not an ocean boat.';

smoke_log('Spaceship Bot stress smoke: invent + paint x1 (HP > 900)');
smoke_log('invent: ' . $seed);

$started = microtime(true);
$cards = [];
try {
    $concept = ship_smoke_invent($seed);
    $concept['type'] = 'Bot';
    $concept['subject'] = trim((string)($concept['subject'] ?? '')) ?: 'Intelligent living spaceship';
    $concept['height'] = trim((string)($concept['height'] ?? '')) ?: '420 m';
    $concept['mass'] = trim((string)($concept['mass'] ?? '')) ?: '180000 t';
    $concept['creator_username'] = 'SmokeDeck';
    $concept['show_credit'] = false;
    $concept = cardy_ensure_power_ability($concept);
    $concept['type'] = 'Bot';

    $stats = cardobot_generate_stats($concept);
    $stats['hp'] = 942;
    $stats['npo'] = max(80, (int)($stats['npo'] ?? 80));
    $stats['con'] = max(85, (int)($stats['con'] ?? 85));
    $stats['height'] = $concept['height'];
    $stats['mass'] = $concept['mass'];

    $prompt = build_render_prompt($concept);
    smoke_log('  -> ' . ($concept['nickname'] ?? '?') . ' / ' . ($concept['type'] ?? '?')
        . ' / HP ' . $stats['hp']
        . ' / ability ' . ($concept['ability_name'] ?? '') . ' ' . ($concept['ability_line'] ?? ''));
    smoke_log('  paint…');
    $imgResult = generate_card_image($prompt, ['timeout' => 120]);
    $artFile = null;
    $provider = $imgResult['provider'] ?? null;
    if (!empty($imgResult['ok'])) {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string)($concept['nickname'] ?? 'ship'));
        $slug = trim($slug, '-') ?: 'ship';
        $artFile = ship_smoke_save_image($imgResult['image'] ?? [], $outDir . '/1-' . strtolower($slug));
        smoke_log('  art: ' . ($artFile ?: 'no file') . ' via ' . ($provider ?: '?'));
    } else {
        smoke_log('  art FAILED: ' . ($imgResult['error'] ?? 'unknown'));
    }

    $cards[] = [
        'seed' => $seed,
        'concept' => $concept,
        'stats' => $stats,
        'prompt' => $prompt,
        'art' => $artFile,
        'provider' => $provider,
        'ok_art' => !empty($imgResult['ok']),
        'error' => empty($imgResult['ok']) ? ($imgResult['error'] ?? 'paint failed') : null,
    ];
} catch (Throwable $e) {
    smoke_log('  ERROR: ' . $e->getMessage());
    $cards[] = [
        'seed' => $seed,
        'concept' => null,
        'stats' => null,
        'art' => null,
        'ok_art' => false,
        'error' => $e->getMessage(),
    ];
}

$manifest = [
    'generated_at' => date('c'),
    'elapsed_sec' => round(microtime(true) - $started, 1),
    'count' => count($cards),
    'note' => 'spaceship-bot-stress: type Bot; HP forced 942; ability_name + ability_line; space vessel not boat',
    'cards' => $cards,
];
file_put_contents($outDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
smoke_log('Wrote smoke-out/manifest.json in ' . $manifest['elapsed_sec'] . 's');
smoke_log('Open card-ai-smoke.html to view framed card.');
