<?php
/** Read-only catalog-query diagnosis, not a shared schema-helper implementation. */
require __DIR__.'/cli_common.php';$o=getopt('',['fixture:']);$private=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private synthetic fixture required.');$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_perf[a-z0-9]+\z/',$db),'Guarded performance clone required.');$p=cli_db($db);$result=['diagnostic_only'=>true,'samples'=>[]];
$old="SELECT TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 's3_%'";
$s2=['s2_operations_no_update','s2_operations_no_delete','s2_due_no_update','s2_due_no_delete','s2_advance_identity','s2_advance_no_delete'];
function keyed(array $rows):array{$out=[];foreach($rows as $r)$out[$r['TRIGGER_NAME']]=$r;ksort($out);return $out;}
for($i=0;$i<6;$i++){
    $t=hrtime(true);$a=$p->query($old)->fetchAll();$oldMs=(hrtime(true)-$t)/1e6;
    $t=hrtime(true);$rows=$p->query('SHOW TRIGGERS FROM `'.str_replace('`','``',$db).'`')->fetchAll();$showMs=(hrtime(true)-$t)/1e6;
    $mapped=[];foreach($rows as $r)if(preg_match('/^s3./s',$r['Trigger']))$mapped[]=['TRIGGER_NAME'=>$r['Trigger'],'EVENT_OBJECT_TABLE'=>$r['Table'],'ACTION_TIMING'=>$r['Timing'],'EVENT_MANIPULATION'=>$r['Event'],'ACTION_STATEMENT'=>$r['Statement']];
    cli_require(keyed($a)===keyed($mapped),'Catalog trigger definitions must agree exactly; do not assume equivalence.');
    $count=(int)$p->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('".implode("','",$s2)."')")->fetchColumn();
    cli_require($count===count(array_intersect($s2,array_column($rows,'Trigger'))),'Stage2 trigger membership must agree exactly.');
    $result['samples'][]=['sample'=>$i,'first_or_repeat'=>$i===0?'first':'repeat','information_schema_ms'=>$oldMs,'show_triggers_ms'=>$showMs,'stage3_definition_equality'=>true,'stage2_membership_equality'=>true];
}
file_put_contents(dirname($path).'/perf-catalog-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Catalog diagnostic: 6 exact-definition and membership comparisons passed. No source helper changed.'.PHP_EOL;
