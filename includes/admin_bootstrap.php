<?php
require_once __DIR__ . '/user_roles.php';
require_once __DIR__ . '/user_identities.php';
require_once __DIR__ . '/logger.php';

/** Called only by the CLI setup tool and isolated integration tests. */
function bootstrap_admin(PDO $pdo, string $name, string $email, string $password): int
{
    if (trim($name)==='' || strlen($name)>255 || strlen($email)>255 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<USER_PASSWORD_MIN_LENGTH) {
        throw new InvalidArgumentException('Valid name, email and password of at least ' . USER_PASSWORD_MIN_LENGTH . ' characters are required.');
    }
    if (user_identity_table($pdo)!=='user_identities') { throw new RuntimeException('Apply all migrations before bootstrap.'); }
    $lock=$pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':initial_admin'), 0)")->fetchColumn();
    if ((int)$lock!==1) {throw new RuntimeException('Another bootstrap is running.');}
    try {
        $pdo->beginTransaction();
        if ((int)$pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn()!==0 || (int)$pdo->query('SELECT COUNT(*) FROM user_identities')->fetchColumn()!==0) {
            throw new RuntimeException('Bootstrap refused: this is not an empty fresh installation.');
        }
        $s=$pdo->prepare("INSERT INTO Users (FullName,Role,Email,Password) VALUES (:name,'Admin',:email,:hash)");
        $s->execute(['name'=>trim($name),'email'=>trim($email),'hash'=>password_hash($password,PASSWORD_DEFAULT)]);
        $id=(int)$pdo->lastInsertId();user_identity_create($pdo,$id);
        if (!log_system_action($pdo,$id,'CREATE','Users',$id,null,['reason'=>'CLI initial Administrator bootstrap','role'=>'Admin'])) {throw new RuntimeException('Bootstrap audit failed.');}
        $pdo->commit();return $id;
    } catch(Throwable $e) {if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    finally {$pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':initial_admin'))");}
}
