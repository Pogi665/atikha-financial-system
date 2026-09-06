<?php
require_once __DIR__ . '/migration_support.php';
try {
    $options=getopt('',['database:','execute','dry-run','backup-manifest:','maintenance-confirmed','expected-audit-count:']);
    $database=$options['database']??'';$pdo=cli_db($database);
    $expectedAudit=filter_var($options['expected-audit-count']??238,FILTER_VALIDATE_INT);
    cli_require($expectedAudit!==false && $expectedAudit>=238,'Expected audit count must be at least 238.');
    cli_require(!(isset($options['execute']) && isset($options['dry-run'])),'Choose dry-run or execute, not both.');
    if (!isset($options['execute'])) {
        $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
        $p=migration_preflight($pdo);
        try { migration_validate_before($pdo,$p,$expectedAudit); } catch(Throwable $e) { echo json_encode($p,JSON_PRETTY_PRINT)."\n"; throw $e; }
        $pdo->rollBack();
        echo json_encode(['mode'=>'DRY RUN: no writes','database'=>$database,'preflight'=>$p,'planned'=>['receipt_transfers'=>6,'notification_deletions'=>9,'completed_reset_deletions'=>2,'user_deletions'=>[2,7,9,10],'retained_board_sender'=>7,'audit_rows_preserved'=>$expectedAudit,'retired_actor_audit_rows'=>81]],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit;
    }
    migration_authorize($pdo,$database,$options);
    echo json_encode(migration_cleanup($pdo,$expectedAudit),JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) {if(isset($pdo)&&$pdo->inTransaction()){$pdo->rollBack();}fwrite(STDERR,$e->getMessage()."\n");exit(1);}
