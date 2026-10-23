<?php
/** Grow offset-neutral selected-account workload through real writers; no DDL. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:','lines:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private synthetic fixture required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_s4g1perf[a-z0-9]+\z/',$db),'Fresh Stage4 performance clone required.');
$target=(int)($o['lines']??0);cli_require(in_array($target,[500,5000],true),'Use a declared 500 or 5000 line workload.');
$dir=dirname($path);cli_require(!file_exists($dir.'/stage4-workload-started.json'),'Fresh workload required.');
$evidence=realpath($f['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$private.DIRECTORY_SEPARATOR),'Private synthetic evidence required.');
define('RECEIPT_UPLOAD_DIR',$evidence);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/accounting_workspace.php';require __DIR__.'/../includes/general_ledger_query.php';
$case=json_decode(file_get_contents($dir.'/stage4-ledger-fixture.json'),true,64,JSON_THROW_ON_ERROR);$criteria=$case['criteria'];
$p=cli_db($db);$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic owner required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$before=ledger_read($p,$uid,$criteria);$remaining=$target-$before['period_count'];cli_require($remaining>=0,'Source workload already too large.');
file_put_contents($dir.'/stage4-workload-started.json','{}');
while($remaining>0){
    $count=min(100,$remaining);if(($count%2)===1&&$count<3)throw new RuntimeException('Cannot create a single offset-neutral line.');
    // An odd-sized journal begins with three balanced selected-account lines.
    $amounts=[];if($count%2){$amounts=[['1.00',''],['2.00',''],['','3.00']];}
    while(count($amounts)<$count){$amounts[]=['1.00',''];$amounts[]=['','1.00'];}
    // Leave three rather than one when the final remainder is odd.
    if($remaining-$count===1){$count-=2;$amounts=array_slice($amounts,0,$count);}
    $lines=[];foreach($amounts as $i=>[$debit,$credit])$lines[]=['client_id'=>'line'.$i,'account_id'=>$criteria['account_id'],'fund_project_id'=>'','debit_amount'=>$debit,'credit_amount'=>$credit];
    $v=['entry_date'=>substr($criteria['from'],0,7).'-10','reference'=>'S4 neutral workload','description'=>'Synthetic offset-neutral performance movements','party_id'=>'','default_project_id'=>'','cash_account_id'=>'','cash_amount'=>'','cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>$lines,'documents'=>[]];
    $request=['csrf_token'=>csrf_token(),'draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'source_book'=>'GJ','payload'=>$v];
    $d=workspace_save($p,$uid,$request);$identity=['csrf_token'=>csrf_token(),'draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];
    $review=workspace_review($p,$uid,$identity);workspace_post($p,$uid,$identity+['payload'=>$d['payload'],'review_token'=>$review['token']]);$remaining-=count($lines);
}
$after=ledger_read($p,$uid,$criteria);cli_require($after['period_count']===$target&&$after['ending']===$before['ending'],'Actual workload counts/balance must match.');
$counts=[];foreach(['journal_entries','journal_entry_lines','journal_drafts','cash_advances'] as $table)$counts[$table]=(int)$p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
$out=['database'=>$db,'criteria'=>$criteria,'selected_period_lines'=>$after['period_count'],'ending'=>$after['ending'],'total_counts'=>$counts,'writer_preparation_complete'=>true,'server_mode'=>'normal MariaDB3306; no startup/configuration/cache changes'];
file_put_contents($dir.'/stage4-workload.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
