<?php
/** Synthetic Phase 2 checks. Requires an empty explicitly named disposable database. */
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/journal.php';
require_once __DIR__ . '/../includes/accounts.php';
$checks = 0;
function jt(bool $ok, string $label): void {
    global $checks; cli_require($ok, $label); $checks++; echo "PASS: $label\n";
}
function journal_counts(PDO $pdo): array {
    return array_map(fn($table) => (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(),
        ['journal_entries', 'journal_entry_lines', 'audit_logs']);
}
function rejected(PDO $pdo, callable $op, string $label, ?int $status = null): void {
    $before = journal_counts($pdo);
    try { $op(); } catch (Throwable $e) {
        jt($status === null || ($e instanceof JournalProblem && $e->status === $status), "$label rejected correctly");
        jt(journal_counts($pdo) === $before && !$pdo->inTransaction(), "$label leaves no partial header, lines or audit");
        return;
    }
    throw new RuntimeException("Unexpected acceptance: $label");
}
function sample_post(int $asset, int $income, string $amount = '123.45'): array {
    return ['csrf_token' => csrf_token(), 'submission_key' => journal_submission_key(), 'entry_date' => '2026-10-04',
        'reference' => 'Fixture', 'description' => 'Synthetic journal', 'line_count' => '2', 'form_complete' => '1',
        'lines' => [['account_id' => (string)$asset, 'fund_project_id' => '', 'debit_amount' => $amount, 'credit_amount' => ''],
            ['account_id' => (string)$income, 'fund_project_id' => (getenv('ATIKHA_TEST_INTEGRATED')==='1'?'':'4294967295'), 'debit_amount' => '', 'credit_amount' => $amount]]];
}
try {
    $opts = getopt('', ['database:', 'worker:']); $db = $opts['database'] ?? '';
    cli_require(is_string($db) && (bool)preg_match('/\Aatikha_test_journal_[a-z0-9]+\z/', $db), 'Explicit disposable atikha_test_journal_* database required.');
    if(getenv('ATIKHA_TEST_INTEGRATED')==='1'&&!isset($opts['worker'])){
        $server=new PDO('mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1'),getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'');$server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    $pdo = cli_db($db);
    if (isset($opts['worker'])) {
        $path = realpath($opts['worker']); $private = realpath(__DIR__ . '/../.migration-private');
        cli_require($path && $private && str_starts_with($path, $private . DIRECTORY_SEPARATOR), 'Worker fixture must be private.');
        $fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $_SESSION = $fixture['session'];
        echo json_encode(journal_post($pdo, (int)$_SESSION['UserID'], $fixture['post'])); exit;
    }
    cli_require($pdo->query('SHOW TABLES')->fetchAll() === [], 'Disposable database must be empty.');
    cli_sql_file($pdo, __DIR__ . '/../database.sql');
    foreach (glob(__DIR__ . '/../migrations/*.sql') as $path) {
        if (preg_match('/\A(\d{3})_/', basename($path), $m) && (int)$m[1] <= 15) { cli_sql_file($pdo, $path); }
    }
    $pdo->exec("INSERT INTO journal_entries (entry_date,description) VALUES ('2026-01-01','Preserved draft')");
    $old = $pdo->query('SELECT * FROM journal_entries')->fetch();
    cli_sql_file($pdo, __DIR__ . '/../migrations/016_journal_posting_metadata.sql');
    $new = $pdo->query('SELECT * FROM journal_entries')->fetch();
    jt(array_intersect_key($new, $old) === $old && $new['submission_key'] === null && $new['posted_by_user_id'] === null,
        'Migration 016 retains existing header values with nullable metadata');
    $pdo->exec('DELETE FROM journal_entries');
    if(getenv('ATIKHA_TEST_INTEGRATED')==='1'){
        foreach(['017_trial_balance_report_snapshots.sql','018_journal_receipt_evidence.sql','019_stage1_foundation.sql','020_stage2_advances.sql','021_journal_corrections.sql'] as $migration){
            if(str_starts_with($migration,'017_')){$files=glob(__DIR__.'/../migrations/017_*.sql');cli_sql_file($pdo,$files[0]);}else cli_sql_file($pdo,__DIR__.'/../migrations/'.$migration);
        }
        require_once __DIR__.'/../includes/stage2_common.php';require_once __DIR__.'/../includes/stage3_common.php';cli_require(stage3_schema($pdo)&&stage2_schema($pdo),'Journal operations require complete integrated schema.');echo "PROFILE: integrated journal operations on 019/020/021\n";
    }
    foreach (['admin' => ['Admin',1], 'other' => ['Admin',1], 'management' => ['Management',1], 'inactive' => ['Admin',0]] as $name => [$role,$active]) {
        $stmt = $pdo->prepare('INSERT INTO Users (FullName,Role,Email,Password,Is_Active) VALUES (?,?,?,?,?)');
        $stmt->execute(['Journal '.$name,$role,$name.'@example.invalid','not-a-login-hash',$active]);
    }
    $pdo->exec('INSERT INTO user_identities (UserID,FullName,Email,Role) SELECT UserID,FullName,Email,Role FROM Users');
    $users = $pdo->query('SELECT Email,UserID FROM Users')->fetchAll(PDO::FETCH_KEY_PAIR);
    $uid = (int)$users['admin@example.invalid']; $_SESSION = ['UserID'=>$uid, 'Role'=>'Admin'];
    $asset = account_save($pdo, $uid, 'create', ['name'=>'Fixture Cash <script>', 'account_type'=>'Asset','account_code'=>'1000', 'normal_balance'=>'Debit','is_cash_account'=>'1']);
    $income = account_save($pdo, $uid, 'create', ['name'=>'Fixture Revenue', 'account_type'=>'Income','account_code'=>'4000', 'normal_balance'=>'Credit','is_cash_account'=>'0']);
    foreach (['Liability','Equity','Expense'] as $type) {
        $id = account_save($pdo,$uid,'create',['name'=>'Fixture '.$type, 'account_type'=>$type,'account_code'=>'','normal_balance'=>$type==='Expense'?'Debit':'Credit','is_cash_account'=>'0']);
        $row = account_load($pdo,$id);
        jt($row['Account_Type']===$type && $row['Type']===($type==='Expense'?'Expense':null), "$type creation has correct legacy classification");
    }
    jt(account_load($pdo,$asset)['Type'] === null && account_load($pdo,$income)['Type'] === 'Fund', 'Asset and Income legacy compatibility fields');
    rejected($pdo,fn()=>account_save($pdo,$uid,'create',['name'=>'Bad cash','account_type'=>'Income','account_code'=>'','normal_balance'=>'Credit','is_cash_account'=>'1']), 'Cash flag on non-Asset');
    rejected($pdo,fn()=>account_save($pdo,$uid,'create',['name'=>'Duplicate code','account_type'=>'Asset','account_code'=>'1000','normal_balance'=>'Debit','is_cash_account'=>'0']), 'Duplicate account code');
    rejected($pdo,fn()=>account_save($pdo,$uid,'update',['account_id'=>$asset,'account_code'=>'1000','normal_balance'=>'Debit','is_cash_account'=>'1','description'=>['bad']]), 'Array text field');
    $post = sample_post($asset,$income);
    if(getenv('ATIKHA_TEST_INTEGRATED')==='1'){$forgedProject=$post;$forgedProject['lines'][1]['fund_project_id']='4294967295';rejected($pdo,fn()=>journal_post($pdo,$uid,$forgedProject),'Integrated legacy route rejects project allocation');}
    $result = journal_post($pdo,$uid,$post);
    jt(!$result['duplicate'], 'Balanced journal posted');
    $header = $pdo->query('SELECT * FROM journal_entries WHERE id='.$result['id'])->fetch();
    jt($header['status']==='posted' && (int)$header['posted_by_user_id']===$uid && strlen($header['submission_hash'])===64, 'Posted header stores actor and durable hash');
    $lines = $pdo->query('SELECT * FROM journal_entry_lines WHERE journal_entry_id='.$result['id'].' ORDER BY id')->fetchAll();
    jt(count($lines)===2 && $lines[0]['debit_amount']==='123.45' && $lines[0]['credit_amount']==='0.00' && $lines[0]['fund_project_id']===null && (getenv('ATIKHA_TEST_INTEGRATED')==='1'?$lines[1]['fund_project_id']===null:(int)$lines[1]['fund_project_id']===4294967295), 'Exact amounts and optional fund ID retained');
    $counts = journal_counts($pdo); $again = journal_post($pdo,$uid,$post);
    jt($again['duplicate'] && $again['id']===$result['id'] && journal_counts($pdo)===$counts, 'Identical retry creates no duplicate header, lines or audit');
    $changed = $post; $changed['description'] = 'Different payload';
    rejected($pdo,fn()=>journal_post($pdo,$uid,$changed), 'Reused key with changed payload',409);
    $changed = $post; $changed['csrf_token'] = 'invalid'; rejected($pdo,fn()=>journal_post($pdo,$uid,$changed),'Invalid CSRF',400);
    $changed = $post; $changed['submission_key'] = str_repeat('a',64); rejected($pdo,fn()=>journal_post($pdo,$uid,$changed),'Invalid signature',400);
    foreach (['management','inactive'] as $name) {
        $_SESSION = ['UserID'=>(int)$users[$name.'@example.invalid']]; $bad = sample_post($asset,$income);
        rejected($pdo,fn()=>journal_post($pdo,(int)$_SESSION['UserID'],$bad), "$name actor",403);
    }
    $_SESSION = ['UserID'=>$uid];
    foreach (['-1','+1','1e2','1,000','0.001','NaN','10000000000000.00','1.',''] as $amount) {
        $bad = sample_post($asset,$income,$amount); rejected($pdo,fn()=>journal_post($pdo,$uid,$bad), "Invalid money '$amount'",422);
    }
    foreach (['both'=>'1.00', 'negative'=>'-1.00'] as $label=>$value) {
        $bad=sample_post($asset,$income);$bad['lines'][0]['credit_amount']=$value;
        rejected($pdo,fn()=>journal_post($pdo,$uid,$bad), "$label sides",422);
    }
    $bad=sample_post($asset,$income);$bad['lines'][1]['credit_amount']='123.46'; rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Unbalanced totals',422);
    $bad=sample_post($asset,$income);unset($bad['form_complete']);rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Truncated submission',422);
    $bad=sample_post($asset,$income);$bad['line_count']='3';rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Mismatched line count',422);
    $bad=sample_post($asset,$income);$bad['entry_date']='2026-02-30';rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Invalid date',422);
    $bad=sample_post($asset,$income);$bad['lines'][0]['account_id']='4294967295';rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Nonexistent account',422);
    $bad=sample_post($asset,$income);$bad['lines'][0]['fund_project_id']='4294967296';rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Fund ID overflow',422);
    account_save($pdo,$uid,'disable',['account_id'=>$income]);
    $bad=sample_post($asset,$income);rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),'Inactive account',422);
    account_save($pdo,$uid,'enable',['account_id'=>$income]);
    foreach (['account_code'=>'1001','normal_balance'=>'Credit','is_cash_account'=>'0'] as $field=>$value) {
        $input=['account_id'=>$asset,'account_code'=>'1000','normal_balance'=>'Debit','is_cash_account'=>'1'];$input[$field]=$value;
        rejected($pdo,fn()=>account_save($pdo,$uid,'update',$input), "Posted account $field change");
    }
    account_save($pdo,$uid,'update',['account_id'=>$asset,'name'=>'Forged name','account_type'=>'Liability','account_code'=>'1000','normal_balance'=>'Debit','is_cash_account'=>'1','description'=>'Allowed metadata']);
    jt(account_load($pdo,$asset)['Name']==='Fixture Cash <script>' && account_load($pdo,$asset)['Account_Type']==='Asset', 'Posted account metadata editable, name/type immutable');
    $blank = account_save($pdo,$uid,'create',['name'=>'Uncoded','account_type'=>'Asset','account_code'=>'','normal_balance'=>'Debit','is_cash_account'=>'0']);
    journal_post($pdo,$uid,sample_post($blank,$income));
    account_save($pdo,$uid,'update',['account_id'=>$blank,'account_code'=>'1099','normal_balance'=>'Debit','is_cash_account'=>'0']);
    jt(account_load($pdo,$blank)['Account_Code']==='1099','Posted account with NULL code can be assigned once');
    rejected($pdo,fn()=>account_save($pdo,$uid,'update',['account_id'=>$blank,'account_code'=>'1100','normal_balance'=>'Debit','is_cash_account'=>'0']),'Assigned posted code cannot change');
    foreach (['journal_entries'=>'1','journal_entry_lines'=>"NEW.credit_amount > 0",'audit_logs'=>"NEW.module='General Journal'"] as $table=>$condition) {
        $pdo->exec("CREATE TRIGGER jt_fail BEFORE INSERT ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected test failure'; END IF; END");
        $bad=sample_post($asset,$income);
        rejected($pdo,fn()=>journal_post($pdo,$uid,$bad),"Injected $table insert failure");
        $pdo->exec('DROP TRIGGER jt_fail');
    }
    $large=sample_post($asset,$income,'9999999999999.99');$large['lines']=[];
    for($i=0;$i<100;$i++){$large['lines'][]=['account_id'=>(string)($i%2?$income:$asset),'fund_project_id'=>'','debit_amount'=>$i%2?'':'9999999999999.99','credit_amount'=>$i%2?'9999999999999.99':''];}
    $large['line_count']='100';$r=journal_post($pdo,$uid,$large);
    jt((int)$pdo->query('SELECT COUNT(*) FROM journal_entry_lines WHERE journal_entry_id='.$r['id'])->fetchColumn()===100,'100 maximum-value lines posted with exact integer math');
    $audit=$pdo->query("SELECT new_values FROM audit_logs WHERE module='General Journal' AND record_id=".$r['id'])->fetchColumn();
    jt(count(json_decode($audit,true)['lines'])===100,'All 100 lines captured in audit');
    $tooMany=$large;$tooMany['submission_key']=journal_submission_key();$tooMany['lines'][]=$large['lines'][0];$tooMany['line_count']='101';rejected($pdo,fn()=>journal_post($pdo,$uid,$tooMany),'101 lines',422);
    $path=__DIR__.'/../.migration-private/journal-race-'.bin2hex(random_bytes(5)).'.json';
    file_put_contents($path,json_encode(['session'=>$_SESSION,'post'=>sample_post($asset,$income)]));
    $before=journal_counts($pdo);$workers=[];
    for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--database='.$db,'--worker='.realpath($path)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $results=[];
    foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cli_require(proc_close($process)===0,$err);$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    unlink($path);$after=journal_counts($pdo);
    jt($results[0]['id']===$results[1]['id'] && $results[0]['duplicate']!==$results[1]['duplicate'] && $after===[$before[0]+1,$before[1]+2,$before[2]+1], 'Concurrent identical requests produce exactly one journal and audit');
    foreach(['Incoming_Funds','Expenses','Receipts','Reports','forecast_cache'] as $table){jt((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()===0,"$table untouched by journal posting");}
    jt(journal_today()===(new DateTimeImmutable('today',new DateTimeZone('Asia/Manila')))->format('Y-m-d'),'Entry date uses Asia/Manila');
    echo "Journal checks passed: $checks\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
