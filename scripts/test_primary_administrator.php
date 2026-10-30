<?php
if(PHP_SAPI!=='cli')exit;
define('ATIKHA_ISOLATED_TEST',true);
require_once __DIR__.'/cli_common.php';
require_once __DIR__.'/../includes/users.php';
try {
    $o=getopt('',['database:','primary-id:','other-id:']);$db=$o['database']??'';
    cli_require((bool)preg_match('/\Aatikha_test_predefense_[a-f0-9]{12}\z/',$db),'Only the owned disposable predefense target is allowed.');
    $p=cli_db($db);cli_require($p->query('SELECT DATABASE()')->fetchColumn()===$db,'Wrong database');
    $primary=users_id($o['primary-id']??null);$other=users_id($o['other-id']??null);cli_require($primary>0&&$other>0&&$primary!==$other,'Provide explicit fixture owners.');
    $before=users_load($p,$primary);$state=primary_administrator_state($p);cli_require($state['state']==='ready'&&$state['user_id']===$primary,'Fixture designation is not ready.');
    $count=0;$check=static function($value,$label)use(&$count){cli_require((bool)$value,$label);$count++;};
    foreach(['disable_user','reactivate_user','update_user'] as $action){
        $input=['user_id'=>$primary,'full_name'=>'Unauthorized change','email'=>'guard-check@example.invalid','role'=>'Admin','password'=>'UnchangedSecret9!'];
        try{users_save($p,$other,$action,$input);throw new RuntimeException('Protected request unexpectedly succeeded.');}catch(InvalidArgumentException $e){$check(str_contains($e->getMessage(),'Primary Administrator'),'Another Admin is rejected.');}
        $check(users_load($p,$primary)===$before,'Protected identity unchanged.');
    }
    foreach(['disable_user','update_user'] as $action){try{users_save($p,$primary,$action,['user_id'=>$primary,'full_name'=>$before['FullName'],'email'=>$before['Email'],'role'=>'Management','password'=>'']);throw new RuntimeException('Self-demotion succeeded.');}catch(InvalidArgumentException $e){$check(true,'Self lock retained.');}}
    users_save($p,$primary,'update_user',['user_id'=>$primary,'full_name'=>$before['FullName'],'email'=>$before['Email'],'role'=>'Admin','password'=>'']);$check(users_load($p,$primary)===$before,'Existing self-edit preserved.');
    $p->beginTransaction();try{$p->exec('DELETE FROM system_primary_administrator');$check(primary_administrator_state($p)['state']==='uninitialized','Missing initialization is detected.');}finally{$p->rollBack();}
    $p->beginTransaction();try{$p->exec("UPDATE Users SET Role='Management' WHERE UserID=".$primary);$check(primary_administrator_state($p)['state']==='partial','Invalid designated role is detected.');}finally{$p->rollBack();}
    $check(primary_administrator_initialize($p,$primary,$primary)['duplicate'],'Initialization recovery.');
    try{primary_administrator_initialize($p,$other,$primary);throw new RuntimeException('Transfer unexpectedly succeeded.');}catch(RuntimeException $e){$check(str_contains($e->getMessage(),'different Primary'),'No transfer writer.');}
    $check(primary_administrator_state($p)['user_id']===$primary,'Exactly one ready primary remains.');
    echo json_encode(['ok'=>true,'assertions'=>$count],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
