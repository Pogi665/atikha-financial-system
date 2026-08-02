<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/logger.php';

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
        'SELECT UserID, FullName, Role, Password
         FROM Users
         WHERE Email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['Password'])) {
        session_regenerate_id(true);
        $_SESSION['UserID'] = $user['UserID'];
        $_SESSION['FullName'] = $user['FullName'];
        $_SESSION['Role'] = $user['Role'];

        log_system_action(
            $pdo,
            (int) $user['UserID'],
            AUDIT_ACTION_LOGIN,
            'Auth',
            null,
            null,
            ['email' => $email, 'role' => $user['Role']]
        );

        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Login failed: ' . $e->getMessage());
}

header('Location: login.php?error=invalid');
exit;
