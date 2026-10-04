<?php

/**
 * Receipt image storage and lookup.
 *
 * Shared by ocr_expense.php (the review workspace) and ocr_extract.php (the
 * AJAX upload endpoint) so both go through the same hardened upload path.
 */

// CLI/isolated HTTP fixtures may define a private evidence root before loading.
if (!defined('RECEIPT_UPLOAD_DIR')) { define('RECEIPT_UPLOAD_DIR', __DIR__ . '/../uploads/receipts'); }
const RECEIPT_PUBLIC_DIR = 'uploads/receipts';
const MAX_RECEIPT_BYTES = 8 * 1024 * 1024;
const MAX_RECEIPT_PIXELS = 20000000;

// Real MIME types, read from the file contents rather than the client's header.
const ALLOWED_RECEIPT_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

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
    $size = (int) @filesize($tmpPath);
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
        $result['error'] = 'Only JPG, PNG or WebP images are accepted. Convert HEIC/HEIF first.';

        return $result;
    }

    // Bound dimensions BEFORE GD allocates decoded pixels.
    {
        $dimensions = @getimagesize($tmpPath);
        if ($dimensions === false
            || empty($dimensions[0])
            || empty($dimensions[1])
            || (int) $dimensions[0] < 32
            || (int) $dimensions[1] < 32
            || (int) $dimensions[0] > intdiv(MAX_RECEIPT_PIXELS, (int) $dimensions[1])
        ) {
            $result['error'] = 'That file is not a readable image. Please upload a clear receipt photo.';

            return $result;
        }
        if (!function_exists('imagecreatefromstring')) {
            $result['error'] = 'Receipt image validation is unavailable. Contact your administrator.';
            return $result;
        }
        $decoded = @imagecreatefromstring((string) file_get_contents($tmpPath));
        if ($decoded === false) {
            $result['error'] = 'That image could not be decoded. Upload a readable receipt photo.';
            return $result;
        }
        imagedestroy($decoded);
    }

    if (!is_dir(RECEIPT_UPLOAD_DIR) && !@mkdir(RECEIPT_UPLOAD_DIR, 0755, true) && !is_dir(RECEIPT_UPLOAD_DIR)) {
        error_log('Unable to create receipt upload directory: ' . RECEIPT_UPLOAD_DIR);
        $result['error'] = 'The server could not store the image. Please contact your administrator.';

        return $result;
    }
    if (!is_file(RECEIPT_UPLOAD_DIR . '/.htaccess')
        || !str_contains((string) file_get_contents(RECEIPT_UPLOAD_DIR . '/.htaccess'), 'Require all denied')) {
        $result['error'] = 'Protected receipt storage is unavailable. Contact your administrator.';
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
        'public_path' => '',
        'mime'        => $mime,
        'size'        => $size,
        'original'    => substr($originalName, 0, 255),
        'sha256'      => hash_file('sha256', $destination),
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

/** Caller must validate the active session. All three authorization conditions apply. */
function load_transaction_receipt(PDO $pdo, int $receiptId, int $expenseId, int $userId, string $role): ?array
{
    if ($role !== 'Admin' || $userId <= 0 || $receiptId <= 0 || $expenseId <= 0) { return null; }
    $stmt = $pdo->prepare('SELECT r.File_Path, r.Mime_Type FROM Receipts r
        INNER JOIN Expenses e ON e.ExpenseID = r.ExpenseID
        WHERE r.ReceiptID = :receipt AND r.ExpenseID = :expense AND r.UploadedBy_UserID = :user');
    $stmt->execute(['receipt' => $receiptId, 'expense' => $expenseId, 'user' => $userId]);
    return $stmt->fetch() ?: null;
}

/** Reject tampered paths and symlinks outside the receipt directory. */
function transaction_receipt_path(string $storedPath): ?string
{
    if (!preg_match('~\Auploads/receipts/([a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp|heic|heif))\z~D', $storedPath, $matches)) { return null; }
    $root = realpath(RECEIPT_UPLOAD_DIR);
    $resolved = realpath(RECEIPT_UPLOAD_DIR . DIRECTORY_SEPARATOR . $matches[1]);
    if ($root === false || $resolved === false || !is_file($resolved) || !is_readable($resolved)) { return null; }
    $prefix = $root . DIRECTORY_SEPARATOR;
    return strncasecmp($resolved, $prefix, strlen($prefix)) === 0 ? $resolved : null;
}
