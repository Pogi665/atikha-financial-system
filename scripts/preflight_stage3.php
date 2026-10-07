<?php
/** Read-only: never loads working config, migrates, repairs data or deletes evidence. */
require_once __DIR__.'/cli_common.php';
define('ATIKHA_ISOLATED_TEST',true);
require_once __DIR__.'/../includes/journal_corrections.php';
$opt=getopt('',['database:']);$db=$opt['database']??'';
try{
    cli_require(is_string($db)&&$db!=='','Specify --database explicitly.');$p=cli_db($db);
    $p->exec('SET TRANSACTION READ ONLY');
    $report=accounting_read($p,function()use($p){
        $problems=[];$s1=stage1_schema($p);$s2=stage2_schema($p);$state=stage3_schema_state($p);
        if(!$s1)$problems[]='Complete migration 019 is required; never rerun 019 or 015.';
        if(!$s2)$problems[]='Complete migration 020 is required; inspect partial deployment rather than rerunning.';
        $tables=['journal_entries','journal_entry_lines','Categories','Users','user_identities','Receipts','receipt_ocr_attempts','audit_logs','projects','parties','journal_drafts','draft_evidence_reservations','advance_control_designations','posted_evidence_associations','evidence_allocations','accounting_write_state'];
        $s=$p->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')');$s->execute($tables);$found=$s->fetchAll(PDO::FETCH_KEY_PAIR);
        $found=array_change_key_case($found,CASE_LOWER);
        foreach($tables as $table)if(($found[strtolower($table)]??null)!=='InnoDB')$problems[]='Missing or non-InnoDB prerequisite '.$table;
        $triggers=$p->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('s1_evidence_no_update','s1_evidence_no_delete','s1_allocation_no_update','s1_allocation_no_delete')")->fetchAll(PDO::FETCH_COLUMN);
        if(count($triggers)!==4)$problems[]='Stage 1 immutable evidence protections are incomplete.';
        if($state['state']==='partial')$problems=array_merge($problems,['Partial/incompatible 021 detected. Stop and inspect/restore; never rerun blindly.'],$state['problems']);
        if(!$problems)$problems=array_merge($problems,correction_integrity($p));
        $preserved=[];
        if($s1&&$s2&&count($found)===count($tables))foreach(['journal_entries','journal_entry_lines','Receipts','posted_evidence_associations','evidence_allocations','journal_drafts','cash_advances','cash_advance_operations','cash_advance_due_changes','audit_logs'] as $table){
            $keys=['Receipts'=>'ReceiptID','audit_logs'=>'id','evidence_allocations'=>'association_id,line_id'];
            $s=$p->query('SELECT * FROM '.$table.' ORDER BY '.($keys[$table]??'id'));$hash=hash_init('sha256');$count=0;
            while($row=$s->fetch(PDO::FETCH_ASSOC)){
                // Schema-only additions do not change preservation hashes of original fields.
                if($table==='journal_drafts')unset($row['correction_target_journal_id'],$row['correction_mode']);
                if($table==='posted_evidence_associations')unset($row['source_association_id'],$row['correction_id']);
                hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;
            }
            $preserved[$table]=['rows'=>$count,'sha256'=>hash_final($hash)];
        }
        return ['deployment_state'=>$state['state']==='complete'?'021 already applied':($state['state']==='partial'?'Partial 021':'021 not applied'),
            'schema_complete'=>$state['complete'],'schema_ready_for_021'=>$state['state']==='absent'&&!$problems,
            'problems'=>array_values(array_unique($problems)),'preservation_manifest'=>$preserved];
    });
    foreach(['mbstring','pdo_mysql','fileinfo','gd'] as $ext)if(!extension_loaded($ext))$report['problems'][]='Missing PHP extension '.$ext;
    if(PHP_INT_SIZE!==8||version_compare(PHP_VERSION,'8.1','<'))$report['problems'][]='64-bit PHP 8.1 or later is required.';
    $protection=__DIR__.'/../uploads/receipts/.htaccess';if(!is_file($protection)||!str_contains(file_get_contents($protection),'Require all denied'))$report['problems'][]='Protected receipt storage rule is missing.';
    $report['schema_ready_for_021']=$report['schema_ready_for_021']&&!$report['problems'];
    echo workspace_json(['database'=>$db,'verified_database'=>$p->query('SELECT DATABASE()')->fetchColumn(),'server_version'=>$p->query('SELECT VERSION()')->fetchColumn()]+$report+
        ['checks_passed'=>!$report['problems'],'writes_performed'=>false,'file_bytes_verified'=>false,
        'next_step'=>$report['schema_complete']?'Do not rerun 021. Follow docs/stage3-delivery.md for separately authorized activation and pending working acceptance; a complete schema is not deployment authorization.':'Back up current database/application/protected files and rehearse on a copy before separately authorized migration. Never rerun 015, 019 or 020.'])."\n";
    exit($report['problems']?1:0);
}catch(Throwable $e){fwrite(STDERR,'Stage 3 preflight failed: '.$e->getMessage()."\n");exit(1);}
