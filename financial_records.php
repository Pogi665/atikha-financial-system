<?php
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/require_role.php';
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/accounting_query.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit('This page is read-only.');}
$flags=layout_role_flags();$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$filters=accounting_records_filters([]);$data=['rows'=>[],'journals'=>[]];$accounts=[];$filterError='';$ledgerError=false;
try{
    $filters=accounting_records_filters($_GET);
    accounting_read($pdo,function()use($pdo,&$filters,&$accounts,&$data){
        $accounts=accounting_accounts($pdo);
        // Old account drill-down links map by exact name AND type, never by name alone.
        if(isset($_GET['filter_category'])||isset($_GET['filter_type'])||isset($_GET['category'])){
            $name=$_GET['filter_category']??$_GET['category']??'';
            $legacyType=$_GET['filter_type']??$filters['type'];$type=['Fund'=>'Income','Expense'=>'Expense','Incoming'=>'Income'][$legacyType]??$legacyType;
            $matches=array_values(array_filter($accounts,static fn($a)=>$a['Name']===$name&&($type===''||$a['Account_Type']===$type)));
            if($name!==''&&count($matches)!==1){throw new InvalidArgumentException('This account link is ambiguous or unavailable. Select an account by ID.');}
            if($name!==''){$filters['account_id']=(string)$matches[0]['CategoryID'];$filters['type']=$matches[0]['Account_Type'];}
        }
        if($filters['account_id']!==''&&!in_array($filters['account_id'],array_map('strval',array_column($accounts,'CategoryID')),true)){throw new InvalidArgumentException('Account not found.');}
        $data=accounting_records($pdo,$filters);
    });
}catch(InvalidArgumentException $e){http_response_code(400);$filterError=$e->getMessage();}
catch(Throwable $e){http_response_code(503);$ledgerError=true;error_log('Journal history: '.$e->getMessage());}
$context=$filters['context'];$title=['crb'=>'Cash Receipts Book','cdb'=>'Cash Disbursements Book'][$context]??'Journal History / General Ledger';
$activePage=['crb'=>'crb','cdb'=>'cdb'][$context]??'financial_records';
$clearUrl='financial_records.php?from=&to='.($context!=='records'?'&view='.$context:'');
$scope=$filters['from']===''&&$filters['to']===''?'all':($filters['from']===substr(accounting_today(),0,7).'-01'&&$filters['to']===accounting_today()?'month':'custom');
layout_begin($title,$activePage,[], '<link rel="stylesheet" href="assets/vendor/datatables/2.3.8/dataTables.dataTables.min.css"><link rel="stylesheet" href="assets/css/financial_records.css">','min-h-screen min-w-[1024px] bg-slate-50 financial-records-page'.($flags['isExecutive']?' executive-theme':''));
?>
<div class="records-page" data-context="<?= $escape($context) ?>"><h1 class="text-2xl font-bold text-slate-900"><?= $escape($title) ?></h1>
<p>Read-only posted journal lines, newest first. Dates use Asia/Manila.</p>
<section class="records-card <?= $flags['isExecutive']?'exec-card':'' ?>"><h2>Filter Records</h2>
<form method="GET" id="records-filters" action="financial_records.php">
<?php if($context!=='records'): ?><input type="hidden" name="view" value="<?= $escape($context) ?>"><?php endif; ?>
<label>Date scope <select id="date-scope"><?php foreach(['all'=>'All dates','month'=>'This month through today','custom'=>'Custom range'] as $value=>$label): ?><option value="<?= $value ?>" <?= $scope===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>From <input type="date" id="from" name="from" value="<?= $escape($filters['from']) ?>"></label><label>Through <input type="date" id="to" name="to" value="<?= $escape($filters['to']) ?>"></label>
<label>Account type <select name="type" id="type"><option value="">All types</option><?php foreach(ACCOUNTING_TYPES as $type): ?><option <?= $filters['type']===$type?'selected':'' ?>><?= $type ?></option><?php endforeach; ?></select></label>
<label>Account <select name="account_id" id="account_id"><option value="">All accounts</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['CategoryID'] ?>" <?= $filters['account_id']===(string)$a['CategoryID']?'selected':'' ?>><?= $escape(($a['Account_Code']??'#'.$a['CategoryID']).' · '.$a['Name'].' · '.$a['Account_Type'].(!(int)$a['Is_Active']?' (inactive)':'')) ?></option><?php endforeach; ?></select></label>
<button>Apply Filters</button><a href="<?= $escape($clearUrl) ?>">Clear filters</a></form>
<p class="records-filter-summary">Date range: <?= $escape($filters['from']?:'All earlier dates') ?> through <?= $escape($filters['to']?:'All later dates') ?> · <?= $escape($filters['type']?:'All account types') ?></p></section>
<?php if($filterError!==''): ?><p role="alert"><?= $escape($filterError) ?></p><?php elseif($ledgerError): ?><p role="alert">Unable to load journal history. Please try again later.</p><?php else: ?>
<section class="records-summaries" aria-label="Matching journal line totals"><?php foreach(['debit'=>'Total Debits','credit'=>'Total Credits','difference'=>'Difference'] as $key=>$label): ?><div class="records-card"><h2><?= $label ?></h2><p id="records-total-<?= $key ?>">Calculating…</p></div><?php endforeach; ?></section>
<section class="records-card <?= $flags['isExecutive']?'exec-card':'' ?>"><h2>Posted Journal Lines</h2><p>Totals follow all filters and search across every page. A filtered subset can differ even when its complete journals balance; use Trial Balance to check all accounts.</p>
<?php if($context!=='records'): ?><p>Cash-book view: <?= $context==='crb'?'debit':'credit' ?> lines of cash-flagged Asset accounts. Internal transfers may appear in both books. This does not classify operating cash flow or restricted funds.</p><?php endif; ?>
<div class="records-table-scroll"><table id="records-table" class="display" aria-label="Posted journal lines"><thead><tr><?php foreach(['Date','Reference','Description','Account','Debit','Credit','View','Journal order','Line order'] as $label): ?><th><?= $label ?></th><?php endforeach; ?></tr></thead><tbody></tbody></table></div>
<p id="records-page" role="status"></p><p id="records-empty-help" hidden>No records match. <a href="<?= $escape($clearUrl) ?>">Clear filters</a>.</p><noscript><p role="alert">Enable JavaScript to search and view journal lines.</p></noscript>
<dialog id="transaction-dialog" aria-labelledby="transaction-dialog-title"><div class="records-dialog-heading"><h2 id="transaction-dialog-title">Complete Journal Entry</h2><button type="button" id="transaction-close">Close</button></div><dl id="transaction-details"></dl><div id="transaction-documents" class="records-table-scroll"></div></dialog>
</section>
<script id="records-data" type="application/json"><?= json_encode($data+['page'=>$filters['page'],'monthStart'=>substr(accounting_today(),0,7).'-01','today'=>accounting_today()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?></div>
<?php layout_end('<script src="assets/vendor/jquery/3.7.1/jquery.min.js"></script><script src="assets/vendor/datatables/2.3.8/dataTables.min.js"></script><script src="assets/js/financial_records.js"></script>'); ?>
