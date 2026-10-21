<?php
/** Offline PDO doubles for direct no-index reader; no database connection. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_corrected_probe.php';
$n=0;function tc(string $name,bool $ok):void{global $n;if(!$ok)throw new RuntimeException('FAILED: '.$name);$n++;}
function rc(string $name,callable $f):void{$bad=false;try{$f();}catch(Throwable $e){$bad=true;}tc($name,$bad);}
function cc():array{return array_fill_keys(['Handler_read_first','Handler_read_key','Handler_read_last','Handler_read_next','Handler_read_prev','Handler_read_rnd','Handler_read_rnd_next'],0);}
final class P3CStatement extends PDOStatement{
    private int $i=0;public function __construct(private array $rows){}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return $this->rows[$this->i++]??false;}
    public function closeCursor():bool{return true;}
}
final class P3CDouble extends PDO{
    public array $commands=[],$counts,$batches,$plan=[['type'=>'system','key'=>null,'rows'=>'1']],$warnings=[];
    public bool $badColumns=false,$indexHit=false,$shortCounter=false,$readFailure=false,$closeFailure=false,$warnOnOpen=false;
    public int $page=0;public bool $opened=false;
    public function __construct(array $batches){$this->counts=cc();$this->batches=$batches;}
    public function exec(string $sql):int|false{
        $this->commands[]=$sql;
        if($sql==='HANDLER mysql.tables_priv OPEN AS p3c_grant_rows'){$this->opened=true;if($this->warnOnOpen)$this->warnings=[['Level'=>'Warning','Code'=>'1','Message'=>'synthetic warning']];return 0;}
        if($sql==='HANDLER p3c_grant_rows CLOSE'){$this->opened=false;if($this->closeFailure)throw new RuntimeException('synthetic close error');return 0;}
        throw new RuntimeException('Forbidden exec: '.$sql);
    }
    public function query(string $sql,?int $mode=null,mixed ...$args):PDOStatement|false{
        $this->commands[]=$sql;
        if($sql==='SHOW INDEX FROM mysql.tables_priv')return new P3CStatement([['Key_name'=>'PRIMARY'],['Key_name'=>'Grantor']]);
        if(str_starts_with($sql,'EXPLAIN SELECT * FROM mysql.tables_priv IGNORE INDEX'))return new P3CStatement($this->plan);
        if($sql==='SHOW FULL COLUMNS FROM mysql.tables_priv'){
            $names=['Host','Db','User','Table_name','Grantor','Timestamp','Table_priv','Column_priv'];if($this->badColumns)array_pop($names);
            return new P3CStatement(array_map(fn($v)=>['Field'=>$v,'Extra'=>''],$names));
        }
        if($sql==='SHOW WARNINGS')return new P3CStatement($this->warnings);
        if($sql==="SHOW SESSION STATUS LIKE 'Handler_read_%'")return new P3CStatement(array_map(fn($k,$v)=>['Variable_name'=>$k,'Value'=>(string)$v],array_keys($this->counts),array_values($this->counts)));
        if(preg_match('/\AHANDLER p3c_grant_rows READ (FIRST|NEXT) LIMIT 128\z/D',$sql)){
            if(!$this->opened)throw new RuntimeException('Read without open');if($this->readFailure)throw new RuntimeException('synthetic read error');
            $rows=$this->batches[$this->page++]??[];
            if(!$this->shortCounter)$this->counts['Handler_read_rnd_next']+=count($rows)+1;
            if($this->indexHit)$this->counts['Handler_read_first']++;
            return new P3CStatement($rows);
        }
        throw new RuntimeException('Forbidden row SELECT or other query: '.$sql);
    }
}
$row=['host','db','user','table','private synthetic grant',null,"\xff\0",''];
foreach([[],[$row],[$row,$row]] as $rows){
    $p=new P3CDouble([$rows,[]]);$saved=[];$r=p3c_scan($p,function($name,$value)use(&$saved){$saved[$name]=$value;});
    tc('empty/one/duplicate counts exact',$r['rows']===count($rows));
    tc('all physical values hashed exactly',$r['sha256']===p2b_multiset(array_map('p2b_row_hash',$rows)));
    tc('EOF and no-index proof explicit',$r['eof'] && $r['counter_proof'] && $r['index']===null && $r['access']==='HANDLER_TABLE_SCAN');
    tc('handle closed after success',!$p->opened && in_array('HANDLER p3c_grant_rows CLOSE',$p->commands,true));
    tc('raw grant never in scan evidence',!str_contains(json_encode($saved,JSON_THROW_ON_ERROR),'private synthetic grant'));
    tc('no row SELECT executed',count(array_filter($p->commands,fn($q)=>str_starts_with($q,'SELECT ')))===0);
    tc('actual system EXPLAIN saved',$saved['before']['select_plan']===$p->plan);
}
$p=new P3CDouble([array_fill(0,128,$row),[$row],[]]);$r=p3c_scan($p);
tc('multi-batch enumerates beyond first limit',$r['rows']===129 && $p->page===3);
tc('partial batch still followed by explicit EOF read',count(array_filter($p->commands,fn($q)=>$q==='HANDLER p3c_grant_rows READ NEXT LIMIT 128'))===2);
$p=new P3CDouble([[$row],[]]);$p->plan=[['type'=>'ALL','key'=>null]];tc('reader independent of ALL label',p3c_scan($p)['rows']===1);
$p=new P3CDouble([[$row],[]]);$p->indexHit=true;$saved=[];rc('any index access blocks baseline',function()use($p,&$saved){p3c_scan($p,function($name,$value)use(&$saved){$saved[$name]=$value;});});
tc('actual counter mismatch retained before rejection',isset($saved['complete']) && $saved['complete']['after']['Handler_read_first']>0);
$p=new P3CDouble([[$row],[]]);$p->shortCounter=true;rc('missing sequential/EOF counter proof rejected',fn()=>p3c_scan($p));
$p=new P3CDouble([[$row],[]]);$p->readFailure=true;rc('read interruption blocks baseline',fn()=>p3c_scan($p));tc('handler closed after read exception',!$p->opened);
$p=new P3CDouble([[$row],[]]);$p->warnOnOpen=true;rc('warnings block read',fn()=>p3c_scan($p));tc('warning closed handle and fetched no data',$p->page===0 && !$p->opened);
$p=new P3CDouble([[$row],[]]);$p->closeFailure=true;rc('close failure blocks baseline',fn()=>p3c_scan($p));
$p=new P3CDouble([[$row],[]]);$p->badColumns=true;rc('schema mismatch blocks physical open',fn()=>p3c_scan($p));tc('no open after metadata mismatch',!in_array('HANDLER mysql.tables_priv OPEN AS p3c_grant_rows',$p->commands,true));
$p=new P3CDouble([[['wrong-shape']],[]]);rc('wrong row width rejected',fn()=>p3c_scan($p));tc('wrong width closes handle',!$p->opened);
$p=new P3CDouble([[$row],[]]);rc('private evidence failure blocks reader',fn()=>p3c_scan($p,fn()=>throw new RuntimeException('synthetic write failure')));tc('no open after before-evidence failure',!$p->opened);
$base=cc();$after=$base;$after['Handler_read_rnd_next']=2;tc('valid one-row direct counter proof',p3c_counter_proof($base,$after,1)['Handler_read_rnd_next']===2);
foreach(['Handler_read_first','Handler_read_key','Handler_read_last','Handler_read_next','Handler_read_prev','Handler_read_rnd'] as $key){$bad=$after;$bad[$key]=1;rc('reject counter increment: '.$key,fn()=>p3c_counter_proof($base,$bad,1));}
rc('missing counter inventory rejected',fn()=>p3c_counters([]));
$counterRows=array_map(fn($k)=>['Variable_name'=>$k,'Value'=>'0'],array_keys($base));tc('exact installed counter shape accepted',p3c_counters($counterRows)===$base);
$bad=$counterRows;$bad[]=$bad[0];rc('duplicate counter rejected',fn()=>p3c_counters($bad));
$bad=$counterRows;$bad[0]['Value']='-1';rc('negative counter rejected',fn()=>p3c_counters($bad));
$bad=$counterRows;$bad[0]['Value']='999999999999999999999';rc('counter overflow rejected',fn()=>p3c_counters($bad));
$bad=$after;$bad['Handler_read_rnd_next']=-1;rc('counter reset rejected',fn()=>p3c_counter_proof($base,$bad,1));
$good=['passed'=>true,'mode'=>'capture','writes_performed'=>false,'stable_baseline'=>true,'no_table_checks'=>true,
    'baseline'=>['snapshot'=>['tables'=>[]],'tables_priv_scan'=>['rows'=>1,'sha256'=>'synthetic','access'=>'HANDLER_TABLE_SCAN','index'=>null,'eof'=>true,'counter_proof'=>true]]];
tc('complete corrected baseline accepted',p3c_report_baseline($good)===$good['baseline']);
foreach(['eof','counter_proof'] as $key){$bad=$good;$bad['baseline']['tables_priv_scan'][$key]=false;rc('incomplete baseline rejected: '.$key,fn()=>p3c_report_baseline($bad));}
$bad=$good;$bad['baseline']['tables_priv_scan']['access']='ALL';rc('old optimizer-only proof rejected',fn()=>p3c_report_baseline($bad));
$bad=$good;$bad['baseline']['tables_priv_scan']['rows']=-1;rc('negative physical row baseline rejected',fn()=>p3c_report_baseline($bad));
$p=new P3CDouble(array_fill(0,800,[$row]));rc('no EOF within scan page ceiling rejects baseline',fn()=>p3c_scan($p));tc('exhausted scan closes handle',!$p->opened);
$a=['tables_priv_scan'=>['rows'=>0,'sha256'=>p2b_multiset([])]];$b=['tables_priv_scan'=>['rows'=>1,'sha256'=>p2b_multiset([p2b_row_hash($row)])]];
rc('changed repeated physical baseline is rejected',fn()=>p2b_compare($a,$b));
$root=__DIR__.'/../.migration-private/phase3a-corrected-php-tests-'.bin2hex(random_bytes(5));mkdir($root,0700,true);
p2b_json_write($root.'/results.json',['passed'=>$n,'database_connected'=>false,'server_started'=>false,'repair_run'=>false,'pdo_doubles_only'=>true]);
echo json_encode(['passed'=>$n,'evidence'=>realpath($root)],JSON_PRETTY_PRINT)."\n";
