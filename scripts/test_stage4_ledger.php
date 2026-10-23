<?php
/** Connected oracle and adversarial reads, only on a new guarded synthetic clone. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private synthetic fixture required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_s4g1[a-z0-9]+\z/',$db),'Fresh Stage4 clone required.');
$dir=dirname($path);cli_require(!file_exists($dir.'/stage4-started.json'),'Consumed fixture; use a new clone.');
$evidence=realpath($f['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$private.DIRECTORY_SEPARATOR),'Private synthetic evidence required.');
define('RECEIPT_UPLOAD_DIR',$evidence);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/cash_advance.php';require __DIR__.'/../includes/correction_posting.php';require __DIR__.'/../includes/general_ledger_query.php';
$p=cli_db($db);cli_require(stage3_schema($p)&&stage2_schema($p),'Complete source schema required.');
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid'")->fetchColumn();
$manager=(int)$p->query("SELECT UserID FROM Users WHERE Email='management@example.invalid'")->fetchColumn();
cli_require($uid>0&&$manager>0,'Synthetic role identities required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$s4party=(int)$p->query('SELECT id FROM parties WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();cli_require($s4party>0,'Synthetic party required.');
file_put_contents($dir.'/stage4-started.json','{}');$checks=[];
function s4check(bool $ok,string $label):void{global $checks;cli_require($ok,$label);$checks[]=$label;echo "PASS: $label\n";}
function s4reject(callable $op,int $status,string $label):void{try{$op();}catch(LedgerProblem $e){s4check($e->status===$status,$label);return;}throw new RuntimeException('Unexpected acceptance: '.$label);}
function s4r(array $r):array{return ['csrf_token'=>csrf_token()]+$r;}
function s4id(array $d):array{return ['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];}
function s4line(string $id,int $account,string $debit='',string $credit=''):array{return ['client_id'=>$id,'account_id'=>(string)$account,'fund_project_id'=>'','debit_amount'=>$debit,'credit_amount'=>$credit];}
function s4payload(string $date,array $lines,int $cash=0,string $amount=''):array{global $s4party;return ['entry_date'=>$date,'reference'=>'S4%_\\ <script>','description'=>'Synthetic ledger training','party_id'=>(string)$s4party,'default_project_id'=>'','cash_account_id'=>$cash?(string)$cash:'','cash_amount'=>$amount,'cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>$lines,'documents'=>[]];}
function s4ordinary(PDO $p,int $uid,string $book,array $v):array{
    $d=workspace_save($p,$uid,s4r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'source_book'=>$book,'payload'=>$v]));
    $r=workspace_review($p,$uid,s4r(s4id($d)));return workspace_post($p,$uid,s4r(s4id($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]));
}
function s4advance(PDO $p,int $uid,string $kind,array $v,?int $advance=null):array{return advance_save($p,$uid,s4r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'workflow_kind'=>$kind,'advance_id'=>$advance===null?'':(string)$advance,'payload'=>$v]));}
function s4advancepost(PDO $p,int $uid,array $d):array{$r=advance_review($p,$uid,s4r(s4id($d)));return advance_post($p,$uid,s4r(s4id($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]));}
function s4support(PDO $p,int $uid,array $d,bool $money):array{
    $im=imagecreatetruecolor(24,24);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$name=bin2hex(random_bytes(16)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);
    $q=$p->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES(?,?,'image/png',?,?,?,'Failed')");$q->execute(['uploads/receipts/'.$name,'Synthetic S4 proof.png',strlen($bytes),hash('sha256',$bytes),$uid]);$rid=(int)$p->lastInsertId();
    $q=$p->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Synthetic manual review')");$q->execute([$rid,$uid,bin2hex(random_bytes(32)),hash('sha256',$bytes),hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
    $d=workspace_attach($p,$uid,s4r(s4id($d)),$rid);$v=$d['payload'];$doc=&$v['documents'][count($v['documents'])-1];$doc['reviewed']=true;
    if($money){$doc['purpose']='amount';$doc['support_side']='debit';$doc['declared_amount']=$doc['accepted_amount']='8000.00';$doc['allocations']=[['client_id'=>'cost','amount'=>'8000.00']];}unset($doc);
    return advance_save($p,$uid,s4r(s4id($d)+['workflow_kind'=>$d['workflow_kind'],'advance_id'=>(string)$d['advance_id'],'payload'=>$v]));
}
function s4manifest(PDO $p):string{
    $out=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){cli_require((bool)preg_match('/\A[a-zA-Z0-9_]+\z/',$t),'Identifier');$rows=[];foreach($p->query('SELECT * FROM `'.$t.'`')->fetchAll(PDO::FETCH_NUM) as $r)$rows[]=hash('sha256',serialize($r));sort($rows);$out[$t]=hash('sha256',implode('|',$rows));}ksort($out);return hash('sha256',json_encode($out));
}
$accounts=[];
foreach(['bank'=>['Asset','Debit',1],'control'=>['Asset','Debit',0],'expense'=>['Expense','Debit',0],'income'=>['Income','Credit',0],'equity'=>['Equity','Credit',0],'empty'=>['Asset','Credit',0]] as $key=>$attrs){
    $q=$p->prepare('INSERT INTO Categories(Name,Account_Code,Account_Type,Normal_Balance,Is_Cash_Account,Is_Active) VALUES(?,?,?,?,?,1)');$q->execute(['S4 Synthetic '.ucfirst($key),'S4'.strtoupper($key),...$attrs]);$accounts[$key]=(int)$p->lastInsertId();
}
workspace_control($p,$uid,s4r(['account_id'=>(string)$accounts['control'],'operation'=>'add','reason'=>'Synthetic unused advance control']));
$month=(new DateTimeImmutable(substr(accounting_today(),0,7).'-01'))->modify('-1 month')->format('Y-m');$from=$month.'-01';$to=accounting_month_end((int)substr($month,0,4),(int)substr($month,5,2));$day=fn($d)=>$month.'-'.str_pad((string)$d,2,'0',STR_PAD_LEFT);
$prior=(new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
$opening=s4ordinary($p,$uid,'GJ',s4payload($prior,[s4line('bank',$accounts['bank'],'10000.00'),s4line('equity',$accounts['equity'],'','10000.00')]));
$payment=s4ordinary($p,$uid,'CDB',s4payload($day(2),[s4line('cost',$accounts['expense'],'1000.00')],$accounts['bank'],'1000.00'));
$receipt=s4ordinary($p,$uid,'CRB',s4payload($day(3),[s4line('income',$accounts['income'],'','5000.00')],$accounts['bank'],'5000.00'));
$s2=json_decode(file_get_contents($dir.'/stage2-fixture.json'),true,64,JSON_THROW_ON_ERROR);
$v=s4payload($day(4),[],$accounts['bank'],'10000.00');$v+=['control_account_id'=>(string)$accounts['control'],'due_date'=>$to,'approval_name'=>'','approval_date'=>'','approval_reference'=>'','line_notes'=>[]];$v['party_id']=(string)$s2['employee'];
$d=s4advance($p,$uid,'advance_release',$v);$release=s4advancepost($p,$uid,$d);$advance=(int)$release['advance_id'];
$v['entry_date']=$day(5);$v['cash_account_id']=$v['cash_amount']=$v['control_account_id']=$v['due_date']=$v['party_id']='';$v['lines']=[s4line('cost',$accounts['expense'],'8000.00')];
$d=s4advance($p,$uid,'advance_liquidation',$v,$advance);$d=s4support($p,$uid,$d,true);$liquidation=s4advancepost($p,$uid,$d);
$v['entry_date']=$day(6);$v['cash_account_id']=(string)$accounts['bank'];$v['cash_amount']='2000.00';$v['lines']=[];
$d=s4advance($p,$uid,'advance_return',$v,$advance);$d=s4support($p,$uid,$d,false);$d=advance_confirm($p,$uid,s4r(s4id($d)));$returned=s4advancepost($p,$uid,$d);
$target=correction_entry_target($p,(int)$payment['id']);$seed=correction_seed($target,'reverse_replace','CDB');$seed['entry_date']=$day(7);$seed['reason']='Synthetic corrected training amount';$seed['backdate_reason']='Synthetic prior-month oracle';$seed['replacement']['entry_date']=$day(7);$seed['replacement']['cash_amount']='900.00';$seed['replacement']['lines'][0]['debit_amount']='900.00';
$d=correction_save($p,$uid,s4r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'target_journal_id'=>(string)$payment['id'],'mode'=>'reverse_replace','source_book'=>'CDB','payload'=>$seed]));$review=correction_review($p,$uid,s4r(s4id($d)));$correction=correction_post($p,$uid,s4r(s4id($d)+['payload'=>$d['payload'],'review_token'=>$review['token']]));
$criteria=['account_id'=>(string)$accounts['bank'],'from'=>$from,'to'=>$to,'search'=>'','page'=>'1','page_size'=>'25'];
$read=ledger_read($p,$uid,$criteria);
s4check([$read['opening'],$read['period_debits'],$read['period_credits'],$read['ending']]===['10000.00','8000.00','11900.00','6100.00'],'Connected exact bank opening/debits/credits/ending oracle');
s4check(array_column($read['rows'],'running_balance')===['9000.00','14000.00','4000.00','6000.00','7000.00','6100.00'],'Chronological running balances include reversal and replacement');
foreach(['expense'=>'8900.00','control'=>'0.00','income'=>'-5000.00','equity'=>'-10000.00','empty'=>'0.00'] as $key=>$ending){$r=ledger_read($p,$uid,array_replace($criteria,['account_id'=>(string)$accounts[$key]]));s4check($r['ending']===$ending,'Actual signed '.$key.' balance');}
$tb=accounting_trial_balance($p,$to);$nets=array_column($tb['rows'],'net','CategoryID');foreach($accounts as $key=>$id)s4check(ledger_read($p,$uid,array_replace($criteria,['account_id'=>(string)$id]))['ending']===$nets[$id],'Ledger and same-cutoff TB agree for '.$key);
$detail=advance_detail($p,$uid,$advance,$to);s4check($detail['outstanding']==='0.00','Dedicated advance release/liquidation/return settles');
$before=s4manifest($p);
foreach(['%','_','\\','<script>'] as $literal){$r=ledger_read($p,$uid,array_replace($criteria,['search'=>$literal]));s4check($r['matching_count']>0&&$r['ending']==='6100.00','Literal search '.json_encode($literal).' preserves full ending');}
$missing=ledger_read($p,$uid,array_replace($criteria,['search'=>'no synthetic match 53e3']));s4check($missing['rows']===[]&&$missing['period_count']===6&&$missing['ending']==='6100.00','No search matches retains full summary');
$printed=ledger_read($p,$uid,array_replace($criteria,['search'=>'no synthetic match','page'=>'999']),true);s4check(count($printed['rows'])===6&&$printed['criteria']['search']==='','Full-period print ignores search/page explicitly');
s4check(ledger_read($p,$uid,[])['account']===null,'Neutral initial selection has no invented account');
foreach([['account_id'=>[]],['account_id'=>null],['account_id'=>'0'],['account_id'=>'1e3'],['from'=>'2026-02-30'],['from'=>$to,'to'=>$from],['search'=>str_repeat('a',1001)],['page'=>'0'],['page_size'=>'500'],['to'=>[]]] as $bad)s4reject(fn()=>ledger_read($p,$uid,array_replace($criteria,$bad)),400,'Malformed request '.json_encode($bad));
s4reject(fn()=>ledger_read($p,$uid,array_replace($criteria,['account_id'=>'4294967295'])),404,'Missing account rejects');
s4check(str_starts_with((string)ledger_return_url(['ledger_account_id'=>$criteria['account_id'],'ledger_from'=>$from,'ledger_to'=>$to,'return_url'=>'https://evil.invalid/']),'general_ledger.php?'),'Return context fixed to local whitelisted route');
s4check(ledger_return_url(['ledger_account_id'=>[]])===null,'Malformed optional return context omitted');
$_SESSION=['UserID'=>$manager,'Role'=>'Admin'];$managed=ledger_read($p,$manager,$criteria);s4check($managed['rows']===$read['rows']&&$_SESSION['Role']==='Management','DB Management role overrides forged session and gets same financial rows');
s4check(!preg_match('/"(?:receipt_id|attachments|proof|draft_id|reservation|download|file_path)"\s*:/i',json_encode($managed)),'Ledger whitelist contains no evidence/draft/proof metadata');
$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
$p->beginTransaction();try{$p->exec('UPDATE Users SET Is_Active=0 WHERE UserID='.$uid);s4reject(fn()=>ledger_read($p,$uid,$criteria),403,'Inactive database actor rejects');}finally{$p->rollBack();}
s4check(s4manifest($p)===$before,'All-table read-only and rolled-back denial checks preserve exact synthetic data/audit');
$p->beginTransaction();try{$p->exec('UPDATE Categories SET Is_Active=0 WHERE CategoryID='.$accounts['bank']);$r=ledger_read($p,$uid,$criteria);s4check((int)$r['account']['Is_Active']===0&&$r['ending']==='6100.00','Inactive account retains history');}finally{$p->rollBack();}
$p->beginTransaction();try{$q=$p->prepare("INSERT INTO journal_entries(entry_date,description,status) VALUES(?,'Synthetic corrupt opening','posted')");$q->execute([$prior]);$bad=(int)$p->lastInsertId();$q=$p->prepare('INSERT INTO journal_entry_lines(journal_entry_id,account_id,debit_amount,credit_amount) VALUES(?,?,1,0)');$q->execute([$bad,$accounts['bank']]);s4reject(fn()=>ledger_read($p,$uid,$criteria),503,'Invalid contributing opening journal fails closed');}finally{$p->rollBack();}
// Offset-neutral multiple selected-account lines prove ordering before pagination.
for($i=0;$i<12;$i++)s4ordinary($p,$uid,'GJ',s4payload($day(8),[s4line('one',$accounts['bank'],'100.00'),s4line('two',$accounts['bank'],'50.00'),s4line('three',$accounts['bank'],'','150.00')]));
$all=ledger_read($p,$uid,$criteria,true);$page2=ledger_read($p,$uid,array_replace($criteria,['page'=>'2']));
$financial=static function(array $rows):array{foreach($rows as &$row)unset($row['transaction_url']);unset($row);return $rows;};
s4check($financial($page2['rows'])===$financial(array_slice($all['rows'],25,25)),'Later page retains full-period precomputed row balances');
s4check(str_contains($page2['rows'][0]['transaction_url'],'ledger_page=2'),'Drilldown return context retains the applied later page');
$last=ledger_read($p,$uid,array_replace($criteria,['page'=>'999']));s4check($last['page_adjusted']&&$last['criteria']['page']==='2','Beyond-last page visibly canonicalizes');
$search=ledger_read($p,$uid,array_replace($criteria,['search'=>'Synthetic ledger training']));$mapped=array_column($all['rows'],'running_balance','line_id');foreach($search['rows'] as $row)s4check($row['running_balance']===$mapped[$row['line_id']],'Filtered row '.$row['line_id'].' retains original running balance');
$p->beginTransaction();try{$first=ledger_read($p,$uid,$criteria);$other=cli_db($db);s4ordinary($other,$uid,'GJ',s4payload($day(9),[s4line('bank',$accounts['bank'],'0.01'),s4line('equity',$accounts['equity'],'','0.01')]));$again=ledger_read($p,$uid,$criteria);unset($first['generated_at'],$again['generated_at']);s4check($first===$again,'Actor/account/financial reads reuse one outer consistent snapshot');}finally{$p->rollBack();}
s4check(ledger_read($p,$uid,$criteria)['ending']==='6100.01','Next request observes committed concurrent movement');
foreach(['crb'=>['debit_amount','7000.00'],'cdb'=>['credit_amount','11900.00']] as $book=>[$side,$amount]){
    $records=accounting_records($p,['context'=>$book,'from'=>$from,'to'=>$to,'account_id'=>(string)$accounts['bank'],'type'=>'']);$gross=0;
    foreach($records['rows'] as $row)if((int)$row['account_id']===$accounts['bank'])$gross=accounting_add($gross,accounting_cents($row[$side]));
    s4check($records['completeBook']&&accounting_decimal($gross)===$amount,'Retained '.strtoupper($book).' full-entry gross cash activity');
    s4check((bool)array_filter($records['rows'],fn($row)=>(int)$row['account_id']!==$accounts['bank']),'Retained '.strtoupper($book).' includes counterpart account lines');
    if($book==='cdb')s4check(isset($records['journals'][(int)$payment['id']],$records['journals'][(int)$correction['replacement_journal_id']]),'CDB retains corrected original and replacement');
}
$history=accounting_records($p,['context'=>'records','from'=>$from,'to'=>$to,'account_id'=>(string)$accounts['bank'],'type'=>'']);s4check(!$history['completeBook']&&!array_filter($history['rows'],fn($row)=>(int)$row['account_id']!==$accounts['bank']),'Journal History stays line-filtered, not replaced by General Ledger');
$p->beginTransaction();try{$p->exec("UPDATE Categories SET Normal_Balance='Credit' WHERE CategoryID=".$accounts['bank']);s4check(ledger_read($p,$uid,$criteria)['ending']==='6100.01','Actual Debit balance is independent of configured normal side');}finally{$p->rollBack();}
require_once __DIR__.'/../includes/report_snapshots.php';$tb=accounting_trial_balance($p,$to);$year=(int)substr($from,0,4);$monthNumber=(int)substr($from,5,2);
$snapshot=report_snapshot_submit($p,$uid,['csrf_token'=>csrf_token(),'report_year'=>(string)$year,'report_month'=>(string)$monthNumber,'submission_key'=>report_submission_token($year,$monthNumber,$tb['fingerprint']),'source_fingerprint'=>$tb['fingerprint']]);
s4check((int)$snapshot['id']>0,'Existing immutable Trial Balance snapshot workflow remains usable');
$out=['database'=>$db,'accounts'=>$accounts,'criteria'=>$criteria,'advance_id'=>$advance,'payment_journal'=>(int)$payment['id'],'correction'=>$correction,'snapshot_id'=>(int)$snapshot['id'],'base_bank_ending'=>'6100.00','after_snapshot_test_ending'=>'6100.01','checks'=>count($checks),'passed'=>true,'labels'=>$checks];
file_put_contents($dir.'/stage4-ledger-fixture.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Stage4 ledger checks: '.count($checks).PHP_EOL;
