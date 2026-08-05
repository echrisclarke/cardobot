<?php
/**
 * Locale pack API: get / set / validate / ensure translation cache.
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/state.php';

api_boot(false);
api_assert_same_origin();
$user = api_require_login();
ensure_user_row();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    $userId = (int)(ensure_user_row() ?: 0);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
i18n_seed_presets_if_needed();

if ($method === 'GET') {
    $code = isset($_GET['code']) ? i18n_normalize_code((string)$_GET['code']) : '';
    if ($code === '') {
        // Test account must not sticky-load a prior non-English preferred locale.
        if (function_exists('cardobot_is_test_user') && cardobot_is_test_user((string)($user['username'] ?? ''))) {
            $code = i18n_session_locale() ?: 'en';
        } else {
            $code = i18n_user_preferred_locale($userId) ?: i18n_session_locale();
        }
    }
    $pack = i18n_fetch_pack($code);
    $presets = [];
    foreach (I18N_PRESET_LOCALES as $pCode => $meta) {
        $presets[] = [
            'code' => $pCode,
            'name_en' => $meta['name_en'],
            'name_native' => $meta['name_native'],
        ];
    }
    api_json([
        'ok' => true,
        'locale' => $pack['code'],
        'name_en' => $pack['name_en'],
        'name_native' => $pack['name_native'],
        'status' => $pack['status'],
        'strings' => $pack['strings'],
        'catalog_version' => $pack['catalog_version'],
        'presets' => $presets,
    ]);
}

$data = api_require_post_json();
$action = isset($data['action']) && is_string($data['action']) ? $data['action'] : 'set';
$sessionId = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';

$session = null;
if ($sessionId !== '') {
    $session = cardy_session_find($sessionId);
}

switch ($action) {
    case 'validate':
        $raw = isset($data['language']) && is_string($data['language']) ? trim($data['language']) : '';
        $result = i18n_validate_language($raw);
        if (empty($result['ok'])) {
            api_json([
                'ok' => false,
                'reason' => $result['reason'] ?? 'rejected',
                'message' => $result['message'] ?? i18n_t('lang.rejected'),
            ]);
        }
        $pack = i18n_ensure_locale($result['code'], $result['name_en'], $result['name_native']);
        api_json([
            'ok' => true,
            'locale' => $pack['code'],
            'name_en' => $pack['name_en'],
            'name_native' => $pack['name_native'],
            'status' => $pack['status'],
            'strings' => $pack['strings'],
        ]);
        break;

    case 'set':
        $code = isset($data['code']) ? i18n_normalize_code((string)$data['code']) : '';
        $raw = isset($data['language']) && is_string($data['language']) ? trim($data['language']) : '';
        if ($code === '' && $raw !== '') {
            $result = i18n_validate_language($raw);
            if (empty($result['ok'])) {
                api_json([
                    'ok' => false,
                    'reason' => $result['reason'] ?? 'rejected',
                    'message' => $result['message'] ?? i18n_t('lang.rejected'),
                ]);
            }
            $code = $result['code'];
            $nameEn = $result['name_en'];
            $nameNative = $result['name_native'];
        } else {
            $nameEn = I18N_PRESET_LOCALES[$code]['name_en'] ?? $code;
            $nameNative = I18N_PRESET_LOCALES[$code]['name_native'] ?? $code;
        }
        if ($code === '') {
            api_error('bad_locale', 'Missing locale code', 400);
        }
        $pack = i18n_ensure_locale($code, $nameEn ?? '', $nameNative ?? '');
        if (($pack['status'] ?? '') === 'rejected') {
            api_json([
                'ok' => false,
                'reason' => 'rejected',
                'message' => i18n_t('lang.rejected'),
            ]);
        }
        i18n_set_session_locale($pack['code']);
        i18n_save_user_locale($userId, $pack['code']);
        // Update active chat session even if client omitted session_id (menu switch).
        if (!is_array($session) && $userId > 0) {
            $session = cardy_session_load_for_user($userId);
        }
        if (is_array($session)) {
            $session['locale'] = $pack['code'];
            $session['locale_picked'] = true;
            $session['awaiting_locale_confirm'] = false;
            $session['awaiting_language_switch'] = false;
            $session['awaiting_other_locale'] = false;
            cardy_session_save($session);
        }
        api_json([
            'ok' => true,
            'locale' => $pack['code'],
            'name_en' => $pack['name_en'],
            'name_native' => $pack['name_native'],
            'status' => $pack['status'],
            'strings' => $pack['strings'],
            'message' => i18n_t('lang.changed', $pack['code'], ['language' => i18n_locale_native_name($pack['code'])]),
            'path_suggestions' => [
                i18n_t('path.fast', $pack['code']),
                i18n_t('path.long', $pack['code']),
                i18n_t('path.form', $pack['code']),
                i18n_t('path.chat', $pack['code']),
                i18n_t('lang.change_chip', $pack['code']),
            ],
        ]);
        break;

    default:
        api_error('bad_action', 'Unknown action', 400);
}
