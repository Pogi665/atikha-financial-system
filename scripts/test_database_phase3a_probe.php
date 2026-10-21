<?php
/** Offline tests of explicit grant scans and baseline guards; no real PDO connection. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_probe.php';
$n=0;
function t3(string $name,bool $ok):void{global $n;if(!$ok)throw new RuntimeException('FAILED: '.$name);$n++;}
function r3(string $name,callable $f):void{$blocked=false;try{$f();}catch(Throwable $e){$blocked=true;}t3($name,$blocked);}
final class P3Statement extends PDOStatement{
    private array $rows;private int $i=0;
    public function __construct(array $rows){$this->rows=$rows;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return $this->rows[$this->i++]??false;}
    public function closeCursor():bool{return true;}
}
final class P3PDO extends PDO{
    public array $queries=[];public array $plan=[['type'=>'ALL','key'=>null]];
    public function __construct(){}
    public function query(string $sql,?int $mode=null,mixed ...$args):PDOStatement|false{
        $this->queries[]=$sql;
        if($sql==='SHOW INDEX FROM mysql.tables_priv')return new P3Statement([['Key_name'=>'PRIMARY'],['Key_name'=>'PRIMARY'],['Key_name'=>'Grantor']]);
        if(str_starts_with($sql,'EXPLAIN '))return new P3Statement($this->plan);
        if(str_starts_with($sql,'SELECT * FROM mysql.tables_priv IGNORE INDEX'))return new P3Statement([['a',null,"\xff\0"],['b','','private synthetic grant']]);
        throw new RuntimeException('Unexpected SQL in offline scan test.');
    }
}
$sql=p3a_scan_sql([['Key_name'=>'PRIMARY'],['Key_name'=>'Grantor'],['Key_name'=>'PRIMARY']]);
t3('scan excludes both installed keys',$sql==='SELECT * FROM mysql.tables_priv IGNORE INDEX (`PRIMARY`,`Grantor`)');
r3('missing key rejected',fn()=>p3a_scan_sql([['Key_name'=>'PRIMARY']]));
r3('unknown extra index rejected',fn()=>p3a_scan_sql([['Key_name'=>'PRIMARY'],['Key_name'=>'Grantor'],['Key_name'=>'extra']]));
r3('index injection rejected',fn()=>p3a_scan_sql([['Key_name'=>'PRIMARY`); DROP DATABASE mysql']]));
r3('missing index metadata rejected',fn()=>p3a_scan_sql([[]]));
p3a_scan_plan([['type'=>'ALL','key'=>null]]);t3('full scan plan accepted',true);
r3('index range plan rejected',fn()=>p3a_scan_plan([['type'=>'range','key'=>'PRIMARY']]));
r3('covering index plan rejected',fn()=>p3a_scan_plan([['type'=>'index','key'=>'PRIMARY']]));
r3('ALL with key still rejected',fn()=>p3a_scan_plan([['type'=>'ALL','key'=>'PRIMARY']]));
r3('missing key field rejected',fn()=>p3a_scan_plan([['type'=>'ALL']]));
r3('empty plan rejected',fn()=>p3a_scan_plan([]));
r3('multi-table plan rejected',fn()=>p3a_scan_plan([['type'=>'ALL','key'=>null],['type'=>'ALL','key'=>null]]));
$p=new P3PDO();$scan=p3a_scan($p);t3('every synthetic row counted',$scan['rows']===2);
t3('binary null row hashes preserved',$scan['sha256']===p2b_multiset([p2b_row_hash(['a',null,"\xff\0"]),p2b_row_hash(['b','','private synthetic grant'])]));
t3('plan inspected before scan',count($p->queries)===3 && str_starts_with($p->queries[1],'EXPLAIN ') && str_starts_with($p->queries[2],'SELECT *'));
t3('raw grant values stay out of report',!str_contains(json_encode($scan,JSON_THROW_ON_ERROR),'private synthetic grant'));
$p=new P3PDO();$p->plan=[['type'=>'ref','key'=>'PRIMARY']];r3('unsafe actual plan aborts before row scan',fn()=>p3a_scan($p));t3('failed plan performed no row scan',count($p->queries)===2);
$report=['passed'=>true,'mode'=>'capture','writes_performed'=>false,'no_table_checks'=>true,'stable_baseline'=>true,'baseline'=>['snapshot'=>['tables'=>['synthetic'=>1]],'tables_priv_scan'=>$scan]];
t3('complete captured baseline accepted',p3a_report_baseline($report)===$report['baseline']);
foreach(['passed','no_table_checks','stable_baseline'] as $k){$bad=$report;$bad[$k]=false;r3('required capture gate: '.$k,fn()=>p3a_report_baseline($bad));}
$bad=$report;$bad['writes_performed']=true;r3('capture write claim rejected',fn()=>p3a_report_baseline($bad));
$bad=$report;$bad['mode']='inspect';r3('inspection cannot masquerade as original baseline',fn()=>p3a_report_baseline($bad));
$bad=$report;unset($bad['baseline']['snapshot']);r3('missing snapshot rejected',fn()=>p3a_report_baseline($bad));
r3('working root rejected before PDO',fn()=>p3a_options(['root'=>'C:/xampp/mysql/data','pid'=>'1','output'=>'C:/tmp/x','uploads'=>'x','mode'=>'capture']));
r3('missing explicit options rejected',fn()=>p3a_options([]));
// Old strict warning classifier remains unchanged even for the previously observed warning+OK.
r3('known proc warning still blocks checks',fn()=>p2b_check_rows([['Msg_type'=>'warning','Msg_text'=>"1 client is using or hasn't closed the table properly"],['Msg_type'=>'status','Msg_text'=>'OK']]));
$root=__DIR__.'/../.migration-private/phase3a-probe-tests-'.bin2hex(random_bytes(5));mkdir($root,0700,true);
p2b_json_write($root.'/results.json',['passed'=>$n,'database_connected'=>false,'server_started'=>false,'repair_run'=>false]);
echo json_encode(['passed'=>$n,'private_evidence'=>realpath($root),'database_connected'=>false,'repair_run'=>false],JSON_PRETTY_PRINT)."\n";
