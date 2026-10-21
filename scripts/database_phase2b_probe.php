<?php
/** Recovery-copy inspection only. No working config, sessions, external calls or SQL writers. */
declare(strict_types=1);

function p2b_require(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function p2b_ident(string $s): string {
    p2b_require((bool)preg_match('/\A[A-Za-z0-9_]+\z/D', $s), 'Unsupported SQL identifier.');
    return '`'.$s.'`';
}
function p2b_path(string $s): string { return strtolower(rtrim(str_replace('\\','/',$s),'/')); }
function p2b_row_hash(array $row): string {
    $h=hash_init('sha256');
    foreach ($row as $value) {
        p2b_require($value===null || is_scalar($value), 'Unsupported value in row fingerprint.');
        hash_update($h,$value===null ? "N;" : 'S'.strlen((string)$value).':'.(string)$value.';');
    }
    return hash_final($h);
}
function p2b_multiset(array $hashes): string {
    sort($hashes,SORT_STRING);
    return hash('sha256',implode("\n",$hashes)."\n");
}
function p2b_json_write(string $path,array $data): void {
    $h=@fopen($path,'xb'); p2b_require($h!==false,'Evidence destination already exists or cannot be created.');
    try {
        $text=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        p2b_require(fwrite($h,$text)===strlen($text),'Incomplete evidence write.');
        fflush($h);
    } finally { fclose($h); }
}
function p2b_snapshot(PDO $p): array {
    $tables=[];
    foreach($p->query("SELECT TABLE_NAME,TABLE_TYPE,COALESCE(ENGINE,'VIEW') engine FROM information_schema.TABLES WHERE TABLE_SCHEMA='atikha_finance' ORDER BY TABLE_NAME")->fetchAll() as $t){
        $name=$t['TABLE_NAME'];$id=p2b_ident($name);
        $definition=$p->query('SHOW CREATE '.($t['TABLE_TYPE']==='VIEW'?'VIEW':'TABLE').' atikha_finance.'.$id)->fetch(PDO::FETCH_NUM);
        $rows=[];$count=0;
        // Multiset fingerprint includes every column, null and binary value, duplicate multiplicity.
        // This is independent of physical/order/primary-key layout and never publishes raw credentials.
        $q=$p->query('SELECT * FROM atikha_finance.'.$id);
        while($r=$q->fetch(PDO::FETCH_NUM)){$rows[]=p2b_row_hash($r);$count++;}
        $q->closeCursor();
        $tables[$name]=['type'=>$t['TABLE_TYPE'],'engine'=>$t['engine'],'schema'=>$definition[1],
            'columns'=>$p->query('SHOW FULL COLUMNS FROM atikha_finance.'.$id)->fetchAll(PDO::FETCH_ASSOC),
            'rows'=>$count,'sha256'=>p2b_multiset($rows)];
    }
    p2b_require(count($tables)>0,'No application tables found.');
    $objects=[];
    foreach([
        'triggers'=>"SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_ORDER,ACTION_STATEMENT,SQL_MODE,DEFINER,CHARACTER_SET_CLIENT,COLLATION_CONNECTION,DATABASE_COLLATION FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='atikha_finance' ORDER BY TRIGGER_NAME",
        'routines'=>"SELECT ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_DEFINITION,DTD_IDENTIFIER,IS_DETERMINISTIC,SQL_DATA_ACCESS,SECURITY_TYPE,SQL_MODE,ROUTINE_COMMENT,DEFINER,CHARACTER_SET_CLIENT,COLLATION_CONNECTION,DATABASE_COLLATION FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='atikha_finance' ORDER BY ROUTINE_NAME",
        'parameters'=>"SELECT SPECIFIC_NAME,ORDINAL_POSITION,PARAMETER_MODE,PARAMETER_NAME,DTD_IDENTIFIER FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA='atikha_finance' ORDER BY SPECIFIC_NAME,ORDINAL_POSITION",
        'events'=>"SELECT EVENT_NAME,DEFINER,TIME_ZONE,EVENT_DEFINITION,EVENT_TYPE,EXECUTE_AT,INTERVAL_VALUE,INTERVAL_FIELD,SQL_MODE,STARTS,ENDS,STATUS,ON_COMPLETION,EVENT_COMMENT,CHARACTER_SET_CLIENT,COLLATION_CONNECTION,DATABASE_COLLATION FROM information_schema.EVENTS WHERE EVENT_SCHEMA='atikha_finance' ORDER BY EVENT_NAME",
        'database'=>"SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='atikha_finance'"
    ] as $kind=>$sql) $objects[$kind]=$p->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    // Account/grant data stays hashed, even in the private report. No mutations or grants bypass.
    $permissions=[];
    foreach(['global_priv','db','tables_priv','columns_priv','procs_priv','proxies_priv','roles_mapping','user'] as $name){
        $rows=[];$q=$p->query('SELECT * FROM mysql.'.p2b_ident($name));
        while($r=$q->fetch(PDO::FETCH_NUM)) $rows[]=p2b_row_hash($r);
        $q->closeCursor();$permissions[$name]=['rows'=>count($rows),'sha256'=>p2b_multiset($rows)];
    }
    $grants=$p->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
    $permissions['current_connection_grants']=p2b_multiset(array_map(fn($v)=>hash('sha256',$v),$grants));
    return ['tables'=>$tables,'objects'=>$objects,'permissions'=>$permissions];
}
function p2b_check_rows(array $rows): void {
    p2b_require(count($rows)>0,'CHECK TABLE returned no result.');
    foreach($rows as $r) p2b_require(($r['Msg_type']??'')==='status' && ($r['Msg_text']??'')==='OK','CHECK TABLE needs review; no repair attempted.');
}
function p2b_table_checks(PDO $p,string $log): int {
    $count=0;
    $objects=$p->query("SELECT TABLE_SCHEMA,TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA IN ('atikha_finance','mysql') ORDER BY TABLE_SCHEMA,TABLE_NAME")->fetchAll();
    p2b_require(count($objects)>0,'Empty table-check inventory.');
    $f=fopen($log,'xb');p2b_require($f!==false,'Check evidence exists or cannot be created.');
    try {
        foreach($objects as $t){
            $q=p2b_ident($t['TABLE_SCHEMA']).'.'.p2b_ident($t['TABLE_NAME']);
            // Serial standard checks, no EXTENDED, repair, analyze or optimize.
            $rows=$p->query('CHECK TABLE '.$q)->fetchAll(PDO::FETCH_ASSOC);
            fwrite($f,json_encode(['object'=>$q,'result'=>$rows],JSON_THROW_ON_ERROR)."\n");fflush($f);
            p2b_check_rows($rows);$count++;
        }
    } finally {fclose($f);}
    return $count;
}
function p2b_orphans(PDO $p): array {
    $keys=$p->query("SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='atikha_finance' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll();
    $groups=[];$out=[];
    foreach($keys as $k) $groups[$k['TABLE_NAME'].'.'.$k['CONSTRAINT_NAME']][]=$k;
    foreach($groups as $name=>$columns){
        $notNull=[];$matches=[];
        foreach($columns as $k){
            p2b_require($k['REFERENCED_TABLE_SCHEMA']==='atikha_finance','Cross-schema foreign key needs separate review.');
            $notNull[]='c.'.p2b_ident($k['COLUMN_NAME']).' IS NOT NULL';
            $matches[]='r.'.p2b_ident($k['REFERENCED_COLUMN_NAME']).'=c.'.p2b_ident($k['COLUMN_NAME']);
        }
        $first=$columns[0];
        $sql='SELECT COUNT(*) FROM atikha_finance.'.p2b_ident($first['TABLE_NAME']).' c WHERE '.implode(' AND ',$notNull).
            ' AND NOT EXISTS (SELECT 1 FROM atikha_finance.'.p2b_ident($first['REFERENCED_TABLE_NAME']).' r WHERE '.implode(' AND ',$matches).')';
        $n=(string)$p->query($sql)->fetchColumn();$out[$name]=$n;
        p2b_require($n==='0','Foreign-key orphan check failed: '.$name);
    }
    p2b_require(count($groups)>0,'No application foreign keys found.');
    return $out;
}
function p2b_evidence_path(string $root,string $public): string {
    p2b_require((bool)preg_match('~\Auploads/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_. ()-]+\z~D',$public),'Unsupported attachment path.');
    p2b_require(!in_array('..',explode('/',$public),true),'Attachment path traversal.');
    $base=realpath($root);$file=realpath($root.'/'.substr($public,8));
    p2b_require($base!==false && $file!==false && is_file($file) && str_starts_with(p2b_path($file),p2b_path($base).'/'),'Missing or escaping preserved attachment.');
    return $file;
}
function p2b_evidence(PDO $p,string $uploads): array {
    $files=[];$legacyHashes=0;
    foreach($p->query('SELECT ReceiptID,File_Path,File_Size,Mime_Type,File_SHA256,Posted_File_SHA256 FROM receipts')->fetchAll() as $r){
        $file=p2b_evidence_path($uploads,$r['File_Path']);$hash=hash_file('sha256',$file);
        foreach(['File_SHA256','Posted_File_SHA256'] as $key){
            if($r[$key]!==null) p2b_require(hash_equals(strtolower($r[$key]),$hash),'Receipt stored hash mismatch.');
        }
        if($r['File_SHA256']===null) $legacyHashes++;
        if($r['File_Size']!==null) p2b_require((string)filesize($file)===(string)$r['File_Size'],'Receipt size mismatch.');
        if($r['Mime_Type']!==null && $r['Mime_Type']!=='') {
            $finfo=new finfo(FILEINFO_MIME_TYPE);p2b_require($finfo->file($file)===$r['Mime_Type'],'Receipt MIME mismatch.');
        }
        $files['receipt:'.$r['ReceiptID']]=['sha256'=>$hash,'bytes'=>filesize($file)];
    }
    foreach(['board_communications'=>'File_Path','external_communications'=>'File_Path'] as $table=>$column){
        // Verify exact source schema; an unexpected legacy column is a stop, never silently skipped.
        $q=$p->query('SELECT '.p2b_ident($column).' FROM '.p2b_ident($table).' WHERE '.p2b_ident($column).' IS NOT NULL AND '.p2b_ident($column)."<>''");
        $i=0;
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $path){$file=p2b_evidence_path($uploads,$path);$files[$table.':'.++$i]=['sha256'=>hash_file('sha256',$file),'bytes'=>filesize($file)];}
    }
    ksort($files,SORT_STRING);
    return ['files'=>$files,'legacy_receipts_without_stored_hash'=>$legacyHashes,'bytes_changed'=>false];
}
function p2b_compare(array $a,array $b): void {
    p2b_require($a===$b,'Application schema/data/objects/permission fingerprints differ; stop for review.');
}
function p2b_main(array $args): int {
    $out=null;$report=['passed'=>false,'writes_performed'=>false];
    try {
        p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'CLI 64-bit PHP required.');
        $opt=getopt('',['root:','pid:','output:','uploads:']);
        foreach(['root','pid','output','uploads'] as $k) p2b_require(isset($opt[$k])&&is_string($opt[$k]),'Missing explicit isolated argument.');
        $root=realpath($opt['root']);$candidateOutput=$opt['output'];
        $parent='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
        p2b_require($root!==false && preg_match('~\A'.preg_quote(strtolower($parent),'~').'phase2b-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}/(?:validation|restore)\z~D',p2b_path($root))===1,'Not a fresh Phase 2-B candidate path.');
        p2b_require(basename($candidateOutput)==='inspection.json' && p2b_path(dirname($candidateOutput))===p2b_path($root.'/logs') && p2b_path((string)realpath(dirname($candidateOutput)))===p2b_path($root.'/logs'),'Output must stay in ordinary candidate logs.');
        $out=$candidateOutput;
        p2b_require(p2b_path($opt['uploads'])===p2b_path($parent.'preserved/storage/uploads'),'Unexpected evidence root.');
        p2b_require(ctype_digit($opt['pid']) && (int)$opt['pid']>0,'Invalid explicit candidate PID.');
        $p=new PDO('mysql:host=127.0.0.1;port=3307;dbname=atikha_finance;charset=utf8mb4','root','',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>3]);
        $identity=$p->query('SELECT @@port port,@@datadir datadir,@@pid_file pid_file,VERSION() version,@@global.read_only ro,@@global.innodb_force_recovery recovery,@@global.event_scheduler events,@@global.log_bin binlog')->fetch();
        p2b_require($identity['port']==='3307' && p2b_path($identity['datadir'])===p2b_path($root.'/data') &&
            p2b_path($identity['pid_file'])===p2b_path($root.'/candidate.pid') && trim(file_get_contents($identity['pid_file']))===$opt['pid'] &&
            str_starts_with($identity['version'],'10.4.32-MariaDB') && $identity['ro']==='1' && $identity['recovery']==='0' && $identity['events']==='OFF' && $identity['binlog']==='0','Authenticated isolated identity failed.');
        $p->exec("SET SESSION time_zone='+00:00', max_statement_time=20");
        $before=p2b_snapshot($p);
        $report['checked_objects']=p2b_table_checks($p,$root.'/logs/table-checks.jsonl');
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();
        try {
            $report['foreign_keys']=p2b_orphans($p);
            // The isolated constant suppresses working config loading; call read-only integrity readers only.
            define('ATIKHA_ISOLATED_TEST',true);
            require_once __DIR__.'/../includes/journal_corrections.php';
            p2b_require(stage1_schema($p) && stage2_schema($p) && stage3_schema($p),'Complete 019/020/021 schema required; never migrate in this helper.');
            $report['integrity_problems']=correction_integrity($p);
            p2b_require($report['integrity_problems']===[],'Accounting/correction/advance/evidence integrity needs review.');
            $report['evidence']=p2b_evidence($p,$opt['uploads']);
            $after=p2b_snapshot($p);p2b_compare($before,$after);
            $report['snapshot']=$after;$report['stable_before_after_checks']=true;
            $p->rollBack();
        } catch(Throwable $e) {if($p->inTransaction())$p->rollBack();throw $e;}
        $report['passed']=true;$report['working_database_accessed']=false;
        p2b_json_write($out,$report);echo "ISOLATED_INSPECTION_PASSED\n";return 0;
    } catch(Throwable $e){
        $report['failure']=$e->getMessage();
        if(is_string($out) && !file_exists($out)) p2b_json_write($out,$report);
        fwrite(STDERR,"Isolated inspection stopped; see private evidence.\n");return 1;
    }
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) exit(p2b_main($argv));
