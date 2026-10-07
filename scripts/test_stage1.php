<?php
/** Creates ONLY a new explicit disposable database. Never reads live config or uploads. */
require_once __DIR__.'/cli_common.php';
define('ATIKHA_ISOLATED_TEST',true);
$opts=getopt('',['database:','worker:']);$db=$opts['database']??'';
cli_require(is_string($db)&&(bool)preg_match('/\Aatikha_test_stage1_[a-z0-9]+\z/',$db),'A new atikha_test_stage1_* database is required.');
$private=__DIR__.'/../.migration-private';
if(isset($opts['worker'])){
    $path=realpath($opts['worker']);$base=realpath($private);cli_require($path&&$base&&str_starts_with($path,$base.DIRECTORY_SEPARATOR),'Private worker fixture required.');
    $f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$receiptRoot=realpath($f['receipt_root']);cli_require($receiptRoot&&str_starts_with($receiptRoot,$base.DIRECTORY_SEPARATOR),'Private evidence required.');
    define('RECEIPT_UPLOAD_DIR',$receiptRoot);define('STAGE1_WORKSPACE_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
    require_once __DIR__.'/../includes/accounting_workspace.php';$_SESSION=$f['session'];
    try{$result=workspace_post(cli_db($db),$_SESSION['UserID'],$f['post']);echo workspace_json(['ok'=>true,'result'=>$result]);}
    catch(Throwable $e){echo workspace_json(['ok'=>false,'error'=>$e->getMessage(),'status'=>$e instanceof JournalProblem?$e->status:500]);}exit;
}
$run=$private.'/stage1-core-'.bin2hex(random_bytes(5));mkdir($run.'/receipts',0755,true);
define('RECEIPT_UPLOAD_DIR',$run.'/receipts');define('STAGE1_WORKSPACE_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
copy(__DIR__.'/../uploads/receipts/.htaccess',RECEIPT_UPLOAD_DIR.'/.htaccess');
require_once __DIR__.'/../includes/accounting_workspace.php';require_once __DIR__.'/../includes/accounting_query.php';
$checks=0;
function st(bool $ok,string $label):void{global $checks;cli_require($ok,$label);$checks++;echo "PASS: $label\n";}
function counts(PDO $p):array{return array_map(fn($t)=>(int)$p->query("SELECT COUNT(*) FROM $t")->fetchColumn(),['journal_entries','journal_entry_lines','audit_logs']);}
function no(PDO $p,callable $op,string $label,?int $status=null):void{$before=counts($p);try{$op();}catch(Throwable $e){st($status===null||($e instanceof JournalProblem&&$e->status===$status),$label.' rejected');st(counts($p)===$before&&!$p->inTransaction(),$label.' leaves no financial or audit partial state');return;}throw new RuntimeException('Unexpected acceptance: '.$label);}
function request(array $more=[]):array{return ['csrf_token'=>csrf_token()]+$more;}
function payload(array $lines,int $party=0,int $cash=0,string $amount=''):array{return ['entry_date'=>journal_today(),'reference'=>'TEST','description'=>'Synthetic NGO training activity','party_id'=>$party?(string)$party:'','default_project_id'=>'','cash_account_id'=>$cash?(string)$cash:'','cash_amount'=>$amount,'cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>$lines,'documents'=>[]];}
function line(string $id,int $account,string $debit='',string $credit='',int $project=0):array{return ['client_id'=>$id,'account_id'=>(string)$account,'debit_amount'=>$debit,'credit_amount'=>$credit,'fund_project_id'=>$project?(string)$project:''];}
function save(PDO $p,int $uid,string $book,array $payload):array{return workspace_save($p,$uid,request(['source_book'=>$book,'draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'payload'=>$payload]));}
function identity(array $d):array{return ['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];}
function post_request(PDO $p,int $uid,array $d):array{$review=workspace_review($p,$uid,request(identity($d)));return request(identity($d)+['payload'=>$d['payload'],'review_token'=>$review['token']]);}
function stage_image(PDO $p,int $uid):int{$im=imagecreatetruecolor(64,64);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$name=bin2hex(random_bytes(12)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);
    $s=$p->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES(?,?,'image/png',?,?,?,'Failed')");$s->execute(['uploads/receipts/'.$name,'Fixture <script>.png',strlen($bytes),hash('sha256',$bytes),$uid]);$id=(int)$p->lastInsertId();
    $s=$p->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Fixture manual')");$s->execute([$id,$uid,bin2hex(random_bytes(32)),hash('sha256',$bytes),hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);return $id;}
try{
    $server=new PDO('mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1').';charset=utf8mb4',getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$p=cli_db($db);cli_sql_file($p,__DIR__.'/../database.sql');
    foreach(glob(__DIR__.'/../migrations/*.sql') as $path){if(preg_match('/\A(\d{3})_/',basename($path),$m)&&(int)$m[1]<=18){cli_sql_file($p,$path);}}
    foreach(['admin'=>'Admin','other'=>'Admin','management'=>'Management'] as $name=>$role){$s=$p->prepare('INSERT INTO Users(FullName,Email,Role,Password,Is_Active) VALUES(?,?,?,?,1)');$s->execute(['Stage1 '.$name,$name.'@example.invalid',$role,'not-a-login-hash']);}
    $p->exec('INSERT INTO user_identities(UserID,FullName,Email,Role) SELECT UserID,FullName,Email,Role FROM Users');$users=$p->query('SELECT Email,UserID FROM Users')->fetchAll(PDO::FETCH_KEY_PAIR);$uid=(int)$users['admin@example.invalid'];$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
    $bank=account_save($p,$uid,'create',['name'=>'Stage1 Bank','account_type'=>'Asset','account_code'=>'S100','normal_balance'=>'Debit','is_cash_account'=>'1']);
    $petty=account_save($p,$uid,'create',['name'=>'Stage1 Petty Cash','account_type'=>'Asset','account_code'=>'S110','normal_balance'=>'Debit','is_cash_account'=>'1']);
    $training=account_save($p,$uid,'create',['name'=>'Stage1 Training Expense','account_type'=>'Expense','account_code'=>'S500','normal_balance'=>'Debit','is_cash_account'=>'0']);
    $transport=account_save($p,$uid,'create',['name'=>'Stage1 Transportation','account_type'=>'Expense','account_code'=>'S510','normal_balance'=>'Debit','is_cash_account'=>'0']);
    $tax=account_save($p,$uid,'create',['name'=>'Stage1 Withholding Payable','account_type'=>'Liability','account_code'=>'S200','normal_balance'=>'Credit','is_cash_account'=>'0']);
    $income=account_save($p,$uid,'create',['name'=>'Stage1 Donations','account_type'=>'Income','account_code'=>'S400','normal_balance'=>'Credit','is_cash_account'=>'0']);
    $advance=account_save($p,$uid,'create',['name'=>'Stage1 Cash Advance Employees','account_type'=>'Asset','account_code'=>'S120','normal_balance'=>'Debit','is_cash_account'=>'0']);
    $legacy=request(['submission_key'=>journal_submission_key(),'entry_date'=>journal_today(),'description'=>'Legacy preserved','reference'=>'','form_complete'=>'1','line_count'=>'2','lines'=>[['account_id'=>(string)$bank,'debit_amount'=>'1.00'],['account_id'=>(string)$income,'credit_amount'=>'1.00']]]);
    $old=journal_post($p,$uid,$legacy);$stage1LegacyPost=$old;$stage1LegacyRequest=$legacy;$stage1LegacySession=$_SESSION;$before=counts($p);
    if(getenv('ATIKHA_TEST_INTEGRATED')!=='1')st(!stage1_enabled($p),'Workspace unavailable before 019');
    cli_sql_file($p,__DIR__.'/../migrations/019_stage1_foundation.sql');
    if(getenv('ATIKHA_TEST_INTEGRATED')==='1'){
        cli_sql_file($p,__DIR__.'/../migrations/020_stage2_advances.sql');cli_sql_file($p,__DIR__.'/../migrations/021_journal_corrections.sql');
        cli_require(stage3_schema($p)&&stage2_schema($p)&&stage1_schema($p),'Integrated schema must be complete before operational tests.');
        echo "PROFILE: integrated operational schema 019/020/021 ready; legacy row prepared before migration\n";
    }else st(stage1_enabled($p)&&counts($p)===$before,'019 preserves financial and audit history');
    st($p->query('SELECT source_book FROM journal_entries WHERE id='.$old['id'])->fetchColumn()===null,'Existing book remains unknown');st(journal_post($p,$uid,$legacy)['duplicate'],'Legacy submission hash remains compatible');
    $party=workspace_master_save($p,$uid,request(['kind'=>'parties','name'=>'Synthetic NGO training supplier','party_type'=>'organization','reference'=>'','description'=>'Dummy capstone fixture','is_active'=>'1']));
    $project=workspace_master_save($p,$uid,request(['kind'=>'projects','name'=>'Synthetic community training','code'=>'DEMO-A','description'=>'Dummy capstone fixture','is_active'=>'1']));
    $empty=save($p,$uid,'CDB',payload([],0,0));st(array_slice(counts($p),0,2)===array_slice($before,0,2),'Incomplete draft has no financial effect');
    $receipt=save($p,$uid,'CRB',payload([line('donation',$income,'','10000.00',(int)$project['id'])],(int)$party['id'],$bank,'10000.00'));
    $review=workspace_review($p,$uid,request(identity($receipt)));st($review['coverage']['status']==='No monetary support','Ordinary receipt without evidence is truthfully labelled');
    $r=post_request($p,$uid,$receipt);$result=workspace_post($p,$uid,$r);st(!$result['duplicate'],'Donation 10000 posts to CRB');$c=counts($p);
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$r['csrf_token']=csrf_token();$r['review_token']='1000000000.'.str_repeat('0',64);
    st(workspace_post($p,$uid,$r)['duplicate']&&counts($p)===$c,'Lost response retry after logout and expired review recovers original');
    $bad=$r;$bad['payload']['description']='Changed';no($p,fn()=>workspace_post($p,$uid,$bad),'Changed posted payload',409);
    $bad=$r;$bad['csrf_token']='invalid';no($p,fn()=>workspace_post($p,$uid,$bad),'Invalid CSRF',400);
    $payment=save($p,$uid,'CDB',payload([line('training',$training,'5000.00','',(int)$project['id']),line('transport',$transport,'3000.00')],(int)$party['id'],$bank,'8000.00'));
    $doc=stage_image($p,$uid);$payment=workspace_attach($p,$uid,request(identity($payment)),$doc);
    no($p,fn()=>workspace_review($p,$uid,request(identity($payment))),'Unreviewed attachment',422);
    no($p,fn()=>receipt_review($p,$doc,$uid),'Old receipt review bypass of reservation',409);
    no($p,fn()=>receipt_lock_for_post($p,$uid,['receipt_id'=>$doc],''),'Old receipt posting bypass of reservation',409);
    $docReview=['receipt_id'=>(string)$doc,'purpose'=>'amount','reviewed'=>true,'support_side'=>'debit','declared_amount'=>'6000.00','accepted_amount'=>'6000.00','exclusion_reason'=>'','allocations'=>[['client_id'=>'training','amount'=>'5000.00'],['client_id'=>'transport','amount'=>'1000.00']]];
    $pp=$payment['payload'];$pp['documents']=[$docReview];$payment=workspace_save($p,$uid,request(identity($payment)+['source_book'=>'CDB','payload'=>$pp]));
    $v=workspace_review($p,$uid,request(identity($payment)));st($v['coverage']['status']==='Partially covered'&&$v['coverage']['debit']===['eligible'=>'8000.00','covered'=>'6000.00'],'6000 evidence of 8000 split payment is partial');
    $payResult=workspace_post($p,$uid,post_request($p,$uid,$payment));st((int)$p->query('SELECT COUNT(*) FROM evidence_allocations')->fetchColumn()===2,'Evidence allocations atomically persisted');
    $query=accounting_records($p,accounting_records_filters(['view'=>'cdb','from'=>'','to'=>'','account_id'=>(string)$training]));st(count($query['rows'])===3&&$query['completeBook'],'Cash-book account filter retains the full three-line entry');
    st($query['journals'][$payResult['id']]['evidence_coverage']['status']==='Partially covered','Posted history retains truthful partial coverage');
    $query=accounting_records($p,accounting_records_filters(['from'=>'','to'=>'','account_id'=>(string)$training]));st(count($query['rows'])===1,'Journal History retains line-filter behavior');
    $withholding=save($p,$uid,'CDB',payload([line('gross',$training,'4500.00'),line('tax',$tax,'','500.00')],(int)$party['id'],$bank,'4000.00'));
    $v=workspace_review($p,$uid,request(identity($withholding)));st($v['coverage']['debit']['eligible']==='4500.00','Withholding coverage denominator is gross 4500, not cash 4000');workspace_post($p,$uid,post_request($p,$uid,$withholding));
    $transferPayload=payload([line('cashin',$petty,'2500.00'),line('cashout',$bank,'','2500.00')]);$transferPayload['transaction_kind']='transfer';$transfer=save($p,$uid,'GJ',$transferPayload);$v=workspace_review($p,$uid,request(identity($transfer)));st($v['coverage']['status']==='Not applicable','Cash-only transfer has N/A monetary coverage');workspace_post($p,$uid,post_request($p,$uid,$transfer));
    $accrual=save($p,$uid,'GJ',payload([line('cost',$training,'3000.00'),line('payable',$tax,'','3000.00')]));$v=workspace_review($p,$uid,request(identity($accrual)));st($v['coverage']['debit']['eligible']==='3000.00'&&$v['coverage']['credit']['eligible']==='3000.00','General Journal coverage keeps sides separate');
    $pr=post_request($p,$uid,$accrual);$expired=$pr;$expired['review_token']='1000000000.'.str_repeat('0',64);no($p,fn()=>workspace_post($p,$uid,$expired),'Expired new-post review',409);
    workspace_control($p,$uid,request(['account_id'=>(string)$advance,'reason'=>'Future cash advances','operation'=>'add']));
    no($p,fn()=>account_save($p,$uid,'update',['account_id'=>$advance,'account_code'=>'S120','normal_balance'=>'Credit','is_cash_account'=>'0']),'Unused designated normal balance');
    no($p,fn()=>account_save($p,$uid,'update',['account_id'=>$advance,'account_code'=>'S120','normal_balance'=>'Debit','is_cash_account'=>'1']),'Unused designated cash classification');
    no($p,fn()=>account_save($p,$uid,'disable',['account_id'=>$advance]),'Unused designated disabling');
    $blocked=save($p,$uid,'GJ',payload([line('advance',$advance,'100.00'),line('bank',$bank,'','100.00')]));no($p,fn()=>workspace_review($p,$uid,request(identity($blocked))),'Ordinary designated posting',422);
    $legacyBlocked=$legacy;$legacyBlocked['csrf_token']=csrf_token();$legacyBlocked['submission_key']=journal_submission_key();$legacyBlocked['lines'][0]['account_id']=(string)$advance;no($p,fn()=>journal_post($p,$uid,$legacyBlocked),'Legacy designated posting',422);
    no($p,fn()=>workspace_post($p,$uid,$pr),'Master change invalidates review',409);
    $pr=post_request($p,$uid,$accrual);
    $p->exec("CREATE TRIGGER s1_test_audit_failure BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture audit failure'");
    no($p,fn()=>workspace_post($p,$uid,$pr),'Audit failure atomic rollback');$p->exec('DROP TRIGGER s1_test_audit_failure');
    $jobs=[];for($i=0;$i<2;$i++){$path=$run.'/worker'.$i.'.json';file_put_contents($path,workspace_json(['receipt_root'=>RECEIPT_UPLOAD_DIR,'session'=>$_SESSION,'post'=>$pr]));$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--database='.$db,'--worker='.$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$jobs[]=[$proc,$pipes];}
    $out=[];foreach($jobs as [$proc,$pipes]){$data=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cli_require(proc_close($proc)===0,$err);$out[]=json_decode($data,true);}
    st($out[0]['ok']&&$out[1]['ok']&&$out[0]['result']['id']===$out[1]['result']['id']&&$out[0]['result']['duplicate']!==$out[1]['result']['duplicate'],'Concurrent posting creates one journal and recovers duplicate');
    $pp=$empty['payload'];$pp['description']='Different window';$saved=workspace_save($p,$uid,request(identity($empty)+['source_book'=>'CDB','payload'=>$pp]));no($p,fn()=>workspace_save($p,$uid,request(identity($empty)+['source_book'=>'CDB','payload'=>$pp])),'Stale draft revision',409);
    no($p,fn()=>workspace_master_save($p,$uid,request(['kind'=>'projects','id'=>(string)$project['id'],'revision'=>'1','code'=>'CHANGED','name'=>'Changed','description'=>'','is_active'=>'1'])),'Posted project code change',422);
    $renamed=workspace_master_save($p,$uid,request(['kind'=>'parties','id'=>(string)$party['id'],'revision'=>'1','name'=>'Renamed supplier','party_type'=>'organization','reference'=>'','description'=>'','is_active'=>'1']));
    st(str_contains($p->query('SELECT party_snapshot FROM journal_entries WHERE id='.$result['id'])->fetchColumn(),'Synthetic NGO training supplier'),'Posted party label survives master rename');
    $_SESSION=['UserID'=>(int)$users['other@example.invalid'],'Role'=>'Admin'];no($p,fn()=>workspace_draft($p,(int)$_SESSION['UserID'],(int)$empty['id']),'Other owner lookup',404);
    $_SESSION=['UserID'=>(int)$users['management@example.invalid'],'Role'=>'Management'];no($p,fn()=>workspace_lists($p,(int)$_SESSION['UserID']),'Management workspace access',403);
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$bad=save($p,$uid,'CDB',payload([line('x',$training,'1e2')],(int)$party['id'],$bank,'100.00'));no($p,fn()=>workspace_review($p,$uid,request(identity($bad))),'Scientific notation',422);
    try{$p->exec('UPDATE posted_evidence_associations SET accepted_amount=1');throw new RuntimeException('Immutable evidence unexpectedly updated');}catch(PDOException $e){st(true,'Posted evidence immutable trigger');}
    // Multiple document coverage, reservation collisions and fresh reattachment review.
    $multi=save($p,$uid,'CDB',payload([line('cost',$training,'100.00')],(int)$party['id'],$bank,'100.00'));
    $doc1=stage_image($p,$uid);$doc2=stage_image($p,$uid);$multi=workspace_attach($p,$uid,request(identity($multi)),$doc1);$multi=workspace_attach($p,$uid,request(identity($multi)),$doc2);
    $otherDraft=save($p,$uid,'GJ',payload([]));no($p,fn()=>workspace_attach($p,$uid,request(identity($otherDraft)),$doc1),'Image reservation to a second draft',409);
    $multiPayload=$multi['payload'];foreach($multiPayload['documents'] as &$d){$d=['receipt_id'=>$d['receipt_id'],'reviewed'=>true,'purpose'=>'amount','support_side'=>'debit','declared_amount'=>'50.00','accepted_amount'=>'50.00','exclusion_reason'=>'','allocations'=>[['client_id'=>'cost','amount'=>'50.00']]];}unset($d);
    $multi=workspace_save($p,$uid,request(identity($multi)+['source_book'=>'CDB','payload'=>$multiPayload]));$v=workspace_review($p,$uid,request(identity($multi)));st($v['coverage']['status']==='Fully covered','Two separate images can fully support one payment');
    $over=$multi['payload'];$over['documents'][1]['declared_amount']=$over['documents'][1]['accepted_amount']=$over['documents'][1]['allocations'][0]['amount']='51.00';$tooMuch=workspace_save($p,$uid,request(identity($multi)+['source_book'=>'CDB','payload'=>$over]));no($p,fn()=>workspace_review($p,$uid,request(identity($tooMuch))),'Combined evidence exceeding a line',422);
    $multi=workspace_save($p,$uid,request(identity($tooMuch)+['source_book'=>'CDB','payload'=>$multiPayload]));$file=receipt_absolute_path($p->query('SELECT File_Path FROM Receipts WHERE ReceiptID='.$doc1)->fetchColumn());$bytes=file_get_contents($file);file_put_contents($file,$bytes.'changed');no($p,fn()=>workspace_review($p,$uid,request(identity($multi))),'Changed reserved file',409);file_put_contents($file,$bytes);
    $multi=workspace_remove_or_discard($p,$uid,request(identity($multi)+['receipt_id'=>(string)$doc1]),false);$multi=workspace_attach($p,$uid,request(identity($multi)),$doc1);st($multi['payload']['documents'][1]['reviewed']===false&&$multi['payload']['documents'][1]['allocations']===[],'Reattachment resets manual review and accepted allocations');
    $discard=workspace_remove_or_discard($p,$uid,request(identity($multi)),true);st($discard['state']==='Discarded'&&is_file($file)&&(int)$p->query('SELECT COUNT(*) FROM draft_evidence_reservations WHERE draft_id='.(int)$multi['id'])->fetchColumn()===0,'Draft discard soft-discards documents without physical deletion');
    $future=payload([line('x',$training,'1.00')],(int)$party['id'],$bank,'1.00');$future['entry_date']=(new DateTimeImmutable(journal_today()))->modify('+1 day')->format('Y-m-d');$fd=save($p,$uid,'CDB',$future);no($p,fn()=>workspace_review($p,$uid,request(identity($fd))),'Future cash payment',422);
    $doubleCash=save($p,$uid,'CDB',payload([line('petty',$petty,'1.00')],(int)$party['id'],$bank,'1.00'));no($p,fn()=>workspace_review($p,$uid,request(identity($doubleCash))),'Multiple cash lines in a cash book',422);
    $same=payload([line('in',$bank,'1.00'),line('out',$bank,'','1.00')]);$same['transaction_kind']='transfer';$sd=save($p,$uid,'GJ',$same);no($p,fn()=>workspace_review($p,$uid,request(identity($sd))),'Transfer to the same account',422);
    $maximum=[];for($i=0;$i<100;$i++){$maximum[]=line('max'.$i,$i%2?$tax:$training,$i%2?'':'9999999999999.99',$i%2?'9999999999999.99':'');}
    $max=save($p,$uid,'GJ',payload($maximum));$maxResult=workspace_post($p,$uid,post_request($p,$uid,$max));st((int)$p->query('SELECT COUNT(*) FROM journal_entry_lines WHERE journal_entry_id='.$maxResult['id'])->fetchColumn()===100,'New JSON workspace posts 100 maximum-value lines exactly');
    // The original single-image journal service remains compatible after 019.
    $legacyDoc=stage_image($p,$uid);$rv=receipt_review($p,$legacyDoc,$uid);$legacyNew=$legacy;$legacyNew['csrf_token']=csrf_token();$legacyNew['submission_key']=journal_submission_key();
    $legacyNew+=['receipt_id'=>(string)$legacyDoc,'receipt_attempt_id'=>(string)$rv['attempt']['id'],'receipt_hash'=>$rv['receipt']['File_SHA256'],'intake_signature'=>$rv['intake_signature'],'confirmed_currency'=>'PHP'];
    $legacyPosted=journal_post($p,$uid,$legacyNew);st($p->query('SELECT source_book FROM journal_entries WHERE id='.$legacyPosted['id'])->fetchColumn()==='GJ'&&$p->query('SELECT purpose FROM posted_evidence_associations WHERE journal_id='.$legacyPosted['id'])->fetchColumn()==='legacy','New legacy scalar posting records GJ and truthful legacy evidence');
    st(accounting_integrity($p,journal_today())===[],'All posted journals remain balanced');
    echo "Stage 1 checks: $checks\nPrivate evidence: $run\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
