<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/gemini_client.php';
require_once __DIR__ . '/includes/logger.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

const RECEIPT_UPLOAD_DIR = __DIR__ . '/uploads/receipts';
const RECEIPT_PUBLIC_DIR = 'uploads/receipts';
const MAX_RECEIPT_BYTES = 8 * 1024 * 1024;

// Real MIME types, read from the file contents rather than the client's header.
const ALLOWED_RECEIPT_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/heic' => 'heic',
    'image/heif' => 'heif',
];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$userId = (int) $_SESSION['UserID'];
$categories = fetch_category_names_safe($pdo, CATEGORY_TYPE_EXPENSE);

$errorMessage = '';
$successMessage = '';
$receipt = null;
$extracted = null;

/**
 * Human-readable reason for a PHP upload error code.
 */
function receipt_upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That image is too large. Please upload a receipt photo under 8 MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'Please choose or capture a receipt image first.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not store the image. Please contact your administrator.';
        case UPLOAD_ERR_EXTENSION:
            return 'The upload was blocked by the server configuration.';
        default:
            return 'The image could not be uploaded. Please try again.';
    }
}

/**
 * Validate and store an uploaded receipt.
 *
 * @return array{ok: bool, error: string, path: string, public_path: string,
 *               mime: string, size: int, original: string}
 */
function store_uploaded_receipt(array $file): array
{
    $result = [
        'ok'          => false,
        'error'       => '',
        'path'        => '',
        'public_path' => '',
        'mime'        => '',
        'size'        => 0,
        'original'    => '',
    ];

    // 1. PHP-level upload status.
    $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code !== UPLOAD_ERR_OK) {
        $result['error'] = receipt_upload_error_message($code);

        return $result;
    }

    $tmpPath = $file['tmp_name'] ?? '';

    // 2. Confirm this really came through PHP's upload handler, which rules out
    //    a forged tmp_name pointing at an arbitrary server file.
    if (!is_string($tmpPath) || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $result['error'] = 'The upload could not be verified. Please try again.';

        return $result;
    }

    // 3. Size bounds.
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        $result['error'] = 'The uploaded file is empty. Please try again.';

        return $result;
    }
    if ($size > MAX_RECEIPT_BYTES) {
        $result['error'] = 'That image is too large. Please upload a receipt photo under 8 MB.';

        return $result;
    }

    // 4. MIME sniffed from content, not from $file['type'] which the client sets.
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (is_string($detected)) {
                $mime = strtolower($detected);
            }
        }
    }
    if ($mime === '') {
        $probe = @getimagesize($tmpPath);
        if (is_array($probe) && !empty($probe['mime'])) {
            $mime = strtolower((string) $probe['mime']);
        }
    }
    if (!isset(ALLOWED_RECEIPT_MIMES[$mime])) {
        $result['error'] = 'Only JPG, PNG, WEBP or HEIC images are accepted.';

        return $result;
    }

    // 5. Independent confirmation that the bytes actually decode as an image.
    //    HEIC is exempt because getimagesize() cannot parse it on most builds.
    if (!in_array($mime, ['image/heic', 'image/heif'], true)) {
        $dimensions = @getimagesize($tmpPath);
        if ($dimensions === false
            || empty($dimensions[0])
            || empty($dimensions[1])
            || (int) $dimensions[0] < 32
            || (int) $dimensions[1] < 32
        ) {
            $result['error'] = 'That file is not a readable image. Please upload a clear receipt photo.';

            return $result;
        }
    }

    if (!is_dir(RECEIPT_UPLOAD_DIR) && !@mkdir(RECEIPT_UPLOAD_DIR, 0755, true) && !is_dir(RECEIPT_UPLOAD_DIR)) {
        error_log('Unable to create receipt upload directory: ' . RECEIPT_UPLOAD_DIR);
        $result['error'] = 'The server could not store the image. Please contact your administrator.';

        return $result;
    }

    // 6. Filename is generated, never derived from user input, so double
    //    extensions, traversal and null bytes are structurally impossible.
    $filename = bin2hex(random_bytes(16)) . '.' . ALLOWED_RECEIPT_MIMES[$mime];
    $destination = RECEIPT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;

    // 7. Move into place and drop execute permissions.
    if (!move_uploaded_file($tmpPath, $destination)) {
        error_log('move_uploaded_file failed for destination: ' . $destination);
        $result['error'] = 'The server could not store the image. Please try again.';

        return $result;
    }
    @chmod($destination, 0644);

    $originalName = is_string($file['name'] ?? null) ? basename($file['name']) : '';

    return [
        'ok'          => true,
        'error'       => '',
        'path'        => RECEIPT_PUBLIC_DIR . '/' . $filename,
        'public_path' => RECEIPT_PUBLIC_DIR . '/' . $filename,
        'mime'        => $mime,
        'size'        => $size,
        'original'    => substr($originalName, 0, 255),
    ];
}

/**
 * Load a receipt the current user owns. Returns null for anything else, so a
 * guessed ReceiptID leaks nothing.
 */
function load_owned_receipt(PDO $pdo, int $receiptId, int $userId): ?array
{
    if ($receiptId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ReceiptID, ExpenseID, File_Path, Mime_Type, OCR_Status, OCR_Raw_JSON, OCR_Error
         FROM Receipts
         WHERE ReceiptID = :receipt_id AND UploadedBy_UserID = :user_id'
    );
    $stmt->execute([
        'receipt_id' => $receiptId,
        'user_id'    => $userId,
    ]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Absolute path for a stored receipt, rebuilt from the basename so a tampered
 * File_Path can never escape the uploads directory.
 */
function receipt_absolute_path(string $storedPath): string
{
    return RECEIPT_UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($storedPath);
}

function receipt_public_url(string $storedPath): string
{
    return RECEIPT_PUBLIC_DIR . '/' . basename($storedPath);
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errorMessage = 'Your session expired. Please try again.';
        $action = '';
    }
}

// ---------------------------------------------------------------------------
// Upload: store the image, record the receipt, then hand it to Gemini.
// ---------------------------------------------------------------------------
if ($action === 'upload') {
    $stored = store_uploaded_receipt($_FILES['receipt_image'] ?? []);

    if (!$stored['ok']) {
        $errorMessage = $stored['error'];
    } else {
        $receiptId = 0;

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
            $errorMessage = 'Unable to save the receipt. Please try again.';
        }

        if ($receiptId > 0) {
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

            $_SESSION['pending_receipt_id'] = $receiptId;

            header('Location: ' . $_SERVER['PHP_SELF'] . '?receipt=' . $receiptId);
            exit;
        }
    }
}

// ---------------------------------------------------------------------------
// Discard: mark the receipt unused and remove the image from disk.
// ---------------------------------------------------------------------------
if ($action === 'discard') {
    $receiptId = (int) ($_POST['receipt_id'] ?? 0);

    try {
        $target = load_owned_receipt($pdo, $receiptId, $userId);

        if ($target !== null && $target['ExpenseID'] === null) {
            $stmt = $pdo->prepare(
                'UPDATE Receipts SET OCR_Status = :ocr_status WHERE ReceiptID = :receipt_id'
            );
            $stmt->execute([
                'ocr_status' => 'Discarded',
                'receipt_id' => $receiptId,
            ]);

            @unlink(receipt_absolute_path($target['File_Path']));

            log_system_action(
                $pdo,
                $userId,
                AUDIT_ACTION_DELETE,
                'OCR',
                $receiptId,
                [
                    'entity'     => 'receipt',
                    'receipt_id' => $receiptId,
                    'file_path'  => $target['File_Path'],
                    'ocr_status' => $target['OCR_Status'],
                ],
                ['entity' => 'receipt', 'ocr_status' => 'Discarded', 'image_removed' => true],
                receipt_public_url($target['File_Path'])
            );
        }

        unset($_SESSION['pending_receipt_id']);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?discarded=1');
        exit;
    } catch (PDOException $e) {
        error_log('Failed to discard receipt: ' . $e->getMessage());
        $errorMessage = 'Unable to discard the receipt. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Save: the user has reviewed the extracted values. Write the expense and link
// the receipt to it in one transaction.
// ---------------------------------------------------------------------------
if ($action === 'save') {
    $receiptId = (int) ($_POST['receipt_id'] ?? 0);
    $payee = isset($_POST['payee']) ? trim($_POST['payee']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $amount = $_POST['amount'] ?? '';
    $dateIncurred = $_POST['date_incurred'] ?? '';

    $amountValid = is_numeric($amount)
        && (float) $amount > 0
        && (float) $amount <= RECEIPT_MAX_AMOUNT;
    $dateValid = is_string($dateIncurred)
        && $dateIncurred !== ''
        && strtotime($dateIncurred) !== false;
    $categoryValid = in_array($category, $categories, true);

    try {
        $target = load_owned_receipt($pdo, $receiptId, $userId);

        if ($target === null) {
            $errorMessage = 'That receipt could not be found.';
        } elseif ($target['ExpenseID'] !== null) {
            // Guards against a double submit attaching one receipt twice.
            $errorMessage = 'This receipt has already been saved as an expense.';
        } elseif ($payee === '' || !$categoryValid || !$amountValid || !$dateValid) {
            $errorMessage = 'Please fill in all fields with valid values.';
            $receipt = $target;
        } else {
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO Expenses
                        (Payee, Category, Amount, Date_Incurred, RecordedBy_UserID)
                     VALUES
                        (:payee, :category, :amount, :date_incurred, :recorded_by)'
                );
                $stmt->execute([
                    'payee'         => $payee,
                    'category'      => $category,
                    'amount'        => round((float) $amount, 2),
                    'date_incurred' => $dateIncurred,
                    'recorded_by'   => $userId,
                ]);

                $expenseId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'UPDATE Receipts
                     SET ExpenseID = :expense_id, OCR_Status = :ocr_status
                     WHERE ReceiptID = :receipt_id AND ExpenseID IS NULL'
                );
                $stmt->execute([
                    'expense_id' => $expenseId,
                    'ocr_status' => 'Processed',
                    'receipt_id' => $receiptId,
                ]);

                // Inside the transaction on purpose: if the audit row cannot be
                // written, the expense is rolled back rather than saved
                // unlogged.
                $aiValues = gemini_normalized_from_raw((string) ($target['OCR_Raw_JSON'] ?? ''), $categories);

                log_system_action(
                    $pdo,
                    $userId,
                    AUDIT_ACTION_CREATE,
                    'OCR',
                    $expenseId,
                    null,
                    [
                        // record_id is the new ExpenseID; receipt_id ties it back
                        // to the scanned image in source_link.
                        'entity'           => 'expense',
                        'payee'            => $payee,
                        'category'         => $category,
                        'amount'           => number_format(round((float) $amount, 2), 2, '.', ''),
                        'date_incurred'    => $dateIncurred,
                        'receipt_id'       => $receiptId,
                        'ai_confidence'    => $aiValues['confidence'] ?? null,
                        'edited_before_save' => $aiValues !== null
                            ? audit_diff(
                                [
                                    'payee'         => $aiValues['merchant'],
                                    'category'      => $aiValues['category'],
                                    'amount'        => $aiValues['total_amount'],
                                    'date_incurred' => $aiValues['transaction_date'],
                                ],
                                [
                                    'payee'         => $payee,
                                    'category'      => $category,
                                    'amount'        => number_format(round((float) $amount, 2), 2, '.', ''),
                                    'date_incurred' => $dateIncurred,
                                ]
                            )
                            : null,
                    ],
                    receipt_public_url((string) $target['File_Path'])
                );

                $pdo->commit();

                unset($_SESSION['pending_receipt_id']);

                header('Location: expenses.php?saved=1');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    } catch (PDOException $e) {
        error_log('Failed to save OCR expense: ' . $e->getMessage());
        $errorMessage = 'Unable to save the record. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Review state: load the receipt named in the query string (or the failed save
// attempt above) and rebuild the extracted values from the stored response.
// ---------------------------------------------------------------------------
if ($receipt === null && isset($_GET['receipt'])) {
    try {
        $receipt = load_owned_receipt($pdo, (int) $_GET['receipt'], $userId);
        if ($receipt === null) {
            $errorMessage = $errorMessage ?: 'That receipt could not be found.';
        }
    } catch (PDOException $e) {
        error_log('Failed to load receipt: ' . $e->getMessage());
        $errorMessage = $errorMessage ?: 'Unable to load the receipt. Please try again.';
    }
}

if ($receipt !== null) {
    if ($receipt['ExpenseID'] !== null) {
        $successMessage = 'This receipt has already been saved as an expense.';
        $receipt = null;
    } elseif ($receipt['OCR_Status'] === 'Discarded') {
        $receipt = null;
    } else {
        if ($receipt['OCR_Status'] === 'Processed' && !empty($receipt['OCR_Raw_JSON'])) {
            $extracted = gemini_normalized_from_raw((string) $receipt['OCR_Raw_JSON'], $categories);
        }

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

            if ($errorMessage === '') {
                $errorMessage = $receipt['OCR_Error'] !== null && $receipt['OCR_Error'] !== ''
                    ? (string) $receipt['OCR_Error']
                    : 'The receipt was saved but could not be read automatically. Please enter the details below.';
            }
        }

        // A failed save re-renders the form; keep what the user typed.
        if ($action === 'save') {
            $extracted['merchant'] = isset($_POST['payee']) ? trim((string) $_POST['payee']) : '';
            $extracted['total_amount'] = is_string($_POST['amount'] ?? null) ? $_POST['amount'] : '';
            $extracted['transaction_date'] = is_string($_POST['date_incurred'] ?? null)
                ? $_POST['date_incurred']
                : '';
            if (in_array($_POST['category'] ?? '', $categories, true)) {
                $extracted['category'] = (string) $_POST['category'];
            }
        }
    }
}

if (isset($_GET['discarded'])) {
    $successMessage = 'Receipt discarded.';
}

$aiConfigured = gemini_is_configured();
$missingFields = $extracted['missing'] ?? [];
$lowConfidence = $extracted !== null && $extracted['confidence'] < RECEIPT_LOW_CONFIDENCE;
$confidencePercent = $extracted !== null ? (int) round($extracted['confidence'] * 100) : 0;

$isAdmin = ($_SESSION['Role'] ?? '') === 'Admin';
$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Receipt — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-100">
    <aside class="fixed inset-y-0 left-0 w-64 bg-slate-800 text-slate-100 flex flex-col">
        <div class="px-6 py-6 border-b border-slate-700">
            <h2 class="text-lg font-bold tracking-tight">Atikha Finance</h2>
            <p class="text-slate-400 text-xs mt-1">Management System</p>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1">
            <a
                href="dashboard.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Dashboard
            </a>
            <a
                href="funds.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Incoming Funds
            </a>
            <a
                href="expenses.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Expenses
            </a>
            <a
                href="ocr_expense.php"
                class="block rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-medium text-white"
            >
                Scan Receipt
            </a>
            <a
                href="reports.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Reports
            </a>
            <?php if ($isAdmin): ?>
                <a
                    href="admin_users.php"
                    class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
                >
                    User Management
                </a>
                <a
                    href="audit_trail.php"
                    class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
                >
                    Audit Trail
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="ml-64 flex flex-col min-h-screen">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Signed in as</p>
                <p class="text-slate-900 font-semibold">
                    <?= $fullName ?>
                    <span class="text-slate-400 font-normal">·</span>
                    <span class="text-slate-600 font-medium text-sm"><?= $role ?></span>
                </p>
            </div>
            <a
                href="logout.php"
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
            >
                Logout
            </a>
        </header>

        <main class="flex-1 p-8 space-y-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Scan Receipt</h1>
                <p class="text-slate-600 mt-2">
                    Photograph a receipt and let AI fill in the expense details for you to review.
                </p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                    <p class="text-sm text-red-600 font-medium">
                        <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                    <p class="text-sm text-emerald-700 font-medium">
                        <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!$aiConfigured): ?>
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                    <p class="text-sm text-amber-800 font-medium">
                        AI extraction is not configured yet.
                    </p>
                    <p class="text-sm text-amber-700 mt-1">
                        Copy <code class="font-mono text-xs bg-amber-100 px-1 py-0.5 rounded">config.example.php</code>
                        to <code class="font-mono text-xs bg-amber-100 px-1 py-0.5 rounded">config.php</code>
                        and add your Gemini API key. Receipts still upload and can be filled in by hand.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($receipt === null): ?>
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-2xl">
                    <h2 class="text-lg font-semibold text-slate-900">Upload Receipt</h2>
                    <p class="text-sm text-slate-600 mt-1">
                        JPG, PNG, WEBP or HEIC, up to 8 MB. On a phone this opens the rear camera.
                    </p>

                    <form
                        method="POST"
                        action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                        enctype="multipart/form-data"
                        class="mt-5 space-y-5"
                        id="receipt-form"
                    >
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <label
                            for="receipt_image"
                            class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center cursor-pointer hover:border-slate-400 hover:bg-slate-100 transition"
                        >
                            <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                            </svg>
                            <span class="text-sm font-medium text-slate-700">
                                Tap to take a photo or choose a file
                            </span>
                            <span class="text-xs text-slate-500" id="file-name">No file selected</span>
                            <input
                                type="file"
                                id="receipt_image"
                                name="receipt_image"
                                accept="image/*"
                                capture="environment"
                                required
                                class="sr-only"
                            >
                        </label>

                        <div class="hidden" id="preview-wrapper">
                            <p class="text-sm font-medium text-slate-700 mb-2">Preview</p>
                            <img id="preview" alt="Selected receipt preview" class="max-h-64 rounded-lg border border-slate-200">
                        </div>

                        <button
                            type="submit"
                            id="submit-button"
                            class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-slate-600 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            Scan Receipt
                        </button>
                    </form>
                </section>
            <?php else: ?>
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Review Extracted Details</h2>
                            <p class="text-sm text-slate-600 mt-0.5">
                                Nothing is saved until you confirm. Correct anything the AI misread.
                            </p>
                        </div>
                        <?php if ($receipt['OCR_Status'] === 'Processed'): ?>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $lowConfidence ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                <?= $confidencePercent ?>% confidence
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-700 px-3 py-1 text-xs font-semibold">
                                Manual entry
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-5 gap-6 p-6">
                        <div class="col-span-2 space-y-3">
                            <img
                                src="<?= htmlspecialchars(receipt_public_url((string) $receipt['File_Path']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="Uploaded receipt"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 object-contain max-h-[28rem]"
                            >
                            <form
                                method="POST"
                                action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                onsubmit="return confirm('Discard this receipt? The image will be deleted.');"
                            >
                                <input type="hidden" name="action" value="discard">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="receipt_id" value="<?= (int) $receipt['ReceiptID'] ?>">
                                <button
                                    type="submit"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                                >
                                    Discard Receipt
                                </button>
                            </form>
                        </div>

                        <div class="col-span-3 space-y-4">
                            <?php if ($lowConfidence && $receipt['OCR_Status'] === 'Processed'): ?>
                                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                                    <p class="text-sm text-amber-800 font-medium">
                                        The AI was unsure about this receipt. Please verify every field carefully.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($extracted['notes'])): ?>
                                <p class="text-sm text-slate-600">
                                    <span class="font-medium text-slate-700">AI note:</span>
                                    <?= htmlspecialchars($extracted['notes'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($missingFields)): ?>
                                <p class="text-sm text-slate-600">
                                    Fields the AI could not read are highlighted and left blank.
                                </p>
                            <?php endif; ?>

                            <form
                                method="POST"
                                action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                class="grid grid-cols-2 gap-4"
                            >
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="receipt_id" value="<?= (int) $receipt['ReceiptID'] ?>">

                                <?php
                                $baseInputClass = 'w-full rounded-lg border px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition';
                                $normalBorder = ' border-slate-300';
                                $missingBorder = ' border-amber-400 bg-amber-50';
                                $payeeClass = $baseInputClass . (in_array('merchant', $missingFields, true) ? $missingBorder : $normalBorder);
                                $amountClass = $baseInputClass . (in_array('total_amount', $missingFields, true) ? $missingBorder : $normalBorder);
                                $dateClass = $baseInputClass . (in_array('transaction_date', $missingFields, true) ? $missingBorder : $normalBorder);
                                ?>

                                <div class="col-span-2">
                                    <label for="payee" class="block text-sm font-medium text-slate-700 mb-1">
                                        Payee / Merchant
                                    </label>
                                    <input
                                        type="text"
                                        id="payee"
                                        name="payee"
                                        required
                                        value="<?= htmlspecialchars($extracted['merchant'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="<?= $payeeClass ?>"
                                        placeholder="Vendor or recipient"
                                    >
                                </div>

                                <div>
                                    <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">
                                        Total Amount
                                    </label>
                                    <input
                                        type="number"
                                        id="amount"
                                        name="amount"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        value="<?= htmlspecialchars($extracted['total_amount'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="<?= $amountClass ?>"
                                        placeholder="0.00"
                                    >
                                </div>

                                <div>
                                    <label for="date_incurred" class="block text-sm font-medium text-slate-700 mb-1">
                                        Date Incurred
                                    </label>
                                    <input
                                        type="date"
                                        id="date_incurred"
                                        name="date_incurred"
                                        required
                                        value="<?= htmlspecialchars($extracted['transaction_date'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="<?= $dateClass ?>"
                                    >
                                </div>

                                <div class="col-span-2">
                                    <label for="category" class="block text-sm font-medium text-slate-700 mb-1">
                                        Category
                                        <span class="text-slate-500 font-normal">(suggested by AI)</span>
                                    </label>
                                    <select
                                        id="category"
                                        name="category"
                                        required
                                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                                    >
                                        <?php foreach ($categories as $cat): ?>
                                            <option
                                                value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $cat === $extracted['category'] ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-span-2 flex items-center gap-3 pt-2">
                                    <button
                                        type="submit"
                                        class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-slate-600 focus:ring-offset-2"
                                    >
                                        Save Expense
                                    </button>
                                    <a
                                        href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900 transition"
                                    >
                                        Scan another receipt
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        (function () {
            const input = document.getElementById('receipt_image');
            if (!input) {
                return;
            }

            const fileName = document.getElementById('file-name');
            const preview = document.getElementById('preview');
            const previewWrapper = document.getElementById('preview-wrapper');
            const form = document.getElementById('receipt-form');
            const submitButton = document.getElementById('submit-button');

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) {
                    fileName.textContent = 'No file selected';
                    previewWrapper.classList.add('hidden');
                    return;
                }

                const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                fileName.textContent = file.name + ' (' + sizeMb + ' MB)';

                if (preview.src) {
                    URL.revokeObjectURL(preview.src);
                }
                preview.src = URL.createObjectURL(file);
                previewWrapper.classList.remove('hidden');
            });

            form.addEventListener('submit', function () {
                submitButton.disabled = true;
                submitButton.textContent = 'Reading receipt…';
            });
        })();
    </script>
</body>
</html>
