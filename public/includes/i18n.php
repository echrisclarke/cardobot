<?php
/**
 * Permanent AI-cached i18n for Card-o-Bot UI + Cardy locale.
 */

require_once __DIR__ . '/i18n/catalog_en.php';
require_once __DIR__ . '/env.php';

const I18N_PRESET_LOCALES = [
    'en' => ['name_en' => 'English', 'name_native' => 'English'],
    'es' => ['name_en' => 'Spanish', 'name_native' => 'Español'],
    'zh-Hans' => ['name_en' => 'Mandarin Chinese (Simplified)', 'name_native' => '中文'],
    'fr' => ['name_en' => 'French', 'name_native' => 'Français'],
    'de' => ['name_en' => 'German', 'name_native' => 'Deutsch'],
    'ja' => ['name_en' => 'Japanese', 'name_native' => '日本語'],
    'pt-BR' => ['name_en' => 'Portuguese (Brazil)', 'name_native' => 'Português'],
    'ko' => ['name_en' => 'Korean', 'name_native' => '한국어'],
    'it' => ['name_en' => 'Italian', 'name_native' => 'Italiano'],
];

/** Known constructed / joke languages to reject without an LLM call. */
const I18N_REJECT_CODES = [
    'tlh', 'qya', 'sjn', 'art-x-elvish', 'art-x-klingon', 'art-x-dothraki',
    'art-x-navi', 'art-x-pirate', 'xx-pirate', 'x-pirate',
];

function i18n_pdo(): ?PDO {
    return get_db_connection();
}

function i18n_ensure_schema(?PDO $pdo = null): void {
    $pdo = $pdo ?: i18n_pdo();
    if (!$pdo) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cardobot_locales` (
            `code` VARCHAR(32) NOT NULL PRIMARY KEY,
            `name_en` VARCHAR(120) NOT NULL DEFAULT '',
            `name_native` VARCHAR(120) NOT NULL DEFAULT '',
            `status` ENUM('ready','building','rejected') NOT NULL DEFAULT 'building',
            `reject_reason` VARCHAR(255) NULL DEFAULT NULL,
            `catalog_version` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cardobot_ui_strings` (
            `locale` VARCHAR(32) NOT NULL,
            `string_key` VARCHAR(120) NOT NULL,
            `value` TEXT NOT NULL,
            `source` ENUM('seed','ai') NOT NULL DEFAULT 'ai',
            `catalog_version` INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`locale`, `string_key`),
            INDEX `idx_locale` (`locale`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $pdo->exec("ALTER TABLE `cardobot_users` ADD COLUMN `preferred_locale` VARCHAR(32) NULL DEFAULT NULL");
        } catch (Throwable $e) {
            // column may already exist
        }
        try {
            $pdo->exec("ALTER TABLE `cardobot_cards` ADD COLUMN `locale` VARCHAR(32) NULL DEFAULT NULL");
        } catch (Throwable $e) {
            // column may already exist
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('i18n_ensure_schema: ' . $e->getMessage());
    }
}

function i18n_normalize_code(string $raw): string {
    $code = trim(str_replace('_', '-', $raw));
    if ($code === '') {
        return '';
    }
    // zh-CN / zh-cn → zh-Hans; zh-TW → zh-Hant
    $lower = strtolower($code);
    if ($lower === 'zh' || $lower === 'zh-cn' || $lower === 'zh-sg' || $lower === 'cmn') {
        return 'zh-Hans';
    }
    if ($lower === 'zh-tw' || $lower === 'zh-hk' || $lower === 'zh-hant') {
        return 'zh-Hant';
    }
    if ($lower === 'pt' || $lower === 'pt-br') {
        return 'pt-BR';
    }
    if (preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $code)) {
        $parts = explode('-', $code);
        $parts[0] = strtolower($parts[0]);
        for ($i = 1; $i < count($parts); $i++) {
            $parts[$i] = strlen($parts[$i]) <= 3
                ? strtoupper($parts[$i])
                : (strlen($parts[$i]) === 4 ? ucfirst(strtolower($parts[$i])) : $parts[$i]);
        }
        return implode('-', $parts);
    }
    return $code;
}

function i18n_session_locale(?array $session = null): string {
    if (is_array($session) && !empty($session['locale'])) {
        return i18n_normalize_code((string)$session['locale']);
    }
    if (!empty($_SESSION['cardobot_locale'])) {
        return i18n_normalize_code((string)$_SESSION['cardobot_locale']);
    }
    return 'en';
}

function i18n_set_session_locale(string $code): void {
    $code = i18n_normalize_code($code);
    if ($code === '') {
        $code = 'en';
    }
    $_SESSION['cardobot_locale'] = $code;
}

function i18n_user_preferred_locale(int $userId): ?string {
    if ($userId <= 0) {
        return null;
    }
    $pdo = i18n_pdo();
    if (!$pdo) {
        return null;
    }
    i18n_ensure_schema($pdo);
    try {
        $stmt = $pdo->prepare('SELECT preferred_locale FROM cardobot_users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $loc = trim((string)($row['preferred_locale'] ?? ''));
        return $loc !== '' ? i18n_normalize_code($loc) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function i18n_save_user_locale(int $userId, string $code): void {
    if ($userId <= 0) {
        return;
    }
    $code = i18n_normalize_code($code);
    $pdo = i18n_pdo();
    if (!$pdo) {
        return;
    }
    i18n_ensure_schema($pdo);
    try {
        $stmt = $pdo->prepare('UPDATE cardobot_users SET preferred_locale = ? WHERE id = ?');
        $stmt->execute([$code, $userId]);
    } catch (Throwable $e) {
        error_log('i18n_save_user_locale: ' . $e->getMessage());
    }
}

function i18n_load_seed_pack(string $code): ?array {
    $code = i18n_normalize_code($code);
    $path = __DIR__ . '/i18n/packs/' . $code . '.php';
    if (!is_file($path)) {
        // also try filename as-is for zh-Hans etc.
        $alt = __DIR__ . '/i18n/packs/' . str_replace('/', '', $code) . '.php';
        $path = is_file($alt) ? $alt : $path;
    }
    if (!is_file($path)) {
        return null;
    }
    $pack = require $path;
    return is_array($pack) ? $pack : null;
}

function i18n_upsert_locale_row(PDO $pdo, string $code, string $nameEn, string $nameNative, string $status, int $catalogVersion, ?string $rejectReason = null): void {
    $stmt = $pdo->prepare(
        'INSERT INTO cardobot_locales (code, name_en, name_native, status, reject_reason, catalog_version)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_native = VALUES(name_native),
           status = VALUES(status), reject_reason = VALUES(reject_reason), catalog_version = VALUES(catalog_version)'
    );
    $stmt->execute([$code, $nameEn, $nameNative, $status, $rejectReason, $catalogVersion]);
}

function i18n_store_strings(PDO $pdo, string $code, array $strings, string $source, int $catalogVersion): void {
    $stmt = $pdo->prepare(
        'INSERT INTO cardobot_ui_strings (locale, string_key, value, source, catalog_version)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE value = IF(cardobot_ui_strings.source = \'seed\' AND VALUES(source) = \'ai\', cardobot_ui_strings.value, VALUES(value)),
           source = IF(cardobot_ui_strings.source = \'seed\', cardobot_ui_strings.source, VALUES(source)),
           catalog_version = GREATEST(cardobot_ui_strings.catalog_version, VALUES(catalog_version))'
    );
    foreach ($strings as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        $stmt->execute([$code, $key, $value, $source, $catalogVersion]);
    }
}

function i18n_seed_presets_if_needed(): void {
    static $doneVersion = null;
    if ($doneVersion === I18N_CATALOG_VERSION) {
        return;
    }
    $pdo = i18n_pdo();
    if (!$pdo) {
        return;
    }
    i18n_ensure_schema($pdo);
    $catalog = i18n_catalog_en();
    i18n_upsert_locale_row($pdo, 'en', 'English', 'English', 'ready', I18N_CATALOG_VERSION);
    i18n_store_strings($pdo, 'en', $catalog, 'seed', I18N_CATALOG_VERSION);

    foreach (I18N_PRESET_LOCALES as $code => $meta) {
        if ($code === 'en') {
            continue;
        }
        $seed = i18n_load_seed_pack($code);
        if (!$seed) {
            continue;
        }
        // Only fill missing keys from seed; never wipe AI fills for extra keys.
        $merged = array_merge($catalog, $seed);
        // Prefer seed translations over English for known keys.
        foreach ($seed as $k => $v) {
            $merged[$k] = $v;
        }
        i18n_upsert_locale_row($pdo, $code, $meta['name_en'], $meta['name_native'], 'ready', I18N_CATALOG_VERSION);
        i18n_store_strings($pdo, $code, $merged, 'seed', I18N_CATALOG_VERSION);
    }
    $doneVersion = I18N_CATALOG_VERSION;
}

function i18n_fetch_pack(string $code): array {
    $code = i18n_normalize_code($code) ?: 'en';
    $catalog = i18n_catalog_en();
    if ($code === 'en') {
        return [
            'code' => 'en',
            'name_en' => 'English',
            'name_native' => 'English',
            'status' => 'ready',
            'strings' => $catalog,
            'catalog_version' => I18N_CATALOG_VERSION,
        ];
    }

    $pdo = i18n_pdo();
    if (!$pdo) {
        $seed = i18n_load_seed_pack($code);
        return [
            'code' => $code,
            'name_en' => I18N_PRESET_LOCALES[$code]['name_en'] ?? $code,
            'name_native' => I18N_PRESET_LOCALES[$code]['name_native'] ?? $code,
            'status' => $seed ? 'ready' : 'missing',
            'strings' => $seed ? array_merge($catalog, $seed) : $catalog,
            'catalog_version' => I18N_CATALOG_VERSION,
        ];
    }

    i18n_ensure_schema($pdo);
    i18n_seed_presets_if_needed();

    $stmt = $pdo->prepare('SELECT * FROM cardobot_locales WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $locale = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare('SELECT string_key, value FROM cardobot_ui_strings WHERE locale = ?');
    $stmt->execute([$code]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $strings = $catalog;
    foreach ($rows as $row) {
        $strings[$row['string_key']] = $row['value'];
    }

    return [
        'code' => $code,
        'name_en' => (string)($locale['name_en'] ?? (I18N_PRESET_LOCALES[$code]['name_en'] ?? $code)),
        'name_native' => (string)($locale['name_native'] ?? (I18N_PRESET_LOCALES[$code]['name_native'] ?? $code)),
        'status' => (string)($locale['status'] ?? (count($rows) ? 'ready' : 'missing')),
        'strings' => $strings,
        'catalog_version' => (int)($locale['catalog_version'] ?? 0),
        'reject_reason' => $locale['reject_reason'] ?? null,
    ];
}

function i18n_t(string $key, ?string $locale = null, array $vars = []): string {
    $locale = $locale ?: i18n_session_locale();
    $pack = i18n_fetch_pack($locale);
    $text = $pack['strings'][$key] ?? (i18n_catalog_en()[$key] ?? $key);
    foreach ($vars as $k => $v) {
        $text = str_replace('{' . $k . '}', (string)$v, $text);
    }
    return $text;
}

function i18n_reject_heuristic(string $input): ?string {
    $low = mb_strtolower(trim($input));
    if ($low === '') {
        return 'empty';
    }
    $banned = [
        'klingon', 'tlhIngan', 'quenya', 'sindarin', 'elvish', 'elfish', 'dothraki',
        "na'vi", 'navi', 'high valyrian', 'valyrian', 'esperanto joke', 'pig latin',
        'pirate', 'lolcat', 'emoji', 'binary', 'morse', 'gibberish', 'asdf',
        'shakespearean', 'yoda',
    ];
    foreach ($banned as $b) {
        if (str_contains($low, mb_strtolower($b))) {
            return 'constructed_or_joke';
        }
    }
    $code = i18n_normalize_code($input);
    if (in_array(strtolower($code), array_map('strtolower', I18N_REJECT_CODES), true)) {
        return 'constructed_or_joke';
    }
    return null;
}

/**
 * Validate free-text language. Returns ok + code + names, or rejection.
 */
function i18n_validate_language(string $input): array {
    $input = trim($input);
    $heuristic = i18n_reject_heuristic($input);
    if ($heuristic === 'empty') {
        return ['ok' => false, 'reason' => 'empty', 'message' => 'Enter a language name.'];
    }
    if ($heuristic === 'constructed_or_joke') {
        return ['ok' => false, 'reason' => 'constructed_or_joke', 'message' => i18n_t('lang.rejected')];
    }

    // Direct preset / code hit.
    $asCode = i18n_normalize_code($input);
    if (isset(I18N_PRESET_LOCALES[$asCode])) {
        $meta = I18N_PRESET_LOCALES[$asCode];
        return [
            'ok' => true,
            'code' => $asCode,
            'name_en' => $meta['name_en'],
            'name_native' => $meta['name_native'],
        ];
    }
    foreach (I18N_PRESET_LOCALES as $code => $meta) {
        if (strcasecmp($input, $meta['name_en']) === 0
            || strcasecmp($input, $meta['name_native']) === 0) {
            return [
                'ok' => true,
                'code' => $code,
                'name_en' => $meta['name_en'],
                'name_native' => $meta['name_native'],
            ];
        }
    }

    require_once __DIR__ . '/openai.php';
    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'ok' => ['type' => 'boolean'],
            'code' => ['type' => 'string'],
            'name_en' => ['type' => 'string'],
            'name_native' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ],
        'required' => ['ok', 'code', 'name_en', 'name_native', 'reason'],
    ];
    $prompt = "You classify user language requests for a UI translation system.\n"
        . "Accept only real natural human languages that a modern LLM can write fluently.\n"
        . "Reject constructed/fictional languages (Klingon, Quenya, Sindarin, Na'vi, Dothraki, etc.),\n"
        . "joke registers (pirate, lolcat), ciphers, programming languages, and gibberish.\n"
        . "If accepted, return a short BCP-47-like code (e.g. fr, hi, ar, sw, cy).\n"
        . "User input: " . json_encode($input, JSON_UNESCAPED_UNICODE);

    $result = openai_chat_responses($prompt, [
        'schema' => $schema,
        'schema_name' => 'locale_validate',
        'max_output_tokens' => 200,
        'timeout' => 45,
    ]);
    if (!$result['ok'] || !is_array($result['parsed'] ?? null)) {
        return ['ok' => false, 'reason' => 'validate_failed', 'message' => i18n_t('error.generic')];
    }
    $p = $result['parsed'];
    if (empty($p['ok'])) {
        return [
            'ok' => false,
            'reason' => (string)($p['reason'] ?? 'rejected'),
            'message' => i18n_t('lang.rejected'),
        ];
    }
    $code = i18n_normalize_code((string)($p['code'] ?? ''));
    if ($code === '' || i18n_reject_heuristic($code) || i18n_reject_heuristic((string)($p['name_en'] ?? ''))) {
        return ['ok' => false, 'reason' => 'constructed_or_joke', 'message' => i18n_t('lang.rejected')];
    }
    return [
        'ok' => true,
        'code' => $code,
        'name_en' => (string)($p['name_en'] ?? $code),
        'name_native' => (string)($p['name_native'] ?? $code),
    ];
}

function i18n_missing_keys(string $code): array {
    $catalog = i18n_catalog_en();
    $pack = i18n_fetch_pack($code);
    $missing = [];
    foreach ($catalog as $key => $en) {
        if (!isset($pack['strings'][$key]) || $pack['strings'][$key] === '' || $pack['strings'][$key] === $en && $code !== 'en') {
            // If equal to English for a non-en locale and no DB row, treat as missing only when key absent from seed/DB.
            // Simpler: missing if key not in DB rows for this locale.
        }
    }
    $pdo = i18n_pdo();
    if (!$pdo) {
        $seed = i18n_load_seed_pack($code) ?: [];
        foreach ($catalog as $key => $_) {
            if (!array_key_exists($key, $seed)) {
                $missing[] = $key;
            }
        }
        return $missing;
    }
    i18n_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT string_key FROM cardobot_ui_strings WHERE locale = ?');
    $stmt->execute([$code]);
    $have = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $have[$row['string_key']] = true;
    }
    foreach ($catalog as $key => $_) {
        if (empty($have[$key])) {
            $missing[] = $key;
        }
    }
    return $missing;
}

function i18n_translate_keys(string $code, string $nameEn, array $keys): array {
    if (!$keys) {
        return [];
    }
    require_once __DIR__ . '/openai.php';
    $catalog = i18n_catalog_en();
    $slice = [];
    foreach ($keys as $key) {
        if (isset($catalog[$key])) {
            $slice[$key] = $catalog[$key];
        }
    }
    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'strings' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'string'],
            ],
        ],
        'required' => ['strings'],
    ];
    // json_schema strict objects need listed properties; send as array of pairs instead.
    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                    ],
                    'required' => ['key', 'value'],
                ],
            ],
        ],
        'required' => ['items'],
    ];
    $pairs = [];
    foreach ($slice as $k => $v) {
        $pairs[] = ['key' => $k, 'en' => $v];
    }
    $prompt = "Translate Card-o-Bot UI strings into {$nameEn} ({$code}).\n"
        . "Keep meaning; keep {placeholders} like {fields} unchanged.\n"
        . "Tone: playful console / retro sci-fi, concise.\n"
        . "Return every key.\n"
        . "Source JSON:\n" . json_encode($pairs, JSON_UNESCAPED_UNICODE);

    $result = openai_chat_responses($prompt, [
        'schema' => $schema,
        'schema_name' => 'ui_translate',
        'max_output_tokens' => 2500,
        'timeout' => 120,
    ]);
    $out = [];
    if ($result['ok'] && is_array($result['parsed']['items'] ?? null)) {
        foreach ($result['parsed']['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $k = (string)($item['key'] ?? '');
            $v = (string)($item['value'] ?? '');
            if ($k !== '' && $v !== '' && isset($slice[$k])) {
                $out[$k] = $v;
            }
        }
    }
    // Fallback: keep English for any missed keys so pack can go ready.
    foreach ($slice as $k => $v) {
        if (!isset($out[$k])) {
            $out[$k] = $v;
        }
    }
    return $out;
}

/**
 * Ensure locale pack is ready (seed or AI). Returns pack array.
 *
 * Preset locales with seed packs never block on OpenAI. Missing keys fall back
 * to English so Cardy boot / resume stays fast. AI fill is only for custom
 * "Other language" locales the visitor explicitly requested.
 */
function i18n_ensure_locale(string $code, string $nameEn = '', string $nameNative = '', bool $allowAiFill = true): array {
    $code = i18n_normalize_code($code) ?: 'en';
    if ($code === 'en') {
        return i18n_fetch_pack('en');
    }

    $pdo = i18n_pdo();
    i18n_seed_presets_if_needed();
    $pack = i18n_fetch_pack($code);
    if ($pack['status'] === 'rejected') {
        return $pack;
    }

    $missing = i18n_missing_keys($code);
    // Recover stuck "building" rows once keys are present.
    if (!$missing && ($pack['status'] === 'ready' || $pack['status'] === 'building')) {
        if ($pdo && $pack['status'] !== 'ready') {
            i18n_upsert_locale_row(
                $pdo,
                $code,
                (string)($pack['name_en'] ?: $code),
                (string)($pack['name_native'] ?: $code),
                'ready',
                I18N_CATALOG_VERSION
            );
            return i18n_fetch_pack($code);
        }
        return $pack;
    }

    $seed = i18n_load_seed_pack($code);
    $isPresetSeeded = $seed !== null && isset(I18N_PRESET_LOCALES[$code]);
    // Boot path must never wait on model translation for house languages.
    $useAi = $allowAiFill && !$isPresetSeeded;

    if (!$pdo) {
        if ($seed) {
            return i18n_fetch_pack($code);
        }
        $nameEn = $nameEn !== '' ? $nameEn : ($pack['name_en'] ?: $code);
        if ($useAi) {
            $translated = i18n_translate_keys($code, $nameEn, array_keys(i18n_catalog_en()));
            return [
                'code' => $code,
                'name_en' => $nameEn,
                'name_native' => $nameNative !== '' ? $nameNative : $code,
                'status' => 'ready',
                'strings' => array_merge(i18n_catalog_en(), $translated),
                'catalog_version' => I18N_CATALOG_VERSION,
                'ephemeral' => true,
            ];
        }
        return [
            'code' => $code,
            'name_en' => $nameEn,
            'name_native' => $nameNative !== '' ? $nameNative : $code,
            'status' => 'ready',
            'strings' => i18n_catalog_en(),
            'catalog_version' => I18N_CATALOG_VERSION,
            'ephemeral' => true,
        ];
    }

    i18n_ensure_schema($pdo);
    if ($nameEn === '') {
        $nameEn = $pack['name_en'] !== $code ? $pack['name_en'] : $code;
    }
    if ($nameNative === '') {
        $nameNative = $pack['name_native'] ?: $nameEn;
    }

    $catalog = i18n_catalog_en();
    if ($seed) {
        $merged = array_merge($catalog, $seed);
        foreach ($seed as $k => $v) {
            $merged[$k] = $v;
        }
        i18n_store_strings($pdo, $code, $merged, 'seed', I18N_CATALOG_VERSION);
    }

    $missing = i18n_missing_keys($code);
    if ($missing) {
        if ($useAi) {
            i18n_upsert_locale_row($pdo, $code, $nameEn, $nameNative, 'building', I18N_CATALOG_VERSION);
            $translated = i18n_translate_keys($code, $nameEn, $missing);
            i18n_store_strings($pdo, $code, $translated, 'ai', I18N_CATALOG_VERSION);
        } else {
            // Instant English fallback for any key still absent (keeps boot unblocked).
            $fill = [];
            foreach ($missing as $key) {
                if (isset($catalog[$key])) {
                    $fill[$key] = $catalog[$key];
                }
            }
            if ($fill) {
                i18n_store_strings($pdo, $code, $fill, 'seed', I18N_CATALOG_VERSION);
            }
        }
    }
    i18n_upsert_locale_row($pdo, $code, $nameEn, $nameNative, 'ready', I18N_CATALOG_VERSION);
    return i18n_fetch_pack($code);
}

function i18n_locale_display_name(string $code): string {
    $pack = i18n_fetch_pack($code);
    return $pack['name_en'] !== '' ? $pack['name_en'] : $code;
}

function i18n_locale_native_name(string $code): string {
    $code = i18n_normalize_code($code) ?: 'en';
    if (isset(I18N_PRESET_LOCALES[$code]['name_native'])) {
        return I18N_PRESET_LOCALES[$code]['name_native'];
    }
    $pack = i18n_fetch_pack($code);
    return $pack['name_native'] !== '' ? $pack['name_native'] : ($pack['name_en'] !== '' ? $pack['name_en'] : $code);
}

/**
 * Map a language tag (e.g. en-US, zh-CN) to a known/preset locale code.
 */
function i18n_match_language_tag(string $tag): ?string {
    $tag = trim($tag);
    if ($tag === '' || str_starts_with($tag, '*')) {
        return null;
    }
    // Strip q= weight if present.
    if (str_contains($tag, ';')) {
        $tag = trim(explode(';', $tag, 2)[0]);
    }
    $norm = i18n_normalize_code($tag);
    if ($norm === '') {
        return null;
    }
    if (isset(I18N_PRESET_LOCALES[$norm])) {
        return $norm;
    }
    // Primary subtag only (en from en-GB).
    $primary = strtolower(explode('-', $norm)[0]);
    if ($primary === 'zh') {
        return 'zh-Hans';
    }
    if ($primary === 'pt') {
        return 'pt-BR';
    }
    foreach (I18N_PRESET_LOCALES as $code => $_) {
        if (strtolower(explode('-', $code)[0]) === $primary) {
            return $code;
        }
    }
    // Non-preset but valid BCP-47 primary: allow if 2–3 letter language.
    if (preg_match('/^[a-z]{2,3}$/', $primary)) {
        return i18n_normalize_code($primary) ?: $primary;
    }
    return null;
}

function i18n_detect_from_accept_language(?string $header = null): ?string {
    if ($header === null) {
        $header = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    }
    $header = trim($header);
    if ($header === '') {
        return null;
    }
    $parts = array_map('trim', explode(',', $header));
    $ranked = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $q = 1.0;
        if (preg_match('/;q=([0-9.]+)/i', $part, $m)) {
            $q = (float)$m[1];
        }
        $tag = trim(explode(';', $part, 2)[0]);
        $ranked[] = ['tag' => $tag, 'q' => $q];
    }
    usort($ranked, static fn($a, $b) => $b['q'] <=> $a['q']);
    foreach ($ranked as $row) {
        $matched = i18n_match_language_tag($row['tag']);
        if ($matched !== null) {
            return $matched;
        }
    }
    return null;
}

/**
 * @param list<string>|mixed $languages
 */
function i18n_detect_from_navigator_languages($languages): ?string {
    if (!is_array($languages)) {
        return null;
    }
    foreach ($languages as $lang) {
        if (!is_string($lang)) {
            continue;
        }
        $matched = i18n_match_language_tag($lang);
        if ($matched !== null) {
            return $matched;
        }
    }
    return null;
}

/**
 * Predict locale: preferred_locale > Accept-Language > navigator.languages > en.
 *
 * @param list<string>|null $navigatorLanguages
 */
function i18n_predict_locale(int $userId, ?array $navigatorLanguages = null): string {
    $pref = $userId > 0 ? i18n_user_preferred_locale($userId) : null;
    if ($pref) {
        return $pref;
    }
    $fromHeader = i18n_detect_from_accept_language();
    if ($fromHeader) {
        return $fromHeader;
    }
    $fromNav = i18n_detect_from_navigator_languages($navigatorLanguages ?? []);
    if ($fromNav) {
        return $fromNav;
    }
    return 'en';
}

/**
 * Find a named UI language inside free text (longest name wins).
 */
function i18n_named_language_in_text(string $raw): ?string {
    $low = mb_strtolower(trim($raw));
    if ($low === '') {
        return null;
    }
    $chipMap = [
        'mandarin chinese' => 'zh-Hans',
        'simplified chinese' => 'zh-Hans',
        'mandarin' => 'zh-Hans',
        'chinese' => 'zh-Hans',
        'english' => 'en',
        'inglés' => 'en',
        'ingles' => 'en',
        'spanish' => 'es',
        'español' => 'es',
        'espanol' => 'es',
        'french' => 'fr',
        'français' => 'fr',
        'francais' => 'fr',
        'german' => 'de',
        'deutsch' => 'de',
        'japanese' => 'ja',
        'portuguese' => 'pt-BR',
        'português' => 'pt-BR',
        'portugues' => 'pt-BR',
        'korean' => 'ko',
        'italian' => 'it',
        'italiano' => 'it',
        '中文' => 'zh-Hans',
        '普通话' => 'zh-Hans',
        '英文' => 'en',
        '英语' => 'en',
        '西班牙语' => 'es',
        '法语' => 'fr',
        '德语' => 'de',
        '日语' => 'ja',
        '日本語' => 'ja',
        '한국어' => 'ko',
    ];
    $best = null;
    $bestLen = 0;
    foreach ($chipMap as $name => $code) {
        $nameLow = mb_strtolower($name);
        if (str_contains($low, $nameLow) || str_contains($raw, $name)) {
            $len = mb_strlen($name);
            if ($len > $bestLen) {
                $best = $code;
                $bestLen = $len;
            }
        }
    }
    return $best;
}

/**
 * Detect mid-chat "change language" intent.
 * Returns ['intent' => bool, 'target' => ?string] where target is a locale code if named.
 *
 * Requires a clear language-switch frame. Bare "switch to a bigger robot" must NOT match.
 */
function i18n_detect_change_language_intent(string $message): array {
    $raw = trim($message);
    if ($raw === '') {
        return ['intent' => false, 'target' => null];
    }
    $low = mb_strtolower($raw);
    $named = i18n_named_language_in_text($raw);

    // "back to english", "switch to Spanish", "change to 中文", "return to english"
    if ($named !== null && preg_match(
        '/\b(?:back|switch|change|return|go)\s+to\b/u',
        $low
    )) {
        return ['intent' => true, 'target' => $named];
    }

    // "speak english", "talk in spanish", "write in chinese", "use english"
    if ($named !== null && preg_match(
        '/\b(?:speak|talk|write|use|set)\s+(?:in\s+|the\s+language\s+)?/u',
        $low
    )) {
        return ['intent' => true, 'target' => $named];
    }

    // Short chips / typed lines that are only a language name (or "… please").
    if ($named !== null && preg_match(
        '/^(?:please\s+)?(?:english|inglés|ingles|spanish|español|espanol|chinese|mandarin|中文|普通话|英文|英语|french|français|francais|german|deutsch|japanese|日本語|portuguese|português|korean|한국어|italian|italiano)(?:\s+please)?[.!?…]*$/iu',
        trim($raw)
    )) {
        return ['intent' => true, 'target' => $named];
    }

    // Explicit "change language" / multilingual equivalents (may omit target).
    $barePhrases = [
        'change language', 'switch language', 'set language', 'another language',
        'hablar en', 'cambiar idioma', 'cambiar lengua',
        '换成', '换语言', '切换语言', '改回', '改成中文', '用中文', '用英语', '用英文', '用法语',
        'changer de langue', 'sprache wechseln',
        '言語を変', '言語変更', '언어 변경', 'mudar idioma', 'cambia lingua',
    ];
    foreach ($barePhrases as $p) {
        if (str_contains($low, $p) || str_contains($raw, $p)) {
            return ['intent' => true, 'target' => $named];
        }
    }

    if (preg_match('/\b(language|idioma|langue|sprache|语言|言語|언어)\b/u', $low)
        && preg_match('/\b(change|switch|set|cambiar|changer|wechseln|换成|切换|mudar|cambia|back)\b/u', $low)) {
        return ['intent' => true, 'target' => $named];
    }

    // "switch to fr" / "change to de" (code only, after a switch frame)
    if (preg_match('/\b(?:back|switch|change|return|go)\s+to\s+([a-z]{2,3}(?:-[A-Za-z0-9]+)?)\b/u', $low, $m)) {
        $matched = i18n_match_language_tag($m[1]);
        if ($matched) {
            return ['intent' => true, 'target' => $matched];
        }
    }

    return ['intent' => false, 'target' => null];
}
