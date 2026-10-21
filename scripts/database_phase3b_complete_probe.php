<?php
/** Continuation only: recover proven bytes in fresh rehearsal storage; inspect fresh DB copies. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_corrected_probe.php';

function p3d_spec(string $path,string $hash):array {
    $private=realpath(__DIR__.'/../.migration-private');$file=realpath($path);
    p2b_require($private!==false && $file!==false && p2b_path($file)===p2b_path($private.'/phase3b-completion-inputs.json'),'Pinned private completion manifest required.');
    p2b_require(preg_match('/\A[A-F0-9]{64}\z/D',$hash)===1 && hash_equals($hash,strtoupper(hash_file('sha256',$file))),'Completion manifest hash differs.');
    $spec=json_decode(file_get_contents($file),true,128,JSON_THROW_ON_ERROR);
    p2b_require(($spec['Format']??'')==='phase3b-completion-inputs-v1' && count($spec['Protected']??[])===19 && count($spec['Recoveries']??[])===3 && count($spec['ReceiptMetadata']??[])===4,'Incomplete completion specification.');
    p2b_require(p2b_path($spec['OriginalUploads'])===p2b_path('C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/preserved/storage/uploads'),'Unexpected original uploads.');
    p3d_mapping($spec['ReceiptMetadata'],$spec['Recoveries']);
    return $spec;
}
function p3d_mapping(array $metadata,array $recoveries):void {
    $rows=[];
    foreach($metadata as $row){
        p2b_require(is_string($row['ReceiptID']??null) && ctype_digit($row['ReceiptID']) && !isset($rows[$row['ReceiptID']]),'Duplicate/invalid pinned receipt metadata.');
        $rows[$row['ReceiptID']]=$row;
    }
    $seen=[];
    foreach($recoveries as $item){
        $id=$item['ReceiptID']??'';p2b_require(isset($rows[$id]) && !isset($seen[$id]),'Recovery identity differs from pinned receipts.');$seen[$id]=true;$row=$rows[$id];
        $hashes=array_values(array_filter([$row['File_SHA256'],$row['Posted_File_SHA256']],fn($v)=>$v!==null));
        p2b_require($item['PublicPath']===$row['File_Path'] && $item['Bytes']===$row['File_Size'] && $item['Mime']===$row['Mime_Type'] && $item['Hashes']===$hashes,'Recovery specification differs from original receipt metadata.');
    }
}
function p3d_relative(string $public):string {
    p2b_require(preg_match('~\Auploads/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_. ()-]+\z~D',$public)===1 && !in_array('..',explode('/',$public),true),'Unsafe recovery filename.');
    return substr($public,8);
}
function p3d_verify_file(string $file,array $spec):void {
    p2b_require(is_file($file) && !is_link($file) && count($spec['Hashes']??[])>0,'Ordinary source with recorded hashes required.');
    $hash=hash_file('sha256',$file);
    foreach($spec['Hashes'] as $wanted)p2b_require(is_string($wanted) && preg_match('/\A[a-fA-F0-9]{64}\z/D',$wanted)===1 && hash_equals(strtolower($wanted),$hash),'Recovered receipt differs from a recorded hash.');
    p2b_require(is_string($spec['Bytes']) && ctype_digit($spec['Bytes']) && (string)filesize($file)===$spec['Bytes'],'Recovered receipt size differs.');
    p2b_require(is_string($spec['Mime']) && $spec['Mime']!=='' && (new finfo(FILEINFO_MIME_TYPE))->file($file)===$spec['Mime'],'Recovered receipt MIME differs.');
}
function p3d_recovery_plan(string $original,string $rehearsal,array $recoveries):array {
    $source=realpath($original);$dest=realpath($rehearsal);
    p2b_require($source!==false && $dest!==false && !is_link($original) && !is_link($rehearsal) && p2b_path($source)!==p2b_path($dest) &&
        !str_starts_with(p2b_path($dest),p2b_path($source).'/') && !str_starts_with(p2b_path($source),p2b_path($dest).'/'),'Upload roots overlap or are missing.');
    p2b_require(count($recoveries)===3,'Exactly three approved recoveries required.');
    $plan=[];$targets=[];$ids=[];
    foreach($recoveries as $spec){
        p2b_require(is_string($spec['ReceiptID']??null) && ctype_digit($spec['ReceiptID']) && (int)$spec['ReceiptID']>0 && !isset($ids[$spec['ReceiptID']]),'Invalid/duplicate receipt identity.');
        $ids[$spec['ReceiptID']]=true;$relative=p3d_relative($spec['PublicPath']);$key=strtolower($relative);
        p2b_require(!isset($targets[$key]),'Duplicate recovery destination.');$targets[$key]=true;
        p2b_require(!file_exists($source.'/'.$relative) && !is_link($source.'/'.$relative),'Original destination is no longer missing.');
        $candidate=p2b_evidence_path($source,'uploads/'.$spec['SourceRelative']);p3d_verify_file($candidate,$spec);
        $target=$dest.'/'.$relative;$parent=realpath(dirname($target));
        p2b_require($parent!==false && str_starts_with(p2b_path($parent).'/',p2b_path($dest).'/') && !file_exists($target) && !is_link($target),'Destination exists or escapes rehearsal storage.');
        $plan[]=['spec'=>$spec,'source'=>$candidate,'target'=>$target];
    }
    return $plan;
}
function p3d_copy_verified(array $plan):array {
    $files=[];
    foreach($plan as $item){
        p3d_verify_file($item['source'],$item['spec']);
        $in=fopen($item['source'],'rb');p2b_require($in!==false,'Cannot open verified source.');
        try{
            $out=fopen($item['target'],'xb');p2b_require($out!==false,'Cannot create new recovery destination; no overwrite.');
            try{$bytes=stream_copy_to_stream($in,$out);p2b_require($bytes===(int)$item['spec']['Bytes'] && fflush($out),'Incomplete recovery copy.');}
            finally{fclose($out);}
        }finally{fclose($in);}
        p3d_verify_file($item['source'],$item['spec']);p3d_verify_file($item['target'],$item['spec']);
        $files[]=['receipt_id'=>$item['spec']['ReceiptID'],'sha256'=>hash_file('sha256',$item['target']),'bytes'=>filesize($item['target'])];
    }
    return $files;
}
function p3d_metadata(PDO $p,array $expected):void {
    $actual=$p->query('SELECT ReceiptID,File_Path,File_Size,Mime_Type,File_SHA256,Posted_File_SHA256 FROM receipts ORDER BY ReceiptID')->fetchAll(PDO::FETCH_ASSOC);
    p2b_compare($expected,$actual);
}
function p3d_role(string $root,bool $prepare):string {
    $base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
    $pattern='~\A'.preg_quote(strtolower($base),'~').'phase2b-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}'.($prepare?'':'/(validation|restore)').'\z~D';
    p2b_require(preg_match($pattern,p2b_path($root))===1,'Wrong continuation role.');
    return $prepare?$root:dirname($root);
}
function p3d_paths(array $opt,array $spec):array {
    foreach(['root','output','uploads'] as $key)p2b_require(is_string($opt[$key]??null),'Explicit continuation arguments required.');
    $root=realpath($opt['root']);p2b_require($root!==false,'Missing completion root.');
    $prepare=array_key_exists('prepare-files',$opt);
    $attempt=p3d_role($root,$prepare);$uploads=realpath($opt['uploads']);
    p2b_require($uploads!==false && p2b_path($uploads)===p2b_path($attempt.'/rehearsal/uploads'),'Only this fresh rehearsal upload role is allowed.');
    $output=$opt['output'];$name=$prepare?'filename-recovery.json':'inspection.json';
    p2b_require(basename($output)===$name && p2b_path((string)realpath(dirname($output)))===p2b_path($root.'/logs'),'Output outside completion logs.');
    if(!$prepare){
        p2b_require(is_string($opt['pid']??null) && ctype_digit($opt['pid']) && (int)$opt['pid']>0,'Explicit candidate PID required.');
        p2b_require(is_string($opt['baseline']??null) && p2b_path((string)realpath($opt['baseline']))===p2b_path($spec['Baseline']) && hash_equals($spec['BaselineHash'],strtoupper(hash_file('sha256',$opt['baseline']))),'Original full baseline must match its pin.');
    }
    return ['root'=>$root,'uploads'=>$uploads,'output'=>$output,'prepare'=>$prepare];
}
function p3d_main():int {
    $out=null;$p=null;$report=['passed'=>false,'working_database_accessed'=>false];
    try{
        p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'64-bit CLI PHP required.');
        $opt=getopt('',['prepare-files','root:','output:','uploads:','pid:','baseline:','manifest:','manifest-sha:']);
        $spec=p3d_spec($opt['manifest']??'',$opt['manifest-sha']??'');$paths=p3d_paths($opt,$spec);
        $root=$paths['root'];$out=$paths['output'];
        if($paths['prepare']){
            $plan=p3d_recovery_plan($spec['OriginalUploads'],$paths['uploads'],$spec['Recoveries']);
            $files=p3d_copy_verified($plan);
            $report['passed']=true;
            p2b_json_write($out,$report+['sql_executed'=>false,'files'=>$files,'recovered_count'=>count($files),'passed_recovery'=>true]);
            echo "REHEARSAL_FILENAMES_RECOVERED\n";return 0;
        }
        $report+=['writes_performed'=>false,'mode'=>'inspect'];
        $p=new PDO('mysql:host=127.0.0.1;port=3307;dbname=atikha_finance;charset=utf8mb4','root','',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>3]);
        $id=$p->query('SELECT @@port port,@@datadir datadir,@@pid_file pid_file,VERSION() version,@@global.read_only ro,@@global.innodb_force_recovery recovery,@@global.event_scheduler events,@@global.log_bin binlog')->fetch();
        p2b_require($id['port']==='3307' && p2b_path($id['datadir'])===p2b_path($root.'/data') && p2b_path($id['pid_file'])===p2b_path($root.'/candidate.pid') &&
            trim(file_get_contents($id['pid_file']))===$opt['pid'] && str_starts_with($id['version'],'10.4.32-MariaDB') && $id['ro']==='1' && $id['recovery']==='0' && $id['events']==='OFF' && $id['binlog']==='0','Authenticated completion identity failed.');
        $p->exec("SET SESSION time_zone='+00:00', max_statement_time=20");
        $scanNumber=0;$save=function(string $stage,array $value)use($root,&$scanNumber):void{
            if($stage==='before')$scanNumber++;
            p2b_require(preg_match('/\A(?:before|complete|page-[0-9]{4})\z/D',$stage)===1,'Unsafe scan label.');
            p2b_json_write($root.'/logs/physical-scan-'.sprintf('%02d',$scanNumber).'-'.$stage.'.json',$value);
        };
        $baseline=p3c_report_baseline(json_decode(file_get_contents($spec['Baseline']),true,512,JSON_THROW_ON_ERROR));
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();
        p3d_metadata($p,$spec['ReceiptMetadata']);$first=p3c_capture($p,$save);p2b_compare($baseline,$first);$p->rollBack();
        p2b_json_write($root.'/logs/baseline-first.json',$first);
        $report+=['baseline_matched_before_checks'=>true,'receipt_metadata_matches_pinned_export'=>true];
        $report['checked_objects']=p2b_table_checks($p,$root.'/logs/table-checks.jsonl');
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$report['foreign_keys']=p2b_orphans($p);
        define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/../includes/journal_corrections.php';
        p2b_require(stage1_schema($p) && stage2_schema($p) && stage3_schema($p),'Full existing schema required; no migration.');
        $report['integrity_problems']=correction_integrity($p);p2b_require($report['integrity_problems']===[],'Accounting integrity needs review.');
        $report['evidence']=p2b_evidence($p,$paths['uploads']);p3d_metadata($p,$spec['ReceiptMetadata']);
        $after=p3c_capture($p,$save);p2b_compare($first,$after);p2b_compare($baseline,$after);$p->rollBack();
        $report+=['snapshot'=>$after['snapshot'],'tables_priv_scan'=>$after['tables_priv_scan'],'stable_before_after_checks'=>true,'baseline_matched_after_checks'=>true];
        $report['passed']=true;p2b_json_write($out,$report);echo "PHASE3B_COMPLETION_INSPECTION_PASSED\n";return 0;
    }catch(Throwable $e){
        if($p instanceof PDO && $p->inTransaction())$p->rollBack();$report['failure']=$e->getMessage();
        if(is_string($out) && !file_exists($out))p2b_json_write($out,$report);
        fwrite(STDERR,"Phase 3-B completion stopped; retain private evidence.\n");return 1;
    }
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(p3d_main());
