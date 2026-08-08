<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

function reset_json_response(bool $ok, ?string $error = null): void
{
    $payload = ['ok' => $ok];

    if (!$ok && $error !== null && $error !== '') {
        $payload['error'] = $error;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reset_json_response(false, 'Invalid request method.');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    reset_json_response(false, 'Invalid or expired session. Please refresh and try again.');
}

$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    reset_json_response(false, 'Please enter a valid email address.');
}

$now = time();
$lastRequest = $_SESSION['last_reset_request'] ?? 0;

if (is_int($lastRequest) && ($now - $lastRequest) < 60) {
    reset_json_response(false, 'Please wait before submitting another request.');
}

$_SESSION['last_reset_request'] = $now;

try {
    $stmt = $pdo->prepare(
        'SELECT UserID, Email
         FROM Users
         WHERE Email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $pendingStmt = $pdo->prepare(
            'SELECT ResetID
             FROM password_resets
             WHERE UserID = :user_id
               AND Status = :status
             LIMIT 1'
        );
        $pendingStmt->execute([
            'user_id' => (int) $user['UserID'],
            'status'  => 'pending',
        ]);

        if (!$pendingStmt->fetch()) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO password_resets (UserID, Email, ip_address)
                 VALUES (:user_id, :email, :ip_address)'
            );
            $insertStmt->execute([
                'user_id'    => (int) $user['UserID'],
                'email'      => $user['Email'],
                'ip_address' => audit_client_ip(),
            ]);
        }
    }
} catch (PDOException $e) {
    error_log('Password reset request failed: ' . $e->getMessage());
    reset_json_response(false, 'Unable to process your request. Please try again later.');
}

reset_json_response(true);
