<?php

/**
 * Scan Receipt workspace.
 *
 * A split screen: the receipt image on the left, the extracted values on the
 * right. Uploading is normally driven by ocr_extract.php over AJAX so the page
 * can show a loading state, but the synchronous 'upload' action below is kept
 * as the no-JavaScript fallback.
 *
 * Saving always goes through this page, so the values a Staff member confirms
 * are validated server-side no matter how they got into the form.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/gemini_client.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/receipts.php';
require_once __DIR__ . '/includes/require_role.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

require_login();
require_role(['Staff', 'Admin'], 'Scan Receipt');

$csrfToken = csrf_token();
$userId = (int) $_SESSION['UserID'];
$categories = fetch_category_names_safe($pdo, CATEGORY_TYPE_EXPENSE);

$errorMessage = '';
$successMessage = '';
$receipt = null;
$extracted = null;

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Your session expired. Please try again.';
    $action = '';
}

// ---------------------------------------------------------------------------
// Upload: the no-JavaScript path. Store the image, record the receipt, then
// hand it to Gemini. With JavaScript this work happens in ocr_extract.php.
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
$hasReceipt = $receipt !== null;
$missingFields = $extracted['missing'] ?? [];
$lowConfidence = $extracted !== null && $extracted['confidence'] < RECEIPT_LOW_CONFIDENCE;
$confidencePercent = $extracted !== null ? (int) round($extracted['confidence'] * 100) : 0;

$isAdmin = ($_SESSION['Role'] ?? '') === 'Admin';
$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');

// Workspace theme tokens. The amber variant marks a field the AI could not read.
$fieldBaseClass = 'w-full rounded-lg border px-4 py-2.5 text-slate-900 placeholder-slate-400'
    . ' focus:ring-2 focus:ring-offset-0 outline-none transition';
$fieldNormalClass = 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500';
$fieldMissingClass = 'border-amber-400 bg-amber-50 focus:border-amber-500 focus:ring-amber-500';
$primaryButtonClass = 'inline-flex items-center justify-center rounded-lg bg-emerald-600 hover:bg-emerald-700'
    . ' text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-emerald-500'
    . ' focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

/**
 * Class list for one extraction field, amber when the AI left it blank.
 */
function ocr_field_class(string $key, array $missing, string $base, string $normal, string $amber): string
{
    return $base . ' ' . (in_array($key, $missing, true) ? $amber : $normal);
}
$activePage = 'ocr_expense';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Receipt — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-50">
    <?php include __DIR__ . '/includes/nav.php'; ?>

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

        <main class="flex-1 p-8 space-y-6">
            <div class="border-l-4 border-emerald-600 pl-4">
                <h1 class="text-2xl font-bold text-slate-900">Scan Receipt</h1>
                <p class="text-slate-600 mt-1">
                    Drop a receipt on the left, then review what the AI read on the right before saving.
                </p>
            </div>

            <div
                id="alert-error"
                class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 <?= $errorMessage === '' ? 'hidden' : '' ?>"
            >
                <p class="text-sm text-red-600 font-medium" id="alert-error-text">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

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

            <div class="grid grid-cols-2 gap-6 items-start">
                <!-- Left: the receipt image -->
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <h2 class="text-base font-semibold text-slate-900">Receipt Image</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            JPG, PNG, WEBP or HEIC, up to 8 MB. On a phone this opens the rear camera.
                        </p>
                    </div>

                    <div class="p-6 space-y-4">
                        <form
                            method="POST"
                            action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                            enctype="multipart/form-data"
                            id="upload-form"
                            class="<?= $hasReceipt ? 'hidden' : '' ?>"
                        >
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                            <label
                                for="receipt_image"
                                id="dropzone"
                                class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/50 transition"
                            >
                                <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                                </svg>
                                <span class="text-sm font-semibold text-slate-700">
                                    Drag a receipt here, or click to browse
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

                            <button
                                type="submit"
                                id="fallback-submit"
                                class="<?= $primaryButtonClass ?> w-full mt-4"
                            >
                                Scan Receipt
                            </button>
                        </form>

                        <div id="preview-shell" class="<?= $hasReceipt ? '' : 'hidden' ?> space-y-3">
                            <img
                                id="preview"
                                <?php if ($hasReceipt): ?>
                                    src="<?= htmlspecialchars(receipt_public_url((string) $receipt['File_Path']), ENT_QUOTES, 'UTF-8') ?>"
                                <?php endif; ?>
                                alt="Receipt preview"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 object-contain max-h-[32rem]"
                            >
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    id="replace-image"
                                    class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition"
                                >
                                    Replace Image
                                </button>
                                <form
                                    method="POST"
                                    action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                    id="discard-form"
                                    class="flex-1"
                                    onsubmit="return confirm('Discard this receipt? The image will be deleted.');"
                                >
                                    <input type="hidden" name="action" value="discard">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input
                                        type="hidden"
                                        name="receipt_id"
                                        id="discard-receipt-id"
                                        value="<?= $hasReceipt ? (int) $receipt['ReceiptID'] : '' ?>"
                                    >
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100 hover:border-red-300 transition"
                                    >
                                        Discard
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Right: the extracted values -->
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Expense Details</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Nothing is saved until you confirm. Correct anything the AI misread.
                            </p>
                        </div>
                        <span
                            id="confidence-badge"
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $hasReceipt ? ($receipt['OCR_Status'] === 'Processed' ? ($lowConfidence ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') : 'bg-slate-100 text-slate-700') : 'hidden' ?>"
                        >
                            <?php if ($hasReceipt): ?>
                                <?= $receipt['OCR_Status'] === 'Processed' ? $confidencePercent . '% confidence' : 'Manual entry' ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Idle -->
                    <div id="state-idle" class="<?= $hasReceipt ? 'hidden' : '' ?> px-6 py-20 text-center">
                        <svg class="mx-auto w-12 h-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <p class="text-sm font-medium text-slate-600 mt-4">Upload a receipt to begin</p>
                        <p class="text-xs text-slate-500 mt-1">
                            The extracted date, payee, amount and category will appear here for review.
                        </p>
                    </div>

                    <!-- Loading -->
                    <div id="state-loading" class="hidden px-6 py-10">
                        <div class="flex items-center gap-3">
                            <svg class="animate-spin h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="text-sm font-medium text-slate-700">Reading receipt&hellip;</p>
                        </div>
                        <div class="mt-6 space-y-4 animate-pulse">
                            <div class="h-3 w-24 rounded bg-slate-200"></div>
                            <div class="h-10 rounded-lg bg-slate-100"></div>
                            <div class="h-3 w-32 rounded bg-slate-200"></div>
                            <div class="h-10 rounded-lg bg-slate-100"></div>
                            <div class="h-3 w-20 rounded bg-slate-200"></div>
                            <div class="h-10 rounded-lg bg-slate-100"></div>
                        </div>
                    </div>

                    <!-- Ready -->
                    <div id="state-form" class="<?= $hasReceipt ? '' : 'hidden' ?> p-6 space-y-4">
                        <div
                            id="ai-warning"
                            class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 <?= ($hasReceipt && $lowConfidence && $receipt['OCR_Status'] === 'Processed') ? '' : 'hidden' ?>"
                        >
                            <p class="text-sm text-amber-800 font-medium" id="ai-warning-text">
                                The AI was unsure about this receipt. Please verify every field carefully.
                            </p>
                        </div>

                        <p
                            id="ai-note"
                            class="text-sm text-slate-600 <?= !empty($extracted['notes']) ? '' : 'hidden' ?>"
                        >
                            <span class="font-medium text-slate-700">AI note:</span>
                            <span id="ai-note-text"><?= htmlspecialchars($extracted['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </p>

                        <form
                            method="POST"
                            action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                            id="save-form"
                            class="grid grid-cols-2 gap-4"
                        >
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input
                                type="hidden"
                                name="receipt_id"
                                id="save-receipt-id"
                                value="<?= $hasReceipt ? (int) $receipt['ReceiptID'] : '' ?>"
                            >

                            <div>
                                <label for="date_incurred" class="block text-sm font-medium text-slate-700 mb-1">
                                    Date Incurred
                                </label>
                                <input
                                    type="date"
                                    id="date_incurred"
                                    name="date_incurred"
                                    required
                                    value="<?= htmlspecialchars($extracted['transaction_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    class="<?= ocr_field_class('transaction_date', $missingFields, $fieldBaseClass, $fieldNormalClass, $fieldMissingClass) ?>"
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
                                    value="<?= htmlspecialchars($extracted['total_amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    class="<?= ocr_field_class('total_amount', $missingFields, $fieldBaseClass, $fieldNormalClass, $fieldMissingClass) ?>"
                                    placeholder="0.00"
                                >
                            </div>

                            <div class="col-span-2">
                                <label for="payee" class="block text-sm font-medium text-slate-700 mb-1">
                                    Payee / Merchant
                                </label>
                                <input
                                    type="text"
                                    id="payee"
                                    name="payee"
                                    required
                                    maxlength="255"
                                    value="<?= htmlspecialchars($extracted['merchant'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    class="<?= ocr_field_class('merchant', $missingFields, $fieldBaseClass, $fieldNormalClass, $fieldMissingClass) ?>"
                                    placeholder="Vendor or recipient"
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
                                    class="<?= $fieldBaseClass . ' ' . $fieldNormalClass ?>"
                                >
                                    <?php foreach ($categories as $cat): ?>
                                        <option
                                            value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $cat === ($extracted['category'] ?? '') ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-span-2 flex items-center gap-3 pt-2">
                                <button
                                    type="submit"
                                    id="save-button"
                                    class="<?= $primaryButtonClass ?>"
                                >
                                    Confirm &amp; Save Expense
                                </button>
                                <a
                                    href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="text-sm font-medium text-slate-600 hover:text-emerald-700 transition"
                                >
                                    Scan another receipt
                                </a>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const endpoint = 'ocr_extract.php';
            const maxBytes = <?= MAX_RECEIPT_BYTES ?>;
            const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            const uploadForm = document.getElementById('upload-form');
            const fileInput = document.getElementById('receipt_image');
            const dropzone = document.getElementById('dropzone');
            const fileName = document.getElementById('file-name');
            const fallbackSubmit = document.getElementById('fallback-submit');
            const previewShell = document.getElementById('preview-shell');
            const preview = document.getElementById('preview');
            const replaceButton = document.getElementById('replace-image');
            const discardReceiptId = document.getElementById('discard-receipt-id');

            const stateIdle = document.getElementById('state-idle');
            const stateLoading = document.getElementById('state-loading');
            const stateForm = document.getElementById('state-form');
            const confidenceBadge = document.getElementById('confidence-badge');
            const aiWarning = document.getElementById('ai-warning');
            const aiWarningText = document.getElementById('ai-warning-text');
            const aiNote = document.getElementById('ai-note');
            const aiNoteText = document.getElementById('ai-note-text');
            const alertError = document.getElementById('alert-error');
            const alertErrorText = document.getElementById('alert-error-text');

            const saveForm = document.getElementById('save-form');
            const saveButton = document.getElementById('save-button');
            const saveReceiptId = document.getElementById('save-receipt-id');
            const fieldPayee = document.getElementById('payee');
            const fieldAmount = document.getElementById('amount');
            const fieldDate = document.getElementById('date_incurred');
            const fieldCategory = document.getElementById('category');

            const missingClasses = ['border-amber-400', 'bg-amber-50', 'focus:border-amber-500', 'focus:ring-amber-500'];
            const normalClasses = ['border-slate-300', 'focus:border-emerald-500', 'focus:ring-emerald-500'];

            // A receipt row already exists once the image is stored, so leaving
            // without saving would strand it.
            let hasUnsavedReceipt = <?= $hasReceipt ? 'true' : 'false' ?>;
            let objectUrl = null;
            let busy = false;

            // JavaScript takes over the upload, so the synchronous fallback
            // button is only for browsers that never run this script.
            fallbackSubmit.classList.add('hidden');

            function showError(message) {
                alertErrorText.textContent = message;
                alertError.classList.remove('hidden');
            }

            function clearError() {
                alertError.classList.add('hidden');
            }

            function setFieldMissing(field, isMissing) {
                if (!field) {
                    return;
                }
                field.classList.remove(...missingClasses, ...normalClasses);
                field.classList.add(...(isMissing ? missingClasses : normalClasses));
            }

            function showState(name) {
                stateIdle.classList.toggle('hidden', name !== 'idle');
                stateLoading.classList.toggle('hidden', name !== 'loading');
                stateForm.classList.toggle('hidden', name !== 'form');
            }

            function handleFile(file) {
                if (!file || busy) {
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    showError('Only JPG, PNG, WEBP or HEIC images are accepted.');
                    return;
                }

                if (file.size > maxBytes) {
                    showError('That image is too large. Please upload a receipt photo under 8 MB.');
                    return;
                }

                clearError();

                const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                fileName.textContent = file.name + ' (' + sizeMb + ' MB)';

                // Paint the local image first so the receipt is visible while
                // the request is still in flight.
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }
                objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                uploadForm.classList.add('hidden');
                previewShell.classList.remove('hidden');

                upload(file);
            }

            function upload(file) {
                busy = true;
                showState('loading');
                confidenceBadge.classList.add('hidden');
                saveButton.disabled = true;

                const payload = new FormData();
                payload.append('receipt_image', file);
                payload.append('csrf_token', csrfToken);

                fetch(endpoint, {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().catch(function () {
                            throw new Error('The server returned an unreadable response.');
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error(result.error || 'The receipt could not be read.');
                        }
                        applyExtraction(result.data);
                    })
                    .catch(function (error) {
                        showError(error.message || 'The upload failed. Please check your connection and try again.');
                        resetToUpload();
                    })
                    .finally(function () {
                        busy = false;
                    });
            }

            function applyExtraction(data) {
                hasUnsavedReceipt = true;

                saveReceiptId.value = data.receipt_id;
                discardReceiptId.value = data.receipt_id;
                preview.src = data.image_url;

                fieldPayee.value = data.payee || '';
                fieldAmount.value = data.amount || '';
                fieldDate.value = data.date_incurred || '';
                if (data.category) {
                    fieldCategory.value = data.category;
                }

                const missing = Array.isArray(data.missing) ? data.missing : [];
                setFieldMissing(fieldPayee, missing.indexOf('merchant') !== -1);
                setFieldMissing(fieldAmount, missing.indexOf('total_amount') !== -1);
                setFieldMissing(fieldDate, missing.indexOf('transaction_date') !== -1);

                confidenceBadge.classList.remove(
                    'hidden',
                    'bg-emerald-100', 'text-emerald-800',
                    'bg-amber-100', 'text-amber-800',
                    'bg-slate-100', 'text-slate-700'
                );

                if (data.status === 'Processed') {
                    confidenceBadge.textContent = Math.round(data.confidence * 100) + '% confidence';
                    confidenceBadge.classList.add(
                        ...(data.low_confidence
                            ? ['bg-amber-100', 'text-amber-800']
                            : ['bg-emerald-100', 'text-emerald-800'])
                    );
                } else {
                    confidenceBadge.textContent = 'Manual entry';
                    confidenceBadge.classList.add('bg-slate-100', 'text-slate-700');
                }

                if (data.warning) {
                    aiWarningText.textContent = data.warning;
                    aiWarning.classList.remove('hidden');
                } else if (data.low_confidence && data.status === 'Processed') {
                    aiWarningText.textContent = 'The AI was unsure about this receipt. Please verify every field carefully.';
                    aiWarning.classList.remove('hidden');
                } else {
                    aiWarning.classList.add('hidden');
                }

                if (data.notes) {
                    aiNoteText.textContent = data.notes;
                    aiNote.classList.remove('hidden');
                } else {
                    aiNote.classList.add('hidden');
                }

                saveButton.disabled = false;
                showState('form');
            }

            function resetToUpload() {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
                preview.removeAttribute('src');
                fileInput.value = '';
                fileName.textContent = 'No file selected';
                previewShell.classList.add('hidden');
                uploadForm.classList.remove('hidden');
                confidenceBadge.classList.add('hidden');
                showState('idle');
            }

            fileInput.addEventListener('change', function () {
                handleFile(fileInput.files && fileInput.files[0]);
            });

            ['dragenter', 'dragover'].forEach(function (name) {
                dropzone.addEventListener(name, function (event) {
                    event.preventDefault();
                    dropzone.classList.add('border-emerald-500', 'bg-emerald-50');
                });
            });

            ['dragleave', 'drop'].forEach(function (name) {
                dropzone.addEventListener(name, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove('border-emerald-500', 'bg-emerald-50');
                });
            });

            dropzone.addEventListener('drop', function (event) {
                const files = event.dataTransfer && event.dataTransfer.files;
                if (files && files.length > 0) {
                    handleFile(files[0]);
                }
            });

            // Dropping anywhere else should not make the browser navigate to
            // the file.
            ['dragover', 'drop'].forEach(function (name) {
                window.addEventListener(name, function (event) {
                    if (!dropzone.contains(event.target)) {
                        event.preventDefault();
                    }
                });
            });

            replaceButton.addEventListener('click', function () {
                fileInput.click();
            });

            saveForm.addEventListener('submit', function () {
                hasUnsavedReceipt = false;
                saveButton.disabled = true;
                saveButton.textContent = 'Saving…';
            });

            document.getElementById('discard-form').addEventListener('submit', function () {
                hasUnsavedReceipt = false;
            });

            window.addEventListener('beforeunload', function (event) {
                if (!hasUnsavedReceipt) {
                    return;
                }
                event.preventDefault();
                event.returnValue = '';
            });
        })();
    </script>
</body>
</html>
