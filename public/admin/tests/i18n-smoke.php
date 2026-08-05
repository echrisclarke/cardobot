<?php
/**
 * CLI / browser smoke for i18n cache + guardrails (no network translate for presets).
 * Usage: php public/admin/tests/i18n-smoke.php
 */
require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/i18n.php';

header('Content-Type: text/plain; charset=utf-8');

$fails = [];
$pass = [];

$en = i18n_fetch_pack('en');
if (($en['status'] ?? '') !== 'ready' || empty($en['strings']['path.fast'])) {
    $fails[] = 'en pack not ready';
} else {
    $pass[] = 'en pack ready';
}

$es = i18n_load_seed_pack('es');
if (!$es || ($es['path.fast'] ?? '') === '' || ($es['path.fast'] ?? '') === i18n_catalog_en()['path.fast']) {
    $fails[] = 'es seed missing or still English';
} else {
    $pass[] = 'es seed translated';
}

$zh = i18n_load_seed_pack('zh-Hans');
if (!$zh || empty($zh['lang.prompt'])) {
    $fails[] = 'zh-Hans seed missing';
} else {
    $pass[] = 'zh-Hans seed present';
}

$rej = i18n_reject_heuristic('Klingon');
if ($rej !== 'constructed_or_joke') {
    $fails[] = 'Klingon not rejected by heuristic';
} else {
    $pass[] = 'Klingon rejected';
}

$rej2 = i18n_reject_heuristic('Quenya elvish');
if ($rej2 !== 'constructed_or_joke') {
    $fails[] = 'Elvish not rejected';
} else {
    $pass[] = 'Elvish rejected';
}

$okFr = i18n_reject_heuristic('French');
if ($okFr !== null) {
    $fails[] = 'French incorrectly rejected by heuristic';
} else {
    $pass[] = 'French allowed by heuristic';
}

if (i18n_normalize_code('zh-CN') !== 'zh-Hans') {
    $fails[] = 'zh-CN normalize failed';
} else {
    $pass[] = 'zh-CN → zh-Hans';
}

foreach ($pass as $p) {
    echo "PASS $p\n";
}
foreach ($fails as $f) {
    echo "FAIL $f\n";
}
echo $fails ? ("\n" . count($fails) . " failed\n") : "\nAll i18n smokes passed\n";
exit($fails ? 1 : 0);
