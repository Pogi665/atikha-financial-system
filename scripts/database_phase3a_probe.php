<?php
/** Phase 3-A copied-server baseline/inspection. No working config or SQL writers. */
declare(strict_types=1);
require_once __DIR__.'/database_phase2b_probe.php';

function p3a_scan_sql(array $indexRows): string {
    $names=[];foreach($indexRows as $r){p2b_require(isset($r['Key_name']),'Missing grant index metadata.');$names[$r['Key_name']]=true;}
    ksort($names,SORT_STRING);p2b_require(array_keys($names)===['Grantor','PRIMARY'],'Unexpected mysql.tables_priv index set.');
    return 'SELECT * FROM mysql.tables_priv IGNORE INDEX (`PRIMARY`,`Grantor`)';
}
function p3a_scan_plan(array $plan): void {
    p2b_require(count($plan)===1 && ($plan[0]['type']??'')==='ALL' && array_key_exists('key',$plan[0]) && $plan[0]['key']===null,'Permission baseline does not prove a full table scan.');
}
function p3a_scan(PDO $p): array {
    $sql=p3a_scan_sql($p->query('SHOW INDEX FROM mysql.tables_priv')->fetchAll(PDO::FETCH_ASSOC));
    $plan=$p->query('EXPLAIN '.$sql)->fetchAll(PDO::FETCH_ASSOC);p3a_scan_plan($plan);
    $q=$p->query($sql);$rows=[];while($r=$q->fetch(PDO::FETCH_NUM))$rows[]=p2b_row_hash($r);$q->closeCursor();
    return ['rows'=>count($rows),'sha256'=>p2b_multiset($rows),'access'=>'ALL','index'=>null];
}
function p3a_capture(PDO $p): array {
    $scan=p3a_scan($p);$snapshot=p2b_snapshot($p);
    p2b_require(($snapshot['permissions']['tables_priv']??null)===['rows'=>$scan['rows'],'sha256'=>$scan['sha256']], 'Default and explicitly forced grant scans differ.');
    return ['snapshot'=>$snapshot,'tables_priv_scan'=>$scan];
}
function p3a_report_baseline(array $report): array {
    p2b_require(($report['passed']??null)===true && ($report['mode']??null)==='capture' &&
        ($report['writes_performed']??null)===false && ($report['no_table_checks']??null)===true &&
        ($report['stable_baseline']??null)===true && isset($report['baseline']['snapshot'],$report['baseline']['tables_priv_scan']), 'Private pre-repair baseline is incomplete.');
    return $report['baseline'];
}
function p3a_options(array $opt): array {
    foreach(['root','pid','output','uploads','mode'] as $key)p2b_require(isset($opt[$key]) && is_string($opt[$key]),'Explicit isolated arguments required.');
    $base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
    $root=realpath($opt['root']);p2b_require($root!==false,'Missing isolated root.');
    p2b_require(preg_match('~\A'.preg_quote(strtolower($base),'~').'phase2b-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}/(capture|validation|restore)\z~D',p2b_path($root),$m)===1,'Invalid trial role/path.');
    $role=$m[1];p2b_require(($opt['mode']==='capture' && $role==='capture') || ($opt['mode']==='inspect' && in_array($role,['validation','restore'],true)),'Probe mode and candidate role differ.');
    $name=$opt['mode']==='capture'?'capture.json':'inspection.json';$out=$opt['output'];
    p2b_require(basename($out)===$name && p2b_path(dirname($out))===p2b_path($root.'/logs') && p2b_path((string)realpath(dirname($out)))===p2b_path($root.'/logs'),'Output outside ordinary trial logs.');
    p2b_require(ctype_digit($opt['pid']) && (int)$opt['pid']>0,'Invalid explicit PID.');
    p2b_require(p2b_path($opt['uploads'])===p2b_path($base.'preserved/storage/uploads'),'Unexpected preserved evidence root.');
    $baseline=null;
    if($opt['mode']==='inspect'){
        p2b_require(isset($opt['baseline']) && is_string($opt['baseline']),'Explicit original baseline required.');
        $baseline=realpath($opt['baseline']);
        p2b_require($baseline!==false && p2b_path($baseline)===p2b_path(dirname($root).'/capture/logs/capture.json'),'Baseline outside this attempt capture role.');
    }
    return ['root'=>$root,'output'=>$out,'baseline'=>$baseline,'role'=>$role];
}
function p3a_main(): int {
    $out=null;$p=null;$report=['passed'=>false,'writes_performed'=>false,'working_database_accessed'=>false];
    try {
        p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'CLI 64-bit PHP required.');
        $opt=getopt('',['root:','pid:','output:','uploads:','mode:','baseline:']);$paths=p3a_options($opt);
        $root=$paths['root'];$out=$paths['output'];$report['mode']=$opt['mode'];
        $p=new PDO('mysql:host=127.0.0.1;port=3307;dbname=atikha_finance;charset=utf8mb4','root','',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>3]);
        $id=$p->query('SELECT @@port port,@@datadir datadir,@@pid_file pid_file,VERSION() version,@@global.read_only ro,@@global.innodb_force_recovery recovery,@@global.event_scheduler events,@@global.log_bin binlog')->fetch();
        p2b_require($id['port']==='3307' && p2b_path($id['datadir'])===p2b_path($root.'/data') && p2b_path($id['pid_file'])===p2b_path($root.'/candidate.pid') &&
            trim(file_get_contents($id['pid_file']))===$opt['pid'] && str_starts_with($id['version'],'10.4.32-MariaDB') &&
            $id['ro']==='1' && $id['recovery']==='0' && $id['events']==='OFF' && $id['binlog']==='0','Authenticated isolated identity failed.');
        $p->exec("SET SESSION time_zone='+00:00', max_statement_time=20");
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$first=p3a_capture($p);$p->rollBack();
        // Preserve the baseline immediately, even if a later read/check fails.
        p2b_json_write($root.'/logs/baseline-first.json',$first);
        if($opt['mode']==='capture'){
            $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$second=p3a_capture($p);$p->rollBack();p2b_compare($first,$second);
            $report+=['baseline'=>$first,'stable_baseline'=>true,'no_table_checks'=>true];
        }else{
            $baselineReport=json_decode(file_get_contents($paths['baseline']),true,512,JSON_THROW_ON_ERROR);
            $baseline=p3a_report_baseline($baselineReport);p2b_compare($baseline,$first);
            $report['baseline_matched_before_checks']=true;
            $report['checked_objects']=p2b_table_checks($p,$root.'/logs/table-checks.jsonl');
            $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();
            $report['foreign_keys']=p2b_orphans($p);
            define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/../includes/journal_corrections.php';
            p2b_require(stage1_schema($p) && stage2_schema($p) && stage3_schema($p),'Complete 019/020/021 schema required; no migration allowed.');
            $report['integrity_problems']=correction_integrity($p);p2b_require($report['integrity_problems']===[],'Accounting/correction/advance/evidence integrity needs review.');
            $report['evidence']=p2b_evidence($p,$opt['uploads']);
            $after=p3a_capture($p);p2b_compare($first,$after);p2b_compare($baseline,$after);$p->rollBack();
            $report+=['snapshot'=>$after['snapshot'],'tables_priv_scan'=>$after['tables_priv_scan'],'stable_before_after_checks'=>true,'baseline_matched_after_checks'=>true];
        }
        $report['passed']=true;p2b_json_write($out,$report);echo "PHASE3A_ISOLATED_PROBE_PASSED\n";return 0;
    }catch(Throwable $e){
        if($p instanceof PDO && $p->inTransaction())$p->rollBack();$report['failure']=$e->getMessage();
        if(is_string($out) && !file_exists($out))p2b_json_write($out,$report);
        fwrite(STDERR,"Phase 3-A probe stopped; see private evidence.\n");return 1;
    }
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(p3a_main());
