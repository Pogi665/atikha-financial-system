<?php
require_once __DIR__.'/migration_support.php';
try {
    $o=getopt('',['database:','copy-database:','directory:']);$source=$o['database']??'';$copy=$o['copy-database']??'';
    cli_require((bool)preg_match('/\Aatikha_test_[a-z0-9_]+\z/',$copy),'Restoration target must be disposable atikha_test_*.');
    $pdo=cli_db($source);$restored=cli_db($copy);$dir=realpath($o['directory']??'');
    cli_require($dir!==false && is_file($dir.'/database.sql') && is_dir($dir.'/uploads'),'Export and upload backup required.');
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$hashes=[];
    foreach($tables as $table) {
        $hashes[$table]=migration_hash_rows($pdo,$table);
        cli_require($hashes[$table]===migration_hash_rows($restored,$table),'Restoration mismatch: '.$table);
        $a=$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch();$b=$restored->query('SHOW CREATE TABLE `'.$table.'`')->fetch();
        cli_require(array_values($a)[1]===array_values($b)[1],'Restored schema differs: '.$table);
    }
    migration_check_triggers($pdo);migration_check_triggers($restored);
    $triggers=static fn(PDO $db)=>$db->query('SELECT TRIGGER_NAME,EVENT_MANIPULATION,ACTION_TIMING,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME')->fetchAll();
    cli_require($triggers($pdo)===$triggers($restored),'Restored trigger definitions differ.');
    $uploads=[];$root=realpath(__DIR__.'/../uploads');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file) {
        if(!$file->isFile()){continue;}$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
        $uploads[$relative]=hash_file('sha256',$file->getPathname());
        cli_require(is_file($dir.'/uploads/'.$relative) && hash_file('sha256',$dir.'/uploads/'.$relative)===$uploads[$relative],'Upload restoration mismatch: '.$relative);
    }
    ksort($uploads);
    cli_require(migration_upload_hashes($dir.'/uploads')===$uploads,'Backup has unexpected upload files.');
    $receiptContents=[];
    foreach($pdo->query('SELECT * FROM Receipts ORDER BY ReceiptID')->fetchAll() as $r) {
        $id=$r['ReceiptID'];unset($r['UploadedBy_UserID']);$receiptContents[$id]=hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE));
    }
    $m=['source_database'=>$source,'restored_database'=>$copy,'restoration_verified'=>true,'verified_at'=>gmdate('c'),
        'export_path'=>$dir.'/database.sql','export_sha256'=>hash_file('sha256',$dir.'/database.sql'),
        'upload_backup'=>$dir.'/uploads','uploads'=>$uploads,'table_hashes'=>$hashes,'preserved'=>migration_preserved($pdo),
        'receipt_contents'=>$receiptContents,'identities'=>$pdo->query('SELECT UserID,FullName,Email,Role FROM Users ORDER BY UserID')->fetchAll(),
        'transferred_receipt_ids'=>array_map('intval',$pdo->query('SELECT ReceiptID FROM Receipts WHERE UploadedBy_UserID IN (2,7) ORDER BY ReceiptID')->fetchAll(PDO::FETCH_COLUMN))];
    file_put_contents($dir.'/manifest.json',json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo 'PASS: restored '.count($tables).' tables, audit triggers and '.count($uploads)." upload files; manifest written.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
