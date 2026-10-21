<?php
/** Focused advance gates on an already-complete NEW private synthetic clone. No bootstrap. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:','gate:']);$gate=$o['gate']??'b';cli_require(in_array($gate,['b','c','d','all'],true),'Choose b/c/d/all.');
$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');cli_require($private&&$path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private fixture required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];cli_require((bool)preg_match('/\Aatikha_test_stage1_u3bcd[a-z0-9]+\z/',$db),'Guarded U3-BCD synthetic clone required.');
$evidence=realpath($f['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$private.DIRECTORY_SEPARATOR),'Private evidence required.');
define('RECEIPT_UPLOAD_DIR',$evidence);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/cash_advance.php';$p=cli_db($db);cli_require(stage2_enabled($p)&&stage3_schema($p),'Complete validated schema required; no migrations are run.');
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic owner required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$checks=['b'=>0,'c'=>0,'d'=>0];$current='b';$casePath=dirname($path).'/u3-bcd-cases.json';
function bcd_check(bool $ok,string $label):void{global $checks,$current;if(!$ok)throw new RuntimeException($label);$checks[$current]++;echo 'PASS '.$current.': '.$label."\n";}
function bcd_r(array $r):array{return ['csrf_token'=>csrf_token()]+$r;}
function bcd_identity(array $d):array{return ['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];}
function bcd_state(PDO $p):array{$out=[];foreach($p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $t)$out[$t]=hash('sha256',workspace_json($p->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll()));return $out;}
function bcd_payload(array $c,string $amount='10000.00'):array{return ['entry_date'=>journal_today(),'reference'=>'U3BCD-'.bin2hex(random_bytes(4)),'description'=>'Synthetic outreach travel and training advance','party_id'=>(string)$c['employee'],'default_project_id'=>(string)$c['project'],'cash_account_id'=>(string)$c['bank'],'cash_amount'=>$amount,'cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>[],'documents'=>[],'control_account_id'=>(string)$c['control'],'due_date'=>journal_today(),'approval_name'=>'','approval_date'=>'','approval_reference'=>'','line_notes'=>[]];}
function bcd_save(PDO $p,int $uid,string $kind,array $payload,?int $advance=null,?array $draft=null):array{return advance_save($p,$uid,bcd_r(($draft?bcd_identity($draft):['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32))])+['workflow_kind'=>$kind,'advance_id'=>$advance===null?'':(string)$advance,'payload'=>$payload]));}
function bcd_review(PDO $p,int $uid,array $d):array{
    $before=bcd_state($p);$review=advance_review($p,$uid,bcd_r(bcd_identity($d)));bcd_check(bcd_state($p)===$before,'Review changes none of all application tables');
    $prepared=workspace_tx($p,$uid,function($version)use($p,$uid,$d){$d=workspace_draft($p,$uid,(int)$d['id']);$original=$d['workflow_kind']==='advance_release'?null:advance_load($p,(int)$d['advance_id'],true);$input=advance_canonical($p,$d,$d['payload'],$original);return advance_prepare($p,$uid,$d,$input,$version,$original);});
    $t=$prepared['timeline'];bcd_check($review['advance_effect']===['accounting_date'=>$t['accounting_date'],'balance_as_of'=>journal_today(),'before_outstanding'=>$t['before_display'],'after_outstanding'=>$t['after_display'],'new_advance_outstanding'=>$t['replacement_display']],'Public effect exactly whitelists the actual validated prepared timeline');
    bcd_check($review['input']===$prepared['input']&&$review['outstanding']===($prepared['remaining']===null?null:ledger_decimal($prepared['remaining'])),'Existing canonical input/outstanding remain unchanged');
    bcd_check(workspace_draft($p,$uid,(int)$d['id'])['payload']===$d['payload'],'Projection does not mutate saved payload');return $review;
}
function bcd_post(PDO $p,int $uid,array $d,array $review):array{return advance_post($p,$uid,bcd_r(bcd_identity($d)+['payload'=>$d['payload'],'review_token'=>$review['token']]));}
function bcd_replay(PDO $p,int $uid,array $d,array $posted):void{
    $before=bcd_state($p);$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$r=bcd_r(bcd_identity($d)+['payload'=>$d['payload'],'review_token'=>'1000000000.'.str_repeat('0',64)]);$out=advance_post($p,$uid,$r);
    bcd_check($out['duplicate']&&$out['id']===$posted['id']&&$out['advance_id']===$posted['advance_id']&&bcd_state($p)===$before,'Expired-review same-request replay recovers identities with no write');
    $r['payload']['description'].=' changed';try{advance_post($p,$uid,$r);throw new RuntimeException('Changed replay was accepted.');}catch(JournalProblem $e){bcd_check($e->status===409&&bcd_state($p)===$before,'Changed successful replay conflicts without any table write');}
}
function bcd_reject(PDO $p,callable $fn,string $label):void{$before=bcd_state($p);try{$fn();throw new RuntimeException('Unexpected acceptance: '.$label);}catch(JournalProblem $e){bcd_check(bcd_state($p)===$before,$label.' preserves all tables');}}
function bcd_support(PDO $p,int $uid,array $d,?string $amount=null,array $allocations=[]):array{
    $im=imagecreatetruecolor(64,64);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$name=bin2hex(random_bytes(12)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);
    $s=$p->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES(?,?,'image/png',?,?,?,'Failed')");$s->execute(['uploads/receipts/'.$name,'Synthetic U3 evidence.png',strlen($bytes),hash('sha256',$bytes),$uid]);$id=(int)$p->lastInsertId();
    $s=$p->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Synthetic manual review')");$s->execute([$id,$uid,bin2hex(random_bytes(32)),hash('sha256',$bytes),hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
    $d=workspace_attach($p,$uid,bcd_r(bcd_identity($d)),$id);$payload=$d['payload'];$doc=&$payload['documents'][count($payload['documents'])-1];$doc['reviewed']=true;if($amount!==null){$doc['purpose']='amount';$doc['support_side']='debit';$doc['declared_amount']=$doc['accepted_amount']=$amount;$doc['allocations']=$allocations;}unset($doc);
    return bcd_save($p,$uid,$d['workflow_kind'],$payload,(int)$d['advance_id'],$d);
}
function bcd_line(string $id,int $account,string $debit='',string $credit='',?int $project=null):array{return ['client_id'=>$id,'account_id'=>(string)$account,'fund_project_id'=>$project===null?'':(string)$project,'debit_amount'=>$debit,'credit_amount'=>$credit];}
function bcd_liquidation(array $c,array $lines):array{$v=bcd_payload($c);$v['cash_account_id']=$v['cash_amount']=$v['control_account_id']=$v['due_date']=$v['party_id']='';$v['lines']=$lines;return $v;}
try{
    if($gate==='b'||$gate==='all'){
        cli_require(!file_exists($casePath)&&(int)$p->query("SELECT COUNT(*) FROM journal_entries WHERE reference LIKE 'U3BCD-%'")->fetchColumn()===0,'Fresh unconsumed fixture required.');
        $party=workspace_master_save($p,$uid,bcd_r(['kind'=>'parties','name'=>'Synthetic U3 accountable employee with a long community outreach assignment','party_type'=>'person','is_active'=>'1']));
        $second=workspace_master_save($p,$uid,bcd_r(['kind'=>'parties','name'=>'Synthetic second U3 accountable employee','party_type'=>'person','is_active'=>'1']));
        $project=workspace_master_save($p,$uid,bcd_r(['kind'=>'projects','name'=>'Synthetic outreach <training & community> project with a long display label','code'=>'U3BCD-'.bin2hex(random_bytes(4)),'is_active'=>'1']));
        $source=json_decode(file_get_contents(dirname($path).'/stage2-fixture.json'),true,64,JSON_THROW_ON_ERROR);
        $c=['database'=>$db,'receipt_root'=>$evidence,'employee'=>$party['id'],'employee2'=>$second['id'],'project'=>$project['id'],'bank'=>$source['bank'],'petty'=>$source['petty'],'control'=>$source['control'],'training'=>$source['training'],'tax'=>$source['tax'],'equipment'=>$source['equipment']];
        $d=bcd_save($p,$uid,'advance_release',bcd_payload($c));$review=bcd_review($p,$uid,$d);
        bcd_check($review['advance_effect']['before_outstanding']===null&&$review['advance_effect']['after_outstanding']===null&&$review['advance_effect']['new_advance_outstanding']==='10000.00','New release has explicit null prior balances and authoritative proposed outstanding');
        bcd_check(count($review['lines'])===2&&$review['coverage']['status']==='Optional proof not supplied','Release generates the complete journal and permits no proof');
        $posted=bcd_post($p,$uid,$d,$review);$c['advance']=$posted['advance_id'];$c['release_journal']=$posted['id'];
        bcd_check(advance_detail($p,$uid,$c['advance'],journal_today())['outstanding']==='10000.00','Release creates asset outstanding');
        bcd_check($p->query('SELECT source_book FROM journal_entries WHERE id='.(int)$posted['id'])->fetchColumn()==='CDB','Release source book is CDB');
        $lines=$p->query('SELECT account_id,debit_amount,credit_amount FROM journal_entry_lines WHERE journal_entry_id='.(int)$posted['id'])->fetchAll();
        bcd_check(count(array_filter($lines,fn($l)=>(int)$l['account_id']===(int)$c['control']&&$l['debit_amount']==='10000.00'))===1,'Generated designated control debit creates an asset, not an expense');
        bcd_replay($p,$uid,$d,$posted);
        for($i=0;$i<26;$i++){$sd=bcd_save($p,$uid,'advance_release',bcd_payload($c,'100.00'));$sr=advance_review($p,$uid,bcd_r(bcd_identity($sd)));bcd_post($p,$uid,$sd,$sr);}
        $reg=advance_register($p,$uid,['party_id'=>(string)$c['employee']]);bcd_check($reg['count']===27&&count($reg['rows'])===25&&$reg['totals']['outstanding']==='12600.00','Employee advisory contract gives all-page count/totals independently of first25 rows');
        file_put_contents($casePath,workspace_json($c));
    }else{$c=json_decode(file_get_contents($casePath),true,64,JSON_THROW_ON_ERROR);cli_require($c['database']===$db,'Matching case identity required.');}
    if($gate==='c'||$gate==='all'){
        $current='c';cli_require(!isset($c['liquidation']),'Fresh liquidation gate required.');
        $payload=bcd_liquidation($c,[bcd_line('cost',$c['training'],'8000.00','',$c['project'])]);$d=bcd_save($p,$uid,'advance_liquidation',$payload,$c['advance']);
        bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($d))),'Unsupported liquidation');
        $d=bcd_support($p,$uid,$d,'8000.00',[['client_id'=>'cost','amount'=>'8000.00']]);$review=bcd_review($p,$uid,$d);
        bcd_check($review['advance_effect']['before_outstanding']==='10000.00'&&$review['advance_effect']['after_outstanding']==='2000.00','10000 release and 8000 supported liquidation projects 2000');
        $posted=bcd_post($p,$uid,$d,$review);bcd_replay($p,$uid,$d,$posted);$c['liquidation']=$posted['id'];bcd_check(advance_detail($p,$uid,$c['advance'],journal_today())['outstanding']==='2000.00','Actual partial liquidation matches authoritative projection');
        $release=bcd_payload($c,'4000.00');$release['party_id']=(string)$c['employee2'];$rd=bcd_save($p,$uid,'advance_release',$release);$rp=bcd_post($p,$uid,$rd,advance_review($p,$uid,bcd_r(bcd_identity($rd))));
        $gross=bcd_liquidation($c,[bcd_line('cost',$c['training'],'4500.00','',$c['project']),bcd_line('tax',$c['tax'],'','500.00')]);$gross['line_notes']=['tax'=>'Synthetic manually assessed withholding liability'];$gd=bcd_save($p,$uid,'advance_liquidation',$gross,$rp['advance_id']);$gd=bcd_support($p,$uid,$gd,'4000.00',[['client_id'=>'cost','amount'=>'4000.00']]);
        bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($gd))),'Net-only 4000 support cannot cover 4500 gross costs');
        $g=$gd['payload'];$g['documents'][0]['declared_amount']=$g['documents'][0]['accepted_amount']='4500.00';$g['documents'][0]['allocations'][0]['amount']='4500.00';$gd=bcd_save($p,$uid,'advance_liquidation',$g,$rp['advance_id'],$gd);$gr=bcd_review($p,$uid,$gd);
        bcd_check($gr['input']['advance_context']['gross_costs']==='4500.00'&&$gr['input']['advance_context']['liability_credits']==='500.00'&&$gr['input']['advance_context']['reduction']==='4000.00'&&$gr['coverage']['debit']===['eligible'=>'4500.00','covered'=>'4500.00'],'Gross evidence and net control reduction remain distinct');
        bcd_check($gr['coverage']['credit']===['eligible'=>'0.00','covered'=>'0.00']&&count($gr['lines'])===3,'Liability and generated control credit add no evidence denominator');
        $bad=$g;$bad['documents']=[];$bd=bcd_save($p,$uid,'advance_liquidation',$bad,$rp['advance_id']);$bd=bcd_support($p,$uid,$bd,'4500.00',[['client_id'=>'tax','amount'=>'4500.00']]);bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($bd))),'Evidence cannot be assigned to a liability');
        $gp=bcd_post($p,$uid,$gd,$gr);bcd_check(advance_detail($p,$uid,$rp['advance_id'],journal_today())['status']==='settled','Gross-liability liquidation settles exactly 4000 outstanding');
        $fresh=bcd_save($p,$uid,'advance_liquidation',bcd_liquidation($c,[bcd_line('cost',$c['training'],'1.00')]),$c['advance']);
        bcd_reject($p,fn()=>workspace_attach($p,$uid,bcd_r(bcd_identity($fresh)),(int)$g['documents'][0]['receipt_id']),'Posted image cannot be reused for an ordinary liquidation');
        $historical=bcd_payload($c,'500.00');$historical['party_id']=(string)$c['employee2'];$historical['entry_date']=$historical['due_date']=(new DateTimeImmutable(journal_today()))->modify('-2 days')->format('Y-m-d');$hd=bcd_save($p,$uid,'advance_release',$historical);$hp=bcd_post($p,$uid,$hd,advance_review($p,$uid,bcd_r(bcd_identity($hd))));
        $cost=bcd_liquidation($c,[bcd_line('asset',$c['equipment'],'300.00','',$c['project']),bcd_line('cost',$c['training'],'200.00')]);$cost['entry_date']=(new DateTimeImmutable(journal_today()))->modify('-1 day')->format('Y-m-d');$cd=bcd_save($p,$uid,'advance_liquidation',$cost,$hp['advance_id']);$cd=bcd_support($p,$uid,$cd,'500.00',[['client_id'=>'asset','amount'=>'300.00'],['client_id'=>'cost','amount'=>'200.00']]);$cr=bcd_review($p,$uid,$cd);
        bcd_check($cr['advance_effect']['accounting_date']===$cost['entry_date']&&$cr['advance_effect']['balance_as_of']===journal_today()&&$cr['advance_effect']['after_outstanding']==='0.00','Backdated multi-project asset/cost review retains explicit current-balance basis');bcd_post($p,$uid,$cd,$cr);
        file_put_contents($casePath,workspace_json($c));
    }
    if($gate==='d'||$gate==='all'){
        $current='d';cli_require(isset($c['liquidation'])&&!isset($c['return']),'Successful C and fresh D gate required.');
        $payload=bcd_payload($c,'2000.00');$payload['control_account_id']='';$d=bcd_save($p,$uid,'advance_return',$payload,$c['advance']);
        bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($d))),'Return without proof');$d=bcd_support($p,$uid,$d);bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($d))),'Reviewed proof without explicit confirmation');
        $d=advance_confirm($p,$uid,bcd_r(bcd_identity($d)));$confirmation=$d['return_confirmation'];$changed=$d['payload'];$changed['description'].=' revised wording';$d=bcd_save($p,$uid,'advance_return',$changed,$c['advance'],$d);bcd_check($d['return_confirmation']===$confirmation,'Unrelated wording preserves server proof context');
        foreach(['cash_amount'=>'1999.00','cash_account_id'=>(string)$c['petty'],'entry_date'=>(new DateTimeImmutable(journal_today()))->modify('-1 day')->format('Y-m-d'),'document_review'=>false] as $field=>$value){
            $original=$d['payload'];$changed=$original;if($field==='document_review')$changed['documents'][0]['reviewed']=false;else $changed[$field]=$value;
            $d=bcd_save($p,$uid,'advance_return',$changed,$c['advance'],$d);bcd_check($d['return_confirmation']===null,'Bound '.$field.' change clears server confirmation');
            $d=bcd_save($p,$uid,'advance_return',$original,$c['advance'],$d);$d=advance_confirm($p,$uid,bcd_r(bcd_identity($d)));
        }
        $review=bcd_review($p,$uid,$d);bcd_check($review['coverage']['status']==='Return proof confirmed'&&$review['coverage']['debit']===['eligible'=>'0.00','covered'=>'0.00'],'Return proof has no expenditure evidence denominator');
        bcd_check($review['advance_effect']['before_outstanding']==='2000.00'&&$review['advance_effect']['after_outstanding']==='0.00','10000 release /8000 liquidation /2000 return projects zero');$posted=bcd_post($p,$uid,$d,$review);bcd_replay($p,$uid,$d,$posted);$c['return']=$posted['id'];
        bcd_check(advance_detail($p,$uid,$c['advance'],journal_today())['status']==='settled','Full unused-cash return settles original advance');
        $fresh=bcd_save($p,$uid,'advance_return',$payload,$c['advance']);$fresh=bcd_support($p,$uid,$fresh);$fresh=advance_confirm($p,$uid,bcd_r(bcd_identity($fresh)));bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($fresh))),'Further return against settled advance');
        $release=bcd_payload($c,'500.00');$release['party_id']=(string)$c['employee2'];$rd=bcd_save($p,$uid,'advance_release',$release);$rp=bcd_post($p,$uid,$rd,advance_review($p,$uid,bcd_r(bcd_identity($rd))));
        foreach(['100.00','150.00'] as $amount){$v=bcd_payload($c,$amount);$v['party_id']=$v['control_account_id']='';$ret=bcd_save($p,$uid,'advance_return',$v,$rp['advance_id']);$ret=bcd_support($p,$uid,$ret);$ret=advance_confirm($p,$uid,bcd_r(bcd_identity($ret)));$rv=bcd_review($p,$uid,$ret);bcd_post($p,$uid,$ret,$rv);}
        bcd_check(advance_detail($p,$uid,$rp['advance_id'],journal_today())['outstanding']==='250.00','Multiple partial returns before liquidation preserve remaining250');
        file_put_contents($casePath,workspace_json($c));
    }
    echo 'Gate results: '.workspace_json($checks)."\nCases: $casePath\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
