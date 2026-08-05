<?php
/**
 * Style bake-off smoke: 3 house styles × 3 cards (distinct kinds/concepts).
 *
 * Usage:
 *   php run-style-smoke.php
 *   php run-style-smoke.php --style=a
 * Then open: card-style-smoke.html
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

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$onlyStyle = null;
if ($isCli) {
    foreach ($argv as $arg) {
        if (preg_match('/--style=([abc])/i', $arg, $m)) {
            $onlyStyle = strtolower($m[1]);
        }
    }
} else {
    $q = strtolower(trim((string)($_GET['style'] ?? '')));
    if (in_array($q, ['a', 'b', 'c'], true)) {
        $onlyStyle = $q;
    }
}

$styles = [
    'a' => [
        'key' => 'a',
        'name' => 'Dockyard Screenprint',
        'block' => 'HOUSE STYLE (mandatory, do not vary): mid-century dockyard screenprint. '
            . 'Flat color shapes in a limited ink set (about 3-5 colors), soft misregistration, '
            . 'visible paper grain, slight ink bleed at edges, bold contour lines, almost no gradients. '
            . 'Matte printed finish. Artist hand visible as print wobble and edge roughness, not oily oil-paint brushwork. '
            . 'No photorealism, no glossy 3D render, no cinematic HDR lighting, no lens blur.',
    ],
    'b' => [
        'key' => 'b',
        'name' => 'Risograph Workshop',
        'block' => 'HOUSE STYLE (mandatory, do not vary): modern risograph workshop print. '
            . 'Grainy halftone dots, overlapping translucent inks (lean teal, coral, cream), '
            . 'soft shadows as stipple, matte paper stock, no shine. '
            . 'Artist hand visible as ink grain and registration drift. '
            . 'No photorealism, no glossy 3D render, no cinematic HDR lighting, no lens blur.',
    ],
    'c' => [
        'key' => 'c',
        'name' => 'Card-o-Bot Gouache',
        'block' => cardobot_house_art_style(),
    ],
];

// Three totally different concepts per style (kinds differ across the bake-off).
$styleSeeds = [
    'a' => [
        'A dented Bot night-shift bolt sorter on a rainy cargo dock, humming off-key.',
        'A shy Human star-chart librarian who trades tea for broken compasses.',
        'A tiny Critter made of warm copper grit that nests inside printer vents.',
    ],
    'b' => [
        'A tall carbon-fiber Android crane operator with careful, gentle hands.',
        'An intelligent living spaceship Bot freighter with a scarred friendly hull (not an ocean boat).',
        'A magnetic Critter like a scrap ferret that rides conveyor belts for fun.',
    ],
    'c' => [
        'A massive intelligent living spaceship Bot in deep space, city-scale hull, scarred and odd, not an ocean boat, not a cute mascot face.',
        'A Human dock mechanic in a patched jumpsuit, warm and a bit awkward, holding a bent wrench.',
        'A strange little Critter of copper grit, wire whiskers, and one mismatched eye, nesting in a warm printer vent; quirky more than adorable.',
    ],
];

$outRoot = __DIR__ . '/smoke-out/styles';
if (!is_dir($outRoot) && !mkdir($outRoot, 0775, true) && !is_dir($outRoot)) {
    fwrite(STDERR, "Could not create smoke-out/styles\n");
    exit(1);
}

function smoke_log(string $line): void {
    echo $line . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
    @ob_flush();
    @flush();
}

function style_smoke_invent(string $seed): array {
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
        . "Match the kind implied by the spark (Bot / Android / Human / Critter).\n"
        . "bio: about them, aim 120-180 chars (max 220). Optional subtle ship hint. "
        . "Only mention powers in bio for special RULE effects, never plain +stat bumps.\n"
        . "power_name MAX 18; ability_name MAX 16 (title); ability_line MAX 12 (effect); power_value MAX 10.\n"
        . "power_mode: stat or rule. power_value: +2 NPO style or RULE/2T.\n"
        . "height/mass must match body. Units abbreviated ONLY: m, cm, kg, t "
        . "(e.g. \"1.8 m\", \"68 kg\", \"180000 t\"). Never write meters, tonnes, kilograms. No commas in numbers.\n"
        . "name_ink one of: slate, charcoal, rose, teal, ink, copper.\n"
        . "stats_ink one of: white, mint, ice, peach, butter, foam.\n"
        . "card_bg one of: dock_teal, rose_mist, mint_hull, night_steel, warm_cargo, deep_cyan.\n"
        . "No em dashes.";

    $result = openai_chat_responses($prompt, [
        'schema' => $schema,
        'schema_name' => 'card_style_smoke_concept',
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

function style_smoke_save_image(array $image, string $pathNoExt): ?string {
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

function style_smoke_prompt(array $concept, string $styleBlock): string {
    $prompt = build_render_prompt($concept);
    $house = cardobot_house_art_style();
    // Bake-off: swap the locked house block for the style under test.
    if ($styleBlock !== $house && str_contains($prompt, $house)) {
        return str_replace($house, $styleBlock, $prompt);
    }
    if (!str_contains($prompt, $styleBlock)) {
        $prompt .= '. ' . $styleBlock;
    }
    return $prompt;
}

$started = microtime(true);
$runStyles = $onlyStyle ? [$onlyStyle => $styles[$onlyStyle]] : $styles;
smoke_log('Style smoke: ' . implode(', ', array_map(static fn($s) => $s['name'], $runStyles)));

$groups = [];
foreach ($runStyles as $key => $style) {
    $dir = $outRoot . '/' . $key;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create ' . $dir);
    }

    smoke_log('');
    smoke_log('=== STYLE ' . strtoupper($key) . ': ' . $style['name'] . ' ===');
    $seeds = $styleSeeds[$key];
    $cards = [];

    foreach ($seeds as $i => $seed) {
        $n = $i + 1;
        smoke_log("{$n}/3 invent: {$seed}");
        try {
            $concept = style_smoke_invent($seed);
            $concept['creator_username'] = 'StyleLab';
            $concept = cardy_ensure_power_ability($concept);
            $stats = cardobot_generate_stats($concept);
            $prompt = style_smoke_prompt($concept, $style['block']);
            smoke_log('  -> ' . ($concept['nickname'] ?? '?') . ' / ' . ($concept['type'] ?? '?'));
            smoke_log('  paint…');
            $imgResult = generate_card_image($prompt, ['timeout' => 120]);
            $artFile = null;
            $provider = $imgResult['provider'] ?? null;
            if (!empty($imgResult['ok'])) {
                $slug = preg_replace('/[^a-z0-9]+/i', '-', (string)($concept['nickname'] ?? ('card' . $n)));
                $slug = trim($slug, '-') ?: ('card-' . $n);
                $artFile = style_smoke_save_image(
                    $imgResult['image'] ?? [],
                    $dir . '/' . $n . '-' . strtolower($slug)
                );
                smoke_log('  art: ' . ($artFile ?: 'no file') . ' via ' . ($provider ?: '?'));
            } else {
                smoke_log('  art FAILED: ' . ($imgResult['error'] ?? 'unknown'));
            }

            $cards[] = [
                'style_key' => $key,
                'style_name' => $style['name'],
                'seed' => $seed,
                'concept' => $concept,
                'stats' => $stats,
                'prompt' => $prompt,
                'art' => $artFile,
                'art_dir' => $key,
                'provider' => $provider,
                'ok_art' => !empty($imgResult['ok']),
                'error' => empty($imgResult['ok']) ? ($imgResult['error'] ?? 'paint failed') : null,
            ];
        } catch (Throwable $e) {
            smoke_log('  ERROR: ' . $e->getMessage());
            $cards[] = [
                'style_key' => $key,
                'style_name' => $style['name'],
                'seed' => $seed,
                'concept' => null,
                'stats' => null,
                'art' => null,
                'art_dir' => $key,
                'ok_art' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    $groups[$key] = [
        'key' => $key,
        'name' => $style['name'],
        'block' => $style['block'],
        'cards' => $cards,
    ];
}

$prev = [];
$prevPath = $outRoot . '/manifest.json';
if (is_file($prevPath)) {
    $decoded = json_decode((string)file_get_contents($prevPath), true);
    if (is_array($decoded['styles'] ?? null)) {
        $prev = $decoded['styles'];
    }
}
$merged = array_merge($prev, $groups);

$manifest = [
    'generated_at' => date('c'),
    'elapsed_sec' => round(microtime(true) - $started, 1),
    'styles' => $merged,
];
file_put_contents($prevPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
smoke_log('');
smoke_log('Wrote smoke-out/styles/manifest.json in ' . $manifest['elapsed_sec'] . 's');
smoke_log('Open card-style-smoke.html to compare framed cards.');
