<?php
require_once __DIR__.'/cli_common.php';
require_once __DIR__.'/../includes/mfa.php';
require_once __DIR__.'/../includes/csrf.php';
try {
    $o=getopt('',['database:','sessions:','email:','inspect','receipt','report-fixture','report-error:']);$db=$o['database']??'';
    cli_require((bool)preg_match('/\Aatikha_test_[a-z0-9_]+\z/',$db),'HTTP fixtures require disposable database.');$pdo=cli_db($db);
    if (isset($o['report-error'])) {
        cli_require(in_array($o['report-error'], ['on','off'], true), 'Expected on or off.');
        $pdo->exec($o['report-error'] === 'on' ? 'RENAME TABLE Expenses TO Expenses_unavailable' : 'RENAME TABLE Expenses_unavailable TO Expenses');
        echo json_encode(['ok'=>true]); exit;
    }
    if (isset($o['report-fixture'])) {
        $id = (int) $pdo->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid'")->fetchColumn();
        $year = (int) date('Y') - 1;
        $pdo->exec("INSERT INTO Categories (Name,Type) VALUES ('Panel fund','Fund'),('Panel expense','Expense') ON DUPLICATE KEY UPDATE Is_Active=1");
        $f = $pdo->prepare('INSERT INTO Incoming_Funds (Source_Donor,Category,Purpose,Project_Code,Amount,Date_Received,RecordedBy_UserID) VALUES (?,?,?,?,?,?,?)');
        $e = $pdo->prepare('INSERT INTO Expenses (Payee,Category,Purpose,Project_Code,Amount,Date_Incurred,RecordedBy_UserID) VALUES (?,?,?,?,?,?,?)');
        $f->execute(['Opening donor','Panel fund',null,null,'100.10',"$year-01-31",$id]);
        $e->execute(['Opening payee','Panel expense',null,null,'25.05',"$year-01-31",$id]);
        $f->execute(['Historical <donor>','Panel fund','Purpose <b> & verified','SHARED','10.01',"$year-02-01",$id]);
        $first = (int) $pdo->lastInsertId();
        $f->execute(['Legacy fund','Panel fund',null,null,'0.02',"$year-02-01",$id]);
        $legacyFund = (int) $pdo->lastInsertId();
        $e->execute(['Historical payee','Panel expense','Confirmed expense',null,'100.00',"$year-02-01",$id]);
        $e->execute(['Legacy expense','Panel expense',null,'SHARED','0.01',"$year-02-28",$id]);
        $legacyExpense = (int) $pdo->lastInsertId();
        echo json_encode(['year'=>$year,'first'=>$first,'fund'=>$legacyFund,'expense'=>$legacyExpense]); exit;
    }
    if(isset($o['inspect'])) {
        echo json_encode(['users'=>$pdo->query('SELECT UserID,Email,Role FROM Users ORDER BY UserID')->fetchAll(),
            'funds'=>$pdo->query('SELECT * FROM Incoming_Funds ORDER BY FundID')->fetchAll(),
            'expenses'=>$pdo->query('SELECT * FROM Expenses ORDER BY ExpenseID')->fetchAll(),
            'receipts'=>$pdo->query('SELECT * FROM Receipts ORDER BY ReceiptID')->fetchAll(),
            'messages'=>$pdo->query('SELECT * FROM Board_Communications ORDER BY CommunicationID')->fetchAll(),
            'audits'=>$pdo->query('SELECT module,old_values,new_values FROM audit_logs ORDER BY id')->fetchAll(),
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
