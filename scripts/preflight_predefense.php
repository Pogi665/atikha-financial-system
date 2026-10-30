<?php
/** Read-only verification of explicitly named disposable predefense schemas. */
require_once __DIR__.'/cli_common.php';
define('ATIKHA_ISOLATED_TEST', true);
require_once __DIR__.'/../includes/stage3_common.php';
require_once __DIR__.'/../includes/report_bundles.php';
require_once __DIR__.'/../includes/primary_administrator.php';
try {
    $options=getopt('', ['database:']);
    $database=$options['database']??'';
    cli_require((bool)preg_match('/\Aatikha_test_predefense_(?:demo_|race_)?[a-f0-9]{12}\z/', $database), 'Choose an explicitly owned disposable predefense database. Working rollout is separate.');
    $pdo=cli_db($database);
    cli_require($pdo->query('SELECT DATABASE()')->fetchColumn()===$database, 'Unexpected database identity.');
    $foreign=$pdo->query('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_SCHEMA<>DATABASE()')->fetchColumn();
    cli_require((int)$foreign===0, 'Cross-schema dependency: stop.');
    $out=['database'=>$database,'cross_schema_foreign_keys'=>0,
        'stage1'=>stage1_schema($pdo),'stage2'=>stage2_schema($pdo),'stage3'=>stage3_schema($pdo),
        'reports'=>report_configuration_schema($pdo),'budgets'=>budget_revision_schema($pdo),'periods'=>periods_schema($pdo),
        'primary'=>primary_administrator_state($pdo),
        'tables'=>(int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn(),
        'triggers'=>count($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC))];
    cli_require($out['stage1']&&$out['stage2']&&$out['stage3'], 'An accounting predecessor schema is incomplete.');
    foreach(['reports','budgets','periods'] as $component) cli_require($out[$component]['complete'], 'A later financial component is incomplete: '.$component);
    cli_require($out['primary']['state']==='ready', 'Primary Administrator setup is incomplete.');
    echo json_encode($out, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }
