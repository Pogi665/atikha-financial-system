<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/board_uploads.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/review_ui.php';

require_login();
require_role(['Staff', 'Admin'], 'Message the Board');

$userId = (int) $_SESSION['UserID'];
$csrfToken = csrf_token();
$activePage = 'board_messages';

$errorMessage = '';
$successMessage = '';

if (isset($_GET['sent'])) {
    $successMessage = 'Your message was sent to Management for review.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session expired. Please try again.';
    } else {
        $subject = isset($_POST['subject']) ? trim((string) $_POST['subject']) : '';
        $messageBody = isset($_POST['message_body']) ? trim((string) $_POST['message_body']) : '';

        if ($subject === '' || $messageBody === '') {
            $errorMessage = 'Please enter both a subject and message body.';
        } else {
            $filePath = null;

            if (isset($_FILES['attachment']) && is_array($_FILES['attachment'])) {
                $upload = store_uploaded_board_file($_FILES['attachment']);
                if ($upload['ok']) {
                    $filePath = $upload['path'];
                } elseif (($upload['error'] ?? '') !== '') {
                    $errorMessage = $upload['error'];
                }
            }

            if ($errorMessage === '') {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO Board_Communications
                            (Sender_UserID, Subject, Message_Body, File_Path, Review_Status)
                         VALUES
                            (:sender_id, :subject, :message_body, :file_path, :review_status)'
                    );
                    $stmt->execute([
                        'sender_id'     => $userId,
                        'subject'       => mb_substr($subject, 0, 255),
                        'message_body'  => $messageBody,
                        'file_path'     => $filePath,
                        'review_status' => 'Requested',
                    ]);

                    $communicationId = (int) $pdo->lastInsertId();

                    notification_notify_management(
                        $pdo,
                        'New board message: ' . mb_substr($subject, 0, 200),
                        'board_inbox.php?id=' . $communicationId
                    );

                    log_system_action(
                        $pdo,
                        $userId,
                        AUDIT_ACTION_REVIEW_REQUEST,
                        'Board_Communications',
                        $communicationId,
                        null,
                        ['subject' => $subject, 'has_attachment' => $filePath !== null]
                    );

                    header('Location: ' . $_SERVER['PHP_SELF'] . '?sent=1');
                    exit;
                } catch (PDOException $e) {
                    error_log('Board message insert failed: ' . $e->getMessage());
                    $errorMessage = 'Unable to send your message. Please try again.';
                }
            }
        }
    }
}

$sentMessages = [];
try {
    $stmt = $pdo->prepare(
        'SELECT CommunicationID, Subject, Message_Body, File_Path, Review_Status, Created_At
         FROM Board_Communications
         WHERE Sender_UserID = :user_id
         ORDER BY Created_At DESC
         LIMIT 50'
    );
    $stmt->execute(['user_id' => $userId]);
    $sentMessages = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Board sent messages query failed: ' . $e->getMessage());
}

$fieldClass = 'w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400'
    . ' focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-0 outline-none transition';
$primaryButtonClass = 'inline-flex items-center justify-center rounded-lg bg-emerald-600 hover:bg-emerald-700'
    . ' text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message the Board — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-50">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="ml-64 flex flex-col min-h-screen">
        <?php include __DIR__ . '/includes/header_bar.php'; ?>

        <main class="flex-1 p-8 space-y-6">
            <div class="border-l-4 border-emerald-600 pl-4">
                <h1 class="text-2xl font-bold text-slate-900">Message the Board</h1>
                <p class="text-slate-600 mt-1">Send an internal message to Management with an optional attachment.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                    <p class="text-sm text-red-600 font-medium"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                    <p class="text-sm text-emerald-700 font-medium"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">Compose Message</h2>
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div>
                            <label for="subject" class="block text-sm font-medium text-slate-700 mb-1">Subject</label>
                            <input type="text" id="subject" name="subject" required maxlength="255" class="<?= $fieldClass ?>" placeholder="Brief subject line">
                        </div>

                        <div>
                            <label for="message_body" class="block text-sm font-medium text-slate-700 mb-1">Message Body</label>
                            <textarea id="message_body" name="message_body" required rows="8" class="<?= $fieldClass ?>" placeholder="Write your message to Management..."></textarea>
                        </div>

                        <div>
                            <label for="attachment" class="block text-sm font-medium text-slate-700 mb-1">
                                Attachment <span class="text-slate-400 font-normal">(optional, max 8 MB)</span>
                            </label>
                            <input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,application/pdf,image/jpeg,image/png" class="block w-full text-sm text-slate-600">
                            <p class="text-xs text-slate-500 mt-1">PDF, JPG, PNG, DOC, or DOCX</p>
                        </div>

                        <button type="submit" class="<?= $primaryButtonClass ?>">Send to Management</button>
                    </form>
                </section>

                <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <h2 class="text-lg font-semibold text-slate-900">Your Sent Messages</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if ($sentMessages === []): ?>
                            <p class="px-6 py-8 text-sm text-slate-500 italic">You have not sent any messages yet.</p>
                        <?php else: ?>
                            <?php foreach ($sentMessages as $message): ?>
                                <article class="px-6 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-medium text-slate-900"><?= htmlspecialchars($message['Subject'], ENT_QUOTES, 'UTF-8') ?></h3>
                                            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($message['Created_At'])), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <?= review_render_status_badge((string) $message['Review_Status']) ?>
                                    </div>
                                    <p class="text-sm text-slate-600 mt-2 line-clamp-3"><?= htmlspecialchars($message['Message_Body'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if (!empty($message['File_Path'])): ?>
                                        <p class="mt-2">
                                            <a href="board_attachment.php?id=<?= (int) $message['CommunicationID'] ?>" class="text-sm text-emerald-700 hover:underline">View attachment</a>
                                        </p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="assets/js/notifications.js"></script>
</body>
</html>
