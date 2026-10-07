<?php
/** Migration-only profile on a NEW guarded disposable database. No working config/data. */
require_once __DIR__.'/cli_common.php';define('ATIKHA_ISOLATED_TEST',true);
require_once __DIR__.'/../includes/correction_posting.php';
$options=getopt('',['database:']);$db=$options['database']??'';
cli_require(is_string($db)&&(bool)preg_match('/\Aatikha_test_stage1_[a-z0-9]+\z/',$db),'NEW guarded disposable database required.');
define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);
$checks=0;function transition(bool $ok,string $label): void {global $checks;cli_require($ok,$label);$checks++;echo "PASS: $label\n";}
try{
    $server=new PDO('mysql:host='.(getenv('ATIKHA_DB_HOST')?:'127.0.0.1'),getenv('ATIKHA_DB_USER')?:'root',getenv('ATIKHA_DB_PASSWORD')?:'');$server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$p=cli_db($db);cli_sql_file($p,__DIR__.'/../database.sql');
    foreach(glob(__DIR__.'/../migrations/*.sql') as $file)if(preg_match('/\A(\d{3})_/',basename($file),$m)&&(int)$m[1]<=18)cli_sql_file($p,$file);
    $accounts=$p->query('SELECT CategoryID FROM Categories ORDER BY CategoryID LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);$p->exec("INSERT INTO journal_entries(entry_date,description,status) VALUES('2026-10-01','Synthetic migration preservation','posted')");$id=(int)$p->lastInsertId();$p->prepare('INSERT INTO journal_entry_lines(journal_entry_id,account_id,debit_amount,credit_amount) VALUES(?,?,1,0),(?,?,0,1)')->execute([$id,$accounts[0],$id,$accounts[1]]);
    $headers=$p->query('SELECT * FROM journal_entries ORDER BY id')->fetchAll();$lines=$p->query('SELECT * FROM journal_entry_lines ORDER BY id')->fetchAll();
    transition(!stage1_enabled($p),'Before 019 ordinary workspace unavailable');
    cli_sql_file($p,__DIR__.'/../migrations/019_stage1_foundation.sql');transition(stage1_enabled($p),'019 enables complete Stage 1 schema');
    $after=$p->query('SELECT * FROM journal_entries ORDER BY id')->fetchAll();transition(array_intersect_key($after[0],$headers[0])===$headers[0]&&$after[0]['source_book']===null,'019 preserves original header and does not infer its book');$afterLines=$p->query('SELECT * FROM journal_entry_lines ORDER BY id')->fetchAll();transition(array_map(fn($r)=>array_intersect_key($r,$lines[0]),$afterLines)===$lines,'019 preserves original lines');
    transition(!stage2_enabled($p)&&!stage3_enabled($p),'Absent 020/021 cannot enable later workflows');
    cli_sql_file($p,__DIR__.'/../migrations/020_stage2_advances.sql');transition(stage2_enabled($p)&&!stage3_enabled($p),'020 completes advances but does not enable absent corrections');
    $beforeHeaders=$p->query('SELECT * FROM journal_entries ORDER BY id')->fetchAll();$beforeLines=$p->query('SELECT * FROM journal_entry_lines ORDER BY id')->fetchAll();transition($beforeHeaders===$after&&$beforeLines===$afterLines,'020 preserves complete journal headers/lines');
    transition(stage3_schema_state($p)['state']==='absent','021 prerequisite state is absent, not partial');
    cli_sql_file($p,__DIR__.'/../migrations/021_journal_corrections.sql');transition(stage3_schema($p)&&stage3_enabled($p),'021 completes schema with explicit isolated flags');transition($beforeHeaders===$p->query('SELECT * FROM journal_entries ORDER BY id')->fetchAll()&&$beforeLines===$p->query('SELECT * FROM journal_entry_lines ORDER BY id')->fetchAll(),'021 preserves journal values/counts');
    try{$p->exec("UPDATE journal_entries SET description='forbidden' WHERE id=$id");throw new RuntimeException('Immutable history accepted mutation');}catch(PDOException $e){transition($e->getCode()==='45000','021 enforces immutable posted header');}
    $trigger=$p->query('SHOW CREATE TRIGGER s3_mapping_no_delete')->fetch();$p->exec('DROP TRIGGER s3_mapping_no_delete');transition(stage3_schema_state($p)['state']==='partial'&&!stage3_enabled($p),'Partial 021 disables correction entry');$p->exec($trigger['SQL Original Statement']);transition(stage3_schema($p),'Disposable trigger restoration returns complete state');
    echo "Schema transition checks: $checks\nDisposable database: $db\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
