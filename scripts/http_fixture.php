<?php
require_once __DIR__.'/cli_common.php';
require_once __DIR__.'/../includes/mfa.php';
require_once __DIR__.'/../includes/csrf.php';
try {
    $o=getopt('',['database:','sessions:','email:','inspect','receipt']);$db=$o['database']??'';
    cli_require((bool)preg_match('/\Aatikha_test_[a-z0-9_]+\z/',$db),'HTTP fixtures require disposable database.');$pdo=cli_db($db);
    if(isset($o['inspect'])) {
        echo json_encode(['users'=>$pdo->query('SELECT UserID,Email,Role FROM Users ORDER BY UserID')->fetchAll(),
            'funds'=>$pdo->query('SELECT * FROM Incoming_Funds ORDER BY FundID')->fetchAll(),
            'expenses'=>$pdo->query('SELECT * FROM Expenses ORDER BY ExpenseID')->fetchAll(),
            'receipts'=>$pdo->query('SELECT * FROM Receipts ORDER BY ReceiptID')->fetchAll(),
            'messages'=>$pdo->query('SELECT * FROM Board_Communications ORDER BY CommunicationID')->fetchAll(),
            'identity_count'=>(int)$pdo->query('SELECT COUNT(*) FROM user_identities')->fetchColumn()]);exit;
    }
    $s=$pdo->prepare('SELECT UserID,Email FROM Users WHERE Email=:email');$s->execute(['email'=>$o['email']??'']);$user=$s->fetch();cli_require((bool)$user,'Fixture account missing.');
    if(isset($o['receipt'])) {
        $s=$pdo->prepare("INSERT INTO Receipts (File_Path,Original_Filename,Mime_Type,File_Size,OCR_Status,UploadedBy_UserID) VALUES ('fixture.png','fixture.png','image/png',1,'Processed',:id)");$s->execute(['id'=>$user['UserID']]);echo json_encode(['receipt_id'=>(int)$pdo->lastInsertId()]);exit;
    }
    cli_require(is_dir($o['sessions']??''),'Isolated session directory required.');
    session_save_path($o['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
    $code=mfa_generate_code();mfa_save_code($pdo,(int)$user['UserID'],$code);mfa_set_pending((int)$user['UserID'],mfa_mask_email($user['Email']));
    $out=['session'=>session_id(),'code'=>$code,'csrf'=>csrf_token()];session_write_close();echo json_encode($out);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
