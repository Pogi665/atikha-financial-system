<?php
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/ledger_query.php';
require_once __DIR__ . '/../includes/ledger_ui.php';
require_once __DIR__ . '/../includes/admin_bootstrap.php';

$checks = 0;
function ledger_test(bool $ok, string $label): void
{
    global $checks;
    cli_require($ok, $label);
    $checks++;
    echo "PASS: $label\n";
}
try {
    $options = getopt('', ['database:']);
    $db = $options['database'] ?? '';
    cli_require((bool) preg_match('/\Aatikha_test_[a-z0-9_]+\z/', $db), 'Disposable database required.');
    $pdo = cli_db($db);
    cli_require($pdo->query('SHOW TABLES')->fetchAll() === [], 'Empty disposable database required.');
    cli_sql_file($pdo, __DIR__ . '/../database.sql');
    foreach (glob(__DIR__ . '/../migrations/*.sql') as $path) {
        if (!str_starts_with(basename($path), '011_')) { cli_sql_file($pdo, $path); }
    }
    $id = bootstrap_admin($pdo, 'Fixture Admin', 'admin@example.invalid', bin2hex(random_bytes(24)));
    $pdo->exec("INSERT INTO Incoming_Funds (Source_Donor, Category, Amount, Date_Received, RecordedBy_UserID, Project_Code)
        VALUES ('Legacy donor', 'Donation', 100.10, '2025-01-31', $id, 'OLD')");
    $pdo->exec("INSERT INTO Expenses (Payee, Category, Amount, Date_Incurred, RecordedBy_UserID)
        VALUES ('Legacy expense', 'Equipment', 25.05, '2025-01-31', $id)");
    $old = [];
    foreach (['Incoming_Funds', 'Expenses'] as $table) { $old[$table] = $pdo->query("SELECT * FROM $table")->fetchAll(); }
    cli_sql_file($pdo, __DIR__ . '/../migrations/011_transaction_reporting.sql');
    cli_sql_file($pdo, __DIR__ . '/../migrations/011_transaction_reporting.sql');
    foreach ($old as $table => $rows) {
        $new = $pdo->query("SELECT * FROM $table")->fetchAll();
        ledger_test(array_intersect_key($new[0], $rows[0]) === $rows[0], "$table old fields unchanged after migration and rerun");
        ledger_test($new[0]['Purpose'] === null, "$table legacy purpose remains NULL");
    }
    $pdo->exec("INSERT INTO Categories (Name,Type) VALUES ('Custom funding','Fund'),('Custom spending','Expense')");
    $fund = $pdo->prepare('INSERT INTO Incoming_Funds (Source_Donor,Category,Purpose,Project_Code,Amount,Date_Received,RecordedBy_UserID) VALUES (?,?,?,?,?,?,?)');
    $expense = $pdo->prepare('INSERT INTO Expenses (Payee,Category,Purpose,Project_Code,Amount,Date_Incurred,RecordedBy_UserID) VALUES (?,?,?,?,?,?,?)');
    $fund->execute(['Donor <script>', 'Custom funding', 'Purpose <b> & "quoted"', 'PROJECT-A', '10.01', '2025-02-01', $id]);
    $fund->execute(['Second donor', 'Custom funding', null, null, '0.02', '2025-02-01', $id]);
    $expense->execute(['Payee', 'Custom spending', 'Confirmed purpose', null, '100.00', '2025-02-01', $id]);
    $expense->execute(['Last day', 'Custom spending', null, 'PROJECT-A', '0.01', '2025-02-28', $id]);
    $fund->execute(['Next month', 'Custom funding', 'Excluded', 'PROJECT-A', '999.99', '2025-03-01', $id]);
    $period = ledger_period($pdo, '2025-02-01', '2025-02-28');
    ledger_test($period['opening_balance'] === '75.05', 'Opening includes both transaction types before period');
    ledger_test(array_column($period['rows'], 'remaining_balance') === ['85.06','85.08','-14.92','-14.93'], 'Exact cents, both directions, negative balances, same-day type and ID order');
    ledger_test($period['incoming_total'] === '10.03' && $period['expense_total'] === '100.01' && $period['closing_balance'] === '-14.93', 'Period totals and exclusive end boundary');
    ledger_test(ledger_completeness($period['rows']) === ['affected'=>3,'missing_purpose'=>2,'unallocated'=>2], 'Distinct completeness counts do not double count');
    ob_start(); ledger_completeness_notice(ledger_completeness($period['rows'])); ledger_render_table($period['rows']); $html = ob_get_clean();
    ledger_test(str_contains($html, '3 records need attention') && str_contains($html, 'Unallocated is a legitimate status'), 'Visible counted completeness notice');
    ledger_test(str_contains($html, 'Purpose &lt;b&gt; &amp; &quot;quoted&quot;') && !str_contains($html, '<script>'), 'Database strings HTML escaped');
    ledger_test(str_contains($html, 'Not specified') && str_contains($html, 'PROJECT-A') && str_contains($html, 'Organization Balance After Transaction'), 'Purpose, allocation, legacy placeholders and explicit balance label');
    ledger_test(in_array('Custom spending', ledger_category_options($pdo), true) && $period['rows'][0]['category'] === 'Custom funding', 'Categories table names preserved');
    $empty = ledger_period($pdo, '2025-04-01', '2025-04-30');
    ledger_test($empty['rows'] === [] && $empty['opening_balance'] === '985.06' && $empty['closing_balance'] === '985.06', 'Empty period carries prior organization balance');
    $none = ledger_period($pdo, '2024-01-01', '2024-01-31');
    ledger_test($none['opening_balance'] === '0.00' && $none['closing_balance'] === '0.00', 'Empty history has zero balance');
    for ($i = 0; $i < 51; $i++) { $fund->execute(['Page '.$i, 'Custom funding', 'Purpose', 'PROJECT-A', '0.01', '2025-02-02', $id]); }
    $filters = ['from'=>'2025-02-01','to'=>'2025-02-28','type'=>'','category'=>'','page'=>2];
    $view = ledger_view($pdo, $filters);
    ledger_test($view['total'] === 55 && count($view['rows']) === 5 && $view['rows'][4]['remaining_balance'] === '85.06', 'Second page retains organization balance and reverse deterministic order');
    ledger_test($view['completeness']['affected'] === 3, 'Completeness spans all pages');
    $filtered = ledger_view($pdo, array_replace($filters, ['page'=>1,'type'=>'Expense','category'=>'Custom spending']));
    ledger_test(array_column($filtered['rows'], 'remaining_balance') === ['-14.42','-14.92'], 'Filters do not remove hidden transactions from organization balances');
    ledger_test($filtered['completeness'] === ['affected'=>2,'missing_purpose'=>1,'unallocated'=>1], 'Completeness follows selected filters');
    ledger_test(transaction_details_input(['purpose'=>'  ','project_code'=>'']) === null, 'Legacy edits require real purpose');
    ledger_test(transaction_details_input(['purpose'=>'Real','project_code'=>'  ']) === ['purpose'=>'Real','project_code'=>null], 'Unallocated remains NULL');
    ledger_test(transaction_details_input(['purpose'=>str_repeat('a',1001)]) === null && transaction_details_input(['purpose'=>'Real','project_code'=>str_repeat('a',51)]) === null, 'Overlength metadata rejected');
    ledger_test(transaction_details_input(['purpose'=>['bad']]) === null, 'Non-string purpose rejected');
    $pdo->exec('RENAME TABLE Expenses TO Expenses_unavailable');
    try {
        ledger_period($pdo, '2025-02-01', '2025-02-28');
        throw new RuntimeException('Missing table was silently treated as empty.');
    } catch (PDOException $e) {
        ledger_test(!$pdo->inTransaction(), 'Query failure propagates and read transaction is closed');
    } finally { $pdo->exec('RENAME TABLE Expenses_unavailable TO Expenses'); }
    echo "PASS: $checks ledger assertions\n";
} catch (Throwable $e) { fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n"); exit(1); }
