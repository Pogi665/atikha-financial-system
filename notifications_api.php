<?php

/**
 * JSON endpoint for in-app notifications (bell dropdown).
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<string, mixed>|null $data
 */
function notifications_respond(bool $ok, ?array $data, string $error, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notifications_respond(false, null, 'This endpoint accepts POST requests only.', 405);
}

if (empty($_SESSION['UserID'])) {
    notifications_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    notifications_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

$userId = (int) $_SESSION['UserID'];
$action = (string) ($_POST['action'] ?? 'list');

if ($action === 'list') {
    $limit = (int) ($_POST['limit'] ?? 10);
    $rows = notification_fetch_for_user($pdo, $userId, $limit);
    $items = [];

    foreach ($rows as $row) {
        $items[] = [
            'id'         => (int) $row['NotificationID'],
            'message'    => $row['Message'],
            'target_url' => $row['Target_URL'],
            'is_read'    => (bool) $row['Is_Read'],
            'created_at' => $row['Created_At'],
            'time_label' => notification_format_relative_time((string) $row['Created_At']),
        ];
    }

    notifications_respond(true, [
        'items'         => $items,
        'unread_count'  => notification_unread_count($pdo, $userId),
    ], '');
}

if ($action === 'mark_read') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        notifications_respond(false, null, 'Invalid notification.', 400);
    }

    $updated = notification_mark_read($pdo, $userId, $notificationId);
    if (!$updated) {
        notifications_respond(false, null, 'That notification could not be found.', 404);
    }

    notifications_respond(true, [
        'unread_count' => notification_unread_count($pdo, $userId),
    ], '');
}

if ($action === 'mark_all_read') {
    notification_mark_all_read($pdo, $userId);
    notifications_respond(true, ['unread_count' => 0], '');
}

notifications_respond(false, null, 'Unknown action.', 400);
