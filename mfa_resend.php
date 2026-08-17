<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mfa.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

function mfa_resend_json_response(bool $ok, ?string $error = null): void
{
    $payload = ['ok' => $ok];

    if (!$ok && $error !== null && $error !== '') {
        $payload['error'] = $error;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mfa_resend_json_response(false, 'Invalid request method.');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    mfa_resend_json_response(false, 'Invalid or expired session. Please refresh and try again.');
}

$pendingUserId = mfa_pending_user_id();

if ($pendingUserId <= 0) {
    mfa_resend_json_response(false, 'Your verification session has expired. Please sign in again.');
}

$now = time();
$lastResend = $_SESSION['last_mfa_resend'] ?? 0;

if (is_int($lastResend) && ($now - $lastResend) < 60) {
    mfa_resend_json_response(false, 'Please wait before requesting another code.');
}

$_SESSION['last_mfa_resend'] = $now;

try {
    $stmt = $pdo->prepare(
        'SELECT Email, FullName
         FROM Users
         WHERE UserID = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $pendingUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        mfa_clear_pending();
        mfa_resend_json_response(false, 'Your verification session has expired. Please sign in again.');
    }

    if (!mfa_issue_and_send(
        $pdo,
        $pendingUserId,
        (string) $user['Email'],
        (string) $user['FullName']
    )) {
        mfa_resend_json_response(false, 'Unable to send the verification code. Please try again later.');
    }

    mfa_resend_json_response(true);
} catch (PDOException $e) {
    error_log('MFA resend failed: ' . $e->getMessage());
    mfa_resend_json_response(false, 'Unable to send the verification code. Please try again later.');
}
