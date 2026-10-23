<?php
session_start();
require_once __DIR__.'/includes/require_role.php';
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/general_ledger_query.php';
require_once __DIR__.'/includes/layout.php';
header('Cache-Control: private, no-store');
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$format=$_GET['format']??'html';$data=null;$error='';$status=200;
try{
    if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');throw new LedgerProblem('Use GET to view the ledger.',405);}
    if(!is_string($format)||!in_array($format,['html','json','print'],true))throw new LedgerProblem('Choose a valid ledger view.');
    $data=ledger_read($pdo,(int)($_SESSION['UserID']??0),$_GET,$format==='print');
}catch(LedgerProblem $e){$status=$e->status;$error=$e->getMessage();}
catch(Throwable $e){$status=503;$error='The ledger could not be loaded. Please try again.';error_log('General Ledger read unavailable.');}
http_response_code($status);
if($format==='json'){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$data!==null,'data'=>$data,'error'=>$error],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
if(in_array($status,[401,403,405],true))exit($escape($error));
$criteria=$data['criteria']??ledger_request([]);
$accounts=$data['accounts']??[];
if($data===null){
    try{$neutral=ledger_read($pdo,(int)($_SESSION['UserID']??0),[]);$accounts=$neutral['accounts'];}catch(Throwable $e){/* Keep the original unavailable response. */}
    foreach($criteria as $key=>$value)if(isset($_GET[$key])&&is_string($_GET[$key])&&preg_match('//u',$_GET[$key]))$criteria[$key]=$_GET[$key];
}
$print=$format==='print';$account=$data['account']??null;
$label=$account?$account['Name'].($account['Account_Code']?' · '.$account['Account_Code']:'').(!(int)$account['Is_Active']?' (inactive)':''):'Choose an account';
$printUrl=$account?ledger_url(array_replace($criteria,['search'=>'','page'=>'1'])).'&format=print':'#';
layout_begin('General Ledger','general_ledger',[], '<link rel="stylesheet" href="assets/css/general_ledger.css?v='.filemtime(__DIR__.'/assets/css/general_ledger.css').'">');
?>
<main class="general-ledger<?= $print?' gl-print-view':'' ?>" id="gl-root">
<header class="gl-heading"><div><?php if($print): ?><p class="gl-eyebrow">ATIKHA FINANCE</p><?php endif; ?><p class="gl-eyebrow">BOOKS OF ACCOUNTS · READ ONLY</p><h1>General Ledger</h1><p>Review one account's opening balance, movements and ending balance.</p></div><a href="reports.php" class="gl-nav-button gl-no-print">Reports</a></header>
<p id="gl-error" class="gl-error" role="alert"<?= $error===''?' hidden':'' ?>><?= $escape($error) ?></p>
<?php if(!$print): ?>
<form id="gl-controls" class="gl-controls" method="get" action="general_ledger.php">
<label>Account<select name="account_id" id="gl-account"><option value="">Choose an account</option><?php foreach($accounts as $a): ?><option value="<?= $escape($a['CategoryID']) ?>"<?= $criteria['account_id']===(string)$a['CategoryID']?' selected':'' ?>><?= $escape(($a['Account_Code']?$a['Account_Code'].' · ':'').$a['Name'].(!(int)$a['Is_Active']?' (inactive)':'')) ?></option><?php endforeach; ?></select></label>
<label>From<input name="from" type="date" required value="<?= $escape($criteria['from']) ?>"></label><label>To<input name="to" type="date" required value="<?= $escape($criteria['to']) ?>"></label>
<label class="gl-search-label">Search movements<input name="search" type="search" maxlength="1000" value="<?= $escape($criteria['search']) ?>" placeholder="Journal, reference, purpose, party or project"></label>
<label>Rows<select name="page_size"><?php foreach(['25','50','100'] as $size): ?><option<?= $criteria['page_size']===$size?' selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label>
<input type="hidden" name="page" value="1"><button id="gl-apply" class="gl-primary" type="submit">Apply</button><a href="general_ledger.php">Reset</a>
</form>
<p class="gl-status" id="gl-status" role="status">Applied filters are shown with the account below.</p>
<?php endif; ?>
<section class="gl-sheet" id="gl-sheet"<?= !$account?' hidden':'' ?> aria-label="Account movements">
<div class="gl-sheet-heading"><div><h2 id="gl-account-label"><?= $escape($label) ?></h2><p id="gl-period-label"><?= $escape($criteria['from'].' to '.$criteria['to']) ?> · <?= $print?'Full account period, all movements':'Live posted account movements' ?></p></div>
<?php if(!$print): ?><a id="gl-export" class="gl-action gl-no-print" href="<?= $escape('financial_export.php?'.http_build_query($criteria+['kind'=>'ledger'])) ?>">Export matching rows</a> <a id="gl-print" class="gl-action gl-no-print" href="<?= $escape($printUrl) ?>" target="_blank" rel="noopener">Print full period</a><?php else: ?><button type="button" class="gl-action gl-no-print" onclick="window.print()">Print / Save PDF</button><?php endif; ?></div>
<dl class="gl-balances"><div><dt>Opening balance</dt><dd id="gl-opening"><?= $escape(ledger_balance($data['opening']??'0.00')) ?></dd></div><div><dt>Period debits</dt><dd id="gl-debits"><?= $escape(accounting_money($data['period_debits']??'0.00')) ?></dd></div><div><dt>Period credits</dt><dd id="gl-credits"><?= $escape(accounting_money($data['period_credits']??'0.00')) ?></dd></div><div><dt>Ending balance</dt><dd id="gl-ending"><?= $escape(ledger_balance($data['ending']??'0.00')) ?></dd></div></dl>
<p id="gl-counts"><?= $account?$escape($print?'All '.$data['period_count'].' movements for the account period.':'Showing '.count($data['rows']).' of '.$data['matching_count'].' matching movements; '.$data['period_count'].' period movements. Balances include movements hidden by search.') :'' ?></p>
<p id="gl-page-notice" role="status"><?= ($data['page_adjusted']??false)?'The requested page was beyond the results. Showing the last available page.':'' ?></p>
<div class="gl-table-wrap" tabindex="0" role="region" aria-label="Chronological ledger table"><table><thead><tr><th>Date / journal</th><th>Reference / description</th><th>Party / project</th><th class="gl-amount">Debit (PHP)</th><th class="gl-amount">Credit (PHP)</th><th class="gl-amount">Running balance</th><th class="gl-no-print">Transaction</th></tr></thead>
<tbody id="gl-rows"><?php foreach($data['rows']??[] as $row): ?><tr><td><?= $escape($row['entry_date']) ?><br>Journal #<?= $escape($row['journal_id']) ?> · <?= $escape($row['source_book']) ?></td><td><?= $escape($row['reference']?:'Not provided') ?><br><?= $escape($row['description']) ?></td><td><?= $escape($row['party_name']??'Not recorded') ?><br><?= $escape($row['project_name_snapshot']??'Organization operations') ?></td><td class="gl-amount"><?= $escape(accounting_money($row['debit_amount'])) ?></td><td class="gl-amount"><?= $escape(accounting_money($row['credit_amount'])) ?></td><td class="gl-amount"><?= $escape(ledger_balance($row['running_balance'])) ?></td><td class="gl-no-print"><a href="<?= $escape($row['transaction_url']) ?>">View transaction</a></td></tr><?php endforeach; ?>
<?php if($account&&!$data['rows']): ?><tr><td colspan="7"><?= $data['period_count']?'No movements match this search. Opening, totals and ending still cover the full period.':'No movements in this period. The opening balance carries forward.' ?></td></tr><?php endif; ?></tbody>
<tfoot><tr><th colspan="3">Full period totals / ending balance</th><td id="gl-total-debits" class="gl-amount"><?= $escape(accounting_money($data['period_debits']??'0.00')) ?></td><td id="gl-total-credits" class="gl-amount"><?= $escape(accounting_money($data['period_credits']??'0.00')) ?></td><td id="gl-total-ending" class="gl-amount"><?= $escape(ledger_balance($data['ending']??'0.00')) ?></td><td class="gl-no-print"></td></tr></tfoot></table></div>
<?php if(!$print): ?><nav class="gl-pagination gl-no-print" aria-label="Ledger pages"><a class="gl-page" id="gl-prev" href="<?= $escape(ledger_url(array_replace($criteria,['page'=>(string)max(1,(int)$criteria['page']-1)]))) ?>" aria-disabled="<?= (int)$criteria['page']<=1?'true':'false' ?>">Previous</a><span id="gl-page-label">Page <?= $escape($criteria['page']) ?> of <?= (int)($data['pages']??1) ?></span><a class="gl-page" id="gl-next" href="<?= $escape(ledger_url(array_replace($criteria,['page'=>(string)min((int)($data['pages']??1),(int)$criteria['page']+1)]))) ?>" aria-disabled="<?= (int)$criteria['page']>=(int)($data['pages']??1)?'true':'false' ?>">Next</a></nav><?php endif; ?>
<p class="gl-generated" id="gl-generated">Generated <?= $escape($data['generated_at']??'') ?>. Live values may change when new transactions are recorded.</p>
</section><p id="gl-prompt"<?= $account?' hidden':'' ?>><?= $error!==''?'Correct the filters above and apply again.':'Choose an account and period, then select Apply.' ?></p>
</main>
<?php if(!$print): ?><script type="application/json" id="gl-initial"><?= json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script><?php endif; ?>
<?php layout_end(!$print?'<script src="assets/js/general_ledger.js?v='.filemtime(__DIR__.'/assets/js/general_ledger.js').'"></script>':''); ?>
