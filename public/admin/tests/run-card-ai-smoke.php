<?php
/**
 * CLI / browser smoke: AI invents 3 card concepts, fills flavor, paints art, writes a manifest.
 *
 * Usage:
 *   php run-card-ai-smoke.php
 *   php run-card-ai-smoke.php --count=2
 * Then open: card-ai-smoke.html
 */

// Local monorepo: keys live in sibling private/.env (also discovered by env.php).
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

$isCli = (PHP_SAPI === 'cli');
$count = 3;
if ($isCli) {
    foreach ($argv as $arg) {
        if (preg_match('/--count=(\d+)/', $arg, $m)) {
            $count = max(1, min(5, (int)$m[1]));
        }
    }
} else {
    header('Content-Type: text/plain; charset=utf-8');
    $count = max(1, min(5, (int)($_GET['count'] ?? 3)));
}

$outDir = __DIR__ . '/smoke-out';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Could not create smoke-out\n");
    exit(1);
}

$seeds = [
    'A rusty dock bot who hums while sorting bolts on a night shift.',
    'A shy human map librarian who trades tea for star charts.',
    'A tiny copper critter that nests in warm printer vents.',
    'A tall carbon-fiber android crane operator with gentle hands.',
    'An old hull welder android with a lucky washer on a chain.',
];

function smoke_log(string $line): void {
    echo $line . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
    @ob_flush();
    @flush();
}

function smoke_invent_concept(string $seed): array {
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
        . "nickname: short callsign, 1-3 words, max 22 chars.\n"
        . "type: Bot, Android, Human, or Critter only. Intelligent spaceships are Bot.\n"
        . "bio: about them, aim 120-180 chars (max 220). Optional subtle ship hint. "
        . "Only mention powers in bio for special RULE effects, never plain +stat bumps.\n"
        . "power_name MAX 18; ability_name MAX 16 (title); ability_line MAX 12 (effect); power_value MAX 10.\n"
        . "power_mode: stat or rule. power_value: +2 NPO style or RULE/2T.\n"
        . "height/mass must match body. Units abbreviated ONLY: m, cm, kg, t "
        . "(e.g. \"1.8 m\", \"68 kg\"). Never write meters, tonnes, kilograms. No commas in numbers.\n"
        . "name_ink one of: slate, charcoal, rose, teal, ink, copper.\n"
        . "stats_ink one of: white, mint, ice, peach, butter, foam.\n"
        . "card_bg one of: dock_teal, rose_mist, mint_hull, night_steel, warm_cargo, deep_cyan.\n"
        . "No em dashes.";

    $result = openai_chat_responses($prompt, [
        'schema' => $schema,
        'schema_name' => 'card_ai_smoke_concept',
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

function smoke_save_image(array $image, string $pathNoExt): ?string {
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

$cards = [];
$started = microtime(true);
smoke_log('Card AI smoke: invent + paint x' . $count);

for ($i = 0; $i < $count; $i++) {
    $seed = $seeds[$i % count($seeds)];
    smoke_log(($i + 1) . "/{$count} invent: {$seed}");
    try {
        $concept = smoke_invent_concept($seed);
        $concept['creator_username'] = 'SmokeDeck';
        $concept = cardy_ensure_power_ability($concept);
        $stats = cardobot_generate_stats($concept);
        $prompt = build_render_prompt($concept);
        smoke_log('  -> ' . ($concept['nickname'] ?? '?') . ' / ' . ($concept['type'] ?? '?'));
        smoke_log('  paint…');
        $imgResult = generate_card_image($prompt, ['timeout' => 120]);
        $artFile = null;
        $provider = $imgResult['provider'] ?? null;
        if (!empty($imgResult['ok'])) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', (string)($concept['nickname'] ?? ('card' . $i)));
            $slug = trim($slug, '-') ?: ('card-' . $i);
            $artFile = smoke_save_image($imgResult['image'] ?? [], $outDir . '/' . ($i + 1) . '-' . strtolower($slug));
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
}

$manifest = [
    'generated_at' => date('c'),
    'elapsed_sec' => round(microtime(true) - $started, 1),
    'count' => count($cards),
    'cards' => $cards,
];
file_put_contents($outDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
smoke_log('Wrote smoke-out/manifest.json in ' . $manifest['elapsed_sec'] . 's');
smoke_log('Open card-ai-smoke.html to view framed cards.');
