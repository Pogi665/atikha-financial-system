<?php
require_once __DIR__.'/stage3_common.php';
/** Schema/data protections intentionally do not depend on the Stage 2 UI flag. */
function stage2_tables(PDO $pdo): bool
{
    return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('cash_advances','cash_advance_operations','cash_advance_due_changes')")->fetchColumn()===3;
}
function stage2_schema(PDO $pdo): bool
{
    if(!stage2_tables($pdo))return false;
    $n=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_drafts' AND COLUMN_NAME IN ('workflow_kind','advance_id','return_confirmation')")->fetchColumn();
    $t=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('s2_operations_no_update','s2_operations_no_delete','s2_due_no_update','s2_due_no_delete','s2_advance_identity','s2_advance_no_delete')")->fetchColumn();
    $foreign=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('fk_s2_advance_party','fk_s2_advance_project','fk_s2_advance_control','fk_s2_advance_release','fk_s2_advance_actor','fk_s2_draft_advance','fk_s2_operation_advance','fk_s2_operation_journal','fk_s2_operation_draft','fk_s2_operation_line','fk_s2_operation_actor','fk_s2_due_advance','fk_s2_due_actor') AND DELETE_RULE='RESTRICT'")->fetchColumn();
    $engines=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('cash_advances','cash_advance_operations','cash_advance_due_changes') AND ENGINE='InnoDB'")->fetchColumn();
    $index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cash_advance_operations' AND INDEX_NAME='uq_s2_one_release' AND NON_UNIQUE=0 AND COLUMN_NAME='release_advance_id'")->fetchColumn();
    return $n===3&&$t===6&&$foreign===13&&$engines===3&&$index===1;
}
function stage2_enabled(PDO $pdo): bool
{
    return defined('STAGE2_ADVANCES_ENABLED')&&STAGE2_ADVANCES_ENABLED===true&&stage1_enabled($pdo)&&stage2_schema($pdo);
}
function stage2_sensitive_journals(PDO $pdo,array $ids): array
{
    return stage3_sensitive_journals($pdo,$ids);
}
function stage2_sensitive_journals_direct(PDO $pdo,array $ids): array
{
    if(!$ids)return [];$found=[];
    foreach(array_chunk($ids,500) as $chunk){
        $marks=implode(',',array_fill(0,count($chunk),'?'));
        $s=$pdo->prepare("SELECT id FROM journal_entries WHERE id IN ($marks) AND transaction_kind IN ('advance_release','advance_liquidation','advance_return')");$s->execute(array_values($chunk));
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id)$found[(int)$id]=true;
        $s=$pdo->prepare("SELECT DISTINCT l.journal_entry_id FROM journal_entry_lines l JOIN advance_control_designations d ON d.account_id=l.account_id WHERE l.journal_entry_id IN ($marks)");$s->execute(array_values($chunk));foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id)$found[(int)$id]=true;
        if(stage2_tables($pdo)){$s=$pdo->prepare("SELECT journal_id FROM cash_advance_operations WHERE journal_id IN ($marks)");$s->execute(array_values($chunk));foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id)$found[(int)$id]=true;}
    }return $found;
}
function workspace_ordinary(array $draft): void
{
    if((int)$draft['payload_version']!==2||($draft['workflow_kind']??'ordinary')!=='ordinary')throw new JournalProblem('Open this advance draft through Cash advances.',409);
}

function stage2_private_viewer(PDO $pdo): bool
{
    $uid=(int)($_SESSION['UserID']??0);if($uid<=0)return false;
    $s=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=?');$s->execute([$uid]);$u=$s->fetch();return $u&&$u['Role']==='Admin'&&(int)$u['Is_Active']===1;
}
