<?php

/**
 * In-app notification helpers.
 *
 * Role-targeted alerts fan out to one row per user so Is_Read stays per-user.
 */

const NOTIFICATION_ROLE_MANAGEMENT = 'Management';
const NOTIFICATION_ROLE_ADMIN = 'Admin';
const NOTIFICATION_ROLE_STAFF = 'Staff';

const EXPENSE_LARGE_AMOUNT_LIMIT = 50000.00;
const BUDGET_WARNING_PCT = 90.0;

/**
 * Create one or more notification rows.
 *
 * @param int|null $userId  Direct recipient; ignored when $role is set.
 * @param string|null $role Fan-out to all users with this role.
 */
function notification_create(
    PDO $pdo,
    ?int $userId,
    ?string $role,
    string $message,
    string $targetUrl = ''
): bool {
    $message = mb_substr(trim($message), 0, 500);
    if ($message === '') {
        return false;
    }

    $targetUrl = mb_substr(trim($targetUrl), 0, 255);

    try {
        if ($role !== null && $role !== '') {
            $stmt = $pdo->prepare(
                'SELECT UserID FROM Users WHERE Role = :role ORDER BY UserID ASC'
            );
            $stmt->execute(['role' => $role]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($userIds === []) {
                return false;
            }

            $insert = $pdo->prepare(
                'INSERT INTO Notifications
                    (Recipient_UserID, Recipient_Role, Message, Target_URL)
                 VALUES
                    (:user_id, NULL, :message, :target_url)'
            );

            foreach ($userIds as $recipientId) {
                $insert->execute([
                    'user_id'    => (int) $recipientId,
                    'message'    => $message,
                    'target_url' => $targetUrl,
                ]);
            }

            return true;
        }

        if ($userId === null || $userId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO Notifications
                (Recipient_UserID, Recipient_Role, Message, Target_URL)
             VALUES
                (:user_id, NULL, :message, :target_url)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'message'    => $message,
            'target_url' => $targetUrl,
        ]);

        return true;
    } catch (PDOException $e) {
        error_log('Notification insert failed: ' . $e->getMessage());

        return false;
    }
}

/**
 * Notify all Management users.
 */
function notification_notify_management(PDO $pdo, string $message, string $targetUrl = ''): bool
{
    return notification_create($pdo, null, NOTIFICATION_ROLE_MANAGEMENT, $message, $targetUrl);
}

/**
 * @return array<int, array<string, mixed>>
 */
function notification_fetch_for_user(PDO $pdo, int $userId, int $limit = 10): array
{
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    try {
        $stmt = $pdo->prepare(
            'SELECT NotificationID, Message, Target_URL, Is_Read, Created_At
             FROM Notifications
             WHERE Recipient_UserID = :user_id
             ORDER BY Created_At DESC, NotificationID DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Notification fetch failed: ' . $e->getMessage());

        return [];
    }
}

function notification_unread_count(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM Notifications
             WHERE Recipient_UserID = :user_id AND Is_Read = 0'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function notification_mark_read(PDO $pdo, int $userId, int $notificationId): bool
{
    if ($userId <= 0 || $notificationId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE Notifications
             SET Is_Read = 1
             WHERE NotificationID = :notification_id AND Recipient_UserID = :user_id'
        );
        $stmt->execute([
            'notification_id' => $notificationId,
            'user_id'         => $userId,
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Notification mark read failed: ' . $e->getMessage());

        return false;
    }
}

function notification_mark_all_read(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE Notifications SET Is_Read = 1
             WHERE Recipient_UserID = :user_id AND Is_Read = 0'
        );
        $stmt->execute(['user_id' => $userId]);

        return true;
    } catch (PDOException $e) {
        error_log('Notification mark all read failed: ' . $e->getMessage());

        return false;
    }
}

function notification_format_relative_time(string $timestamp): string
{
    $time = strtotime($timestamp);
    if ($time === false) {
        return '';
    }

    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);

        return $mins . ' min' . ($mins === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);

        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    return date('M j, Y g:i A', $time);
}

function notification_review_status_label(string $status): string
{
    switch ($status) {
        case 'Requested':
            return 'Pending Review';
        case 'Reviewed':
            return 'Reviewed';
        default:
            return 'None';
    }
}

function notification_review_status_badge_class(string $status): string
{
    switch ($status) {
        case 'Requested':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'Reviewed':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
}
