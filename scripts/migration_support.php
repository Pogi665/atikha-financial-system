<?php
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/user_identities.php';
require_once __DIR__ . '/../includes/logger.php';

const RETIRING_IDS = '2,7,9,10';

function migration_upload_hashes(string $root): array
{
    $root=realpath($root);cli_require($root!==false,'Upload directory missing.');$hashes=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file) {
        if($file->isFile()) {$hashes[str_replace('\\','/',substr($file->getPathname(),strlen($root)+1))]=hash_file('sha256',$file->getPathname());}
    }
    ksort($hashes);return $hashes;
}

function migration_hash_rows(PDO $pdo, string $table, string $where = ''): array
{
    // Only internally selected identifiers; never accept identifiers from HTTP input.
    cli_require((bool) preg_match('/\A[a-zA-Z_]+\z/', $table), 'Invalid table.');
    $rows = $pdo->query("SELECT * FROM `$table` $where ORDER BY 1")->fetchAll();
    $hashes = [];
    foreach ($rows as $row) { $hashes[(string) reset($row)] = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE)); }
    return $hashes;
}

function migration_preserved(PDO $pdo): array
{
    return [
        'users' => migration_hash_rows($pdo, 'Users', 'WHERE UserID IN (1,8)'),
        'funds' => migration_hash_rows($pdo, 'Incoming_Funds'),
        'expenses' => migration_hash_rows($pdo, 'Expenses'),
        'audit' => migration_hash_rows($pdo, 'audit_logs'),
        'board' => migration_hash_rows($pdo, 'Board_Communications'),
    ];
}

function migration_check_triggers(PDO $pdo): void
{
    $rows = $pdo->query("SELECT TRIGGER_NAME,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE='audit_logs'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['trg_audit_logs_no_update','trg_audit_logs_no_delete'] as $name) {
        cli_require(isset($rows[$name]) && str_contains($rows[$name], "SIGNAL SQLSTATE '45000'"), 'Audit immutability trigger missing: ' . $name);
    }
}

function migration_authorize(PDO $pdo, string $database, array $options): void
{
    cli_require(isset($options['maintenance-confirmed']), 'Execution requires --maintenance-confirmed (writes stopped and old sessions revoked).');
    if ($database !== 'atikha_finance') { return; }
    $path = $options['backup-manifest'] ?? '';
    cli_require(is_file($path), 'Live execution requires a verified backup manifest.');
    $m = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    cli_require(($m['source_database'] ?? '') === $database && ($m['restoration_verified'] ?? false) === true, 'Backup restoration is not verified.');
    cli_require(is_file($m['export_path']) && hash_file('sha256', $m['export_path']) === $m['export_sha256'], 'Database export missing or changed.');
    cli_require(migration_upload_hashes(__DIR__.'/../uploads')===$m['uploads'],'Live upload file set changed since backup.');
    cli_require(migration_upload_hashes($m['upload_backup'])===$m['uploads'],'Upload backup file set changed.');
    foreach ($m['uploads'] as $relative => $hash) {
        cli_require(is_file($m['upload_backup'] . '/' . $relative) && hash_file('sha256', $m['upload_backup'] . '/' . $relative) === $hash, 'Upload backup missing or changed: ' . $relative);
        cli_require(is_file(__DIR__ . '/../uploads/' . $relative) && hash_file('sha256', __DIR__ . '/../uploads/' . $relative) === $hash, 'Live upload changed: ' . $relative);
    }
    $now = migration_preserved($pdo);
    foreach (['users','funds','expenses','board'] as $key) { cli_require($now[$key] === $m['preserved'][$key], 'Backup baseline drift: ' . $key); }
    cli_require(array_intersect_key($now['audit'], $m['preserved']['audit']) === $m['preserved']['audit'], 'Original audit contents changed.');
    // Before cleanup, every source table must still match the restored export.
    if ((int) $pdo->query('SELECT COUNT(*) FROM Users WHERE UserID IN (' . RETIRING_IDS . ')')->fetchColumn() > 0) {
        foreach ($m['table_hashes'] as $table => $hashes) { cli_require(migration_hash_rows($pdo, $table) === $hashes, 'Backup table drift: ' . $table); }
    }
}

function migration_preflight(PDO $pdo): array
{
    $users = $pdo->query('SELECT UserID,FullName,Role FROM Users ORDER BY UserID')->fetchAll();
    $dependencies = [];
    foreach (['Incoming_Funds'=>'RecordedBy_UserID','Expenses'=>'RecordedBy_UserID','Receipts'=>'UploadedBy_UserID',
        'audit_logs'=>'user_id','Reports'=>'SubmittedBy_UserID','Board_Communications'=>'Sender_UserID',
        'Notifications'=>'Recipient_UserID','password_resets'=>'UserID','forecast_cache'=>'GeneratedBy_UserID'] as $table=>$column) {
        $dependencies[$table] = $pdo->query("SELECT `$column` AS user_id, COUNT(*) AS n FROM `$table` WHERE `$column` IN (" . RETIRING_IDS . ") GROUP BY `$column` ORDER BY `$column`")->fetchAll();
    }
    $fks = $pdo->query("SELECT k.TABLE_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IN ('users','user_identities') ORDER BY k.TABLE_NAME,k.COLUMN_NAME")->fetchAll();
    return ['users'=>$users, 'funds'=>$pdo->query('SELECT COUNT(*) AS n,SUM(Amount) AS total FROM Incoming_Funds')->fetch(),
        'expenses'=>$pdo->query('SELECT COUNT(*) AS n,SUM(Amount) AS total FROM Expenses')->fetch(),
        'audit_count'=>(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(), 'dependencies'=>$dependencies, 'foreign_keys'=>$fks,
        'schema_009_applied'=>user_identity_table($pdo) === 'user_identities'];
}

function migration_validate_before(PDO $pdo, array $p, int $expectedAudit = 238): void
{
    $expected = [1=>['System Administrator','Admin'],2=>['Test Staff','Staff'],7=>['Ron Jayson','Staff'],8=>['Zoe','Management'],9=>['John Doe','Management'],10=>['Juan Dela Cruz','Management']];
    $actual=[]; foreach ($p['users'] as $u) { $actual[(int)$u['UserID']]=[$u['FullName'],$u['Role']]; }
    cli_require($actual === $expected, 'Unexpected users, names or roles; stop for review.');
    cli_require((int)$p['funds']['n'] === 13 && $p['funds']['total'] === '2420000.00', 'Fund baseline drift.');
    cli_require((int)$p['expenses']['n'] === 56 && $p['expenses']['total'] === '887479.40', 'Expense baseline drift.');
    cli_require($expectedAudit >= 238 && $p['audit_count'] === $expectedAudit, 'Audit baseline drift.');
    $expectedCounts=['Incoming_Funds'=>[], 'Expenses'=>[], 'Receipts'=>[2=>1,7=>5], 'audit_logs'=>[2=>26,7=>49,9=>4,10=>2],
        'Reports'=>[], 'Board_Communications'=>[7=>1], 'Notifications'=>[7=>1,9=>4,10=>4], 'password_resets'=>[2=>1,9=>1], 'forecast_cache'=>[]];
    foreach ($expectedCounts as $table=>$expectedRows) {
        $counts=[]; foreach ($p['dependencies'][$table] as $r) { $counts[(int)$r['user_id']]=(int)$r['n']; }
        cli_require($counts === $expectedRows, 'Dependency drift: ' . $table);
    }
    cli_require((int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE UserID IN (2,9) AND Status='completed'")->fetchColumn() === 2, 'Reset requests are not completed.');
    cli_require((int)$pdo->query('SELECT COUNT(*) FROM password_resets WHERE ResolvedBy_UserID IN (' . RETIRING_IDS . ')')->fetchColumn() === 0, 'Unexpected reset resolver dependency.');
    migration_check_triggers($pdo);
}

function migration_cleanup(PDO $pdo, int $expectedAudit = 238, ?callable $beforeDelete = null): array
{
    cli_require(!$pdo->inTransaction(), 'Cleanup requires its own transaction.');
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT UserID FROM Users ORDER BY UserID FOR UPDATE')->fetchAll();
        $p=migration_preflight($pdo); migration_validate_before($pdo,$p,$expectedAudit);
        cli_require($p['schema_009_applied'], 'Apply schema checkpoint 009 first.');
        foreach (['audit_logs'=>'user_id','board_communications'=>'Sender_UserID'] as $table=>$column) {
            $s=$pdo->prepare('SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c AND REFERENCED_TABLE_NAME IS NOT NULL');
            $s->execute(['t'=>$table,'c'=>$column]); cli_require($s->fetchColumn()==='user_identities','Historical foreign key not migrated: '.$table);
        }
        $before=migration_preserved($pdo);
        $receiptBefore=migration_hash_rows($pdo,'Receipts');
        $receipts=$pdo->query('SELECT r.*,h.FullName FROM Receipts r JOIN user_identities h ON h.UserID=r.UploadedBy_UserID WHERE r.UploadedBy_UserID IN (2,7) ORDER BY r.ReceiptID FOR UPDATE')->fetchAll();
        foreach ($receipts as $r) {
            $s=$pdo->prepare('UPDATE Receipts SET UploadedBy_UserID=1 WHERE ReceiptID=:id');$s->execute(['id'=>$r['ReceiptID']]);
            log_system_action($pdo,1,'EDIT','Receipts',(int)$r['ReceiptID'], ['original_identity'=>['id'=>(int)$r['UploadedBy_UserID'],'name'=>$r['FullName']]],
                ['new_custodian'=>['id'=>1,'name'=>'System Administrator'],'receipt_ids'=>[(int)$r['ReceiptID']],'reason'=>'Two-role consolidation: transfer operational receipt custody; preserve original audit actors.']);
        }
        foreach (['Notifications'=>['NotificationID','Recipient_UserID'],'password_resets'=>['ResetID','UserID']] as $table=>[$pk,$owner]) {
            $rows=$pdo->query("SELECT `$pk`,`$owner` FROM `$table` WHERE `$owner` IN (".RETIRING_IDS.") ORDER BY `$pk` FOR UPDATE")->fetchAll();
            $pdo->exec("DELETE FROM `$table` WHERE `$owner` IN (".RETIRING_IDS.")");
            log_system_action($pdo,1,'DELETE',$table,null,['records'=>$rows],['reason'=>'Remove obsolete test-account workflow records during two-role consolidation.']);
        }
        if ($beforeDelete !== null) { $beforeDelete($pdo); } // Test-only fault injection; CLI exposes no hook.
        foreach ($pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='users'")->fetchAll() as $fk) {
            $t=$fk['TABLE_NAME'];$c=$fk['COLUMN_NAME'];
            cli_require((int)$pdo->query("SELECT COUNT(*) FROM `$t` WHERE `$c` IN (".RETIRING_IDS.")")->fetchColumn()===0,'Unhandled dependency: '.$t.'.'.$c);
        }
        foreach ([2,7,9,10] as $id) {
            $s=$pdo->prepare('SELECT UserID,FullName,Role FROM Users WHERE UserID=:id');$s->execute(['id'=>$id]);$original=$s->fetch();
            $s=$pdo->prepare('DELETE FROM Users WHERE UserID=:id');$s->execute(['id'=>$id]);cli_require($s->rowCount()===1,'User deletion mismatch.');
            log_system_action($pdo,1,'DELETE','Users',$id,$original,['reason'=>'Retire test login account; historical identity retained.']);
        }
        cli_require(array_map('intval',$pdo->query('SELECT UserID FROM Users ORDER BY UserID')->fetchAll(PDO::FETCH_COLUMN))===[1,8],'Unexpected remaining Users.');
        $after=migration_preserved($pdo);
        foreach (['users','funds','expenses','board'] as $key) { cli_require($before[$key]===$after[$key],'Preservation failed: '.$key); }
        cli_require(array_intersect_key($after['audit'],$before['audit'])===$before['audit'],'Original audit rows changed.');
        cli_require(count($after['audit'])===$expectedAudit+12,'Expected twelve migration audit entries.');
        foreach ($receipts as $r) {
            unset($r['FullName']);$r['UploadedBy_UserID']=1;
            $s=$pdo->prepare('SELECT * FROM Receipts WHERE ReceiptID=:id');$s->execute(['id'=>$r['ReceiptID']]);
            cli_require($s->fetch()===$r,'Unexpected receipt change.');
            unset($receiptBefore[(string)$r['ReceiptID']]);
        }
        cli_require(array_intersect_key(migration_hash_rows($pdo,'Receipts'),$receiptBefore)===$receiptBefore,'Unrelated receipts changed.');
        migration_check_triggers($pdo);$pdo->commit();return ['transferred_receipts'=>6,'deleted_notifications'=>9,'deleted_resets'=>2,'deleted_users'=>[2,7,9,10],'retained_board_sender'=>7,'original_audit_entries'=>$expectedAudit,'new_audit_entries'=>12];
    } catch (Throwable $e) { if($pdo->inTransaction()){$pdo->rollBack();} throw $e; }
}
