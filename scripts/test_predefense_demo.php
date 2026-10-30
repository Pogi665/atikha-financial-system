<?php
/** Read-only business-key/oracle checks. Requires an explicitly prepared V2 demo. */
if(PHP_SAPI!=='cli')exit;
define('ATIKHA_ISOLATED_TEST',true);
require_once __DIR__.'/cli_common.php';
$o=getopt('',['context:']);$path=realpath($o['context']??'');$private=realpath(__DIR__.'/../.migration-private');
cli_require($path&&str_starts_with($path,$private.DIRECTORY_SEPARATOR),'Private demo context required.');
$c=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$db=$c['database']??'';
cli_require((bool)preg_match('/\Aatikha_test_predefense_demo_[a-f0-9]{12}\z/',$db),'Only the owned V2 demo is allowed.');
$directory=dirname($path);$data=json_decode(file_get_contents($directory.'/dataset.private.json'),true,64,JSON_THROW_ON_ERROR);$seed=json_decode(file_get_contents($directory.'/seed-complete.private.json'),true,64,JSON_THROW_ON_ERROR);
cli_require($seed['database']===$db&&$seed['dataset']==='ATIKHA-DEMO-V2-FULL','Demo provenance mismatch.');
define('RECEIPT_UPLOAD_DIR',$directory.'/evidence');foreach(['STAGE1_WORKSPACE_ENABLED','STAGE2_ADVANCES_ENABLED','STAGE3_CORRECTIONS_ENABLED','OCR_JOURNAL_ENABLED'] as $flag)define($flag,true);
require_once __DIR__.'/../includes/report_bundles.php';require_once __DIR__.'/../includes/general_ledger_query.php';
$pdo=cli_db($db);cli_require($pdo->query('SELECT DATABASE()')->fetchColumn()===$db,'Wrong database.');$_SESSION=['UserID'=>$seed['users']['ADMIN-FINANCE'],'Role'=>'Admin'];$uid=$_SESSION['UserID'];
$expenseChecks=0;$s=$pdo->prepare('SELECT account_id,fund_project_id,debit_amount,credit_amount FROM journal_entry_lines WHERE journal_entry_id=?');
foreach($data['recognized_expenses'] as $row){
    if($row['date']>accounting_today())continue;
    $id=$seed['journals'][$row['record_key']]??null;cli_require((bool)$id,'Missing final expense business key: '.$row['record_key']);$s->execute([$id]);$found=false;
    foreach($s as $line)if((int)$line['account_id']===$seed['accounts'][$row['expense_account']]&&($line['fund_project_id']===null?null:(int)$line['fund_project_id'])===($row['project_code']===''?null:$seed['projects'][$row['project_code']])&&accounting_add(accounting_cents($line['debit_amount']),-accounting_cents($line['credit_amount']))===accounting_cents($row['amount_php']))$found=true;
    cli_require($found,'Wrong final amount/account/project: '.$row['record_key']);$expenseChecks++;
}
$monthly=[];foreach($data['monthly_expected_summary'] as $row){$m=financial_report_model($pdo,$uid,$row['month']);$b=budget_actual_model($pdo,$uid,$row['month']);$l=ledger_read($pdo,$uid,['account_id'=>(string)$seed['accounts']['Community Program Bank'],'from'=>$row['month'].'-01','to'=>reporting_month_range($row['month'])[1],'search'=>'','page'=>'1','page_size'=>'25']);$actual=[$m['income_statement']['income'],$m['income_statement']['expenses'],$l['opening'],$l['ending'],$b['approved_total']];$expected=[$row['incoming_funds_php'],$row['recognized_expenses_php'],$row['opening_demo_cash_php'],$row['closing_demo_cash_php'],$row['approved_budget_php']];cli_require($m['source_valid']&&$actual===$expected,'Monthly financial oracle mismatch: '.$row['month']);$monthly[$row['month']]=['match'=>true,'income'=>$actual[0],'expenses'=>$actual[1],'opening_cash'=>$actual[2],'closing_cash'=>$actual[3],'approved_budget'=>$actual[4]];}
$volume=['incoming'=>count($data['incoming_funds']),'recognized_expenses'=>$expenseChecks,'reclassifications'=>count($data['general_journal_reclassifications']),'advances'=>count($seed['advances']),'corrections'=>count($seed['corrections']),'evidence'=>count(array_filter($data['evidence_metadata'],static fn($r)=>isset($seed['evidence'][$r['evidence_key']]))),'ocr'=>count($seed['ocr']),'supplied_budget_allocations'=>count($data['monthly_budget_allocations']),'report_states'=>count($seed['reports']),'parties'=>count($seed['parties'])];
$targets=[120,360,24,22,15,220,36,221,14,64];cli_require(array_values($volume)===$targets,'V2 module-density targets differ.');
$counts=[];foreach(['journal_entries','journal_entry_lines','Receipts','evidence_item_reviews','cash_advances','journal_corrections','budget_revisions','budget_revision_allocations','complete_report_bundles','complete_report_reviews','Notifications','audit_logs','projects','parties','Categories'] as $table)$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
echo json_encode(['ok'=>true,'dataset'=>$seed['dataset'],'expense_business_keys'=>$expenseChecks,'monthly'=>$monthly,'volume'=>$volume,'counts'=>$counts],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
