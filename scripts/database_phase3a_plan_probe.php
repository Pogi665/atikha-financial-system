<?php
/** Diagnostic-only copied-server index metadata and EXPLAIN capture; never fetches table rows. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_probe.php';

function p3d_role(string $root): void {
    $base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
    p2b_require(!str_contains($root,"\n") && !str_contains($root,"\r") && preg_match('~\A'.preg_quote(strtolower($base),'~').'phase3a-plan-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}/diagnostic\z~D',p2b_path($root))===1,'Not the fresh diagnostic-only role.');
}
function p3d_collect(PDO $p,callable $save): array {
    $indexes=$p->query('SHOW INDEX FROM mysql.tables_priv')->fetchAll(PDO::FETCH_ASSOC);
    // Persist each result before any interpretation; failure must retain actual observations.
    $save('indexes.json',['indexes'=>$indexes]);
    $sql=p3a_scan_sql($indexes);
    $plan=$p->query('EXPLAIN '.$sql)->fetchAll(PDO::FETCH_ASSOC);
    $save('query-plan.json',['sql'=>$sql,'explain'=>$plan]);
    $accepted=true;$reason=null;
    try {p3a_scan_plan($plan);} catch(Throwable $e){$accepted=false;$reason=$e->getMessage();}
    return ['diagnostic_complete'=>true,'indexes'=>$indexes,'scan_sql'=>$sql,'explain'=>$plan,
        'existing_scan_guard_accepted'=>$accepted,'existing_scan_guard_reason'=>$reason,
        'financial_rows_read'=>false,'permission_row_select_executed'=>false,'table_checks_performed'=>false,'repair_performed'=>false,'import_performed'=>false];
}
function p3d_main(): int {
    $out=null;$report=['passed'=>false,'working_database_accessed'=>false,'writes_performed'=>false,'mode'=>'plan-diagnostic'];
    try {
        p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'64-bit CLI required.');
        $opt=getopt('',['root:','pid:','output:']);
        foreach(['root','pid','output'] as $key)p2b_require(isset($opt[$key]) && is_string($opt[$key]),'Explicit diagnostic arguments required.');
        $root=realpath($opt['root']);p2b_require($root!==false,'Diagnostic root missing.');p3d_role($opt['root']);p3d_role($root);
        p2b_require(ctype_digit($opt['pid']) && (int)$opt['pid']>0,'Explicit PID required.');
        p2b_require(basename($opt['output'])==='plan-diagnostic.json' && p2b_path(dirname($opt['output']))===p2b_path($root.'/logs') && p2b_path((string)realpath(dirname($opt['output'])))===p2b_path($root.'/logs'),'Diagnostic output outside ordinary logs.');
        $out=$opt['output'];
        $p=new PDO('mysql:host=127.0.0.1;port=3307;dbname=mysql;charset=utf8mb4','root','',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>3]);
        $id=$p->query('SELECT @@port port,@@datadir datadir,@@pid_file pid_file,VERSION() version,@@global.read_only ro,@@global.innodb_force_recovery recovery,@@global.event_scheduler events,@@global.log_bin binlog')->fetch();
        p2b_require($id['port']==='3307' && p2b_path($id['datadir'])===p2b_path($root.'/data') && p2b_path($id['pid_file'])===p2b_path($root.'/candidate.pid') &&
            trim(file_get_contents($id['pid_file']))===$opt['pid'] && str_starts_with($id['version'],'10.4.32-MariaDB') &&
            $id['ro']==='1' && $id['recovery']==='0' && $id['events']==='OFF' && $id['binlog']==='0','Authenticated diagnostic identity failed.');
        $p->exec('SET SESSION max_statement_time=10');
        $report+=p3d_collect($p,fn(string $name,array $value)=>p2b_json_write($root.'/logs/'.$name,$value));
        $report['passed']=true;p2b_json_write($out,$report);echo "PHASE3A_PLAN_METADATA_CAPTURED\n";return 0;
    }catch(Throwable $e){
        $report['failure']=$e->getMessage();if(is_string($out) && !file_exists($out))p2b_json_write($out,$report);
        fwrite(STDERR,"Plan diagnostic stopped; inspect private evidence.\n");return 1;
    }
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(p3d_main());
