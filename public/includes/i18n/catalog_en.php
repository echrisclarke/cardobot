<?php
/**
 * English UI string catalog (source of truth).
 * Bump I18N_CATALOG_VERSION when keys are added so missing locales fill new keys only.
 */
const I18N_CATALOG_VERSION = 1;

function i18n_catalog_en(): array {
    return [
        'nav.home' => 'Home',
        'nav.gallery' => 'Gallery',
        'nav.profile' => 'Profile',
        'nav.logout' => 'Log out',
        'nav.login' => 'Log in',
        'nav.back' => 'Back',
        'nav.done' => 'Done',

        'studio.paint' => 'Paint my card',
        'studio.save' => 'Save card',
        'studio.download' => 'Download PNG',
        'studio.back_chat' => 'Back to chat',
        'studio.flip' => 'Flip',
        'studio.draw' => 'Draw',
        'studio.tint' => 'Tint',
        'studio.get' => 'Get',

        'chat.send' => 'Send',
        'chat.placeholder' => 'Type...',
        'chat.continue' => 'Continue',
        'chat.initializing' => 'Initializing connection...',

        'lang.prompt' => 'Which language should we use on this console?',
        'lang.english' => 'English',
        'lang.spanish' => 'Español',
        'lang.chinese' => '中文 (Mandarin)',
        'lang.other' => 'Other…',
        'lang.other_prompt' => 'Type a real language name (or code), and I will switch the console to it.',
        'lang.other_placeholder' => 'e.g. French, 日本語, de',
        'lang.rejected' => 'I can use real natural languages, but not made-up ones like that. Try Spanish, Mandarin, French, or another living language.',
        'lang.set' => 'Got it. Console language locked in.',

        'path.fast' => 'Yeah, make a card',
        'path.long' => 'Make a detailed one',
        'path.form' => 'Fill out a form',
        'path.chat' => 'Just chat for now',
        'path.form_ack' => 'Sure. Skip the Q and A. Fill this in, then I will print from what you wrote. *beep*',
        'path.confirm_ack' => 'Got it. Take a look, then we can paint your card. *whirr*',

        'form.title_chat' => 'Your card so far',
        'form.title_form' => 'Build your card',
        'form.hint_chat' => 'Tweak anything, then paint.',
        'form.hint_form' => 'Pick a kind, name them, describe the look, then paint. No chat needed.',
        'form.kind' => 'Kind',
        'form.who' => 'Who / what are they?',
        'form.who_ph' => 'e.g. rusty dock crane bot',
        'form.nickname' => 'Nickname',
        'form.nickname_ph' => 'Type your own callsign',
        'form.vibe' => 'Vibe',
        'form.vibe_ph' => 'mood or energy',
        'form.details' => 'Details / look',
        'form.details_ph' => 'what they look like',
        'form.setting' => 'Setting',
        'form.setting_ph' => 'where they are',
        'form.stake' => 'Stake',
        'form.stake_ph' => 'what matters to them',
        'form.optional' => '(optional)',
        'form.paint' => 'Paint it!',
        'form.update' => 'Update',
        'form.missing' => 'Still need: {fields}',
        'form.kind_bot' => 'Bot',
        'form.kind_android' => 'Android',
        'form.kind_human' => 'Human',
        'form.kind_critter' => 'Critter',

        'profile.language' => 'Language',
        'profile.language_help' => 'Console and new cards use this language.',
        'profile.save' => 'Save',
        'profile.saved' => 'Saved.',
        'profile.title' => 'Profile',

        'error.generic' => 'Something went wrong. Try again.',
        'error.auth' => 'Please log in to continue.',
        'error.network' => 'Network error. Check your connection.',
    ];
}
