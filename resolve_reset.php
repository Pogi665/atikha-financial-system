<?php

/**
 * Resolves one admin-mediated password reset request.
 *
 * POST-only, Administrator-only, JSON in and out. Called from the resolve
 * modal on admin_users.php.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/user_roles.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<string, mixed>|null $data
 */
function resolve_respond(bool $ok, ?array $data, string $error, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resolve_respond(false, null, 'This endpoint accepts POST requests only.', 405);
}

if (empty($_SESSION['UserID'])) {
    resolve_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

if (($_SESSION['Role'] ?? '') !== 'Admin') {
    resolve_respond(false, null, 'Resolving password resets is restricted to System Administrators.', 403);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    resolve_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

$adminId = (int) $_SESSION['UserID'];
$resetId = isset($_POST['reset_id']) ? (int) $_POST['reset_id'] : 0;
$newPassword = (string) ($_POST['new_password'] ?? '');

if ($resetId <= 0) {
    resolve_respond(false, null, 'That reset request could not be identified.', 422);
}

if (strlen($newPassword) < USER_PASSWORD_MIN_LENGTH) {
    resolve_respond(
        false,
        null,
        'The temporary password must be at least ' . USER_PASSWORD_MIN_LENGTH . ' characters.',
        422
    );
}

try {
    $pdo->beginTransaction();

    // FOR UPDATE holds the row for the rest of the transaction, so two admins
    // resolving the same request at once cannot both write a password.
    $lookup = $pdo->prepare(
        'SELECT ResetID, UserID, Email
         FROM password_resets
         WHERE ResetID = :reset_id AND Status = \'pending\'
         FOR UPDATE'
    );
    $lookup->execute(['reset_id' => $resetId]);
    $reset = $lookup->fetch();

    if ($reset === false) {
        $pdo->rollBack();
        resolve_respond(false, null, 'That request was already resolved or no longer exists.', 409);
    }

    $targetUserId = (int) $reset['UserID'];

    $updateUser = $pdo->prepare(
        'UPDATE Users
         SET Password = :password
         WHERE UserID = :user_id'
    );
    $updateUser->execute([
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'user_id'  => $targetUserId,
    ]);

    $closeRequest = $pdo->prepare(
        'UPDATE password_resets
         SET Status = \'completed\',
             ResolvedAt = NOW(),
             ResolvedBy_UserID = :admin_id
         WHERE ResetID = :reset_id AND Status = \'pending\''
    );
    $closeRequest->execute([
        'admin_id' => $adminId,
        'reset_id' => $resetId,
    ]);

    // Inside a transaction log_system_action() rethrows, so a failed audit
    // write rolls the password change back with it.
    log_system_action(
        $pdo,
        $adminId,
        AUDIT_ACTION_APPROVAL,
        'Users',
        $targetUserId,
        ['reset_status' => 'pending'],
        [
            'reset_status' => 'completed',
            'reset_id'     => $resetId,
            'email'        => $reset['Email'],
        ]
    );

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Password reset resolution failed for request ' . $resetId . ': ' . $e->getMessage());
    resolve_respond(false, null, 'The password could not be reset. Please try again.', 500);
}

resolve_respond(true, ['reset_id' => $resetId], '');
