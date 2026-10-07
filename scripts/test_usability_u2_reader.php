<?php
/** Focused posted-reader checks. Guarded private disposable fixture only. */
define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/cli_common.php';
$options=getopt('',['fixture:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($options['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private fixture required.');$fixture=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$fixture['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_[a-z0-9]+\z/',$db),'Guarded disposable database required.');
$evidence=realpath($fixture['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$private.DIRECTORY_SEPARATOR),'Private evidence required.');
foreach(['STAGE1_WORKSPACE_ENABLED','STAGE2_ADVANCES_ENABLED','STAGE3_CORRECTIONS_ENABLED'] as $flag)define($flag,true);define('RECEIPT_UPLOAD_DIR',$evidence);define('OCR_JOURNAL_ENABLED',false);
require_once __DIR__.'/../includes/accounting_workspace.php';require_once __DIR__.'/../includes/accounting_query.php';
$pdo=cli_db($db);cli_require(stage3_schema($pdo),'Complete integrated schema required.');$checks=0;
function u2check(bool $ok,string $label):void{global $checks;cli_require($ok,$label);$checks++;echo 'PASS: '.$label."\n";}
function u2reject(callable $op,int $status,string $label):void{try{$op();throw new RuntimeException('Expected rejection: '.$label);}catch(JournalProblem $e){u2check($e->status===$status,$label);}}
function u2manifest(PDO $p):array{$out=[];foreach(['journal_entries','journal_entry_lines','journal_drafts','Receipts','posted_evidence_associations','evidence_allocations','journal_corrections','audit_logs','Users'] as $t)$out[$t]=hash('sha256',json_encode($p->query('SELECT * FROM '.$t.' ORDER BY 1')->fetchAll()));return $out;}
$admin=(int)$pdo->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid'")->fetchColumn();$manager=(int)$pdo->query("SELECT UserID FROM Users WHERE Email='management@example.invalid'")->fetchColumn();$other=(int)$pdo->query("SELECT UserID FROM Users WHERE Email='other@example.invalid'")->fetchColumn();
$_SESSION=['UserID'=>$admin,'Role'=>'Admin'];$before=u2manifest($pdo);$id=(int)$fixture['ordinary_journal'];$j=accounting_posted_journal($pdo,$admin,$id);
$s=$pdo->prepare('SELECT COUNT(*) n,SUM(debit_amount) debit,SUM(credit_amount) credit FROM journal_entry_lines WHERE journal_entry_id=?');$s->execute([$id]);$expected=$s->fetch();
u2check(count($j['lines'])===(int)$expected['n']&&$j['debit_cents']===(string)accounting_cents($expected['debit'])&&$j['credit_cents']===(string)accounting_cents($expected['credit']),'Scoped reader retains every line and exact totals');
u2check((int)$j['header']['journal_id']===$id&&isset($j['header']['recorded_at']),'Scoped header retains identity and stored creation timestamp');
$s=$pdo->prepare('SELECT target_journal_id,reversal_journal_id,replacement_journal_id FROM journal_corrections ORDER BY id DESC LIMIT 1');$s->execute();$c=$s->fetch();
foreach($c as $role=>$journal){if($journal===null)continue;$detail=accounting_posted_journal($pdo,$admin,(int)$journal);u2check((int)$detail['header']['journal_id']===(int)$journal&&!empty($detail['corrections']),'Direct access retains '.$role);}
$s=$pdo->query("SELECT j.id FROM journal_entries j JOIN posted_evidence_associations a ON a.journal_id=j.id WHERE a.source_association_id IS NOT NULL LIMIT 1");$reused=$s->fetchColumn();
if($reused!==false){$detail=accounting_posted_journal($pdo,$admin,(int)$reused);u2check((bool)$detail['attachments']&&!array_filter($detail['attachments'],fn($a)=>!str_contains($a['url'],'journal_id='.$reused)),'Reused evidence URLs use the relevant posted association');}
$_SESSION=['UserID'=>$other,'Role'=>'Admin'];u2check(accounting_posted_journal($pdo,$other,$id)['header']['journal_id']==$id,'Posted inspection is not restricted to private draft owner');
$_SESSION=['UserID'=>$manager,'Role'=>'Admin'];$sensitive=accounting_posted_journal($pdo,$manager,(int)$fixture['advance_journal']);u2check($sensitive['attachments']===[]&&isset($sensitive['evidence_coverage']['status']),'Database Management identity overrides forged session role and keeps financial summary');
$_SESSION=['UserID'=>0];u2reject(fn()=>accounting_posted_journal($pdo,0,$id),401,'Anonymous service read rejects');$_SESSION=['UserID'=>$admin,'Role'=>'Admin'];u2reject(fn()=>accounting_posted_journal($pdo,$admin,4294967295),404,'Missing posted record rejects');
$pdo->beginTransaction();try{$pdo->exec('UPDATE Users SET Is_Active=0 WHERE UserID='.$admin);u2reject(fn()=>accounting_posted_journal($pdo,$admin,$id),403,'Inactive actor rejects');}finally{$pdo->rollBack();}
$pdo->beginTransaction();try{$pdo->exec("INSERT INTO journal_entries(entry_date,description,status) VALUES('2026-01-01','Synthetic unposted U2 reader fixture','draft')");$unposted=(int)$pdo->lastInsertId();u2reject(fn()=>accounting_posted_journal($pdo,$admin,$unposted),404,'Unposted journal is not exposed');}finally{$pdo->rollBack();}
u2check(u2manifest($pdo)===$before,'Posted reads and rolled-back denial fixtures preserve data and audits');
$source=file_get_contents(__DIR__.'/../includes/accounting_query.php');$function=substr($source,strpos($source,'function accounting_posted_journal('),strpos($source,'function accounting_records(')-strpos($source,'function accounting_posted_journal('));
u2check(!str_contains($function,'accounting_records(')&&!str_contains($function,'advance_detail(')&&str_contains($function,'accounting_complete_journals($pdo,[$id]'),'Exact adapter uses a scoped ID reader instead of all-history or advance comparison');
echo "U2 reader checks: $checks\n";
