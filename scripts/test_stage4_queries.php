<?php
/** Record actual ledger SQL templates/plans only on a guarded synthetic workload. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';require __DIR__.'/../includes/general_ledger_query.php';
$o=getopt('',['fixture:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private synthetic workload required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_s4g1perf[a-z0-9]+\z/',$db),'Stage4 performance namespace required.');
$w=json_decode(file_get_contents(dirname($path).'/stage4-workload.json'),true,64,JSON_THROW_ON_ERROR);cli_require($w['database']===$db&&$w['writer_preparation_complete'],'Prepared workload required.');
class Stage4TracePDO extends PDO
{
    public array $templates=[];
    public function prepare(string $query,array $options=[]): PDOStatement|false {$this->templates[]=$query;return parent::prepare($query,$options);}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {$this->templates[]=$query;return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);}
}
$p=new Stage4TracePDO('mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1').';dbname='.$db.';charset=utf8mb4',getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic actor required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$p->templates=[];
$r=ledger_read($p,$uid,$w['criteria']);$templates=$p->templates;
cli_require($r['period_count']===$w['selected_period_lines']&&count($templates)<=10,'Bounded query count and declared workload must match.');
$windows=array_values(array_filter($templates,fn($sql)=>str_starts_with($sql,'WITH movements AS')));cli_require(count($windows)===2,'Summary and result window queries required.');
$plans=[];foreach($windows as $sql){$s=$p->prepare('EXPLAIN FORMAT=JSON '.$sql);$c=$w['criteria'];$s->execute([$c['account_id'],$c['from'],accounting_next_day($c['to']),$c['search'],$c['search']]);$plans[]=['sql'=>$sql,'plan'=>json_decode($s->fetchColumn(),true,64,JSON_THROW_ON_ERROR)];}
$out=['passed'=>true,'selected_period_lines'=>$r['period_count'],'model_query_count'=>count($templates),'actual_window_plans'=>$plans,'all_templates'=>$templates,'no_evidence_hydration'=>!array_filter($templates,fn($sql)=>preg_match('/\b(?:FROM|JOIN)\s+`?(?:Receipts|posted_evidence_associations|evidence_allocations|journal_drafts)\b/i',$sql))];
cli_require($out['no_evidence_hydration'],'Ledger SQL must not load private evidence or drafts.');file_put_contents(dirname($path).'/stage4-query-plans.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['passed'=>true,'selected_period_lines'=>$r['period_count'],'model_query_count'=>count($templates),'window_plans'=>count($plans),'evidence_queries'=>0]).PHP_EOL;
