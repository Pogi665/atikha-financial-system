<?php
require_once __DIR__ . '/user_roles.php';
require_once __DIR__ . '/user_identities.php';
require_once __DIR__ . '/logger.php';

function users_text(array $input, string $key): string
{
    if (isset($input[$key]) && !is_string($input[$key])) {
        throw new InvalidArgumentException('Invalid form input.');
    }
    return trim($input[$key] ?? '');
}

function users_id($value): int
{
    return is_scalar($value) ? (int) (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0) : 0;
}

function users_load(PDO $pdo, int $id, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT UserID, FullName, Email, Role, Is_Active, created_at FROM Users WHERE UserID = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function users_audit_values(array $row): array
{
    return ['full_name' => $row['FullName'], 'email' => $row['Email'], 'role' => $row['Role'], 'is_active' => (int) $row['Is_Active']];
}

/** Caller must validate POST and CSRF before invoking this function. */
function users_save(PDO $pdo, int $actorId, string $action, array $input): int
{
    if (!in_array($action, ['create_user', 'update_user', 'disable_user', 'reactivate_user'], true)) {
        throw new InvalidArgumentException('Select a valid user action.');
    }
    $id = $action === 'create_user' ? 0 : users_id($input['user_id'] ?? null);
    if ($action !== 'create_user' && !$id) { throw new InvalidArgumentException('Select a valid user.'); }
    $password = '';
    if (in_array($action, ['create_user', 'update_user'], true)) {
        $name = users_text($input, 'full_name');
        $email = users_text($input, 'email');
        $role = users_text($input, 'role');
        if ($name === '' || mb_strlen($name) > 255) { throw new InvalidArgumentException('Full name is required and must be at most 255 characters.'); }
        if (mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid email address of at most 255 characters.'); }
        if (!user_role_is_valid($role)) { throw new InvalidArgumentException('Select a valid role.'); }
        if (!is_string($input['password'] ?? '')) { throw new InvalidArgumentException('Invalid password input.'); }
        $password = $input['password'] ?? '';
        if (($action === 'create_user' || $password !== '') && strlen($password) < USER_PASSWORD_MIN_LENGTH) {
            throw new InvalidArgumentException('The password must be at least ' . USER_PASSWORD_MIN_LENGTH . ' characters.');
        }
    }
    $pdo->beginTransaction();
    try {
        $ids = array_unique(array_filter([$actorId, $id]));
        sort($ids, SORT_NUMERIC);
        $locked = [];
        foreach ($ids as $lockId) { $locked[$lockId] = users_load($pdo, $lockId, true); }
        $actor = $locked[$actorId] ?? null;
        if (!$actor || !$actor['Is_Active'] || $actor['Role'] !== 'Admin') { throw new InvalidArgumentException('An active administrator account is required.'); }
        $before = $id ? ($locked[$id] ?? null) : null;
        if ($id && !$before) { throw new InvalidArgumentException('That user could not be found.'); }
        if ($id === $actorId && ($action === 'disable_user' || ($action === 'update_user' && $role !== 'Admin'))) {
            throw new InvalidArgumentException('You cannot disable your own account or remove your administrator role.');
        }
        if ($action === 'create_user') {
            $stmt = $pdo->prepare('INSERT INTO Users (FullName, Email, Role, Password, Is_Active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            user_identity_create($pdo, $id);
        } elseif ($action === 'update_user') {
            $sql = 'UPDATE Users SET FullName = ?, Email = ?, Role = ?';
            $params = [$name, $email, $role];
            if ($password !== '') { $sql .= ', Password = ?'; $params[] = password_hash($password, PASSWORD_DEFAULT); }
            $params[] = $id;
            $pdo->prepare($sql . ' WHERE UserID = ?')->execute($params);
        } else {
            $active = $action === 'reactivate_user' ? 1 : 0;
            if ((int) $before['Is_Active'] === $active) { $pdo->commit(); return $id; }
            $pdo->prepare('UPDATE Users SET Is_Active = ?' . ($active ? '' : ', MFA_Code = NULL, MFA_Expires_At = NULL') . ' WHERE UserID = ?')->execute([$active, $id]);
        }
        $after = users_audit_values(users_load($pdo, $id));
        if ($action === 'update_user' && $password !== '') { $after['credential_reset'] = true; }
        if (!log_system_action($pdo, $actorId, $action === 'create_user' ? AUDIT_ACTION_CREATE : AUDIT_ACTION_EDIT,
            'Users', $id, $before ? users_audit_values($before) : null, $after, 'admin_users.php')) {
            throw new RuntimeException('The audit entry could not be saved.');
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException('An account with that email already exists.', 0, $e);
        }
        throw $e;
    }
}
