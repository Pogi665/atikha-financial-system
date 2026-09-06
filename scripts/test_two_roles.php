<?php
require_once __DIR__.'/migration_support.php';
require_once __DIR__.'/../includes/audit_query.php';
require_once __DIR__.'/../includes/admin_bootstrap.php';
function test_ok(bool $ok,string $name): void { cli_require($ok,$name);echo 'PASS: '.$name."\n"; }
try {
    $o=getopt('',['database:','fresh-database:','expected-audit-count:']);$db=$o['database']??'';$fresh=$o['fresh-database']??'';
    cli_require((bool)preg_match('/\Aatikha_test_[a-z0-9_]+\z/',$db) && (bool)preg_match('/\Aatikha_test_[a-z0-9_]+\z/',$fresh) && $db!==$fresh,'Tests require two distinct disposable databases.');
    $pdo=cli_db($db);$count=(int)($o['expected-audit-count']??238);
    migration_validate_before($pdo,migration_preflight($pdo),$count);
    cli_require(user_identity_table($pdo)==='user_identities','Apply 009 to restored copy first.');
    $before=migration_preserved($pdo);$receiptBefore=migration_hash_rows($pdo,'Receipts');
    $notificationBefore=migration_hash_rows($pdo,'Notifications');$resetBefore=migration_hash_rows($pdo,'password_resets');
    try { migration_cleanup($pdo,$count,static function(){throw new RuntimeException('injected cleanup failure');}); throw new RuntimeException('Injection did not abort.'); }
    catch(RuntimeException $e) {cli_require($e->getMessage()==='injected cleanup failure',$e->getMessage());}
    test_ok(migration_preserved($pdo)===$before && migration_hash_rows($pdo,'Receipts')===$receiptBefore && migration_hash_rows($pdo,'Notifications')===$notificationBefore && migration_hash_rows($pdo,'password_resets')===$resetBefore,'Cleanup failure rolls back transfers, deletions and audit appends');
    $result=migration_cleanup($pdo,$count);test_ok($result['deleted_users']===[2,7,9,10],'Approved cleanup completes on restored copy');
    $after=migration_preserved($pdo);
    test_ok($after['users']===$before['users'],'Admin and Zoe complete rows/credentials unchanged');
    test_ok($after['funds']===$before['funds'] && $after['expenses']===$before['expenses'],'Every financial row and total unchanged');
    test_ok(array_intersect_key($after['audit'],$before['audit'])===$before['audit'],'All original audit rows byte-equivalent');
    test_ok($after['board']===$before['board'],'Ron board message unchanged, original sender retained');
    $retired=0;
    foreach([2=>26,7=>49,9=>4,10=>2] as $id=>$n) {
        $rows=audit_fetch_logs($pdo,audit_filters_from_request(['user_id'=>$id]),AUDIT_EXPORT_LIMIT);
        test_ok(count($rows)===$n,'Historical actor '.$id.' searchable/exportable');$retired+=count($rows);
    }
    test_ok($retired===81 && count(audit_fetch_logs($pdo,audit_filters_from_request(['q'=>'Ron Jayson','user_id'=>7]),AUDIT_EXPORT_LIMIT))===49,'All 81 retired entries and native-PDO name search work');
    $s=$pdo->query('SELECT b.Sender_UserID,h.FullName FROM Board_Communications b JOIN user_identities h ON h.UserID=b.Sender_UserID WHERE b.Sender_UserID=7')->fetch();
    test_ok($s && $s['FullName']==='Ron Jayson','Board sender resolves from historical registry');
    foreach(['UPDATE audit_logs SET module=module LIMIT 1','DELETE FROM audit_logs LIMIT 1'] as $sql) {
        try {$pdo->exec($sql);throw new RuntimeException('Immutability failed.');}catch(PDOException $e){test_ok($e->getCode()==='45000','Audit trigger rejects '.strtok($sql,' '));}
    }
    try {migration_cleanup($pdo,$count);throw new RuntimeException('Rerun unexpectedly succeeded.');}catch(RuntimeException $e){test_ok(str_contains($e->getMessage(),'Unexpected users'),'Cleanup rerun refuses without duplicate events');}
    cli_sql_file($pdo,__DIR__.'/../migrations/010_two_operational_roles.sql');
    foreach(['Users'=>'Role','Notifications'=>'Recipient_Role'] as $table=>$column){$s=$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();test_ok($s['Type']==="enum('Admin','Management')",$table.' operational ENUM has exactly two roles');}
    $pdo->exec('CREATE DATABASE `'.$fresh.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$new=cli_db($fresh);
    cli_sql_file($new,__DIR__.'/../database.sql');
    foreach(glob(__DIR__.'/../migrations/*.sql') as $path){cli_sql_file($new,$path);}
    test_ok((int)$new->query('SELECT COUNT(*) FROM Users')->fetchColumn()===0,'Fresh install has no seeded credentials');
    $secret=bin2hex(random_bytes(24));$id=bootstrap_admin($new,'Fixture Admin','admin@example.invalid',$secret);
    $user=$new->query('SELECT * FROM Users')->fetch();
    test_ok($id===1 && password_verify($secret,$user['Password']) && $user['Password']!==$secret,'Bootstrap hashes runtime password');
    test_ok((int)$new->query('SELECT COUNT(*) FROM user_identities')->fetchColumn()===1 && (int)$new->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===1,'Bootstrap creates identity and audit atomically');
    try {bootstrap_admin($new,'Duplicate','duplicate@example.invalid',$secret);throw new RuntimeException('Duplicate bootstrap succeeded.');}catch(RuntimeException $e){test_ok(str_contains($e->getMessage(),'not an empty'),'Bootstrap refuses nonempty installation');}
    test_ok(!user_role_is_valid('Staff') && user_role_is_valid('Admin') && user_role_is_valid('Management'),'Role vocabulary accepts only approved operational roles');
    echo "PASS: migration and fresh-install integration suite complete.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");exit(1);}
