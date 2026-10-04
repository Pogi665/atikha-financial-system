<?php
/** Synthetic Phase 3 checks. NEVER accepts the application database. */
require_once __DIR__.'/cli_common.php';
require_once __DIR__.'/../includes/report_snapshots.php';
require_once __DIR__.'/../includes/forecast_query.php';
require_once __DIR__.'/../includes/dashboard_query.php';
function ac(bool $ok,string $label): void { cli_require($ok,$label);echo "PASS: $label\n"; }
function ap(PDO $pdo,string $date,array $lines,string $status='posted'): int
{
    $pdo->beginTransaction();try{
        $s=$pdo->prepare('INSERT INTO journal_entries (entry_date,description,status) VALUES (?,?,?)');$s->execute([$date,'Synthetic <script>window.bad=1</script>',$status]);$id=(int)$pdo->lastInsertId();
        $s=$pdo->prepare('INSERT INTO journal_entry_lines (journal_entry_id,account_id,debit_amount,credit_amount) VALUES (?,?,?,?)');foreach($lines as $l){$s->execute(array_merge([$id],$l));}$pdo->commit();return $id;
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function reject(callable $fn,string $label,?int $status=null): void
{
    try{$fn();}catch(Throwable $e){ac($status===null||($e instanceof ReportProblem&&$e->status===$status),$label);return;}throw new RuntimeException('Unexpected acceptance: '.$label);
}
function request_for(PDO $pdo,int $year,int $month): array
{
    $tb=accounting_trial_balance($pdo,accounting_month_end($year,$month));return ['csrf_token'=>csrf_token(),'report_year'=>(string)$year,'report_month'=>(string)$month,'source_fingerprint'=>$tb['fingerprint'],'submission_key'=>report_submission_token($year,$month,$tb['fingerprint'])];
}
try{
    $opts=getopt('',['database:','worker:']);$db=$opts['database']??'';
    cli_require(is_string($db)&&(bool)preg_match('/\Aatikha_test_phase3_[a-z0-9]+\z/',$db),'Explicit disposable atikha_test_phase3_* database required.');
    if(isset($opts['worker'])){
        $path=realpath($opts['worker']);$private=realpath(__DIR__.'/../.migration-private');cli_require($path&&$private&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private fixture required.');
        $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$_SESSION=$fixture['session'];
        echo json_encode(report_snapshot_submit(cli_db($db),$fixture['uid'],$fixture['input']));exit;
    }
    $server=new PDO('mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1').';charset=utf8mb4',getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo=cli_db($db);
    cli_sql_file($pdo,__DIR__.'/../database.sql');foreach(glob(__DIR__.'/../migrations/*.sql') as $path){if(preg_match('/\A(\d{3})_/',basename($path),$m)&&(int)$m[1]<=17){cli_sql_file($pdo,$path);}}
    ac(report_snapshot_available($pdo),'Migration 017 rehearsed; snapshot tables empty and available');
    $empty=accounting_trial_balance($pdo,'2026-09-30');ac($empty['total_debits']==='0.00'&&$empty['total_credits']==='0.00'&&!$empty['invalid_journals']&&count($empty['rows'])>0,'Empty journal history preserves zero-balance accounts');
    foreach(['admin'=>'Admin','other'=>'Admin','management'=>'Management'] as $name=>$role){
        $s=$pdo->prepare('INSERT INTO Users (FullName,Email,Role,Password,Is_Active) VALUES (?,?,?,?,1)');$s->execute(['Accounting '.$name,$name.'@example.invalid',$role,'not-a-login-hash']);
    }
    $pdo->exec('INSERT INTO user_identities (UserID,FullName,Email,Role) SELECT UserID,FullName,Email,Role FROM Users');
    $users=$pdo->query('SELECT Email,UserID FROM Users')->fetchAll(PDO::FETCH_KEY_PAIR);$uid=(int)$users['admin@example.invalid'];$manager=(int)$users['management@example.invalid'];
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
    $accounts=[];foreach(['Bank'=>['Asset','Debit',1],'Receivable'=>['Asset','Debit',0],'Capital'=>['Equity','Credit',0],'Revenue'=>['Income','Credit',0],'Unpaid'=>['Liability','Credit',0],'Expense'=>['Expense','Debit',0],'Contra'=>['Asset','Credit',0],'Wallet'=>['Asset','Debit',1]] as $name=>[$type,$normal,$cash]){
        $s=$pdo->prepare('INSERT INTO Categories (Name,Type,Account_Type,Normal_Balance,Is_Cash_Account,Is_Active) VALUES (?,?,?,?,?,1)');$s->execute(['Fixture '.$name,['Income'=>'Fund','Expense'=>'Expense'][$type]??null,$type,$normal,$cash]);$accounts[$name]=(int)$pdo->lastInsertId();
    }
    $a=$accounts;$date='2026-09-05';
    ap($pdo,$date,[[$a['Bank'],'1000.00','0.00'],[$a['Capital'],'0.00','1000.00']]);
    ap($pdo,$date,[[$a['Receivable'],'250.00','0.00'],[$a['Revenue'],'0.00','250.00']]);
    ap($pdo,$date,[[$a['Expense'],'120.00','0.00'],[$a['Unpaid'],'0.00','120.00']]);
    $tb=accounting_trial_balance($pdo,'2026-09-30');$k=accounting_kpis($pdo,'2026-09-30');
    ac($k===['assets'=>'1250.00','income'=>'250.00','expenses'=>'120.00'],'Known KPIs distinguish credit income, unpaid expenses and assets');
    ac($tb['total_debits']==='1370.00'&&$tb['total_credits']==='1370.00'&&!$tb['invalid_journals'],'Known Trial Balance totals 1370.00 per side');
    $token=request_for($pdo,2026,9);$first=report_snapshot_submit($pdo,$uid,$token);$count=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    ac(report_snapshot_submit($pdo,$uid,$token)['duplicate']&&(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===$count,'Identical snapshot retries do not duplicate figures or audits');
    $bad=$token;$bad['source_fingerprint']=str_repeat('a',64);reject(fn()=>report_snapshot_submit($pdo,$uid,$bad),'Tampered signature rejected',400);
    $bad=$token;$bad['csrf_token']='wrong';reject(fn()=>report_snapshot_submit($pdo,$uid,$bad),'Invalid CSRF rejected',400);
    $stale=request_for($pdo,2026,9);ap($pdo,'2026-09-06',[[$a['Bank'],'0.10','0.00'],[$a['Revenue'],'0.00','0.10']]);
    reject(fn()=>report_snapshot_submit($pdo,$uid,$stale),'Backdated posting invalidates displayed submission',409);
    $pdo->exec('UPDATE Categories SET Account_Code=\'BANK\',Is_Active=0 WHERE CategoryID='.$a['Bank']);
    $frozen=report_snapshot_load_id($pdo,$first['id']);$bank=array_values(array_filter($frozen['rows'],fn($r)=>(int)$r['CategoryID']===$a['Bank']))[0];
    ac($bank['Account_Code']===null&&(int)$bank['Is_Active']===1&&$frozen['total_debits']==='1370.00','Frozen metadata and figures survive account changes and backdated posting');
    $_SESSION=['UserID'=>$manager,'Role'=>'Management'];$review=['csrf_token'=>csrf_token(),'entity_id'=>(string)$first['id'],'review_notes'=>'Reviewed original revision'];
    report_snapshot_review($pdo,$manager,$review);$again=report_snapshot_review($pdo,$manager,$review);ac($again['duplicate'],'Older revision can be reviewed; review retries retain original audit');
    $managerToken=request_for($pdo,2026,9);reject(fn()=>report_snapshot_submit($pdo,$manager,$managerToken),'Management cannot submit snapshots',403);
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];reject(fn()=>report_snapshot_review($pdo,$uid,['csrf_token'=>csrf_token(),'entity_id'=>(string)$first['id']]),'Admin cannot approve snapshots',403);
    $second=report_snapshot_submit($pdo,$uid,request_for($pdo,2026,9));ac((int)report_snapshot_load_id($pdo,$second['id'])['header']['revision']===2,'Resubmission creates revision 2 and preserves reviewed revision 1');
    // Two concurrent clients must allocate different immutable revisions.
    $private=__DIR__.'/../.migration-private';if(!is_dir($private)){mkdir($private);}$workers=[];
    for($i=0;$i<2;$i++){$input=request_for($pdo,2026,9);$path=$private.'/phase3-worker-'.bin2hex(random_bytes(4)).'.json';file_put_contents($path,json_encode(['session'=>$_SESSION,'uid'=>$uid,'input'=>$input]));$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--database='.$db,'--worker='.$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$proc,$pipes,$path];}
    $ids=[];foreach($workers as [$proc,$pipes,$path]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);unlink($path);cli_require($exit===0,'Worker failed: '.$err);$ids[]=json_decode($out,true,512,JSON_THROW_ON_ERROR)['id'];}
    $revs=array_map(fn($id)=>(int)report_snapshot_load_id($pdo,$id)['header']['revision'],$ids);sort($revs);ac($revs===[3,4],'Concurrent submissions allocate unique revisions');
    // Roll back header and lines if the required audit insert fails.
    $before=(int)$pdo->query('SELECT COUNT(*) FROM trial_balance_snapshots')->fetchColumn();$input=request_for($pdo,2026,9);
    $pdo->exec("CREATE TRIGGER phase3_fail_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    reject(fn()=>report_snapshot_submit($pdo,$uid,$input),'Audit failure rejects entire snapshot');$pdo->exec('DROP TRIGGER phase3_fail_audit');
    ac((int)$pdo->query('SELECT COUNT(*) FROM trial_balance_snapshots')->fetchColumn()===$before&&!$pdo->inTransaction(),'Audit failure leaves no partial snapshot transaction');
    ap($pdo,'2026-09-09',[[$a['Bank'],'900.00','0.00'],[$a['Revenue'],'0.00','900.00']],'draft');
    ap($pdo,'2026-11-01',[[$a['Bank'],'700.00','0.00'],[$a['Revenue'],'0.00','700.00']]);
    ac(accounting_kpis($pdo,'2026-09-30')['assets']==='1250.10','Drafts and future entries excluded; inactive Bank retained');
    ap($pdo,'2026-09-10',[[$a['Expense'],'20.00','0.00'],[$a['Contra'],'0.00','20.00']]);
    ap($pdo,'2026-09-11',[[$a['Wallet'],'50.00','0.00'],[$a['Bank'],'0.00','50.00']]);
    ap($pdo,'2026-09-12',[[$a['Bank'],'10.00','0.00'],[$a['Expense'],'0.00','10.00']]);
    $k=accounting_kpis($pdo,'2026-09-30');ac($k['assets']==='1240.10'&&$k['expenses']==='130.00','Contra assets, transfers and expense credits have correct signs');
    $all=accounting_records_filters(['from'=>'','to'=>'']);$records=accounting_records($pdo,$all);$cash=accounting_records($pdo,array_merge($all,['context'=>'crb']));
    ac(count($records['rows'])>10&&!array_filter($records['rows'],fn($r)=>$r['debit_amount']==='900.00'),'Ledger retains complete dataset and excludes drafts');
    ac(!array_filter($cash['rows'],fn($r)=>!in_array((int)$r['account_id'],[$a['Bank'],$a['Wallet']],true)),'CRB restricts to cash-flagged Asset debit lines');
    $one=accounting_records($pdo,array_merge($all,['account_id'=>(string)$a['Wallet']]));$j=$one['journals'][$one['rows'][0]['journal_id']];ac(count($j['lines'])===2,'Account filtering still supplies complete journal dialog');
    ap($pdo,'2026-08-01',[[$a['Expense'],'30.00','0.00'],[$a['Bank'],'0.00','30.00']]);
    ap($pdo,'2026-07-01',[[$a['Bank'],'100.00','0.00'],[$a['Expense'],'0.00','100.00']]);
    $series=accounting_monthly($pdo,['2026-04','2026-05','2026-06','2026-07','2026-08','2026-09']);ac($series[3]['expenses_decimal']==='-100.00'&&$series[5]['balance_decimal']===accounting_kpis($pdo,'2026-09-30')['assets'],'Monthly series retains reversals and cumulative Assets');
    $history=forecast_fetch_history($pdo,'2026-10-04');ac(forecast_history_is_sufficient($history)&&$history['basis']===FORECAST_BASIS,'Forecast sufficiency uses two positive closed net-expense months');
    ac($history['metrics']['runway_months']===null&&$history['metrics']['donor_concentration']===null,'Unsupported cash and donor metrics unavailable');
    $neg=$history;$neg['metrics']['recent_avg_expenses']=-12.34;ac(forecast_baseline_projection($neg)[0]['projected_expenses']===-12.34,'Signed baseline preserved');
    $projection=forecast_baseline_projection($history);$decoded=['basis'=>FORECAST_BASIS,'version'=>2,'chart_data'=>$projection,'risk_level'=>'UNKNOWN'];
    $s=$pdo->prepare('INSERT INTO forecast_cache (Data_Fingerprint,History_JSON,Forecast_JSON,Model) VALUES (?,?,?,?)');$s->execute([forecast_fingerprint($history),json_encode($history),json_encode($decoded),'fixture']);
    ac(forecast_cache_load($pdo,$history)!==null,'Matching basis, fingerprint and TTL cache accepted');
    $s=$pdo->prepare('UPDATE forecast_cache SET History_JSON=?');$s->execute([json_encode(['basis'=>FORECAST_BASIS,'version'=>2])]);ac(forecast_cache_load($pdo,$history)===null,'Incomplete cache history is rejected without warnings');$s->execute([json_encode($history)]);
    $changed=$history;$changed['budgets']['2026-10']=[['category'=>'Fixture Expense','amount'=>'1.00']];ac(forecast_cache_load($pdo,$changed)===null,'Changed budgets invalidate forecast cache');
    $pdo->exec('UPDATE forecast_cache SET created_at=NOW()-INTERVAL 25 HOUR');ac(forecast_cache_load($pdo,$history)===null,'Expired cache rejected');
    $bad=['chart_data'=>[['month'=>'2099-01','projected_expenses'=>INF]]];ac(!forecast_validate_projection($bad,$history['projection_months'],130,20)['valid'],'Malformed AI projection returns baseline');
    $signed=['chart_data'=>forecast_baseline_projection($neg)];ac(forecast_validate_projection($signed,$history['projection_months'],130,-12.34)['valid'],'Valid signed AI projection is accepted');
    $pdo->exec("INSERT INTO Budgets (Category,Year,Month,Amount) VALUES ('Fixture Expense',2026,9,100.00)");$u=budget_utilization($pdo,2026,9);$fixtureBudget=array_values(array_filter($u['by_category'],fn($r)=>$r['category']==='Fixture Expense'))[0];ac($u['spent']==='130.00'&&$fixtureBudget['budgeted']==='100.00'&&$fixtureBudget['remaining']==='-30.00','Budget spending follows posted Expense net amounts and preserves other allocations');
    // Offset corruption: overall difference is zero but individual journals are invalid.
    $bad1=ap($pdo,'2026-09-15',[[$a['Bank'],'1.00','0.00']]);$bad2=ap($pdo,'2026-09-15',[[$a['Revenue'],'0.00','1.00']]);$tb=accounting_trial_balance($pdo,'2026-09-30');
    ac($tb['difference']==='0.00'&&count($tb['invalid_journals'])===2,'Offsetting corrupt journals do not pass integrity checks');reject(fn()=>report_snapshot_submit($pdo,$uid,request_for($pdo,2026,9)),'Corrupted journals block submission',422);
    $pdo->exec('DELETE FROM journal_entry_lines WHERE journal_entry_id IN ('.$bad1.','.$bad2.')');$pdo->exec('DELETE FROM journal_entries WHERE id IN ('.$bad1.','.$bad2.')');
    reject(fn()=>accounting_cents('92233720368547758.08'),'Aggregate overflow rejected');reject(fn()=>accounting_add(PHP_INT_MAX,1),'Addition overflow rejected');
    ac(accounting_money('90071992547409.93')==='₱90,071,992,547,409.93','Large exact amounts format without floating-point loss');
    ac(accounting_today(new DateTimeImmutable('2026-09-30T16:01:00Z'))==='2026-10-01','Asia/Manila midnight boundary');
    $writer=cli_db($db);$seen=accounting_read($pdo,function()use($pdo,$writer,$a){$before=accounting_kpis($pdo,'2026-09-30');ap($writer,'2026-09-19',[[$a['Bank'],'1.00','0.00'],[$a['Revenue'],'0.00','1.00']]);return [$before,accounting_kpis($pdo,'2026-09-30')];});
    ac($seen[0]===$seen[1]&&accounting_kpis($pdo,'2026-09-30')!==$seen[0],'Repeatable reads resist concurrent committed posting');
    ap($pdo,'2025-01-01',[[$a['Bank'],'21.00','0.00'],[$a['Capital'],'0.00','21.00']]);$monthly=accounting_monthly($pdo,['2026-04','2026-05','2026-06','2026-07','2026-08','2026-09']);ac($monthly[0]['balance_decimal']==='21.00','Asset sparkline includes pre-window opening balances');
    $now=accounting_today();$y=(int)substr($now,0,4);$m=(int)substr($now,5,2);$before=accounting_cents(budget_mtd_spent($pdo,$y,$m));
    ap($pdo,$now,[[$a['Expense'],'2.00','0.00'],[$a['Bank'],'0.00','2.00']]);ap($pdo,accounting_next_day($now),[[$a['Expense'],'3.00','0.00'],[$a['Bank'],'0.00','3.00']]);ac(accounting_cents(budget_mtd_spent($pdo,$y,$m))===accounting_add($before,200),'Current budget spending stops at Manila today');
    $pdo->exec("CREATE TRIGGER phase3_fail_notification BEFORE INSERT ON Notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic notification failure'");$notify=report_snapshot_submit($pdo,$uid,request_for($pdo,2026,9));$pdo->exec('DROP TRIGGER phase3_fail_notification');ac($notify['warning']!==''&&report_snapshot_load_id($pdo,$notify['id'])!==null,'Notification failure warns after preserving committed snapshot');
    $input=request_for($pdo,2026,9);$pdo->exec('UPDATE Users SET Is_Active=0 WHERE UserID='.$uid);reject(fn()=>report_snapshot_submit($pdo,$uid,$input),'Inactive submitter rejected',401);$pdo->exec('UPDATE Users SET Is_Active=1 WHERE UserID='.$uid);
    echo "PASS: Disposable database retained for browser tests: $db\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
