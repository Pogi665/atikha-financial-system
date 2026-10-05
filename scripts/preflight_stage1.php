<?php
/** Read-only deployment preflight. Never executes DDL, updates or file deletions. */
require_once __DIR__.'/cli_common.php';
$o=getopt('',['database:']);$db=$o['database']??'';
try{
    cli_require(is_string($db)&&$db!=='','Specify --database explicitly.');$p=cli_db($db);$problems=[];
    $needed=['journal_entries'=>['submission_key','submission_hash','posted_by_user_id'],
        'Receipts'=>['JournalEntryID','File_SHA256','Posted_File_SHA256','Upload_Key']];
    foreach($needed as $table=>$cols){foreach($cols as $column){$s=$p->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);if(!(int)$s->fetchColumn())$problems[]="Missing prerequisite $table.$column";}}
    $s=$p->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('journal_entries','journal_entry_lines','Categories','Users','user_identities','Receipts','receipt_ocr_attempts','audit_logs')");
    $engines=$s->fetchAll();if(count($engines)!==8)$problems[]='Prerequisite tables are missing.';
    foreach($engines as $t){if($t['ENGINE']!=='InnoDB')$problems[]=$t['TABLE_NAME'].' must use InnoDB.';}
    $new=$p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('projects','parties','journal_drafts','draft_evidence_reservations','advance_control_designations','posted_evidence_associations','evidence_allocations','accounting_write_state')")->fetchAll(PDO::FETCH_COLUMN);
    if($new)$problems[]='019 tables already exist. Do not rerun; inspect the existing deployment or restore after a partial migration.';
    $s=$p->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='journal_entries' AND COLUMN_NAME IN ('source_book','transaction_kind','party_id','party_snapshot')) OR (TABLE_NAME='journal_entry_lines' AND COLUMN_NAME IN ('project_code_snapshot','project_name_snapshot')))");
    if($s->fetchAll())$problems[]='019 columns already exist. Do not rerun 019.';
    if(!$problems){
        if((int)$p->query('SELECT COUNT(*) FROM journal_entry_lines WHERE fund_project_id IS NOT NULL')->fetchColumn())$problems[]='Existing project placeholders need a reviewed migration mapping. No automatic mapping is allowed.';
        if((int)$p->query("SELECT COUNT(*) FROM journal_entries WHERE status='draft'")->fetchColumn())$problems[]='Existing journal drafts need review before cutover.';
        if((int)$p->query('SELECT COUNT(*) FROM Receipts WHERE JournalEntryID IS NOT NULL AND (File_SHA256 IS NULL OR Posted_File_SHA256 IS NULL OR File_SHA256<>Posted_File_SHA256)')->fetchColumn())$problems[]='Posted receipt hashes need investigation.';
        $bad=$p->query("SELECT j.id FROM journal_entries j LEFT JOIN journal_entry_lines l ON l.journal_entry_id=j.id WHERE j.status='posted' GROUP BY j.id HAVING COUNT(l.id)<2 OR SUM(l.debit_amount)<>SUM(l.credit_amount) OR SUM(l.debit_amount)<=0")->fetchAll(PDO::FETCH_COLUMN);
        if($bad)$problems[]='Existing journals need integrity review: '.implode(',',$bad);
    }
    foreach(['mbstring','pdo_mysql','fileinfo','gd'] as $extension){if(!extension_loaded($extension))$problems[]='Missing PHP extension: '.$extension;}
    if(PHP_INT_SIZE!==8||version_compare(PHP_VERSION,'8.1','<'))$problems[]='Stage 1 requires 64-bit PHP 8.1 or later.';
    $protection=__DIR__.'/../uploads/receipts/.htaccess';if(!is_file($protection)||!str_contains(file_get_contents($protection),'Require all denied'))$problems[]='Receipt storage protection file is missing.';
    echo json_encode(['database'=>$db,'server_version'=>$p->query('SELECT VERSION()')->fetchColumn(),'schema_ready_for_019'=>!$problems,
        'problems'=>$problems,'writes_performed'=>false,'next_step'=>'Backup database and protected files; rehearse on a disposable copy; obtain separate migration/activation authorization. Never rerun 015.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    exit($problems?1:0);
}catch(Throwable $e){fwrite(STDERR,'Preflight failed: '.$e->getMessage()."\n");exit(1);}
