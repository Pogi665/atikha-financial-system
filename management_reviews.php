<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/review_ui.php';

require_login();
require_role(['Management'], 'Review Queue');

$activePage = 'management_reviews';
$flags = layout_role_flags();
$isExecutive = $flags['isExecutive'];
$csrfToken = csrf_token();

$filter = (string) ($_GET['filter'] ?? 'pending');
if (!in_array($filter, ['pending', 'all'], true)) {
    $filter = 'pending';
}

$highlightEntity = (string) ($_GET['entity'] ?? '');
$highlightId = (int) ($_GET['id'] ?? 0);

$pendingExpenses = [];
$pendingFunds = [];
$pendingReports = [];
$pendingBoard = [];

try {
    $expenseSql = 'SELECT e.ExpenseID, e.Date_Incurred, e.Payee, e.Category, e.Amount,
                          e.Review_Status, e.Review_Notes, u.FullName AS SubmitterName
                   FROM Expenses e
                   INNER JOIN Users u ON u.UserID = e.RecordedBy_UserID';
    if ($filter === 'pending') {
        $expenseSql .= " WHERE e.Review_Status = 'Requested'";
    } else {
        $expenseSql .= " WHERE e.Review_Status IN ('Requested','Reviewed')";
    }
    $expenseSql .= ' ORDER BY e.Date_Incurred DESC, e.ExpenseID DESC LIMIT 100';
    $pendingExpenses = $pdo->query($expenseSql)->fetchAll() ?: [];

    $fundSql = 'SELECT f.FundID, f.Date_Received, f.Source_Donor, f.Category, f.Amount,
                       f.Review_Status, f.Review_Notes, u.FullName AS SubmitterName
                FROM Incoming_Funds f
                INNER JOIN Users u ON u.UserID = f.RecordedBy_UserID';
    if ($filter === 'pending') {
        $fundSql .= " WHERE f.Review_Status = 'Requested'";
    } else {
        $fundSql .= " WHERE f.Review_Status IN ('Requested','Reviewed')";
    }
    $fundSql .= ' ORDER BY f.Date_Received DESC, f.FundID DESC LIMIT 100';
    $pendingFunds = $pdo->query($fundSql)->fetchAll() ?: [];

    $reportSql = 'SELECT r.ReportID, r.Report_Month, r.Report_Year, r.Total_Revenue,
                         r.Total_Expenses, r.Net_Income, r.Review_Status, r.Review_Notes,
                         r.Created_At, u.FullName AS SubmitterName
                  FROM Reports r
                  INNER JOIN Users u ON u.UserID = r.SubmittedBy_UserID';
    if ($filter === 'pending') {
        $reportSql .= " WHERE r.Review_Status = 'Requested'";
    } else {
        $reportSql .= " WHERE r.Review_Status IN ('Requested','Reviewed')";
    }
    $reportSql .= ' ORDER BY r.Report_Year DESC, r.Report_Month DESC LIMIT 100';
    $pendingReports = $pdo->query($reportSql)->fetchAll() ?: [];

    $boardSql = 'SELECT b.CommunicationID, b.Subject, b.Message_Body, b.File_Path,
                        b.Review_Status, b.Created_At, u.FullName AS SubmitterName
                 FROM Board_Communications b
                 INNER JOIN Users u ON u.UserID = b.Sender_UserID';
    if ($filter === 'pending') {
        $boardSql .= " WHERE b.Review_Status = 'Requested'";
    } else {
        $boardSql .= " WHERE b.Review_Status IN ('Requested','Reviewed')";
    }
    $boardSql .= ' ORDER BY b.Created_At DESC LIMIT 100';
    $pendingBoard = $pdo->query($boardSql)->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Review queue query failed: ' . $e->getMessage());
}

$pendingCount = count(array_filter($pendingExpenses, static fn ($r) => $r['Review_Status'] === 'Requested'))
    + count(array_filter($pendingFunds, static fn ($r) => $r['Review_Status'] === 'Requested'))
    + count(array_filter($pendingReports, static fn ($r) => $r['Review_Status'] === 'Requested'))
    + count(array_filter($pendingBoard, static fn ($r) => $r['Review_Status'] === 'Requested'));

$cardClass = $isExecutive ? 'exec-card' : 'bg-white rounded-xl border border-slate-200 shadow-sm p-6';
$btnPrimary = $isExecutive ? 'exec-btn-primary' : 'rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2 px-4 text-sm transition';

layout_begin('Review Queue', $activePage);
?>

<div class="js-review-root" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div id="review-flash" class="hidden"></div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Review Queue</h1>
            <p class="text-slate-600 mt-2">
                Pending items submitted by Staff for Management review.
                <?php if ($pendingCount > 0): ?>
                    <span class="font-semibold text-amber-700"><?= (int) $pendingCount ?> awaiting review.</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="management_reviews.php?filter=pending" class="<?= $filter === 'pending' ? $btnPrimary : 'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition' ?>">
                Pending
            </a>
            <a href="management_reviews.php?filter=all" class="<?= $filter === 'all' ? $btnPrimary : 'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition' ?>">
                All Submissions
            </a>
            <a href="board_inbox.php" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                Board Inbox
            </a>
        </div>
    </div>

    <section class="<?= $cardClass ?> mt-6">
        <label for="review-notes" class="block text-sm font-medium text-slate-700 mb-1">Review notes (optional, applied to the next item you mark reviewed)</label>
        <textarea id="review-notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Optional notes sent to the original submitter"></textarea>
    </section>

    <section class="<?= $cardClass ?> mt-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Expenses</h2>
        <?php if ($pendingExpenses === []): ?>
            <p class="text-sm text-slate-500 italic">No expense review items.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left">
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Date</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Payee</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Amount</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Submitted By</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Status</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingExpenses as $row): ?>
                            <?php
                            $rowId = (int) $row['ExpenseID'];
                            $highlight = $highlightEntity === 'expense' && $highlightId === $rowId;
                            ?>
                            <tr class="border-b border-slate-100 <?= $highlight ? 'bg-amber-50' : '' ?>">
                                <td class="px-4 py-3"><?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Incurred'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-900"><?= htmlspecialchars($row['Payee'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="block text-xs text-slate-500"><?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars(format_peso((float) $row['Amount']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['SubmitterName'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3"><?= review_render_status_badge((string) $row['Review_Status']) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($row['Review_Status'] === 'Requested'): ?>
                                        <button type="button" class="js-mark-reviewed <?= $btnPrimary ?>" data-entity-type="expense" data-entity-id="<?= $rowId ?>">
                                            Mark as Reviewed
                                        </button>
                                    <?php elseif (!empty($row['Review_Notes'])): ?>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars((string) $row['Review_Notes'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="<?= $cardClass ?>">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Incoming Funds</h2>
        <?php if ($pendingFunds === []): ?>
            <p class="text-sm text-slate-500 italic">No incoming fund review items.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left">
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Date</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Source</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Amount</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Submitted By</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Status</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFunds as $row): ?>
                            <?php
                            $rowId = (int) $row['FundID'];
                            $highlight = $highlightEntity === 'fund' && $highlightId === $rowId;
                            ?>
                            <tr class="border-b border-slate-100 <?= $highlight ? 'bg-amber-50' : '' ?>">
                                <td class="px-4 py-3"><?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Received'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-900"><?= htmlspecialchars($row['Source_Donor'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="block text-xs text-slate-500"><?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars(format_peso((float) $row['Amount']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['SubmitterName'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3"><?= review_render_status_badge((string) $row['Review_Status']) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($row['Review_Status'] === 'Requested'): ?>
                                        <button type="button" class="js-mark-reviewed <?= $btnPrimary ?>" data-entity-type="fund" data-entity-id="<?= $rowId ?>">
                                            Mark as Reviewed
                                        </button>
                                    <?php elseif (!empty($row['Review_Notes'])): ?>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars((string) $row['Review_Notes'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="<?= $cardClass ?>">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Monthly Reports</h2>
        <?php if ($pendingReports === []): ?>
            <p class="text-sm text-slate-500 italic">No report review items.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left">
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Period</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Net Income</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Submitted By</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600">Status</th>
                            <th class="px-4 py-2 text-xs font-semibold uppercase text-slate-600 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingReports as $row): ?>
                            <?php
                            $rowId = (int) $row['ReportID'];
                            $periodLabel = date('F Y', mktime(0, 0, 0, (int) $row['Report_Month'], 1, (int) $row['Report_Year']));
                            $highlight = $highlightEntity === 'report' && $highlightId === $rowId;
                            ?>
                            <tr class="border-b border-slate-100 <?= $highlight ? 'bg-amber-50' : '' ?>">
                                <td class="px-4 py-3">
                                    <a href="reports.php?month=<?= str_pad((string) $row['Report_Month'], 2, '0', STR_PAD_LEFT) ?>&year=<?= (int) $row['Report_Year'] ?>" class="text-blue-800 hover:underline font-medium">
                                        <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars(format_peso((float) $row['Net_Income']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['SubmitterName'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3"><?= review_render_status_badge((string) $row['Review_Status']) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($row['Review_Status'] === 'Requested'): ?>
                                        <button type="button" class="js-mark-reviewed <?= $btnPrimary ?>" data-entity-type="report" data-entity-id="<?= $rowId ?>">
                                            Mark as Reviewed
                                        </button>
                                    <?php elseif (!empty($row['Review_Notes'])): ?>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars((string) $row['Review_Notes'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="<?= $cardClass ?>">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Board Messages</h2>
        <?php if ($pendingBoard === []): ?>
            <p class="text-sm text-slate-500 italic">No board message review items. See <a href="board_inbox.php" class="text-blue-800 hover:underline">Board Inbox</a>.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pendingBoard as $row): ?>
                    <article class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900"><?= htmlspecialchars($row['Subject'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="text-xs text-slate-500 mt-1">
                                    From <?= htmlspecialchars($row['SubmitterName'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars(date('M j, Y g:i A', strtotime($row['Created_At'])), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <?= review_render_status_badge((string) $row['Review_Status']) ?>
                        </div>
                        <p class="text-sm text-slate-700 mt-3 whitespace-pre-wrap"><?= htmlspecialchars($row['Message_Body'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($row['File_Path'])): ?>
                            <p class="mt-2">
                                <a href="board_attachment.php?id=<?= (int) $row['CommunicationID'] ?>" class="text-sm text-blue-800 hover:underline">Download attachment</a>
                            </p>
                        <?php endif; ?>
                        <?php if ($row['Review_Status'] === 'Requested'): ?>
                            <div class="mt-4">
                                <button type="button" class="js-mark-reviewed <?= $btnPrimary ?>" data-entity-type="board" data-entity-id="<?= (int) $row['CommunicationID'] ?>">
                                    Mark as Reviewed
                                </button>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
layout_end('<script src="assets/js/review_actions.js"></script>');
?>
