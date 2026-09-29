<?php
require_once __DIR__ . '/user_roles.php';

/** Returns false for invalid sessions; database failures deliberately propagate. */
function user_session_validate(PDO $pdo): bool
{
    if (empty($_SESSION['UserID'])) { return false; }
    $stmt = $pdo->prepare('SELECT FullName, Role, Is_Active FROM Users WHERE UserID = ?');
    $stmt->execute([(int) $_SESSION['UserID']]);
    $user = $stmt->fetch();
    if (!$user || (int) $user['Is_Active'] !== 1 || !user_role_is_valid($user['Role'])) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        return false;
    }
    $_SESSION['FullName'] = $user['FullName'];
    $_SESSION['Role'] = $user['Role'];
    return true;
}
