<?php
/** Historical actors are not login accounts. Never use this table for authorization. */
function user_identity_table(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_identities'");
    // Staged deployment: live schema 009 is applied only after separate approval.
    return (int) $stmt->fetchColumn() === 1 ? 'user_identities' : 'Users';
}

function user_identity_create(PDO $pdo, int $userId): void
{
    if (user_identity_table($pdo) === 'Users') {
        return; // Migration 009 backfills pre-migration accounts.
    }
    $stmt = $pdo->prepare('INSERT INTO user_identities (UserID, FullName, Email, Role)
        SELECT UserID, FullName, Email, Role FROM Users WHERE UserID=:id');
    $stmt->execute(['id' => $userId]);
    if ($stmt->rowCount() !== 1) {
        throw new PDOException('Unable to create historical identity.');
    }
}
