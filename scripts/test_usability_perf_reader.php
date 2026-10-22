<?php
/** Exact projection and repeatable-read tests. Fresh synthetic clone only; no migrations. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:','baseline-reader:','baseline-stage2:','baseline-stage3:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');$baseline=realpath($o['baseline-reader']??'');
cli_require($path&&$baseline&&str_starts_with($path,$private.DIRECTORY_SEPARATOR)&&str_starts_with($baseline,$private.DIRECTORY_SEPARATOR),'Private fixture and frozen baseline required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_perfcheck[a-z0-9]+\z/',$db),'Fresh perfcheck clone required.');
$uploads=realpath($f['receipt_root']);cli_require($uploads&&str_starts_with($uploads,$private.DIRECTORY_SEPARATOR),'Private synthetic evidence required.');
define('RECEIPT_UPLOAD_DIR',$uploads);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/journal_corrections.php';
// Only the frozen reader function is evaluated; it uses the same unchanged readers/services.
$old=file_get_contents($baseline);$start=strpos($old,'function correction_posted_detail(');$end=strpos($old,'/** All posted designated lines',$start);
cli_require($start!==false&&$end!==false,'Expected baseline reader boundary required.');
eval(str_replace('function correction_posted_detail(','function perf_baseline_detail(',substr($old,$start,$end-$start)));
$p=cli_db($db);$writer=cli_db($db);$checks=[];
foreach(['stage2'=>['stage2_schema','stage2_enabled'],'stage3'=>['stage3_schema_state','stage3_schema']] as $name=>[$function,$next]){
    $bp=realpath($o['baseline-'.$name]??'');cli_require($bp&&str_starts_with($bp,$private.DIRECTORY_SEPARATOR),'Private frozen schema helper required.');
    $bytes=file_get_contents($bp);$from=strpos($bytes,'function '.$function.'(');$to=strpos($bytes,'function '.$next.'(',$from);
    cli_require($from!==false&&$to!==false,'Frozen schema function boundaries required.');
    eval(str_replace('function '.$function.'(','function perf_old_'.$function.'(',substr($bytes,$from,$to-$from)));
}
function pc(bool $ok,string $label):void{global $checks;cli_require($ok,$label);$checks[]=$label;echo 'PASS: '.$label.PHP_EOL;}
function rejected(callable $fn,int $status,string $label):void{try{$fn();throw new RuntimeException('Unexpected accepted request: '.$label);}catch(JournalProblem $e){pc($e->status===$status,$label);}}
function manifest(PDO $p):array{$out=[];foreach($p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $t)$out[$t]=hash('sha256',json_encode($p->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));return $out;}
class PerfBoundaryPDO extends PDO{
    public $between=null;public bool $guardInTransaction=false;
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        if(str_starts_with($query,'SELECT Role,Is_Active FROM Users'))$this->guardInTransaction=$this->inTransaction();
        if($this->between&&str_starts_with($query,'SELECT id FROM journal_entries WHERE')){$callback=$this->between;$this->between=null;$callback($this);}
        return parent::prepare($query,$options);
    }
}
$uids=$p->query("SELECT Email,UserID FROM Users WHERE Email IN ('admin@example.invalid','management@example.invalid')")->fetchAll(PDO::FETCH_KEY_PAIR);cli_require(count($uids)===2,'Both synthetic roles required.');
$original=manifest($p);$ids=[];
pc(stage3_schema_state($p)===perf_old_stage3_schema_state($p)&&stage2_schema($p)===perf_old_stage2_schema($p),'Full-schema validators agree exactly with frozen source');
foreach(['s3_corrections_no_delete','s2_operations_no_delete'] as $name){
    $definition=$p->query('SHOW CREATE TRIGGER `'.$name.'`')->fetch();$ddl=$definition['SQL Original Statement'];
    $p->exec('DROP TRIGGER `'.$name.'`');
    try{
        pc(stage3_schema_state($p)===perf_old_stage3_schema_state($p)&&stage2_schema($p)===perf_old_stage2_schema($p),'Missing '.$name.' has identical fail-closed schema result');
        pc(str_starts_with($name,'s3_')?!stage3_schema($p):!stage2_schema($p),'Missing '.$name.' is not accepted');
        if(str_starts_with($name,'s3_')){
            $p->exec('CREATE TRIGGER `'.$name.'` BEFORE DELETE ON journal_corrections FOR EACH ROW SET @perf_only_test=1');
            pc(stage3_schema_state($p)===perf_old_stage3_schema_state($p)&&!stage3_schema($p),'Incompatible immutable trigger body remains rejected identically');
            $p->exec('DROP TRIGGER `'.$name.'`');
        }
    }finally{
        if($p->query("SHOW TRIGGERS WHERE `Trigger`=".$p->quote($name))->fetch())$p->exec('DROP TRIGGER `'.$name.'`');
        $p->exec($ddl);
    }
    pc(stage3_schema_state($p)===perf_old_stage3_schema_state($p)&&stage3_schema($p)&&stage2_schema($p),'Restored '.$name.' is read afresh without stale schema cache');
}
foreach($p->query("SELECT c.*,o.operation_kind FROM journal_corrections c LEFT JOIN cash_advance_operations o ON o.journal_id=c.target_journal_id ORDER BY c.id") as $c){
    $key=($c['operation_kind']??'ordinary').'-'.$c['mode'];if(!isset($ids[$key]))$ids[$key]=(int)$c['target_journal_id'];
    if($c['parent_correction_id']!==null)$ids['multistep']=(int)$c['target_journal_id'];
}
cli_require(isset($ids['ordinary-reverse_replace'],$ids['ordinary-reverse_only'],$ids['release-reverse_replace'],$ids['liquidation-reverse_replace'],$ids['return-reverse_replace'],$ids['multistep']),'Expected ordinary, advance and multi-step chains required.');
$hashes=[];
foreach(['Admin'=>'admin@example.invalid','Management'=>'management@example.invalid'] as $role=>$email){
    $uid=(int)$uids[$email];$_SESSION=['UserID'=>$uid,'Role'=>$role];
    foreach($ids as $kind=>$jid){
        $before=perf_baseline_detail($p,$uid,$jid);$after=correction_posted_detail($p,$uid,$jid);
        pc($before===$after,$role.' full baseline/final projection equality: '.$kind);
        $hashes[$role][$kind]=hash('sha256',json_encode($after,JSON_THROW_ON_ERROR));
        $wanted=[];foreach($after['chain'] as $c)foreach(['target_journal_id','reversal_journal_id','replacement_journal_id'] as $k)if($c[$k]!==null)$wanted[$c[$k]]=true;
        pc(count($after['journals'])===count($wanted)&&!array_diff_key($after['journals'],$wanted),'Only the validated chain journal union is loaded: '.$role.' '.$kind);
        foreach($after['journals'] as $journal)pc($journal['debit_cents']===$journal['credit_cents']&&count($journal['lines'])>=2,'Complete balanced projection: '.$role.' '.$kind);
    }
    rejected(fn()=>correction_posted_detail($p,$uid,2147483647),404,$role.' missing posted journal rejected');
    $uncorrected=(int)$p->query("SELECT j.id FROM journal_entries j LEFT JOIN journal_corrections c ON j.id IN (c.target_journal_id,c.reversal_journal_id,c.replacement_journal_id) WHERE j.status='posted' AND c.id IS NULL LIMIT 1")->fetchColumn();
    rejected(fn()=>correction_posted_detail($p,$uid,$uncorrected),404,$role.' existing journal without correction rejected');
}
$uid=(int)$uids['admin@example.invalid'];$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$target=$ids['release-reverse_replace'];
$draft=(int)$p->query('SELECT draft_id FROM journal_corrections WHERE target_journal_id='.$target)->fetchColumn();
$draftOwner=(int)$p->query('SELECT owner_id FROM journal_drafts WHERE id='.$draft)->fetchColumn();$badOwner=(int)$uids['management@example.invalid'];
$_SESSION['UserID']=0;rejected(fn()=>correction_posted_detail($p,$uid,$target),401,'Session and requested actor must match');$_SESSION['UserID']=$uid;
$p->beginTransaction();try{$p->exec('UPDATE Users SET Is_Active=0 WHERE UserID='.$uid);rejected(fn()=>correction_posted_detail($p,$uid,$target),403,'Inactive database-backed actor rejected despite Admin session');pc($p->inTransaction(),'Nested reader does not commit caller transaction');}finally{$p->rollBack();}
$p->beginTransaction();try{$p->exec('UPDATE journal_drafts SET owner_id='.$badOwner.' WHERE id='.$draft);rejected(fn()=>correction_posted_detail($p,$uid,$target),409,'Corrupt correction dependency fails closed');pc($p->inTransaction(),'Failed nested read preserves caller transaction ownership');}finally{$p->rollBack();}
$reference=correction_posted_detail($p,$uid,$target);
$dsn='mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1').';dbname='.$db.';charset=utf8mb4';
$hook=new PerfBoundaryPDO($dsn,getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach(['actor','dependency'] as $race){
    $hook->between=function(PDO $reader)use($writer,$uid,$draft,$badOwner,$race){pc($reader->inTransaction(),'Concurrent '.$race.' change happens within comparison transaction');pc($reader->query('SELECT @@tx_isolation')->fetchColumn()==='REPEATABLE-READ','Comparison uses repeatable-read snapshot');$writer->exec($race==='actor'?'UPDATE Users SET Is_Active=0 WHERE UserID='.$uid:'UPDATE journal_drafts SET owner_id='.$badOwner.' WHERE id='.$draft);};
    try{
        pc(correction_posted_detail($hook,$uid,$target)===$reference,'Concurrent '.$race.' change cannot mix snapshots');
        pc($hook->guardInTransaction,'Authoritative active-role guard shares the comparison snapshot');
        rejected(fn()=>correction_posted_detail($hook,$uid,$target),$race==='actor'?403:409,'Next request observes '.$race.' change; no stale authority/dependency cache');
    }finally{$writer->exec($race==='actor'?'UPDATE Users SET Is_Active=1 WHERE UserID='.$uid:'UPDATE journal_drafts SET owner_id='.$draftOwner.' WHERE id='.$draft);}
    pc(correction_posted_detail($hook,$uid,$target)===$reference,'Restored '.$race.' state is read afresh');
}
pc(manifest($p)===$original,'All synthetic table fingerprints restored after bounded negative/concurrency tests');
file_put_contents(dirname($path).'/perf-reader-results.json',json_encode(['passed'=>true,'checks'=>count($checks),'labels'=>$checks,'projection_hashes'=>$hashes,'table_fingerprints_unchanged'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Performance reader checks: '.count($checks).PHP_EOL;
