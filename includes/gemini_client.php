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

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return $fail('Unable to build the AI request.');
    }

    $model = defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : 'gemini-2.5-flash';
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
            'The AI service blocked this image (' . (string) $decoded['promptFeedback']['blockReason'] . ').',
            $raw
        );
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'unknown';
        error_log('Gemini returned no usable text. finishReason=' . (string) $finishReason);

        return $fail('The AI could not read this receipt. Please enter the details manually.', $raw);
    }

    $extracted = json_decode(gemini_strip_code_fences($text), true);
    if (!is_array($extracted)) {
        error_log('Gemini returned non-JSON content: ' . substr($text, 0, 500));

        return $fail('The AI response could not be understood. Please enter the details manually.', $raw);
    }

    return [
        'ok'    => true,
        'data'  => normalize_receipt_data($extracted, $categories),
        'raw'   => $raw,
        'error' => '',
    ];
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
