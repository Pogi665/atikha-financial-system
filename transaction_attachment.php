<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/receipts.php';

require_login(); // Revalidates existence, active status and role against Users.
if ($_SESSION['Role'] !== 'Admin') { http_response_code(403); exit('Access denied.'); }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit('This endpoint is read-only.');
}
$expenseId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$receiptId = filter_var($_GET['receipt_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (($_GET['type'] ?? null) !== 'Expense' || !$expenseId || !$receiptId) {
    http_response_code(400); exit('Invalid transaction identifiers.');
}
try {
    $receipt = load_transaction_receipt($pdo, $receiptId, $expenseId, (int) $_SESSION['UserID'], $_SESSION['Role']);
    if (!$receipt) { http_response_code(404); exit('Supporting document not found or access restricted.'); }
    $path = transaction_receipt_path($receipt['File_Path']);
    if (!$path) { http_response_code(404); exit('Supporting document unavailable.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!isset(ALLOWED_RECEIPT_MIMES[$mime])) { http_response_code(415); exit('Supporting document unavailable.'); }
    $inline = !isset($_GET['download']) && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { readfile($path); }
} catch (Throwable $e) {
    error_log('Transaction attachment failed: ' . $e->getMessage());
    http_response_code(500); exit('Unable to load supporting document.');
}
