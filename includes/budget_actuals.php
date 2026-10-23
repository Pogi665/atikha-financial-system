<?php
/** Approved monthly allocations and exact recognized expense in disjoint line buckets. */
require_once __DIR__.'/budget_revisions.php';require_once __DIR__.'/financial_report_query.php';
function budget_actual_model(PDO $pdo,int $uid,string $month,?string $cutoff=null): array
{
    return accounting_read($pdo,function()use($pdo,$uid,$month,$cutoff){
        reporting_actor($pdo,$uid);$month=reporting_month($month);$schema=budget_revision_schema($pdo);if($schema['state']==='partial')throw new ReportingProblem('Budget approval records need a deployment integrity review.',503);
        $reports=financial_report_model($pdo,$uid,$month,$cutoff);if(!$reports['source_valid'])throw new ReportingProblem('Recorded accounting source needs an integrity review before budget actuals can be shown.',503);$root=$schema['complete']?budget_root($pdo,$month):null;$approved=$root&&$root['approved_revision_id']?budget_published($pdo,(int)$root['approved_revision_id']):null;$rows=[];$types=[];foreach($reports['worksheet'] as $a)$types[$a['account_id']]=$a['account_type'];
        foreach($approved['payload']['lines']??[] as $line){$key=$line['account_id'].':'.$line['bucket_key'];$rows[$key]=$line+['approved_amount'=>$line['amount'],'actual'=>'0.00','actual_project_label'=>null];}
        foreach($reports['project_activity'] as $a){if(($types[$a['account_id']]??null)!=='Expense')continue;$bucket=$a['project_id']===null?'operations':'project:'.$a['project_id'];$key=$a['account_id'].':'.$bucket;
            if(!isset($rows[$key]))$rows[$key]=['account_id'=>$a['account_id'],'project_id'=>$a['project_id']===null?null:(int)$a['project_id'],'bucket_key'=>$bucket,'account_label'=>$a['account_name'],'project_label'=>$a['project_name'],'project_code'=>$a['project_code'],'approved_amount'=>null,'actual'=>'0.00'];$rows[$key]['actual']=$a['recognized_expense'];$rows[$key]['actual_project_label']=$a['project_name'];
        }
        $budget=$actual=0;foreach($rows as &$row){$spent=accounting_cents($row['actual']);$actual=accounting_add($actual,$spent);$allocation=$row['approved_amount']===null?null:accounting_cents($row['approved_amount']);if($allocation!==null)$budget=accounting_add($budget,$allocation);$row['remaining']=$allocation===null?null:accounting_decimal(accounting_add($allocation,-$spent));$row['utilization']=$allocation!==null&&$allocation>0?$spent/$allocation*100:null;$row['overrun']=$allocation!==null&&$spent>$allocation;$row['unallocated_actual']=$allocation===null&&$spent!==0;}unset($row);$rows=array_values($rows);usort($rows,fn($a,$b)=>strcmp($a['project_label'],$b['project_label'])?:strcmp($a['account_label'],$b['account_label'])?:($a['account_id']<=>$b['account_id']));
        if($approved&&accounting_decimal($budget)!==$approved['payload']['total'])throw new ReportingProblem('The approved budget total needs an integrity review.',503);
        return ['month'=>$month,'from'=>$reports['from'],'as_of'=>$reports['as_of'],'approved'=>$approved,'approval_state'=>$approved?'approved':($schema['complete']?'no_approved_budget':'unavailable'),'approved_total'=>$approved?accounting_decimal($budget):null,'actual_total'=>accounting_decimal($actual),'remaining_total'=>$approved?accounting_decimal(accounting_add($budget,-$actual)):null,'utilization'=>$approved&&$budget>0?$actual/$budget*100:null,'rows'=>$rows,'reporting_issues'=>$reports['issues'],'complete'=>$reports['core_complete'],'fingerprint'=>hash('sha256',reporting_json([$reports['fingerprint'],$approved?[$approved['id'],$approved['payload_hash']]:null,$rows]))];
    });
}
function budget_legacy_reference(PDO $pdo,int $uid,string $month): array
{
    reporting_actor($pdo,$uid);$month=reporting_month($month);$s=$pdo->prepare('SELECT Category,Amount FROM Budgets WHERE Year=? AND Month=? ORDER BY Category');$s->execute([(int)substr($month,0,4),(int)substr($month,5,2)]);$out=[];foreach($s as $r)$out[]=['category'=>$r['Category'],'amount'=>accounting_decimal(accounting_cents($r['Amount']))];return ['label'=>'Recorded budgets - approval unverified','rows'=>$out,'promoted_to_approved'=>false];
}
