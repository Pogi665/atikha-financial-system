<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin','Management'],'Cash advances');require_once __DIR__.'/includes/cash_advance.php';require_once __DIR__.'/includes/layout.php';
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit;}
header('Cache-Control: private, no-store');
try{
    $uid=(int)$_SESSION['UserID'];$role=advance_guard($pdo,$uid);
    $id=journal_id(journal_string($_GET,'advance_id',true),true);
    $asof=advance_date(journal_string($_GET,'as_of',true)?:journal_today());
    // Reuse the existing catalog read for labels; no additional reader/comparison query.
    $catalog=accounting_read($pdo,fn()=>advance_state($pdo,journal_today()));
    $options=array_map('advance_summary',array_values($catalog['advances']));
    $controlNames=array_column($catalog['reconciliation'],'account_name','account_id');
    $detail=$id===null?null:advance_detail($pdo,$uid,$id,$asof);
}catch(JournalProblem $e){http_response_code($e->status);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$money=fn($v)=>'PHP '.preg_replace('/\B(?=(\d{3})+(?!\d))/',',',explode('.',(string)$v)[0]).'.'.(explode('.',(string)$v)[1]??'00');
$historical=$asof!==journal_today();
layout_begin('Cash advances','cash_advances',[],'<link rel="stylesheet" href="assets/css/accounting_workspace.css?v='.filemtime(__DIR__.'/assets/css/accounting_workspace.css').'">');
?>
<div class="accounting-workspace advance-register" id="advance-register">
<header class="aw-header">
    <div><p class="aw-eyebrow">CASH ADVANCES</p><h1><?= $detail?$esc($detail['number']):'Cash advance register' ?></h1><p>Track released funds, supported liquidations and returned cash.</p>
        <?php if($role==='Admin'&&!$detail): ?><div class="ca-next-actions aw-no-print"><a href="cash_advance_entry.php?workflow_kind=advance_release" class="ca-primary-link">Release cash advance</a></div><?php endif; ?>
    </div>
    <div class="ca-toolbar aw-no-print">
        <?php if($detail): ?><a href="cash_advances.php?as_of=<?= $esc($asof) ?>">Back to advance register</a><?php endif; ?>
        <?php if($role==='Admin'): ?><a href="accounting_drafts.php">My drafts</a><?php endif; ?>
        <button type="button" id="ca-print">Print</button>
    </div>
</header>
<p id="ca-error" role="alert" tabindex="-1"></p><p id="ca-status" role="status"></p>
<section id="ca-health" class="ca-health ca-health-loading" data-state="loading" aria-labelledby="ca-health-title" aria-live="polite">
    <h2 id="ca-health-title">Control-account reconciliation</h2>
    <p id="ca-health-message">Loading the independent control-account check…</p>
    <a href="#ca-reconciliation-details" id="ca-health-link" hidden>View full reconciliation</a>
</section>
<?php if($detail): ?>
<p class="ca-date-basis"><strong><?= $historical?'Historical view':'Current view' ?> · As of <?= $esc($asof) ?></strong><br>Historical views include currently recorded postings through this accounting date; they are not frozen reports.</p>
<form method="get" class="ca-date-form aw-no-print">
    <input type="hidden" name="advance_id" value="<?= $id ?>">
    <label>As of<input type="date" name="as_of" value="<?= $esc($asof) ?>" max="<?= journal_today() ?>" min="1000-01-01" required></label><button>Apply date</button>
    <?php if($historical): ?><a href="cash_advances.php?advance_id=<?= $id ?>">View today's advance</a><?php endif; ?>
</form>
<section class="aw-card ca-advance-summary" aria-labelledby="ca-employee">
    <h2 id="ca-employee"><?= $esc($detail['employee']) ?></h2><p class="ca-purpose"><?= $esc($detail['purpose']) ?></p>
    <p class="ca-settlement-status">Settlement status: <strong><?= $esc(ucfirst(str_replace('_',' ',$detail['status']))) ?></strong></p>
    <?php if($detail['can_settle']): ?><nav class="ca-next-actions aw-no-print" aria-label="Settle this advance"><a href="cash_advance_entry.php?workflow_kind=advance_liquidation&amp;advance_id=<?= $id ?>" class="ca-primary-link">Record liquidation</a><a href="cash_advance_entry.php?workflow_kind=advance_return&amp;advance_id=<?= $id ?>" class="ca-primary-link">Return unused cash</a></nav><?php endif; ?>
    <dl class="ca-context">
        <div><dt>Originating project</dt><dd><?= $esc($detail['project']) ?></dd></div>
        <div><dt>Advance control account (current name)</dt><dd><?= $esc($controlNames[$detail['control_account_id']]??'Account #'.$detail['control_account_id']) ?></dd></div>
        <div><dt>Reference</dt><dd><?= $esc($detail['reference']?:'Not provided') ?></dd></div>
    </dl>
    <div class="aw-totals ca-balance-cards"><?php foreach(['outstanding','released','liquidated','returned'] as $k): ?><span<?= $k==='outstanding'?' class="ca-outstanding"':'' ?>><?= ucfirst($k) ?><strong><?= $esc($money($detail[$k])) ?></strong></span><?php endforeach; ?></div>
    <div class="ca-status-line"><span>Due <?= $esc($detail['due_date']) ?></span><span><?= $detail['overdue_days']>0?$detail['overdue_days'].' days overdue · '.$esc($detail['aging_bucket']):'Not overdue at this date' ?></span></div>
    <p class="aw-help">Original released amount: <?= $esc($money($detail['original_released'])) ?>. Balances are effective amounts through the selected date; General Journal reversals provide correction offsets.</p>
    <?php if($detail['lifecycle_date']): ?><p><?= $esc(ucfirst($detail['status'])) ?> from <?= $esc($detail['lifecycle_date']) ?>. <a href="journal_corrections.php?journal_id=<?= (int)$detail['operations'][0]['journal_id'] ?>">View posted correction</a></p><?php endif; ?>
    <?php foreach(['original_advance_id'=>'View original advance','replacement_advance_id'=>'View replacement advance'] as $field=>$label): if($detail[$field]): ?><p><a href="cash_advances.php?advance_id=<?= (int)$detail[$field] ?>&amp;as_of=<?= $esc($asof) ?>"><?= $label ?></a></p><?php endif; endforeach; ?>
    <?php if($detail['can_settle']): ?><p class="aw-help aw-no-print">These actions remain subject to the server's current balance, evidence, lifecycle and reconciliation checks.</p>
    <?php elseif($historical&&$role==='Admin'): ?><p class="aw-no-print">This historical view does not start settlements. Open today's advance to see its current eligible actions.</p><?php endif; ?>
</section>
<section class="aw-card" aria-labelledby="ca-timeline-title">
    <h2 id="ca-timeline-title">Posted operation timeline</h2>
    <ol class="ca-timeline"><?php foreach($detail['operations'] as $op): ?>
        <li class="ca-operation">
            <h3><?= $esc(ucfirst($op['kind'])) ?> · <?= $esc($op['date']) ?></h3>
            <p><strong><?= $op['is_reversal']?'Reversed original amount':($op['kind']==='release'?'Original release':'Original reduction') ?>: <?= $esc($money($op['amount'])) ?></strong> · <?= $esc($op['coverage']['status']) ?></p>
            <p class="ca-operation-meta">Recorded <?= $esc($op['recorded_at_display']) ?> (Asia/Manila) · Reference: <?= $esc($op['reference']?:'Not provided') ?></p>
            <p><a href="journal_transaction.php?journal_id=<?= (int)$op['journal_id'] ?>">View recorded transaction #<?= (int)$op['journal_id'] ?></a></p>
            <details class="ca-journal" data-ca-print><summary>Complete debit and credit journal</summary>
                <div class="aw-table-wrap"><table class="aw-table"><thead><tr><th scope="col">Account</th><th scope="col">Actual project</th><th scope="col">Debit (PHP)</th><th scope="col">Credit (PHP)</th></tr></thead><tbody>
                <?php foreach($op['lines'] as $l): ?><tr><td><?= $esc($l['account_name']) ?></td><td><?= $esc($l['project_name_snapshot']??'Organization operations') ?></td><td><?= $esc($l['debit_amount']) ?></td><td><?= $esc($l['credit_amount']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </details>
            <?php if($op['correction_id']): ?><p><a href="journal_corrections.php?journal_id=<?= (int)$op['journal_id'] ?>">View posted correction</a></p><?php endif; ?>
            <?php if($role==='Admin'&&!$op['is_reversal']&&stage3_enabled($pdo)): ?><p class="aw-no-print"><a href="journal_correction.php?journal_id=<?= (int)$op['journal_id'] ?>">Prepare correction draft</a></p><?php endif; ?>
            <?php if($role==='Admin'): foreach($op['attachments']??[] as $doc): ?><p class="aw-no-print"><a href="<?= $esc($doc['url']) ?>" target="_blank" rel="noopener"><?= $esc($doc['name']) ?></a></p><?php endforeach; endif; ?>
        </li>
    <?php endforeach; ?></ol>
</section>
<details class="aw-card ca-secondary" data-ca-print><summary>Deadline history</summary>
    <p>Initial deadline: <?= $esc($detail['initial_due_date']) ?></p>
    <?php if(!$detail['due_changes']): ?><p>No deadline extensions recorded through the selected date.</p><?php endif; ?>
    <?php foreach($detail['due_changes'] as $change): ?><p><?= $esc($change['effective_date']) ?>: <?= $esc($change['old_due_date']) ?> → <?= $esc($change['new_due_date']) ?> · <?= $esc($change['reason']) ?></p><?php endforeach; ?>
</details>
<?php if($detail['can_settle']): ?>
<details class="aw-card ca-secondary aw-no-print" id="ca-extension-panel"><summary>Extend liquidation deadline</summary>
    <p>Extensions take effect today in Asia/Manila and preserve earlier deadlines.</p>
    <form id="ca-extension" class="aw-grid"><label>New liquidation due date<input name="due_date" type="date" min="<?= journal_today() ?>" required></label><label>Reason for extension<textarea name="reason" required maxlength="2000"></textarea></label><button>Extend deadline</button></form>
</details>
<?php endif; ?>
<?php if($role==='Admin'&&$detail['external_approval']): ?><details class="aw-card ca-secondary" data-ca-print><summary>External approval recorded by accounting staff</summary><p><?= $esc($detail['external_approval']['name']) ?> · <?= $esc($detail['external_approval']['date']) ?> · <?= $esc($detail['external_approval']['reference']) ?></p><p>This records staff-supplied external approval information; it is not system approval.</p></details><?php endif; ?>
<?php else: ?>
<section class="aw-card ca-register-summary"><h2 id="ca-basis">Posted advances</h2><p id="ca-notice"></p><p id="ca-applied-filters" class="ca-register-scope"></p><p id="ca-print-scope" class="ca-register-scope"></p><h3 id="ca-totals-basis">Matching advance totals</h3><div class="aw-totals ca-balance-cards" id="ca-totals"></div></section>
<form id="ca-filters" class="aw-card aw-no-print"><h2>Filter advances</h2><div class="aw-grid">
    <label>As of<input name="as_of" type="date" value="<?= $esc($asof) ?>" max="<?= journal_today() ?>" min="1000-01-01" required></label>
    <?php foreach(['party_id'=>'Employee','project_id'=>'Originating project','control_account_id'=>'Control account'] as $field=>$label): ?>
        <label><?= $label ?><select name="<?= $field ?>"><option value="">All</option><?php if($field==='project_id'): ?><option value="organization">Organization operations</option><?php endif; $seen=[];foreach($options as $row){$value=$row[$field==='project_id'?'originating_project_id':$field];if($value===null||isset($seen[$value]))continue;$seen[$value]=true;$text=$field==='party_id'?$row['employee']:($field==='project_id'?$row['project']:($controlNames[$value]??'Account #'.$value)); ?><option value="<?= $value ?>"><?= $esc($text) ?></option><?php } ?></select></label>
    <?php endforeach; ?>
    <label>Settlement status<select name="status"><option value="">All</option><option value="outstanding">Outstanding</option><option value="partially_settled">Partially settled</option><option value="settled">Settled</option><option value="cancelled">Cancelled</option><option value="replaced">Replaced</option></select></label>
    <label>Overdue<select name="overdue"><option value="">All</option><option value="1">Overdue</option><option value="0">Not overdue</option></select></label>
    <label class="ca-search">Search number, employee, purpose or reference<input name="search" maxlength="200" type="search"></label>
</div><div class="aw-actions"><span id="ca-filter-status" role="status"></span><button class="aw-primary">Apply filters</button></div></form>
<section class="aw-card"><h2>Advances</h2>
    <div class="aw-table-wrap"><table class="aw-table ca-register-table"><thead><tr><th scope="col">Advance / employee</th><th scope="col">Purpose / originating project</th><th scope="col">Outstanding (PHP)</th><th scope="col">Deadline / status</th><th scope="col">Amount details</th></tr></thead><tbody id="ca-rows"></tbody></table></div>
    <div class="aw-actions aw-no-print"><button type="button" id="ca-prev">Previous</button><span id="ca-page"></span><button type="button" id="ca-next">Next</button></div>
</section>
<?php endif; ?>
<details class="aw-card ca-secondary" id="ca-reconciliation-details" data-ca-print hidden>
    <summary>Full control-account reconciliation — independent of register filters</summary>
    <p id="ca-reconciliation-basis">No successful control-account check is available.</p>
    <div id="ca-reconciliation"></div>
</details>
<noscript><p>JavaScript is required to load register results and the independent reconciliation check. Advance detail amounts and posted journals above remain read-only.</p></noscript>
</div>
<script type="application/json" id="advance-data"><?= json_encode(['csrf_token'=>csrf_token(),'detail'=>$detail,'has_posted_advances'=>(bool)$options,'role'=>$role],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php layout_end('<script src="assets/js/cash_advances.js?v='.filemtime(__DIR__.'/assets/js/cash_advances.js').'"></script>'); ?>
