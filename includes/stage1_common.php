<?php
require_once __DIR__.'/stage2_common.php';
/** Shared serialization barrier. Guards remain enforced with the UI flag off. */
function stage1_schema(PDO $pdo): bool
{
    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('projects','parties','journal_drafts','draft_evidence_reservations',
        'advance_control_designations','posted_evidence_associations','evidence_allocations','accounting_write_state')")->fetchColumn();
    if ((int)$n !== 8) { return false; }
    return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='journal_entries' AND COLUMN_NAME IN ('source_book','transaction_kind','party_id','party_snapshot')")->fetchColumn() === 4;
}
function stage1_enabled(PDO $pdo): bool
{
    return defined('STAGE1_WORKSPACE_ENABLED') && STAGE1_WORKSPACE_ENABLED === true && stage1_schema($pdo);
}
function stage1_write_lock(PDO $pdo): ?string
{
    if (!stage1_schema($pdo)) { return null; }
    $s = $pdo->query('SELECT change_version FROM accounting_write_state WHERE id=1 FOR UPDATE');
    $v = $s->fetchColumn();
    if ($v === false) { throw new RuntimeException('Accounting coordination state is unavailable.'); }
    return (string)$v;
}
function stage1_write_changed(PDO $pdo): void
{
    if (stage1_schema($pdo)) { $pdo->exec('UPDATE accounting_write_state SET change_version=change_version+1 WHERE id=1'); }
}
function stage1_reserved_guard(PDO $pdo, int $receiptId): void
{
    if (!stage1_schema($pdo)) { return; }
    $s = $pdo->prepare('SELECT draft_id FROM draft_evidence_reservations WHERE receipt_id=?'); $s->execute([$receiptId]);
    if ($id = $s->fetchColumn()) {
        throw new JournalProblem('This image is reserved to draft #' . (int)$id . '. Open its owning draft to change it.', 409);
    }
}
function stage1_control_guard(PDO $pdo, array $accountIds): void
{
    if (!stage1_schema($pdo) || !$accountIds) { return; }
    $s = $pdo->prepare('SELECT account_id FROM advance_control_designations WHERE account_id IN ('
        . implode(',',array_fill(0,count($accountIds),'?')) . ')'); $s->execute(array_values($accountIds));
    if ($s->fetchColumn() !== false) { throw new JournalProblem('Advance-control accounts are reserved for the cash-advance workflow. Ordinary entries cannot use them.'); }
}
