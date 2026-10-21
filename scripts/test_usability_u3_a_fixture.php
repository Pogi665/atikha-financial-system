<?php
/** Synthetic U3-A register cases, only on a new guarded private fixture clone. */
define('ATIKHA_ISOLATED_TEST',true);require __DIR__.'/cli_common.php';
$o=getopt('',['fixture:']);$base=realpath(__DIR__.'/../.migration-private');$path=realpath($o['fixture']??'');
cli_require($base&&$path&&str_starts_with($path,$base.DIRECTORY_SEPARATOR),'Private fixture required.');
$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$f['database'];
cli_require((bool)preg_match('/\Aatikha_test_stage1_u3a[a-z0-9]+\z/',$db),'New U3-A disposable clone required.');
$evidence=realpath($f['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$base.DIRECTORY_SEPARATOR),'Private evidence required.');
define('RECEIPT_UPLOAD_DIR',$evidence);define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);
require __DIR__.'/../includes/cash_advance.php';$p=cli_db($db);cli_require(stage2_enabled($p)&&stage3_schema($p),'Complete integrated synthetic schema required.');
$uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='admin@example.invalid' AND Is_Active=1")->fetchColumn();cli_require($uid>0,'Synthetic owner required.');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
cli_require((int)$p->query("SELECT COUNT(*) FROM journal_entries WHERE reference LIKE 'U3A-%'")->fetchColumn()===0,'Fresh clone without U3-A postings required.');
$marker='U3A-'.strtoupper(bin2hex(random_bytes(4)));$csrf=['csrf_token'=>csrf_token()];
$party=workspace_master_save($p,$uid,$csrf+['kind'=>'parties','name'=>'Synthetic employee with a long label for training and field activities','party_type'=>'person','is_active'=>'1']);
$project=workspace_master_save($p,$uid,$csrf+['kind'=>'projects','name'=>'Synthetic outreach <training & community> project with a long display name','code'=>$marker,'description'=>'Private synthetic U3-A test','is_active'=>'1']);
$controls=$p->query('SELECT c.CategoryID FROM Categories c JOIN advance_control_designations d ON d.account_id=c.CategoryID WHERE c.Is_Active=1 ORDER BY c.CategoryID')->fetchAll(PDO::FETCH_COLUMN);cli_require(count($controls)>0,'Active designated control required.');
$bank=(int)$p->query("SELECT CategoryID FROM Categories WHERE Is_Active=1 AND Is_Cash_Account=1 ORDER BY CategoryID LIMIT 1")->fetchColumn();cli_require($bank>0,'Active cash account required.');
$today=journal_today();$release=(new DateTimeImmutable($today))->modify('-3 days')->format('Y-m-d');$due=(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');$ids=[];
for($i=0;$i<26;$i++){
    $payload=['entry_date'=>$release,'reference'=>$marker.'-'.$i,'description'=>$marker.' Synthetic outreach release '.$i.' for training, travel and community activities.','party_id'=>(string)$party['id'],'default_project_id'=>(string)$project['id'],'cash_account_id'=>(string)$bank,'cash_amount'=>$i===0?'10000.00':'100.00','cash_project_id'=>(string)$project['id'],'transaction_kind'=>'ordinary','lines'=>[],'documents'=>[],'control_account_id'=>(string)$controls[$i>=24?count($controls)-1:0],'due_date'=>$i===0?$today:$due,'approval_name'=>'','approval_date'=>'','approval_reference'=>'','line_notes'=>[]];
    $d=advance_save($p,$uid,$csrf+['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'workflow_kind'=>'advance_release','advance_id'=>'','payload'=>$payload]);
    $identity=['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];$review=advance_review($p,$uid,$csrf+$identity);$posted=advance_post($p,$uid,$csrf+$identity+['payload'=>$d['payload'],'review_token'=>$review['token']]);$ids[]=$posted['advance_id'];
}
$state=advance_state($p,$today);$samples=[];foreach($state['advances'] as $a){$s=advance_summary($a);$samples[$s['status']]??=$s['id'];}
echo json_encode(['database'=>$db,'marker'=>$marker,'party'=>$party['id'],'project'=>$project['id'],'controls'=>$controls,'today'=>$today,'release_date'=>$release,'ids'=>$ids,'amount'=>'12500.00','samples'=>$samples],JSON_THROW_ON_ERROR);
