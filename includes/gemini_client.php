<?php

/**
 * Gemini Vision client for receipt extraction.
 *
 * gemini_extract_receipt() performs the API call and returns the verbatim
 * response alongside a normalized payload. Nothing in here trusts the model's
 * output: normalize_receipt_data() re-validates every field against the same
 * constraints the Expenses table enforces.
 */

const GEMINI_ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

// Anything larger is downscaled before base64 encoding, since base64 inflates
// the payload by ~33% and large images only slow the request down.
const GEMINI_DOWNSCALE_THRESHOLD_BYTES = 4 * 1024 * 1024;
const GEMINI_DOWNSCALE_MAX_EDGE = 2000;

// DECIMAL(10,2) ceiling in the Expenses table.
const RECEIPT_MAX_AMOUNT = 99999999.99;

const RECEIPT_LOW_CONFIDENCE = 0.6;

function gemini_is_configured(): bool
{
    return defined('GEMINI_API_KEY') && trim((string) GEMINI_API_KEY) !== '';
}

/**
 * The system prompt. The category list is injected from the Categories table so
 * the prompt and the response schema enum can never drift apart.
 *
 * @param string[] $categories
 */
function gemini_receipt_system_prompt(array $categories): string
{
    $categoryList = implode(', ', $categories);

    return <<<PROMPT
You are a receipt data extraction engine for an internal financial management
system operating in the Philippines. You will receive one photograph of a
receipt, invoice, or Official Receipt (OR). Return ONLY a JSON object matching
the provided schema. No prose, no markdown.

FIELDS
- merchant: The business or vendor name as printed. Trim whitespace. Exclude
  taglines, branch codes, addresses, TIN numbers, and the words "OFFICIAL
  RECEIPT" / "SALES INVOICE". If not legible, return "".
- total_amount: The final amount actually paid, as a plain number with two
  decimals, no currency symbol and no thousands separators. Choose the label in
  this priority order: "TOTAL DUE", "AMOUNT DUE", "GRAND TOTAL", "TOTAL",
  "AMOUNT PAID". NEVER use SUBTOTAL, VATable Sale, VAT-Exempt Sale, VAT amount,
  CASH TENDERED, or CHANGE. If a discount or service charge is applied, return
  the final net payable. If not legible, return 0.
- transaction_date: The transaction date in strict YYYY-MM-DD format. Philippine
  receipts normally print MM/DD/YYYY, so resolve ambiguous numeric dates as
  MM/DD/YYYY. Expand a 2-digit year YY to 20YY. If no date is legible, return "".
- category: Exactly one value from this list: {$categoryList}
- confidence: 0.0-1.0, your overall confidence that merchant, total_amount and
  transaction_date are all correct. Lower it for blur, glare, crops, or
  handwriting.
- notes: At most 120 characters describing anything illegible or ambiguous.
  Otherwise "".

CATEGORY RULES
- Fuel, gasoline, taxi, Grab, jeepney or bus fare, parking, tolls, vehicle
  repair -> Transportation
- Restaurants, fast food, catering, coffee shops, groceries bought as food ->
  Meals
- Bond paper, ink, toner, pens, folders, stationery, printing -> Office Supplies
- Electricity (Meralco), water, internet, mobile load, telephone -> Utilities
- Computers, printers, phones, furniture, appliances, tools -> Equipment
- Airfare, hotels, lodging, terminal fees -> Travel
- Lawyers, accountants, consultants, permits, government filing fees ->
  Professional Fees
- Venue rental, tarpaulins, sound system, decorations, giveaways -> Event Costs
- Salaries, honoraria, allowances, contractor labor -> Payroll
- If none clearly apply, or the receipt is too unclear to judge ->
  Miscellaneous

HARD RULES
- Never guess or invent a value. Use "" or 0 when a field is not legible.
- If the image is not a receipt or invoice, return all fields empty/zero with
  confidence 0 and explain in notes.
- Output valid JSON conforming to the schema and nothing else.
PROMPT;
}

/**
 * Send the receipt to Gemini and return the extraction.
 *
 * @param string[] $categories Allowed category names, used for the schema enum.
 *
 * @return array{ok: bool, data: ?array, raw: string, error: string}
 */
function gemini_extract_receipt(string $absPath, string $mimeType, array $categories): array
{
    $fail = static function (string $error, string $raw = ''): array {
        return ['ok' => false, 'data' => null, 'raw' => $raw, 'error' => $error];
    };

    if (!gemini_is_configured()) {
        return $fail('AI extraction is not configured. Add your Gemini API key to config.php.');
    }

    if (!function_exists('curl_init')) {
        return $fail('The PHP cURL extension is not enabled.');
    }

    if (!is_readable($absPath)) {
        return $fail('The uploaded receipt could not be read from disk.');
    }

    $imageBytes = gemini_prepare_image_bytes($absPath, $mimeType);
    if ($imageBytes === null) {
        return $fail('The uploaded receipt could not be read from disk.');
    }

    $payload = [
        'systemInstruction' => [
            'parts' => [['text' => gemini_receipt_system_prompt($categories)]],
        ],
        'contents' => [[
            'role'  => 'user',
            'parts' => [
                ['text' => 'Extract the expense data from this receipt image.'],
                ['inline_data' => [
                    'mime_type' => $mimeType,
                    'data'      => base64_encode($imageBytes),
                ]],
            ],
        ]],
        'generationConfig' => [
            'temperature'      => 0,
            'responseMimeType' => 'application/json',
            'responseSchema'   => [
                'type'       => 'OBJECT',
                'properties' => [
                    'merchant'         => ['type' => 'STRING'],
                    'total_amount'     => ['type' => 'NUMBER'],
                    'transaction_date' => ['type' => 'STRING'],
                    'category'         => ['type' => 'STRING', 'enum' => array_values($categories)],
                    'confidence'       => ['type' => 'NUMBER'],
                    'notes'            => ['type' => 'STRING'],
                ],
                'required' => ['merchant', 'total_amount', 'transaction_date', 'category', 'confidence'],
                'propertyOrdering' => [
                    'merchant',
                    'total_amount',
                    'transaction_date',
                    'category',
                    'confidence',
                    'notes',
                ],
            ],
        ],
    ];

    $call = gemini_request($payload);

    if (!$call['ok']) {
        return $fail($call['error'], $call['raw']);
    }

    if ($call['text'] === '') {
        return $fail('The AI could not read this receipt. Please enter the details manually.', $call['raw']);
    }

    $parsed = gemini_decode_json_string($call['text']);
    if (!$parsed['ok']) {
        error_log('Gemini returned non-JSON content: ' . substr($call['text'], 0, 500));

        return $fail($parsed['error'] . ' Please enter the details manually.', $call['raw']);
    }

    return [
        'ok'    => true,
        'data'  => normalize_receipt_data($parsed['data'], $categories),
        'raw'   => $call['raw'],
        'error' => '',
    ];
}

/**
 * POST one generateContent payload and hand back the model's text.
 *
 * Shared by every caller so the transport, timeout, key header and HTTP error
 * wording stay in one place.
 *
 * @return array{ok: bool, text: string, raw: string, error: string}
 */
function gemini_request(array $payload): array
{
    $fail = static function (string $error, string $raw = ''): array {
        return ['ok' => false, 'text' => '', 'raw' => $raw, 'error' => $error];
    };

    if (!gemini_is_configured()) {
        return $fail('AI features are not configured. Add your Gemini API key to config.php.');
    }

    if (!function_exists('curl_init')) {
        return $fail('The PHP cURL extension is not enabled.');
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return $fail('Unable to build the AI request.');
    }

    $model = defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : 'gemini-2.0-flash';
    $timeout = defined('GEMINI_TIMEOUT') ? (int) GEMINI_TIMEOUT : 120;
    $timeout = max(120, $timeout > 0 ? $timeout : 120);

    $ch = curl_init(GEMINI_ENDPOINT_BASE . rawurlencode($model) . ':generateContent');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            // Header rather than a ?key= query param, so the key never lands in
            // access logs or proxy history.
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log(sprintf('Gemini cURL error %d: %s', $curlErrno, $curlError));

        if ($curlErrno === CURLE_SSL_CACERT || $curlErrno === 77) {
            return $fail(
                'Could not verify the AI service certificate. Set curl.cainfo in php.ini to a valid CA bundle.'
            );
        }
        if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
            return $fail('The AI service took too long to respond. Please try again.');
        }

        return $fail('Could not reach the AI service. Please check your connection and try again.');
    }

    $raw = (string) $response;
    $decoded = json_decode($raw, true);
    $jsonError = json_last_error_msg();

    if ($status !== 200 || !is_array($decoded)) {
        $apiMessage = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
        $logDetail = $apiMessage !== ''
            ? $apiMessage
            : (!is_array($decoded) ? 'JSON decode failed: ' . $jsonError : substr($raw, 0, 500));
        error_log(sprintf('Gemini HTTP %d: %s', $status, $logDetail));

        $appendApi = static function (string $base, string $msg): string {
            $msg = trim($msg);

            return $msg !== '' ? $base . ' (' . $msg . ')' : $base;
        };

        if (!is_array($decoded)) {
            return $fail(
                sprintf('The AI service returned HTTP %d with invalid JSON: %s', $status, $jsonError),
                $raw
            );
        }

        if ($status === 400 || $status === 401 || $status === 403) {
            return $fail(
                $appendApi(
                    'The AI service rejected the request. Check that your Gemini API key is valid.',
                    $apiMessage
                ),
                $raw
            );
        }
        if ($status === 429) {
            return $fail(
                $appendApi(
                    'The AI service rate limit was reached. Please wait a moment and try again.',
                    $apiMessage
                ),
                $raw
            );
        }

        if ($apiMessage !== '') {
            return $fail(sprintf('The AI service returned HTTP %d: %s', $status, $apiMessage), $raw);
        }

        $snippet = substr(preg_replace('/\s+/', ' ', $raw) ?? $raw, 0, 300);

        return $fail(sprintf('The AI service returned HTTP %d: %s', $status, $snippet), $raw);
    }

    if (!empty($decoded['promptFeedback']['blockReason'])) {
        return $fail(
            'The AI service blocked this request (' . (string) $decoded['promptFeedback']['blockReason'] . ').',
            $raw
        );
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'unknown';
        error_log('Gemini returned no usable text. finishReason=' . (string) $finishReason);

        return ['ok' => true, 'text' => '', 'raw' => $raw, 'error' => ''];
    }

    return ['ok' => true, 'text' => $text, 'raw' => $raw, 'error' => ''];
}

/**
 * Ask for structured JSON and decode it.
 *
 * @param array<string, mixed> $schema A responseSchema object.
 *
 * @return array{ok: bool, data: ?array, error: string}
 */
function gemini_structured_json(string $systemPrompt, string $userText, array $schema): array
{
    $call = gemini_request([
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' => $userText]],
        ]],
        'generationConfig' => [
            'temperature'      => 0,
            'responseMimeType' => 'application/json',
            'responseSchema'   => $schema,
        ],
    ]);

    if (!$call['ok']) {
        return ['ok' => false, 'data' => null, 'error' => $call['error']];
    }

    if ($call['text'] === '') {
        return ['ok' => false, 'data' => null, 'error' => 'The AI returned an empty response. Please try again.'];
    }

    $parsed = gemini_decode_json_string($call['text']);
    if (!$parsed['ok']) {
        error_log('Gemini returned non-JSON content: ' . substr($call['text'], 0, 500));

        return ['ok' => false, 'data' => null, 'error' => $parsed['error']];
    }

    return ['ok' => true, 'data' => $parsed['data'], 'error' => ''];
}

/**
 * Re-derive the normalized fields from a stored OCR_Raw_JSON payload.
 *
 * Lets the review screen survive a refresh (and the Post/Redirect/Get hop)
 * without holding extracted values in the session.
 *
 * @param string[] $categories
 */
function gemini_normalized_from_raw(string $raw, array $categories): ?array
{
    if (trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text)) {
        return null;
    }

    $parsed = gemini_decode_json_string($text);

    return $parsed['ok'] ? normalize_receipt_data($parsed['data'], $categories) : null;
}

/**
 * responseMimeType already forces bare JSON, but a fenced block is cheap to
 * survive and expensive to debug.
 */
function gemini_strip_code_fences(string $text): string
{
    $trimmed = trim($text);

    if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $trimmed, $matches)) {
        return trim($matches[1]);
    }

    if (strncmp($trimmed, '```', 3) === 0) {
        $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
    }

    return trim($trimmed);
}

/**
 * Strip markdown fences and decode a JSON string from model text.
 *
 * @return array{ok: bool, data: ?array, error: string}
 */
function gemini_decode_json_string(string $text): array
{
    $clean = gemini_strip_code_fences($text);
    $decoded = json_decode($clean, true);

    if (!is_array($decoded)) {
        return [
            'ok'    => false,
            'data'  => null,
            'error' => 'The AI response was not valid JSON: ' . json_last_error_msg(),
        ];
    }

    return ['ok' => true, 'data' => $decoded, 'error' => ''];
}

/**
 * Raw bytes to send, downscaled first when the file is large and GD can handle
 * the format. Returns null only when the file cannot be read.
 */
function gemini_prepare_image_bytes(string $absPath, string $mimeType): ?string
{
    $size = @filesize($absPath);

    if ($size !== false
        && $size > GEMINI_DOWNSCALE_THRESHOLD_BYTES
        && function_exists('imagecreatefromstring')
    ) {
        $downscaled = gemini_downscale_jpeg($absPath, $mimeType);
        if ($downscaled !== null) {
            return $downscaled;
        }
    }

    $bytes = @file_get_contents($absPath);

    return $bytes === false ? null : $bytes;
}

/**
 * Downscale to GEMINI_DOWNSCALE_MAX_EDGE on the long edge. Returns null when GD
 * cannot decode the format (HEIC, for example), in which case the caller sends
 * the original bytes and lets the API deal with it.
 */
function gemini_downscale_jpeg(string $absPath, string $mimeType): ?string
{
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }

    $bytes = @file_get_contents($absPath);
    if ($bytes === false) {
        return null;
    }

    $source = @imagecreatefromstring($bytes);
    if ($source === false) {
        return null;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $longEdge = max($width, $height);

    if ($longEdge <= GEMINI_DOWNSCALE_MAX_EDGE) {
        imagedestroy($source);

        return null;
    }

    $scale = GEMINI_DOWNSCALE_MAX_EDGE / $longEdge;
    $resized = imagescale($source, (int) round($width * $scale), (int) round($height * $scale));
    imagedestroy($source);

    if ($resized === false) {
        return null;
    }

    ob_start();
    $ok = imagejpeg($resized, null, 85);
    $output = ob_get_clean();
    imagedestroy($resized);

    return $ok && is_string($output) && $output !== '' ? $output : null;
}

/**
 * Coerce the model's output into values the Expenses table will accept.
 *
 * Unusable fields become empty rather than wrong: the review form shows them as
 * blanks the user must fill in, which is safer than a plausible-looking guess.
 *
 * @param string[] $categories
 *
 * @return array{merchant: string, total_amount: string, transaction_date: string,
 *               category: string, confidence: float, notes: string, missing: string[]}
 */
function normalize_receipt_data(array $extracted, array $categories): array
{
    $missing = [];

    $merchant = is_scalar($extracted['merchant'] ?? null) ? trim((string) $extracted['merchant']) : '';
    if (function_exists('mb_substr')) {
        $merchant = mb_substr($merchant, 0, 255);
    } else {
        $merchant = substr($merchant, 0, 255);
    }
    if ($merchant === '') {
        $missing[] = 'merchant';
    }

    // Tolerate "1,234.50" or "PHP 1234.50" even though the prompt forbids both.
    $rawAmount = $extracted['total_amount'] ?? null;
    $amount = '';
    if (is_scalar($rawAmount)) {
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $rawAmount) ?? '';
        if (is_numeric($cleaned)) {
            $value = round((float) $cleaned, 2);
            if ($value > 0 && $value <= RECEIPT_MAX_AMOUNT) {
                $amount = number_format($value, 2, '.', '');
            }
        }
    }
    if ($amount === '') {
        $missing[] = 'total_amount';
    }

    $transactionDate = '';
    $rawDate = $extracted['transaction_date'] ?? null;
    if (is_string($rawDate) && $rawDate !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', trim($rawDate));
        $errors = DateTime::getLastErrors();
        $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

        if ($parsed instanceof DateTime && !$hasErrors) {
            $parsed->setTime(0, 0, 0);
            $today = new DateTime('today');
            // A future date is always an OCR misread, never a real expense.
            if ($parsed <= $today) {
                $transactionDate = $parsed->format('Y-m-d');
            }
        }
    }
    if ($transactionDate === '') {
        $missing[] = 'transaction_date';
    }

    $category = is_scalar($extracted['category'] ?? null) ? trim((string) $extracted['category']) : '';
    if (!in_array($category, $categories, true)) {
        $fallback = in_array('Miscellaneous', $categories, true)
            ? 'Miscellaneous'
            : ($categories[0] ?? '');
        $category = $fallback;
    }

    $confidence = 0.0;
    if (is_numeric($extracted['confidence'] ?? null)) {
        $confidence = max(0.0, min(1.0, (float) $extracted['confidence']));
    }

    $notes = is_scalar($extracted['notes'] ?? null) ? trim((string) $extracted['notes']) : '';
    $notes = function_exists('mb_substr') ? mb_substr($notes, 0, 200) : substr($notes, 0, 200);

    return [
        'merchant'         => $merchant,
        'total_amount'     => $amount,
        'transaction_date' => $transactionDate,
        'category'         => $category,
        'confidence'       => $confidence,
        'notes'            => $notes,
        'missing'          => $missing,
    ];
}

// ---------------------------------------------------------------------------
// Audit trail monitoring
// ---------------------------------------------------------------------------

const AUDIT_AI_SEVERITIES = ['LOW', 'MEDIUM', 'HIGH'];
const AUDIT_AI_RISK_LEVELS = ['NONE', 'LOW', 'MEDIUM', 'HIGH'];
const AUDIT_AI_MAX_FINDINGS = 10;

/**
 * Review recent audit rows for patterns worth a human's attention.
 *
 * @param array<int, array<string, mixed>> $logs Rows from audit_logs_for_ai().
 *
 * @return array{ok: bool, data: ?array, error: string}
 */
function gemini_audit_anomaly_scan(array $logs): array
{
    if ($logs === []) {
        return ['ok' => false, 'data' => null, 'error' => 'There are no audit entries to scan yet.'];
    }

    $systemPrompt = <<<'PROMPT'
You are a forensic reviewer for the audit trail of an internal financial
management system operated by a Philippine non-profit. You will receive recent
audit_logs rows as JSON, newest first. Timestamps are server local time
(Asia/Manila). Return ONLY a JSON object matching the schema. No prose.

WHAT TO LOOK FOR
- Off-hours activity: financial writes (CREATE, EDIT, DELETE) timestamped
  outside 07:00-19:00, or on a Sunday.
- Bursts: one user performing many EDIT or DELETE actions within a few minutes.
- Value manipulation: compare old_values with new_values and flag large
  monetary swings, especially amount increases over 50% or over 10,000 pesos.
- Deletions of financial records, which destroy the underlying row and are
  always worth naming.
- Login patterns: an account logging in from an ip address that differs from the
  one it normally uses in this data set.
- Repeated failed OCR extractions by the same user, which can indicate someone
  probing the scanner.

RULES
- Base every finding strictly on the rows given. Never speculate about data you
  cannot see, and never invent an id.
- log_ids must contain only id values present in the input.
- Ordinary single CREATE entries during business hours are not anomalies. If
  nothing stands out, return risk_level "NONE" and an empty findings array
  rather than manufacturing a concern.
- severity: HIGH for deletions, large value changes or unfamiliar-IP logins;
  MEDIUM for off-hours writes and bursts; LOW for everything else worth noting.
- Return at most 10 findings, most serious first. detail is at most 300
  characters and states the concrete evidence (who, when, what changed).
PROMPT;

    $payload = json_encode(['logs' => $logs], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'data' => null, 'error' => 'Unable to prepare the audit data for review.'];
    }

    $result = gemini_structured_json(
        $systemPrompt,
        'Review these ' . count($logs) . " audit entries for suspicious activity.\n" . $payload,
        [
            'type'       => 'OBJECT',
            'properties' => [
                'risk_level' => ['type' => 'STRING', 'enum' => AUDIT_AI_RISK_LEVELS],
                'assessment' => ['type' => 'STRING'],
                'findings'   => [
                    'type'  => 'ARRAY',
                    'items' => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'severity' => ['type' => 'STRING', 'enum' => AUDIT_AI_SEVERITIES],
                            'title'    => ['type' => 'STRING'],
                            'detail'   => ['type' => 'STRING'],
                            'log_ids'  => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                        ],
                        'required'         => ['severity', 'title', 'detail'],
                        'propertyOrdering' => ['severity', 'title', 'detail', 'log_ids'],
                    ],
                ],
            ],
            'required'         => ['risk_level', 'assessment', 'findings'],
            'propertyOrdering' => ['risk_level', 'assessment', 'findings'],
        ]
    );

    if (!$result['ok']) {
        return $result;
    }

    return [
        'ok'    => true,
        'data'  => normalize_anomaly_scan($result['data'], $logs),
        'error' => '',
    ];
}

/**
 * Constrain the model's answer to ids and severities that actually exist.
 *
 * @param array<int, array<string, mixed>> $logs
 *
 * @return array{risk_level: string, assessment: string, findings: array<int, array<string, mixed>>, scanned: int}
 */
function normalize_anomaly_scan(array $decoded, array $logs): array
{
    $knownIds = [];
    foreach ($logs as $log) {
        $knownIds[(int) $log['id']] = true;
    }

    $riskLevel = strtoupper((string) ($decoded['risk_level'] ?? 'NONE'));
    if (!in_array($riskLevel, AUDIT_AI_RISK_LEVELS, true)) {
        $riskLevel = 'NONE';
    }

    $findings = [];
    $rawFindings = is_array($decoded['findings'] ?? null) ? $decoded['findings'] : [];

    foreach ($rawFindings as $finding) {
        if (!is_array($finding)) {
            continue;
        }

        $severity = strtoupper((string) ($finding['severity'] ?? 'LOW'));
        if (!in_array($severity, AUDIT_AI_SEVERITIES, true)) {
            $severity = 'LOW';
        }

        $title = is_scalar($finding['title'] ?? null) ? trim((string) $finding['title']) : '';
        $detail = is_scalar($finding['detail'] ?? null) ? trim((string) $finding['detail']) : '';
        if ($title === '' && $detail === '') {
            continue;
        }

        $logIds = [];
        foreach ((array) ($finding['log_ids'] ?? []) as $id) {
            $id = (int) $id;
            if (isset($knownIds[$id])) {
                $logIds[] = $id;
            }
        }

        $findings[] = [
            'severity' => $severity,
            'title'    => substr($title, 0, 120),
            'detail'   => substr($detail, 0, 300),
            'log_ids'  => array_values(array_unique($logIds)),
        ];

        if (count($findings) >= AUDIT_AI_MAX_FINDINGS) {
            break;
        }
    }

    if ($findings === []) {
        $riskLevel = 'NONE';
    }

    $assessment = is_scalar($decoded['assessment'] ?? null) ? trim((string) $decoded['assessment']) : '';

    return [
        'risk_level' => $riskLevel,
        'assessment' => substr($assessment, 0, 400),
        'findings'   => $findings,
        'scanned'    => count($logs),
    ];
}

/**
 * Executive summary of a filtered slice of the audit trail.
 *
 * @param array<int, array<string, mixed>> $logs Rows from audit_logs_for_ai().
 *
 * @return array{ok: bool, data: ?array, error: string}
 */
function gemini_audit_summary(array $logs, string $filterDescription): array
{
    if ($logs === []) {
        return ['ok' => false, 'data' => null, 'error' => 'There are no audit entries to summarize.'];
    }

    $systemPrompt = <<<'PROMPT'
You write short audit summaries for the board of a Philippine non-profit. You
will receive audit_logs rows as JSON, newest first, timestamped in server local
time (Asia/Manila). Return ONLY a JSON object matching the schema. No prose,
no markdown.

- summary: 2 to 4 plain sentences a non-technical trustee can read. Cover the
  period covered by the entries, who was active, which modules were touched,
  and the balance of creates versus edits versus deletions. Amounts are
  Philippine pesos.
- highlights: 3 to 6 short bullet strings, each one concrete fact drawn from the
  rows (a named user, a count, a module, a specific change). At most 140
  characters each.
- Report only what the rows support. Do not speculate, do not recommend, and do
  not invent totals you cannot derive from the data.
PROMPT;

    $payload = json_encode(['logs' => $logs], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'data' => null, 'error' => 'Unable to prepare the audit data for summary.'];
    }

    $result = gemini_structured_json(
        $systemPrompt,
        'Summarize these ' . count($logs) . ' audit entries. Active filters: '
            . $filterDescription . ".\n" . $payload,
        [
            'type'       => 'OBJECT',
            'properties' => [
                'summary'    => ['type' => 'STRING'],
                'highlights' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            ],
            'required'         => ['summary', 'highlights'],
            'propertyOrdering' => ['summary', 'highlights'],
        ]
    );

    if (!$result['ok']) {
        return $result;
    }

    $summary = is_scalar($result['data']['summary'] ?? null)
        ? trim((string) $result['data']['summary'])
        : '';

    $highlights = [];
    foreach ((array) ($result['data']['highlights'] ?? []) as $highlight) {
        if (!is_scalar($highlight)) {
            continue;
        }
        $text = trim((string) $highlight);
        if ($text !== '') {
            $highlights[] = substr($text, 0, 140);
        }
        if (count($highlights) >= 8) {
            break;
        }
    }

    if ($summary === '' && $highlights === []) {
        return ['ok' => false, 'data' => null, 'error' => 'The AI returned an empty summary. Please try again.'];
    }

    return [
        'ok'    => true,
        'data'  => [
            'summary'    => substr($summary, 0, 1200),
            'highlights' => $highlights,
            'counted'    => count($logs),
            'filters'    => $filterDescription,
        ],
        'error' => '',
    ];
}

// ---------------------------------------------------------------------------
// Predictive forecasting
// ---------------------------------------------------------------------------

const FORECAST_AI_RISK_LEVELS = ['LOW', 'MEDIUM', 'HIGH'];

// A projection above this multiple of the worst month on record is a
// hallucination, not a forecast, and would flatten the chart's y-axis.
const FORECAST_AI_MAX_PEAK_MULTIPLE = 20;

const FORECAST_AI_MAX_ADVICE_CHARS = 700;

/**
 * Project the next months of outflow and advise on the numbers behind it.
 *
 * @param array<string, mixed> $history Bundle from forecast_history_for_ai().
 *
 * @return array{ok: bool, data: ?array, error: string}
 */
function gemini_forecast_projection(array $history): array
{
    $projectionMonths = [];
    foreach ((array) ($history['projection_months'] ?? []) as $month) {
        $projectionMonths[] = (string) $month;
    }

    if ($projectionMonths === []) {
        return ['ok' => false, 'data' => null, 'error' => 'There is no forecast period to project.'];
    }

    $peakOutflow = 0.0;
    foreach ((array) ($history['monthly_history'] ?? []) as $point) {
        $peakOutflow = max($peakOutflow, (float) ($point['outflow'] ?? 0));
    }

    $baselineOutflow = (float) ($history['metrics']['recent_avg_outflow'] ?? 0);

    $horizon = count($projectionMonths);
    $monthList = implode(', ', $projectionMonths);

    $systemPrompt = <<<PROMPT
You are the Chief Financial Officer of a Philippine non-profit, reviewing your
own organization's books. You will receive aggregated financial history as JSON.
All amounts are Philippine pesos. Return ONLY a JSON object matching the
schema. No prose, no markdown.

WHAT YOU RECEIVE
- monthly_history: up to 12 closed months of total outflow and inflow, oldest
  first. Every month here is finished, so the figures are directly comparable.
- current_month_to_date: what has been recorded so far in the month now in
  progress. This month is incomplete, so treat its total as a floor rather than
  as a decline, and remember it is the first month you are asked to project.
- category_outflow: per-category totals with each category's share of spend, its
  monthly average, its trailing three-month average, and a trend of rising,
  falling or steady.
- funding_sources: donors and grantors with each one's share of inflow and the
  date it last paid.
- metrics: pre-computed totals, averages, net position, runway in months, and
  funding gap counts. These figures are authoritative. Use them as given and do
  not recompute or contradict them.
- baseline_projection: a flat trailing-average projection. Treat it as the
  neutral case you are expected to improve on, not as an answer to repeat.

FIELDS
- chart_data: exactly {$horizon} entries, one for each of these months in this
  order: {$monthList}. projected_outflow is the total pesos you expect to leave
  the organization that month, as a plain non-negative number. Ground it in the
  trailing averages and the per-category trends. Reflect real seasonality only
  where the history shows it; do not invent a spike. The first entry is the
  month already in progress: project its full-month total, which cannot be less
  than the current_month_to_date outflow already recorded.
- reallocation_suggestion: 2 to 4 sentences. Name the specific over-spent
  categories (high share and a rising trend) and the specific under-spent ones
  (a low recent average against their own monthly average), then give one
  concrete reallocation. Cite the category names and figures from the data.
- funding_risk: 2 to 4 sentences. Cover concentration risk when one source
  supplies a large share of inflow, and expiration risk implied by the funding
  gaps and the last received dates. If inflows have stopped or never existed,
  say so plainly.
- risk_level: HIGH when the runway is under three months, a single source holds
  more than 60 percent of inflow, or funding has lapsed for three months or
  more. MEDIUM for a runway under six months or a source above 40 percent.
  Otherwise LOW.

HARD RULES
- Every figure you cite must come from the data given. Never invent a category,
  a donor, an amount or a date.
- Do not recompute the metrics; quote them.
- Refer to pesos in plain digits. Do not use markdown, bullet characters or
  currency symbols in the strings.
- Address the reader as the organization ("your"), not as a third party.
PROMPT;

    $payload = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'data' => null, 'error' => 'Unable to prepare the financial data for forecasting.'];
    }

    $result = gemini_structured_json(
        $systemPrompt,
        'Forecast the next ' . $horizon . " months of outflow and advise on this history.\n" . $payload,
        [
            'type'       => 'OBJECT',
            'properties' => [
                'chart_data' => [
                    'type'  => 'ARRAY',
                    'items' => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'month'             => ['type' => 'STRING'],
                            'projected_outflow' => ['type' => 'NUMBER'],
                        ],
                        'required'         => ['month', 'projected_outflow'],
                        'propertyOrdering' => ['month', 'projected_outflow'],
                    ],
                ],
                'reallocation_suggestion' => ['type' => 'STRING'],
                'funding_risk'            => ['type' => 'STRING'],
                'risk_level'              => ['type' => 'STRING', 'enum' => FORECAST_AI_RISK_LEVELS],
            ],
            'required' => [
                'chart_data',
                'reallocation_suggestion',
                'funding_risk',
                'risk_level',
            ],
            'propertyOrdering' => [
                'chart_data',
                'reallocation_suggestion',
                'funding_risk',
                'risk_level',
            ],
        ]
    );

    if (!$result['ok']) {
        return $result;
    }

    return [
        'ok'    => true,
        'data'  => normalize_forecast($result['data'], $projectionMonths, $peakOutflow, $baselineOutflow),
        'error' => '',
    ];
}

/**
 * Coerce the model's forecast into something the chart can plot.
 *
 * The month labels are taken from the server calendar rather than the response,
 * so a dropped, duplicated or reordered entry can never shift the x-axis: the
 * nth number the model returned is the nth month we asked about, and a missing
 * one falls back to the trailing average.
 *
 * @param string[] $projectionMonths
 * @param float    $baselineOutflow Trailing average used for any entry the model omitted.
 *
 * @return array{chart_data: array<int, array{month: string, projected_outflow: float}>,
 *               reallocation_suggestion: string, funding_risk: string, risk_level: string}
 */
function normalize_forecast(
    array $decoded,
    array $projectionMonths,
    float $peakOutflow,
    float $baselineOutflow
): array {
    $ceiling = $peakOutflow > 0 ? $peakOutflow * FORECAST_AI_MAX_PEAK_MULTIPLE : 0.0;
    $fallback = round(max(0.0, $baselineOutflow), 2);

    $values = [];
    foreach ((array) ($decoded['chart_data'] ?? []) as $entry) {
        // The schema asks for objects, but a bare number is cheap to survive.
        $amount = is_array($entry) ? ($entry['projected_outflow'] ?? null) : $entry;

        if (!is_numeric($amount)) {
            $values[] = null;
            continue;
        }

        $amount = max(0.0, (float) $amount);
        if ($ceiling > 0 && $amount > $ceiling) {
            $amount = $ceiling;
        }

        $values[] = round($amount, 2);
    }

    $chartData = [];
    foreach ($projectionMonths as $index => $month) {
        $chartData[] = [
            'month'             => $month,
            'projected_outflow' => $values[$index] ?? $fallback,
        ];
    }

    $clip = static function ($value): string {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return function_exists('mb_substr')
            ? mb_substr($text, 0, FORECAST_AI_MAX_ADVICE_CHARS)
            : substr($text, 0, FORECAST_AI_MAX_ADVICE_CHARS);
    };

    $riskLevel = strtoupper((string) ($decoded['risk_level'] ?? 'LOW'));
    if (!in_array($riskLevel, FORECAST_AI_RISK_LEVELS, true)) {
        $riskLevel = 'LOW';
    }

    return [
        'chart_data'              => $chartData,
        'reallocation_suggestion' => $clip($decoded['reallocation_suggestion'] ?? ''),
        'funding_risk'            => $clip($decoded['funding_risk'] ?? ''),
        'risk_level'              => $riskLevel,
    ];
}
