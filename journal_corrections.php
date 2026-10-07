<?php
/** Posted financial list/comparisons; all private projections use trusted server readers. */
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin','Management'],'Posted corrections');
require_once __DIR__.'/includes/journal_corrections.php';require_once __DIR__.'/includes/layout.php';
header('Cache-Control: private, no-store');
try{
    $uid=(int)$_SESSION['UserID'];correction_reader_guard($pdo,$uid);$id=null;$detail=null;
    $journal=journal_string($_GET,'journal_id',true);$correction=journal_string($_GET,'correction_id',true);
    if($journal!==''&&$correction!=='')throw new JournalProblem('Choose one correction lookup.');
    if($correction!==''){$cid=journal_id($correction);$s=$pdo->prepare('SELECT target_journal_id FROM journal_corrections WHERE id=?');$s->execute([$cid]);$found=$s->fetchColumn();if($found===false)throw new JournalProblem('Posted correction not found.',404);$id=(int)$found;}
    elseif($journal!=='')$id=journal_id($journal);
    if($id!==null){$detail=correction_posted_detail($pdo,$uid,$id);$chain=$detail['chain'];}
    else{$chain=accounting_read($pdo,function()use($pdo){$rows=$pdo->query('SELECT * FROM journal_corrections ORDER BY accounting_date DESC,id DESC')->fetchAll();$out=[];foreach($rows as $c){if(!stage3_correction_valid($pdo,$c))throw new JournalProblem('Correction chain integrity failed.',409);$out[]=array_intersect_key($c,array_flip(['id','accounting_date','reason','target_journal_id','reversal_journal_id','replacement_journal_id','created_at']));}return $out;});}
}catch(JournalProblem $e){http_response_code($e->status);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$manila=fn($v)=>(new DateTimeImmutable($v,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
layout_begin('Posted corrections','general_journal',[], '<link rel="stylesheet" href="assets/css/accounting_workspace.css?v='.filemtime(__DIR__.'/assets/css/accounting_workspace.css').'">');
?>
<div class="accounting-workspace correction-details">
<header class="aw-header"><div><p class="aw-eyebrow">POSTED FINANCIAL HISTORY</p><h1><?= $detail?'Posted correction comparison':'Posted corrections' ?></h1><p>Originals remain posted. General Journal reversals and replacements show the complete correction history.</p></div><div class="correction-navigation"><a href="journal_corrections.php">All corrections</a> <button type="button" onclick="window.print()">Print comparison</button></div></header>
<?php if(!$chain): ?><p>No posted corrections.</p><?php endif; ?>
<?php foreach($chain as $c): ?><section class="aw-card correction-comparison" id="correction-<?= $esc($c['id']) ?>"><h2><a href="journal_corrections.php?correction_id=<?= $esc($c['id']) ?>">Correction #<?= $esc($c['id']) ?></a></h2><p class="aw-print-label">POSTED &mdash; Accounting date: <?= $esc($c['accounting_date']) ?> &middot; Recorded: <?= $esc($manila($c['created_at'])) ?> (Asia/Manila)</p><p><?= $esc($c['reason']) ?></p><p>Original #<?= $esc($c['target_journal_id']) ?> &middot; GJ reversal #<?= $esc($c['reversal_journal_id']) ?> &middot; <?= $c['replacement_journal_id']===null?'Reversal only':'Replacement #'.$esc($c['replacement_journal_id']) ?></p>
<?php if($detail): ?>
<p>At this correction date, the original and exact reversal cancel. <?= $c['replacement_journal_id']===null?'The corrected net ledger effect is zero.':'The replacement below is the corrected net ledger effect.' ?> Later corrections remain separate steps in this chain.</p>
<?php foreach(['target_journal_id'=>'Original','reversal_journal_id'=>'Generated reversal','replacement_journal_id'=>'Replacement'] as $key=>$label): if($c[$key]===null)continue;$j=$detail['journals'][$c[$key]];$h=$j['header'];$party=$h['party_snapshot']?json_decode($h['party_snapshot'],true):null; ?>
<div class="correction-journal"><h3><?= $esc($label) ?> journal #<?= $esc($c[$key]) ?> &mdash; <?= $esc($h['source_book']) ?></h3><p>Accounting date <?= $esc($h['entry_date']) ?> &middot; <?= $esc($party['name']??'No party') ?> &middot; Reference: <?= $esc($h['reference']??'Not recorded') ?></p><p><?= $esc($h['description']) ?></p><p>Evidence: <?= $esc($j['evidence_coverage']['status']??'Not recorded') ?></p>
<div class="aw-table-wrap"><table class="aw-table"><thead><tr><th>Account</th><th>Project</th><th>Debit (PHP)</th><th>Credit (PHP)</th></tr></thead><tbody>
<?php foreach($j['lines'] as $line): ?><tr><td><?= $esc(($line['account_code']??'#'.$line['account_id']).' - '.$line['account_name']) ?></td><td><?= $esc($line['project_name_snapshot']??'Organization operations') ?></td><td><?= $esc($line['debit_amount']) ?></td><td><?= $esc($line['credit_amount']) ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><th colspan="2">Complete entry totals</th><td><?= $esc(ledger_decimal((int)$j['debit_cents'])) ?></td><td><?= $esc(ledger_decimal((int)$j['credit_cents'])) ?></td></tr></tfoot></table></div>
<?php foreach($j['attachments'] as $a): ?><p><a href="<?= $esc($a['url']) ?>"><?= $esc($a['name']) ?></a> &mdash; <?= $esc(($a['review']['purpose']??'')==='amount'?'Reviewed amount PHP '.$a['review']['accepted_amount']:'Supporting document') ?></p><?php endforeach; ?>
</div><?php endforeach; ?>
<?php endif; ?></section><?php endforeach; ?>
<nav class="correction-navigation"><a href="financial_records.php?view=crb&amp;from=&amp;to=">View Cash Receipts Book</a> &middot; <a href="financial_records.php?view=cdb&amp;from=&amp;to=">View Cash Disbursements Book</a> &middot; <a href="financial_records.php?from=&amp;to=">View Journal History</a></nav>
<p>Cash books show gross originating activity, including originals and replacements. Their totals alone are not corrected net cash amounts; linked General Journal reversals supply the offsets.</p>
</div>
<?php layout_end(); ?>
