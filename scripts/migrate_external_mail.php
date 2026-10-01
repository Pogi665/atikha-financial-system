<?php
/** Guarded, additive migration 014; no SMTP or email delivery is performed. */
require_once __DIR__ . '/migration_support.php';

function external_schema_validate(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM External_Communications')->fetchAll(PDO::FETCH_UNIQUE);
    $expected = ['CommunicationID','Sender_UserID','To_Email','From_Email','Reply_To_Email','Subject',
        'Message_Body','File_Path','Attachment_Name','Attachment_Mime','Attachment_Size','SMTP_Message_ID',
        'Submission_Key','Send_Status','Failure_Code','Created_At','Updated_At','Sent_At'];
    cli_require(array_keys($columns) === $expected, 'Migration 014 column shape differs.');
    cli_require($columns['Send_Status']['Type'] === "enum('Sending','Sent','Failed','Unknown')", 'Unexpected send statuses.');
    $fks = $pdo->query("SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND LOWER(TABLE_NAME)='external_communications'
        AND COLUMN_NAME='Sender_UserID'")->fetchAll(PDO::FETCH_COLUMN);
    cli_require($fks === ['user_identities'], 'External sender identity FK missing.');
    $indexes = $pdo->query('SHOW INDEX FROM External_Communications')->fetchAll();
    $names = array_unique(array_column($indexes, 'Key_name'));
    foreach (['PRIMARY','uq_external_submission','idx_external_sent','idx_external_created'] as $index) {
        cli_require(in_array($index, $names, true), 'Missing index: ' . $index);
    }
    $unique = array_filter($indexes, static fn(array $row): bool => $row['Key_name'] === 'uq_external_submission');
    cli_require(count($unique) === 1 && (int) array_values($unique)[0]['Non_unique'] === 0, 'Submission key must be unique.');
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) { return; }
try {
    $options = getopt('', ['database:', 'execute', 'backup-manifest:', 'maintenance-confirmed']);
    $database = $options['database'] ?? '';
    $pdo = cli_db($database);
    cli_require(user_identity_table($pdo) === 'user_identities', 'Migration 009 is required before 014.');
    cli_require((bool) $pdo->query("SHOW COLUMNS FROM Users LIKE 'Is_Active'")->fetch(), 'Migration 013 is required before 014.');
    migration_check_triggers($pdo);
    if (!isset($options['execute'])) {
        echo "DRY RUN: migration 014 prerequisites pass; no SQL executed.\n";
        exit;
    }
    if ($database === 'atikha_finance') {
        $manifestPath = $options['backup-manifest'] ?? '';
        cli_require(isset($options['maintenance-confirmed']) && is_file($manifestPath), 'Live execution requires a verified backup and maintenance confirmation.');
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        cli_require(($manifest['restoration_verified'] ?? false) === true
            && ($manifest['source_database'] ?? '') === $database, 'Backup was not restored and verified.');
        cli_require(is_file($manifest['export_path']) && hash_file('sha256', $manifest['export_path']) === $manifest['export_sha256'], 'Backup export changed.');
        cli_require(migration_upload_hashes(__DIR__ . '/../uploads') === $manifest['uploads']
            && migration_upload_hashes($manifest['upload_backup']) === $manifest['uploads'], 'Upload backup or live files changed.');
        foreach ($manifest['table_hashes'] as $table => $hashes) {
            cli_require(migration_hash_rows($pdo, $table) === $hashes, 'Backup baseline drift: ' . $table);
        }
    }
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $before = [];
    foreach ($tables as $table) { $before[$table] = migration_hash_rows($pdo, $table); }
    cli_sql_file($pdo, __DIR__ . '/../migrations/014_external_communications.sql');
    external_schema_validate($pdo);
    foreach ($before as $table => $hashes) {
        cli_require(migration_hash_rows($pdo, $table) === $hashes, 'Existing rows changed: ' . $table);
    }
    migration_check_triggers($pdo);
    echo 'PASS: migration 014 applied to ' . $database . '; all existing rows and audit protections preserved.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
