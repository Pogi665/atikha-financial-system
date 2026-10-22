<?php
/** Grow only a fresh synthetic full-schema clone through existing advance writers. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:','advances:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private fixture required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_perf[a-z0-9]+\z/',$db),'Fresh performance clone required.');
$dir=dirname($path);cli_require(!file_exists($dir.'/perf-started.json'),'Do not reuse consumed preparation.');
$uploads=realpath($f['receipt_root']);cli_require($uploads&&str_starts_with($uploads,$private.DIRECTORY_SEPARATOR),'Private synthetic evidence required.');
define('RECEIPT_UPLOAD_DIR',$uploads);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/cash_advance.php';require __DIR__.'/../includes/journal_corrections.php';
$p=cli_db($db);cli_require(stage2_enabled($p)&&stage3_enabled($p),'Integrated schema required; no migrations.');
$s2=json_decode(file_get_contents($dir.'/stage2-fixture.json'),true,64,JSON_THROW_ON_ERROR);
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic owner required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$target=(int)$p->query("SELECT c.target_journal_id FROM journal_corrections c JOIN cash_advance_operations o ON o.journal_id=c.target_journal_id WHERE o.operation_kind='release' ORDER BY c.id DESC LIMIT 1")->fetchColumn();cli_require($target>0,'An existing advance-release correction is required.');
cli_require(correction_integrity($p)===[],'Synthetic source integrity must pass.');
file_put_contents($dir.'/perf-started.json',json_encode(['database'=>$db,'target'=>$target]));
function pf_request(array $v):array{return $v+['csrf_token'=>csrf_token()];}
function pf_identity(array $d):array{return ['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];}
function pf_payload(array $s,string $amount):array{return ['entry_date'=>journal_today(),'reference'=>'PERF-SYNTHETIC','description'=>'Synthetic community training advance','party_id'=>(string)$s['employee'],'default_project_id'=>'','cash_account_id'=>(string)$s['bank'],'cash_amount'=>$amount,'cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>[],'documents'=>[],'control_account_id'=>(string)$s['control'],'due_date'=>journal_today(),'approval_name'=>'','approval_date'=>'','approval_reference'=>'','line_notes'=>[]];}
function pf_save(PDO $p,int $uid,string $kind,array $v,?int $advance=null,?array $d=null):array{return advance_save($p,$uid,pf_request(($d?pf_identity($d):['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32))])+['workflow_kind'=>$kind,'advance_id'=>$advance===null?'':(string)$advance,'payload'=>$v]));}
function pf_post(PDO $p,int $uid,array $d):array{$r=advance_review($p,$uid,pf_request(pf_identity($d)));return advance_post($p,$uid,pf_request(pf_identity($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]));}
function pf_document(PDO $p,int $uid,array $d,bool $monetary):array{
    $im=imagecreatetruecolor(24,24);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$name=bin2hex(random_bytes(16)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);
    $q=$p->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES(?,?,'image/png',?,?,?,'Failed')");$q->execute(['uploads/receipts/'.$name,'Synthetic performance support.png',strlen($bytes),hash('sha256',$bytes),$uid]);$rid=(int)$p->lastInsertId();
    $q=$p->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Synthetic manual review')");$q->execute([$rid,$uid,bin2hex(random_bytes(32)),hash('sha256',$bytes),hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
    $d=workspace_attach($p,$uid,pf_request(pf_identity($d)),$rid);$v=$d['payload'];$doc=&$v['documents'][count($v['documents'])-1];$doc['reviewed']=true;
    if($monetary){$doc['purpose']='amount';$doc['support_side']='debit';$doc['declared_amount']=$doc['accepted_amount']='80.00';$doc['allocations']=[['client_id'=>'cost','amount'=>'80.00']];}unset($doc);
    return pf_save($p,$uid,$d['workflow_kind'],$v,(int)$d['advance_id'],$d);
}
$desired=(int)($o['advances']??0);cli_require($desired>=0&&$desired<=100,'At most 100 advances.');$initial=(int)$p->query('SELECT COUNT(*) FROM cash_advances')->fetchColumn();
for($i=$initial;$i<$desired;$i++){
    $d=pf_save($p,$uid,'advance_release',pf_payload($s2,'100.00'));$release=pf_post($p,$uid,$d);$aid=(int)$release['advance_id'];
    $v=pf_payload($s2,'');$v['cash_account_id']=$v['control_account_id']=$v['due_date']=$v['party_id']='';$v['lines']=[['client_id'=>'cost','account_id'=>(string)$s2['training'],'fund_project_id'=>'','debit_amount'=>'80.00','credit_amount'=>'']];
    $d=pf_save($p,$uid,'advance_liquidation',$v,$aid);$d=pf_document($p,$uid,$d,true);pf_post($p,$uid,$d);
    $v=pf_payload($s2,'20.00');$v['control_account_id']=$v['due_date']=$v['party_id']='';$d=pf_save($p,$uid,'advance_return',$v,$aid);$d=pf_document($p,$uid,$d,false);$d=advance_confirm($p,$uid,pf_request(pf_identity($d)));pf_post($p,$uid,$d);
    cli_require(advance_detail($p,$uid,$aid,journal_today())['outstanding']==='0.00','Synthetic lifecycle must settle.');
    if(($i+1)%10===0)echo 'Prepared '.($i+1).' synthetic advances'.PHP_EOL;
}
cli_require(correction_integrity($p)===[],'Prepared workload integrity must pass.');
$counts=[];foreach(['cash_advances','cash_advance_operations','cash_advance_operation_reversals','journal_corrections','journal_entries','journal_entry_lines','journal_drafts','Receipts','posted_evidence_associations'] as $t)$counts[$t]=(int)$p->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();
$chain=correction_chain($p,$uid,$target);$f['perf_target_journal']=$target;$f['perf_chain_length']=count($chain);$f['perf_counts']=$counts;$f['perf_integrity_passed']=true;
file_put_contents($dir.'/perf-fixture.json',json_encode($f,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Performance workload ready: '.json_encode($counts).PHP_EOL;
