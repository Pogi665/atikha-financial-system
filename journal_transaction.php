<?php
/** Exact posted record; no financial writes, private drafts or all-history queries. */
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();
require_once __DIR__.'/includes/accounting_query.php';require_once __DIR__.'/includes/journal.php';require_once __DIR__.'/includes/layout.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit('Use GET to view a posted transaction.');}
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
try{
    $value=$_GET['journal_id']??null;
    if(!is_string($value)||!preg_match('/\A[0-9]{1,10}\z/',$value)||ltrim($value,'0')===''||strlen(ltrim($value,'0'))===10&&strcmp(ltrim($value,'0'),'4294967295')>0)throw new JournalProblem('Choose a valid positive transaction ID.',400);
    $id=(int)$value;$j=accounting_posted_journal($pdo,(int)($_SESSION['UserID']??0),$id);$h=$j['header'];
    $itemUrls=item_review_urls($pdo,[$id]);foreach($j['attachments'] as &$attachment)if(isset($itemUrls[$id][$attachment['id']]))$attachment['item_review_url']=$itemUrls[$id][$attachment['id']];unset($attachment);
    $party=isset($h['party_snapshot'])&&$h['party_snapshot']?json_decode($h['party_snapshot'],true,64,JSON_THROW_ON_ERROR):null;
}catch(JournalProblem $e){http_response_code($e->status);exit($esc($e->getMessage()));}
catch(Throwable $e){error_log('Exact posted transaction reader unavailable.');http_response_code(503);exit('This transaction could not be displayed. Retry this same transaction link; do not record it again.');}
$book=$h['source_book']??'Legacy General Journal';$back=['CDB'=>'financial_records.php?view=cdb&from=&to=','CRB'=>'financial_records.php?view=crb&from=&to='][$book]??'financial_records.php?from=&to=';
require_once __DIR__.'/includes/general_ledger_query.php';
$ledgerBack=ledger_return_url($_GET);
if($ledgerBack!==null)$back=$ledgerBack;
require __DIR__.'/includes/transaction_detail_page.php';
