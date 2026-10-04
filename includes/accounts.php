<?php

require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/logger.php';

const ACCOUNT_TYPES = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];

function account_text(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

function account_id(array $input, string $key = 'account_id'): int
{
    $value = $input[$key] ?? null;
    return is_scalar($value) ? (int) (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0) : 0;
}

function account_load(PDO $pdo, int $id, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT CategoryID, Name, Type, Account_Code, Account_Type, Normal_Balance, Is_Cash_Account,
        Detail_Type, Description, Is_Active FROM Categories WHERE CategoryID = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function account_has_posted_lines(PDO $pdo, int $id): bool
{
    // Lock the account row before this check in mutations, as the posting service does.
    // A locking read sees the latest committed entry even under REPEATABLE READ.
    $stmt = $pdo->prepare("SELECT l.id FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id
        WHERE l.account_id=? AND j.status='posted' LIMIT 1 FOR UPDATE");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() !== false;
}

/** The page supplies authentication and CSRF validation before calling this. */
function account_save(PDO $pdo, int $userId, string $action, array $input): int
{
    if (!in_array($action, ['create', 'update', 'disable', 'enable'], true)) {
        throw new InvalidArgumentException('Select a valid account action.');
    }
    $detail = account_text($input, 'detail_type');
    $description = account_text($input, 'description');
    foreach (['name', 'account_type', 'detail_type', 'description'] as $key) {
        if (array_key_exists($key, $input) && (!is_string($input[$key]) || !mb_check_encoding($input[$key], 'UTF-8'))) {
            throw new InvalidArgumentException('Account fields must contain valid text.');
        }
    }
    if (in_array($action, ['create', 'update'], true)
        && (mb_strlen($detail) > 100 || mb_strlen($description) > 2000)) {
        throw new InvalidArgumentException('Detail type must be at most 100 characters and description at most 2,000 characters.');
    }
    $pdo->beginTransaction();
    try {
        $actor = $pdo->prepare('SELECT Role, Is_Active FROM Users WHERE UserID=? FOR UPDATE');
        $actor->execute([$userId]);
        $user = $actor->fetch();
        if (!$user || $user['Role'] !== 'Admin' || (int) $user['Is_Active'] !== 1) {
            throw new InvalidArgumentException('Account management is restricted to active System Administrators.');
        }
        $before = null;
        if ($action === 'create') {
            $name = account_text($input, 'name');
            $type = account_text($input, 'account_type');
            if ($name === '' || mb_strlen($name) > 100 || !mb_check_encoding($name, 'UTF-8') || !in_array($type, ACCOUNT_TYPES, true)) {
                throw new InvalidArgumentException('Enter an account name of at most 100 characters and select an accounting type.');
            }
            [$code, $normal, $cash] = account_accounting_fields($input, $type);
            $legacy = ['Income' => 'Fund', 'Expense' => 'Expense'][$type] ?? null;
            $stmt = $pdo->prepare('INSERT INTO Categories (Name, Type, Account_Code, Account_Type, Normal_Balance,
                Is_Cash_Account, Detail_Type, Description, Is_Active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$name, $legacy, $code, $type, $normal, $cash,
                $detail === '' ? null : $detail, $description === '' ? null : $description]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = account_id($input);
            $before = account_load($pdo, $id, true);
            if ($before === null) {
                throw new InvalidArgumentException('That account could not be found.');
            }
            if ($action === 'update') {
                // Names and types are historical identifiers, never editable here.
                [$code, $normal, $cash] = account_accounting_fields($input, $before['Account_Type']);
                if (account_has_posted_lines($pdo, $id)
                    && (($before['Account_Code'] !== null && $code !== $before['Account_Code'])
                        || $normal !== $before['Normal_Balance'] || $cash !== (int) $before['Is_Cash_Account'])) {
                    throw new InvalidArgumentException('Accounting fields are locked after posting. An unassigned code may be assigned once.');
                }
                $stmt = $pdo->prepare('UPDATE Categories SET Account_Code=?, Normal_Balance=?, Is_Cash_Account=?,
                    Detail_Type=?, Description=? WHERE CategoryID=?');
                $stmt->execute([$code, $normal, $cash, $detail === '' ? null : $detail,
                    $description === '' ? null : $description, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE Categories SET Is_Active = ? WHERE CategoryID = ?');
                $stmt->execute([$action === 'enable' ? 1 : 0, $id]);
            }
        }
        $after = account_load($pdo, $id);
        if (!log_system_action($pdo, $userId, $action === 'create' ? AUDIT_ACTION_CREATE : AUDIT_ACTION_EDIT,
            'Chart of Accounts', $id, $before, $after, 'admin_accounts.php?status=all')) {
            throw new RuntimeException('The audit entry could not be saved.');
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException('The account code or account name/type is already in use. Check inactive accounts before creating a replacement.', 0, $e);
        }
        throw $e;
    }
}

function account_accounting_fields(array $input, string $type): array
{
    foreach (['account_code', 'normal_balance', 'is_cash_account'] as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) {
            throw new InvalidArgumentException('Invalid accounting field.');
        }
    }
    $code = account_text($input, 'account_code');
    $normal = account_text($input, 'normal_balance');
    $cash = account_text($input, 'is_cash_account');
    if (!mb_check_encoding($code, 'UTF-8') || mb_strlen($code) > 30
        || !in_array($normal, ['Debit', 'Credit'], true) || !in_array($cash, ['0', '1'], true)
        || ($cash === '1' && $type !== 'Asset')) {
        throw new InvalidArgumentException('Use a code of at most 30 characters, choose a normal balance, and mark only Asset accounts as cash accounts.');
    }
    return [$code === '' ? null : $code, $normal, (int) $cash];
}
