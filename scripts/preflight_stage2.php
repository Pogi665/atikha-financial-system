<?php
/** Read only. No configuration loading, DDL, data repair, or receipt deletion. */
require_once __DIR__.'/cli_common.php';define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/../includes/cash_advance.php';
$opt=getopt('',['database:']);$db=$opt['database']??'';
try{
    cli_require(is_string($db)&&$db!=='','Specify --database explicitly.');$p=cli_db($db);$problems=[];
    $report=accounting_read($p,function()use($p,&$problems){
        if(!stage1_schema($p))$problems[]='Complete Stage 1 schema is required; do not rerun 019 or 015.';
        $tables=['journal_entries','journal_entry_lines','Categories','Users','user_identities','Receipts','receipt_ocr_attempts','audit_logs','projects','parties','journal_drafts','draft_evidence_reservations','advance_control_designations','posted_evidence_associations','evidence_allocations','accounting_write_state'];
        $s=$p->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')');$s->execute($tables);$found=$s->fetchAll();if(count($found)!==count($tables))$problems[]='Stage 1 prerequisite tables are missing.';foreach($found as $t)if($t['ENGINE']!=='InnoDB')$problems[]=$t['TABLE_NAME'].' must use InnoDB.';
        $n=(int)$p->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('s1_evidence_no_update','s1_evidence_no_delete','s1_allocation_no_update','s1_allocation_no_delete')")->fetchColumn();if($n!==4)$problems[]='Stage 1 immutable-evidence triggers are missing.';
        $partial=(int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('cash_advances','cash_advance_operations','cash_advance_due_changes')")->fetchColumn()+(int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_drafts' AND COLUMN_NAME IN ('workflow_kind','advance_id','return_confirmation')")->fetchColumn()+(int)$p->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 's2_%' AND TRIGGER_NAME NOT LIKE 's2_test_%'")->fetchColumn();$complete=stage2_schema($p);
        if($partial&&!$complete)$problems[]='Partial or incompatible 020 schema detected. Stop and inspect/recover; do not rerun migration blindly.';
        if(stage1_schema($p)){
            if(accounting_integrity($p,journal_today()))$problems[]='Posted journal integrity failed.';
            if((int)$p->query('SELECT COUNT(*) FROM Receipts WHERE JournalEntryID IS NOT NULL AND (File_SHA256 IS NULL OR Posted_File_SHA256 IS NULL OR File_SHA256<>Posted_File_SHA256)')->fetchColumn())$problems[]='Posted evidence hashes are inconsistent.';
            if((int)$p->query("SELECT COUNT(*) FROM draft_evidence_reservations v JOIN Receipts r ON r.ReceiptID=v.receipt_id JOIN journal_drafts d ON d.id=v.draft_id WHERE d.state<>'Draft' OR r.JournalEntryID IS NOT NULL OR r.UploadedBy_UserID<>d.owner_id OR r.OCR_Status='Discarded'")->fetchColumn())$problems[]='Draft evidence reservations are inconsistent.';
            if((int)$p->query("SELECT COUNT(*) FROM advance_control_designations d JOIN Categories c ON c.CategoryID=d.account_id WHERE c.Is_Active<>1 OR c.Account_Type<>'Asset' OR c.Normal_Balance<>'Debit' OR c.Is_Cash_Account<>0")->fetchColumn())$problems[]='Designated control accounts are ineligible.';
            if((int)$p->query('SELECT COUNT(*) FROM posted_evidence_associations e JOIN Receipts r ON r.ReceiptID=e.receipt_id WHERE r.JournalEntryID<>e.journal_id OR r.JournalEntryID IS NULL')->fetchColumn())$problems[]='Posted document/journal associations are inconsistent.';
            if((int)$p->query('SELECT COUNT(*) FROM evidence_allocations a JOIN posted_evidence_associations e ON e.id=a.association_id JOIN journal_entry_lines l ON l.id=a.line_id WHERE l.journal_entry_id<>e.journal_id')->fetchColumn())$problems[]='Evidence allocation journal links are inconsistent.';
            if($complete){$state=advance_state($p,journal_today());foreach($state['reconciliation'] as $r)if(!$r['ok'])$problems[]='Control account #'.$r['account_id'].' does not reconcile: '.implode('; ',$r['errors']);}
            elseif(!$partial&&(int)$p->query("SELECT COUNT(*) FROM journal_entry_lines l JOIN advance_control_designations d ON d.account_id=l.account_id JOIN journal_entries j ON j.id=l.journal_entry_id WHERE j.status='posted'")->fetchColumn())$problems[]='Pre-020 control lines require investigation; no historical advance inference is allowed.';
        }
        return ['deployment_state'=>$complete?'020 already applied':($partial?'Partial 020':'020 not applied'),'schema_complete'=>$complete,'schema_ready_for_020'=>!$partial&&!$problems];
    });
    foreach(['mbstring','pdo_mysql','fileinfo','gd'] as $ext)if(!extension_loaded($ext))$problems[]='Missing PHP extension '.$ext;
    if(PHP_INT_SIZE!==8||version_compare(PHP_VERSION,'8.1','<'))$problems[]='64-bit PHP 8.1 or later is required.';
    $protection=__DIR__.'/../uploads/receipts/.htaccess';if(!is_file($protection)||!str_contains(file_get_contents($protection),'Require all denied'))$problems[]='Protected receipt storage rule is missing.';
    $report['schema_ready_for_020']=$report['schema_ready_for_020']&&!$problems;
    echo workspace_json(['database'=>$db,'server_version'=>$p->query('SELECT VERSION()')->fetchColumn()]+$report+['problems'=>$problems,'checks_passed'=>!$problems,'writes_performed'=>false,'next_step'=>$report['schema_complete']?'Do not rerun 020. Verify deployment before separately authorized activation.':'Back up database/application/protected files, restore and rehearse on a copy, then obtain deployment authorization. Never rerun 019 or 015.'])."\n";
    exit($problems?1:0);
}catch(Throwable $e){fwrite(STDERR,'Stage 2 preflight failed: '.$e->getMessage()."\n");exit(1);}
