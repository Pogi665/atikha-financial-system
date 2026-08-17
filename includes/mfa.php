<?php

/**
 * Email MFA (OTP) helpers for login verification.
 */

require_once __DIR__ . '/mailer.php';

function mfa_generate_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function mfa_mask_email(string $email): string
{
    $email = trim($email);

    if ($email === '' || !str_contains($email, '@')) {
        return 'your email address';
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLength = strlen($local);

    if ($localLength <= 1) {
        $maskedLocal = '*';
    } elseif ($localLength === 2) {
        $maskedLocal = $local[0] . '*';
    } else {
        $maskedLocal = $local[0] . str_repeat('*', min(3, $localLength - 2)) . substr($local, -1);
    }

    return $maskedLocal . '@' . $domain;
}

function mfa_save_code(PDO $pdo, int $userId, string $code): void
{
    $stmt = $pdo->prepare(
        'UPDATE Users
         SET MFA_Code = :code,
             MFA_Expires_At = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
         WHERE UserID = :user_id'
    );
    $stmt->execute([
        'code'    => $code,
        'user_id' => $userId,
    ]);
}

function mfa_clear_code(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE Users
         SET MFA_Code = NULL,
             MFA_Expires_At = NULL
         WHERE UserID = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
}

/**
 * @return 'valid'|'invalid'|'expired'
 */
function mfa_check_code(PDO $pdo, int $userId, string $code): string
{
    $stmt = $pdo->prepare(
        'SELECT MFA_Code,
                CASE WHEN MFA_Expires_At < NOW() THEN 1 ELSE 0 END AS is_expired
         FROM Users
         WHERE UserID = :user_id
           AND MFA_Code IS NOT NULL
           AND MFA_Expires_At IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'invalid';
    }

    if ((int) $row['is_expired'] === 1) {
        return 'expired';
    }

    $stored = (string) $row['MFA_Code'];

    if (!hash_equals($stored, $code)) {
        return 'invalid';
    }

    return 'valid';
}

function mfa_verify_code(PDO $pdo, int $userId, string $code): bool
{
    return mfa_check_code($pdo, $userId, $code) === 'valid';
}

function mfa_issue_and_send(PDO $pdo, int $userId, string $email, string $fullName): bool
{
    $code = mfa_generate_code();
    mfa_save_code($pdo, $userId, $code);

    return send_mfa_email($email, $fullName, $code);
}

function mfa_pending_user_id(): int
{
    return (int) ($_SESSION['mfa_pending_user_id'] ?? 0);
}

function mfa_set_pending(int $userId, string $maskedEmail): void
{
    $_SESSION['mfa_pending_user_id'] = $userId;
    $_SESSION['mfa_pending_email'] = $maskedEmail;
}

function mfa_clear_pending(): void
{
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_email']);
}

function mfa_pending_email(): string
{
    $email = $_SESSION['mfa_pending_email'] ?? '';

    return is_string($email) && $email !== '' ? $email : 'your email address';
}
