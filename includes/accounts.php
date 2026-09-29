<?php

require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/logger.php';

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
    $stmt = $pdo->prepare('SELECT CategoryID, Name, Type, Detail_Type, Description, Is_Active FROM Categories WHERE CategoryID = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** The page supplies authentication and CSRF validation before calling this. */
function account_save(PDO $pdo, int $userId, string $action, array $input): int
{
    if (!in_array($action, ['create', 'update', 'disable', 'enable'], true)) {
        throw new InvalidArgumentException('Select a valid account action.');
    }
    $detail = account_text($input, 'detail_type');
    $description = account_text($input, 'description');
    if (in_array($action, ['create', 'update'], true)
        && (mb_strlen($detail) > 100 || mb_strlen($description) > 2000)) {
        throw new InvalidArgumentException('Detail type must be at most 100 characters and description at most 2,000 characters.');
    }
    $pdo->beginTransaction();
    try {
        $before = null;
        if ($action === 'create') {
            $name = account_text($input, 'name');
            $type = account_text($input, 'type');
            if ($name === '' || mb_strlen($name) > 100 || !in_array($type, [CATEGORY_TYPE_FUND, CATEGORY_TYPE_EXPENSE], true)) {
                throw new InvalidArgumentException('Enter an account name of at most 100 characters and select Income or Expense.');
            }
            $stmt = $pdo->prepare('INSERT INTO Categories (Name, Type, Detail_Type, Description, Is_Active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$name, $type, $detail === '' ? null : $detail, $description === '' ? null : $description]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = account_id($input);
            $before = account_load($pdo, $id, true);
            if ($before === null) {
                throw new InvalidArgumentException('That account could not be found.');
            }
            if ($action === 'update') {
                // Names and types are historical identifiers, never editable here.
                $stmt = $pdo->prepare('UPDATE Categories SET Detail_Type = ?, Description = ? WHERE CategoryID = ?');
                $stmt->execute([$detail === '' ? null : $detail, $description === '' ? null : $description, $id]);
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
            throw new InvalidArgumentException('An account with this name and type already exists. Show inactive accounts to reactivate it if needed.', 0, $e);
        }
        throw $e;
    }
}
