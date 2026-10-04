<?php
/** Version 2: recognized income and signed net expenses; not cash flow. */
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/budget_query.php';
const FORECAST_BASIS='net_expenses_v1';
const FORECAST_HISTORY_MONTHS=12;
const FORECAST_HORIZON_MONTHS=6;
const FORECAST_TTL_HOURS=24;
const FORECAST_MIN_ACTIVE_MONTHS=2;
function forecast_month_window(int $count): array { return accounting_months($count); }
function forecast_projection_months(string $fromMonth,int $count): array
{
    accounting_date($fromMonth.'-01');$cursor=new DateTimeImmutable($fromMonth.'-01');$out=[];
    for($i=0;$i<$count;$i++){$out[]=$cursor->format('Y-m');$cursor=$cursor->modify('+1 month');}return $out;
}
function forecast_fetch_history(PDO $pdo,?string $today=null): array
{
    $today=accounting_date($today??accounting_today());
    return accounting_read($pdo,function()use($pdo,$today){
        $months=accounting_months(12,$today);$series=accounting_monthly($pdo,$months);$start=$months[0].'-01';$end=substr($today,0,7).'-01';
        $projection=forecast_projection_months(substr($today,0,7),6);$categories=accounting_expenses($pdo,$start,$end);
        $s=$pdo->prepare("SELECT DATE_FORMAT(j.entry_date,'%Y-%m') AS month,c.CategoryID,SUM(l.debit_amount-l.credit_amount) AS total
            FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id JOIN Categories c ON c.CategoryID=l.account_id
            WHERE j.status='posted' AND c.Account_Type='Expense' AND j.entry_date>=:start AND j.entry_date<:end GROUP BY month,c.CategoryID");
        $s->execute(['start'=>$start,'end'=>$end]);$catMonthly=[];
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$catMonthly[$r['CategoryID']][$r['month']]=accounting_cents($r['total']);}
        $recent=0;$totalExpenses=$totalIncome=0;$positive=0;
        foreach($series as $p){$totalExpenses=accounting_add($totalExpenses,accounting_cents($p['expenses_decimal']));$totalIncome=accounting_add($totalIncome,accounting_cents($p['income_decimal']));if($p['expenses']>0){$positive++;}}
        foreach(array_slice($series,-3) as $p){$recent=accounting_add($recent,accounting_cents($p['expenses_decimal']));}
        $validShares=$totalExpenses>0&&!array_filter($categories,static fn($r)=>accounting_cents($r['total'])<0);
        foreach($categories as &$c){$r=0;foreach(array_slice($months,-3) as $m){$r=accounting_add($r,$catMonthly[$c['CategoryID']][$m]??0);}
            $c['total_decimal']=$c['total'];$c['total']=(float)$c['total'];$c['monthly_avg']=round($c['total']/12,2);$c['recent_avg']=round($r/300,2);
            $c['trend']=forecast_trend($c['recent_avg'],$c['monthly_avg']);$c['share_pct']=$validShares?round(accounting_cents($c['total_decimal'])/$totalExpenses*100,2):null;
        }unset($c);
        $current=['month'=>substr($today,0,7),'income'=>0.0,'expenses'=>0.0,'income_decimal'=>'0.00','expenses_decimal'=>'0.00'];
        $s=$pdo->prepare("SELECT c.Account_Type,SUM(l.debit_amount-l.credit_amount) AS net FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id JOIN Categories c ON c.CategoryID=l.account_id
            WHERE j.status='posted' AND j.entry_date>=:start AND j.entry_date<:end AND c.Account_Type IN ('Income','Expense') GROUP BY c.Account_Type");
        $s->execute(['start'=>$end,'end'=>accounting_next_day($today)]);
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$k=$r['Account_Type']==='Income'?'income':'expenses';$v=accounting_cents($r['net']);if($k==='income'){$v=-$v;}$current[$k.'_decimal']=accounting_decimal($v);$current[$k]=(float)$current[$k.'_decimal'];}
        $budgets=[];foreach($projection as $month){$budgets[$month]=budget_fetch_month($pdo,(int)substr($month,0,4),(int)substr($month,5,2));}
        return ['version'=>2,'basis'=>FORECAST_BASIS,'as_of'=>$today,'months'=>$months,'projection_months'=>$projection,'series'=>$series,'current_month'=>$current,'categories'=>$categories,'budgets'=>$budgets,
            'metrics'=>['total_expenses'=>(float)accounting_decimal($totalExpenses),'total_income'=>(float)accounting_decimal($totalIncome),
            'recent_avg_expenses'=>round($recent/300,2),'positive_expense_months'=>$positive,'runway_months'=>null,'donor_concentration'=>null,'solvency_rating'=>null]];
    });
}
function forecast_trend(float $recent,float $average): string
{
    $change=$recent-$average;$threshold=max(.01,abs($average)*.1);return $change>$threshold?'rising':($change<-$threshold?'falling':'steady');
}
function forecast_history_is_sufficient(array $history): bool
{
    return count(array_filter($history['series'],static fn($p)=>$p['expenses']>0))>=FORECAST_MIN_ACTIVE_MONTHS;
}
function forecast_baseline_projection(array $history): array
{
    return array_map(static fn($m)=>['month'=>$m,'projected_expenses'=>round((float)$history['metrics']['recent_avg_expenses'],2)],$history['projection_months']);
}
function forecast_fingerprint(array $history): string
{
    foreach(['version','basis','as_of','months','projection_months','series','current_month','categories','budgets'] as $key){
        if(!array_key_exists($key,$history)){throw new UnexpectedValueException('Incomplete forecast history.');}
    }
    // CHAR(40) cache identity; this is a content fingerprint, not a security token.
    return sha1(json_encode([$history['version'],$history['basis'],$history['as_of'],$history['months'],$history['projection_months'],$history['series'],$history['current_month'],$history['categories'],$history['budgets']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
}
function forecast_history_for_ai(array $history): array
{
    return ['currency'=>'PHP','basis'=>$history['basis'],'as_of'=>$history['as_of'],'monthly_history'=>$history['series'],
        'current_month_to_date'=>$history['current_month'],'category_expenses'=>$history['categories'],'recorded_budgets'=>$history['budgets'],
        'metrics'=>$history['metrics'],'projection_months'=>$history['projection_months'],'baseline_projection'=>forecast_baseline_projection($history)];
}
function forecast_validate_projection(array $decoded,array $months,float $peak,float $baseline): array
{
    $fallback=array_map(static fn($m)=>['month'=>$m,'projected_expenses'=>round($baseline,2)],$months);
    $items=$decoded['chart_data']??null;$valid=is_array($items)&&count($items)===count($months);$out=[];
    $bound=max(1.0,abs($peak)*20,abs($baseline)*20);
    if($valid){foreach($items as $i=>$r){if(!is_array($r)||($r['month']??null)!==$months[$i]||!isset($r['projected_expenses'])||!is_numeric($r['projected_expenses'])||!is_finite((float)$r['projected_expenses'])||abs((float)$r['projected_expenses'])>$bound){$valid=false;break;}$out[]=['month'=>$months[$i],'projected_expenses'=>round((float)$r['projected_expenses'],2)];}}
    return ['valid'=>$valid,'projection'=>$valid?$out:$fallback];
}
function forecast_cache_load(PDO $pdo,array $history): ?array
{
    $s=$pdo->prepare("SELECT History_JSON,Forecast_JSON,created_at FROM forecast_cache WHERE Data_Fingerprint=? AND Model<>'generation_attempt' AND created_at>=NOW()-INTERVAL 24 HOUR ORDER BY ForecastID DESC LIMIT 1");
    $s->execute([forecast_fingerprint($history)]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r){return null;}
    $h=json_decode($r['History_JSON'],true);$f=json_decode($r['Forecast_JSON'],true);
    if(!is_array($h)||!is_array($f)||($h['basis']??null)!==FORECAST_BASIS||($f['basis']??null)!==FORECAST_BASIS||($h['version']??null)!==2||($f['version']??null)!==2){return null;}
    try{if(!hash_equals(forecast_fingerprint($history),forecast_fingerprint($h))){return null;}}
    catch(Throwable $e){return null;}
    $peak=0;foreach($history['series'] as $p){$peak=max($peak,abs($p['expenses']));}
    if(!forecast_validate_projection($f,$history['projection_months'],$peak,$history['metrics']['recent_avg_expenses'])['valid']){return null;}
    return ['forecast'=>$f,'created_at'=>$r['created_at']];
}
