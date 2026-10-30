<?php
/** Focused designation protection for supported account writers. No role change. */
function primary_administrator_state(PDO $pdo, bool $lock = false): array
{
    $q = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_primary_administrator'");
    if ((int)$q->fetchColumn() === 0) return ['state'=>'absent','user_id'=>null];
    try {
        $columns = $pdo->query('SHOW COLUMNS FROM system_primary_administrator')->fetchAll(PDO::FETCH_ASSOC);
        if (array_column($columns, 'Field') !== ['id','user_id','designated_by','designated_at','schema_version']) return ['state'=>'partial','user_id'=>null];
        $keys = $pdo->query("SELECT COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_primary_administrator' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn(); $found = [];
        foreach ($keys as $key) {
            if ($key['REFERENCED_TABLE_SCHEMA'] !== $database) return ['state'=>'partial','user_id'=>null];
            $found[$key['COLUMN_NAME']] = strtolower($key['REFERENCED_TABLE_NAME']).'.'.$key['REFERENCED_COLUMN_NAME'];
        }
        if ($found !== ['user_id'=>'users.UserID','designated_by'=>'user_identities.UserID'] && $found !== ['designated_by'=>'user_identities.UserID','user_id'=>'users.UserID']) return ['state'=>'partial','user_id'=>null];
        $rules=$pdo->query("SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='system_primary_administrator'")->fetchAll(PDO::FETCH_ASSOC);
        if(count($rules)!==2)return ['state'=>'partial','user_id'=>null];
        foreach($rules as $rule)if(!in_array($rule['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rule['DELETE_RULE'],['RESTRICT','NO ACTION'],true))return ['state'=>'partial','user_id'=>null];
        $definition = $pdo->query('SHOW CREATE TABLE system_primary_administrator')->fetch(PDO::FETCH_NUM)[1];
        $flat = strtolower(preg_replace('/[\s`()]+/', '', $definition));
        if (!str_contains($flat, 'engine=innodb') || !str_contains($flat, 'checkid=1') || !str_contains($flat, 'checkschema_version=1') || !str_contains($flat, 'uniquekeyprimary_administrator_useruser_id') || !str_contains($flat, 'primarykeyid')) return ['state'=>'partial','user_id'=>null];
        $rows = $pdo->query('SELECT * FROM system_primary_administrator'.($lock?' FOR UPDATE':''))->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return ['state'=>'uninitialized','user_id'=>null];
        if (count($rows)!==1 || (int)$rows[0]['id']!==1 || (int)$rows[0]['schema_version']!==1) return ['state'=>'partial','user_id'=>null];
        $id = (int)$rows[0]['user_id']; $q=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=?');$q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['Role']!=='Admin' || (int)$user['Is_Active']!==1) return ['state'=>'partial','user_id'=>null];
        return ['state'=>'ready','user_id'=>$id];
    } catch (PDOException $e) { return ['state'=>'partial','user_id'=>null]; }
}

function primary_administrator_lock(PDO $pdo): int
{
    if (!$pdo->inTransaction()) throw new LogicException('Account protection requires a transaction.');
    $state=primary_administrator_state($pdo,true);
    if ($state['state']!=='ready') throw new InvalidArgumentException('User changes are unavailable until the Primary Administrator designation is verified. Ask the system custodian to complete account setup.');
    return $state['user_id'];
}

function primary_administrator_guard(int $primary, int $actor, int $target, string $action, ?string $role=null): void
{
    if ($target!==$primary) return;
    if ($actor!==$primary) throw new InvalidArgumentException('Only the Primary Administrator can change this protected account.');
    if (in_array($action,['disable_user','delete_user'],true) || ($action==='update_user' && $role!=='Admin')) throw new InvalidArgumentException('The Primary Administrator must remain active with the System Administrator role.');
}

function primary_administrator_initialize(PDO $pdo, int $target, int $actor): array
{
    $state=primary_administrator_state($pdo);
    if (!in_array($state['state'],['uninitialized','ready'],true)) throw new RuntimeException('The designation schema is absent or partial; initialization refused.');
    $pdo->beginTransaction();
    try {
        $state=primary_administrator_state($pdo,true);
        $ids=array_values(array_unique([$target,$actor]));sort($ids,SORT_NUMERIC);
        foreach($ids as $id){$q=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u||$u['Role']!=='Admin'||(int)$u['Is_Active']!==1)throw new RuntimeException('Select explicit existing active administrator accounts.');}
        if($state['state']==='ready'){
            if($state['user_id']!==$target)throw new RuntimeException('A different Primary Administrator is already designated; transfer is not available.');
            $pdo->commit();return ['user_id'=>$target,'duplicate'=>true];
        }
        if($state['state']!=='uninitialized')throw new RuntimeException('The designation changed or needs an integrity check.');
        $pdo->prepare('INSERT INTO system_primary_administrator(id,user_id,designated_by,designated_at,schema_version) VALUES(1,?,?,UTC_TIMESTAMP(),1)')->execute([$target,$actor]);
        require_once __DIR__.'/logger.php';
        if(!log_system_action($pdo,$actor,AUDIT_ACTION_CREATE,'Primary Administrator',$target,null,['designated_user_id'=>$target],'admin_users.php'))throw new RuntimeException('Designation audit could not be saved.');
        $pdo->commit();return ['user_id'=>$target,'duplicate'=>false];
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
