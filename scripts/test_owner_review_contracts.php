<?php
/** Owner-review contracts: pure cutoff and private-revision allocation semantics. */
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/includes/planning_budget.php';
$n=0;function owner_check($ok,$label){global $n;if(!$ok)throw new RuntimeException($label);$n++;}
owner_check(reporting_actual_cutoff('2026-09','2026-10-29')==='2026-09-30','Past month ends normally');
owner_check(reporting_actual_cutoff('2026-10','2026-10-29')==='2026-10-29','Current cutoff is today');
owner_check(reporting_actual_cutoff('2026-11','2026-10-29')===null,'Future has no actual cutoff');
$base=['month'=>'2026-11','note'=>'Effective approval','lines'=>[['account_id'=>1,'project_id'=>null,'amount'=>'100.00'],['account_id'=>2,'project_id'=>null,'amount'=>'0.00'],['account_id'=>3,'project_id'=>4,'amount'=>'50.00']]];
$selected=['month'=>'2026-11','note'=>'Selected recommendation','lines'=>[['account_id'=>1,'project_id'=>null,'amount'=>'123.45']]];
$out=planning_budget_payload($selected,$base);$map=[];foreach($out['lines'] as $r)$map[$r['account_id']]=$r;
owner_check(count($map)===3,'Unselected approval allocations retained');owner_check($map[1]['amount']==='123.45','Selected allocation overlaid');owner_check($map[2]['amount']==='0.00','Existing deliberate zero retained');owner_check($map[3]['amount']==='50.00','Other project retained');
owner_check(count(planning_budget_payload($selected,null)['lines'])===1,'New draft contains only explicit selection');
$blankRejected=false;try{budget_revision_payload(['month'=>'2026-11','note'=>'','lines'=>[['account_id'=>1,'project_id'=>null,'amount'=>'']]]);}catch(BudgetCellProblem $e){$blankRejected=true;}owner_check($blankRejected,'Blank input must be omitted rather than coerced to zero');
$zero=budget_revision_payload(['month'=>'2026-11','note'=>'','lines'=>[['account_id'=>2,'project_id'=>null,'amount'=>'0.00']]]);owner_check(count($zero['lines'])===1&&$zero['lines'][0]['amount']==='0.00','Deliberate zero survives canonicalization');
$bad=false;try{planning_budget_payload($selected,['month'=>'2026-10','lines'=>[]]);}catch(ReportingProblem $e){$bad=$e->status===409;}owner_check($bad,'Cross-month copy rejected');
echo json_encode(['passed'=>$n]);
