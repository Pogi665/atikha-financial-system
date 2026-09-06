<?php
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/migration_support.php';
try {
    $options = getopt('', ['database:', 'phase:', 'execute', 'backup-manifest:', 'maintenance-confirmed']);
    $database = $options['database'] ?? '';
    $phase = $options['phase'] ?? '';
    cli_require(in_array($phase, ['009', '010'], true), 'Specify --phase=009 or --phase=010.');
    $pdo = cli_db($database);
    if ($phase === '010') {
        cli_require((int) $pdo->query("SELECT COUNT(*) FROM Users WHERE Role NOT IN ('Admin','Management')")->fetchColumn() === 0, 'Operational legacy roles remain. Run cleanup first.');
        cli_require((int) $pdo->query("SELECT COUNT(*) FROM Notifications WHERE Recipient_Role IS NOT NULL AND Recipient_Role NOT IN ('Admin','Management')")->fetchColumn() === 0, 'Unexpected notification roles remain; review before narrowing.');
        if ($database === 'atikha_finance') { cli_require(array_map('intval', $pdo->query('SELECT UserID FROM Users ORDER BY UserID')->fetchAll(PDO::FETCH_COLUMN)) === [1,8], 'Live cleanup has not completed.'); }
    }
    if (!isset($options['execute'])) { echo "DRY RUN: schema checkpoint $phase; no SQL executed. DDL requires independent recovery.\n"; exit; }
    migration_authorize($pdo, $database, $options);
    $before = migration_preserved($pdo);
    if ($phase === '009' && user_identity_table($pdo) !== 'Users') {
        cli_require((int) $pdo->query('SELECT COUNT(*) FROM Users u JOIN user_identities h ON h.UserID=u.UserID WHERE h.FullName<>u.FullName OR h.Email<>u.Email OR h.Role<>u.Role')->fetchColumn() === 0, 'Existing identity differs; stop for review.');
    }
    cli_sql_file($pdo, __DIR__ . '/../migrations/' . ($phase === '009' ? '009_user_identity_history.sql' : '010_two_operational_roles.sql'));
    cli_require(migration_preserved($pdo) === $before, 'Preservation check failed after DDL; keep maintenance active and restore checkpoint.');
    migration_check_triggers($pdo);
    if ($phase === '009') {
        foreach (['audit_logs'=>'user_id','board_communications'=>'Sender_UserID'] as $table=>$column) {
            $s=$pdo->prepare('SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND COLUMN_NAME=:column_name AND REFERENCED_TABLE_NAME IS NOT NULL');
            $s->execute(['table_name'=>$table,'column_name'=>$column]);
            cli_require($s->fetchColumn()==='user_identities','Historical foreign key checkpoint incomplete: '.$table);
        }
    }
    echo "PASS: schema checkpoint $phase; DDL committed independently.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
