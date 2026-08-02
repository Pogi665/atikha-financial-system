<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/logger.php';

session_start();

// Read the identity before the session is torn down, otherwise there is nobody
// left to attribute the logout to.
$userId = (int) ($_SESSION['UserID'] ?? 0);

if ($userId > 0) {
    log_system_action($pdo, $userId, AUDIT_ACTION_LOGOUT, 'Auth');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
