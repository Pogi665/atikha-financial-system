<?php
/** Corrected baseline: direct no-index HANDLER scan to end-of-data, with counter proof. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_probe.php';

function p3c_counters(array $rows): array {
    $all=[];
    foreach($rows as $r){
        p2b_require(isset($r['Variable_name'],$r['Value']) && !isset($all[$r['Variable_name']]) &&
            preg_match('/\A[0-9]+\z/D',(string)$r['Value'])===1 && strlen((string)$r['Value'])<19,'Invalid session read counters.');
        $all[$r['Variable_name']]=(int)$r['Value'];
    }
    $out=[];
    foreach(['Handler_read_first','Handler_read_key','Handler_read_last','Handler_read_next','Handler_read_prev','Handler_read_rnd','Handler_read_rnd_next'] as $key){
        p2b_require(array_key_exists($key,$all),'Required session read counter missing.');$out[$key]=$all[$key];
    }
    return $out;
}
function p3c_counter_proof(array $before,array $after,int $rows): array {
    p2b_require($rows>=0,'Invalid physical row count.');$delta=[];
    foreach($before as $key=>$value){
        p2b_require(isset($after[$key]) && $after[$key]>=$value,'Read counter decreased or missing.');
        $delta[$key]=$after[$key]-$value;
        if($key!=='Handler_read_rnd_next')p2b_require($delta[$key]===0,'Index or positioned access occurred during physical grant scan.');
    }
    p2b_require(count($delta)===7 && isset($delta['Handler_read_rnd_next']) && $delta['Handler_read_rnd_next']>=$rows+1,'No complete sequential read/EOF proof.');
    return $delta;
}
function p3c_no_warnings(PDO $p): void {
    p2b_require($p->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_ASSOC)===[],'Physical scan emitted a diagnostic; stop before repair.');
}
function p3c_scan(PDO $p,?callable $save=null): array {
    $indexes=$p->query('SHOW INDEX FROM mysql.tables_priv')->fetchAll(PDO::FETCH_ASSOC);
    // Inventory validation is retained. Its SELECT/EXPLAIN form is evidence only, never used as the reader.
    $sql=p3a_scan_sql($indexes);$plan=$p->query('EXPLAIN '.$sql)->fetchAll(PDO::FETCH_ASSOC);
    $columns=$p->query('SHOW FULL COLUMNS FROM mysql.tables_priv')->fetchAll(PDO::FETCH_ASSOC);
    p2b_require(array_column($columns,'Field')===['Host','Db','User','Table_name','Grantor','Timestamp','Table_priv','Column_priv'],'Unexpected physical grant-table columns.');
    foreach($columns as $c)p2b_require(!str_contains(strtolower((string)($c['Extra']??'')),'invisible'),'Invisible grant column needs review.');
    p3c_no_warnings($p);
    $before=p3c_counters($p->query("SHOW SESSION STATUS LIKE 'Handler_read_%'")->fetchAll(PDO::FETCH_ASSOC));
    if($save)$save('before',['indexes'=>$indexes,'select_plan'=>$plan,'select_sql'=>$sql,'counters'=>$before,'read_method'=>'HANDLER without index name']);
    $opened=false;$hashes=[];$eof=false;
    try {
        $p->exec('HANDLER mysql.tables_priv OPEN AS p3c_grant_rows');$opened=true;p3c_no_warnings($p);
        for($page=1;$page<=800;$page++){
            $direction=$page===1?'FIRST':'NEXT';
            $q=$p->query('HANDLER p3c_grant_rows READ '.$direction.' LIMIT 128');$count=0;
            while($row=$q->fetch(PDO::FETCH_NUM)){
                p2b_require(count($row)===8,'Physical row shape differs.');$hashes[]=p2b_row_hash($row);$count++;
                p2b_require(count($hashes)<=100000,'Physical scan row ceiling reached; no partial baseline.');
            }
            $q->closeCursor();p3c_no_warnings($p);
            if($save)$save(sprintf('page-%04d',$page),['page'=>$page,'direction'=>$direction,'rows'=>$count,'total_rows'=>count($hashes),'empty_result'=>$count===0]);
            if($count===0){$eof=true;break;}
        }
    }finally{if($opened){$p->exec('HANDLER p3c_grant_rows CLOSE');p3c_no_warnings($p);}}
    p2b_require($eof,'Physical scan did not reach end-of-data; no partial baseline.');
    $after=p3c_counters($p->query("SHOW SESSION STATUS LIKE 'Handler_read_%'")->fetchAll(PDO::FETCH_ASSOC));
    // Persist actual evidence before judging it, including a counter mismatch.
    if($save)$save('complete',['rows'=>count($hashes),'sha256'=>p2b_multiset($hashes),'eof'=>$eof,'before'=>$before,'after'=>$after]);
    p3c_counter_proof($before,$after,count($hashes));
    return ['rows'=>count($hashes),'sha256'=>p2b_multiset($hashes),'access'=>'HANDLER_TABLE_SCAN','index'=>null,'eof'=>true,'counter_proof'=>true];
}
function p3c_capture(PDO $p,callable $save): array {
    $scan=p3c_scan($p,$save);$snapshot=p2b_snapshot($p);
    p2b_require(($snapshot['permissions']['tables_priv']??null)===['rows'=>$scan['rows'],'sha256'=>$scan['sha256']],'Physical and ordinary grant-table fingerprints differ.');
    return ['snapshot'=>$snapshot,'tables_priv_scan'=>$scan];
}
function p3c_report_baseline(array $report): array {
    $baseline=p3a_report_baseline($report);$scan=$baseline['tables_priv_scan'];
    p2b_require(($scan['access']??null)==='HANDLER_TABLE_SCAN' && array_key_exists('index',$scan) && $scan['index']===null &&
        ($scan['eof']??null)===true && ($scan['counter_proof']??null)===true && isset($scan['rows']) && is_int($scan['rows']) && $scan['rows']>=0,
        'Original baseline lacks complete physical scan proof.');
    return $baseline;
}
function p3c_options(array $opt): array {
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
function p3c_main(): int {
    $out=null;$p=null;$report=['passed'=>false,'writes_performed'=>false,'working_database_accessed'=>false];
    try {
        p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'CLI 64-bit PHP required.');
        $opt=getopt('',['root:','pid:','output:','uploads:','mode:','baseline:']);$paths=p3c_options($opt);
        $root=$paths['root'];$out=$paths['output'];$report['mode']=$opt['mode'];
        $p=new PDO('mysql:host=127.0.0.1;port=3307;dbname=atikha_finance;charset=utf8mb4','root','',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>3]);
        $id=$p->query('SELECT @@port port,@@datadir datadir,@@pid_file pid_file,VERSION() version,@@global.read_only ro,@@global.innodb_force_recovery recovery,@@global.event_scheduler events,@@global.log_bin binlog')->fetch();
        p2b_require($id['port']==='3307' && p2b_path($id['datadir'])===p2b_path($root.'/data') && p2b_path($id['pid_file'])===p2b_path($root.'/candidate.pid') &&
            trim(file_get_contents($id['pid_file']))===$opt['pid'] && str_starts_with($id['version'],'10.4.32-MariaDB') &&
            $id['ro']==='1' && $id['recovery']==='0' && $id['events']==='OFF' && $id['binlog']==='0','Authenticated isolated identity failed.');
        $p->exec("SET SESSION time_zone='+00:00', max_statement_time=20");
        $scanNumber=0;
        $scanSave=function(string $stage,array $value)use($root,&$scanNumber):void{
            if($stage==='before')$scanNumber++;
            p2b_require((bool)preg_match('/\A(?:before|complete|page-[0-9]{4})\z/D',$stage),'Unsafe scan evidence label.');
            p2b_json_write($root.'/logs/physical-scan-'.sprintf('%02d',$scanNumber).'-'.$stage.'.json',$value);
        };
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$first=p3c_capture($p,$scanSave);$p->rollBack();
        // Preserve the baseline immediately, even if a later read/check fails.
        p2b_json_write($root.'/logs/baseline-first.json',$first);
        if($opt['mode']==='capture'){
            $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$second=p3c_capture($p,$scanSave);$p->rollBack();p2b_compare($first,$second);
            $report+=['baseline'=>$first,'stable_baseline'=>true,'no_table_checks'=>true];
        }else{
            $baselineReport=json_decode(file_get_contents($paths['baseline']),true,512,JSON_THROW_ON_ERROR);
            $baseline=p3c_report_baseline($baselineReport);p2b_compare($baseline,$first);
            $report['baseline_matched_before_checks']=true;
            $report['checked_objects']=p2b_table_checks($p,$root.'/logs/table-checks.jsonl');
            $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();
            $report['foreign_keys']=p2b_orphans($p);
            define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/../includes/journal_corrections.php';
            p2b_require(stage1_schema($p) && stage2_schema($p) && stage3_schema($p),'Complete 019/020/021 schema required; no migration allowed.');
            $report['integrity_problems']=correction_integrity($p);p2b_require($report['integrity_problems']===[],'Accounting/correction/advance/evidence integrity needs review.');
            $report['evidence']=p2b_evidence($p,$opt['uploads']);
            $after=p3c_capture($p,$scanSave);p2b_compare($first,$after);p2b_compare($baseline,$after);$p->rollBack();
            $report+=['snapshot'=>$after['snapshot'],'tables_priv_scan'=>$after['tables_priv_scan'],'stable_before_after_checks'=>true,'baseline_matched_after_checks'=>true];
        }
        $report['passed']=true;p2b_json_write($out,$report);echo "PHASE3A_ISOLATED_PROBE_PASSED\n";return 0;
    }catch(Throwable $e){
        if($p instanceof PDO && $p->inTransaction())$p->rollBack();$report['failure']=$e->getMessage();
        if(is_string($out) && !file_exists($out))p2b_json_write($out,$report);
        fwrite(STDERR,"Phase 3-A probe stopped; see private evidence.\n");return 1;
    }
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(p3c_main());
