<?php
require_once __DIR__.'/cli_common.php';
require_once __DIR__.'/../includes/primary_administrator.php';
try {
    $o=getopt('',['database:','user-id:','actor-id:','confirm-initialize']);
    cli_require(isset($o['confirm-initialize']), 'Explicit --confirm-initialize is required. No automatic account selection.');
    $database=$o['database']??'';
    // Working initialization needs a separate, reviewed rollout procedure.
    cli_require((bool)preg_match('/\Aatikha_test_predefense_[a-f0-9]{12}\z/',$database),'This initializer is restricted to the approved disposable verification databases. Working rollout is not authorized.');
    $target=filter_var($o['user-id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$actor=filter_var($o['actor-id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    cli_require($target!==false&&$actor!==false,'Explicit valid --user-id and --actor-id are required.');
    $pdo=cli_db($database);cli_require($pdo->query('SELECT DATABASE()')->fetchColumn()===$database,'Database identity mismatch.');
    echo json_encode(primary_administrator_initialize($pdo,$target,$actor),JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
