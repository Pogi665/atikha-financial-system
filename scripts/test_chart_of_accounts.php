<?php
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/accounts.php';
require_once __DIR__ . '/../includes/admin_bootstrap.php';

$checks = 0;
$server = null;
function account_test(bool $ok, string $label): void
{
    global $checks;
    cli_require($ok, $label);
    $checks++;
    echo "PASS: $label\n";
}
function account_test_reject(callable $callback, string $label): void
{
    try { $callback(); } catch (Throwable $e) { account_test(true, $label); return; }
    throw new RuntimeException($label);
}
function account_http(string $path, ?array $post = null, string $role = 'Admin'): array
{
    global $port, $fixture;
    $curl = curl_init("http://127.0.0.1:$port/$path");
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => "$fixture/$role.cookies", CURLOPT_COOKIEJAR => "$fixture/$role.cookies", CURLOPT_TIMEOUT => 10]);
    if ($post !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    cli_require($body !== false, 'HTTP request failed: ' . $error);
    return [$code, $body];
}
try {
    $options = getopt('', ['database:', 'port:']);
    $db = $options['database'] ?? '';
    cli_require((bool) preg_match('/\Aatikha_test_[a-z0-9_]+\z/', $db), 'Empty disposable atikha_test_* database required.');
    $port = (int) ($options['port'] ?? 8132);
    cli_require($port >= 1024 && $port <= 65535, 'Invalid test port.');
    $pdo = cli_db($db);
    cli_require($pdo->query('SHOW TABLES')->fetchAll() === [], 'Empty disposable database required.');
    cli_sql_file($pdo, __DIR__ . '/../database.sql');
    foreach (glob(__DIR__ . '/../migrations/*.sql') as $path) {
        if (!str_starts_with(basename($path), '012_')) { cli_sql_file($pdo, $path); }
    }
    // Existing forms require these pre-existing columns, absent from the old SQL bootstrap.
    // Fixture compatibility only; migration 012 does not change transaction schemas.
    foreach (['Expenses', 'Incoming_Funds'] as $table) {
        if (!$pdo->query("SHOW COLUMNS FROM $table LIKE 'Reference_Number'")->fetch()) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN Reference_Number VARCHAR(100) NULL");
        }
    }
    $user = bootstrap_admin($pdo, 'Fixture Admin', 'admin@example.invalid', bin2hex(random_bytes(24)));
    $pdo->exec("INSERT INTO Users (FullName, Role, Email, Password) VALUES ('Fixture Management','Management','management@example.invalid','not-a-login-hash')");
    $management = (int) $pdo->lastInsertId();
    user_identity_create($pdo, $management);
    $pdo->exec("INSERT INTO Expenses (Payee, Category, Purpose, Amount, Date_Incurred, RecordedBy_UserID) VALUES ('Fixture','Legacy missing','Original purpose',12.34,'2025-01-01',$user)");
    $expenseId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO Incoming_Funds (Source_Donor, Category, Purpose, Amount, Date_Received, RecordedBy_UserID) VALUES ('Fixture','Legacy income','Original purpose',56.78,'2025-01-01',$user)");
    $fundId = (int) $pdo->lastInsertId();
    $before = [];
    foreach (['Categories', 'Expenses', 'Incoming_Funds'] as $table) { $before[$table] = $pdo->query("SELECT * FROM $table")->fetchAll(); }
    cli_sql_file($pdo, __DIR__ . '/../migrations/012_chart_of_accounts.sql');
    foreach ($before as $table => $rows) {
        $after = $pdo->query("SELECT * FROM $table")->fetchAll();
        account_test(count($after) === count($rows), "$table row count preserved");
        foreach ($rows as $i => $row) { cli_require(array_intersect_key($after[$i], $row) === $row, "$table values changed"); }
        account_test(true, "$table original values preserved");
    }
    account_test((int) $pdo->query('SELECT COUNT(*) FROM Categories WHERE Detail_Type IS NOT NULL OR Description IS NOT NULL')->fetchColumn() === 0, 'No fabricated metadata');
    $input = ['name' => 'Test <b> & "account"', 'type' => 'Expense', 'detail_type' => 'Office', 'description' => 'Description <script>alert(1)</script>'];
    $id = account_save($pdo, $user, 'create', $input);
    $income = account_save($pdo, $user, 'create', ['name' => 'Test income', 'type' => 'Fund']);
    account_test(in_array($input['name'], fetch_category_names($pdo, 'Expense'), true) && !in_array($input['name'], fetch_category_names($pdo, 'Fund'), true), 'Active accounts filtered by type');
    account_save($pdo, $user, 'update', ['account_id' => $id, 'name' => 'Forged rename', 'type' => 'Fund', 'detail_type' => 'Updated', 'description' => $input['description']]);
    $row = account_load($pdo, $id);
    account_test($row['Name'] === $input['name'] && $row['Type'] === 'Expense' && $row['Detail_Type'] === 'Updated', 'Metadata editable; historical identifiers immutable');
    account_save($pdo, $user, 'disable', ['account_id' => $id]);
    account_test(!in_array($input['name'], fetch_category_names($pdo, 'Expense'), true), 'Disabled account excluded');
    account_test_reject(fn() => account_save($pdo, $user, 'create', $input), 'Inactive duplicate rejected');
    account_save($pdo, $user, 'enable', ['account_id' => $id]);
    account_test(in_array($input['name'], fetch_category_names($pdo, 'Expense'), true), 'Reactivation restores choice');
    account_test_reject(fn() => account_save($pdo, $user, 'create', $input), 'Active duplicate rejected');
    account_test_reject(fn() => account_save($pdo, $user, 'create', ['name' => '', 'type' => 'Fund']), 'Blank name rejected');
    account_test_reject(fn() => account_save($pdo, $user, 'create', ['name' => 'Invalid', 'type' => 'Asset']), 'Invalid type rejected');
    account_test_reject(fn() => account_save($pdo, $user, 'update', ['account_id' => $id, 'description' => str_repeat('x', 2001)]), 'Overlength description rejected');
    account_test_reject(fn() => account_save($pdo, $user, 'update', ['account_id' => -1]), 'Invalid account ID rejected');
    account_test_reject(fn() => account_save($pdo, $user, 'delete', ['account_id' => $id]), 'Permanent deletion rejected');
    account_test((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE module='Chart of Accounts'")->fetchColumn() === 5, 'Create, edit, disable and enable are audited');
    $pdo->exec("CREATE TRIGGER fixture_reject_account_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fixture audit outage'");
    try {
        account_test_reject(fn() => account_save($pdo, $user, 'disable', ['account_id' => $id]), 'Audit outage rejects save');
        account_test((int) account_load($pdo, $id)['Is_Active'] === 1 && !$pdo->inTransaction(), 'Audit failure rolls back account change');
    } finally { $pdo->exec('DROP TRIGGER fixture_reject_account_audit'); }

    // Serve copies only: the production connector and sessions are never changed.
    $fixture = sys_get_temp_dir() . '/atikha_accounts_' . bin2hex(random_bytes(6));
    mkdir($fixture); mkdir("$fixture/includes"); mkdir("$fixture/assets"); mkdir("$fixture/assets/js"); mkdir("$fixture/sessions");
    foreach (['admin_accounts.php', 'financial_records.php', 'expenses.php', 'funds.php'] as $file) { copy(__DIR__ . '/../' . $file, "$fixture/$file"); }
    foreach (glob(__DIR__ . '/../includes/*.php') as $file) { copy($file, "$fixture/includes/" . basename($file)); }
    foreach (glob(__DIR__ . '/../assets/js/*.js') as $file) { copy($file, "$fixture/assets/js/" . basename($file)); }
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
    account_test(account_http('admin_accounts.php', null, 'Anonymous')[0] === 302, 'Anonymous account access redirects');
    account_http('__fixture_login.php');
    account_http('__fixture_login.php?role=Management', null, 'Management');
    account_test(account_http('admin_accounts.php', null, 'Management')[0] === 403 && account_http('admin_accounts.php', ['action' => 'disable', 'account_id' => $id, 'csrf_token' => 'fixture-csrf'], 'Management')[0] === 403, 'Management GET and POST denied');
    [ $code, $html ] = account_http('admin_accounts.php');
    account_test($code === 200 && str_contains($html, 'Test &lt;b&gt; &amp; &quot;account&quot;') && !str_contains($html, '<script>alert(1)</script>'), 'Admin page escapes account names and descriptions');
    account_test(str_contains($html, 'nav_link_class') === false && str_contains($html, 'href="admin_accounts.php" class="flex items-center rounded-lg px-4 py-2.5 text-sm transition bg-slate-700 text-white font-medium"'), 'Admin sidebar link highlighted');
    ob_start(); $_SESSION = ['Role' => 'Management']; $activePage = ''; include __DIR__ . '/../includes/nav.php'; $nav = ob_get_clean();
    account_test(!str_contains($nav, 'admin_accounts.php'), 'Management sidebar omits account management');
    $count = (int) $pdo->query('SELECT COUNT(*) FROM Categories')->fetchColumn();
    account_http('admin_accounts.php', ['action' => 'create', 'name' => 'Bad CSRF', 'type' => 'Fund', 'csrf_token' => 'invalid']);
    account_test((int) $pdo->query('SELECT COUNT(*) FROM Categories')->fetchColumn() === $count, 'Invalid CSRF cannot create account');
    account_test(account_http('admin_accounts.php', ['action' => 'create', 'name' => 'HTTP income', 'type' => 'Fund', 'csrf_token' => 'fixture-csrf'])[0] === 302, 'Valid account POST redirects');
    $httpId = (int) $pdo->query("SELECT CategoryID FROM Categories WHERE Name='HTTP income' AND Type='Fund'")->fetchColumn();
    foreach (['update', 'disable', 'enable'] as $action) {
        account_test(account_http('admin_accounts.php', ['action' => $action, 'account_id' => $httpId, 'detail_type' => 'HTTP detail', 'csrf_token' => 'fixture-csrf'])[0] === 302, "Account $action POST succeeds");
        if ($action === 'disable') {
            account_test(str_contains(account_http('admin_accounts.php?status=inactive')[1], 'HTTP income'), 'Inactive filter shows disabled account');
        }
    }
    account_test(account_load($pdo, $httpId)['Detail_Type'] === 'HTTP detail', 'HTTP metadata persisted');
    [ , $filtered ] = account_http('admin_accounts.php?q=HTTP&type=Fund&status=active');
    account_test(str_contains($filtered, 'HTTP income') && !str_contains($filtered, 'Test &lt;b&gt;'), 'Name and type filters applied');

    foreach (['active', 'inactive'] as $status) {
        [ , $accountsHtml ] = account_http('admin_accounts.php?status=' . $status);
        preg_match_all('/href="(financial_records\.php\?[^\"]+)"/', $accountsHtml, $links);
        account_test(count($links[1]) > 0, 'View Transactions available for ' . $status . ' accounts');
        foreach ($links[1] as $link) {
            [ $code, $report ] = account_http(html_entity_decode($link, ENT_QUOTES, 'UTF-8'));
            account_test($code === 200 && str_contains($report, 'Showing transactions for:') && str_contains($report, 'Clear filters'), 'Account action opens historical ledger');
        }
    }
    $route = 'financial_records.php?' . http_build_query(['filter_category'=>'HTTP income','filter_type'=>'Fund']);
    [ $code, $emptyReport ] = account_http($route);
    account_test($code === 200 && str_contains($emptyReport, '"rows":[]') && str_contains($emptyReport, 'HTTP income'), 'Empty account retains filter badge');
    account_save($pdo, $user, 'disable', ['account_id'=>$httpId]);
    [ , $inactiveReport ] = account_http($route);
    account_test(str_contains($inactiveReport, 'value="HTTP income" selected'), 'Inactive account stays selected in ledger dropdown');
    $historicalRoute = 'financial_records.php?' . http_build_query(['filter_category'=>'Legacy income','filter_type'=>'Fund']);
    [ $code, $historicalReport ] = account_http($historicalRoute);
    account_test($code === 200 && str_contains($historicalReport, '2025-01-01') && !str_contains($historicalReport, '"rows":[]'), 'Account report includes old transactions');
    [ $code, $invalidReport ] = account_http('financial_records.php?filter_category=missing');
    account_test($code === 400 && str_contains($invalidReport, 'valid account type') && !str_contains($invalidReport, 'No records match'), 'Malformed filter produces readable HTTP 400');
    [ $code, $allReport ] = account_http('financial_records.php?from=&to=');
    account_test($code === 200 && str_contains($allReport, '2025-01-01') && str_contains($allReport, 'All dates'), 'Clear Filter shows all history without badge');

    foreach (['expenses.php' => ['Expenses', 'ExpenseID', $expenseId, 'payee', 'date_incurred', 'Legacy missing', 'Expense'],
              'funds.php' => ['Incoming_Funds', 'FundID', $fundId, 'source_donor', 'date_received', 'Legacy income', 'Fund']] as $page => $spec) {
        [$table, $key, $recordId, $party, $date, $legacy, $categoryType] = $spec;
        $data = ['action' => 'update', $page === 'expenses.php' ? 'expense_id' : 'fund_id' => $recordId,
            $party => 'Fixture updated', $date => '2025-01-01', 'category' => $legacy, 'purpose' => 'Updated purpose', 'amount' => '12.34', 'csrf_token' => 'fixture-csrf'];
        account_test(account_http($page, $data)[0] === 302 && $pdo->query("SELECT Category FROM $table WHERE $key=$recordId")->fetchColumn() === $legacy, "$page preserves missing historical category on edit");
        $data['category'] = $categoryType === 'Expense' ? 'Test income' : $input['name'];
        account_test(account_http($page, $data)[0] === 200 && $pdo->query("SELECT Category FROM $table WHERE $key=$recordId")->fetchColumn() === $legacy, "$page rejects wrong-type selection");
        $activeName = $categoryType === 'Expense' ? $input['name'] : 'Test income';
        $data['category'] = $activeName;
        account_test(account_http($page, $data)[0] === 302, "$page accepts active category on edit");
        $pdo->exec("UPDATE Categories SET Is_Active=0 WHERE Type='$categoryType'");
        account_test(fetch_category_names($pdo, $categoryType) === [], "$page zero active categories has no fallback");
        account_test(account_http($page, $data)[0] === 302, "$page keeps its now-disabled category");
        $data['category'] = $categoryType === 'Expense' ? 'Utilities' : 'Donation';
        account_test(account_http($page, $data)[0] === 200 && $pdo->query("SELECT Category FROM $table WHERE $key=$recordId")->fetchColumn() === $activeName, "$page rejects another inactive category on edit");
        $data['category'] = $activeName;
        $data['action'] = 'create';
        $oldCount = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        account_http($page, $data);
        account_test((int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $oldCount, "$page stale disabled create rejected");
        [ , $empty ] = account_http($page);
        account_test(str_contains($empty, 'No active accounts are available'), "$page empty category explanation visible");
        $pdo->exec("UPDATE Categories SET Is_Active=1 WHERE Type='$categoryType'");
        account_test(account_http($page, $data)[0] === 302 && (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $oldCount + 1, "$page accepts active account on create");
        $data['csrf_token'] = 'invalid';
        account_http($page, $data);
        account_test((int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() === $oldCount + 1, "$page rejects invalid CSRF");
    }
    $pdo->exec('RENAME TABLE Categories TO Categories_unavailable');
    try {
        foreach (['expenses.php', 'funds.php'] as $page) {
            foreach (['create', 'update'] as $action) {
                [ , $html ] = account_http($page, ['action' => $action, 'csrf_token' => 'fixture-csrf']);
                account_test(str_contains($html, 'Accounts are unavailable. Please reload and try again before saving.'), "$page query failure blocks $action clearly");
            }
        }
        account_test(str_contains(account_http('admin_accounts.php')[1], 'Accounts are unavailable.'), 'Account page handles unavailable table');
    } finally { $pdo->exec('RENAME TABLE Categories_unavailable TO Categories'); }
    echo "PASS: $checks assertions\nFixture retained for browser review: $fixture\nDatabase: $db\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $failed = true;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
}
exit(isset($failed) ? 1 : 0);
