<?php

/**
 * Receipt upload and extraction endpoint for the Scan Receipt workspace.
 *
 * POST-only, multipart in and JSON out. Stores the image, records the receipt,
 * hands it to Gemini, and returns the values for the reviewer to confirm.
 *
 * Nothing here writes an expense. The Staff member still confirms the values on
 * ocr_expense.php, which is where validation that matters lives.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/gemini_client.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/receipts.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json; charset=utf-8');

const OCR_WORKSPACE_ROLES = ['Staff', 'Admin'];

/**
 * @param array<string, mixed>|null $data
 */
function ocr_respond(bool $ok, ?array $data, string $error, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ocr_respond(false, null, 'This endpoint accepts POST requests only.', 405);
}

if (empty($_SESSION['UserID'])) {
    ocr_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

// A fetch target needs a JSON refusal, not the HTML page require_role() renders.
if (!in_array($_SESSION['Role'] ?? '', OCR_WORKSPACE_ROLES, true)) {
    ocr_respond(false, null, 'Scanning receipts is restricted to Staff and Administrators.', 403);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    ocr_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

$userId = (int) $_SESSION['UserID'];
$categories = fetch_category_names_safe($pdo, CATEGORY_TYPE_EXPENSE);

$stored = store_uploaded_receipt($_FILES['receipt_image'] ?? []);

if (!$stored['ok']) {
    ocr_respond(false, null, $stored['error'], 400);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO Receipts
            (File_Path, Original_Filename, Mime_Type, File_Size, OCR_Status, UploadedBy_UserID)
         VALUES
            (:file_path, :original_filename, :mime_type, :file_size, :ocr_status, :uploaded_by)'
    );
    $stmt->execute([
        'file_path'         => $stored['path'],
        'original_filename' => $stored['original'],
        'mime_type'         => $stored['mime'],
        'file_size'         => $stored['size'],
        'ocr_status'        => 'Pending',
        'uploaded_by'       => $userId,
    ]);
    $receiptId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('Failed to insert receipt: ' . $e->getMessage());
    @unlink(receipt_absolute_path($stored['path']));
    ocr_respond(false, null, 'Unable to save the receipt. Please try again.', 500);
}

$ocr = gemini_extract_receipt(
    receipt_absolute_path($stored['path']),
    $stored['mime'],
    $categories
);

try {
    $stmt = $pdo->prepare(
        'UPDATE Receipts
         SET OCR_Status = :ocr_status, OCR_Raw_JSON = :raw, OCR_Error = :error
         WHERE ReceiptID = :receipt_id AND UploadedBy_UserID = :user_id'
    );
    $stmt->execute([
        'ocr_status' => $ocr['ok'] ? 'Processed' : 'Failed',
        'raw'        => $ocr['raw'] !== '' ? $ocr['raw'] : null,
        'error'      => $ocr['ok'] ? null : $ocr['error'],
        'receipt_id' => $receiptId,
        'user_id'    => $userId,
    ]);
} catch (PDOException $e) {
    error_log('Failed to update receipt OCR status: ' . $e->getMessage());
}

log_system_action(
    $pdo,
    $userId,
    AUDIT_ACTION_CREATE,
    'OCR',
    $receiptId,
    null,
    [
        // record_id is a ReceiptID here, not an ExpenseID.
        'entity'            => 'receipt',
        'receipt_id'        => $receiptId,
        'original_filename' => $stored['original'],
        'mime_type'         => $stored['mime'],
        'file_size'         => $stored['size'],
        'ocr_status'        => $ocr['ok'] ? 'Processed' : 'Failed',
        'ocr_error'         => $ocr['ok'] ? null : $ocr['error'],
    ],
    receipt_public_url($stored['path'])
);

// The image is stored either way, so a failed read is a warning to type the
// details in by hand rather than an error that throws the upload away.
$extracted = $ocr['ok'] ? ($ocr['data'] ?? null) : null;
$warning = '';

if ($extracted === null) {
    $extracted = [
        'merchant'         => '',
        'total_amount'     => '',
        'transaction_date' => '',
        'category'         => in_array('Miscellaneous', $categories, true)
            ? 'Miscellaneous'
            : ($categories[0] ?? ''),
        'confidence'       => 0.0,
        'notes'            => '',
        'missing'          => ['merchant', 'total_amount', 'transaction_date'],
    ];

    $warning = $ocr['error'] !== ''
        ? $ocr['error']
        : 'The receipt was saved but could not be read automatically. Please enter the details below.';
}

$_SESSION['pending_receipt_id'] = $receiptId;

ocr_respond(true, [
    'receipt_id'    => $receiptId,
    'image_url'     => receipt_public_url($stored['path']),
    'status'        => $ocr['ok'] ? 'Processed' : 'Failed',
    'confidence'    => (float) $extracted['confidence'],
    'low_confidence' => (float) $extracted['confidence'] < RECEIPT_LOW_CONFIDENCE,
    'notes'         => (string) $extracted['notes'],
    'missing'       => array_values((array) $extracted['missing']),
    'warning'       => $warning,
    'payee'         => (string) $extracted['merchant'],
    'amount'        => (string) $extracted['total_amount'],
    'date_incurred' => (string) $extracted['transaction_date'],
    'category'      => (string) $extracted['category'],
], '');
