<?php
/** Mutation tests ONLY on explicit atikha_test_phase4_* databases/private files. */
require_once __DIR__.'/cli_common.php';
// Worker JSON must never be polluted by production configuration or constants.
define('ATIKHA_ISOLATED_TEST',true);
$opts=getopt('',['database:','worker:']);$db=$opts['database']??'';
cli_require(is_string($db)&&(bool)preg_match('/\Aatikha_test_phase4_[a-z0-9]+\z/D',$db),'Explicit disposable atikha_test_phase4_* database required.');
$private=__DIR__.'/../.migration-private';
if(isset($opts['worker'])){
    $path=realpath($opts['worker']);$base=realpath($private);
    cli_require($path&&$base&&str_starts_with($path,$base.DIRECTORY_SEPARATOR),'Private worker fixture required.');
    $f=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $root=realpath($f['receipt_root']);cli_require($root&&str_starts_with($root,$base.DIRECTORY_SEPARATOR),'Private receipt root required.');
    define('RECEIPT_UPLOAD_DIR',$root);define('OCR_JOURNAL_ENABLED',true);
    require_once __DIR__.'/../includes/receipt_ocr.php';$_SESSION=$f['session'];
    try {
        if(($f['operation']??'post')==='discard'){receipt_discard(cli_db($db),$_SESSION['UserID'],$f['receipt_id'],$f['post']);$r=['discarded'=>true];}
        else{$r=journal_post(cli_db($db),$_SESSION['UserID'],$f['post']);}
        echo json_encode(['ok'=>true,'result'=>$r]);
    }catch(Throwable $e){echo json_encode(['ok'=>false,'status'=>$e instanceof JournalProblem?$e->status:500,'error'=>$e->getMessage()]);}
    exit;
}
$run=$private.'/phase4-core-'.bin2hex(random_bytes(5));mkdir($run,0755,true);
define('RECEIPT_UPLOAD_DIR',$run.'/receipts');mkdir(RECEIPT_UPLOAD_DIR);
copy(__DIR__.'/../uploads/receipts/.htaccess',RECEIPT_UPLOAD_DIR.'/.htaccess');
define('OCR_JOURNAL_ENABLED',true);
require_once __DIR__.'/../includes/receipt_ocr.php';
$checks=0;
function rt(bool $ok,string $label): void {global $checks;cli_require($ok,$label);$checks++;echo "PASS: $label\n";}
function rc(PDO $p): array{return array_map(fn($t)=>(int)$p->query("SELECT COUNT(*) FROM $t")->fetchColumn(),['journal_entries','journal_entry_lines','audit_logs']);}
function rr(PDO $p,callable $fn,string $label,?int $status=null): void {
    $before=rc($p);try{$fn();}catch(Throwable $e){rt($status===null||($e instanceof JournalProblem&&$e->status===$status),$label.' rejected');rt($before===rc($p)&&!$p->inTransaction(),$label.' leaves no financial/audit partial state');return;}throw new RuntimeException('Unexpected acceptance: '.$label);
}
function stage(PDO $p,int $uid,int $expense,?string $bytes=null,?array $data=null): int {
    if($bytes===null){$im=imagecreatetruecolor(64,64);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);}
    $name=bin2hex(random_bytes(10)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);$hash=hash('sha256',$bytes);
    $s=$p->prepare("INSERT INTO Receipts (File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES (?,?,'image/png',?,?,?,'Processed')");
    $s->execute(['uploads/receipts/'.$name,'Fixture <script>.png',strlen($bytes),$hash,$uid]);$id=(int)$p->lastInsertId();
    $data=$data??normalize_receipt_data(['merchant'=>'Fixture','document_type'=>'receipt','reference'=>'REF','transaction_date'=>'2026-10-01','date_text'=>'October 1 2026','currency'=>'PHP','total_amount'=>'123.45','suggested_debit_account_id'=>$expense,'confidence'=>.95],[['CategoryID'=>$expense]]);
    $s=$p->prepare("INSERT INTO receipt_ocr_attempts (receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,normalized_json) VALUES (?,?,?,'Processed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'fixture',?,?)");
    $s->execute([$id,$uid,bin2hex(random_bytes(32)),$hash,hash('sha256','fixture'),RECEIPT_SCHEMA_VERSION,json_encode($data,JSON_THROW_ON_ERROR)]);return $id;
}
function rp(PDO $p,int $id,int $uid,int $expense,int $asset): array {
    $v=receipt_review($p,$id,$uid);
    return ['csrf_token'=>csrf_token(),'submission_key'=>journal_submission_key(),'entry_date'=>'2026-10-01','reference'=>'REF','description'=>'Manually stated business purpose','line_count'=>'2','form_complete'=>'1',
        'receipt_id'=>(string)$id,'receipt_attempt_id'=>(string)$v['attempt']['id'],'receipt_hash'=>$v['receipt']['File_SHA256'],'intake_signature'=>$v['intake_signature'],'confirmed_currency'=>'PHP',
        'lines'=>[['account_id'=>(string)$expense,'debit_amount'=>'123.45','credit_amount'=>'','fund_project_id'=>''],['account_id'=>(string)$asset,'debit_amount'=>'','credit_amount'=>'123.45','fund_project_id'=>'']]];
}
function workers(string $db,string $run,array $jobs): array {
    $processes=[];foreach($jobs as $job){$path=$run.'/worker-'.bin2hex(random_bytes(4)).'.json';file_put_contents($path,json_encode($job+['receipt_root'=>RECEIPT_UPLOAD_DIR,'session'=>$_SESSION]));$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--database='.$db,'--worker='.$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes,$path];}
    $out=[];foreach($processes as [$proc,$pipes,$path]){$data=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);unlink($path);cli_require($code===0,'Worker failed: '.$error);$out[]=json_decode($data,true,512,JSON_THROW_ON_ERROR);}return $out;
}
try {
    $server=new PDO('mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1').';charset=utf8mb4',getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo=cli_db($db);
    cli_sql_file($pdo,__DIR__.'/../database.sql');
    foreach(glob(__DIR__.'/../migrations/*.sql') as $path){if(preg_match('/\A(\d{3})_/',basename($path),$m)&&(int)$m[1]<=17){cli_sql_file($pdo,$path);}}
    rt(!receipt_schema_available($pdo),'Evidence unavailable before migration 018');
    foreach(['admin'=>'Admin','other'=>'Admin','management'=>'Management'] as $name=>$role){$s=$pdo->prepare('INSERT INTO Users (FullName,Email,Role,Password,Is_Active) VALUES (?,?,?,?,1)');$s->execute(['Receipt '.$name,$name.'@receipt.invalid',$role,'not-a-login-hash']);}
    $pdo->exec('INSERT INTO user_identities (UserID,FullName,Email,Role) SELECT UserID,FullName,Email,Role FROM Users');
    $uids=$pdo->query('SELECT Email,UserID FROM Users')->fetchAll(PDO::FETCH_KEY_PAIR);$uid=(int)$uids['admin@receipt.invalid'];$other=(int)$uids['other@receipt.invalid'];$manager=(int)$uids['management@receipt.invalid'];
    $asset=(int)$pdo->query("SELECT CategoryID FROM Categories WHERE Account_Type='Income' LIMIT 1")->fetchColumn();
    $pdo->exec("INSERT INTO Categories (Name,Type,Account_Type,Normal_Balance,Is_Cash_Account,Is_Active) VALUES ('Phase4 Bank',NULL,'Asset','Debit',1,1)");$bank=(int)$pdo->lastInsertId();
    $expense=(int)$pdo->query("SELECT CategoryID FROM Categories WHERE Account_Type='Expense' LIMIT 1")->fetchColumn();
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];receipt_request_key();
    $manual=['csrf_token'=>csrf_token(),'submission_key'=>journal_submission_key(),'entry_date'=>'2026-10-01','reference'=>'','description'=>'Existing manual journal','line_count'=>'2','form_complete'=>'1','lines'=>[['account_id'=>(string)$bank,'debit_amount'=>'1.00'],['account_id'=>(string)$asset,'credit_amount'=>'1.00']]];
    $old=journal_post($pdo,$uid,$manual);$counts=rc($pdo);
    cli_sql_file($pdo,__DIR__.'/../migrations/018_journal_receipt_evidence.sql');
    rt(receipt_schema_available($pdo)&&rc($pdo)===$counts,'Migration 018 rehearsed, existing journals and audits preserved');
    rt(journal_post($pdo,$uid,$manual)['duplicate'],'Pre-018 manual submission hash still retries identically');
    $d=normalize_receipt_data(['total_amount'=>'9999999999999.99','transaction_date'=>'2026-02-30','currency'=>'USD','suggested_debit_account_id'=>4294967295,'confidence'=>4],[['CategoryID'=>$expense]]);
    rt($d['total_amount']==='9999999999999.99'&&$d['transaction_date']===null&&$d['suggested_debit_account_id']===null&&$d['confidence']===0.0&&count($d['warnings'])>0,'Exact maximum, invalid dates, nonexistent suggestions and confidence normalized safely');
    foreach(['1e2','1,234.50','-1.00','1.001','10000000000000.00',0.1] as $bad){rt(normalize_receipt_data(['total_amount'=>$bad],[])['total_amount']===null,'Unsupported OCR amount rejected: '.json_encode($bad));}
    $ambiguous=normalize_receipt_data(['date_text'=>'10/01/2026','transaction_date'=>'2026-10-01','currency'=>'PHP-invalid'],[]);
    rt($ambiguous['transaction_date']===null&&$ambiguous['currency']===null,'Ambiguous printed date and invalid currency cannot become plausible truncated values');
    $id=stage($pdo,$uid,$expense);$post=rp($pdo,$id,$uid,$expense,$bank);
    rt(rc($pdo)===$counts,'Staging and review create no financial records');
    $bad=$post;$bad['confirmed_currency']='USD';rr($pdo,fn()=>journal_post($pdo,$uid,$bad),'Foreign-currency confirmation',422);
    $bad=$post;$bad['intake_signature']=str_repeat('0',64);rr($pdo,fn()=>journal_post($pdo,$uid,$bad),'Forged intake signature',409);
    $bad=$post;$bad['csrf_token']='bad';rr($pdo,fn()=>journal_post($pdo,$uid,$bad),'Invalid CSRF',400);
    $bad=$post;$bad['description']='';rr($pdo,fn()=>journal_post($pdo,$uid,$bad),'Missing manual business purpose',422);
    $result=journal_post($pdo,$uid,$post);
    rt(!$result['duplicate']&&receipt_duplicate($pdo,$post['receipt_hash'])===$result['id'],'Explicit post atomically links receipt and unique evidence hash');
    $before=rc($pdo);rt(journal_post($pdo,$uid,$post)['duplicate']&&rc($pdo)===$before,'Identical OCR retry returns original journal without duplicate audit');
    $bad=$post;$bad['description']='Changed';rr($pdo,fn()=>journal_post($pdo,$uid,$bad),'Changed retry payload',409);
    $bytes=file_get_contents(receipt_absolute_path($pdo->query('SELECT File_Path FROM Receipts WHERE ReceiptID='.$id)->fetchColumn()));
    $id2=stage($pdo,$uid,$expense,$bytes);$post2=rp($pdo,$id2,$uid,$expense,$bank);
    rr($pdo,fn()=>journal_post($pdo,$uid,$post2),'Identical bytes under different receipt IDs',409);
    $id3=stage($pdo,$uid,$expense);$post3=rp($pdo,$id3,$uid,$expense,$bank);
    $pdo->exec("UPDATE Categories SET Is_Active=0 WHERE CategoryID=$expense");
    rr($pdo,fn()=>journal_post($pdo,$uid,$post3),'Inactive account at posting',422);$pdo->exec("UPDATE Categories SET Is_Active=1 WHERE CategoryID=$expense");
    $pdo->exec("CREATE TRIGGER phase4_fail_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    rr($pdo,fn()=>journal_post($pdo,$uid,$post3),'Audit failure');$pdo->exec('DROP TRIGGER phase4_fail_audit');
    rt($pdo->query("SELECT JournalEntryID FROM Receipts WHERE ReceiptID=$id3")->fetchColumn()===null,'Audit failure rolls back receipt association too');
    $path=receipt_absolute_path($pdo->query("SELECT File_Path FROM Receipts WHERE ReceiptID=$id3")->fetchColumn());$original=file_get_contents($path);file_put_contents($path,'changed');
    rr($pdo,fn()=>journal_post($pdo,$uid,$post3),'Modified evidence',409);file_put_contents($path,$original);
    $oldAttempt=(int)$post3['receipt_attempt_id'];
    rr($pdo,fn()=>$pdo->exec("UPDATE receipt_ocr_attempts SET error_message='rewrite' WHERE id=$oldAttempt"),'Completed attempt rewrite');
    rr($pdo,fn()=>$pdo->exec("DELETE FROM receipt_ocr_attempts WHERE id=$oldAttempt"),'Completed attempt deletion');
    $s=$pdo->prepare("INSERT INTO receipt_ocr_attempts (receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version) VALUES (?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'fixture',?)");
    $s->execute([$id3,$uid,bin2hex(random_bytes(32)),$post3['receipt_hash'],hash('sha256','new'),RECEIPT_SCHEMA_VERSION]);
    rr($pdo,fn()=>journal_post($pdo,$uid,$post3),'Newer extraction invalidates old proposal',409);
    $post3=rp($pdo,$id3,$uid,$expense,$bank);$post3['lines'][0]['debit_amount']='124.55';$post3['lines'][1]['credit_amount']='124.55';$post3['reference']='corrected';
    $edited=journal_post($pdo,$uid,$post3);
    $audit=json_decode($pdo->query("SELECT new_values FROM audit_logs WHERE module='General Journal' AND record_id=".$edited['id'])->fetchColumn(),true);
    rt(isset($audit['receipt_evidence']['corrections']['total_amount'])&&$audit['receipt_evidence']['final']['total_amount']==='124.55','Manual corrections retained alongside original proposal');
    require_once __DIR__.'/../includes/accounting_query.php';
    $records=accounting_records($pdo,accounting_records_filters(['from'=>'','to'=>'']));
    rt(count($records['journals'][$result['id']]['attachments'])===1&&count($records['journals'][$result['id']]['lines'])===2,'Evidence metadata does not multiply journal lines');
    $id4=stage($pdo,$uid,$expense);$post4=rp($pdo,$id4,$uid,$expense,$bank);
    $jobs=workers($db,$run,[['post'=>$post4],['post'=>$post4]]);
    rt($jobs[0]['ok']&&$jobs[1]['ok']&&$jobs[0]['result']['id']===$jobs[1]['result']['id'],'Concurrent same-key posting creates one journal');
    $id5=stage($pdo,$uid,$expense);$bytes=file_get_contents(receipt_absolute_path($pdo->query("SELECT File_Path FROM Receipts WHERE ReceiptID=$id5")->fetchColumn()));
    $id6=stage($pdo,$uid,$expense,$bytes);
    $jobs=workers($db,$run,[['post'=>rp($pdo,$id5,$uid,$expense,$bank)],['post'=>rp($pdo,$id6,$uid,$expense,$bank)]]);
    rt(count(array_filter($jobs,fn($j)=>$j['ok']))===1&&count(array_filter($jobs,fn($j)=>!$j['ok']&&$j['status']===409))===1,'Concurrent identical bytes produce one committed posting');
    $id9=stage($pdo,$uid,$expense);$p9=rp($pdo,$id9,$uid,$expense,$bank);$ownerSession=$_SESSION;
    $bytes=file_get_contents(receipt_absolute_path($pdo->query("SELECT File_Path FROM Receipts WHERE ReceiptID=$id9")->fetchColumn()));
    $id10=stage($pdo,$other,$expense,$bytes);$_SESSION=['UserID'=>$other,'Role'=>'Admin'];receipt_request_key();$p10=rp($pdo,$id10,$other,$expense,$bank);$otherSession=$_SESSION;
    $jobs=workers($db,$run,[['post'=>$p9,'session'=>$ownerSession],['post'=>$p10,'session'=>$otherSession]]);
    rt(count(array_filter($jobs,fn($j)=>$j['ok']))===1&&count(array_filter($jobs,fn($j)=>!$j['ok']&&$j['status']===409))===1,'Independent Admin locks still enforce global unique evidence reservation');
    $_SESSION=$ownerSession;
    $id7=stage($pdo,$uid,$expense);$post7=rp($pdo,$id7,$uid,$expense,$bank);
    $discard=['csrf_token'=>csrf_token(),'request_key'=>receipt_request_key()];
    $jobs=workers($db,$run,[['post'=>$post7],['operation'=>'discard','receipt_id'=>$id7,'post'=>$discard]]);
    $row=$pdo->query("SELECT * FROM Receipts WHERE ReceiptID=$id7")->fetch();
    rt(($row['JournalEntryID']!==null&&$row['OCR_Status']!=='Discarded'&&is_file(receipt_absolute_path($row['File_Path'])))||($row['JournalEntryID']===null&&$row['OCR_Status']==='Discarded'&&!is_file(receipt_absolute_path($row['File_Path']))),'Discard/post race preserves committed evidence or rejects posting');
    $id8=stage($pdo,$uid,$expense);$post8=rp($pdo,$id8,$uid,$expense,$bank);$_SESSION=['UserID'=>$other,'Role'=>'Admin'];$post8['csrf_token']=csrf_token();$post8['submission_key']=journal_submission_key();receipt_request_key();
    rr($pdo,fn()=>journal_post($pdo,$other,$post8),'Different Admin cannot post unowned receipt',404);
    $_SESSION=['UserID'=>$manager,'Role'=>'Management'];$post8['csrf_token']=csrf_token();$post8['submission_key']=journal_submission_key();receipt_request_key();
    rr($pdo,fn()=>journal_post($pdo,$manager,$post8),'Management cannot post',403);
    rt((int)$pdo->query('SELECT COUNT(*) FROM Expenses')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM Incoming_Funds')->fetchColumn()===0,'Legacy transaction tables remain empty');
    echo "Completed $checks checks. Disposable database retained: $db\nPrivate evidence: $run\n";
}catch(Throwable $e){fwrite(STDERR,"FAIL: ".$e->getMessage()."\nDisposable database retained: $db\n");exit(1);}
