<?php
require_once __DIR__.'/accounting_query.php';
const BUDGET_DEFAULT_PLACEHOLDER=10000.00;
function budget_fetch_month(PDO $pdo,int $year,int $month): array
{
    accounting_month_end($year,$month);
    $s=$pdo->prepare('SELECT Category,Amount FROM Budgets WHERE Year=? AND Month=? ORDER BY Category'); $s->execute([$year,$month]);
    return array_map(static fn($r)=>['category'=>$r['Category'],'amount'=>accounting_decimal(accounting_cents($r['Amount']))],$s->fetchAll(PDO::FETCH_ASSOC));
}
function budget_total_for_month(PDO $pdo,int $year,int $month): string
{
    $total=0; foreach(budget_fetch_month($pdo,$year,$month) as $r){$total=accounting_add($total,accounting_cents($r['amount']));} return accounting_decimal($total);
}
function budget_mtd_spent_by_category(PDO $pdo,int $year,int $month): array
{
    $start=sprintf('%04d-%02d-01',$year,$month); $end=min(accounting_month_end($year,$month),accounting_today());
    if($end<$start){return [];}
    $out=[]; foreach(accounting_expenses($pdo,$start,accounting_next_day($end)) as $r){$out[$r['category']]=$r['total'];} return $out;
}
function budget_mtd_spent(PDO $pdo,int $year,int $month): string
{
    $total=0;foreach(budget_mtd_spent_by_category($pdo,$year,$month) as $amount){$total=accounting_add($total,accounting_cents($amount));}return accounting_decimal($total);
}
function budget_utilization(PDO $pdo,int $year,int $month): array
{
    return accounting_read($pdo,function()use($pdo,$year,$month){
        $spend=budget_mtd_spent_by_category($pdo,$year,$month);$rows=[];$budget=$spent=0;
        foreach(budget_fetch_month($pdo,$year,$month) as $r){$rows[$r['category']]=['category'=>$r['category'],'budgeted'=>$r['amount'],'spent'=>$spend[$r['category']]??'0.00'];}
        foreach($spend as $name=>$amount){if(!isset($rows[$name])){$rows[$name]=['category'=>$name,'budgeted'=>'0.00','spent'=>$amount];}}
        foreach($rows as &$r){$b=accounting_cents($r['budgeted']);$s=accounting_cents($r['spent']);$budget=accounting_add($budget,$b);$spent=accounting_add($spent,$s);$r['remaining']=accounting_decimal(accounting_add($b,-$s));$r['pct']=$b>0?$s/$b*100:null;}unset($r);
        $rows=array_values($rows);usort($rows,static fn($a,$b)=>accounting_cents($b['spent'])<=>accounting_cents($a['spent']));
        return ['budgeted'=>accounting_decimal($budget),'spent'=>accounting_decimal($spent),'pct'=>$budget>0?$spent/$budget*100:null,'by_category'=>$rows];
    });
}
function budget_overrun_categories(PDO $pdo,int $year,int $month): array
{
    $out=[];foreach(budget_utilization($pdo,$year,$month)['by_category'] as $r){if(accounting_cents($r['budgeted'])>0&&accounting_cents($r['spent'])>accounting_cents($r['budgeted'])){$r['over_by']=accounting_decimal(accounting_add(accounting_cents($r['spent']),-accounting_cents($r['budgeted'])));$out[]=$r;}}return $out;
}
function budget_upcoming_totals(PDO $pdo,int $monthsAhead=3): array
{
    $cursor=new DateTimeImmutable(substr(accounting_today(),0,7).'-01');$out=[];
    for($i=0;$i<$monthsAhead;$i++){$y=(int)$cursor->format('Y');$m=(int)$cursor->format('n');$amount=budget_total_for_month($pdo,$y,$m);$out[]=['year'=>$y,'month'=>$m,'total'=>(float)$amount,'total_decimal'=>$amount];$cursor=$cursor->modify('+1 month');}return $out;
}
