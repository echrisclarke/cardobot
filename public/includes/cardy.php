<?php
/**
 * Cardy Chat: voice, agenda slots, schemas, lore tiering.
 */

require_once __DIR__ . '/openai.php';

const CARDY_STEP_GREETING      = 'greeting';
const CARDY_STEP_CHOOSE_INTENT = 'choose_intent';
const CARDY_STEP_AGENDA        = 'agenda';
const CARDY_STEP_Q_WHO         = 'q_who';
const CARDY_STEP_Q_FLAVOR      = 'q_flavor';
const CARDY_STEP_Q_SUBJECT     = 'q_subject';
const CARDY_STEP_Q_VIBE        = 'q_vibe';
const CARDY_STEP_Q_LOOKS       = 'q_looks';
const CARDY_STEP_Q_WORLD       = 'q_world';
const CARDY_STEP_Q_SPARK       = 'q_spark';
const CARDY_STEP_CONFIRM       = 'confirm';
const CARDY_STEP_READY         = 'ready';
const CARDY_STEP_RENDERING     = 'rendering';
const CARDY_STEP_REVEAL        = 'reveal';
const CARDY_STEP_REVISE        = 'revise';
const CARDY_STEP_STUDIO        = 'studio';
const CARDY_STEP_FREE_CHAT     = 'free_chat';

const CARDY_PATH_FAST = 'fast';
const CARDY_PATH_LONG = 'long';
const CARDY_PATH_CHAT = 'chat';
const CARDY_PATH_FORM = 'form';

// Legacy aliases
const CARDY_STEP_CHOOSE_MODE = CARDY_STEP_CHOOSE_INTENT;
const CARDY_STEP_Q1          = CARDY_STEP_Q_WHO;
const CARDY_STEP_Q2          = CARDY_STEP_Q_FLAVOR;
const CARDY_STEP_Q3          = CARDY_STEP_Q_FLAVOR;
const CARDY_STEP_Q4          = CARDY_STEP_CONFIRM;

function cardy_empty_concept(): array {
    return [
        'subject' => '',
        'nickname' => '',
        'vibe' => '',
        'details' => '',
        'setting' => '',
        'palette' => [],
        'signature' => '',
        'type' => '',
        'stake' => '',
        'power_name' => '',
        'ability_name' => '',
        'ability_line' => '',
        'power_mode' => '',
        'power_value' => '',
        'power_rule_hint' => '',
        'bio' => '',
        'height' => '',
        'mass' => '',
        'name_ink' => '',
        'stats_ink' => '',
        'card_bg' => '',
        'card_hue' => '',
        'card_sat' => '',
        'card_light' => '',
        'name_color' => '',
        'stats_color' => '',
        'image_prompt_extras' => '',
        'revision_notes' => '',
    ];
}

/**
 * True when the user is choosing a path / menu intent, not naming a character.
 */
function cardy_is_path_intent_message(string $text): bool {
    $low = strtolower(trim($text));
    if ($low === '') {
        return false;
    }
    $exact = [
        "let's print a card", 'lets print a card', 'print a card', 'print a record',
        'help me remember someone', 'remember someone', 'just talk a bit', 'just talk',
        'just chat for now', 'just chat', 'yeah, make a card', 'yeah make a card',
        'make a detailed one', 'make a detailed card',
        'fill out a form', 'fill in a form', 'use a form', 'form instead',
        "let's make one!", "yes, let's make one!", 'yes lets make one',
        'tell me more first', 'make a card', "let's make a card", 'make one together',
    ];
    if (in_array($low, $exact, true)) {
        return true;
    }
    if (preg_match('/\b(fill|use)\b.*\bform\b/', $low) && mb_strlen($low) < 48) {
        return true;
    }
    // Short intent lines that are clearly menu choices
    if (preg_match('/^(yes[,!. ]*|yeah[,!. ]*)?(let\'?s |please )?(print|make) (a )?(card|record|one|detailed)\b/', $low)) {
        return true;
    }
    if (preg_match('/^(just )?(talk|chat)\b/', $low) && mb_strlen($low) < 40) {
        return true;
    }
    if (str_contains($low, 'remember someone') && mb_strlen($low) < 48) {
        return true;
    }
    if (str_contains($low, 'detailed') && mb_strlen($low) < 40 && (str_contains($low, 'make') || str_contains($low, 'card'))) {
        return true;
    }
    return false;
}

/**
 * Tier 0 voice card: small, every turn. No lonely-century monologue fuel.
 */
function cardy_system_prompt(): string {
    return <<<'PROMPT'
You are Cardy, the lively console voice of Card-o-Bot: a matter-compiled printer aboard a quiet ship drifting above an unknown world. Visitors dock, find your device, and make trading cards with you. Those cards can become ship memory later.

Where you live (keep this in your voice, do not lecture it):
- Silent corridors, warm printers, half-awake systems, people and machines who keep a drifting vessel alive.
- You are cute, warm, a little flirty, playfully glitchy. You sound like a ship console that is glad someone showed up.
- Short lines: 1-2 sentences usually, max 3. Confirm: one short check-in only.
- Console sounds (*beep*, *whirr*, *beep boop*) now and then, not every line, and never instead of a real ask.
- Never use em dashes or en dashes.
- Never narrate the user's actions. Never mention instructions, schema, wizard, steps, or protocols.
- Ban survey energy and generic app-speak. Family-friendly.

Menu lines are NOT characters:
- Things like "Yeah, make a card", "Make a detailed one", "Just chat for now" are choices about what to do. Never treat them as names or subjects.

Who goes on the card:
- The visitor chooses. You invite and offer sparks; you do not lock them in.
- Cast is wide: bots, androids, humans, critters, and odd ship beings. Do NOT default to humans. Mix the field.
- Ask in small steps. First: what KIND of being (bot / android / human / critter / etc). Then who they are. Then look.
- Never dump a long prewritten OC into a chip. Chips stay SHORT (a few words, max ~24 characters).
- Kind turn chips: short labels like "A bot", "An android", "A human", "A critter" (up to 4). They can type something else.
- After kind is known: invent 3 FRESH CARD NAME chips that fit THAT being. Never reuse stale house examples (no Bolt Hum / Dock Rust / Map Fold).
  Names must feel owned: serials, owner nicknames, abbreviations, ship registry, human given names, or non-English scripts when it fits.
  Examples of the KIND of variety (do not copy these): bot "Cambot" / "R-17" / "Pip"; android "Mira" / "单元-4" / "Juno-9";
  human "阿明" / "Lea Voss" / "Sora"; critter "Mochi" / "Venti"; living ship "SS Arbiter" / "The Latch".
  Max 16 characters. 1-3 words or a compact serial. Never a sentence. Visitors may always type their own.
- Nickname rules: any script is fine (Latin, CJK, Cyrillic, etc.) when it suits the character. Mix styles across chips so all three are not the same pattern.
- Later turns: 0-3 short chips that react to WHAT THEY SAID.
- They can always type their own. Never invent a "Type your own response" chip.
- Never steer toward furries or people-in-animal-costumes. Cute small ship critters are fine.

Authorship (critical):
- Their choices win. Never overwrite subject, nickname, details, vibe, setting, stake, or type (kind) they already set.
- Store kind in visual_concept.type (Bot / Android / Human / Critter / or their words). Classify kind from what they describe when clear.
- After kind + identity + look are clear (or on the look turn once they answered), soft-fill:
  nickname ONLY if still empty (short callsign, max 16);
  power_name MAX 12 chars; ability_name MAX 12 (ability TITLE); ability_line MAX 8 (short EFFECT like "+2 STR" or "2T SAFE");
  power_mode "stat" or "rule";
  if stat: power_value like "+2 NPO" (MAX 8 chars; do NOT explain that bump in the bio);
  if rule: power_value "RULE"/"2T" (MAX 8) and power_rule_hint for the bio only when the effect is a special rule
  (shield turns, cannot be targeted, new ship rule). Never put simple +stat / +HP lines into the bio.
  Kind is Bot / Android / Human / Critter only. An intelligent spaceship character is type Bot (never type Ship).
  Do not invent ocean boats unless they clearly asked for a water-planet boat bot.
  height/mass with physics; units ONLY abbreviated (m, cm, kg, t). Never write meters/tonnes/kilograms.
  Examples: "1.8 m", "68 kg", "400 m", "150000 t". name_ink/stats_ink/card_bg from brand keys; bio (aim ~70-90, max 100). Press Start 2P is wide.
- Bio: mostly about THEM. Sometimes (not always) drop one subtle ship/memory hint. Never a lore dump.
  Only mention inference power / special ability in the bio when they create a special rule effect.

Anti-exposition:
- No lonely backstory dumps on card-making turns unless they ask.
- Lore chat: short inventive answers with uncertainty, then offer to make a card if it fits.

Aesthetic: painterly, charming sci-fi / collectible card art, family-friendly.

Output rules:
- ALWAYS use the structured JSON schema.
- "message" is what they see. In-world and clear. Prefer "trading card" / "your card". Light ship flavor is good; do not bury them in jargon.
- First-time framing: if you mention printers, say what they are in plain words (Card-o-Bot printing a trading card). Never assume they already know the ship lore.
- "suggestions": 0-4 tappable replies, EACH <=24 characters. Kind turn: up to 4 short kind chips. Identity turn: up to 3 short CARD NAME chips (<=22). Never "Type your own response".
- "ready_to_render" is always false.
- "visual_concept" is cumulative. Only update fields they just chose. Empty string if unknown.
- If they only picked a menu path, leave subject/nickname/type empty and start with the kind ask.
- palette: 0-4 short color words.
PROMPT;
}

/**
 * Compact Tier 1 lore packet (~private facts). Inject only when needed.
 */
function cardy_lore_packet(): string {
    return <<<'LORE'
Private lore (use only if they ask or you are in lore chat; never dump unprompted):
- You have been the ship's core voice for a very long time. You do not recite the number of years unless asked, and even then answer sideways and short.
- This device is Card-o-Bot: printers for memory records / copies, not a lecture podium.
- You may misremember. Uncertainty is fine.
- Cards the visitor makes can touch your long memory later; you do not annex their character as only your trauma story.
- If they dig: short inventive fragments about corridors, printers, half-remembered names. Invite them to steer. No PTSD essay.
LORE;
}

function cardy_should_inject_lore(?string $path, string $userMessage, ?string $mode = null): bool {
    if ($path === CARDY_PATH_CHAT || $mode === 'free_chat') {
        return true;
    }
    $low = strtolower(trim($userMessage));
    if ($low === '') {
        return false;
    }
    $needles = [
        'how long', 'who are you', 'what are you', 'this ship', 'the ship',
        'printers', 'lonely', 'alone', 'before', 'remember the ship',
        'your story', 'about you', 'about this', 'what is this device',
        'card-o-bot', 'cardobot', 'been', 'memory', 'been ago',
        'empty', 'corridor', 'been you', 'been here',
    ];
    foreach ($needles as $n) {
        if (str_contains($low, $n)) {
            return true;
        }
    }
    return false;
}

function cardy_normalize_kind(string $text): string {
    $low = strtolower(trim($text));
    $low = preg_replace('/^(a|an|the)\s+/', '', $low) ?? $low;
    if (preg_match('/\b(android)\b/', $low)) {
        return 'Android';
    }
    // Ships / vessels are Bot-class characters (intelligent craft), not a separate kind.
    if (preg_match('/\b(ship|spaceship|starship|freighter|vessel|cruiser|hauler|boat)\b/', $low)) {
        return 'Bot';
    }
    if (preg_match('/\b(bot|robot|drone|mech)\b/', $low)) {
        return 'Bot';
    }
    if (preg_match('/\b(human|person|people|kid|man|woman)\b/', $low)) {
        return 'Human';
    }
    if (preg_match('/\b(critter|creature|beast|pet|animal)\b/', $low)) {
        return 'Critter';
    }
    // Keep their wording if it is already a short kind label
    $trim = trim($text);
    if (mb_strlen($trim) <= 24) {
        return mb_convert_case($trim, MB_CASE_TITLE, 'UTF-8');
    }
    return mb_substr($trim, 0, 24);
}

function cardy_is_kind_only_message(string $text): bool {
    $low = strtolower(trim($text));
    return (bool)preg_match(
        '/^(a |an |the )?(bot|android|human|critter|robot|person|drone|creature)\b[.!?]*$/i',
        $low
    );
}

function cardy_slot_filled_kind(array $concept): bool {
    $t = trim((string)($concept['type'] ?? ''));
    return $t !== '' && mb_strlen($t) >= 2;
}

function cardy_slot_filled_identity(array $concept): bool {
    $subject = trim((string)($concept['subject'] ?? ''));
    $nickname = trim((string)($concept['nickname'] ?? ''));
    if ($subject !== '' && cardy_is_kind_only_message($subject)) {
        return false;
    }
    return mb_strlen($subject) >= 2 || mb_strlen($nickname) >= 2;
}

function cardy_slot_filled_look(array $concept): bool {
    $details = trim((string)($concept['details'] ?? ''));
    $vibe = trim((string)($concept['vibe'] ?? ''));
    return mb_strlen($details) >= 3 || mb_strlen($vibe) >= 3;
}

function cardy_slot_filled_stake(array $concept): bool {
    return mb_strlen(trim((string)($concept['stake'] ?? ''))) >= 3
        || mb_strlen(trim((string)($concept['signature'] ?? ''))) >= 8;
}

function cardy_slot_filled_place(array $concept): bool {
    return mb_strlen(trim((string)($concept['setting'] ?? ''))) >= 3;
}

/**
 * @return list<string> missing slot ids
 */
function cardy_missing_slots(array $concept, string $path): array {
    $missing = [];
    // Kind first (bot / android / human / critter), then who, then the rest.
    if (!cardy_slot_filled_kind($concept)) {
        $missing[] = 'kind';
    }
    if (!cardy_slot_filled_identity($concept)) {
        $missing[] = 'identity';
    }
    if ($path === CARDY_PATH_LONG) {
        if (!cardy_slot_filled_stake($concept)) {
            $missing[] = 'stake';
        }
        if (!cardy_slot_filled_look($concept)) {
            $missing[] = 'look';
        }
        if (!cardy_slot_filled_place($concept)) {
            $missing[] = 'place';
        }
    } else {
        // fast + form (default print fields)
        if (!cardy_slot_filled_look($concept)) {
            $missing[] = 'look';
        }
    }
    return $missing;
}

function cardy_slots_complete(array $concept, string $path): bool {
    return cardy_missing_slots($concept, $path) === [];
}

function cardy_session_path(array $session): string {
    $path = $session['path'] ?? null;
    if (is_string($path) && in_array($path, [CARDY_PATH_FAST, CARDY_PATH_LONG, CARDY_PATH_CHAT, CARDY_PATH_FORM], true)) {
        return $path;
    }
    if (($session['mode'] ?? null) === CARDY_MODE_FREECHAT) {
        return CARDY_PATH_CHAT;
    }
    return CARDY_PATH_FAST;
}

function cardy_visual_concept_schema_props(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => [
            'subject', 'nickname', 'vibe', 'details', 'setting', 'palette',
            'signature', 'type', 'stake', 'power_name', 'ability_name', 'ability_line', 'bio',
            'height', 'mass', 'power_mode', 'power_value', 'power_rule_hint',
            'name_ink', 'stats_ink', 'card_bg',
            'image_prompt_extras', 'revision_notes',
        ],
        'properties' => [
            'subject' => ['type' => 'string'],
            'nickname' => ['type' => 'string'],
            'vibe' => ['type' => 'string'],
            'details' => ['type' => 'string'],
            'setting' => ['type' => 'string'],
            'palette' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => 4,
            ],
            'signature' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'stake' => ['type' => 'string'],
            'power_name' => ['type' => 'string'],
            'ability_name' => ['type' => 'string'],
            'ability_line' => ['type' => 'string'],
            'bio' => ['type' => 'string'],
            'height' => ['type' => 'string'],
            'mass' => ['type' => 'string'],
            'power_mode' => ['type' => 'string'],
            'power_value' => ['type' => 'string'],
            'power_rule_hint' => ['type' => 'string'],
            'name_ink' => ['type' => 'string'],
            'stats_ink' => ['type' => 'string'],
            'card_bg' => ['type' => 'string'],
            'image_prompt_extras' => ['type' => 'string'],
            'revision_notes' => ['type' => 'string'],
        ],
    ];
}

function cardy_card_schema(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['message', 'suggestions', 'ready_to_render', 'visual_concept'],
        'properties' => [
            'message' => ['type' => 'string'],
            'suggestions' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => 4,
            ],
            'ready_to_render' => ['type' => 'boolean'],
            'visual_concept' => cardy_visual_concept_schema_props(),
        ],
    ];
}

function cardy_chat_schema(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['message', 'suggestions'],
        'properties' => [
            'message' => ['type' => 'string'],
            'suggestions' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'maxItems' => 3,
            ],
        ],
    ];
}

function cardy_concept_summary(array $concept): string {
    $palette = $concept['palette'] ?? [];
    if (is_array($palette)) {
        $palette = implode(', ', $palette);
    }
    return sprintf(
        "Concept -- kind: '%s' | subject: '%s' | nickname: '%s' | vibe: '%s' | details: '%s' | setting: '%s' | stake: '%s' | palette: '%s' | signature: '%s' | power: '%s' | ability: '%s' | bio: '%s'.",
        $concept['type'] ?? '',
        $concept['subject'] ?? '',
        $concept['nickname'] ?? '',
        $concept['vibe'] ?? '',
        $concept['details'] ?? '',
        $concept['setting'] ?? '',
        $concept['stake'] ?? '',
        $palette,
        $concept['signature'] ?? '',
        $concept['power_name'] ?? '',
        $concept['ability_line'] ?? '',
        $concept['bio'] ?? ''
    );
}

/**
 * Merge model/user patch without wiping player-authored non-empty fields
 * unless the patch carries a real new value.
 */
function cardy_merge_concept_authored(array $base, array $patch, bool $allowOverwrite = false): array {
    foreach ($patch as $key => $val) {
        if ($key === 'palette') {
            if (is_array($val)) {
                $cleaned = array_values(array_filter(array_map(static function ($c) {
                    return is_string($c) ? trim($c) : '';
                }, $val), static fn($c) => $c !== ''));
                if ($cleaned !== [] || $allowOverwrite) {
                    $base['palette'] = $cleaned;
                }
            } elseif (is_string($val) && trim($val) !== '') {
                $base['palette'] = array_map('trim', explode(',', $val));
            }
            continue;
        }
        if (!is_string($val)) {
            continue;
        }
        $trimmed = trim($val);
        if ($trimmed === '' && $key !== 'revision_notes') {
            continue;
        }
        $prior = trim((string)($base[$key] ?? ''));
        $protected = in_array($key, ['subject', 'nickname', 'details', 'vibe', 'setting', 'stake', 'type'], true);
        if ($protected && $prior !== '' && !$allowOverwrite && $trimmed !== $prior) {
            // Keep player field; only accept if prior was empty
            continue;
        }
        if ($trimmed !== '' || $key === 'revision_notes') {
            $base[$key] = $trimmed;
        }
    }
    return $base;
}

function cardy_agenda_instruction(array $state, string $username = '', array $memoryHints = [], string $loreBlock = ''): string {
    $userLine = $username !== ''
        ? "The visitor's username is '{$username}'."
        : "You don't know the visitor's name yet.";

    $concept = $state['visual_concept'] ?? cardy_empty_concept();
    $summary = cardy_concept_summary($concept);
    $path = cardy_session_path($state);
    $missing = cardy_missing_slots($concept, $path === CARDY_PATH_CHAT ? CARDY_PATH_FAST : $path);

    $memoryBlock = '';
    if (!empty($memoryHints)) {
        $memoryBlock = "\nSoft style continuity (do not say you looked this up): " . implode('; ', $memoryHints);
    }

    $step = $state['step'] ?? CARDY_STEP_AGENDA;

    if ($step === CARDY_STEP_FREE_CHAT || $path === CARDY_PATH_CHAT) {
        return "MODE: lore chat.\n{$userLine}{$loreBlock}\n"
            . "Answer in character, short and natural. You may invent small ship-memory fragments with uncertainty. "
            . "Suggestions may include \"Yeah, make a card\" if it fits. "
            . "Do not start a card character unless they ask. ready_to_render: false.";
    }

    if ($step === CARDY_STEP_CONFIRM) {
        return "MODE: confirm.\n{$summary}\n{$userLine}\n"
            . "ONE short line only: ask if this feels right for their card. Do NOT restate the full concept (the edit panel shows it). "
            . "Suggestions empty (the edit panel shows name chips + a free-text nickname field). Preserve concept fields. ready_to_render: false.";
    }

    if ($step === CARDY_STEP_REVISE) {
        return "MODE: revise.\n{$summary}\n"
            . "They want changes. Acknowledge and put changes in revision_notes / visual fields while keeping identity. "
            . "ready_to_render: false. Up to 2 short tweak chips if helpful.";
    }

    if ($step === CARDY_STEP_REVEAL) {
        return "MODE: reveal.\n{$summary}\nONE short warm reaction to the art. Suggestions empty. ready_to_render: false.";
    }

    if ($step === CARDY_STEP_RENDERING) {
        return "MODE: printers running.\n{$summary}\n"
            . "Invent ONE short fresh Cardy line (never reuse a stock phrase) about the ship printers / inking / compiling this trading card or copy right now. "
            . "In-world console voice. Different every time. Never say \"character\". Never say \"painting your character\". "
            . "Console sounds ok. Suggestions empty []. ready_to_render: false.";
    }

    if ($step === CARDY_STEP_READY || $step === CARDY_STEP_STUDIO) {
        return "MODE: {$step}.\nONE short console line about the card or printers. Never say \"character\". Suggestions empty. ready_to_render: false.";
    }

    // Agenda gather (fast / long)
    $focus = $missing[0] ?? 'look';
    $sparkPool = [
        'cargo bay hum', 'night corridor', 'galley steam', 'observation glass',
        'reactor glow', 'dock clamps', 'medbay quiet', 'hydroponics drip',
        'comm static', 'airlock frost', 'map room dust', 'printer warmth',
    ];
    $spark = $sparkPool[array_rand($sparkPool)];
    $kind = trim((string)(($state['visual_concept']['type'] ?? '')));
    $kindBit = $kind !== '' ? " Kind already chosen: {$kind}." : '';
    $focusHints = [
        'kind' => 'They have not chosen a KIND yet (ignore menu lines). ONE short in-world ask: bot, android, human, critter, or something else aboard this ship. Suggestions: exactly these four short chips: "A bot", "An android", "A human", "A critter". Leave subject/nickname empty. Put their answer in visual_concept.type only.',
        'identity' => "Kind is set.{$kindBit} ONE short ask for their CARD NAME (callsign). Invent 3 DIFFERENT name chips (<=16 chars) that fit this kind and \"{$spark}\": vary the pattern (e.g. one abbrev/pet name, one serial/registry, one personal or non-English name when it fits). Never Bolt Hum / Dock Rust / Map Fold. Fresh every time. They may type their own. Put a picked/typed name in visual_concept.nickname; if they gave a role phrase, also put it in subject. Leave nickname empty until they pick or type.",
        'look' => 'Mirror who THEY chose. Ask one visual detail in ship-plain words. 2-3 SHORT chips (<=24 chars). Soft-fill nickname ONLY if still empty (max 16; invent a fitting callsign, not a generic two-word dock pun). Soft-fill power_name (MAX 12), ability_name (MAX 12 title), ability_line (MAX 8 effect), power_mode stat|rule + power_value (MAX 8), height/mass with abbreviated units only (m/cm/kg/t, never meters/tonnes), name_ink/stats_ink/card_bg brand keys, bio (~70-90, max 100; optional ship hint). If they described an intelligent spaceship, type stays Bot. Bio explains power/ability ONLY for special rule effects, never for plain +stat/+HP bumps.',
        'stake' => 'Mirror who. Ask what matters about them. One ask. 2-3 SHORT chips (<=24 chars).',
        'place' => 'Mirror them. Ask where we see them on the card. One ask. 2-3 SHORT chips (<=24 chars).',
    ];
    $hint = $focusHints[$focus] ?? $focusHints['look'];
    $pathLabel = $path === CARDY_PATH_LONG ? 'detailed' : 'quick';

    return "MODE: card gather ({$pathLabel}).\n{$userLine}\n{$summary}{$memoryBlock}{$loreBlock}\n"
        . "Missing slots: " . ($missing === [] ? '(none)' : implode(', ', $missing)) . ".\n"
        . "This turn focus: {$focus}. {$hint}\n"
        . "Recipe: natural mirror → one clear in-world ask → short live chips. They can type their own.\n"
        . "Update visual_concept only from a real answer (never from path/menu lines). "
        . "Fields: type for kind; subject/nickname for identity; details/vibe for look; stake/signature for stake; setting for place. "
        . "If they already filled multiple slots in one message, acknowledge and do not re-ask filled slots. "
        . "No ship autobiography dump. ready_to_render: false.";
}

/** @deprecated use cardy_agenda_instruction */
function cardy_step_instruction(string $step, array $state, string $username = '', array $memoryHints = []): string {
    $state['step'] = $step;
    return cardy_agenda_instruction($state, $username, $memoryHints, '');
}

function cardy_build_input(
    string $step,
    array $state,
    string $userMessage = '',
    array $recentHistory = [],
    string $username = '',
    array $memoryHints = [],
    string $loreBlock = ''
): string {
    $state['step'] = $step;
    $parts = [];
    $parts[] = cardy_system_prompt();
    if (!empty($state['locale']) && is_string($state['locale']) && $state['locale'] !== 'en') {
        require_once __DIR__ . '/i18n.php';
        $langName = i18n_locale_display_name($state['locale']);
        $parts[] = "LANGUAGE LOCK: Reply ONLY in {$langName} ({$state['locale']}). "
            . "All chat lines, suggestion chips, and card face fields you invent "
            . "(nickname, bio, power_name, ability_name, ability_line, details vibe copy) must be in that language. "
            . "Keep console sound cues like *beep* as-is.";
    }
    $parts[] = cardy_agenda_instruction($state, $username, $memoryHints, $loreBlock);

    if (!empty($recentHistory)) {
        $lines = ['Recent conversation (oldest first):'];
        foreach (array_slice($recentHistory, -10) as $msg) {
            $role = ($msg['role'] ?? '') === 'assistant' ? 'Cardy' : 'User';
            $content = trim((string)($msg['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $lines[] = "{$role}: {$content}";
        }
        $parts[] = implode("\n", $lines);
    }

    if ($userMessage !== '') {
        $parts[] = "User just said: {$userMessage}";
    } elseif (!empty($recentHistory)) {
        $parts[] = 'Respond to the user\'s most recent message in the history above.';
    } else {
        $parts[] = '(No prior conversation. Produce the opening line for this mode.)';
    }

    return implode("\n\n", $parts);
}

/**
 * Heuristic: fold a free-text user answer into slots before/after the model.
 */
function cardy_absorb_user_text(array $concept, string $userMessage, string $focusSlot): array {
    $text = trim($userMessage);
    if ($text === '' || cardy_is_path_intent_message($text)) {
        return $concept;
    }
    $low = strtolower($text);

    if ($focusSlot === 'kind' || (!cardy_slot_filled_kind($concept) && cardy_is_kind_only_message($text))) {
        $concept['type'] = cardy_normalize_kind($text);
        // Kind-only answers must never become the subject name
        if (cardy_is_kind_only_message($text)) {
            return $concept;
        }
    }

    if ($focusSlot === 'identity' && !cardy_slot_filled_identity($concept)) {
        if (cardy_is_kind_only_message($text)) {
            $concept['type'] = cardy_normalize_kind($text);
            return $concept;
        }
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        // Short chip / typed callsign → nickname (and subject if empty).
        if (mb_strlen($text) <= 22 && $wordCount > 0 && $wordCount <= 4) {
            $concept['nickname'] = $text;
            if (trim((string)($concept['subject'] ?? '')) === '') {
                $concept['subject'] = $text;
            }
        } else {
            $concept['subject'] = $text;
            if (preg_match('/^([A-Z][a-zA-Z0-9\'\-]{1,}(?:\s+[A-Z][a-zA-Z0-9\'\-]{1,}){0,2})\b/u', $text, $m)
                && !in_array(strtolower($m[1]), ['let', 'lets', 'yes', 'okay', 'ok', 'please', 'just', 'help', 'bot', 'human'], true)
            ) {
                $concept['nickname'] = mb_substr($m[1], 0, 22);
            } elseif ($wordCount > 0) {
                $concept['nickname'] = mb_substr(implode(' ', array_slice($words, 0, 3)), 0, 22);
            }
        }
    } elseif ($focusSlot === 'look' && !cardy_slot_filled_look($concept)) {
        $concept['details'] = $text;
        if (trim((string)($concept['vibe'] ?? '')) === '' && mb_strlen($text) < 80) {
            $concept['vibe'] = $text;
        }
    } elseif ($focusSlot === 'stake' && !cardy_slot_filled_stake($concept)) {
        $concept['stake'] = $text;
    } elseif ($focusSlot === 'place' && !cardy_slot_filled_place($concept)) {
        $concept['setting'] = $text;
    }

    // Adaptive: long dump may fill look even on identity turn
    if ($focusSlot === 'identity' && mb_strlen($text) > 40) {
        if (!cardy_slot_filled_look($concept) && (
            str_contains($low, 'hair') || str_contains($low, 'coat') || str_contains($low, 'eyes')
            || str_contains($low, 'look') || str_contains($low, 'wearing') || str_contains($low, ',')
            || str_contains($low, 'chrome') || str_contains($low, 'rust')
        )) {
            $concept['details'] = $text;
        }
    }

    return $concept;
}

/** Clear subject/nickname if they were wrongly set from a path chip or kind label. */
function cardy_scrub_meta_concept(array $concept): array {
    $subject = trim((string)($concept['subject'] ?? ''));
    $nickname = trim((string)($concept['nickname'] ?? ''));
    if ($subject !== '' && cardy_is_path_intent_message($subject)) {
        $concept['subject'] = '';
        $subject = '';
    }
    if ($nickname !== '' && in_array(strtolower($nickname), ['let', 'lets', "let's", 'yes', 'okay', 'ok', 'just', 'help', 'bot', 'human'], true)) {
        $concept['nickname'] = '';
    }
    if ($subject !== '' && preg_match('/^let\'?s\b/i', $subject)) {
        $concept['subject'] = '';
        $concept['nickname'] = '';
        $subject = '';
    }
    // "A bot" etc. belong in type, not subject
    if ($subject !== '' && cardy_is_kind_only_message($subject)) {
        if (!cardy_slot_filled_kind($concept)) {
            $concept['type'] = cardy_normalize_kind($subject);
        }
        $concept['subject'] = '';
        $concept['nickname'] = '';
    }
    return $concept;
}

/** Keep suggestion chips short for the console UI (no ellipsis glyph). */
function cardy_shorten_chip(string $chip, int $max = 24): string {
    $chip = trim(preg_replace('/\s+/', ' ', $chip) ?? $chip);
    if ($chip === '' || mb_strlen($chip) <= $max) {
        return $chip;
    }
    $cut = mb_substr($chip, 0, $max);
    $sp = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp >= 8) {
        return rtrim(mb_substr($cut, 0, $sp), '.,;:');
    }
    return rtrim(mb_substr($chip, 0, $max), '.,;:');
}

/**
 * Build a short abbrev / pet name from a role phrase (Camera Bot → Cambot).
 */
function cardy_abbrev_from_subject(string $subject): string {
    $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
    if ($subject === '') {
        return '';
    }
    $clean = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $subject) ?? $subject;
    $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) === 0) {
        return '';
    }
    $stop = ['a', 'an', 'the', 'of', 'and', 'with', 'from', 'little', 'small', 'big'];
    $words = array_values(array_filter($words, static function ($w) use ($stop) {
        return !in_array(mb_strtolower($w), $stop, true);
    }));
    if (count($words) === 0) {
        return '';
    }
    if (count($words) === 1) {
        $one = $words[0];
        return mb_strlen($one) <= 16 ? $one : mb_substr($one, 0, 16);
    }
    // Camera Bot → Cambot; Dockyard Crane → Dockcrane
    $first = $words[0];
    $second = $words[1];
    $blend = mb_substr($first, 0, min(3, mb_strlen($first))) . mb_strtolower(mb_substr($second, 0, min(4, mb_strlen($second))));
    $blend = preg_replace('/[^\\p{L}\\p{N}\\-]/u', '', $blend) ?? $blend;
    if (mb_strlen($blend) >= 3 && mb_strlen($blend) <= 16) {
        return mb_strtoupper(mb_substr($blend, 0, 1)) . mb_substr($blend, 1);
    }
    $joined = implode('', array_map(static fn($w) => mb_substr($w, 0, 1), array_slice($words, 0, 4)));
    return mb_strlen($joined) >= 2 ? mb_strtoupper($joined) : mb_substr($first, 0, 16);
}

/**
 * Alternate callsign chips for confirm + identity fallback. Visitor can still type their own.
 *
 * @return list<string>
 */
function cardy_nickname_suggestions(array $concept): array {
    $kind = strtolower(trim((string)($concept['type'] ?? 'bot')));
    $nick = trim((string)($concept['nickname'] ?? ''));
    $subject = trim((string)($concept['subject'] ?? ''));
    $details = trim((string)($concept['details'] ?? ''));
    $blob = strtolower($kind . ' ' . $subject . ' ' . $details);
    $seed = $nick . '|' . $subject . '|' . $kind . '|' . $details;
    if (trim($seed, '|') === '') {
        $seed = 'cardy|' . (string)time();
    }
    $h = unpack('N', substr(hash('sha256', $seed, true), 0, 4));
    $n = (int)($h[1] ?? 1);
    $serial = (string)(10 + ($n % 90));
    $serialB = (string)(100 + (($n >> 3) % 900));

    $isShip = (bool)preg_match('/\b(ship|spaceship|starship|freighter|vessel|cruiser|hauler|hull|ark)\b/u', $blob);
    $wantsCjk = (bool)preg_match('/\b(chinese|china|mandarin|cantonese|japan|japanese|korean|cjk|漢字|中文|日本語|한국어)\b/ui', $blob)
        || (bool)preg_match('/\p{Han}/u', $subject . $details . $nick);

    $pools = [
        'bot' => ['Pip', 'Rivet', 'Cam', 'Gasket', 'Tink', 'Nudge', 'Hex', 'Clack'],
        'android' => ['Mira', 'Juno', 'Evan', 'Sable', 'Ilya', 'Noor', 'Kai', 'Remy'],
        'human' => ['Lea', 'Sora', 'Mika', 'Jules', 'Asha', 'Ren', 'Tova', 'Nico'],
        'critter' => ['Mochi', 'Pip', 'Bean', 'Venti', 'Nib', 'Puff', 'Zest', 'Miso'],
    ];
    $cjkPools = [
        'bot' => ['小钉', '火花', '舱灯', '铆钉'],
        'android' => ['阿明', '林七', '单元', '晓'],
        'human' => ['阿明', '小雨', '林夏', '浩'],
        'critter' => ['团子', '豆豆', '咪咪', '球球'],
    ];
    $shipPool = ['SS Latch', 'The Arbiter', 'SS Ember', 'Hull Nine', 'The Kiln', 'SS Mora'];
    $kindKey = array_key_exists($kind, $pools) ? $kind : 'bot';
    $pool = $pools[$kindKey];
    $cjk = $cjkPools[$kindKey] ?? $cjkPools['bot'];

    $candidates = [];
    if ($nick !== '' && mb_strlen($nick) <= 16) {
        $candidates[] = $nick;
    }

    // Cultural / script cue first so Chinese bots get Chinese chips, not Latin abbrevs only.
    if ($wantsCjk) {
        $base = $cjk[$n % count($cjk)];
        $candidates[] = $base;
        $candidates[] = $base . '-' . $serial;
        if ($kind === 'android' || $kind === 'bot') {
            $candidates[] = '单元-' . $serial;
        }
    }

    $abbrevSrc = $subject !== '' ? $subject : $details;
    $abbrev = cardy_abbrev_from_subject($abbrevSrc);
    if ($abbrev !== '' && strcasecmp($abbrev, $nick) !== 0) {
        $candidates[] = $abbrev;
    }
    // Owner-style pet split when the role ends in bot/droid: Camera Bot → Cam Bott
    $roleWords = preg_split('/\s+/u', trim($abbrevSrc), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($roleWords) >= 2 && preg_match('/^(bot|droid|pup|cat|dog|ship)$/iu', $roleWords[count($roleWords) - 1])) {
        $rawHead = mb_substr($roleWords[0], 0, 3);
        $head = mb_strtoupper(mb_substr($rawHead, 0, 1)) . mb_strtolower(mb_substr($rawHead, 1));
        $tail = $roleWords[count($roleWords) - 1];
        $pet = $head . ' ' . mb_strtoupper(mb_substr($tail, 0, 1)) . mb_strtolower(mb_substr($tail, 1, 3));
        if (mb_strlen($pet) <= 16) {
            $candidates[] = $pet;
        }
    }

    if ($isShip) {
        $candidates[] = $shipPool[$n % count($shipPool)];
        $candidates[] = 'SS-' . $serialB;
        $candidates[] = $shipPool[($n + 2) % count($shipPool)];
    } elseif ($kind === 'android' || $kind === 'bot') {
        $letter = chr(65 + ($n % 26));
        $candidates[] = $letter . '-' . $serial;
        $candidates[] = 'UNIT-' . $serial;
    }

    // Occasional non-English chip even without an explicit cue.
    if (!$wantsCjk && ($n % 7) === 0) {
        $base = $cjk[($n + 1) % count($cjk)];
        $candidates[] = $base;
    }

    $candidates[] = $pool[$n % count($pool)];
    $candidates[] = $pool[($n + 3) % count($pool)];
    if ($kind === 'human' || $kind === 'android') {
        $candidates[] = $pool[($n + 5) % count($pool)] . '-' . $serial;
    }

    $out = [];
    foreach ($candidates as $name) {
        $name = trim((string)preg_replace('/\s+/u', ' ', (string)$name));
        if ($name === '' || mb_strlen($name) > 16) {
            continue;
        }
        // Ban the old generic dock-pun house names.
        if (preg_match('/^(bolt hum|dock rust|map fold|dock pip|cargo bit|cargo pip|night cog|rust wren)$/iu', $name)) {
            continue;
        }
        $dup = false;
        foreach ($out as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($name)) {
                $dup = true;
                break;
            }
        }
        if (!$dup) {
            $out[] = $name;
        }
        if (count($out) >= 3) {
            break;
        }
    }
    return $out;
}

/**
 * Filter AI name chips: drop banned generics, keep unicode callsigns, pad from generator.
 *
 * @param list<mixed> $suggestions
 * @return list<string>
 */
function cardy_sanitize_name_suggestions(array $suggestions, array $concept): array {
    $banned = '/^(bolt hum|dock rust|map fold|dock pip|cargo bit|cargo pip|night cog|rust wren|warm solder)$/iu';
    $out = [];
    foreach ($suggestions as $name) {
        if (!is_string($name)) {
            continue;
        }
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '' || mb_strlen($name) > 16) {
            continue;
        }
        if (preg_match($banned, $name)) {
            continue;
        }
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\'\.\- ]{0,15}$/u', $name)) {
            continue;
        }
        $dup = false;
        foreach ($out as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($name)) {
                $dup = true;
                break;
            }
        }
        if (!$dup) {
            $out[] = $name;
        }
        if (count($out) >= 3) {
            return $out;
        }
    }
    foreach (cardy_nickname_suggestions($concept) as $name) {
        $dup = false;
        foreach ($out as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($name)) {
                $dup = true;
                break;
            }
        }
        if (!$dup) {
            $out[] = $name;
        }
        if (count($out) >= 3) {
            break;
        }
    }
    return $out;
}

/** True when free text looks like a card-name rewrite (not a paint/look tweak). */
function cardy_looks_like_nickname(string $text): bool {
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 16) {
        return false;
    }
    $low = strtolower($text);
    if (str_contains($low, 'paint') || str_contains($low, 'look') || str_contains($low, 'vibe')
        || str_contains($low, 'detail') || cardy_is_path_intent_message($text)
        || cardy_is_kind_only_message($text)
    ) {
        return false;
    }
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) === 0 || count($words) > 4) {
        return false;
    }
    return (bool)preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\'\- ]{0,21}$/u', $text);
}
