<?php
/**
 * Thin OpenAI HTTP client used by chat + image endpoints.
 *
 * Goals:
 *   - One place for timeout, retry-on-5xx, error logging, token logging.
 *   - One place that knows how to walk the /v1/responses output structure
 *     so callers don't have to reimplement the same fragile extraction.
 *   - Strongly-typed return: ['ok' => bool, ...] -- never throws to the caller.
 *
 * Endpoints used by Card-o-Bot:
 *   POST /v1/responses           (chat with Structured Outputs)
 *   POST /v1/images/generations  (image)
 */

require_once __DIR__ . '/env.php';

if (!defined('OPENAI_API_BASE')) {
    define('OPENAI_API_BASE', 'https://api.openai.com');
}

/**
 * Low-level POST to an OpenAI endpoint.
 *
 * @param string $endpoint e.g. '/v1/responses'
 * @param array  $payload  decoded request body
 * @param array  $opts     ['timeout'=>int (default 90), 'retries'=>int (default 1)]
 * @return array {
 *   ok:        bool,
 *   http_code: int,
 *   raw:       string|null  raw response body,
 *   data:      array|null   decoded JSON,
 *   error:     string|null  human-readable error
 * }
 */
function openai_post(string $endpoint, array $payload, array $opts = []): array {
    $timeout = (int)($opts['timeout'] ?? 90);
    $retries = max(0, (int)($opts['retries'] ?? 1));

    $key = get_openai_key(); // exits with JSON 500 if missing
    $url = OPENAI_API_BASE . $endpoint;
    $body = json_encode($payload);

    $attempt = 0;
    $lastResult = null;
    while (true) {
        $attempt++;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $lastResult = [
                'ok' => false,
                'http_code' => 0,
                'raw' => null,
                'data' => null,
                'error' => 'cURL error: ' . $curlError,
            ];
            error_log("openai_post {$endpoint} attempt {$attempt}: {$curlError}");
            if ($attempt <= $retries) {
                continue;
            }
            return $lastResult;
        }

        $data = json_decode($raw, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            if (!is_array($data)) {
                return [
                    'ok' => false,
                    'http_code' => $httpCode,
                    'raw' => $raw,
                    'data' => null,
                    'error' => 'Invalid JSON response from OpenAI',
                ];
            }
            return [
                'ok' => true,
                'http_code' => $httpCode,
                'raw' => $raw,
                'data' => $data,
                'error' => null,
            ];
        }

        // Retry once on transient 5xx + 429.
        if (($httpCode >= 500 || $httpCode === 429) && $attempt <= $retries) {
            error_log("openai_post {$endpoint} attempt {$attempt} got {$httpCode}, retrying");
            usleep(500 * 1000);
            continue;
        }

        $msg = is_array($data) && isset($data['error'])
            ? (is_string($data['error']) ? $data['error'] : ($data['error']['message'] ?? 'API error'))
            : 'API error (HTTP ' . $httpCode . ')';

        return [
            'ok' => false,
            'http_code' => $httpCode,
            'raw' => $raw,
            'data' => $data,
            'error' => $msg,
        ];
    }
}

/**
 * Walk a /v1/responses payload and pull out the assistant's text.
 *
 * The responses API returns:
 *   output: [
 *     { type: 'reasoning', ... },           (sometimes)
 *     { type: 'message', content: [
 *         { type: 'output_text', text: '...' }
 *     ]}
 *   ]
 *
 * Returns the concatenated output_text, or null if nothing usable found.
 */
function openai_extract_text(array $responseData): ?string {
    if (isset($responseData['output_text']) && is_string($responseData['output_text'])) {
        return $responseData['output_text'];
    }

    if (!isset($responseData['output']) || !is_array($responseData['output'])) {
        return null;
    }

    $pieces = [];
    foreach ($responseData['output'] as $item) {
        if (!isset($item['type']) || $item['type'] !== 'message') {
            continue;
        }
        if (!isset($item['content']) || !is_array($item['content'])) {
            if (isset($item['text']) && is_string($item['text'])) {
                $pieces[] = $item['text'];
            }
            continue;
        }
        foreach ($item['content'] as $c) {
            if (isset($c['type']) && $c['type'] === 'output_text' && isset($c['text'])) {
                $pieces[] = $c['text'];
            } elseif (isset($c['text']) && is_string($c['text'])) {
                $pieces[] = $c['text'];
            }
        }
    }

    if (empty($pieces)) {
        return null;
    }
    return implode("\n", $pieces);
}

/**
 * Pull token usage out of a /v1/responses payload, in the same shape the
 * old chat.php returned to the frontend.
 */
function openai_extract_usage(array $responseData): ?array {
    if (!isset($responseData['usage']) || !is_array($responseData['usage'])) {
        return null;
    }
    $u = $responseData['usage'];
    return [
        'input_tokens'     => (int)($u['input_tokens']  ?? 0),
        'output_tokens'    => (int)($u['output_tokens'] ?? 0),
        'total_tokens'     => (int)($u['total_tokens']  ?? 0),
        'reasoning_tokens' => (int)($u['output_tokens_details']['reasoning_tokens'] ?? 0),
    ];
}

/**
 * Call /v1/responses with optional Structured Outputs schema.
 *
 * @param string $input         the prompt (system + user packed by caller)
 * @param array  $opts {
 *   model:      string  defaults to env OPENAI_TEXT_MODEL,
 *   schema:     array|null  JSON schema object; if provided, response is forced JSON,
 *   schema_name:string      name for the schema (required by API when schema set),
 *   max_output_tokens: int  defaults 4000,
 *   timeout:    int         defaults 90,
 *   retries:    int         defaults 1
 * }
 * @return array {
 *   ok: bool, error: ?string, http_code: int,
 *   text: ?string,        raw assistant text (may be JSON if schema used),
 *   parsed: ?array,       text json_decoded if schema was used,
 *   usage: ?array,        token usage,
 *   raw_response: ?array  full decoded response (debugging)
 * }
 */
function openai_chat_responses(string $input, array $opts = []): array {
    $model = $opts['model'] ?? get_text_model();
    $maxTokens = (int)($opts['max_output_tokens'] ?? 900);

    $payload = [
        'model' => $model,
        'input' => $input,
        'max_output_tokens' => $maxTokens,
    ];

    // Only reasoning models accept reasoning.effort (gpt-5 / o-series).
    $effort = $opts['reasoning_effort'] ?? (function_exists('get_reasoning_effort') ? get_reasoning_effort() : 'minimal');
    if (is_string($effort) && $effort !== '' && preg_match('/^(o\d|gpt-5)/i', (string)$model)) {
        $payload['reasoning'] = ['effort' => $effort];
    }

    if (!empty($opts['schema']) && is_array($opts['schema'])) {
        $name = $opts['schema_name'] ?? 'cardy_response';
        $payload['text'] = [
            'format' => [
                'type'   => 'json_schema',
                'name'   => $name,
                'schema' => $opts['schema'],
                'strict' => true,
            ],
        ];
    }

    $result = openai_post('/v1/responses', $payload, [
        'timeout' => (int)($opts['timeout'] ?? 90),
        'retries' => (int)($opts['retries'] ?? 1),
    ]);

    if (!$result['ok']) {
        return [
            'ok' => false,
            'error' => $result['error'],
            'http_code' => $result['http_code'],
            'text' => null,
            'parsed' => null,
            'usage' => null,
            'raw_response' => $result['data'],
        ];
    }

    $data = $result['data'];

    if (isset($data['status']) && $data['status'] === 'incomplete') {
        $reason = $data['incomplete_details']['reason'] ?? 'unknown';
        return [
            'ok' => false,
            'error' => 'Response incomplete: ' . $reason,
            'http_code' => $result['http_code'],
            'text' => null,
            'parsed' => null,
            'usage' => openai_extract_usage($data),
            'raw_response' => $data,
        ];
    }

    $text = openai_extract_text($data);
    if ($text === null || $text === '') {
        return [
            'ok' => false,
            'error' => 'Could not extract message from OpenAI response',
            'http_code' => $result['http_code'],
            'text' => null,
            'parsed' => null,
            'usage' => openai_extract_usage($data),
            'raw_response' => $data,
        ];
    }

    $parsed = null;
    if (!empty($opts['schema'])) {
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            error_log('openai_chat_responses: schema set but JSON parse failed. Text preview: ' . substr($text, 0, 300));
            return [
                'ok' => false,
                'error' => 'Model response was not valid JSON',
                'http_code' => $result['http_code'],
                'text' => $text,
                'parsed' => null,
                'usage' => openai_extract_usage($data),
                'raw_response' => $data,
            ];
        }
    }

    return [
        'ok' => true,
        'error' => null,
        'http_code' => $result['http_code'],
        'text' => $text,
        'parsed' => $parsed,
        'usage' => openai_extract_usage($data),
        'raw_response' => $data,
    ];
}

/**
 * Call /v1/images/generations.
 *
 * @param string $prompt
 * @param array  $opts {
 *   model:   string defaults env OPENAI_IMAGE_MODEL,
 *   size:    string defaults '1024x1024',
 *   quality: string defaults 'high',
 *   n:       int    defaults 1,
 *   timeout: int    defaults 120
 * }
 * @return array {
 *   ok: bool, error: ?string, http_code: int,
 *   image: ?array  first image object (with url or b64_json),
 *   raw_response: ?array
 * }
 */
function openai_image(string $prompt, array $opts = []): array {
    $payload = [
        'model'   => $opts['model']   ?? get_image_model(),
        'prompt'  => $prompt,
        'size'    => $opts['size']    ?? '1024x1024',
        'quality' => $opts['quality'] ?? 'high',
        'n'       => (int)($opts['n'] ?? 1),
    ];

    $result = openai_post('/v1/images/generations', $payload, [
        'timeout' => (int)($opts['timeout'] ?? 120),
        'retries' => 0, // image gen is expensive; do not auto-retry
    ]);

    if (!$result['ok']) {
        return [
            'ok' => false,
            'error' => $result['error'],
            'http_code' => $result['http_code'],
            'image' => null,
            'raw_response' => $result['data'],
        ];
    }

    $data = $result['data'];
    $first = $data['data'][0] ?? null;
    if (!is_array($first)) {
        return [
            'ok' => false,
            'error' => 'No image in response',
            'http_code' => $result['http_code'],
            'image' => null,
            'raw_response' => $data,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'http_code' => $result['http_code'],
        'image' => $first,
        'raw_response' => $data,
    ];
}
