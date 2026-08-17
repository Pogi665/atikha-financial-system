<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/board_uploads.php';
require_once __DIR__ . '/includes/require_role.php';

require_login();

$communicationId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['UserID'];
$role = (string) ($_SESSION['Role'] ?? '');

if ($communicationId <= 0) {
    http_response_code(404);
    exit('Attachment not found.');
}

try {
    $stmt = $pdo->prepare(
        'SELECT CommunicationID, Sender_UserID, File_Path
         FROM Board_Communications
         WHERE CommunicationID = :id'
    );
    $stmt->execute(['id' => $communicationId]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Unable to load attachment.');
}

if ($row === false || empty($row['File_Path'])) {
    http_response_code(404);
    exit('Attachment not found.');
}

$isOwner = (int) $row['Sender_UserID'] === $userId;
$isManagement = $role === 'Management';

if (!$isOwner && !$isManagement) {
    http_response_code(403);
    exit('Access denied.');
}

$absolutePath = board_absolute_path((string) $row['File_Path']);
if (!is_file($absolutePath)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detected = finfo_file($finfo, $absolutePath);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

$filename = basename($absolutePath);
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($absolutePath));
readfile($absolutePath);
exit;
