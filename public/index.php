<?php
/**
 * Card-o-Bot v3
 *
 * Intro flow (preserved from v2):
 *   1) Story text + Continue
 *   2) Loading bar (Cardy's greeting loads in the background)
 *   3) "A face appears..." -> Cardy GIF fades in -> Cardy types out her hello
 *   4) From there a structured 3-question wizard runs
 * Chat v2: gather -> confirm -> ready -> render -> reveal
 *          then Save / Draw (optional) / Download / Revise
 *
 * Backend: api/chat.php, api/render-image.php, api/image-status.php,
 * api/export-card.php, api/download-card.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/console.php';

require_auth();

$basePath = get_base_path();
$assetPath = get_asset_path();
$cardobotUsername = (string)(get_username() ?: 'Cardy');

console_start('Card-o-Bot');
$contentClosed = console_content_end();
?>
<link rel="stylesheet" href="<?php echo cardobot_asset_url('assets/css/studio.css'); ?>">
<link rel="stylesheet" href="<?php echo cardobot_asset_url('assets/css/card-viewer.css'); ?>">
<script src="<?php echo cardobot_asset_url('assets/js/card-layout.js'); ?>"></script>
<script src="<?php echo cardobot_asset_url('assets/js/drawing-engine.js'); ?>"></script>
<script src="<?php echo cardobot_asset_url('assets/js/card-studio.js'); ?>"></script>
<script src="<?php echo cardobot_asset_url('assets/js/card-viewer.js'); ?>"></script>
<style>
/* ------------------------------------------------------------------
   Wizard-specific additions on top of base.css. The intro text,
   loading bar, suggestion chips, terminal typing effect, etc, are all
   defined in base.css and we just reuse them.
   ------------------------------------------------------------------ */

/* Pin Cardy's face to the chat box so it doesn't scroll with the messages.
   base.css uses an absolutely-positioned ::before inside .chat-messages,
   and absolute children of a scroll container scroll with the content.
   Replace it with a real background on the element itself: with the
   default background-attachment ("scroll"), the GIF stays attached to
   the element's border box and does NOT move when child messages scroll. */
body.chat-page .chat-messages.show-cardy-bg::before {
    display: none !important;
}
body.chat-page .chat-messages.show-cardy-bg {
    background-color: #000;
    background-image: url('assets/img/cardyfacevideo (1).gif');
    background-repeat: no-repeat;
    background-position: center center;
    background-size: contain;
    background-attachment: scroll; /* explicit: stay pinned to the element */
}

.system-notification {
    margin: 1rem 0;
    padding: 1rem 1.5rem;
    background: rgba(255, 193, 7, 0.15);
    border: 1px solid rgba(255, 193, 7, 0.4);
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    font-size: 0.9rem;
    line-height: 1.5;
    text-align: left;
    word-wrap: break-word;
    overflow-wrap: break-word;
    box-sizing: border-box;
}
.system-notification strong { color: #ffc107; font-weight: 600; }
.system-notification.hidden { display: none; }

@media (max-width: 768px) {
    .system-notification {
        margin: 0.75rem 0;
        padding: 0.75rem 1rem;
        font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        line-height: 1.4;
    }
}

/* Typing indicator dots while Cardy thinks. */
.typing-indicator {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    background: rgba(var(--color-dark-rgb), 0.3);
    border-radius: var(--radius-md);
    border: 1px solid rgba(var(--color-secondary-rgb), 0.2);
    max-width: fit-content;
}
.typing-indicator .typing-dots { display: flex; gap: 0.25rem; align-items: center; }
.typing-indicator .typing-dot {
    width: 8px; height: 8px;
    background: var(--color-secondary-light);
    border-radius: 50%;
    animation: typingDot 1.4s infinite ease-in-out;
}
.typing-indicator .typing-dot:nth-child(1) { animation-delay: 0s; }
.typing-indicator .typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator .typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingDot {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.7; }
    30% { transform: translateY(-10px); opacity: 1; }
}

/* ----- Render button (page-level) ----- */
.wizard-render-button-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
    padding: 0.5rem 1rem 1rem;
}
.wizard-render-button-container.hidden { display: none !important; }

.wizard-render-button {
    padding: 0.85rem 2.25rem;
    font-family: var(--font-family-retro, var(--font-family-primary));
    font-size: 1.05rem;
    border: 2px solid var(--color-secondary-light);
    background: rgba(var(--color-secondary-rgb), 0.22);
    color: var(--color-text-primary);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 0 12px rgba(var(--color-secondary-rgb), 0.3);
}
.wizard-render-button:hover {
    background: rgba(var(--color-secondary-rgb), 0.35);
    transform: translateY(-1px);
    box-shadow: 0 0 18px rgba(var(--color-secondary-rgb), 0.5);
}
.wizard-render-button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* ----- Inline image card inside the chat scroll ----- */
.wizard-card-image {
    align-self: stretch;
    margin: 0.75rem auto 1rem;
    padding: 0.75rem;
    background: rgba(var(--color-dark-rgb), 0.55);
    border-radius: var(--radius-md);
    border: 1px solid rgba(var(--color-secondary-rgb), 0.35);
    max-width: 520px;
    width: 100%;
    box-sizing: border-box;
    box-shadow: 0 0 16px rgba(var(--color-secondary-rgb), 0.18);
    animation: cardImageReveal 0.6s ease-out;
}
.wizard-card-image img { width: 100%; height: auto; display: block; border-radius: var(--radius-sm); }
.wizard-card-image .wizard-image-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    min-height: 240px;
    padding: 2.5rem 1rem;
    color: var(--color-secondary-light);
    font-family: var(--font-family-retro, var(--font-family-primary));
    text-align: center;
    background: rgba(0, 0, 0, 0.55);
    border: 1px dashed rgba(var(--color-secondary-rgb), 0.45);
    border-radius: var(--radius-sm);
}
.wizard-card-image .wizard-image-loading p {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.4;
}
@keyframes cardImageReveal {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.wizard-spinner {
    width: 36px; height: 36px;
    border: 4px solid rgba(var(--color-secondary-rgb), 0.25);
    border-top-color: var(--color-secondary-light);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.wizard-error {
    margin: 0.75rem 1rem;
    padding: 0.75rem 1rem;
    background: rgba(220, 53, 69, 0.15);
    border: 1px solid rgba(220, 53, 69, 0.4);
    border-radius: var(--radius-md);
    color: var(--color-text-primary);
    font-size: 0.9rem;
}

.waiting-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-secondary-light);
    font-style: italic;
    text-align: center;
}
</style>

<script>document.body.classList.add('chat-page');</script>

<div class="chat-messages" id="chatMessages">
    <!-- Stage 1: intro story -->
    <div class="intro-message" id="introMessage">
        <p>You've just docked your ship and stepped aboard a vessel drifting above an unknown world. The corridors are silent, save for the hum of ancient systems. In a dimly lit chamber, you find a matter-compiled device resting on a console. The words "Card-o-Bot" are etched above its screen.</p>
        <div class="continue-button-container" id="introContinueBtn">
            <button type="button" class="continue-button">Continue</button>
        </div>
    </div>

    <!-- Stage 2: loading bar (hidden until first Continue) -->
    <div class="chat-loading-bar hidden" id="chatLoadingBar">
        <div class="loading-bar-container">
            <div class="loading-bar-fill"></div>
        </div>
        <p class="loading-text">Initializing connection...</p>
    </div>

    <!-- Subsequent stages (face appearing, Cardy messages, suggestion chips) appended dynamically. -->

    <!-- Confirm/form lives inside chat scroll so it never crushes the message flex region. -->
    <div class="confirm-panel" id="confirmPanel">
        <p id="confirmPanelTitle"><strong>Your card so far</strong></p>
        <p class="confirm-hint" id="confirmHint">Tweak anything, then paint.</p>
        <div class="confirm-form-extra" id="confirmFormExtra" hidden>
          <label data-i18n="form.kind">Kind
            <div class="confirm-kind-chips" id="confirmKindChips" aria-label="Card kind"></div>
            <input type="hidden" id="confirmType" value="">
          </label>
          <label data-i18n-label="form.who">Who / what are they?
            <input type="text" id="confirmSubject" maxlength="120" placeholder="e.g. rusty dock crane bot" data-i18n-placeholder="form.who_ph">
          </label>
        </div>
        <label data-i18n-label="form.nickname">Nickname
          <input type="text" id="confirmNickname" maxlength="16" placeholder="Type your own callsign" data-i18n-placeholder="form.nickname_ph">
        </label>
        <div class="confirm-nick-chips" id="confirmNickChips" aria-label="Suggested names from Cardy"></div>
        <label data-i18n-label="form.vibe">Vibe <input type="text" id="confirmVibe" maxlength="120" placeholder="mood or energy" data-i18n-placeholder="form.vibe_ph"></label>
        <label data-i18n-label="form.details">Details / look <textarea id="confirmDetails" rows="2" maxlength="500" placeholder="what they look like" data-i18n-placeholder="form.details_ph"></textarea></label>
        <div class="confirm-form-extra" id="confirmFormExtraMore" hidden>
          <label data-i18n-label="form.setting">Setting <span class="confirm-optional" data-i18n="form.optional">(optional)</span>
            <input type="text" id="confirmSetting" maxlength="160" placeholder="where they are" data-i18n-placeholder="form.setting_ph">
          </label>
          <label data-i18n-label="form.stake">Stake <span class="confirm-optional" data-i18n="form.optional">(optional)</span>
            <input type="text" id="confirmStake" maxlength="160" placeholder="what matters to them" data-i18n-placeholder="form.stake_ph">
          </label>
        </div>
        <p class="confirm-form-error" id="confirmFormError" hidden></p>
        <div class="reveal-actions">
            <button type="button" id="confirmPaintBtn" data-i18n="form.paint">Paint it!</button>
            <button type="button" class="secondary" id="confirmUpdateBtn" data-i18n="form.update">Update</button>
        </div>
    </div>
</div>

<div class="wizard-render-button-container hidden" id="renderButtonContainer">
    <button type="button" class="wizard-render-button" id="renderButton">Paint my card</button>
</div>

<div class="wizard-studio-panel" id="studioPanel">
    <div id="studioRoot"></div>
    <div class="reveal-actions" id="studioActions">
        <button type="button" id="studioSaveBtn">Save card</button>
        <button type="button" class="secondary" id="studioDownloadBtn">Download PNG</button>
        <button type="button" class="secondary" id="studioBackBtn">Back to chat</button>
    </div>
</div>

<div class="chat-input-container">
    <form id="chatForm" class="chat-form">
        <textarea
            id="messageInput"
            class="chat-input"
            placeholder="Type..."
            autocomplete="off"
            rows="1"></textarea>
        <button type="submit" class="chat-send-btn" aria-label="Send message">
            <span>Send</span>
        </button>
    </form>
</div>

<script>
(function() {
    'use strict';

    // ============================================================
    //   Config + DOM
    // ============================================================
    const basePath = <?php echo json_encode($basePath); ?>;

    const $chatMessages    = document.getElementById('chatMessages');
    const $introMessage    = document.getElementById('introMessage');
    const $introContinue   = document.getElementById('introContinueBtn');
    const $chatLoadingBar  = document.getElementById('chatLoadingBar');
    const $renderContainer = document.getElementById('renderButtonContainer');
    const $renderButton    = document.getElementById('renderButton');
    const $chatForm        = document.getElementById('chatForm');
    const $messageInput    = document.getElementById('messageInput');
    const $inputContainer  = document.querySelector('.chat-input-container');
    const $sendBtn         = $chatForm.querySelector('.chat-send-btn');
    const $confirmPanel    = document.getElementById('confirmPanel');
    const $studioPanel     = document.getElementById('studioPanel');
    const $studioRoot      = document.getElementById('studioRoot');

    const assetBase = <?php echo json_encode($assetPath); ?>;
    const cardobotUsername = <?php echo json_encode($cardobotUsername); ?>;
    let studio = null;
    let lastArtUrl = null;
    let lastConcept = {};
    let lastStats = null;
    let cardViewer = null;

    function conceptWithCredit(concept) {
        const c = Object.assign({}, concept || {});
        if (!c.creator_username && !c.username) c.creator_username = cardobotUsername;
        return c;
    }

    // ============================================================
    //   State
    // ============================================================
    const state = {
        sessionId: null,
        step: 'greeting',
        mode: null,
        path: null,
        locale: 'en',
        localePicked: false,
        awaitingOtherLocale: false,
        i18n: {},
        pendingRender: false,
        pendingRenderStatus: '',
        renderInFlight: false,
        readyToRender: false,
        savedCardId: null,
        visualConcept: null,

        // Async-loaded greeting (so the loading bar has something to wait for).
        greeting: null,           // { message, suggestions, session_id }
        greetingReady: false,
        greetingError: null,

        cardyFirstMessageShown: false,
        busy: false,
        typingElement: null,
        typingTimer: null,
        introComplete: false,

        imageTaskId: null,
        imagePollHandle: null,
        imagePollAttempts: 0,
        imagePollAborted: false,
        imageReadyHandled: false,  // gate so we render exactly one image per task
        imageLoadingElement: null, // inline placeholder shown while painting
        imageElement: null,        // inline finished image
    };

    function t(key, fallback) {
        const v = state.i18n && state.i18n[key];
        return (v != null && v !== '') ? v : (fallback != null ? fallback : key);
    }

    function applyI18n() {
        document.querySelectorAll('[data-i18n]').forEach((el) => {
            const key = el.getAttribute('data-i18n');
            if (!key) return;
            el.textContent = t(key, el.textContent);
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
            const key = el.getAttribute('data-i18n-placeholder');
            if (!key) return;
            el.setAttribute('placeholder', t(key, el.getAttribute('placeholder') || ''));
        });
        document.querySelectorAll('[data-i18n-label]').forEach((el) => {
            const key = el.getAttribute('data-i18n-label');
            if (!key) return;
            const labelText = t(key);
            const input = el.querySelector('input, textarea, select, .confirm-kind-chips');
            if (!input) {
                el.textContent = labelText;
                return;
            }
            // Keep first text node as the label; preserve controls.
            let set = false;
            el.childNodes.forEach((node) => {
                if (set) return;
                if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
                    node.textContent = labelText + ' ';
                    set = true;
                }
            });
            if (!set) {
                el.insertBefore(document.createTextNode(labelText + ' '), el.firstChild);
            }
        });
        const sendBtn = document.querySelector('.chat-send-btn span');
        if (sendBtn) sendBtn.textContent = t('chat.send', sendBtn.textContent);
        if ($messageInput) $messageInput.placeholder = t('chat.placeholder', $messageInput.placeholder);
        const continueBtn = document.querySelector('#introContinueBtn .continue-button');
        if (continueBtn) continueBtn.textContent = t('chat.continue', continueBtn.textContent);
        const loadingText = document.querySelector('#chatLoadingBar .loading-text');
        if (loadingText) loadingText.textContent = t('chat.initializing', loadingText.textContent);
        if ($renderButton) $renderButton.textContent = t('studio.paint', $renderButton.textContent);
    }

    async function loadLocalePack(code) {
        try {
            const res = await fetch(basePath + '/api/locale.php' + (code ? ('?code=' + encodeURIComponent(code)) : ''), {
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data && data.ok !== false && data.strings) {
                state.i18n = data.strings;
                state.locale = data.locale || code || 'en';
                applyI18n();
            }
        } catch (err) {
            console.warn('locale pack load failed', err);
        }
    }

    function localeCodeFromChip(text) {
        const lower = String(text || '').toLowerCase().trim();
        if (lower === 'english' || lower.includes('english')) return 'en';
        if (lower === 'español' || lower === 'espanol' || lower.includes('español')) return 'es';
        if (lower.includes('中文') || lower.includes('mandarin') || lower.includes('chinese')) return 'zh-Hans';
        if (lower.startsWith('other') || lower === 'otro…' || lower === 'otro...' || lower.includes('其他') || lower.includes('autre') || lower.includes('andere') || lower.includes('その他') || lower.includes('기타') || lower.includes('outro') || lower.includes('altro')) {
            return 'other';
        }
        return null;
    }

    // ============================================================
    //   HTTP helper
    // ============================================================
    async function postJson(url, body, attempt) {
        attempt = attempt || 0;
        let res;
        try {
            res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body || {}),
            });
        } catch (netErr) {
            if (attempt < 1) return postJson(url, body, attempt + 1);
            throw new Error(netErr.message || 'Network error');
        }
        let data = null;
        try { data = await res.json(); } catch (e) {}
        if (!res.ok) {
            if (res.status === 401) {
                window.location.href = basePath + '/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                throw new Error('Authentication required. Please sign in again.');
            }
            if ((res.status >= 500 || res.status === 502) && attempt < 1) {
                return postJson(url, body, attempt + 1);
            }
            const msg = (data && (data.message || data.error)) || ('HTTP ' + res.status);
            throw new Error(msg);
        }
        return data;
    }

    // ============================================================
    //   Stage 1: pre-load Cardy's greeting in the background
    // ============================================================
    async function preloadGreeting() {
        try {
            const data = await postJson(basePath + '/api/chat.php', { action: 'greeting' });
            if (!data.session_id) throw new Error('No session from Cardy');
            state.sessionId = data.session_id;
            state.greeting = data;
            state.greetingReady = true;
            markGreetingReady();
        } catch (err) {
            console.error('greeting preload failed:', err);
            state.greetingError = err.message || 'unknown error';
            state.greeting = null;
            state.greetingReady = false;
            if (/Authentication required/i.test(state.greetingError)) {
                return; // postJson already sent us to login
            }
            if ($chatLoadingBar) {
                const t = $chatLoadingBar.querySelector('.loading-text');
                if (t) t.textContent = 'Connection glitch. Tap Continue to retry.';
            }
            const noticeBtn = document.querySelector('#noticeContinueBtn .continue-button');
            if (noticeBtn) {
                noticeBtn.disabled = false;
                noticeBtn.style.opacity = '1';
                noticeBtn.onclick = () => { state.greetingReady = false; preloadGreeting(); };
            }
        }
    }
    loadLocalePack();
    preloadGreeting();

    function markGreetingReady() {
        if ($chatLoadingBar) {
            $chatLoadingBar.classList.add('complete');
            const t = $chatLoadingBar.querySelector('.loading-text');
            if (t) t.textContent = 'Initialization complete!';
        }
        const noticeBtn = document.querySelector('#noticeContinueBtn .continue-button');
        const waiting   = document.querySelector('#noticeContinueBtn .waiting-text');
        if (noticeBtn) {
            noticeBtn.disabled = false;
            noticeBtn.style.opacity = '1';
            noticeBtn.style.cursor = 'pointer';
        }
        if (waiting) waiting.remove();
    }

    // ============================================================
    //   Stage 2: user clicks Continue on the intro story
    // ============================================================
    function proceedFromIntro() {
        if ($introContinue) $introContinue.classList.add('hidden');
        $chatLoadingBar.classList.remove('hidden');

        // Continue once Cardy's greeting is ready (no system notice).
        const noticeBtnContainer = document.createElement('div');
        noticeBtnContainer.className = 'continue-button-container';
        noticeBtnContainer.id = 'noticeContinueBtn';

        const noticeBtn = document.createElement('button');
        noticeBtn.type = 'button';
        noticeBtn.className = 'continue-button';
        noticeBtn.textContent = 'Continue';
        noticeBtn.addEventListener('click', proceedFromNotice);

        if (!state.greetingReady) {
            noticeBtn.disabled = true;
            noticeBtn.style.opacity = '0.5';
            noticeBtn.style.cursor = 'not-allowed';
            const waiting = document.createElement('span');
            waiting.className = 'waiting-text';
            waiting.textContent = 'Waiting for Cardy to initialize...';
            noticeBtnContainer.appendChild(waiting);
        }
        noticeBtnContainer.appendChild(noticeBtn);
        $chatMessages.appendChild(noticeBtnContainer);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;

        if (state.greetingReady) markGreetingReady();
    }

    // ============================================================
    //   Stage 3: Continue after init
    //   -> face appears -> face fades out -> Cardy speaks
    // ============================================================
    function proceedFromNotice() {
        const noticeBtn = document.getElementById('noticeContinueBtn');
        if (noticeBtn) noticeBtn.classList.add('hidden');
        if ($chatLoadingBar) $chatLoadingBar.classList.add('hidden');
        if ($introMessage)   $introMessage.classList.add('hidden');

        const faceMsg = document.createElement('div');
        faceMsg.className = 'intro-message face-appearing-message';
        faceMsg.innerHTML = '<p>A face appears on the display...</p>';
        $chatMessages.appendChild(faceMsg);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;

        setTimeout(() => {
            $chatMessages.classList.add('show-cardy-bg');
            setTimeout(() => {
                $chatMessages.querySelectorAll('.face-appearing-message').forEach(el => el.classList.add('hidden'));
                setTimeout(showCardyGreeting, 1000);
            }, 1000);
        }, 1000);
    }

    function showCardyGreeting() {
        if (!state.greeting) return; // shouldn't happen but defensive
        state.step = state.greeting.step || 'greeting';
        state.mode = state.greeting.mode || null;
        state.introComplete = true;
        const messageEl = appendCardyMessage(state.greeting.message, true);
        appendSuggestionsAfter(messageEl, state.greeting.suggestions);
    }

    // ============================================================
    //   Cardy / user message rendering
    // ============================================================
    function appendCardyMessage(text, useTypingEffect) {
        const div = document.createElement('div');
        div.className = 'chat-message cardy';
        $chatMessages.appendChild(div);

        if (!state.cardyFirstMessageShown) {
            state.cardyFirstMessageShown = true;
            $chatMessages.classList.add('show-cardy-bg');
        }

        if (useTypingEffect) {
            const span = document.createElement('span');
            span.className = 'message-text';
            const cursor = document.createElement('span');
            cursor.className = 'terminal-cursor';
            cursor.textContent = '_';
            div.appendChild(span);
            div.appendChild(cursor);
            $chatMessages.scrollTop = $chatMessages.scrollHeight;

            let i = 0;
            const speed = 26;
            (function type() {
                if (i < text.length) {
                    span.textContent += text[i++];
                    $chatMessages.scrollTop = $chatMessages.scrollHeight;
                    setTimeout(type, speed);
                } else {
                    setTimeout(() => {
                        cursor.style.opacity = '0';
                        setTimeout(() => cursor.remove(), 500);
                    }, 800);
                }
            })();
        } else {
            const span = document.createElement('span');
            span.className = 'message-text';
            span.textContent = text;
            div.appendChild(span);
        }
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
        return div;
    }

    function appendUserMessage(text) {
        const div = document.createElement('div');
        div.className = 'chat-message user';
        div.textContent = text;
        $chatMessages.appendChild(div);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
        return div;
    }

    // Suggestion chips, attached AFTER Cardy's typing finishes so they
    // don't appear before the prompt is fully visible.
    function appendSuggestionsAfter(messageEl, suggestions) {
        if (!Array.isArray(suggestions)) suggestions = [];
        const textSpan = messageEl.querySelector('.message-text');
        const delay = textSpan ? Math.min(textSpan.textContent.length * 26 + 600, 4500) : 0;

        setTimeout(() => {
            const wrap = document.createElement('div');
            wrap.className = 'suggested-responses';

            suggestions.forEach(s => {
                if (!s) return;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'suggestion-button';
                btn.textContent = s;
                btn.addEventListener('click', () => handleSuggestion(s));
                wrap.appendChild(btn);
            });

            // Only offer "type your own" when there are chips to contrast against.
            // Empty suggestion turns = just use the text box.
            if (suggestions.length > 0 && !['confirm', 'ready', 'reveal', 'rendering'].includes(state.step)) {
                const own = document.createElement('button');
                own.type = 'button';
                own.className = 'suggestion-button type-own-button';
                own.textContent = 'Type your own response';
                own.addEventListener('click', () => {
                    $messageInput.focus();
                    $messageInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
                wrap.appendChild(own);
            }

            if (wrap.childElementCount > 0) {
                $chatMessages.appendChild(wrap);
                $chatMessages.scrollTop = $chatMessages.scrollHeight;
            }
        }, delay);
    }

    function appendError(msg) {
        const div = document.createElement('div');
        div.className = 'wizard-error';
        div.textContent = msg;
        $chatMessages.appendChild(div);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
    }

    // Strip every old set of suggestion chips so we don't pile them up.
    function clearStaleSuggestions() {
        $chatMessages.querySelectorAll('.suggested-responses').forEach(el => el.remove());
    }

    // ============================================================
    //   Typing indicator
    // ============================================================
    function showTypingIndicator() {
        hideTypingIndicator();
        const div = document.createElement('div');
        div.className = 'chat-message cardy typing-indicator';
        div.innerHTML = '<div class="typing-dots"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
        $chatMessages.appendChild(div);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
        state.typingElement = div;
    }
    function hideTypingIndicator() {
        if (state.typingElement && state.typingElement.parentNode) {
            state.typingElement.parentNode.removeChild(state.typingElement);
        }
        state.typingElement = null;
    }

    // ============================================================
    //   Page-level wizard chrome
    // ============================================================
    function setBusy(busy) {
        state.busy = busy;
        $sendBtn.disabled = busy;
        $messageInput.disabled = busy;
        $sendBtn.style.opacity = busy ? '0.5' : '1';
    }
    function showRenderButton() { $renderContainer.classList.remove('hidden'); }
    function hideRenderButton() { $renderContainer.classList.add('hidden'); }

    // Append the printers-running placeholder INLINE inside the chat scroll.
    // statusText comes from Cardy (AI) when available.
    function showImageLoading(statusText) {
        const line = (statusText && String(statusText).trim())
            ? String(statusText).trim()
            : 'Printers warming up... *whirr*';
        if (state.imageLoadingElement && state.imageLoadingElement.parentNode) {
            const p = state.imageLoadingElement.querySelector('p');
            if (p) p.textContent = line;
            return;
        }
        const block = document.createElement('div');
        block.className = 'wizard-card-image';
        block.setAttribute('data-loading', '1');
        const wrap = document.createElement('div');
        wrap.className = 'wizard-image-loading';
        wrap.setAttribute('role', 'status');
        wrap.setAttribute('aria-live', 'polite');
        wrap.innerHTML = '<div class="wizard-spinner" aria-hidden="true"></div>';
        const p = document.createElement('p');
        p.textContent = line;
        wrap.appendChild(p);
        block.appendChild(wrap);
        $chatMessages.appendChild(block);
        state.imageLoadingElement = block;
        requestAnimationFrame(() => {
            $chatMessages.scrollTop = $chatMessages.scrollHeight;
        });
    }
    function removeImageLoading() {
        if (state.imageLoadingElement && state.imageLoadingElement.parentNode) {
            state.imageLoadingElement.parentNode.removeChild(state.imageLoadingElement);
        }
        state.imageLoadingElement = null;
    }

    // Replace the loading placeholder with the finished image inline.
    // Defensive: if an image block already exists, remove it first so we
    // never accumulate duplicate copies in the chat.
    function showImage(url) {
        removeImageLoading();
        lastArtUrl = url;
        if (state.imageElement && state.imageElement.parentNode) {
            state.imageElement.parentNode.removeChild(state.imageElement);
            state.imageElement = null;
        }
        const block = document.createElement('div');
        block.className = 'wizard-card-image';
        const img = document.createElement('img');
        img.alt = 'Your card';
        img.src = url;
        block.appendChild(img);
        $chatMessages.appendChild(block);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
        state.imageElement = block;
    }

    function ensureViewer() {
        if (cardViewer) return cardViewer;
        cardViewer = new window.CardobotViewer({
            assetBase: assetBase,
            apiBase: basePath,
            onClose: () => {},
            onCreditChange: (on) => {
                lastConcept = Object.assign({}, lastConcept || {}, { show_credit: !!on });
                if (state.visualConcept) {
                    state.visualConcept = Object.assign({}, state.visualConcept, { show_credit: !!on });
                }
            },
            onSave: async ({ download, studio: s, sessionId, viewer }) => {
                await handleSaveFramed(!!download, s, viewer);
            },
        });
        return cardViewer;
    }

    async function openCardApp(mode) {
        if (!lastArtUrl) return;
        $studioPanel.classList.remove('visible');
        const v = ensureViewer();
        await v.open({
            sessionId: state.sessionId,
            concept: conceptWithCredit(lastConcept || state.visualConcept || {}),
            stats: lastStats || {},
            artUrl: lastArtUrl,
            mode: mode === 'draw' ? 'draw' : 'viewer',
        });
    }

    function appendRevealActionsAfter(messageEl) {
        clearStaleSuggestions();
        removeImageLoading();
        if (state.imageElement && state.imageElement.parentNode) {
            state.imageElement.parentNode.removeChild(state.imageElement);
            state.imageElement = null;
        }
        const wrap = document.createElement('div');
        wrap.className = 'suggested-responses reveal-actions';

        function chip(label, fn) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'suggestion-button';
            b.textContent = label;
            b.setAttribute('data-action', label.toLowerCase().replace(/\s+/g, '-'));
            b.addEventListener('click', fn);
            wrap.appendChild(b);
        }

        chip('View card', () => openCardApp('viewer'));
        chip('Draw on it', () => openCardApp('draw'));
        chip('Change something', () => promptRevise());
        chip('Make another one', handleMakeAnother);

        $chatMessages.appendChild(wrap);
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
        // Auto-open the holo viewer
        openCardApp('viewer');
    }

    function isFormPath() {
        return state.path === 'form';
    }

    function fillConfirmPanel(concept, nickSuggestions) {
        lastConcept = concept || {};
        const formMode = isFormPath();
        const title = document.getElementById('confirmPanelTitle');
        const hint = document.getElementById('confirmHint');
        const extra = document.getElementById('confirmFormExtra');
        const extraMore = document.getElementById('confirmFormExtraMore');
        const err = document.getElementById('confirmFormError');
        if (title) {
            title.innerHTML = formMode
                ? '<strong>' + t('form.title_form', 'Build your card') + '</strong>'
                : '<strong>' + t('form.title_chat', 'Your card so far') + '</strong>';
        }
        if (hint) {
            hint.textContent = formMode
                ? t('form.hint_form', 'Pick a kind, name them, describe the look, then paint. No chat needed.')
                : t('form.hint_chat', 'Tweak anything, then paint.');
        }
        if (extra) extra.hidden = !formMode;
        if (extraMore) extraMore.hidden = !formMode;
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }

        const typeEl = document.getElementById('confirmType');
        const subjectEl = document.getElementById('confirmSubject');
        const settingEl = document.getElementById('confirmSetting');
        const stakeEl = document.getElementById('confirmStake');
        if (typeEl) typeEl.value = lastConcept.type || '';
        if (subjectEl) subjectEl.value = lastConcept.subject || '';
        if (settingEl) settingEl.value = lastConcept.setting || '';
        if (stakeEl) stakeEl.value = lastConcept.stake || '';
        document.getElementById('confirmNickname').value = lastConcept.nickname || (!formMode ? (lastConcept.subject || '') : '');
        document.getElementById('confirmVibe').value = lastConcept.vibe || '';
        document.getElementById('confirmDetails').value = lastConcept.details || '';
        if (formMode) renderConfirmKindChips(lastConcept.type || '');
        renderConfirmNickChips(nickSuggestions);
        // Keep the form at the end of the scrollable chat transcript.
        if ($confirmPanel.parentElement !== $chatMessages) {
            $chatMessages.appendChild($confirmPanel);
        } else {
            $chatMessages.appendChild($confirmPanel);
        }
        $confirmPanel.classList.add('visible');
        $chatMessages.scrollTop = $chatMessages.scrollHeight;
        if (typeof applyI18n === 'function') applyI18n();
    }

    function renderConfirmKindChips(selected) {
        const wrap = document.getElementById('confirmKindChips');
        const typeEl = document.getElementById('confirmType');
        if (!wrap) return;
        wrap.innerHTML = '';
        const kinds = [
            { value: 'Bot', label: t('form.kind_bot', 'Bot') },
            { value: 'Android', label: t('form.kind_android', 'Android') },
            { value: 'Human', label: t('form.kind_human', 'Human') },
            { value: 'Critter', label: t('form.kind_critter', 'Critter') },
        ];
        const current = String(selected || (typeEl && typeEl.value) || '').trim();
        kinds.forEach(({ value: kind, label }) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'suggestion-button confirm-kind-chip'
                + (kind.toLowerCase() === current.toLowerCase() ? ' is-selected' : '');
            btn.textContent = label;
            btn.dataset.kind = kind;
            btn.addEventListener('click', () => {
                if (typeEl) typeEl.value = kind;
                wrap.querySelectorAll('.confirm-kind-chip').forEach((el) => {
                    el.classList.toggle('is-selected', el.dataset.kind === kind);
                });
            });
            wrap.appendChild(btn);
        });
    }

    function readConfirmPatch() {
        const patch = {
            nickname: document.getElementById('confirmNickname').value.trim(),
            vibe: document.getElementById('confirmVibe').value.trim(),
            details: document.getElementById('confirmDetails').value.trim(),
        };
        if (isFormPath()) {
            const type = (document.getElementById('confirmType')?.value || '').trim();
            const subject = (document.getElementById('confirmSubject')?.value || '').trim();
            const setting = (document.getElementById('confirmSetting')?.value || '').trim();
            const stake = (document.getElementById('confirmStake')?.value || '').trim();
            if (type) patch.type = type;
            if (subject) patch.subject = subject;
            else if (patch.nickname) patch.subject = patch.nickname;
            if (setting) patch.setting = setting;
            if (stake) patch.stake = stake;
        }
        return patch;
    }

    function validateConfirmForm(patch) {
        const err = document.getElementById('confirmFormError');
        if (!isFormPath()) {
            if (err) { err.hidden = true; err.textContent = ''; }
            return true;
        }
        const missing = [];
        if (!patch.type) missing.push('kind');
        if (!patch.nickname && !patch.subject) missing.push('name or who they are');
        if (!patch.details && !patch.vibe) missing.push('details or vibe');
        if (missing.length) {
            if (err) {
                err.hidden = false;
                err.textContent = 'Need: ' + missing.join(', ') + '.';
            }
            return false;
        }
        if (err) { err.hidden = true; err.textContent = ''; }
        return true;
    }

    function renderConfirmNickChips(suggestions) {
        const wrap = document.getElementById('confirmNickChips');
        if (!wrap) return;
        wrap.innerHTML = '';
        const list = Array.isArray(suggestions) ? suggestions : [];
        const current = (document.getElementById('confirmNickname').value || '').trim();
        const seen = new Set();
        list.forEach((name) => {
            if (!name || typeof name !== 'string') return;
            const label = name.trim().slice(0, 22);
            if (!label) return;
            const key = label.toLowerCase();
            if (seen.has(key)) return;
            seen.add(key);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'suggestion-button confirm-nick-chip'
                + (label.toLowerCase() === current.toLowerCase() ? ' is-selected' : '');
            btn.textContent = label;
            btn.addEventListener('click', () => {
                document.getElementById('confirmNickname').value = label;
                wrap.querySelectorAll('.confirm-nick-chip').forEach((el) => {
                    el.classList.toggle('is-selected', el.textContent === label);
                });
            });
            wrap.appendChild(btn);
        });
        if (wrap.childElementCount > 0) {
            const hint = document.createElement('span');
            hint.className = 'confirm-nick-hint';
            hint.textContent = 'Or type your own name above.';
            wrap.appendChild(hint);
        }
    }

    function hideConfirmPanel() {
        $confirmPanel.classList.remove('visible');
        const wrap = document.getElementById('confirmNickChips');
        if (wrap) wrap.innerHTML = '';
        const kinds = document.getElementById('confirmKindChips');
        if (kinds) kinds.innerHTML = '';
        const err = document.getElementById('confirmFormError');
        if (err) { err.hidden = true; err.textContent = ''; }
    }

    async function ensureStudio() {
        if (studio) return studio;
        studio = new window.CardobotStudio({
            root: $studioRoot,
            assetBase: assetBase,
            frameUrl: assetBase + '/assets/img/01_Card.png',
            bgUrl: assetBase + '/assets/img/01_CardBGtexture.png',
            hideTools: true,
        });
        return studio;
    }

    async function openStudio(enableDrawing) {
        await openCardApp(enableDrawing ? 'draw' : 'viewer');
    }

    function promptRevise() {
        const note = window.prompt('What should change about the art?');
        if (!note) return;
        appendUserMessage(note);
        sendChat({ action: 'revise', user_message: note });
    }

    async function handleSaveFramed(downloadOnly, studioOverride, viewer) {
        try {
            setBusy(true);
            const s = studioOverride || await ensureStudio();
            const concept = conceptWithCredit(lastConcept || state.visualConcept || {});
            if (viewer && viewer.studio) {
                concept.show_credit = viewer.studio.getShowCredit();
            } else if (!Object.prototype.hasOwnProperty.call(concept, 'show_credit')) {
                concept.show_credit = true;
            }
            await s.setConcept(concept, lastStats || {});
            if (lastArtUrl && !studioOverride) await s.setArt(lastArtUrl);
            let png;
            try {
                png = await s.compositeDataUrl(2);
            } catch (corsErr) {
                console.warn('composite CORS, retry without crossOrigin art', corsErr);
                png = await s.compositeDataUrl(2);
            }
            const hsl = s.getHsl();
            const extras = viewer && viewer.getExtras ? viewer.getExtras() : {};
            if (downloadOnly) {
                const a = document.createElement('a');
                a.href = png;
                a.download = (lastConcept.nickname || 'cardobot') + '.png';
                a.click();
                setBusy(false);
                return;
            }
            const data = await postJson(basePath + '/api/export-card.php', Object.assign({
                session_id: state.sessionId,
                composite_png: png,
                drawing_data: s.getDrawingData(),
                hue: hsl.hue,
                saturation: hsl.saturation,
                lightness: hsl.lightness,
                stats: lastStats,
            }, extras));
            state.savedCardId = data.card_id;
            appendCardyMessage('Saved to your collection! You can view and download it anytime from your profile. *beep*', false);
            clearStaleSuggestions();
            const wrap = document.createElement('div');
            wrap.className = 'suggested-responses';
            const link = document.createElement('a');
            link.href = basePath + '/profile.php';
            link.className = 'suggestion-button';
            link.textContent = 'Open my collection';
            wrap.appendChild(link);
            $chatMessages.appendChild(wrap);
        } catch (err) {
            console.error(err);
            appendCardyMessage('I could not save that card. Want to try again?', false);
        } finally {
            setBusy(false);
        }
    }

    function resetUiForNewSession() {
        // Keep Cardy's GIF background and the existing chat history visible
        // (so the user has continuity), but clear interactive bits.
        clearStaleSuggestions();
        hideRenderButton();
        removeImageLoading();
        state.imageElement = null;
        state.savedCardId = null;
        state.imageTaskId = null;
        state.imageReadyHandled = false;
        state.imagePollAborted = true;
        if (state.imagePollHandle) {
            clearTimeout(state.imagePollHandle);
            state.imagePollHandle = null;
        }
    }

    // ============================================================
    //   Suggestion / free-text handling
    //   The wizard step decides what the user's input means.
    // ============================================================
    function pathValueFromText(lower) {
        const fast = t('path.fast', 'Yeah, make a card').toLowerCase();
        const longP = t('path.long', 'Make a detailed one').toLowerCase();
        const formP = t('path.form', 'Fill out a form').toLowerCase();
        const chatP = t('path.chat', 'Just chat for now').toLowerCase();
        if (lower === chatP || lower.includes('talk') || lower.includes('chat') || lower.includes('tell me more')
            || lower.includes('about you') || lower.includes('about the ship') || lower.includes('learn more')) {
            return 'chat';
        }
        if (lower === formP || lower.includes('fill out a form') || lower.includes('fill in a form')
            || lower.includes('use a form') || lower === 'form'
            || (lower.includes('form') && !lower.includes('transform') && !lower.includes('format'))) {
            return 'form';
        }
        if (lower === longP || lower.includes('remember') || lower.includes('detailed') || lower.includes('longer') || lower.includes('slow')) {
            return 'long';
        }
        if (lower === fast) return 'fast';
        return 'fast';
    }

    function handleSuggestion(text) {
        if (state.busy) return;
        clearStaleSuggestions();
        appendUserMessage(text);

        const step = state.step;
        const lower = text.toLowerCase();

        if (step === 'greeting' || state.awaitingOtherLocale) {
            if (!state.localePicked || state.awaitingOtherLocale) {
                const code = localeCodeFromChip(text);
                if (code === 'other') {
                    sendChat({ action: 'select_locale', value: 'other', user_message: text });
                    return;
                }
                if (code) {
                    sendChat({ action: 'select_locale', value: code, user_message: text });
                    return;
                }
                sendChat({ action: 'select_locale', user_message: text });
                return;
            }
            const value = pathValueFromText(lower);
            sendChat({ action: 'select_path', value, user_message: text });
            return;
        }

        if (state.mode === 'free_chat' || state.path === 'chat') {
            if (lower.includes('detailed') || lower.includes('longer')) {
                sendChat({ action: 'select_path', value: 'long', user_message: text });
                return;
            }
            if (lower.includes('make a card') || lower.includes("let's make") || lower.includes('start the card') || lower.includes('yeah, make')) {
                sendChat({ action: 'select_path', value: 'fast', user_message: text });
                return;
            }
            sendChat({ user_message: text });
            return;
        }

        if (step === 'confirm') {
            if (lower.includes('paint')) {
                sendChat({ action: 'confirm', user_message: text });
                return;
            }
            sendChat({ user_message: text });
            return;
        }

        if (step === 'reveal') {
            if (lower.includes('draw')) openCardApp('draw');
            else if (lower.includes('view')) openCardApp('viewer');
            else if (lower.includes('download')) handleSaveFramed(true);
            else if (lower.includes('change') || lower.includes('tweak')) promptRevise();
            else if (lower.startsWith('save')) handleSaveFramed(false);
            else handleMakeAnother();
            return;
        }

        // Agenda gather: server owns landing
        sendChat({ user_message: text });
    }

    function handleFreeText(text) {
        if (state.busy || !text) return;

        if (!state.introComplete) {
            if (state.greetingReady) {
                if ($introContinue) $introContinue.classList.add('hidden');
                proceedFromNotice();
            }
            return;
        }

        clearStaleSuggestions();
        appendUserMessage(text);

        const step = state.step;
        if (state.mode === 'free_chat' || state.path === 'chat') {
            sendChat({ user_message: text });
            return;
        }
        if (step === 'greeting' || state.awaitingOtherLocale) {
            if (!state.localePicked || state.awaitingOtherLocale) {
                const code = localeCodeFromChip(text);
                if (code && code !== 'other') {
                    sendChat({ action: 'select_locale', value: code, user_message: text });
                } else {
                    sendChat({ action: 'select_locale', user_message: text });
                }
                return;
            }
            sendChat({ action: 'select_path', value: pathValueFromText(text.toLowerCase()), user_message: text });
            return;
        }
        if (step === 'confirm') {
            sendChat({ user_message: text });
            return;
        }
        if (step === 'reveal') {
            sendChat({ user_message: text });
            return;
        }
        sendChat({ user_message: text });
    }

    // ============================================================
    //   Core: call /api/chat.php
    // ============================================================
    async function sendChat(payload) {
        if (state.busy) return;
        setBusy(true);
        showTypingIndicator();

        const body = Object.assign({ session_id: state.sessionId }, payload || {});

        try {
            const data = await postJson(basePath + '/api/chat.php', body);
            state.sessionId     = data.session_id || state.sessionId;
            state.step          = data.step || state.step;
            state.mode          = data.mode || state.mode;
            state.path          = data.path || state.path;
            if (data.locale) state.locale = data.locale;
            if (typeof data.locale_picked === 'boolean') state.localePicked = data.locale_picked;
            if (typeof data.awaiting_other_locale === 'boolean') state.awaitingOtherLocale = data.awaiting_other_locale;
            if (data.locale && data.locale !== (state.i18n && state._packLocale)) {
                state._packLocale = data.locale;
                loadLocalePack(data.locale);
            }
            state.readyToRender = !!data.ready_to_render;
            if (data.visual_concept) {
                state.visualConcept = data.visual_concept;
                lastConcept = data.visual_concept;
            }
            if (data.stats) lastStats = data.stats;

            hideTypingIndicator();

            if (state.step === 'confirm') {
                fillConfirmPanel(
                    data.visual_concept || lastConcept,
                    data.nickname_suggestions || []
                );
            } else if (state.step !== 'ready' && state.step !== 'rendering') {
                hideConfirmPanel();
            }

            if (data.message) {
                const el = appendCardyMessage(data.message, true);
                if (state.step === 'reveal') {
                    const textLen = (data.message || '').length;
                    const delay = Math.min(textLen * 26 + 400, 2000);
                    setTimeout(() => appendRevealActionsAfter(el), delay);
                } else if (state.step === 'confirm') {
                    // Panel owns Paint CTA + nickname chips
                    if (data.visual_concept && data.visual_concept.nickname) {
                        document.getElementById('confirmNickname').value = data.visual_concept.nickname;
                    }
                } else if (state.step !== 'rendering' && state.step !== 'studio' && state.step !== 'ready') {
                    appendSuggestionsAfter(el, data.suggestions);
                }
            }

            // Paint only on explicit auto_render (Paint CTA or revise).
            // Defer until after finally clears busy; calling handleRender while
            // sendChat still holds busy made it no-op (no spinner, no paint).
            if (data.auto_render) {
                hideConfirmPanel();
                hideRenderButton();
                clearStaleSuggestions();
                showImageLoading(data.message || '');
                state.pendingRender = true;
                state.pendingRenderStatus = data.message || '';
            } else {
                hideRenderButton();
            }

            if (data.tokens) {
                console.log('Cardy tokens:', data.tokens);
            }
        } catch (err) {
            console.error('chat API error:', err);
            hideTypingIndicator();
            appendError('Cardy had a glitch: ' + err.message);
        } finally {
            setBusy(false);
            $messageInput.value = '';
            autoResize();
            if (window.innerWidth > 768) $messageInput.focus();
            if (state.pendingRender) {
                state.pendingRender = false;
                handleRender();
            }
        }
    }

    // ============================================================
    //   Render flow
    // ============================================================
    async function handleRender() {
        if (!state.sessionId) return;
        if (state.renderInFlight) return;
        state.renderInFlight = true;
        hideRenderButton();

        // Tear down any previous image / poll state from a prior render
        // before starting a new one. (Make-Another also calls this path.)
        if (state.imagePollHandle) {
            clearTimeout(state.imagePollHandle);
            state.imagePollHandle = null;
        }
        state.imagePollAborted = true;
        state.imageReadyHandled = false;
        state.imageTaskId = null;
        if (state.imageElement && state.imageElement.parentNode) {
            state.imageElement.parentNode.removeChild(state.imageElement);
        }
        state.imageElement = null;

        showImageLoading(state.pendingRenderStatus || '');
        state.pendingRenderStatus = '';
        setBusy(true);

        try {
            const data = await postJson(basePath + '/api/render-image.php', { session_id: state.sessionId });
            if (data.status === 'completed' && data.image) {
                onImageReady(data.image);
                return;
            }
            if (data.status === 'generating' && data.task_id) {
                state.imageTaskId = data.task_id;
                pollImageStatus();
                return;
            }
            throw new Error('Unexpected response from render endpoint');
        } catch (err) {
            console.error('render error:', err);
            removeImageLoading();
            appendError('Could not start rendering: ' + err.message);
            showRenderButton();
        } finally {
            state.renderInFlight = false;
            setBusy(false);
        }
    }

    // Serial poller: schedules ONE setTimeout at a time and only schedules
    // the next tick after the current fetch resolves. This prevents the
    // setInterval pile-up that happens when an OpenAI image call takes
    // longer than the poll interval, which previously caused many copies
    // of the same image to render in the chat.
    function pollImageStatus() {
        const taskId = state.imageTaskId;
        if (!taskId) return;

        if (state.imagePollHandle) {
            clearTimeout(state.imagePollHandle);
            state.imagePollHandle = null;
        }
        state.imagePollAttempts = 0;
        state.imagePollAborted = false;
        state.imageReadyHandled = false;

        const maxAttempts = 90;

        const tick = async () => {
            if (state.imagePollAborted) return;
            state.imagePollAttempts++;

            try {
                const data = await postJson(basePath + '/api/image-status.php', { task_id: taskId });
                if (state.imagePollAborted) return;

                if (data.status === 'completed' && data.image) {
                    state.imagePollAborted = true;
                    state.imagePollHandle = null;
                    onImageReady(data.image);
                    return;
                }
                if (data.status === 'failed') {
                    state.imagePollAborted = true;
                    state.imagePollHandle = null;
                    removeImageLoading();
                    appendError('Image generation failed: ' + (data.error || 'unknown error'));
                    showRenderButton();
                    return;
                }
            } catch (err) {
                console.warn('poll error:', err.message);
                if (state.imagePollAttempts >= 3) {
                    state.imagePollAborted = true;
                    state.imagePollHandle = null;
                    removeImageLoading();
                    appendError('Paint status failed: ' + err.message);
                    showRenderButton();
                    return;
                }
            }

            if (state.imagePollAttempts >= maxAttempts) {
                state.imagePollAborted = true;
                state.imagePollHandle = null;
                removeImageLoading();
                appendError('Image is taking too long. Try Render again? *beep*');
                showRenderButton();
                return;
            }

            state.imagePollHandle = setTimeout(tick, 1500);
        };

        state.imagePollHandle = setTimeout(tick, 1500);
    }

    function onImageReady(image) {
        // Idempotent: even if multiple completion paths somehow fire, only
        // one image block + one follow-up sendChat happens per render.
        if (state.imageReadyHandled) return;
        state.imageReadyHandled = true;

        // Stop any further polling, just in case.
        state.imagePollAborted = true;
        if (state.imagePollHandle) {
            clearTimeout(state.imagePollHandle);
            state.imagePollHandle = null;
        }

        let url = image.url || '';
        if (!url && image.b64_json) url = 'data:image/png;base64,' + image.b64_json;
        if (!url) {
            appendError('Image came back empty. Try again?');
            showRenderButton();
            return;
        }
        showImage(url);
        // Server already moved step to reveal in render-image.php / image-status.php.
        sendChat({});
    }

    // ============================================================
    //   Save / make another
    // ============================================================
    async function handleSave(saveBtn) {
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }
        await handleSaveFramed(false);
        if (saveBtn) saveBtn.textContent = 'Saved!';
    }

    function handleMakeAnother() {
        if (state.busy) return;
        resetUiForNewSession();
        // Reset on the server gives us a brand-new session id and a fresh
        // greeting reply. We skip the intro story for repeat sessions.
        sendChat({ action: 'reset' });
    }

    // ============================================================
    //   Input handling
    // ============================================================
    function autoResize() {
        $messageInput.style.height = 'auto';
        const h = Math.min(Math.max($messageInput.scrollHeight, 56), 192);
        $messageInput.style.height = h + 'px';
    }
    $messageInput.addEventListener('input', autoResize);
    $messageInput.addEventListener('paste', () => setTimeout(autoResize, 10));

    $chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = $messageInput.value.trim();
        if (!text) return;
        handleFreeText(text);
    });

    // Mobile keyboard.
    if (window.visualViewport) {
        const adjust = () => {
            const vh = window.visualViewport.height;
            if (vh < window.innerHeight * 0.75) {
                setTimeout(() => $inputContainer.scrollIntoView({ behavior: 'smooth', block: 'end' }), 100);
            }
        };
        window.visualViewport.addEventListener('resize', adjust);
        $messageInput.addEventListener('focus', () => setTimeout(adjust, 250));
    }

    // ============================================================
    //   Wire up the page
    // ============================================================
    if ($introContinue) {
        $introContinue.querySelector('.continue-button').addEventListener('click', proceedFromIntro);
    }
    $renderButton.addEventListener('click', handleRender);

    document.getElementById('confirmPaintBtn').addEventListener('click', () => {
        const patch = readConfirmPatch();
        if (!validateConfirmForm(patch)) return;
        hideConfirmPanel();
        sendChat({ action: 'confirm', user_message: 'Paint it!', concept_patch: patch });
    });
    document.getElementById('confirmUpdateBtn').addEventListener('click', () => {
        const patch = readConfirmPatch();
        if (isFormPath() && !validateConfirmForm(patch)) return;
        sendChat({ action: 'update_concept', concept_patch: patch });
    });
    document.getElementById('studioSaveBtn').addEventListener('click', () => handleSaveFramed(false));
    document.getElementById('studioDownloadBtn').addEventListener('click', () => handleSaveFramed(true));
    document.getElementById('studioBackBtn').addEventListener('click', () => {
        $studioPanel.classList.remove('visible');
    });
})();
</script>
<?php
console_end(true);
?>
