<?php
session_start();
require_once __DIR__.'/includes/require_role.php';
require_login();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/report_snapshots.php';
require_once __DIR__.'/includes/review_ui.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') { header('Allow: GET'); http_response_code(405); exit('Use the review endpoint to submit a snapshot.'); }
$flags=layout_role_flags(); $isExecutive=$flags['isExecutive']; $csrfToken=csrf_token();
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$money=static fn($v)=>$escape(accounting_money((string)$v));
$today=accounting_today(); $year=(int)substr($today,0,4); $month=(int)substr($today,5,2);
$snapshotId=null; $tb=null; $error=''; $revisions=[]; $legacy=[]; $snapshotAvailable=report_snapshot_available($pdo); $liveChanged=null;
try {
    foreach (['year','month','snapshot_id'] as $key) { if (isset($_GET[$key])&&(!is_string($_GET[$key])||!ctype_digit($_GET[$key]))) { throw new InvalidArgumentException('Invalid report selection.'); } }
    if (isset($_GET['snapshot_id'])) {
        $snapshotId=(int)$_GET['snapshot_id'];
        if ($snapshotId<1||$snapshotId>4294967295) { throw new InvalidArgumentException('Invalid snapshot.'); }
        if (!$snapshotAvailable) { throw new ReportProblem('Snapshot reviews require migration 017.',503); }
        $tb=report_snapshot_load_id($pdo,$snapshotId);
        if (!$tb) { throw new ReportProblem('Snapshot not found.',404); }
        $year=(int)$tb['header']['report_year']; $month=(int)$tb['header']['report_month'];
        try { $live=accounting_trial_balance($pdo,$tb['as_of']); $liveChanged=!hash_equals($tb['fingerprint'],$live['fingerprint']); }
        catch (Throwable $e) { error_log('Snapshot comparison failed: '.$e->getMessage()); }
    } else {
        $year=isset($_GET['year'])?(int)$_GET['year']:$year; $month=isset($_GET['month'])?(int)$_GET['month']:$month;
        $tb=accounting_trial_balance($pdo,accounting_month_end($year,$month));
    }
    if ($snapshotAvailable) {
        $s=$pdo->prepare('SELECT id,revision,review_status,captured_at FROM trial_balance_snapshots WHERE report_year=? AND report_month=? ORDER BY revision DESC');
        $s->execute([$year,$month]); $revisions=$s->fetchAll(PDO::FETCH_ASSOC);
    }
    $s=$pdo->prepare('SELECT ReportID,Review_Status,Total_Revenue,Total_Expenses,Net_Income,Created_At FROM Reports WHERE Report_Year=? AND Report_Month=?');
    $s->execute([$year,$month]); $legacy=$s->fetchAll(PDO::FETCH_ASSOC);
} catch (InvalidArgumentException $e) { http_response_code(400); $error=$e->getMessage(); }
catch (ReportProblem $e) { http_response_code($e->status); $error=$e->getMessage(); }
catch (Throwable $e) { http_response_code(503); error_log('Trial Balance unavailable: '.$e->getMessage()); $error='Unable to load the Trial Balance. Please try again later.'; }
if($year<1000||$year>9998||$month<1||$month>12){$year=(int)substr($today,0,4);$month=(int)substr($today,5,2);}
$years=range((int)substr($today,0,4),(int)substr($today,0,4)-4); $years[]=$year;
try { foreach ($pdo->query("SELECT DISTINCT YEAR(entry_date) FROM journal_entries WHERE status='posted'")->fetchAll(PDO::FETCH_COLUMN) as $y) { $years[]=(int)$y; } }
catch (Throwable $e) { /* The report error above remains visible. */ }
$years=array_unique($years); rsort($years); $integrity=$tb&&$tb['difference']==='0.00'&&!$tb['invalid_journals'];
$periodLabel=(new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month)))->format('F Y');
$cardClass=$isExecutive?'exec-card':'bg-white rounded-xl border border-slate-200 shadow-sm p-6';
$btnPrimary=$isExecutive?'exec-btn-primary':'rounded-lg bg-blue-900 text-white font-semibold px-5 py-2.5';
$generatedAt=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('F j, Y g:i A').' Asia/Manila';
layout_begin('Reports','reports',[], '<link rel="stylesheet" href="assets/css/reports.css">');
?>
<div class="js-review-root reports-page" data-csrf="<?= $escape($csrfToken) ?>">
<div id="review-flash" class="hidden"></div>
<div class="flex items-center justify-between gap-4 mb-6"><div><h1 class="text-2xl font-bold text-slate-900">Financial Reports</h1><p class="text-slate-600 mt-2">Trial Balance from posted double-entry journals.</p></div><button type="button" onclick="window.print()" class="<?= $btnPrimary ?> no-print">Print / Export PDF</button></div>
<section class="<?= $cardClass ?> no-print mb-6">
<div class="flex flex-wrap justify-between gap-6"><div><h2 class="text-lg font-semibold text-slate-900 mb-4">Select Reporting Period</h2>
<form method="GET" class="flex flex-wrap items-end gap-4">
<label>Month <select name="month" class="rounded-lg border border-slate-300 px-4 py-2.5"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= $escape((new DateTimeImmutable('2000-'.str_pad((string)$m,2,'0',STR_PAD_LEFT).'-01'))->format('F')) ?></option><?php endfor; ?></select></label>
<label>Year <select name="year" class="rounded-lg border border-slate-300 px-4 py-2.5"><?php foreach($years as $y): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endforeach; ?></select></label>
<button class="<?= $btnPrimary ?>">Generate Statement</button></form></div>
<div><h2 class="text-lg font-semibold text-slate-900 mb-4">Books of Accounts</h2><p><a href="financial_records.php?view=crb">Cash Receipts Book (CRB) →</a></p><p><a href="financial_records.php?view=cdb">Cash Disbursements Book (CDB) →</a></p></div></div>
<?php if($tb): ?><div class="mt-6 pt-6 border-t border-slate-200">
<?php if($snapshotId): ?><p><strong>Frozen revision <?= (int)$tb['header']['revision'] ?></strong> · <?= review_render_status_badge($tb['header']['review_status']) ?></p>
<p>Submitted by <?= $escape($tb['header']['submitter_name']) ?> · captured <?= $escape($tb['header']['captured_at']) ?> UTC</p>
<?php if($tb['header']['review_status']==='Reviewed'): ?><p>Reviewed by <?= $escape($tb['header']['reviewer_name']) ?> · <?= $escape($tb['header']['reviewed_at']) ?> UTC</p><p><?= $escape($tb['header']['review_notes']??'') ?></p><?php endif; ?>
<p><?= $liveChanged===true?'Live source data has changed since this revision. This snapshot retains the original submitted figures.':($liveChanged===false?'This revision matches the current live source.':'Live source comparison is unavailable.') ?></p>
<a href="reports.php?month=<?= $month ?>&amp;year=<?= $year ?>">View live Trial Balance</a>
<?php if($isExecutive&&$tb['header']['review_status']==='Requested'&&$integrity): ?><div class="mt-4"><textarea id="review-notes" maxlength="10000" rows="2" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Optional review notes"></textarea><button class="js-mark-reviewed <?= $btnPrimary ?>" data-entity-type="trial_balance" data-entity-id="<?= $snapshotId ?>">Mark This Revision as Reviewed</button></div><?php endif; ?>
<?php else: ?><p><strong>Live figures</strong> · Review status belongs to a frozen revision, not this changing view.</p>
<?php if(!$snapshotAvailable): ?><p role="status">Apply migration 017 before submitting Trial Balances for review.</p>
<?php elseif($flags['canUseWorkspace']&&$integrity): ?><button class="js-send-review <?= $btnPrimary ?> mt-4" data-entity-type="trial_balance" data-report-month="<?= $month ?>" data-report-year="<?= $year ?>" data-source-fingerprint="<?= $escape($tb['fingerprint']) ?>" data-submission-key="<?= $escape(report_submission_token($year,$month,$tb['fingerprint'])) ?>">Send Frozen Revision for Review</button><?php endif; ?>
<?php endif; ?>
<?php if($revisions): ?><p class="mt-4">Submitted revisions:</p><ul><?php foreach($revisions as $r): ?><li><a href="reports.php?snapshot_id=<?= (int)$r['id'] ?>">Revision <?= (int)$r['revision'] ?> — <?= $escape($r['review_status']) ?> — <?= $escape($r['captured_at']) ?> UTC</a></li><?php endforeach; ?></ul><?php endif; ?>
</div><?php endif; ?></section>
<?php if($error!==''): ?><p role="alert"><?= $escape($error) ?></p>
<?php elseif($tb): ?>
<section class="statement-page <?= $cardClass ?>"><h2 class="text-xl font-bold text-center">ATIKHA FINANCE — TRIAL BALANCE</h2>
<p class="text-center">As of <?= $escape($tb['as_of']) ?> · <?= $escape($periodLabel) ?></p>
<p class="text-center text-sm"><?= $snapshotId?'Frozen revision '.(int)$tb['header']['revision'].' · captured '.$escape($tb['header']['captured_at']).' UTC':'Live figures' ?> · generated <?= $escape($generatedAt) ?></p>
<p class="text-sm mt-4">Cumulative posted balances through month-end. Income and Expense accounts remain unclosed until closing entries are posted.</p>
<?php if(!$integrity): ?><p role="alert" class="text-red-700 font-semibold">Integrity check failed. Difference: <?= $money($tb['difference']) ?>. Invalid journal/snapshot identifiers: <?= $escape(implode(', ',$tb['invalid_journals'])) ?>. Submission is unavailable.</p><?php endif; ?>
<div class="trial-table-wrap"><table class="trial-table"><thead><tr><th>Account Code</th><th>Account Name</th><th>Account Type</th><th>Debit Balance</th><th>Credit Balance</th></tr></thead><tbody>
<?php foreach(['Balance Sheet'=>['Asset','Liability','Equity'],'Income Statement'=>['Income','Expense']] as $label=>$types): $sd=$sc=0; ?>
<tr class="trial-section"><th colspan="5"><?= $escape($label) ?></th></tr>
<?php foreach($tb['rows'] as $r): if(!in_array($r['Account_Type'],$types,true)){continue;} $sd=accounting_add($sd,accounting_cents($r['debit_balance'])); $sc=accounting_add($sc,accounting_cents($r['credit_balance'])); ?>
<tr><td><?= $escape($r['Account_Code']??'—') ?></td><td><?= $escape($r['Name']) ?><?= !(int)$r['Is_Active']?' (inactive)':'' ?></td><td><?= $escape($r['Account_Type']) ?></td><td class="trial-amount"><?= $money($r['debit_balance']) ?></td><td class="trial-amount"><?= $money($r['credit_balance']) ?></td></tr>
<?php endforeach; ?><tr class="trial-subtotal"><th colspan="3"><?= $escape($label) ?> subtotal</th><td class="trial-amount"><?= $money(accounting_decimal($sd)) ?></td><td class="trial-amount"><?= $money(accounting_decimal($sc)) ?></td></tr>
<?php endforeach; ?></tbody><tfoot><tr><th colspan="3">TOTAL</th><td class="trial-amount"><?= $money($tb['total_debits']) ?></td><td class="trial-amount"><?= $money($tb['total_credits']) ?></td></tr><tr><th colspan="3">Difference</th><td colspan="2" class="trial-amount"><?= $money($tb['difference']) ?> · <?= $integrity?'Balanced':'Integrity check failed' ?></td></tr></tfoot></table></div>
</section>
<?php if($legacy): ?><details class="<?= $cardClass ?> mt-6 no-print"><summary>Legacy report history — read-only, separate accounting basis</summary><?php foreach($legacy as $r): ?><p>Legacy report #<?= (int)$r['ReportID'] ?> · <?= $escape($r['Review_Status']) ?> · Income <?= $money($r['Total_Revenue']) ?> · Expenses <?= $money($r['Total_Expenses']) ?> · Net <?= $money($r['Net_Income']) ?></p><?php endforeach; ?><p>These records do not review or approve the Trial Balance above.</p></details><?php endif; ?>
<?php endif; ?></div>
<?php layout_end('<script src="assets/js/review_actions.js"></script>'); ?>
