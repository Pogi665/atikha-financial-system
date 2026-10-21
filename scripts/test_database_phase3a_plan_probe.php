<?php
/** Offline diagnostic tests, with PDO doubles only. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_plan_probe.php';
$n=0;
function td(string $name,bool $ok):void{global $n;if(!$ok)throw new RuntimeException('FAILED: '.$name);$n++;}
function rd(string $name,callable $fn):void{$bad=false;try{$fn();}catch(Throwable $e){$bad=true;}td($name,$bad);}
final class P3DStatement extends PDOStatement{
    public function __construct(private array $rows){}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
final class P3DDouble extends PDO{
    public array $queries=[];
    public array $indexes=[['Key_name'=>'PRIMARY'],['Key_name'=>'Grantor']];
    public array $plan=[['id'=>'1','select_type'=>'SIMPLE','table'=>'tables_priv','type'=>'system','key'=>null,'rows'=>'0','Extra'=>'synthetic']];
    public bool $throwPlan=false;
    public function __construct(){}
    public function query(string $sql,?int $mode=null,mixed ...$args):PDOStatement|false{
        $this->queries[]=$sql;
        if($sql==='SHOW INDEX FROM mysql.tables_priv')return new P3DStatement($this->indexes);
        if($sql==='EXPLAIN SELECT * FROM mysql.tables_priv IGNORE INDEX (`PRIMARY`,`Grantor`)'){
            if($this->throwPlan)throw new RuntimeException('synthetic EXPLAIN error');return new P3DStatement($this->plan);
        }
        throw new RuntimeException('Forbidden SQL in diagnostic: '.$sql);
    }
}
$base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
p3d_role($base.'phase3a-plan-20990101-000000-abcdef12/diagnostic');td('fresh diagnostic role accepted',true);
foreach(['C:/xampp/mysql/data',$base.'phase2b-20990101-000000-abcdef12/capture',$base.'phase3a-plan-20990101-000000-abcdef12/restore',$base.'phase3a-plan-20990101-000000-abcdef12/diagnostic/x',$base."phase3a-plan-20990101-000000-abcdef12/diagnostic\n"] as $bad)rd('non-diagnostic path rejected',fn()=>p3d_role($bad));
$p=new P3DDouble();$saved=[];$events=[];
$r=p3d_collect($p,function($name,$value)use(&$saved,&$events,$p){$saved[$name]=$value;$events[]=[count($p->queries),$name];});
td('metadata diagnostic completes despite original guard rejection',$r['diagnostic_complete'] && !$r['existing_scan_guard_accepted']);
td('exact rejected plan preserved',$saved['query-plan.json']['explain']===$p->plan && $r['explain']===$p->plan);
td('indexes saved before EXPLAIN and plan before interpretation',$events===[[1,'indexes.json'],[2,'query-plan.json']]);
td('original guard reason retained',$r['existing_scan_guard_reason']==='Permission baseline does not prove a full table scan.');
td('only metadata and EXPLAIN queries performed',count($p->queries)===2 && !str_starts_with($p->queries[1],'SELECT'));
foreach(['financial_rows_read','permission_row_select_executed','table_checks_performed','repair_performed','import_performed'] as $key)td('forbidden action remains false: '.$key,$r[$key]===false);
$p=new P3DDouble();$p->plan=[['type'=>'ALL','key'=>null]];$saved=[];
$r=p3d_collect($p,function($name,$value)use(&$saved){$saved[$name]=$value;});
td('passing original guard recorded without row scan',$r['existing_scan_guard_accepted'] && count($p->queries)===2);
td('passing plan saved too',isset($saved['query-plan.json']));
$p=new P3DDouble();$p->plan=[['type'=>'range','key'=>'PRIMARY']];$r=p3d_collect($p,fn()=>null);
td('unsafe plan remains rejected',$r['existing_scan_guard_accepted']===false);
$p=new P3DDouble();$p->plan=[];$saved=[];$r=p3d_collect($p,function($name,$value)use(&$saved){$saved[$name]=$value;});
td('empty EXPLAIN evidence preserved without acceptance',$saved['query-plan.json']['explain']===[] && !$r['existing_scan_guard_accepted']);
$p=new P3DDouble();rd('evidence write failure stops collection',fn()=>p3d_collect($p,fn()=>throw new RuntimeException('synthetic save failure')));
td('no EXPLAIN after index evidence write failure',count($p->queries)===1);
$p=new P3DDouble();$p->indexes[]=['Key_name'=>'unknown'];$saved=[];
rd('unknown index set stops without changing old rule',function()use($p,&$saved){p3d_collect($p,function($name,$value)use(&$saved){$saved[$name]=$value;});});
td('unexpected indexes retained before failure',$saved['indexes.json']['indexes']===$p->indexes && count($p->queries)===1);
$p=new P3DDouble();$p->throwPlan=true;$saved=[];
rd('EXPLAIN exception stops collection',function()use($p,&$saved){p3d_collect($p,function($name,$value)use(&$saved){$saved[$name]=$value;});});
td('index evidence survives EXPLAIN exception',isset($saved['indexes.json']) && !isset($saved['query-plan.json']));
$root=__DIR__.'/../.migration-private/phase3a-plan-php-tests-'.bin2hex(random_bytes(5));mkdir($root,0700,true);
p2b_json_write($root.'/results.json',['passed'=>$n,'database_connected'=>false,'server_started'=>false,'repair_run'=>false,'sql_double_only'=>true]);
echo json_encode(['passed'=>$n,'evidence'=>realpath($root),'database_connected'=>false],JSON_PRETTY_PRINT)."\n";
