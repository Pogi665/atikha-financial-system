<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/user_identities.php';
require_once __DIR__ . '/includes/external_mail.php';
require_login();
require_role(['Admin', 'Management'], 'External Email');
if (is_file(__DIR__ . '/.migration-private/external-email-maintenance.flag')) {
    http_response_code(503);
    header('Retry-After: 60');
    exit('External Email is temporarily unavailable during maintenance.');
}

$activePage = 'external_email';
$userId = (int) $_SESSION['UserID'];
$csrfToken = csrf_token();
$folder = ($_GET['folder'] ?? '') === 'issues' ? 'issues' : 'sent';
$page = isset($_GET['page']) && is_string($_GET['page']) && ctype_digit($_GET['page'])
    ? max(1, min(1000000, (int) $_GET['page'])) : 1;
$selectedId = isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
$compose = ($_GET['view'] ?? '') === 'compose';
$draft = ['to_email' => '', 'subject' => '', 'message_body' => ''];
$error = '';
$flash = $_SESSION['external_email_flash'] ?? null;
unset($_SESSION['external_email_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $compose = true;
    foreach ($draft as $field => $value) { $draft[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : ''; }
    $error = external_post_limit_error($_SERVER);
    if ($error === '' && !csrf_verify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        $error = 'Your session expired. Please reload and try again.';
    }
    if ($error === '') {
        try {
            if (isset($_FILES['attachment']) && !is_array($_FILES['attachment'])) {
                throw new InvalidArgumentException('Invalid attachment upload.');
            }
            $result = external_mail_submit($pdo, $userId, $_POST, $_FILES['attachment'] ?? null);
            $_SESSION['external_email_flash'] = ['message' => external_status_message($result['status']),
                'ok' => $result['status'] === 'Sent'];
            $nextFolder = $result['status'] === 'Sent' ? 'sent' : 'issues';
            header('Location: external_email.php?folder=' . $nextFolder . '&id=' . $result['id'], true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $e) {
            // PDO messages are never shown: they may include SQL or personal data.
            $error = $e instanceof PDOException ? 'Email storage is unavailable. No new email was sent.' : $e->getMessage();
        } catch (Throwable $e) {
            error_log('External email: request processing failed');
            $error = 'Unable to process this email. Please try again later.';
        }
    }
}

$emails = [];
$detail = null;
$total = 0;
$loadError = '';
$replyTo = '';
try {
    $stmt = $pdo->prepare('SELECT Email FROM Users WHERE UserID = ?');
    $stmt->execute([$userId]);
    $replyTo = (string) $stmt->fetchColumn();
    $where = $folder === 'sent' ? "e.Send_Status = 'Sent'" : "e.Send_Status IN ('Sending','Failed','Unknown')";
    $total = (int) $pdo->query('SELECT COUNT(*) FROM External_Communications e WHERE ' . $where)->fetchColumn();
    $page = min($page, max(1, (int) ceil($total / 50)));
    $order = $folder === 'sent' ? 'e.Sent_At DESC, e.CommunicationID DESC' : 'e.Created_At DESC, e.CommunicationID DESC';
    $sql = 'SELECT e.CommunicationID, e.To_Email, e.Subject, LEFT(e.Message_Body, 200) AS Preview,
        e.Send_Status, e.Created_At, e.Sent_At, e.File_Path FROM External_Communications e WHERE '
        . $where . ' ORDER BY ' . $order . ' LIMIT 50 OFFSET ' . (($page - 1) * 50);
    $emails = $pdo->query($sql)->fetchAll();
    if (!$compose && $selectedId > 0) {
        $stmt = $pdo->prepare('SELECT e.*, h.FullName AS SenderName FROM External_Communications e
            LEFT JOIN ' . user_identity_table($pdo) . ' h ON h.UserID = e.Sender_UserID WHERE e.CommunicationID = ?');
        $stmt->execute([$selectedId]);
        $detail = $stmt->fetch() ?: null;
        if ($detail === null) { $error = 'That email record could not be found.'; }
    }
} catch (Throwable $e) {
    error_log('External email: history unavailable');
    $loadError = 'External email history is unavailable. Contact the administrator to check migration 014 and database access.';
}
$configuration = external_mail_configuration();
$submissionKey = external_submission_key();
$flags = layout_role_flags();
$bodyClass = 'min-h-screen min-w-[1024px] bg-slate-50 external-email-page' . ($flags['isExecutive'] ? ' executive-theme' : '');
function external_escape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function external_date(?string $value): string { return $value ? date('M j, Y g:i A', strtotime($value)) : ''; }
$fieldClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100';
$extraHead = '<style>.external-email-page main{padding:1.5rem}.external-email-page .email-preview{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}</style>';
layout_begin('External Email', $activePage, [], $extraHead, $bodyClass);
?>
<div class="flex h-[calc(100vh-8rem)] bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden" data-external-mail>
    <aside class="w-1/3 min-w-0 min-h-0 border-r border-slate-200 flex flex-col bg-slate-50" aria-label="External email folders">
        <div class="shrink-0 p-5 border-b border-slate-200">
            <div class="flex items-center gap-3 mb-4">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
                </span>
                <div><h1 class="text-lg font-bold text-slate-900">External Email</h1><p class="text-xs text-slate-500">Outgoing correspondence</p></div>
            </div>
            <a href="external_email.php?view=compose&amp;folder=<?= $folder ?>" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><span aria-hidden="true">+</span> New Email</a>
            <div class="flex gap-2 mt-4" aria-label="Folders">
                <a href="external_email.php?folder=sent" class="flex-1 rounded-lg px-3 py-2 text-center text-sm font-medium <?= $folder === 'sent' ? 'bg-white text-indigo-700 shadow-sm border border-slate-200' : 'text-slate-500 hover:bg-slate-100' ?>" <?= $folder === 'sent' ? 'aria-current="page"' : '' ?>>Sent</a>
                <a href="external_email.php?folder=issues" class="flex-1 rounded-lg px-3 py-2 text-center text-sm font-medium <?= $folder === 'issues' ? 'bg-white text-indigo-700 shadow-sm border border-slate-200' : 'text-slate-500 hover:bg-slate-100' ?>" <?= $folder === 'issues' ? 'aria-current="page"' : '' ?>>Send issues</a>
            </div>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto" aria-label="<?= $folder === 'sent' ? 'Sent emails' : 'Send issues' ?>">
            <?php if ($loadError !== ''): ?>
                <p class="p-5 text-sm text-red-700" role="alert"><?= external_escape($loadError) ?></p>
            <?php elseif ($emails === []): ?>
                <div class="p-6 text-center"><p class="text-sm font-medium text-slate-700"><?= $folder === 'sent' ? 'No sent emails yet' : 'No send issues' ?></p><p class="mt-2 text-xs text-slate-500"><?= $folder === 'sent' ? 'Compose an email to start your correspondence.' : 'Unconfirmed or unsuccessful attempts appear here.' ?></p></div>
            <?php else: ?>
                <?php foreach ($emails as $email): $isSelected = !$compose && $selectedId === (int) $email['CommunicationID']; ?>
                    <a href="external_email.php?folder=<?= $folder ?>&amp;page=<?= $page ?>&amp;id=<?= (int) $email['CommunicationID'] ?>" class="block hover:bg-slate-100 p-4 border-b border-slate-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 <?= $isSelected ? 'bg-indigo-50 border-l-4 border-l-indigo-500' : '' ?>" <?= $isSelected ? 'aria-current="true"' : '' ?>>
                        <div class="flex items-center justify-between gap-2"><p class="min-w-0 truncate text-sm font-semibold text-slate-900"><?= external_escape($email['To_Email']) ?></p><?php if ($email['File_Path']): ?><span class="shrink-0 text-xs text-slate-400" aria-label="Has attachment">&#128206;</span><?php endif; ?></div>
                        <p class="mt-1 truncate text-sm font-bold text-slate-800"><?= external_escape($email['Subject']) ?></p>
                        <p class="email-preview mt-1 text-xs text-slate-500"><?= external_escape($email['Preview']) ?></p>
                        <div class="mt-3 flex items-center justify-between gap-2 text-xs text-slate-400"><span><?= external_escape(external_date($email['Sent_At'] ?? $email['Created_At'])) ?></span><?php if ($folder === 'issues'): ?><span class="text-amber-700"><?= $email['Send_Status'] === 'Failed' ? 'Failed' : 'Unconfirmed' ?></span><?php endif; ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="shrink-0 flex items-center justify-between gap-2 border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
            <span><?= $total ?> <?= $total === 1 ? 'email' : 'emails' ?> &middot; Page <?= $page ?></span>
            <div class="flex gap-3"><?php if ($page > 1): ?><a class="hover:text-indigo-600" href="external_email.php?folder=<?= $folder ?>&amp;page=<?= $page - 1 ?>">Previous</a><?php endif; ?><?php if ($page * 50 < $total): ?><a class="hover:text-indigo-600" href="external_email.php?folder=<?= $folder ?>&amp;page=<?= $page + 1 ?>">Next</a><?php endif; ?></div>
        </div>
    </aside>
    <section class="w-2/3 min-w-0 min-h-0 flex flex-col bg-white" aria-label="<?= $compose ? 'Compose email' : 'Email details' ?>">
        <?php if ($flash): ?><div class="shrink-0 border-b px-6 py-3 text-sm <?= !empty($flash['ok']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' ?>" role="status"><?= external_escape(!empty($flash['ok']) ? 'Email Sent' : $flash['message']) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="shrink-0 border-b border-red-200 bg-red-50 px-6 py-3 text-sm text-red-700" role="alert"><?= external_escape($error) ?><?php if ($compose): ?><span class="block mt-1">If you selected an attachment, choose it again before sending.</span><?php endif; ?></div><?php endif; ?>
        <?php if ($compose): ?>
            <div class="shrink-0 border-b border-slate-200 px-6 py-5"><h2 class="text-lg font-semibold text-slate-900">New email</h2><p class="mt-1 text-xs text-slate-500">Replies go to <?= external_escape($replyTo) ?></p></div>
            <form id="external-compose" method="POST" action="external_email.php?view=compose" enctype="multipart/form-data" class="flex flex-1 min-h-0 flex-col" data-compose-form>
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= external_escape($submissionKey) ?>">
                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4">
                    <?php if (!$configuration['ready']): ?><p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800" role="alert">Secure SMTP is not configured. Contact the administrator.</p><?php endif; ?>
                    <?php if ($loadError !== ''): ?><p class="text-sm text-red-700" role="alert"><?= external_escape($loadError) ?></p><?php endif; ?>
                    <div><label for="to-email" class="mb-1 block text-sm font-medium text-slate-600">To</label><input id="to-email" type="email" name="to_email" required maxlength="254" autocomplete="email" placeholder="recipient@example.com" class="<?= $fieldClass ?>" value="<?= external_escape($draft['to_email']) ?>"></div>
                    <div><label for="email-subject" class="mb-1 block text-sm font-medium text-slate-600">Subject</label><input id="email-subject" type="text" name="subject" required maxlength="255" placeholder="Add a subject" class="<?= $fieldClass ?>" value="<?= external_escape($draft['subject']) ?>"></div>
                    <div><label for="email-body" class="mb-1 block text-sm font-medium text-slate-600">Message</label><textarea id="email-body" name="message_body" required rows="10" placeholder="Write your email..." class="<?= $fieldClass ?> resize-y"><?= external_escape($draft['message_body']) ?></textarea></div>
                </div>
                <div class="shrink-0 p-4 border-t border-slate-200 bg-slate-50">
                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" <?= !$configuration['ready'] || $loadError !== '' ? 'disabled' : '' ?> data-send-button>Send email</button>
                        <input id="email-attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,application/pdf,image/jpeg,image/png" class="hidden" aria-describedby="attachment-name">
                        <label for="email-attachment" tabindex="0" role="button" aria-label="Attach a file" title="Attach a file" class="cursor-pointer rounded-lg p-2 text-slate-500 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" data-attachment-trigger><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 11-8.5 8.5a6 6 0 0 1-8.5-8.5l9-9a4 4 0 0 1 5.7 5.7l-9 9a2 2 0 0 1-2.8-2.8l8.5-8.5"/></svg></label>
                        <span id="attachment-name" class="min-w-0 truncate text-xs text-slate-600" aria-live="polite">No attachment selected</span>
                    </div>
                </div>
            </form>
        <?php elseif ($detail): ?>
            <div class="flex-1 min-h-0 overflow-y-auto p-6" data-sent-detail>
                <div class="flex items-start justify-between gap-4"><h2 class="min-w-0 break-words text-2xl font-semibold text-slate-900"><?= external_escape($detail['Subject']) ?></h2><span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium <?= $detail['Send_Status'] === 'Sent' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>"><?= $detail['Send_Status'] === 'Sent' ? 'Sent' : ($detail['Send_Status'] === 'Failed' ? 'Failed' : 'Unconfirmed') ?></span></div>
                <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm space-y-2">
                    <p class="break-words"><span class="text-slate-500">From:</span> <?= external_escape($detail['From_Email']) ?></p>
                    <p class="break-words"><span class="text-slate-500">To:</span> <?= external_escape($detail['To_Email']) ?></p>
                    <p class="break-words"><span class="text-slate-500">Reply-To:</span> <?= external_escape($detail['Reply_To_Email']) ?></p>
                    <p><span class="text-slate-500">Sent by:</span> <?= external_escape($detail['SenderName'] ?? 'Historical sender') ?></p>
                    <p><span class="text-slate-500"><?= $detail['Send_Status'] === 'Sent' ? 'Accepted:' : 'Attempted:' ?></span> <?= external_escape(external_date($detail['Sent_At'] ?? $detail['Created_At'])) ?></p>
                </div>
                <p class="mt-4 text-xs text-slate-500"><?= external_escape($detail['Send_Status'] === 'Sent' ? 'Email Sent' : external_status_message($detail['Send_Status'])) ?></p>
                <div class="mt-6 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700"><?= external_escape($detail['Message_Body']) ?></div>
                <?php if ($detail['File_Path']): ?><div class="mt-8 border-t border-slate-200 pt-4"><a href="external_attachment.php?id=<?= (int) $detail['CommunicationID'] ?>" class="inline-flex max-w-full items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-indigo-700 hover:bg-indigo-50"><span aria-hidden="true">&#128206;</span><span class="truncate"><?= external_escape($detail['Attachment_Name']) ?></span><span class="shrink-0 text-xs text-slate-400"><?= number_format($detail['Attachment_Size'] / 1024, 1) ?> KB</span></a></div><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="flex flex-1 flex-col items-center justify-center p-8 text-center"><span class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 text-slate-300"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg></span><h2 class="mt-5 text-lg font-semibold text-slate-800">Your outgoing correspondence</h2><p class="mt-2 max-w-sm text-sm text-slate-500">Select an email to view its details, or choose New Email to write to a vendor, utility company, or client.</p></div>
        <?php endif; ?>
    </section>
</div>
<?php layout_end('<script src="assets/js/external_email.js"></script>'); ?>
