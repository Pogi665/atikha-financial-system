<?php
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/mfa.php';
require_once __DIR__ . '/../includes/user_session.php';
$checks = 0; $server = null;
function check(bool $ok, string $label): void { global $checks; cli_require($ok, $label); $checks++; echo "PASS: $label\n"; }
function rejects(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable $e) { check(true, $label); return; }
    throw new RuntimeException($label);
}
function account_http(string $path, ?array $post = null, string $role = 'Admin'): array {
    global $port, $fixture;
    $c = curl_init("http://127.0.0.1:$port/$path");
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEFILE=>"$fixture/$role.cookies", CURLOPT_COOKIEJAR=>"$fixture/$role.cookies", CURLOPT_TIMEOUT=>10]);
    if ($post !== null) { curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($c); $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE); curl_close($c);
    cli_require($body !== false, 'HTTP request failed'); return [$code, $body];
}
try {
    $options = getopt('', ['database:', 'port:']); $db = $options['database'] ?? ''; $port = (int) ($options['port'] ?? 8133);
    cli_require((bool) preg_match('/\Aatikha_test_[a-z0-9_]+\z/', $db), 'Explicit disposable database required');
    $pdo = cli_db($db); cli_require($pdo->query('SHOW TABLES')->fetchAll() === [], 'Empty database required');
    cli_sql_file($pdo, __DIR__ . '/../database.sql');
    foreach (glob(__DIR__ . '/../migrations/*.sql') as $file) { cli_sql_file($pdo, $file); }
    cli_sql_file($pdo, __DIR__ . '/../migrations/013_user_account_status.sql');
    check((bool) $pdo->query("SHOW COLUMNS FROM Users LIKE 'Is_Active'")->fetch(), 'Fresh schema and migration rerun');
    $user = bootstrap_admin($pdo, 'Fixture Admin', 'admin@example.invalid', 'FixturePass123!');
    // Rehearse an upgrade from the previous Users shape on this disposable fixture only.
    $beforeMigration = $pdo->query('SELECT UserID, FullName, Email, Role, Password FROM Users')->fetchAll();
    $pdo->exec('ALTER TABLE Users DROP COLUMN Is_Active');
    cli_sql_file($pdo, __DIR__ . '/../migrations/013_user_account_status.sql');
    check($pdo->query('SELECT UserID, FullName, Email, Role, Password FROM Users')->fetchAll() === $beforeMigration
        && (int) users_load($pdo, $user)['Is_Active'] === 1, 'Existing user migration preserves credentials and defaults active');
    $input = ['full_name'=>'Fixture <b> & "name"', 'email'=>'management@example.invalid', 'role'=>'Management', 'password'=>'FixturePass123!'];
    $management = users_save($pdo, $user, 'create_user', $input);
    $id = $management;
    $hash = $pdo->query("SELECT Password FROM Users WHERE UserID=$id")->fetchColumn();
    check(password_verify($input['password'], $hash) && $hash !== $input['password'], 'Creation hashes password');
    $identity = $pdo->query("SELECT * FROM user_identities WHERE UserID=$id")->fetch();
    $pdo->exec("INSERT INTO Expenses (Payee, Category, Purpose, Amount, Date_Incurred, RecordedBy_UserID) VALUES ('Fixture','Office','Test',12.34,'2025-01-01',$id)");
    $history = $pdo->query('SELECT * FROM Expenses')->fetchAll();
    $auditBefore = $pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll();
    foreach ([['full_name'=>''], ['full_name'=>str_repeat('x',256)], ['email'=>'invalid'], ['email'=>str_repeat('a',256).'@example.invalid'], ['role'=>'Staff'], ['full_name'=>[]], ['password'=>[]], ['password'=>'short']] as $bad) {
        rejects(fn()=>users_save($pdo,$user,'create_user',array_replace($input,$bad)), 'Invalid create input rejected');
    }
    rejects(fn()=>users_save($pdo,$user,'create_user',$input), 'Duplicate email rejected');
    rejects(fn()=>users_save($pdo,$user,'disable_user',['user_id'=>999999]), 'Missing user rejected');
    rejects(fn()=>users_save($pdo,$user,'disable_user',['user_id'=>[]]), 'Malformed ID rejected');
    rejects(fn()=>users_save($pdo,$user,'disable_user',['user_id'=>$user]), 'Self disable rejected');
    rejects(fn()=>users_save($pdo,$user,'update_user',array_replace($input,['user_id'=>$user,'email'=>'admin@example.invalid'])), 'Self demotion rejected');
    rejects(fn()=>users_save($pdo,$management,'disable_user',['user_id'=>$user]), 'Non-admin actor rejected');
    $edit = array_replace($input,['user_id'=>$id,'password'=>'','full_name'=>'Edited user']);
    users_save($pdo,$user,'update_user',$edit);
    check($pdo->query("SELECT Password FROM Users WHERE UserID=$id")->fetchColumn()===$hash, 'Blank password preserves hash');
    users_save($pdo,$user,'update_user',array_replace($edit,['password'=>'ChangedPass123!']));
    check(password_verify('ChangedPass123!', $pdo->query("SELECT Password FROM Users WHERE UserID=$id")->fetchColumn()), 'Optional password reset hashes replacement');
    mfa_save_code($pdo,$id,'123456');
    users_save($pdo,$user,'disable_user',['user_id'=>$id]);
    check(mfa_check_code($pdo,$id,'123456')==='invalid' && $pdo->query("SELECT MFA_Code FROM Users WHERE UserID=$id")->fetchColumn()===null, 'Disable clears and rejects MFA');
    check(!mfa_issue_and_send($pdo,$id,$input['email'],'Fixture'), 'Disabled MFA issuance rejected before mail');
    $count = $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    users_save($pdo,$user,'disable_user',['user_id'=>$id]);
    check($pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===$count, 'Repeated disable is no-op');
    $_SESSION=['UserID'=>$id,'Role'=>'Admin'];
    check(!user_session_validate($pdo) && $_SESSION===[], 'Disabled stale session cleared');
    users_save($pdo,$user,'reactivate_user',['user_id'=>$id]);
    $_SESSION=['UserID'=>$id,'Role'=>'Admin'];
    check(user_session_validate($pdo) && $_SESSION['Role']==='Management', 'Session role refreshed from database');
    check($pdo->query("SELECT * FROM user_identities WHERE UserID=$id")->fetch()===$identity && $pdo->query('SELECT * FROM Expenses')->fetchAll()===$history, 'Historical identity and financial references preserved');
    check(array_slice($pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(),0,count($auditBefore))===$auditBefore, 'Existing audit records preserved');
    $pdo->exec("CREATE TRIGGER fixture_reject_user_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture audit outage'");
    try {
        rejects(fn()=>users_save($pdo,$user,'disable_user',['user_id'=>$id]), 'Audit outage rejects status change');
        check((int)users_load($pdo,$id)['Is_Active']===1 && !$pdo->inTransaction(), 'Audit failure rolls back status');
        rejects(fn()=>users_save($pdo,$user,'create_user',array_replace($input,['email'=>'rollback@example.invalid'])), 'Audit outage rejects creation');
        check((int)$pdo->query("SELECT COUNT(*) FROM Users WHERE Email='rollback@example.invalid'")->fetchColumn()===0 && (int)$pdo->query("SELECT COUNT(*) FROM user_identities WHERE Email='rollback@example.invalid'")->fetchColumn()===0, 'Account and identity creation roll back');
    } finally { $pdo->exec('DROP TRIGGER fixture_reject_user_audit'); }
    // Serve copies only: the production connector and sessions are never changed.
    $fixture = sys_get_temp_dir() . '/atikha_users_' . bin2hex(random_bytes(6));
    mkdir($fixture); mkdir("$fixture/includes"); mkdir("$fixture/assets"); mkdir("$fixture/assets/js"); mkdir("$fixture/sessions");
    foreach (['admin_users.php', 'admin_accounts.php', 'financial_records.php', 'dashboard.php', 'reports.php', 'audit_trail.php', 'audit_ai.php', 'forecast_ai.php', 'ocr_extract.php', 'notifications_api.php', 'review_actions.php', 'resolve_reset.php', 'auth.php', 'mfa_verify.php', 'mfa_resend.php'] as $file) { copy(__DIR__ . '/../' . $file, "$fixture/$file"); }
    foreach (glob(__DIR__ . '/../includes/*.php') as $file) { copy($file, "$fixture/includes/" . basename($file)); }
    foreach (glob(__DIR__ . '/../assets/js/*.js') as $file) { copy($file, "$fixture/assets/js/" . basename($file)); }
    file_put_contents("$fixture/includes/mailer.php", '<?php function send_mfa_email(...$args): bool { return true; }');
    $connection = ['mysql:host=' . (getenv('ATIKHA_DB_HOST') ?: '127.0.0.1') . ';dbname=' . $db . ';charset=utf8mb4', getenv('ATIKHA_DB_USER') ?: 'root', getenv('ATIKHA_DB_PASSWORD') ?: ''];
    file_put_contents("$fixture/db_connect.php", '<?php $pdo = new PDO(' . implode(', ', array_map(fn($v) => var_export($v, true), $connection)) . ', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);');
    file_put_contents("$fixture/__fixture_login.php", '<?php session_start(); $role = ($_GET["role"] ?? "Admin") === "Management" ? "Management" : "Admin"; $_SESSION = ["UserID" => $role === "Admin" ? ' . $user . ' : ' . $management . ', "Role" => $role, "FullName" => "Fixture " . $role, "csrf_token" => "fixture-csrf"]; header("Location: admin_accounts.php");');
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $fixture . '/sessions', '-S', "127.0.0.1:$port", '-t', $fixture],
        [0 => ['pipe', 'r'], 1 => ['file', "$fixture/server.log", 'a'], 2 => ['file', "$fixture/server.log", 'a']], $pipes, $fixture);
    cli_require(is_resource($server), 'Could not start fixture server.');
    for ($i = 0; $i < 30; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($socket) { fclose($socket); break; } usleep(100000);
    }

    check(account_http('admin_users.php',null,'Anonymous')[0]===302,'Anonymous redirected');
    account_http('__fixture_login.php'); account_http('__fixture_login.php?role=Management',null,'Management');
    check(account_http('admin_users.php',null,'Management')[0]===403,'Management denied user list');
    check(account_http('admin_users.php',['action'=>'disable_user','user_id'=>$user,'csrf_token'=>'fixture-csrf'],'Management')[0]===403,'Management mutation denied');
    $count=$pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn();
    foreach ([[], ['csrf_token'=>'bad'], ['csrf_token'=>[]]] as $csrf) { account_http('admin_users.php',array_replace($input,['action'=>'create_user','email'=>'csrf@example.invalid'],$csrf)); }
    check($pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn()===$count,'Missing, invalid and array CSRF rejected');
    account_http('admin_users.php?action=disable_user&user_id='.$id);
    check((int)users_load($pdo,$id)['Is_Active']===1,'GET cannot disable');
    $httpInput=array_replace($input,['action'=>'create_user','email'=>'http@example.invalid','role'=>'Admin','csrf_token'=>'fixture-csrf']);
    check(account_http('admin_users.php',$httpInput)[0]===302,'Create HTTP redirects');
    $httpId=(int)$pdo->query("SELECT UserID FROM Users WHERE Email='http@example.invalid'")->fetchColumn();
    [$code,$html]=account_http('admin_users.php');
    check($code===200 && str_contains($html,'Fixture &lt;b&gt; &amp; &quot;name&quot;') && !str_contains($html,$hash),'List escapes text and excludes hashes');
    check(str_contains($html,'id="users-tab"') && str_contains($html,'id="resets-tab"') && str_contains($html,'id="pending-reset-count"'),'User management tabs and reset count render');
    check(str_contains($html,'class="user-avatar"') && str_contains($html,'<th scope="col" class="px-6 py-3 font-semibold text-slate-600 whitespace-nowrap">User</th>') && str_contains($html,'status-active'),'Combined user table and status styling render');
    check(str_contains($html,'total users') && str_contains($html,'users_filter_toolbar') === false,'Total user count renders without leaking implementation details');
    check(str_contains(account_http('admin_users.php?tab=resets')[1],'No pending requests'),'Reset tab renders concise empty state');
    [$code,$html]=account_http('admin_users.php?edit='.$httpId);
    check($code===200 && str_contains($html,'New password (optional)') && str_contains($html,'data-open-modal="user"') && !str_contains($html,'value="FixturePass123!"'),'Edit password stays blank and opens modal');
    check(account_http('admin_users.php',array_replace($httpInput,['action'=>'update_user','user_id'=>$httpId,'password'=>'','full_name'=>'HTTP updated']))[0]===302,'Edit HTTP succeeds');
    check(str_contains(account_http('admin_users.php?q=HTTP&role=Admin&status=active')[1],'HTTP updated') && !str_contains(account_http('admin_users.php?q=nomatch')[1],'HTTP updated'),'List filters work');
    $pdo->exec("INSERT INTO password_resets (UserID, Email, ip_address) VALUES ($id,'management@example.invalid','127.0.0.1')");
    $resetId=(int)$pdo->lastInsertId();
    check(str_contains(account_http('admin_users.php')[1],'data-reset-id="'.$resetId.'"'),'Pending reset remains visible');
    check(account_http('resolve_reset.php',['reset_id'=>$resetId,'new_password'=>'ResetPass123!','csrf_token'=>'fixture-csrf'])[0]===200 && password_verify('ResetPass123!',$pdo->query("SELECT Password FROM Users WHERE UserID=$id")->fetchColumn()),'Existing reset workflow works');
    // Fresh cookies per route prove each guard rejects an already-issued session.
    foreach (['admin_users.php','admin_accounts.php','financial_records.php','dashboard.php','reports.php','audit_trail.php'] as $page) {
        account_http('__fixture_login.php?role=Management',null,'Management');
        users_save($pdo,$user,'disable_user',['user_id'=>$id]);
        check(account_http($page,null,'Management')[0]===302,"Disabled session blocked: $page");
        users_save($pdo,$user,'reactivate_user',['user_id'=>$id]);
    }
    foreach (['audit_ai.php','forecast_ai.php','ocr_extract.php','notifications_api.php','review_actions.php','resolve_reset.php'] as $page) {
        account_http('__fixture_login.php?role=Management',null,'Management');
        users_save($pdo,$user,'disable_user',['user_id'=>$id]);
        [$code,$body]=account_http($page,['csrf_token'=>'fixture-csrf'],'Management');
        check($code===401 && is_array(json_decode($body,true)),"Disabled JSON session blocked: $page");
        users_save($pdo,$user,'reactivate_user',['user_id'=>$id]);
    }
    account_http('__fixture_login.php?role=Management',null,'Management');
    users_save($pdo,$user,'update_user',array_replace($edit,['role'=>'Admin']));
    check(account_http('admin_users.php',null,'Management')[0]===200,'Promotion applies to existing session');
    users_save($pdo,$user,'update_user',$edit);
    check(account_http('admin_users.php',null,'Management')[0]===403,'Demotion applies to existing session');
    users_save($pdo,$user,'disable_user',['user_id'=>$id]);
    account_http('auth.php',['email'=>'management@example.invalid','password'=>'ResetPass123!'],'Login');
    check(!str_contains(account_http('mfa_verify.php',null,'Login')[1],'Verify your identity'),'Disabled password login cannot enter MFA');
    users_save($pdo,$user,'reactivate_user',['user_id'=>$id]);
    account_http('auth.php',['email'=>'management@example.invalid','password'=>'ResetPass123!'],'Login');
    check(str_contains(account_http('mfa_verify.php',null,'Login')[1],'Verify your identity'),'Reactivation restores password login');
    users_save($pdo,$user,'disable_user',['user_id'=>$id]);
    $pendingHtml=account_http('mfa_verify.php',null,'Login')[1];
    preg_match('/name="csrf_token" value="([^"]+)"/', $pendingHtml, $csrfMatch);
    $pendingCsrf=$csrfMatch[1] ?? '';
    check($pendingCsrf !== '', 'MFA fixture has a real CSRF token');
    check(json_decode(account_http('mfa_resend.php',['csrf_token'=>$pendingCsrf],'Login')[1],true)['ok']===false,'Disabled pending MFA resend rejected');
    users_save($pdo,$user,'reactivate_user',['user_id'=>$id]);
    account_http('auth.php',['email'=>'management@example.invalid','password'=>'ResetPass123!'],'Login');
    $pendingHtml=account_http('mfa_verify.php',null,'Login')[1];
    preg_match('/name="csrf_token" value="([^"]+)"/', $pendingHtml, $csrfMatch);
    $codeBeforeDisable=$pdo->query("SELECT MFA_Code FROM Users WHERE UserID=$id")->fetchColumn();
    users_save($pdo,$user,'disable_user',['user_id'=>$id]);
    account_http('mfa_verify.php',['csrf_token'=>$csrfMatch[1], 'mfa_code'=>$codeBeforeDisable],'Login');
    check(account_http('admin_users.php',null,'Login')[0]===302,'Disable between password and MFA blocks session creation');
    users_save($pdo,$user,'reactivate_user',['user_id'=>$id]);
    $pdo->exec('ALTER TABLE Users CHANGE COLUMN Is_Active Fixture_Active TINYINT(1) NOT NULL DEFAULT 1');
    try {
        check(account_http('admin_users.php')[0]===503,'HTML schema failure fails closed');
        check(account_http('notifications_api.php',['csrf_token'=>'fixture-csrf'])[0]===503,'JSON schema failure fails closed');
    } finally { $pdo->exec('ALTER TABLE Users CHANGE COLUMN Fixture_Active Is_Active TINYINT(1) NOT NULL DEFAULT 1'); }
    echo "PASS: $checks assertions\nFixture retained: $fixture\nDatabase: $db\n";
} catch (Throwable $e) { fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n"); $failed=true; }
finally { if (is_resource($server)) { proc_terminate($server); proc_close($server); } }
exit(isset($failed)?1:0);
