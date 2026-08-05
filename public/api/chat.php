<?php
/**
 * Card-o-Bot chat endpoint (agenda + lore tiers).
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/cardy.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/ml_client.php';
require_once __DIR__ . '/../includes/stats.php';
require_once __DIR__ . '/../includes/i18n.php';

api_boot(false);
api_assert_same_origin();
$user = api_require_login();
ensure_user_row();
$username = $user['username'] ?? '';
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    $userId = (int)(ensure_user_row() ?: 0);
}

$data = api_require_post_json();

$sessionId   = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
$userMessage = isset($data['user_message']) && is_string($data['user_message']) ? trim($data['user_message']) : '';
$action      = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';
$value       = isset($data['value']) && is_string($data['value']) ? $data['value'] : '';
$conceptPatch = isset($data['concept_patch']) && is_array($data['concept_patch']) ? $data['concept_patch'] : null;
$navigatorLanguages = isset($data['navigator_languages']) && is_array($data['navigator_languages'])
    ? $data['navigator_languages']
    : null;

if (mb_strlen($userMessage) > 1000) {
    $userMessage = mb_substr($userMessage, 0, 1000);
}

$resumed = false;
$refreshLocalePack = false;

if ($action === 'resume' && $sessionId === '') {
    $loaded = cardy_session_load_for_user($userId);
    if ($loaded !== null && cardy_session_is_resumable($loaded)) {
        $session = $loaded;
        $resumed = true;
    } else {
        $session = cardy_session_create();
    }
} elseif ($sessionId !== '') {
    $session = cardy_session_find($sessionId);
    if ($session === null) {
        api_error('unknown_session', 'Unknown session_id', 404);
    }
} else {
    $session = cardy_session_create();
}

$skipModel = false;
$staticMessage = null;
$staticSuggestions = [];
$autoRender = false;

i18n_seed_presets_if_needed();
$prefLocale = i18n_user_preferred_locale($userId);
if (empty($session['locale']) && $prefLocale) {
    $session['locale'] = $prefLocale;
    i18n_set_session_locale($prefLocale);
}
$loc = i18n_session_locale($session);
if (!empty($session['locale'])) {
    $loc = i18n_normalize_code((string)$session['locale']) ?: $loc;
    i18n_set_session_locale($loc);
}

$brandIntro = "Oh. A visitor. I'm Cardy. I run Card-o-Bot aboard this ship: we invent someone together, then I print them onto a trading card. Want to make one with me?";
$langPickerSuggestions = [
    i18n_t('lang.english', 'en'),
    i18n_t('lang.spanish', 'en'),
    i18n_t('lang.chinese', 'en'),
    i18n_t('lang.other', 'en'),
];
$greetingSuggestions = $langPickerSuggestions;

$cardy_apply_locale = static function (array &$session, string $code, int $userId, bool $changed = false) use (&$loc, &$refreshLocalePack): array {
    $nameEn = I18N_PRESET_LOCALES[$code]['name_en'] ?? $code;
    $nameNative = I18N_PRESET_LOCALES[$code]['name_native'] ?? $code;
    $pack = i18n_ensure_locale($code, $nameEn, $nameNative);
    $session['locale'] = $pack['code'];
    $session['locale_picked'] = true;
    $session['awaiting_other_locale'] = false;
    $session['awaiting_locale_confirm'] = false;
    i18n_set_session_locale($pack['code']);
    i18n_save_user_locale($userId, $pack['code']);
    $loc = $pack['code'];
    $refreshLocalePack = true;
    $langName = i18n_locale_native_name($pack['code']);
    $message = $changed
        ? i18n_t('lang.changed', $loc, ['language' => $langName])
        : i18n_t('lang.set', $loc);
    return [
        'message' => $message,
        'suggestions' => [
            i18n_t('path.fast', $loc),
            i18n_t('path.long', $loc),
            i18n_t('path.form', $loc),
            i18n_t('path.chat', $loc),
        ],
    ];
};

$cardy_soft_confirm_greeting = static function (array &$session, int $userId, ?array $navigatorLanguages) use ($brandIntro, &$loc): array {
    $candidate = i18n_predict_locale($userId, $navigatorLanguages);
    // Ensure pack exists so confirm copy can be localized.
    $nameEn = I18N_PRESET_LOCALES[$candidate]['name_en'] ?? $candidate;
    $nameNative = I18N_PRESET_LOCALES[$candidate]['name_native'] ?? $candidate;
    i18n_ensure_locale($candidate, $nameEn, $nameNative);
    $session['locale'] = $candidate;
    $session['locale_picked'] = false;
    $session['awaiting_locale_confirm'] = true;
    $session['awaiting_other_locale'] = false;
    $session['step'] = CARDY_STEP_GREETING;
    i18n_set_session_locale($candidate);
    $loc = $candidate;
    $langName = i18n_locale_native_name($candidate);
    return [
        'message' => $brandIntro . "\n\n" . i18n_t('lang.confirm', $candidate, ['language' => $langName]),
        'suggestions' => [
            i18n_t('lang.confirm_yes', $candidate),
            i18n_t('lang.confirm_other', $candidate),
        ],
    ];
};

$pathSuggestions = [
    i18n_t('path.fast', $loc),
    i18n_t('path.long', $loc),
    i18n_t('path.form', $loc),
    i18n_t('path.chat', $loc),
];

switch ($action) {
    case 'resume':
        if ($resumed) {
            $skipModel = true;
            $loc = i18n_normalize_code((string)($session['locale'] ?? $loc)) ?: $loc;
            i18n_set_session_locale($loc);
            $staticMessage = i18n_t('resume.welcome', $loc);
            $staticSuggestions = [
                i18n_t('resume.continue', $loc),
                i18n_t('resume.new_card', $loc),
            ];
            // Do not append welcome-back into history until we save below;
            // flag so client can restore prior history separately.
            $session['resume_offered'] = true;
            break;
        }
        // No resumable session: soft-confirm greeting.
        $greet = $cardy_soft_confirm_greeting($session, $userId, $navigatorLanguages);
        $skipModel = true;
        $staticMessage = $greet['message'];
        $staticSuggestions = $greet['suggestions'];
        $pathSuggestions = [
            i18n_t('path.fast', $loc),
            i18n_t('path.long', $loc),
            i18n_t('path.form', $loc),
            i18n_t('path.chat', $loc),
        ];
        break;

    case 'continue_resume':
        $skipModel = true;
        $session['resume_offered'] = false;
        $staticMessage = '';
        $staticSuggestions = [];
        $userMessage = '';
        break;

    case 'reset':
        $keepLocale = i18n_normalize_code((string)($session['locale'] ?? $prefLocale ?? 'en')) ?: 'en';
        $hadLocale = !empty($session['locale_picked']) || !empty($prefLocale);
        if ($userId > 0) {
            cardy_session_clear_for_user($userId);
        }
        $session = cardy_session_create();
        $skipModel = true;
        if ($hadLocale && $keepLocale !== '') {
            $applied = $cardy_apply_locale($session, $keepLocale, $userId, false);
            $staticMessage = $brandIntro . "\n\n" . $applied['message'];
            $staticSuggestions = $applied['suggestions'];
        } else {
            $greet = $cardy_soft_confirm_greeting($session, $userId, $navigatorLanguages);
            $staticMessage = $greet['message'];
            $staticSuggestions = $greet['suggestions'];
        }
        $userMessage = '';
        break;

    case 'select_locale':
        $code = $value !== '' ? i18n_normalize_code($value) : '';
        $rawLang = $userMessage !== '' ? $userMessage : $value;
        $lowRaw = mb_strtolower(trim((string)$rawLang));
        $lowVal = mb_strtolower(trim((string)$value));

        // Soft-confirm Yes: keep predicted/session locale.
        $yesLabels = [
            mb_strtolower(i18n_t('lang.confirm_yes', $loc)),
            mb_strtolower(i18n_t('lang.confirm_yes', 'en')),
            'yes', 'y', 'ok', 'okay', 'sure', 'sí', 'si', '好的', '好', 'oui', 'ja', 'はい', '네', 'sim', 'sì',
        ];
        $otherConfirmLabels = [
            mb_strtolower(i18n_t('lang.confirm_other', $loc)),
            mb_strtolower(i18n_t('lang.confirm_other', 'en')),
            'another language', 'another language…', 'another language...',
            'otro idioma', 'otro idioma…', '换一种语言', '换一种语言…',
        ];
        if (!empty($session['awaiting_locale_confirm'])) {
            if ($lowVal === 'confirm_yes' || in_array($lowRaw, $yesLabels, true) || $lowRaw === 'yes') {
                $keep = i18n_normalize_code((string)($session['locale'] ?? 'en')) ?: 'en';
                $applied = $cardy_apply_locale($session, $keep, $userId, false);
                $skipModel = true;
                $staticMessage = $applied['message'];
                $staticSuggestions = $applied['suggestions'];
                $userMessage = '';
                break;
            }
            if ($lowVal === 'confirm_other' || in_array($lowRaw, $otherConfirmLabels, true)
                || str_starts_with($lowRaw, 'another') || str_contains($lowRaw, 'otro idioma')
                || str_contains($lowRaw, '换一种语言')) {
                $session['awaiting_locale_confirm'] = false;
                $skipModel = true;
                $staticMessage = i18n_t('lang.prompt', $loc);
                $staticSuggestions = $langPickerSuggestions;
                $userMessage = '';
                break;
            }
        }

        if ($code === 'other' || $lowVal === 'other' || $lowRaw === mb_strtolower(i18n_t('lang.other', 'en'))
            || $lowRaw === 'other…' || $lowRaw === 'other...' || str_starts_with($lowRaw, 'other')
            || in_array($lowRaw, $otherConfirmLabels, true)) {
            $skipModel = true;
            $session['awaiting_locale_confirm'] = false;
            if ($code === 'other' || $lowVal === 'other' || str_starts_with($lowRaw, 'other')
                || $lowRaw === mb_strtolower(i18n_t('lang.other', 'en'))) {
                $staticMessage = i18n_t('lang.other_prompt', $loc);
                $staticSuggestions = [];
                $session['awaiting_other_locale'] = true;
            } else {
                $staticMessage = i18n_t('lang.prompt', $loc);
                $staticSuggestions = $langPickerSuggestions;
            }
            $userMessage = '';
            break;
        }
        if (!isset(I18N_PRESET_LOCALES[$code]) && $rawLang !== '') {
            // Map chip labels to codes.
            $chipMap = [
                mb_strtolower(i18n_t('lang.english', 'en')) => 'en',
                mb_strtolower(i18n_t('lang.spanish', 'en')) => 'es',
                mb_strtolower(i18n_t('lang.chinese', 'en')) => 'zh-Hans',
                'english' => 'en',
                'español' => 'es',
                'espanol' => 'es',
                '中文 (mandarin)' => 'zh-Hans',
                '中文' => 'zh-Hans',
            ];
            if (isset($chipMap[$lowRaw])) {
                $code = $chipMap[$lowRaw];
            } elseif (isset(I18N_PRESET_LOCALES[$code])) {
                // keep code
            } else {
                $validated = i18n_validate_language($rawLang);
                if (empty($validated['ok'])) {
                    $skipModel = true;
                    $staticMessage = $validated['message'] ?? i18n_t('lang.rejected', $loc);
                    $staticSuggestions = $langPickerSuggestions;
                    break;
                }
                $code = $validated['code'];
                $nameEn = $validated['name_en'];
                $nameNative = $validated['name_native'];
            }
        }
        if ($code === '') {
            $skipModel = true;
            $staticMessage = i18n_t('lang.prompt', $loc);
            $staticSuggestions = $langPickerSuggestions;
            break;
        }
        $nameEn = $nameEn ?? (I18N_PRESET_LOCALES[$code]['name_en'] ?? $code);
        $nameNative = $nameNative ?? (I18N_PRESET_LOCALES[$code]['name_native'] ?? $code);
        $pack = i18n_ensure_locale($code, $nameEn, $nameNative);
        $midSwitch = !empty($session['awaiting_language_switch'])
            || (!empty($session['locale_picked']) && $session['step'] !== CARDY_STEP_GREETING);
        $session['locale'] = $pack['code'];
        $session['locale_picked'] = true;
        $session['awaiting_other_locale'] = false;
        $session['awaiting_locale_confirm'] = false;
        $session['awaiting_language_switch'] = false;
        i18n_set_session_locale($pack['code']);
        i18n_save_user_locale($userId, $pack['code']);
        $loc = $pack['code'];
        $refreshLocalePack = true;
        $skipModel = true;
        if ($midSwitch) {
            $staticMessage = i18n_t('lang.changed', $loc, ['language' => i18n_locale_native_name($loc)]);
            $staticSuggestions = [];
        } else {
            $staticMessage = i18n_t('lang.set', $loc);
            $staticSuggestions = [
                i18n_t('path.fast', $loc),
                i18n_t('path.long', $loc),
                i18n_t('path.form', $loc),
                i18n_t('path.chat', $loc),
            ];
        }
        $userMessage = '';
        break;

    case 'select_path':
        $sel = cardy_select_path_value($value !== '' ? $value : 'fast');
        // Also accept localized path chip text.
        if ($value === '' && $userMessage !== '') {
            $um = mb_strtolower(trim($userMessage));
            foreach (['fast' => 'path.fast', 'long' => 'path.long', 'form' => 'path.form', 'chat' => 'path.chat'] as $pathKey => $i18nKey) {
                if ($um === mb_strtolower(i18n_t($i18nKey, $loc))
                    || $um === mb_strtolower(i18n_t($i18nKey, 'en'))) {
                    $sel = cardy_select_path_value($pathKey);
                    break;
                }
            }
        }
        $session['path'] = $sel['path'];
        $session['mode'] = $sel['mode'];
        $session['step'] = $sel['step'];
        if ($sel['path'] === CARDY_PATH_FORM) {
            // Form path: skip agenda chat; panel collects the card fields.
            if ($userMessage !== '') {
                $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            }
            $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            $session['step'] = CARDY_STEP_CONFIRM;
            $skipModel = true;
            $staticMessage = i18n_t('path.form_ack', $loc);
            $staticSuggestions = [];
            $userMessage = '';
            break;
        }
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            // Path chips / menu lines are never character answers
            if ($sel['path'] !== CARDY_PATH_CHAT && !cardy_is_path_intent_message($userMessage)) {
                $path = $sel['path'];
                $missing = cardy_missing_slots($session['visual_concept'], $path);
                $focus = $missing[0] ?? 'identity';
                $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
                if (cardy_slots_complete($session['visual_concept'], $path)) {
                    $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                    $session['step'] = CARDY_STEP_CONFIRM;
                    $skipModel = true;
                    $staticMessage = i18n_t('path.confirm_ack', $loc);
                    $staticSuggestions = [];
                    $userMessage = '';
                }
            } else {
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            }
            // Already in history; model reads history
            if (!$skipModel) {
                $userMessage = '';
            }
        }
        break;

    case 'advance':
        // Legacy: treat as agenda user message
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $path = cardy_session_path($session);
            if ($path === CARDY_PATH_CHAT) {
                $session['mode'] = CARDY_MODE_CARD;
                $session['path'] = CARDY_PATH_FAST;
                $path = CARDY_PATH_FAST;
                $session['step'] = CARDY_STEP_AGENDA;
            }
            $missing = cardy_missing_slots($session['visual_concept'], $path);
            $focus = $missing[0] ?? 'identity';
            $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
            $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            $session['step'] = CARDY_STEP_AGENDA;
            if (cardy_slots_complete($session['visual_concept'], $path)) {
                $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                $session['step'] = CARDY_STEP_CONFIRM;
                $skipModel = true;
                $staticMessage = 'Got it. Take a look, then we can paint your card. *whirr*';
                $staticSuggestions = [];
                $userMessage = '';
            }
        }
        break;

    case 'confirm':
        if (is_array($conceptPatch)) {
            $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $conceptPatch);
        }
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
        }
        // Paint needs a subject; form path may only have nickname.
        $nick = trim((string)($session['visual_concept']['nickname'] ?? ''));
        $subj = trim((string)($session['visual_concept']['subject'] ?? ''));
        if ($subj === '' && $nick !== '') {
            $session['visual_concept']['subject'] = $nick;
        }
        // Cardy invents a fresh printers-running line; then frontend starts paint.
        $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
        $session['step'] = CARDY_STEP_RENDERING;
        $session['mode'] = CARDY_MODE_CARD;
        if (empty($session['path']) || $session['path'] === CARDY_PATH_CHAT) {
            $session['path'] = CARDY_PATH_FAST;
        }
        $session['stats'] = cardobot_generate_stats($session['visual_concept']);
        $userMessage = '';
        $autoRender = true;
        break;

    case 'update_concept':
        if (is_array($conceptPatch)) {
            $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $conceptPatch);
        }
        $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
        $session['step'] = CARDY_STEP_CONFIRM;
        $skipModel = true;
        $staticMessage = 'Got it. Ready when you are.';
        $staticSuggestions = [];
        break;

    case 'revise':
        if (cardy_revise_remaining($session) <= 0) {
            $skipModel = true;
            $staticMessage = 'I can only tweak this one a couple more times. Want to save it, draw on it, or start a new card?';
            $staticSuggestions = ['View card', 'Draw on it', 'Start over'];
            break;
        }
        $session['revise_count'] = (int)($session['revise_count'] ?? 0) + 1;
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $session['visual_concept']['revision_notes'] = $userMessage;
        }
        $session['step'] = CARDY_STEP_REVISE;
        $session['image_url'] = null;
        $session['image_b64'] = null;
        $session['image_task_id'] = null;
        $userMessage = '';
        break;

    case 'enter_studio':
        $session['step'] = CARDY_STEP_STUDIO;
        $skipModel = true;
        $staticMessage = 'Ink deck online. Draw on the art, then save when it feels right.';
        $staticSuggestions = [];
        break;

    case 'greeting':
        $skipModel = true;
        $session['step'] = CARDY_STEP_GREETING;
        if (!empty($session['locale_picked'])) {
            $staticMessage = $brandIntro;
            $staticSuggestions = [
                i18n_t('path.fast', $loc),
                i18n_t('path.long', $loc),
                i18n_t('path.form', $loc),
                i18n_t('path.chat', $loc),
            ];
        } else {
            $greet = $cardy_soft_confirm_greeting($session, $userId, $navigatorLanguages);
            $staticMessage = $greet['message'];
            $staticSuggestions = $greet['suggestions'];
        }
        break;

    default:
        // Soft-confirm Yes / Another via free text at greeting.
        if ($userMessage !== '' && $session['step'] === CARDY_STEP_GREETING
            && !empty($session['awaiting_locale_confirm']) && empty($session['locale_picked'])) {
            $low = mb_strtolower(trim($userMessage));
            $yesLabels = [
                mb_strtolower(i18n_t('lang.confirm_yes', $loc)),
                mb_strtolower(i18n_t('lang.confirm_yes', 'en')),
                'yes', 'y', 'ok', 'okay', 'sure', 'sí', 'si', '好的', '好',
            ];
            $otherLabels = [
                mb_strtolower(i18n_t('lang.confirm_other', $loc)),
                mb_strtolower(i18n_t('lang.confirm_other', 'en')),
            ];
            if (in_array($low, $yesLabels, true)) {
                $keep = i18n_normalize_code((string)($session['locale'] ?? 'en')) ?: 'en';
                $applied = $cardy_apply_locale($session, $keep, $userId, false);
                $skipModel = true;
                $staticMessage = $applied['message'];
                $staticSuggestions = $applied['suggestions'];
                $userMessage = '';
                break;
            }
            if (in_array($low, $otherLabels, true) || str_starts_with($low, 'another')
                || str_contains($low, 'otro idioma') || str_contains($low, '换一种语言')) {
                $session['awaiting_locale_confirm'] = false;
                $skipModel = true;
                $staticMessage = i18n_t('lang.prompt', $loc);
                $staticSuggestions = $langPickerSuggestions;
                $userMessage = '';
                break;
            }
        }

        // Free-typed "Other" language while awaiting locale.
        if (!empty($session['awaiting_other_locale']) && $userMessage !== '') {
            $validated = i18n_validate_language($userMessage);
            if (empty($validated['ok'])) {
                $skipModel = true;
                $staticMessage = $validated['message'] ?? i18n_t('lang.rejected', $loc);
                $staticSuggestions = $langPickerSuggestions;
                $userMessage = '';
            } else {
                $pack = i18n_ensure_locale($validated['code'], $validated['name_en'], $validated['name_native']);
                $session['locale'] = $pack['code'];
                $session['locale_picked'] = true;
                $session['awaiting_other_locale'] = false;
                $session['awaiting_locale_confirm'] = false;
                i18n_set_session_locale($pack['code']);
                i18n_save_user_locale($userId, $pack['code']);
                $loc = $pack['code'];
                $refreshLocalePack = true;
                $skipModel = true;
                $staticMessage = i18n_t('lang.set', $loc);
                $staticSuggestions = [
                    i18n_t('path.fast', $loc),
                    i18n_t('path.long', $loc),
                    i18n_t('path.form', $loc),
                    i18n_t('path.chat', $loc),
                ];
                $userMessage = '';
            }
            break;
        }

        // Mid-chat language change (locale already picked).
        if ($userMessage !== '' && !empty($session['locale_picked'])
            && empty($session['awaiting_other_locale'])
            && $session['step'] !== CARDY_STEP_GREETING) {
            $change = i18n_detect_change_language_intent($userMessage);
            if (!empty($change['intent'])) {
                $session['history'][] = ['role' => 'user', 'content' => $userMessage];
                $skipModel = true;
                if (!empty($change['target'])) {
                    $code = i18n_normalize_code((string)$change['target']);
                    $nameEn = I18N_PRESET_LOCALES[$code]['name_en'] ?? $code;
                    $nameNative = I18N_PRESET_LOCALES[$code]['name_native'] ?? $code;
                    $pack = i18n_ensure_locale($code, $nameEn, $nameNative);
                    $session['locale'] = $pack['code'];
                    i18n_set_session_locale($pack['code']);
                    i18n_save_user_locale($userId, $pack['code']);
                    $loc = $pack['code'];
                    $refreshLocalePack = true;
                    $staticMessage = i18n_t('lang.changed', $loc, ['language' => i18n_locale_native_name($loc)]);
                    $staticSuggestions = [];
                } else {
                    $staticMessage = i18n_t('lang.change_prompt', $loc);
                    $staticSuggestions = $langPickerSuggestions;
                    $session['awaiting_other_locale'] = false;
                    $session['awaiting_locale_confirm'] = false;
                    // Temporarily accept select_locale mid-chat via next turn;
                    // mark with a soft flag so free text can still pick language.
                    $session['awaiting_language_switch'] = true;
                }
                $userMessage = '';
                break;
            }
        }

        // After "change language" with no target: treat next free text / chip as locale pick.
        if ($userMessage !== '' && !empty($session['awaiting_language_switch'])) {
            $validated = i18n_validate_language($userMessage);
            $skipModel = true;
            if (empty($validated['ok'])) {
                $staticMessage = $validated['message'] ?? i18n_t('lang.rejected', $loc);
                $staticSuggestions = $langPickerSuggestions;
                $userMessage = '';
            } else {
                $pack = i18n_ensure_locale($validated['code'], $validated['name_en'], $validated['name_native']);
                $session['locale'] = $pack['code'];
                $session['locale_picked'] = true;
                $session['awaiting_language_switch'] = false;
                $session['awaiting_other_locale'] = false;
                i18n_set_session_locale($pack['code']);
                i18n_save_user_locale($userId, $pack['code']);
                $loc = $pack['code'];
                $refreshLocalePack = true;
                $staticMessage = i18n_t('lang.changed', $loc, ['language' => i18n_locale_native_name($loc)]);
                $staticSuggestions = [];
                $userMessage = '';
            }
            break;
        }

        if ($userMessage !== '' && ($session['mode'] === CARDY_MODE_FREECHAT || cardy_session_path($session) === CARDY_PATH_CHAT)) {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $low = strtolower($userMessage);
            if (str_contains($low, 'print a card') || str_contains($low, "let's print")
                || str_contains($low, 'make a card') || str_contains($low, 'make a detailed')
                || str_contains($low, 'remember someone') || str_contains($low, 'yeah, make')) {
                $sel = cardy_select_path_value(
                    (str_contains($low, 'remember') || str_contains($low, 'detailed')) ? 'long' : 'fast'
                );
                $session['path'] = $sel['path'];
                $session['mode'] = $sel['mode'];
                $session['step'] = $sel['step'];
            }
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_GREETING && empty($session['locale_picked'])) {
            // Treat free text as a language pick while locale is unset.
            $validated = i18n_validate_language($userMessage);
            $skipModel = true;
            if (empty($validated['ok'])) {
                $staticMessage = $validated['message'] ?? i18n_t('lang.rejected', $loc);
                $staticSuggestions = $langPickerSuggestions;
                $userMessage = '';
            } else {
                $pack = i18n_ensure_locale($validated['code'], $validated['name_en'], $validated['name_native']);
                $session['locale'] = $pack['code'];
                $session['locale_picked'] = true;
                $session['awaiting_locale_confirm'] = false;
                i18n_set_session_locale($pack['code']);
                i18n_save_user_locale($userId, $pack['code']);
                $loc = $pack['code'];
                $refreshLocalePack = true;
                $staticMessage = i18n_t('lang.set', $loc);
                $staticSuggestions = [
                    i18n_t('path.fast', $loc),
                    i18n_t('path.long', $loc),
                    i18n_t('path.form', $loc),
                    i18n_t('path.chat', $loc),
                ];
                $userMessage = '';
            }
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_GREETING) {
            $low = strtolower($userMessage);
            if (str_contains($low, 'talk') || str_contains($low, 'chat') || str_contains($low, 'tell me more')) {
                $sel = cardy_select_path_value('chat');
            } elseif (str_contains($low, 'remember')) {
                $sel = cardy_select_path_value('long');
            } else {
                $sel = cardy_select_path_value('fast');
            }
            $session['path'] = $sel['path'];
            $session['mode'] = $sel['mode'];
            $session['step'] = $sel['step'];
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            if ($sel['path'] !== CARDY_PATH_CHAT && !cardy_is_path_intent_message($userMessage) && mb_strlen($userMessage) > 12) {
                $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, 'identity');
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
                if (cardy_slots_complete($session['visual_concept'], $sel['path'])) {
                    $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                    $session['step'] = CARDY_STEP_CONFIRM;
                    $skipModel = true;
                    $staticMessage = i18n_t('path.confirm_ack', $loc);
                    $staticSuggestions = [];
                    $userMessage = '';
                }
            } else {
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            }
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_CONFIRM) {
            // Tweaks only; never auto-paint from free text
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            if (cardy_looks_like_nickname($userMessage)) {
                $session['visual_concept']['nickname'] = trim($userMessage);
                $staticMessage = 'Name locked in. Tweak anything else, then paint.';
            } else {
                $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, 'look');
                $staticMessage = 'Updated. Check the plate when you are ready.';
            }
            $skipModel = true;
            $staticSuggestions = [];
        } elseif ($userMessage !== '' && in_array($session['step'], [
            CARDY_STEP_AGENDA, CARDY_STEP_Q_WHO, CARDY_STEP_Q_FLAVOR, CARDY_STEP_CHOOSE_INTENT,
            CARDY_STEP_Q_SUBJECT, CARDY_STEP_Q_VIBE, CARDY_STEP_Q_LOOKS,
            CARDY_STEP_Q_WORLD, CARDY_STEP_Q_SPARK,
        ], true)) {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $path = cardy_session_path($session);
            if ($path === CARDY_PATH_CHAT) {
                $session['path'] = CARDY_PATH_FAST;
                $session['mode'] = CARDY_MODE_CARD;
                $path = CARDY_PATH_FAST;
            }
            $missing = cardy_missing_slots($session['visual_concept'], $path);
            $focus = $missing[0] ?? 'identity';
            $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
            $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            $session['step'] = CARDY_STEP_AGENDA;
            if (cardy_slots_complete($session['visual_concept'], $path)) {
                $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                $session['step'] = CARDY_STEP_CONFIRM;
                $skipModel = true;
                $staticMessage = 'Got it. Take a look, then we can paint your card. *whirr*';
                $staticSuggestions = [];
                $userMessage = '';
            }
        }
        break;
}

if (!$skipModel && $session['step'] === CARDY_STEP_GREETING && $userMessage === '' && $action === '') {
    $greet = $cardy_soft_confirm_greeting($session, $userId, $navigatorLanguages);
    $skipModel = true;
    $staticMessage = $greet['message'];
    $staticSuggestions = $greet['suggestions'];
}

$memoryHints = [];
$conceptText = trim(($session['visual_concept']['subject'] ?? '') . ' ' . ($session['visual_concept']['details'] ?? ''));
if ($conceptText !== '' && $userId > 0) {
    $memoryHints = ml_similar_hints($userId, $conceptText, 3);
}

$path = cardy_session_path($session);
$injectLore = cardy_should_inject_lore($path, $userMessage, $session['mode'] ?? null);
$loreBlock = '';
if ($injectLore) {
    $loreBlock = "\n\n" . cardy_lore_packet();
    if (file_exists(__DIR__ . '/../includes/story.php')) {
        require_once __DIR__ . '/../includes/story.php';
        if (function_exists('get_master_story')) {
            $master = get_master_story();
            $summary = trim((string)($master['summary'] ?? ''));
            if ($summary !== '') {
                $loreBlock .= "\nLiving memory summary (short; do not dump): " . mb_substr($summary, 0, 400);
            }
            if (function_exists('get_recent_chapters')) {
                $chapters = get_recent_chapters(2);
                if (!empty($chapters)) {
                    $bits = [];
                    foreach ($chapters as $ch) {
                        $t = trim((string)($ch['chapter_title'] ?? ''));
                        $x = trim((string)($ch['chapter_text'] ?? ''));
                        if ($t !== '' || $x !== '') {
                            $bits[] = ($t !== '' ? $t . ': ' : '') . mb_substr($x, 0, 160);
                        }
                    }
                    if ($bits !== []) {
                        $loreBlock .= "\nRecent fragments: " . implode(' | ', $bits);
                    }
                }
            }
        }
    }
}

if ($skipModel) {
    // Resume welcome-back and continue_resume should not pollute history.
    $skipHistoryAppend = ($action === 'resume' && $resumed)
        || $action === 'continue_resume'
        || ($staticMessage === null || $staticMessage === '');
    if (!$skipHistoryAppend && $staticMessage !== null) {
        $session['history'][] = ['role' => 'assistant', 'content' => $staticMessage];
        if (count($session['history']) > 16) {
            $session['history'] = array_slice($session['history'], -16);
        }
    }
    if ($session['step'] === CARDY_STEP_CONFIRM && empty($session['stats'])) {
        $session['stats'] = cardobot_generate_stats($session['visual_concept']);
    }
    cardy_session_save($session);
    $nickSuggestions = ($session['step'] === CARDY_STEP_CONFIRM)
        ? cardy_nickname_suggestions($session['visual_concept'] ?? [])
        : [];
    api_json([
        'ok' => true,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'path' => $session['path'] ?? null,
        'locale' => $session['locale'] ?? $loc,
        'locale_picked' => !empty($session['locale_picked']),
        'awaiting_other_locale' => !empty($session['awaiting_other_locale']),
        'awaiting_locale_confirm' => !empty($session['awaiting_locale_confirm']),
        'awaiting_language_switch' => !empty($session['awaiting_language_switch']),
        'refresh_locale_pack' => $refreshLocalePack,
        'resumed' => $resumed,
        'history' => $resumed ? ($session['history'] ?? []) : null,
        'message' => $staticMessage ?? '',
        'suggestions' => $staticSuggestions,
        'nickname_suggestions' => $nickSuggestions,
        'visual_concept' => $session['visual_concept'],
        'stats' => $session['stats'] ?? null,
        'ready_to_render' => false,
        'auto_render' => $autoRender,
        'revise_remaining' => cardy_revise_remaining($session),
        'memory_hints' => $memoryHints,
        'image_url' => $session['image_url'] ?? null,
        'image_b64' => $session['image_b64'] ?? null,
        'tokens' => null,
    ]);
}

$inMode = ($session['mode'] === CARDY_MODE_FREECHAT || $path === CARDY_PATH_CHAT) ? 'chat' : 'card';
$schema = $inMode === 'chat' ? cardy_chat_schema() : cardy_card_schema();
$schemaName = $inMode === 'chat' ? 'cardy_chat_turn' : 'cardy_card_turn';

// For select_path opening with empty message, model opens the first ask
$modelMessage = $userMessage;
if ($action === 'select_path' && $modelMessage === '' && $inMode === 'card') {
    $modelMessage = '';
}

$input = cardy_build_input(
    $session['step'],
    $session,
    $modelMessage,
    $session['history'],
    $username,
    $memoryHints,
    $loreBlock
);

$result = openai_chat_responses($input, [
    'schema' => $schema,
    'schema_name' => $schemaName,
    'max_output_tokens' => 900,
    'reasoning_effort' => get_reasoning_effort(),
    'timeout' => 90,
]);

if (!$result['ok'] || !is_array($result['parsed'])) {
    error_log('chat.php: openai_chat_responses failed: ' . ($result['error'] ?? 'unknown'));
    cardy_session_save($session);
    api_error(
        'cardy_glitch',
        $result['error'] ?? 'Cardy is having a glitch. Try again? *beep*',
        502,
        [
            'session_id' => $session['id'],
            'step' => $session['step'],
            'mode' => $session['mode'],
            'message' => 'Oops, I had a little glitch there. Could you try again? *beep boop*',
        ]
    );
}

$parsed = $result['parsed'];
$replyMessage = isset($parsed['message']) && is_string($parsed['message']) ? trim($parsed['message']) : '';
$replySuggestions = isset($parsed['suggestions']) && is_array($parsed['suggestions']) ? array_values($parsed['suggestions']) : [];
$visualConcept = isset($parsed['visual_concept']) && is_array($parsed['visual_concept']) ? $parsed['visual_concept'] : null;

$replyMessage = str_replace(["\xE2\x80\x94", "\xE2\x80\x93"], [',', ','], $replyMessage);

$forbidden = ['type your own', 'enter your own', 'custom response', 'write your own', 'paint it'];
$chipPath = ($path === CARDY_PATH_CHAT) ? CARDY_PATH_FAST : $path;
$missingForChips = cardy_missing_slots($session['visual_concept'] ?? [], $chipPath);
$focusChip = $missingForChips[0] ?? '';

if ($session['step'] === CARDY_STEP_CONFIRM) {
    $replySuggestions = [];
} elseif ($focusChip === 'kind') {
    // Reliable short kind menu; AI still writes the ask in her own words.
    $replySuggestions = ['A bot', 'An android', 'A human', 'A critter'];
} else {
    $replySuggestions = array_values(array_filter($replySuggestions, function ($s) use ($forbidden) {
        if (!is_string($s) || trim($s) === '') {
            return false;
        }
        $low = strtolower($s);
        foreach ($forbidden as $bad) {
            if (strpos($low, $bad) !== false) {
                return false;
            }
        }
        return true;
    }));
    $replySuggestions = array_map(static function ($s) {
        return cardy_shorten_chip((string)$s, 24);
    }, $replySuggestions);
    $replySuggestions = array_values(array_filter($replySuggestions, static fn($s) => $s !== ''));
    if (count($replySuggestions) > 4) {
        $replySuggestions = array_slice($replySuggestions, 0, 4);
    }
    // Identity chips: kill stale dock-pun generics; pad with type-aware invents.
    if ($focusChip === 'identity') {
        $replySuggestions = cardy_sanitize_name_suggestions(
            $replySuggestions,
            $session['visual_concept'] ?? []
        );
    }
}

if ($session['step'] === CARDY_STEP_REVISE && is_array($visualConcept)) {
    $session = cardy_session_after_model($session, $replyMessage, $visualConcept);
    $session['step'] = CARDY_STEP_READY;
    $session['stats'] = cardobot_generate_stats($session['visual_concept']);
    cardy_session_save($session);
    api_json([
        'ok' => true,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'path' => $session['path'] ?? null,
        'message' => $replyMessage,
        'suggestions' => [],
        'visual_concept' => $session['visual_concept'],
        'stats' => $session['stats'],
        'ready_to_render' => false,
        'auto_render' => true,
        'revise_remaining' => cardy_revise_remaining($session),
        'memory_hints' => $memoryHints,
        'tokens' => $result['usage'],
    ]);
}

$session = cardy_session_after_model($session, $replyMessage, $visualConcept);
if ($session['step'] === CARDY_STEP_CONFIRM && empty($session['stats'])) {
    $session['stats'] = cardobot_generate_stats($session['visual_concept']);
}
// Keep printers-running step when we asked Cardy for a paint line.
if ($autoRender) {
    $session['step'] = CARDY_STEP_RENDERING;
}
cardy_session_save($session);

$nickSuggestions = ($session['step'] === CARDY_STEP_CONFIRM)
    ? cardy_nickname_suggestions($session['visual_concept'] ?? [])
    : [];

api_json([
    'ok' => true,
    'session_id' => $session['id'],
    'step' => $session['step'],
    'mode' => $session['mode'],
    'path' => $session['path'] ?? null,
    'locale' => $session['locale'] ?? $loc,
    'locale_picked' => !empty($session['locale_picked']),
    'awaiting_other_locale' => !empty($session['awaiting_other_locale']),
    'awaiting_locale_confirm' => !empty($session['awaiting_locale_confirm']),
    'awaiting_language_switch' => !empty($session['awaiting_language_switch']),
    'refresh_locale_pack' => $refreshLocalePack,
    'resumed' => false,
    'message' => $replyMessage,
    'suggestions' => $replySuggestions,
    'nickname_suggestions' => $nickSuggestions,
    'visual_concept' => $session['visual_concept'],
    'stats' => $session['stats'] ?? null,
    'ready_to_render' => false,
    'auto_render' => $autoRender,
    'revise_remaining' => cardy_revise_remaining($session),
    'memory_hints' => $memoryHints,
    'image_url' => $session['image_url'] ?? null,
    'tokens' => $result['usage'],
]);
