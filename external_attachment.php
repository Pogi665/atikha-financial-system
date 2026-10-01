<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/external_mail.php';
require_login();
require_role(['Admin', 'Management'], 'External Email Attachments');
$id = isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); exit('Attachment not found.'); }
try {
    $stmt = $pdo->prepare('SELECT File_Path, Attachment_Name, Attachment_Mime FROM External_Communications WHERE CommunicationID = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || !$row['File_Path']) { http_response_code(404); exit('Attachment not found.'); }
    $path = external_attachment_path($row['File_Path']);
} catch (RuntimeException $e) {
    http_response_code($e instanceof PDOException ? 503 : 404);
    exit('Attachment unavailable.');
}
$name = $row['Attachment_Name'];
$fallback = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: 'attachment';
header('Content-Type: ' . (isset(ALLOWED_BOARD_MIMES[$row['Attachment_Mime']]) ? $row['Attachment_Mime'] : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
