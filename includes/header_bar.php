<?php

/**
 * Shared authenticated header with notification bell.
 *
 * Expects $pdo (PDO) and optionally $csrfToken before include.
 */

global $pdo;

if (!isset($pdo)) {
    require_once __DIR__ . '/../db_connect.php';
}

if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/csrf.php';
}

require_once __DIR__ . '/notifications.php';

require_once __DIR__ . '/layout.php';

$flags = layout_role_flags();
$userId = (int) ($_SESSION['UserID'] ?? 0);
$headerCsrfToken = $csrfToken ?? csrf_token();
$headerUnreadCount = notification_unread_count($pdo, $userId);
$headerNotifications = notification_fetch_for_user($pdo, $userId, 10);
$headerIsExecutive = $flags['isExecutive'];
$headerBellRingClass = $headerIsExecutive ? 'text-slate-600 hover:text-blue-900' : 'text-slate-600 hover:text-slate-900';

?>
<header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between print:hidden">
    <div>
        <p class="text-sm text-slate-500">Signed in as</p>
        <p class="text-slate-900 font-semibold">
            <?= $flags['fullName'] ?>
            <span class="text-slate-400 font-normal">·</span>
            <span class="text-slate-600 font-medium text-sm"><?= $flags['roleLabel'] ?></span>
        </p>
    </div>

    <div class="flex items-center gap-3">
        <div
            class="js-notification-bell relative"
            data-csrf="<?= htmlspecialchars($headerCsrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >
            <button
                type="button"
                class="js-notif-toggle relative inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white p-2.5 <?= $headerBellRingClass ?> transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                aria-label="Notifications"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="js-notif-badge absolute -top-1 -right-1 min-w-[1.25rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-5 text-white <?= $headerUnreadCount > 0 ? '' : 'hidden' ?>">
                    <?= $headerUnreadCount > 99 ? '99+' : (int) $headerUnreadCount ?>
                </span>
            </button>

            <div class="js-notif-menu hidden absolute right-0 z-50 mt-2 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">Notifications</p>
                </div>
                <div class="js-notif-list max-h-80 overflow-y-auto">
                    <?php if ($headerNotifications === []): ?>
                        <p class="js-notif-empty px-4 py-6 text-sm text-slate-500 text-center">No notifications yet.</p>
                    <?php else: ?>
                        <p class="js-notif-empty hidden px-4 py-6 text-sm text-slate-500 text-center">No notifications yet.</p>
                        <?php foreach ($headerNotifications as $notification): ?>
                            <?php
                            $isRead = (bool) $notification['Is_Read'];
                            $textClass = $isRead ? 'text-slate-600' : 'text-slate-900 font-medium';
                            ?>
                            <button
                                type="button"
                                class="js-notif-item w-full text-left px-4 py-3 border-b border-slate-100 hover:bg-slate-50 transition"
                                data-id="<?= (int) $notification['NotificationID'] ?>"
                                data-url="<?= htmlspecialchars((string) $notification['Target_URL'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <p class="text-sm <?= $textClass ?>">
                                    <?= htmlspecialchars((string) $notification['Message'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-xs text-slate-400 mt-1">
                                    <?= htmlspecialchars(notification_format_relative_time((string) $notification['Created_At']), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <a
            href="logout.php"
            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
        >
            Logout
        </a>
    </div>
</header>
