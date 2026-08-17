<?php

/**
 * CLI smoke test for MFA helpers and database columns.
 * Usage: php scripts/test_mfa_flow.php
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/mfa.php';

$failures = 0;

function assert_true(string $label, bool $condition): void
{
    global $failures;

    if ($condition) {
        echo "[PASS] {$label}\n";
    } else {
        echo "[FAIL] {$label}\n";
        $failures++;
    }
}

$code = mfa_generate_code();
assert_true('mfa_generate_code returns 6 digits', preg_match('/^\d{6}$/', $code) === 1);
assert_true('mfa_mask_email masks local part', mfa_mask_email('staff@atikha.local') === 's***f@atikha.local');

$stmt = $pdo->query("SHOW COLUMNS FROM Users LIKE 'MFA_Code'");
assert_true('Users.MFA_Code column exists', (bool) $stmt->fetch());

$stmt = $pdo->query("SHOW COLUMNS FROM Users LIKE 'MFA_Expires_At'");
assert_true('Users.MFA_Expires_At column exists', (bool) $stmt->fetch());

$userStmt = $pdo->prepare('SELECT UserID FROM Users WHERE Email = :email LIMIT 1');
$userStmt->execute(['email' => 'staff@atikha.local']);
$user = $userStmt->fetch();

if (!$user) {
    echo "[SKIP] staff@atikha.local not found — seed user required for verify test\n";
    exit($failures > 0 ? 1 : 0);
}

$userId = (int) $user['UserID'];
$testCode = '591024';

mfa_save_code($pdo, $userId, $testCode);
assert_true('mfa_check_code accepts saved code', mfa_check_code($pdo, $userId, $testCode) === 'valid');
assert_true('mfa_check_code rejects wrong code', mfa_check_code($pdo, $userId, '000000') === 'invalid');

$pdo->prepare('UPDATE Users SET MFA_Expires_At = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE UserID = :id')
    ->execute(['id' => $userId]);
assert_true('mfa_check_code rejects expired code', mfa_check_code($pdo, $userId, $testCode) === 'expired');

mfa_clear_code($pdo, $userId);
$row = $pdo->prepare('SELECT MFA_Code, MFA_Expires_At FROM Users WHERE UserID = :id');
$row->execute(['id' => $userId]);
$cleared = $row->fetch();
assert_true('mfa_clear_code nulls MFA columns', $cleared['MFA_Code'] === null && $cleared['MFA_Expires_At'] === null);

exit($failures > 0 ? 1 : 0);
