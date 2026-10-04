<?php
/** Double-entry posting. Never writes legacy transactions or executes schema DDL. */
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ledger_query.php';

const JOURNAL_ACCOUNT_TYPES = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
const JOURNAL_MAX_LINES = 100;

class JournalProblem extends RuntimeException
{
    public int $status;
    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function journal_today(): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
}

function journal_submission_key(): string
{
    if (!is_string($_SESSION['journal_submission_secret'] ?? null)) {
        $_SESSION['journal_submission_secret'] = bin2hex(random_bytes(32));
    }
    $nonce = bin2hex(random_bytes(16));
    $signature = hash_hmac('sha256', $nonce . ':' . (int) ($_SESSION['UserID'] ?? 0), $_SESSION['journal_submission_secret']);
    return $nonce . substr($signature, 0, 32);
}

function journal_submission_valid(string $key): bool
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $key)
        || !is_string($_SESSION['journal_submission_secret'] ?? null)) { return false; }
    $expected = substr(hash_hmac('sha256', substr($key, 0, 32) . ':' . (int) ($_SESSION['UserID'] ?? 0),
        $_SESSION['journal_submission_secret']), 0, 32);
    return hash_equals($expected, substr($key, 32));
}

function journal_require_schema(PDO $pdo): void
{
    if (PHP_INT_SIZE < 8) { throw new JournalProblem('Journal posting is temporarily unavailable.', 503); }
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='journal_entries' AND COLUMN_NAME IN ('submission_key','submission_hash','posted_by_user_id')");
    if ((int) $stmt->fetchColumn() !== 3) {
        throw new JournalProblem('Journal posting is temporarily unavailable.', 503);
    }
}

function journal_accounts(PDO $pdo): array
{
    return $pdo->query("SELECT CategoryID, Name, Account_Code, Account_Type, Normal_Balance, Is_Cash_Account
        FROM Categories WHERE Is_Active=1 AND Account_Type IN ('Asset','Liability','Equity','Income','Expense')
        AND Normal_Balance IN ('Debit','Credit') AND Is_Cash_Account IN (0,1)
        AND (Is_Cash_Account=0 OR Account_Type='Asset')
        ORDER BY FIELD(Account_Type,'Asset','Liability','Equity','Income','Expense'), Account_Code IS NULL, Account_Code, Name, CategoryID")->fetchAll();
}

function journal_string(array $input, string $name, bool $optional = false): string
{
    if (!array_key_exists($name, $input)) {
        if ($optional) { return ''; }
        throw new JournalProblem('The submitted form is incomplete. Please review and try again.');
    }
    if (!is_string($input[$name])) { throw new JournalProblem('Invalid value for ' . str_replace('_', ' ', $name) . '.'); }
    $value = trim($input[$name]);
    if (!mb_check_encoding($value, 'UTF-8')) { throw new JournalProblem('Text must use valid UTF-8.'); }
    return $value;
}

function journal_id(string $value, bool $optional = false): ?int
{
    if ($optional && $value === '') { return null; }
    $digits = ltrim($value, '0');
    if (!preg_match('/\A[0-9]{1,10}\z/', $value) || $digits === ''
        || strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, '4294967295') > 0)) {
        throw new JournalProblem('Select a valid account and use a positive numeric Fund/Project ID.');
    }
    return (int) $digits;
}

function journal_amount(string $value): int
{
    if ($value === '') { return 0; }
    if (strlen($value) > 32 || !preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', $value, $matches)) {
        throw new JournalProblem('Amounts must be nonnegative decimal numbers with at most two decimal places.');
    }
    $whole = ltrim($matches[1], '0');
    if (strlen($whole) > 13) { throw new JournalProblem('An amount exceeds the supported accounting limit.'); }
    return ledger_cents(($whole === '' ? '0' : $whole) . '.' . str_pad($matches[2] ?? '', 2, '0'));
}

function journal_input(array $post): array
{
    if (PHP_INT_SIZE < 8) { throw new JournalProblem('Journal posting is temporarily unavailable.', 503); }
    $date = journal_string($post, 'entry_date');
    $reference = journal_string($post, 'reference', true);
    $description = journal_string($post, 'description');
    if (!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $date)
        || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        throw new JournalProblem('Choose a valid entry date.');
    }
    if (mb_strlen($reference) > 100 || $description === '' || mb_strlen($description) > 2000) {
        throw new JournalProblem('Enter a description of at most 2,000 characters and a reference of at most 100 characters.');
    }
    $rawLines = $post['lines'] ?? null;
    $count = journal_string($post, 'line_count');
    if (!is_array($rawLines) || count($rawLines) < 2 || count($rawLines) > JOURNAL_MAX_LINES
        || !preg_match('/\A[0-9]{1,3}\z/', $count) || (int) $count !== count($rawLines)
        || journal_string($post, 'form_complete') !== '1') {
        throw new JournalProblem('Submit 2 to 100 complete lines. The form may have exceeded a server input limit.');
    }
    $lines = []; $debits = $credits = 0;
    foreach ($rawLines as $raw) {
        if (!is_array($raw)) { throw new JournalProblem('Invalid journal line.'); }
        $account = journal_id(journal_string($raw, 'account_id'));
        $fund = journal_id(journal_string($raw, 'fund_project_id', true), true);
        $debit = journal_amount(journal_string($raw, 'debit_amount', true));
        $credit = journal_amount(journal_string($raw, 'credit_amount', true));
        if (!(($debit > 0 && $credit === 0) || ($credit > 0 && $debit === 0))) {
            throw new JournalProblem('Each line must contain one positive Debit or Credit, never both.');
        }
        $debits += $debit; $credits += $credit;
        $lines[] = ['account_id' => $account, 'fund_project_id' => $fund,
            'debit_amount' => ledger_decimal($debit), 'credit_amount' => ledger_decimal($credit)];
    }
    if ($debits <= 0 || $debits !== $credits) { throw new JournalProblem('Total Debits must exactly equal Total Credits and exceed zero.'); }
    return ['entry_date' => $date, 'reference' => $reference === '' ? null : $reference,
        'description' => $description, 'lines' => $lines];
}

function journal_existing(PDO $pdo, string $key, int $userId, string $hash): ?array
{
    $stmt = $pdo->prepare('SELECT id, posted_by_user_id, submission_hash, status FROM journal_entries WHERE submission_key=?');
    $stmt->execute([$key]);
    $existing = $stmt->fetch();
    if (!$existing) { return null; }
    if ((int) $existing['posted_by_user_id'] !== $userId || $existing['status'] !== 'posted'
        || !is_string($existing['submission_hash']) || !hash_equals($existing['submission_hash'], $hash)) {
        throw new JournalProblem('This submission has already been used with different contents. Start a new entry.', 409);
    }
    return ['id' => (int) $existing['id'], 'duplicate' => true];
}

/** Owns its transaction; authentication/CSRF/signature checks cannot be bypassed by callers. */
function journal_post(PDO $pdo, int $userId, array $post): array
{
    if ($userId <= 0 || (int) ($_SESSION['UserID'] ?? 0) !== $userId) {
        throw new JournalProblem('Please sign in again.', 401);
    }
    if (!csrf_verify(is_string($post['csrf_token'] ?? null) ? $post['csrf_token'] : null)) {
        throw new JournalProblem('Your session expired. Reload the form and try again.', 400);
    }
    $key = is_string($post['submission_key'] ?? null) ? $post['submission_key'] : '';
    if (!journal_submission_valid($key)) { throw new JournalProblem('This entry form expired. Reload it and try again.', 400); }
    journal_require_schema($pdo);
    $input = journal_input($post);
    $hash = hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    if ($pdo->inTransaction()) { throw new LogicException('Journal posting must own its transaction.'); }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT Role, Is_Active FROM Users WHERE UserID=? FOR UPDATE');
        $stmt->execute([$userId]);
        $actor = $stmt->fetch();
        if (!$actor || (int) $actor['Is_Active'] !== 1 || $actor['Role'] !== 'Admin') {
            throw new JournalProblem('Posting entries is restricted to active System Administrators.', 403);
        }
        $existing = journal_existing($pdo, $key, $userId, $hash);
        if ($existing) { $pdo->commit(); return $existing; }
        $ids = array_values(array_unique(array_column($input['lines'], 'account_id')));
        sort($ids, SORT_NUMERIC);
        $stmt = $pdo->prepare('SELECT CategoryID, Name, Account_Type, Normal_Balance, Is_Active, Is_Cash_Account FROM Categories
            WHERE CategoryID IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY CategoryID FOR UPDATE');
        $stmt->execute($ids);
        $accounts = [];
        foreach ($stmt->fetchAll() as $account) { $accounts[(int) $account['CategoryID']] = $account; }
        foreach ($ids as $id) {
            $account = $accounts[$id] ?? null;
            if (!$account || (int) $account['Is_Active'] !== 1
                || !in_array($account['Account_Type'], JOURNAL_ACCOUNT_TYPES, true)
                || !in_array($account['Normal_Balance'], ['Debit', 'Credit'], true)
                || !in_array((int) $account['Is_Cash_Account'], [0,1], true)
                || ((int) $account['Is_Cash_Account'] === 1 && $account['Account_Type'] !== 'Asset')) {
                throw new JournalProblem('An account is unavailable or inactive. Review the selected accounts.');
            }
        }
        $stmt = $pdo->prepare("INSERT INTO journal_entries
            (entry_date, reference, description, status, submission_key, submission_hash, posted_by_user_id)
            VALUES (?, ?, ?, 'posted', ?, ?, ?)");
        $stmt->execute([$input['entry_date'], $input['reference'], $input['description'], $key, $hash, $userId]);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO journal_entry_lines
            (journal_entry_id, account_id, debit_amount, credit_amount, fund_project_id) VALUES (?, ?, ?, ?, ?)');
        $auditLines = [];
        foreach ($input['lines'] as $line) {
            $stmt->execute([$id, $line['account_id'], $line['debit_amount'], $line['credit_amount'], $line['fund_project_id']]);
            $auditLines[] = ['id' => (int) $pdo->lastInsertId(), 'account_name' => $accounts[$line['account_id']]['Name']] + $line;
        }
        $audit = ['entry_date' => $input['entry_date'], 'reference' => $input['reference'],
            'description' => $input['description'], 'status' => 'posted', 'posted_by_user_id' => $userId, 'lines' => $auditLines];
        if (!log_system_action($pdo, $userId, AUDIT_ACTION_CREATE, 'General Journal', $id, null, $audit, 'general_journal.php')) {
            throw new RuntimeException('Journal audit record could not be saved.');
        }
        $pdo->commit();
        return ['id' => $id, 'duplicate' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            $existing = journal_existing($pdo, $key, $userId, $hash);
            if ($existing) { return $existing; }
        }
        throw $e;
    }
}
