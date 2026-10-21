<?php
/** Working recovery verification only. No accounting writes, repair, import or HTTP. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3b_complete_probe.php';

function p4_spec(string $path,string $hash):array {
    $expected=__DIR__.'/../.migration-private/phase4-cutover-inputs.json';
    p2b_require(realpath($path)!==false && realpath($path)===realpath($expected) && preg_match('/\A[A-F0-9]{64}\z/D',$hash)===1 && hash_equals($hash,strtoupper(hash_file('sha256',$path))),'Pinned cutover manifest required.');
    $s=json_decode(file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
    p2b_require(($s['Format']??'')==='phase4-cutover-v1' && count($s['Recoveries']??[])===3 && count($s['ReceiptMetadata']??[])===4,'Incomplete cutover specification.');
    p2b_require(p2b_path($s['WorkingData'])==='c:/xampp/mysql/data' && p2b_path($s['WorkingUploads'])===p2b_path(dirname(__DIR__).'/uploads'),'Working paths differ.');
    p3d_mapping($s['ReceiptMetadata'],$s['Recoveries']);
    p3d_mapping($s['ReceiptMetadata'],$s['Recoveries']);
    return $s;
}
function p4_role(string $root,string $output):void {
    p2b_require(preg_match('~\Ac:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/phase4-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\z~D',p2b_path($root))===1,'Wrong cutover evidence role.');
    p2b_require(realpath(dirname($output))!==false && p2b_path(realpath(dirname($output)))===p2b_path($root.'/logs') && in_array(basename($output),['filename-recovery.json','inspection.json'],true) && !file_exists($output),'Fresh output in cutover logs required.');
}
function p4_identity(array $id,array $spec,string $root,string $pid):void {
    p2b_require(ctype_digit($pid) && (int)$pid>0 && $id['port']==='3306' && p2b_path($id['datadir'])===p2b_path($spec['WorkingData']) && p2b_path($id['pid_file'])===p2b_path($root.'/candidate.pid') && trim(file_get_contents($id['pid_file']))===$pid && str_starts_with($id['version'],'10.4.32-MariaDB') && $id['ro']==='1' && $id['recovery']==='0' && $id['events']==='OFF' && $id['binlog']==='0','Authenticated working recovery identity failed.');
}
function p4_main():int {
    $out=null;$p=null;$r=['passed'=>false,'working_database_accessed'=>false,'writes_performed'=>false];
    try {
        p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'64-bit CLI required.');
        $o=getopt('',['recover-files','root:','pid:','output:','manifest:','manifest-sha:']);
        $s=p4_spec($o['manifest']??'',$o['manifest-sha']??'');
        $root=realpath($o['root']??'');p2b_require($root!==false,'Missing cutover root.');
        p4_role($root,$o['output']??'');$out=$o['output'];
        if(array_key_exists('recover-files',$o)) {
            p2b_require(basename($out)==='filename-recovery.json','Wrong recovery output.');
            $files=p3d_copy_verified(p3d_recovery_plan($s['OriginalUploads'],$s['WorkingUploads'],$s['Recoveries']));
            $r['passed']=true;p2b_json_write($out,$r+['recovered_count'=>count($files),'files'=>$files,'file_recovery_passed'=>true]);
            echo "WORKING_RECEIPT_FILENAMES_RECOVERED\n";return 0;
        }
        p2b_require(basename($out)==='inspection.json' && is_string($o['pid']??null),'Inspection arguments missing.');
        p2b_require(hash_equals($s['BaselineHash'],strtoupper(hash_file('sha256',$s['Baseline']))),'Baseline drift.');
        $p=new PDO('mysql:host=127.0.0.1;port=3306;dbname=atikha_finance;charset=utf8mb4','root','',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>3]);
        $id=$p->query('SELECT @@port port,@@datadir datadir,@@pid_file pid_file,VERSION() version,@@global.read_only ro,@@global.innodb_force_recovery recovery,@@global.event_scheduler events,@@global.log_bin binlog')->fetch();
        p4_identity($id,$s,$root,$o['pid']);$r['working_database_accessed']=true;
        $p->exec("SET SESSION time_zone='+00:00', max_statement_time=20");
        $n=0;$save=function(string $stage,array $value)use($root,&$n):void {
            if($stage==='before')$n++;
            p2b_require(preg_match('/\A(?:before|complete|page-[0-9]{4})\z/D',$stage)===1,'Bad scan label.');
            p2b_json_write($root.'/logs/physical-scan-'.sprintf('%02d',$n).'-'.$stage.'.json',$value);
        };
        $base=p3c_report_baseline(json_decode(file_get_contents($s['Baseline']),true,512,JSON_THROW_ON_ERROR));
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();p3d_metadata($p,$s['ReceiptMetadata']);
        $first=p3c_capture($p,$save);p2b_compare($base,$first);$p->rollBack();
        p2b_json_write($root.'/logs/baseline-first.json',$first);
        $r['checked_objects']=p2b_table_checks($p,$root.'/logs/table-checks.jsonl');
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$r['foreign_keys']=p2b_orphans($p);
        require_once __DIR__.'/../includes/journal_corrections.php';
        p2b_require(stage1_schema($p) && stage2_schema($p) && stage3_schema($p),'Existing schema required; no migration.');
        $r['integrity_problems']=correction_integrity($p);p2b_require($r['integrity_problems']===[],'Accounting integrity needs review.');
        $r['evidence']=p2b_evidence($p,$s['WorkingUploads']);p3d_metadata($p,$s['ReceiptMetadata']);
        $last=p3c_capture($p,$save);p2b_compare($first,$last);p2b_compare($base,$last);$p->rollBack();
        $prior=json_decode(file_get_contents($s['ValidationReport']),true,512,JSON_THROW_ON_ERROR);
        p2b_compare($prior['evidence'],$r['evidence']);
        $r+=['snapshot'=>$last['snapshot'],'tables_priv_scan'=>$last['tables_priv_scan'],'baseline_matches_original_before_after'=>true,'evidence_matches_rehearsal'=>true];
        $r['passed']=true;p2b_json_write($out,$r);echo "WORKING_RECOVERY_INSPECTION_PASSED\n";return 0;
    } catch(Throwable $e) {
        if($p instanceof PDO && $p->inTransaction())$p->rollBack();$r['failure']=$e->getMessage();
        if(is_string($out) && !file_exists($out))p2b_json_write($out,$r);
        fwrite(STDERR,"Working recovery probe stopped; inspect private evidence.\n");return 1;
    }
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(p4_main());
