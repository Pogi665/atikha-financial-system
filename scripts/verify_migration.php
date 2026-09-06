<?php
require_once __DIR__.'/migration_support.php';
require_once __DIR__.'/../includes/audit_query.php';
try {
    $o=getopt('',['database:','backup-manifest:','stage:']);$pdo=cli_db($o['database']??'');$stage=$o['stage']??'before';
    cli_require(in_array($stage,['before','after'],true),'Stage must be before or after.');
    $m=json_decode(file_get_contents($o['backup-manifest']??''),true,512,JSON_THROW_ON_ERROR);
    $pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
    $now=migration_preserved($pdo);
    foreach(['users','funds','expenses','board'] as $key){cli_require($now[$key]===$m['preserved'][$key],'Preservation mismatch: '.$key);}
    cli_require(array_intersect_key($now['audit'],$m['preserved']['audit'])===$m['preserved']['audit'],'Original audit contents differ.');
    migration_check_triggers($pdo);
    cli_require(migration_upload_hashes(__DIR__.'/../uploads')===$m['uploads'],'Upload files differ.');
    if($stage==='before') {
        foreach($m['table_hashes'] as $table=>$hashes){cli_require(migration_hash_rows($pdo,$table)===$hashes,'Live table differs: '.$table);}
        echo "PASS: every original live table row and upload file matches verified backup; no live data changes.\n";
    }else{
        cli_require(array_map('intval',$pdo->query('SELECT UserID FROM Users ORDER BY UserID')->fetchAll(PDO::FETCH_COLUMN))===[1,8],'Only Admin and Zoe must remain.');
        cli_require($pdo->query('SELECT UserID,FullName,Email,Role FROM user_identities ORDER BY UserID')->fetchAll()===$m['identities'],'Historical identities differ.');
        cli_require(count($now['audit'])===count($m['preserved']['audit'])+12,'Expected twelve cleanup audit events.');
        foreach(['Notifications'=>['Recipient_UserID',9],'password_resets'=>['UserID',2]] as $table=>[$owner,$deleted]){
            cli_require((int)$pdo->query("SELECT COUNT(*) FROM `$table` WHERE `$owner` IN (".RETIRING_IDS.")")->fetchColumn()===0,'Test workflow records remain.');
            $remaining=migration_hash_rows($pdo,$table);
            cli_require(count($remaining)===count($m['table_hashes'][strtolower($table)])-$deleted,'Workflow deletion count differs.');
            cli_require(array_intersect_key($m['table_hashes'][strtolower($table)],$remaining)===$remaining,'Unrelated workflow records changed.');
        }
        $receiptContents=[];
        foreach($pdo->query('SELECT * FROM Receipts ORDER BY ReceiptID')->fetchAll() as $r){
            $id=$r['ReceiptID'];if(in_array($id,$m['transferred_receipt_ids'],true)){cli_require($r['UploadedBy_UserID']===1,'Receipt custody not transferred.');}
            unset($r['UploadedBy_UserID']);$receiptContents[$id]=hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE));
        }
        cli_require($receiptContents===$m['receipt_contents'],'Receipt content changed.');
        $retired=0;foreach([2,7,9,10] as $id){$retired+=count(audit_fetch_logs($pdo,audit_filters_from_request(['user_id'=>$id]),AUDIT_EXPORT_LIMIT));}
        cli_require($retired===81,'Retired audit entries are not all searchable/exportable.');
        foreach(['Users'=>'Role','Notifications'=>'Recipient_Role'] as $table=>$column){$r=$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();cli_require($r['Type']==="enum('Admin','Management')",'Role ENUM still has legacy values.');}
        echo "PASS: retained accounts, financial rows, original audits, 81 historical actor entries, Ron's message, receipts, workflow cleanup, ENUMs and upload checksums verified.\n";
    }
    $pdo->rollBack();
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction()){$pdo->rollBack();}fwrite(STDERR,$e->getMessage()."\n");exit(1);}
