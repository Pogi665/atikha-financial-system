<?php
/** Validate migration 015 on an empty, explicitly selected disposable database. */
require_once __DIR__ . '/cli_common.php';

$checks = 0;

function core_test(bool $condition, string $label): void
{
    global $checks;
    cli_require($condition, $label);
    $checks++;
    echo "PASS: $label\n";
}

function core_reject(callable $operation, array $allowedCodes, string $label): void
{
    try {
        $operation();
    } catch (PDOException $e) {
        core_test(in_array((int) ($e->errorInfo[1] ?? 0), $allowedCodes, true), $label);
        return;
    }
    throw new RuntimeException('Unexpected acceptance: ' . $label);
}

function core_snapshot(PDO $pdo, string $table): array
{
    // Tables are fixed internal fixtures, never request input.
    return $pdo->query("SELECT * FROM `$table` ORDER BY 1")->fetchAll();
}

try {
    $options = getopt('', ['database:']);
    $database = $options['database'] ?? '';
    cli_require(is_string($database)
        && (bool) preg_match('/\Aatikha_test_[a-z0-9_]+\z/', $database),
        'An empty disposable atikha_test_* database is required; live execution is forbidden.');
    $pdo = cli_db($database);
    cli_require($pdo->query('SHOW TABLES')->fetchAll() === [], 'Disposable database must be empty.');
    cli_require((int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() === 1,
        'Foreign-key checks must be enabled.');
    cli_require((int) $pdo->query('SELECT @@SESSION.check_constraint_checks')->fetchColumn() === 1,
        'CHECK constraints must be enabled.');

    // Build the pre-pivot schema only. The shared SQL loader ignores USE and
    // CREATE DATABASE statements, keeping every operation on the selected fixture.
    cli_sql_file($pdo, __DIR__ . '/../database.sql');
    foreach (glob(__DIR__ . '/../migrations/*.sql') as $path) {
        if (preg_match('/\A(\d{3})_/', basename($path), $match) && (int) $match[1] <= 14) {
            cli_sql_file($pdo, $path);
        }
    }
    foreach (['Incoming_Funds', 'Expenses'] as $table) {
        if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE 'Reference_Number'")->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN Reference_Number VARCHAR(100) NULL");
        }
    }

    // Use only synthetic actors and financial records, never a live-data export.
    $pdo->exec("INSERT INTO Users (FullName, Role, Email, Password)
        VALUES ('Core Fixture Admin', 'Admin', 'core-admin@example.invalid', 'not-a-login-hash')");
    $userId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO user_identities (UserID, FullName, Email, Role)
        SELECT UserID, FullName, Email, Role FROM Users WHERE UserID = $userId");
    foreach (['Expense' => 20, 'Fund' => 7] as $type => $target) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM Categories WHERE Type = '$type'")->fetchColumn();
        $insert = $pdo->prepare('INSERT INTO Categories (Name, Type) VALUES (?, ?)');
        for ($i = $count; $i < $target; $i++) {
            $insert->execute(['Core fixture ' . $type . ' ' . $i, $type]);
        }
    }
    $expenseAccount = (int) $pdo->query("SELECT MIN(CategoryID) FROM Categories WHERE Type='Expense'")->fetchColumn();
    $incomeAccount = (int) $pdo->query("SELECT MIN(CategoryID) FROM Categories WHERE Type='Fund'")->fetchColumn();
    $pdo->exec("UPDATE Categories SET Detail_Type='Fixture details', Description='Preserved description',
        Is_Active=0 WHERE CategoryID=$expenseAccount");
    $pdo->exec("INSERT INTO Incoming_Funds (Source_Donor, Category, Amount, Date_Received, RecordedBy_UserID)
        VALUES ('Fixture donor', 'Donation', 100.00, '2026-01-01', $userId)");
    $pdo->exec("INSERT INTO Expenses (Payee, Category, Amount, Date_Incurred, RecordedBy_UserID)
        VALUES ('Fixture payee', 'Utilities', 25.00, '2026-01-02', $userId)");
    $expenseId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO Receipts (ExpenseID, File_Path, UploadedBy_UserID)
        VALUES ($expenseId, 'fixture-receipt-not-created.png', $userId),
        (NULL, 'fixture-pending-not-created.png', $userId)");
    $pdo->exec("INSERT INTO Reports (Report_Month, Report_Year, SubmittedBy_UserID, Total_Revenue, Total_Expenses, Net_Income)
        VALUES (1, 2026, $userId, 100.00, 25.00, 75.00)");
    $pdo->exec("INSERT INTO forecast_cache (Data_Fingerprint, History_JSON, Forecast_JSON, Model, GeneratedBy_UserID)
        VALUES (REPEAT('a',40), '{}', '{}', 'fixture-no-api', $userId)");
    $financialTargets = ['funds.php', 'expenses.php?id=1', 'financial_records.php?view=crb',
        'reports.php?month=01&year=2026', 'dashboard.php', 'management_reviews.php?entity=fund&id=1'];
    $preservedTargets = ['board_inbox.php?id=1', 'board_messages.php', 'admin_users.php', ''];
    $notification = $pdo->prepare('INSERT INTO Notifications (Recipient_UserID, Message, Target_URL) VALUES (?, ?, ?)');
    foreach (array_merge($financialTargets, $preservedTargets) as $target) {
        $notification->execute([$userId, 'Fixture notification', $target]);
    }
    $pdo->exec("INSERT INTO audit_logs (user_id, action_type, module, record_id, new_values, source_link, ip_address)
        VALUES ($userId, 'CREATE', 'Expenses', $expenseId, '{\"Amount\":\"25.00\"}', 'expenses.php?id=1', '127.0.0.1')");
    $pdo->exec("INSERT INTO Board_Communications (Sender_UserID, Subject, Message_Body)
        VALUES ($userId, 'Preserved fixture', 'Board history')");
    $pdo->exec("INSERT INTO External_Communications (Sender_UserID, To_Email, From_Email, Reply_To_Email,
        Subject, Message_Body, SMTP_Message_ID, Submission_Key)
        VALUES ($userId, 'to@example.invalid', 'from@example.invalid', 'reply@example.invalid',
        'Preserved fixture', 'Email history', 'fixture-message-id', REPEAT('b',64))");
    $pdo->exec("INSERT INTO password_resets (UserID, Email, ip_address)
        VALUES ($userId, 'core-admin@example.invalid', '127.0.0.1')");

    $accountsBefore = core_snapshot($pdo, 'Categories');
    $protectedTables = ['Budgets', 'Users', 'user_identities', 'audit_logs',
        'Board_Communications', 'External_Communications', 'password_resets'];
    $protectedBefore = [];
    foreach ($protectedTables as $table) { $protectedBefore[$table] = core_snapshot($pdo, $table); }
    $notificationsBefore = $pdo->query("SELECT * FROM Notifications WHERE SUBSTRING_INDEX(Target_URL,'?',1)
        NOT IN ('funds.php','expenses.php','financial_records.php','reports.php','dashboard.php','management_reviews.php')
        ORDER BY NotificationID")->fetchAll();

    cli_sql_file($pdo, __DIR__ . '/../migrations/015_double_entry_core.sql');
    core_test($pdo->query('SELECT DATABASE()')->fetchColumn() === $database, 'SQL loader remains on disposable database');
    core_test((int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() === 1, 'Foreign-key checks restored');
    foreach (['Incoming_Funds', 'Expenses', 'Receipts', 'Reports', 'forecast_cache', 'journal_entries', 'journal_entry_lines'] as $table) {
        core_test((int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 0, "$table is empty and retained");
    }
    foreach ($protectedTables as $table) {
        core_test(core_snapshot($pdo, $table) === $protectedBefore[$table], "$table preserved byte-for-byte at field level");
    }
    core_test(core_snapshot($pdo, 'Notifications') === $notificationsBefore, 'Financial notifications removed; unrelated notifications preserved');
    $accountsAfter = core_snapshot($pdo, 'Categories');
    core_test(count($accountsBefore) === 27 && count($accountsAfter) === 27, 'All 27 account identities retained');
    foreach ($accountsBefore as $i => $account) {
        cli_require(array_intersect_key($accountsAfter[$i], $account) === $account, 'Original account fields changed.');
        cli_require($accountsAfter[$i]['Account_Type'] === ($account['Type'] === 'Fund' ? 'Income' : 'Expense')
            && $accountsAfter[$i]['Normal_Balance'] === ($account['Type'] === 'Fund' ? 'Credit' : 'Debit')
            && $accountsAfter[$i]['Account_Code'] === null && (int) $accountsAfter[$i]['Is_Cash_Account'] === 0,
            'Incorrect account mapping or fabricated account metadata.');
    }
    core_test(true, 'Account fields, classifications, normal balances, NULL codes and cash flags verified');

    $entryColumns = $pdo->query('SHOW COLUMNS FROM journal_entries')->fetchAll(PDO::FETCH_UNIQUE);
    core_test($entryColumns['status']['Default'] === 'draft'
        && $entryColumns['status']['Type'] === "enum('draft','posted')", 'Draft default and exact status domain');
    $lineColumns = $pdo->query('SHOW COLUMNS FROM journal_entry_lines')->fetchAll(PDO::FETCH_UNIQUE);
    core_test($lineColumns['debit_amount']['Type'] === 'decimal(15,2)'
        && $lineColumns['credit_amount']['Type'] === 'decimal(15,2)'
        && $lineColumns['debit_amount']['Null'] === 'NO'
        && $lineColumns['credit_amount']['Null'] === 'NO', 'Exact nonnullable money columns');
    core_test($lineColumns['fund_project_id']['Null'] === 'YES', 'Optional fund/project placeholder');
    $engines = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('journal_entries','journal_entry_lines')")->fetchAll(PDO::FETCH_COLUMN);
    core_test($engines === ['InnoDB', 'InnoDB'], 'Both journal tables use InnoDB');
    core_test((int) $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='receipts' AND CONSTRAINT_NAME='fk_receipts_expense'
        AND REFERENCED_TABLE_NAME='expenses'")->fetchColumn() === 1, 'Receipt foreign key retained');
    core_test((int) $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_entry_lines'
        AND COLUMN_NAME='fund_project_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn() === 0,
        'No fabricated fund/project foreign key');

    $pdo->exec("UPDATE Categories SET Account_Code='TEST-EXPENSE' WHERE CategoryID=$expenseAccount");
    core_reject(fn() => $pdo->exec("UPDATE Categories SET Account_Code='TEST-EXPENSE' WHERE CategoryID=$incomeAccount"),
        [1062], 'Duplicate assigned account codes rejected');
    $pdo->exec("INSERT INTO Categories (Name, Type, Account_Type, Normal_Balance, Is_Cash_Account)
        VALUES ('Fixture cash account', NULL, 'Asset', 'Debit', TRUE)");
    $assetAccount = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO Categories (Name, Type, Account_Type, Normal_Balance)
        VALUES ('Fixture payable', NULL, 'Liability', 'Credit'), ('Fixture equity', NULL, 'Equity', 'Credit')");
    core_test((int) $pdo->query('SELECT COUNT(*) FROM Categories WHERE Account_Code IS NULL')->fetchColumn() === 29,
        'Multiple NULL account codes permitted, including new accounting types');
    core_test($pdo->query("SELECT Type FROM Categories WHERE CategoryID=$assetAccount")->fetchColumn() === null,
        'Asset account supports NULL legacy Type');
    core_reject(fn() => $pdo->exec("INSERT INTO Categories (Name, Type, Account_Type, Normal_Balance)
        VALUES ('Fixture cash account', NULL, 'Asset', 'Debit')"), [1062], 'New account name/type uniqueness covers NULL legacy Type');
    core_reject(fn() => $pdo->exec("UPDATE Categories SET Is_Cash_Account=2 WHERE CategoryID=$assetAccount"),
        [4025], 'Cash flag rejects nonboolean values');

    $pdo->exec("INSERT INTO journal_entries (entry_date, description) VALUES ('2026-01-03', 'Fixture journal')");
    $entryId = (int) $pdo->lastInsertId();
    core_test($pdo->query("SELECT status FROM journal_entries WHERE id=$entryId")->fetchColumn() === 'draft', 'New journal defaults to draft');
    $insertLine = $pdo->prepare('INSERT INTO journal_entry_lines
        (journal_entry_id, account_id, debit_amount, credit_amount, fund_project_id) VALUES (?, ?, ?, ?, ?)');
    $insertLine->execute([$entryId, $assetAccount, '100.01', '0.00', null]);
    $insertLine->execute([$entryId, $incomeAccount, '0.00', '100.01', null]);
    core_test($pdo->query("SELECT SUM(debit_amount) FROM journal_entry_lines WHERE journal_entry_id=$entryId")->fetchColumn() === '100.01'
        && $pdo->query("SELECT SUM(credit_amount) FROM journal_entry_lines WHERE journal_entry_id=$entryId")->fetchColumn() === '100.01',
        'Valid debit/credit lines retain exact cents');
    foreach ([['1.00', '1.00'], ['0.00', '0.00'], ['-1.00', '0.00'], ['0.00', '-1.00'], ['1.00', '-1.00']] as [$debit, $credit]) {
        core_reject(fn() => $insertLine->execute([$entryId, $assetAccount, $debit, $credit, null]), [4025],
            "Invalid debit/credit pair rejected: $debit / $credit");
    }
    foreach ([[null, '1.00'], ['1.00', null]] as [$debit, $credit]) {
        core_reject(fn() => $insertLine->execute([$entryId, $assetAccount, $debit, $credit, null]), [1048], 'NULL monetary amount rejected');
    }
    core_reject(fn() => $insertLine->execute([4294967295, $assetAccount, '1.00', '0.00', null]), [1452], 'Nonexistent journal rejected');
    core_reject(fn() => $insertLine->execute([$entryId, 4294967295, '1.00', '0.00', null]), [1452], 'Nonexistent account rejected');
    core_reject(fn() => $pdo->exec("DELETE FROM journal_entries WHERE id=$entryId"), [1451], 'Referenced journal deletion restricted');
    core_reject(fn() => $pdo->exec("DELETE FROM Categories WHERE CategoryID=$assetAccount"), [1451], 'Referenced account deletion restricted');
    core_reject(fn() => $pdo->exec("UPDATE journal_entries SET id=1000 WHERE id=$entryId"), [1451], 'Referenced journal ID update restricted');
    core_reject(fn() => $pdo->exec("UPDATE Categories SET CategoryID=1000 WHERE CategoryID=$assetAccount"), [1451], 'Referenced account ID update restricted');

    // Explicitly document the Phase 1 limit: this schema does not guard posting.
    $pdo->exec("INSERT INTO journal_entries (entry_date, description, status)
        VALUES ('2026-01-04', 'Unbalanced posting fixture: later-phase guard required', 'posted')");
    $unbalancedId = (int) $pdo->lastInsertId();
    $insertLine->execute([$unbalancedId, $assetAccount, '1.00', '0.00', null]);
    core_test($pdo->query("SELECT SUM(debit_amount-credit_amount) FROM journal_entry_lines
        WHERE journal_entry_id=$unbalancedId")->fetchColumn() === '1.00', 'Known boundary: posted balance is not yet enforced');

    // Rerunning must fail on the first ALTER, before any cleanup can recur.
    $journalsBefore = core_snapshot($pdo, 'journal_entries');
    $linesBefore = core_snapshot($pdo, 'journal_entry_lines');
    $pdo->exec("INSERT INTO Expenses (Payee, Category, Amount, Date_Incurred, RecordedBy_UserID)
        VALUES ('Rerun sentinel', 'Utilities', 1.00, '2026-01-05', $userId)");
    core_reject(fn() => cli_sql_file($pdo, __DIR__ . '/../migrations/015_double_entry_core.sql'), [1060],
        'Migration rerun stops on duplicate column before destructive cleanup');
    core_test((int) $pdo->query('SELECT COUNT(*) FROM Expenses')->fetchColumn() === 1
        && core_snapshot($pdo, 'journal_entries') === $journalsBefore
        && core_snapshot($pdo, 'journal_entry_lines') === $linesBefore,
        'Fail-fast rerun preserves sentinel and journal records');
    echo "PASS: $checks assertions on $database; no live database writes.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
