<?php
require_once __DIR__.'/budget_actuals.php';
function budget_approved_category_rows(array $approved): array
{
    $rows=[];foreach($approved['payload']['lines'] as $line){$id=$line['account_id'];$rows[$id]??=['account_id'=>$id,'category'=>$line['account_label'],'amount'=>'0.00'];$rows[$id]['amount']=accounting_decimal(accounting_add(accounting_cents($rows[$id]['amount']),accounting_cents($line['amount'])));}return array_values($rows);
}
function budget_fetch_month(PDO $pdo,int $year,int $month): array
{
    return accounting_read($pdo,function()use($pdo,$year,$month){reporting_actor($pdo,(int)($_SESSION['UserID']??0));accounting_month_end($year,$month);$schema=budget_revision_schema($pdo);if($schema['state']==='absent')return [];if(!$schema['complete'])throw new ReportingProblem('Budget approval records need an integrity review.',503);$root=budget_root($pdo,sprintf('%04d-%02d',$year,$month));if(!$root||!$root['approved_revision_id'])return [];$v=budget_published($pdo,(int)$root['approved_revision_id']);return budget_approved_category_rows($v);});
}
function budget_total_for_month(PDO $pdo,int $year,int $month): ?string
{
    reporting_actor($pdo,(int)($_SESSION['UserID']??0));accounting_month_end($year,$month);$schema=budget_revision_schema($pdo);if($schema['state']==='absent')return null;if(!$schema['complete'])throw new ReportingProblem('Budget approval records need an integrity review.',503);$root=budget_root($pdo,sprintf('%04d-%02d',$year,$month));return $root&&$root['approved_revision_id']?budget_published($pdo,(int)$root['approved_revision_id'])['payload']['total']:null;
}
function budget_mtd_spent_by_category(PDO $pdo,int $year,int $month): array
{
    $start=sprintf('%04d-%02d-01',$year,$month); $end=min(accounting_month_end($year,$month),accounting_today());
    if($end<$start){return [];}
    $model=budget_actual_model($pdo,(int)($_SESSION['UserID']??0),sprintf('%04d-%02d',$year,$month),$end);$out=[];foreach($model['rows'] as $r)$out[$r['account_label']]=accounting_decimal(accounting_add(accounting_cents($out[$r['account_label']]??'0.00'),accounting_cents($r['actual'])));return $out;
}
function budget_mtd_spent(PDO $pdo,int $year,int $month): string
{
    $total=0;foreach(budget_mtd_spent_by_category($pdo,$year,$month) as $amount){$total=accounting_add($total,accounting_cents($amount));}return accounting_decimal($total);
}
function budget_utilization(PDO $pdo,int $year,int $month): array
{
    return accounting_read($pdo,function()use($pdo,$year,$month){
        $monthKey=sprintf('%04d-%02d',$year,$month);$start=$monthKey.'-01';$end=min(accounting_month_end($year,$month),accounting_today());$model=budget_actual_model($pdo,(int)($_SESSION['UserID']??0),$monthKey,$end<$start?$start:$end);$rows=[];
        foreach($model['rows'] as $r){$id=$r['account_id'];$rows[$id]??=['category'=>$r['account_label'],'budgeted'=>'0.00','spent'=>'0.00','has_allocation'=>false];$rows[$id]['spent']=accounting_decimal(accounting_add(accounting_cents($rows[$id]['spent']),accounting_cents($r['actual'])));if($r['approved_amount']!==null){$rows[$id]['has_allocation']=true;$rows[$id]['budgeted']=accounting_decimal(accounting_add(accounting_cents($rows[$id]['budgeted']),accounting_cents($r['approved_amount'])));}}
        foreach($rows as &$r){$b=accounting_cents($r['budgeted']);$s=accounting_cents($r['spent']);$r['remaining']=$r['has_allocation']?accounting_decimal(accounting_add($b,-$s)):null;$r['pct']=$r['has_allocation']&&$b>0?$s/$b*100:null;}unset($r);
        return ['budgeted'=>$model['approved_total'],'spent'=>$model['actual_total'],'pct'=>$model['utilization'],'by_category'=>array_values($rows),'approval_state'=>$model['approval_state'],'approved_revision'=>$model['approved']['id']??null,'as_of'=>$model['as_of'],'complete'=>$model['complete'],'fingerprint'=>$model['fingerprint']];
    });
}
function budget_overrun_categories(PDO $pdo,int $year,int $month): array
{
    $out=[];foreach(budget_utilization($pdo,$year,$month)['by_category'] as $r){if(accounting_cents($r['budgeted'])>0&&accounting_cents($r['spent'])>accounting_cents($r['budgeted'])){$r['over_by']=accounting_decimal(accounting_add(accounting_cents($r['spent']),-accounting_cents($r['budgeted'])));$out[]=$r;}}return $out;
}
function budget_upcoming_totals(PDO $pdo,int $monthsAhead=3): array
{
    $cursor=new DateTimeImmutable(substr(accounting_today(),0,7).'-01');$out=[];
    for($i=0;$i<$monthsAhead;$i++){$y=(int)$cursor->format('Y');$m=(int)$cursor->format('n');$amount=budget_total_for_month($pdo,$y,$m);$out[]=['year'=>$y,'month'=>$m,'total'=>$amount===null?null:(float)$amount,'total_decimal'=>$amount,'approval_state'=>$amount===null?'No approved budget':'Approved'];$cursor=$cursor->modify('+1 month');}return $out;
}
