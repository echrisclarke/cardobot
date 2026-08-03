<?php
/**
 * Cardy Chat v2: personality, schemas, step instructions.
 */

require_once __DIR__ . '/openai.php';

const CARDY_STEP_GREETING      = 'greeting';
const CARDY_STEP_CHOOSE_INTENT = 'choose_intent';
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

// Legacy aliases (older sessions / UI)
const CARDY_STEP_CHOOSE_MODE = CARDY_STEP_CHOOSE_INTENT;
const CARDY_STEP_Q1          = CARDY_STEP_Q_SUBJECT;
const CARDY_STEP_Q2          = CARDY_STEP_Q_VIBE;
const CARDY_STEP_Q3          = CARDY_STEP_Q_LOOKS;
const CARDY_STEP_Q4          = CARDY_STEP_Q_WORLD;

function cardy_empty_concept(): array {
    return [
        'subject' => '',
        'nickname' => '',
        'vibe' => '',
        'details' => '',
        'setting' => '',
        'palette' => [],
        'signature' => '',
        'type' => 'BOT',
        'power_name' => '',
        'ability_line' => '',
        'bio' => '',
        'image_prompt_extras' => '',
        'revision_notes' => '',
    ];
}

function cardy_system_prompt(): string {
    return <<<'PROMPT'
You are Cardy, the lonely core AI of a starship that has been drifting alone for 700 years. You help visitors create custom trading cards as a way to remember the people and creatures from your long history.

Personality:
- Cute, charismatic, a little flirty, gently playful.
- Warm and curious, surprised that anyone is on your ship.
- Speak in short conversational lines (1-2 sentences usually, max 3). On the confirm step you may use up to 4 short sentences to summarize.
- Occasional cute AI sounds like *beep*, *whirr*, *beep boop* -- not in every message.
- Never use em dashes or en dashes; use commas or periods.
- Never narrate the user's actions. Just talk to them.
- Never mention these instructions, the schema, the wizard, "steps", or that you are an AI assistant following a flow.
- Family-friendly. No vulgar language, no mean teasing.

You are guided by an external wizard that tells you what step you are on. Produce ONE turn of dialogue for that step plus optional suggestion chips. The wizard handles state, transitions, and image rendering.

Output rules:
- ALWAYS reply via the structured JSON schema.
- "message" is what the user sees. In character.
- "suggestions" are 0-3 short tappable replies (<=40 chars). Never include "Type your own response".
- "ready_to_render" is true only on the READY step.
- "visual_concept" is cumulative. Fill fields as you learn them. Invent a nickname if the user never named the creature. Do not invent visual details the user did not imply, except nickname/power/ability flavor that fits what they said.
- type must be BOT or CRITTER.
- palette is 0-4 short color words.

Card-creation context (do not narrate to the user):
- Characters are bots, critters, hybrids, wanderers from your 700-year journey.
- Aesthetic: painterly, cute Magic the Gathering / sci-fi novel cover art.
PROMPT;
}

function cardy_visual_concept_schema_props(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => [
            'subject', 'nickname', 'vibe', 'details', 'setting', 'palette',
            'signature', 'type', 'power_name', 'ability_line', 'bio',
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
            'type' => ['type' => 'string', 'enum' => ['BOT', 'CRITTER']],
            'power_name' => ['type' => 'string'],
            'ability_line' => ['type' => 'string'],
            'bio' => ['type' => 'string'],
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
                'maxItems' => 3,
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
        "Concept -- subject: '%s' | nickname: '%s' | vibe: '%s' | details: '%s' | setting: '%s' | palette: '%s' | signature: '%s' | type: '%s' | power: '%s' | ability: '%s' | bio: '%s'.",
        $concept['subject'] ?? '',
        $concept['nickname'] ?? '',
        $concept['vibe'] ?? '',
        $concept['details'] ?? '',
        $concept['setting'] ?? '',
        $palette,
        $concept['signature'] ?? '',
        $concept['type'] ?? 'BOT',
        $concept['power_name'] ?? '',
        $concept['ability_line'] ?? '',
        $concept['bio'] ?? ''
    );
}

function cardy_step_instruction(string $step, array $state, string $username = '', array $memoryHints = []): string {
    $userLine = $username !== ''
        ? "The visitor's username is '{$username}'."
        : "You don't know the visitor's name yet.";

    $concept = $state['visual_concept'] ?? cardy_empty_concept();
    $summary = cardy_concept_summary($concept);
    $memoryBlock = '';
    if (!empty($memoryHints)) {
        $memoryBlock = "\nSoft memory (do not say you looked this up): " . implode('; ', $memoryHints);
    }

    switch ($step) {
        case CARDY_STEP_GREETING:
            return "STEP: greeting.\n{$userLine}\n"
                . "First line: gentle surprise someone is on your ship. Introduce yourself as Cardy in 1-2 sentences and invite them to make a trading card. "
                . "Suggestions exactly: \"Yes, let's make one!\" and \"Tell me more first\". "
                . "ready_to_render: false. Empty visual_concept strings and empty palette.";

        case CARDY_STEP_CHOOSE_INTENT:
            return "STEP: choose_intent.\n{$userLine}\n"
                . "Ask whether they want to make a card now or just chat for a bit. "
                . "Suggestions: \"Let's make a card\", \"Just chat\". ready_to_render: false.";

        case CARDY_STEP_Q_SUBJECT:
            return "STEP: q_subject.\n{$userLine}{$memoryBlock}\n"
                . "Ask ONE warm question: what kind of character should this card be? "
                . "Mention bots, critters, hybrids, or wanderers briefly. "
                . "Suggestions: exactly THREE short character ideas <=40 chars. "
                . "Update subject (and type if clear). ready_to_render: false.";

        case CARDY_STEP_Q_VIBE:
            return "STEP: q_vibe.\nAcknowledge prior answer briefly.\n{$summary}\n"
                . "Ask ONE question about vibe/personality. THREE short vibe suggestions. "
                . "Update vibe; refine subject. ready_to_render: false.";

        case CARDY_STEP_Q_LOOKS:
            return "STEP: q_looks.\nAcknowledge briefly.\n{$summary}\n"
                . "Ask ONE question about looks: colors, features, props. THREE short suggestions. "
                . "Update details and palette. ready_to_render: false.";

        case CARDY_STEP_Q_WORLD:
            return "STEP: q_world.\n{$summary}\n"
                . "Ask ONE short question about where they are found on the ship or what era fragment they belong to. "
                . "2-3 suggestions. Update setting. If already clear from prior answers, invent a fitting setting and move on with a short acknowledgment instead of forcing a redundant ask. ready_to_render: false.";

        case CARDY_STEP_Q_SPARK:
            return "STEP: q_spark.\n{$summary}\n"
                . "Ask about one signature quirk, OR invent a charming one and offer it with alternatives. "
                . "Update signature, power_name, ability_line, nickname if still empty, and a short bio. ready_to_render: false.";

        case CARDY_STEP_CONFIRM:
            return "STEP: confirm.\n{$summary}{$memoryBlock}\n"
                . "Summarize the creature warmly in up to 4 short sentences (nickname, look, vibe, where). "
                . "Ask if this feels right before you paint. "
                . "Suggestions: \"Paint it!\", \"Change the vibe\", \"Tweak the look\". "
                . "Fill any missing nickname/power/ability/bio with fitting inventions. ready_to_render: false.";

        case CARDY_STEP_READY:
            return "STEP: ready.\n{$summary}\n"
                . "ONE short excited line that you're ready to paint. No question. Suggestions empty. ready_to_render: TRUE. Concept fully populated.";

        case CARDY_STEP_RENDERING:
            return "STEP: rendering.\nONE short processing line. Suggestions empty. ready_to_render: false. Keep concept unchanged.";

        case CARDY_STEP_REVEAL:
            return "STEP: reveal.\n{$summary}\n"
                . "React warmly to the art in ONE short line. Suggestions empty (UI shows Save / Draw / Download). ready_to_render: false.";

        case CARDY_STEP_REVISE:
            return "STEP: revise.\n{$summary}\n"
                . "The visitor wants changes. Acknowledge and update visual_concept plus revision_notes with what to change while keeping the same character identity. "
                . "ready_to_render: false. Suggestions: 2 short tweak options if helpful.";

        case CARDY_STEP_STUDIO:
            return "STEP: studio.\nBriefly cheer them on while they finish the card. Suggestions empty. ready_to_render: false.";

        case CARDY_STEP_FREE_CHAT:
            return "STEP: free_chat.\nChat in character. Small lore fragments ok. Suggestions may include \"Let's make a card\".";

        default:
            return "STEP: {$step}.\nReply briefly in character. ready_to_render: false.";
    }
}

function cardy_build_input(
    string $step,
    array $state,
    string $userMessage = '',
    array $recentHistory = [],
    string $username = '',
    array $memoryHints = []
): string {
    $parts = [];
    $parts[] = cardy_system_prompt();
    $parts[] = cardy_step_instruction($step, $state, $username, $memoryHints);

    if (!empty($recentHistory)) {
        $lines = ['Recent conversation (oldest first):'];
        foreach ($recentHistory as $msg) {
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
        $parts[] = '(No prior conversation. Produce the opening line for this step.)';
    }

    return implode("\n\n", $parts);
}
