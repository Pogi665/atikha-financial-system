<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/mfa.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.php?error=invalid');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT UserID, FullName, Role, Email, Password
         FROM Users
         WHERE Email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['Password'])) {
        $userId = (int) $user['UserID'];

        if (!mfa_issue_and_send($pdo, $userId, (string) $user['Email'], (string) $user['FullName'])) {
            mfa_clear_code($pdo, $userId);
            header('Location: login.php?error=mfa_send');
            exit;
        }

        mfa_set_pending($userId, mfa_mask_email((string) $user['Email']));

        header('Location: mfa_verify.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Login failed: ' . $e->getMessage());
}

header('Location: login.php?error=invalid');
exit;
