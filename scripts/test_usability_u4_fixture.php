<?php
/** Synthetic U4 targets through existing writers, on a fresh full-schema clone only. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private synthetic fixture required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];cli_require((bool)preg_match('/\Aatikha_test_stage1_u4[a-z0-9]+\z/',$db),'Fresh U4 database required.');
$receiptRoot=realpath($f['receipt_root']);cli_require($receiptRoot&&str_starts_with($receiptRoot,$private.DIRECTORY_SEPARATOR),'Private synthetic images required.');
cli_require(!file_exists(dirname($path).'/u4-cases.json'),'Do not reuse a consumed U4 preparation.');
define('RECEIPT_UPLOAD_DIR',$receiptRoot);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/cash_advance.php';require __DIR__.'/../includes/correction_posting.php';
$p=cli_db($db);cli_require(stage2_enabled($p)&&stage3_enabled($p),'Complete schema required; no migrations.');
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic owner required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$s2=json_decode(file_get_contents(dirname($path).'/stage2-fixture.json'),true,64,JSON_THROW_ON_ERROR);
function u4r(array $v):array{return $v+['csrf_token'=>csrf_token()];}
function u4id(array $d):array{return ['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];}
function u4payload(array $f):array{return ['entry_date'=>journal_today(),'reference'=>'U4-SYNTHETIC-'.bin2hex(random_bytes(5)),'description'=>'Synthetic community training correction target','party_id'=>(string)$f['employee'],'default_project_id'=>'','cash_account_id'=>(string)$f['bank'],'cash_amount'=>'100.00','cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>[],'documents'=>[],'control_account_id'=>(string)$f['control'],'due_date'=>journal_today(),'approval_name'=>'','approval_date'=>'','approval_reference'=>'','line_notes'=>[]];}
function u4line(int $account,string $debit,string $credit):array{return ['client_id'=>bin2hex(random_bytes(20)),'account_id'=>(string)$account,'fund_project_id'=>'','debit_amount'=>$debit,'credit_amount'=>$credit];}
function u4asave(PDO $p,int $uid,string $kind,array $payload,?int $advance=null,?array $d=null):array{return advance_save($p,$uid,u4r(($d?u4id($d):['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32))])+['workflow_kind'=>$kind,'advance_id'=>$advance===null?'':(string)$advance,'payload'=>$payload]));}
function u4post(PDO $p,int $uid,array $d,bool $advance):array{$r=$advance?advance_review($p,$uid,u4r(u4id($d))):workspace_review($p,$uid,u4r(u4id($d)));$input=u4r(u4id($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]);return $advance?advance_post($p,$uid,$input):workspace_post($p,$uid,$input);}
function u4support(PDO $p,int $uid,array $d,bool $correction,bool $monetary):array{
    $im=imagecreatetruecolor(64,64);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$name=bin2hex(random_bytes(16)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);
    $q=$p->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES(?,?,'image/png',?,?,?,'Failed')");$q->execute(['uploads/receipts/'.$name,'Synthetic U4 support.png',strlen($bytes),hash('sha256',$bytes),$uid]);$rid=(int)$p->lastInsertId();
    $q=$p->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Synthetic manual review')");$q->execute([$rid,$uid,bin2hex(random_bytes(32)),hash('sha256',$bytes),hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
    $d=$correction?correction_attach($p,$uid,u4r(u4id($d)+['receipt_id'=>(string)$rid])):workspace_attach($p,$uid,u4r(u4id($d)),$rid);
    $payload=$d['payload'];$replacement=&$payload;if($correction)$replacement=&$payload['replacement'];$doc=&$replacement['documents'][count($replacement['documents'])-1];$doc['reviewed']=true;
    if($monetary){$doc['purpose']='amount';$doc['support_side']='debit';$doc['declared_amount']=$doc['accepted_amount']='100.00';$doc['allocations']=[['client_id'=>$replacement['lines'][0]['client_id'],'amount'=>'100.00']];}unset($doc,$replacement);
    return $correction?correction_save($p,$uid,u4r(u4id($d)+['source_book'=>$d['source_book'],'mode'=>$d['correction_mode'],'target_journal_id'=>(string)$d['correction_target_journal_id'],'payload'=>$payload])):u4asave($p,$uid,$d['workflow_kind'],$payload,(int)$d['advance_id'],$d);
}
$cases=['database'=>$db,'ordinary'=>[],'advance'=>[]];
foreach(['CRB','CDB','GJ'] as $book)foreach(['reverse_only','reverse_replace'] as $mode){
    $payload=u4payload($s2);$payload['party_id']=(string)$f['party'];$payload['lines']=$book==='GJ'?[u4line((int)$s2['training'],'100.00',''),u4line((int)$s2['bank'],'','100.00')]:[u4line((int)($book==='CRB'?$f['income']:$s2['training']),$book==='CDB'?'100.00':'',$book==='CRB'?'100.00':'')];
    if($book==='GJ')$payload['cash_account_id']=$payload['cash_amount']='';
    $d=workspace_save($p,$uid,u4r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'source_book'=>$book,'payload'=>$payload]));$posted=u4post($p,$uid,$d,false);$target=correction_entry_target($p,(int)$posted['id']);$replacementBook=correction_book($target,$mode,$book);$seed=correction_seed($target,$mode,$replacementBook);$seed['reason']='Synthetic U4 '.$book.' '.$mode.' reason';
    $cd=correction_save($p,$uid,u4r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'target_journal_id'=>(string)$posted['id'],'mode'=>$mode,'source_book'=>$replacementBook,'payload'=>$seed]));
    $cases['ordinary'][]=['draft_id'=>$cd['id'],'target'=>$posted['id'],'book'=>$book,'mode'=>$mode];
}
foreach(['release','liquidation','return'] as $kind)foreach(['reverse_only','reverse_replace'] as $mode){
    $payload=u4payload($s2);$payload['cash_amount']='1000.00';$release=u4asave($p,$uid,'advance_release',$payload);$posted=u4post($p,$uid,$release,true);$advance=(int)$posted['advance_id'];
    if($kind!=='release'){
        $payload=u4payload($s2);$payload['control_account_id']=$payload['due_date']=$payload['party_id']='';
        if($kind==='liquidation'){$payload['cash_account_id']=$payload['cash_amount']='';$payload['lines']=[u4line((int)$s2['training'],'100.00','')];}
        $d=u4asave($p,$uid,'advance_'.$kind,$payload,$advance);$d=u4support($p,$uid,$d,false,$kind==='liquidation');
        if($kind==='return')$d=advance_confirm($p,$uid,u4r(u4id($d)));
        $posted=u4post($p,$uid,$d,true);
    }
    $target=correction_entry_target($p,(int)$posted['id']);$book=correction_book($target,$mode,'GJ');$seed=correction_seed($target,$mode,$book);$seed['reason']='Synthetic U4 advance '.$kind.' '.$mode.' reason';
    $cd=correction_save($p,$uid,u4r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'target_journal_id'=>(string)$posted['id'],'mode'=>$mode,'source_book'=>$book,'payload'=>$seed]));
    if($mode==='reverse_replace'&&$kind!=='release'){$cd=u4support($p,$uid,$cd,true,$kind==='liquidation');if($kind==='return')$cd=correction_confirm($p,$uid,u4r(u4id($cd)));}
    $cases['advance'][]=['draft_id'=>$cd['id'],'target'=>$posted['id'],'kind'=>$kind,'advance_id'=>$advance,'mode'=>$mode];
}
file_put_contents(dirname($path).'/u4-cases.json',json_encode($cases,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Prepared 12 synthetic correction cases through existing writers; no migrations.'.PHP_EOL;
