<?php
/** Read-only verification against the pre-migration backup manifest. */
require_once __DIR__ . '/migrate_external_mail.php';
try {
    $options = getopt('', ['database:', 'backup-manifest:']);
    $pdo = cli_db($options['database'] ?? '');
    $path = $options['backup-manifest'] ?? '';
    cli_require(is_file($path), 'Verified backup manifest required.');
    $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    cli_require(($manifest['restoration_verified'] ?? false) === true, 'Backup restoration was not verified.');
    external_schema_validate($pdo);
    foreach ($manifest['table_hashes'] as $table => $before) {
        $current = migration_hash_rows($pdo, $table);
        $preserved = strtolower($table) === 'audit_logs' ? array_intersect_key($current, $before) : $current;
        cli_require($preserved === $before, 'Original records changed: ' . $table);
    }
    $files = migration_upload_hashes(__DIR__ . '/../uploads');
    cli_require(array_intersect_key($files, $manifest['uploads']) === $manifest['uploads'], 'Original upload contents changed.');
    migration_check_triggers($pdo);
    $counts = $pdo->query('SELECT Send_Status, COUNT(*) AS rows_count FROM External_Communications GROUP BY Send_Status')->fetchAll();
    echo 'PASS: schema 014, all original table rows, uploads and audit protections verified.' . PHP_EOL;
    echo json_encode(['external_email_counts' => $counts], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
