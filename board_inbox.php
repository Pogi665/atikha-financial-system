<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/review_ui.php';

require_login();
require_role(['Management'], 'Board Inbox');

$activePage = 'board_inbox';
$flags = layout_role_flags();
$isExecutive = $flags['isExecutive'];
$csrfToken = csrf_token();

$filter = (string) ($_GET['filter'] ?? 'pending');
if (!in_array($filter, ['pending', 'reviewed', 'all'], true)) {
    $filter = 'pending';
}

$detailId = (int) ($_GET['id'] ?? 0);
$messages = [];
$detail = null;

try {
    $sql = 'SELECT b.CommunicationID, b.Subject, b.Message_Body, b.File_Path,
                   b.Review_Status, b.Created_At, u.FullName AS SenderName
            FROM Board_Communications b
            INNER JOIN Users u ON u.UserID = b.Sender_UserID';
    if ($filter === 'pending') {
        $sql .= " WHERE b.Review_Status = 'Requested'";
    } elseif ($filter === 'reviewed') {
        $sql .= " WHERE b.Review_Status = 'Reviewed'";
    }
    $sql .= ' ORDER BY b.Created_At DESC LIMIT 100';
    $messages = $pdo->query($sql)->fetchAll() ?: [];

    if ($detailId > 0) {
        $stmt = $pdo->prepare(
            'SELECT b.CommunicationID, b.Subject, b.Message_Body, b.File_Path,
                    b.Review_Status, b.Created_At, u.FullName AS SenderName
             FROM Board_Communications b
             INNER JOIN Users u ON u.UserID = b.Sender_UserID
             WHERE b.CommunicationID = :id'
        );
        $stmt->execute(['id' => $detailId]);
        $detail = $stmt->fetch() ?: null;
    }
} catch (PDOException $e) {
    error_log('Board inbox query failed: ' . $e->getMessage());
}

$cardClass = $isExecutive ? 'exec-card' : 'bg-white rounded-xl border border-slate-200 shadow-sm p-6';
$btnPrimary = $isExecutive ? 'exec-btn-primary' : 'rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2 px-4 text-sm transition';

layout_begin('Board Inbox', $activePage);
?>

<div class="js-review-root" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div id="review-flash" class="hidden"></div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Board Inbox</h1>
            <p class="text-slate-600 mt-2">Internal messages submitted by Staff and Administrators.</p>
        </div>
        <div class="flex gap-2">
            <a href="board_inbox.php?filter=pending" class="<?= $filter === 'pending' ? $btnPrimary : 'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition' ?>">Pending</a>
            <a href="board_inbox.php?filter=reviewed" class="<?= $filter === 'reviewed' ? $btnPrimary : 'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition' ?>">Reviewed</a>
            <a href="board_inbox.php?filter=all" class="<?= $filter === 'all' ? $btnPrimary : 'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition' ?>">All</a>
            <a href="management_reviews.php" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">Review Queue</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <section class="<?= $cardClass ?> lg:col-span-1">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Messages</h2>
            <?php if ($messages === []): ?>
                <p class="text-sm text-slate-500 italic">No messages in this view.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($messages as $message): ?>
                        <?php $isActive = $detailId === (int) $message['CommunicationID']; ?>
                        <a
                            href="board_inbox.php?filter=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>&id=<?= (int) $message['CommunicationID'] ?>"
                            class="block rounded-lg border px-4 py-3 transition <?= $isActive ? 'border-blue-800 bg-blue-50' : 'border-slate-200 hover:bg-slate-50' ?>"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-slate-900 text-sm"><?= htmlspecialchars($message['Subject'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?= review_render_status_badge((string) $message['Review_Status']) ?>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                <?= htmlspecialchars($message['SenderName'], ENT_QUOTES, 'UTF-8') ?>
                                · <?= htmlspecialchars(date('M j, Y', strtotime($message['Created_At'])), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="<?= $cardClass ?> lg:col-span-2">
            <?php if ($detail === null): ?>
                <p class="text-sm text-slate-500">Select a message to read its contents.</p>
            <?php else: ?>
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900"><?= htmlspecialchars($detail['Subject'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="text-sm text-slate-500 mt-1">
                            From <?= htmlspecialchars($detail['SenderName'], ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars(date('F j, Y g:i A', strtotime($detail['Created_At'])), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <?= review_render_status_badge((string) $detail['Review_Status']) ?>
                </div>

                <div class="prose prose-sm max-w-none">
                    <p class="text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($detail['Message_Body'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <?php if (!empty($detail['File_Path'])): ?>
                    <p class="mt-4">
                        <a href="board_attachment.php?id=<?= (int) $detail['CommunicationID'] ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                            Download attachment
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($detail['Review_Status'] === 'Requested'): ?>
                    <div class="mt-6 border-t border-slate-200 pt-4">
                        <label for="review-notes" class="block text-sm font-medium text-slate-700 mb-1">Review notes (optional)</label>
                        <textarea id="review-notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Optional notes for the sender"></textarea>
                        <button
                            type="button"
                            class="js-mark-reviewed mt-3 <?= $btnPrimary ?>"
                            data-entity-type="board"
                            data-entity-id="<?= (int) $detail['CommunicationID'] ?>"
                        >
                            Mark as Reviewed
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
layout_end('<script src="assets/js/review_actions.js"></script>');
?>
