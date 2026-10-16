<?php
/** Explicit synthetic writer setup for U2-C records checks; no working DB accepted. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$options=getopt('',['fixture:','marker:']);$base=realpath(__DIR__.'/../.migration-private');$path=realpath($options['fixture']??'');
cli_require($path&&str_starts_with($path,$base.DIRECTORY_SEPARATOR),'Private fixture required');
$fixture=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$fixture['database'];$marker=$options['marker']??'';
cli_require((bool)preg_match('/\Aatikha_test_stage1_[a-z0-9]+\z/',$db),'Disposable database required');
cli_require((bool)preg_match('/\AU2C[0-9A-F]{12}\z/',$marker),'Synthetic unique marker required');
$evidence=realpath($fixture['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$base.DIRECTORY_SEPARATOR),'Private evidence required');
define('RECEIPT_UPLOAD_DIR',$evidence);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/accounting_workspace.php';$p=cli_db($db);
cli_require(stage1_enabled($p)&&stage2_schema($p)&&stage3_schema($p),'Complete operational fixture required');
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic owner required');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$s=$p->prepare('SELECT COUNT(*) FROM journal_entries WHERE description LIKE ?');$s->execute([$marker.'%']);cli_require((int)$s->fetchColumn()===0,'Fresh unique records marker required');
$ids=[];
foreach(['CRB'=>13,'CDB'=>13,'GJ'=>2] as $book=>$count){
    for($i=0;$i<$count;$i++){
        $counter=(string)$fixture[$book==='CRB'?'income':'training'];$side=$book==='CRB'?'credit_amount':'debit_amount';$opposite=$book==='CRB'?'debit_amount':'credit_amount';
        $lines=[['client_id'=>'counter','account_id'=>$counter,'fund_project_id'=>'','debit_amount'=>'','credit_amount'=>'']];$lines[0][$side]='10.00';
        if($book!=='GJ'&&$i===0){$lines[0][$side]='5.00';$lines[]=$lines[0];$lines[1]['client_id']='second';}
        if($book==='GJ')$lines[]=['client_id'=>'bank','account_id'=>(string)$fixture['bank'],'fund_project_id'=>'','debit_amount'=>'','credit_amount'=>'10.00'];
        $payload=['entry_date'=>journal_today(),'reference'=>$marker.'-'.$book.'-'.$i,'description'=>$marker.' synthetic book entry '.$book.' '.$i,'party_id'=>$book==='GJ'?'':(string)$fixture['party'],'default_project_id'=>'','cash_account_id'=>$book==='GJ'?'':(string)$fixture['bank'],'cash_amount'=>$book==='GJ'?'':'10.00','cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>$lines,'documents'=>[]];
        $csrf=['csrf_token'=>csrf_token()];$draft=workspace_save($p,$uid,$csrf+['draft_id'=>'','source_book'=>$book,'submission_key'=>bin2hex(random_bytes(32)),'payload'=>$payload]);$identity=['draft_id'=>(string)$draft['id'],'revision'=>(string)$draft['revision'],'submission_key'=>$draft['submission_key']];
        $review=workspace_review($p,$uid,$csrf+$identity);$posted=workspace_post($p,$uid,$csrf+$identity+['payload'=>$draft['payload'],'review_token'=>$review['token']]);$ids[$book][]=$posted['id'];
    }
}
echo json_encode(['marker'=>$marker,'journals'=>$ids,'amount_per_book'=>'130.00'],JSON_THROW_ON_ERROR);
