<?php
/**
 * Smoke checks for locale predict + change-language intent (no DB required).
 */
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/state.php';

$fail = 0;
function expect($cond, string $label) {
    global $fail;
    if ($cond) {
        echo "OK  $label\n";
    } else {
        echo "FAIL $label\n";
        $fail++;
    }
}

expect(i18n_detect_from_accept_language('en-US,es;q=0.9') === 'en', 'accept-language en-US');
expect(i18n_detect_from_accept_language('zh-CN,zh;q=0.9') === 'zh-Hans', 'accept-language zh-CN');
expect(i18n_detect_from_navigator_languages(['fr-FR', 'en']) === 'fr', 'navigator fr-FR');
expect(i18n_predict_locale(0, ['de-DE']) === 'de', 'predict navigator de');

$c = i18n_detect_change_language_intent('please switch to Spanish');
expect(!empty($c['intent']) && ($c['target'] ?? '') === 'es', 'change intent spanish');

$c2 = i18n_detect_change_language_intent('cambiar idioma');
expect(!empty($c2['intent']) && empty($c2['target']), 'change intent bare');

$c3 = i18n_detect_change_language_intent('make a robot with lasers');
expect(empty($c3['intent']), 'no false change intent');

expect(str_contains(i18n_t('lang.confirm', 'en', ['language' => 'English']), 'English'), 'lang.confirm');
expect(I18N_CATALOG_VERSION >= 2, 'catalog version bumped');

$session = [
    'id' => 'cs_test',
    'step' => 'agenda',
    'locale_picked' => true,
    'history' => [['role' => 'assistant', 'content' => 'hi']],
];
expect(cardy_session_is_resumable($session), 'resumable with history');
expect(!cardy_session_is_resumable(['id' => 'x', 'step' => 'greeting', 'locale_picked' => false, 'history' => []]), 'fresh not resumable');

echo $fail === 0 ? "\nAll smoke checks passed.\n" : "\n$fail check(s) failed.\n";
exit($fail === 0 ? 0 : 1);
