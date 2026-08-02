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

    $extracted = json_decode(gemini_strip_code_fences($call['text']), true);
    if (!is_array($extracted)) {
        error_log('Gemini returned non-JSON content: ' . substr($call['text'], 0, 500));

        return $fail('The AI response could not be understood. Please enter the details manually.', $call['raw']);
    }

    return [
        'ok'    => true,
        'data'  => normalize_receipt_data($extracted, $categories),
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
    $timeout = defined('GEMINI_TIMEOUT') ? (int) GEMINI_TIMEOUT : 45;

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
        CURLOPT_TIMEOUT        => $timeout > 0 ? $timeout : 45,
        CURLOPT_CONNECTTIMEOUT => 10,
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

    if ($status !== 200 || !is_array($decoded)) {
        $apiMessage = is_array($decoded) ? ($decoded['error']['message'] ?? '') : '';
        error_log(sprintf('Gemini HTTP %d: %s', $status, $apiMessage !== '' ? $apiMessage : substr($raw, 0, 500)));

        if ($status === 400 || $status === 401 || $status === 403) {
            return $fail('The AI service rejected the request. Check that your Gemini API key is valid.', $raw);
        }
        if ($status === 429) {
            return $fail('The AI service rate limit was reached. Please wait a moment and try again.', $raw);
        }

        return $fail('The AI service returned an unexpected response. Please try again.', $raw);
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

    $decoded = json_decode(gemini_strip_code_fences($call['text']), true);
    if (!is_array($decoded)) {
        error_log('Gemini returned non-JSON content: ' . substr($call['text'], 0, 500));

        return ['ok' => false, 'data' => null, 'error' => 'The AI response could not be understood. Please try again.'];
    }

    return ['ok' => true, 'data' => $decoded, 'error' => ''];
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

    $extracted = json_decode(gemini_strip_code_fences($text), true);

    return is_array($extracted) ? normalize_receipt_data($extracted, $categories) : null;
}

/**
 * responseMimeType already forces bare JSON, but a fenced block is cheap to
 * survive and expensive to debug.
 */
function gemini_strip_code_fences(string $text): string
{
    $trimmed = trim($text);

    if (strncmp($trimmed, '```', 3) === 0) {
        $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
    }

    return trim($trimmed);
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
