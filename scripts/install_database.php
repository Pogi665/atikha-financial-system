<?php
require_once __DIR__.'/cli_common.php';
try {
    $o=getopt('',['database:','execute']);$pdo=cli_db($o['database']??'');
    cli_require($pdo->query('SHOW TABLES')->fetchAll()===[],'Fresh install refused: database is not empty.');
    if(!isset($o['execute'])){echo "DRY RUN: empty database ready for schema installation.\n";exit;}
    cli_sql_file($pdo,__DIR__.'/../database.sql');
    foreach(glob(__DIR__.'/../migrations/*.sql') as $path){cli_sql_file($pdo,$path);}
    echo "Schema installed. DDL committed independently. Run bootstrap_admin.php from an interactive console.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
