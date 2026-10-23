<?php
/** Version 3: explicitly classified recognized history and approved budgets; advisory only. */
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/budget_query.php';
const FORECAST_BASIS='recognized_expenses_v2';
const FORECAST_VERSION=3;
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
    $today=accounting_date($today??accounting_today());$uid=(int)($_SESSION['UserID']??0);
    return accounting_read($pdo,function()use($pdo,$today,$uid){
        reporting_actor($pdo,$uid);$months=accounting_months(12,$today);$projection=forecast_projection_months(substr($today,0,7),6);$series=[];$categoryTotals=[];$categoryMonthly=[];$sources=[];$current=null;
        foreach(array_merge($months,[substr($today,0,7)]) as $month){
            $model=financial_report_model($pdo,$uid,$month,$month===substr($today,0,7)?$today:null);if(!$model['source_valid'])throw new ReportingProblem('Recorded history needs an integrity review before forecasting.',503);
            $hasExpenseActivity=false;foreach($model['worksheet'] as $row)if($row['account_type']==='Expense'&&(accounting_cents($row['period_debit'])!==0||accounting_cents($row['period_credit'])!==0))$hasExpenseActivity=true;
            if($hasExpenseActivity&&$model['configuration']===null)throw new ReportingProblem('Complete reporting setup for expense history in '.$month.' before forecasting.',422);
            $sources[$month]=$model['fingerprint'];$point=['month'=>$month,'income'=>(float)$model['income_statement']['income'],'expenses'=>(float)$model['income_statement']['expenses'],'income_decimal'=>$model['income_statement']['income'],'expenses_decimal'=>$model['income_statement']['expenses']];
            if($month===substr($today,0,7)){$current=$point;continue;}$series[]=$point;
            foreach($model['income_statement']['rows'] as $row)if($row['account_type']==='Expense'){$id=$row['account_id'];$categoryTotals[$id]??=['category'=>$row['account_name'],'cents'=>0];$cents=accounting_cents($row['amount']);$categoryTotals[$id]['cents']=accounting_add($categoryTotals[$id]['cents'],$cents);$categoryMonthly[$id][$month]=$cents;}
        }
        $recent=$totalExpenses=$totalIncome=0;$positive=0;foreach($series as $p){$totalExpenses=accounting_add($totalExpenses,accounting_cents($p['expenses_decimal']));$totalIncome=accounting_add($totalIncome,accounting_cents($p['income_decimal']));if($p['expenses']>0)$positive++;}foreach(array_slice($series,-3) as $p)$recent=accounting_add($recent,accounting_cents($p['expenses_decimal']));
        $validShares=$totalExpenses>0&&!array_filter($categoryTotals,static fn($c)=>$c['cents']<0);$categories=[];
        foreach($categoryTotals as $id=>$c){$r=0;foreach(array_slice($months,-3) as $month)$r=accounting_add($r,$categoryMonthly[$id][$month]??0);$total=accounting_decimal($c['cents']);$average=round((float)$total/12,2);$recentAverage=round($r/300,2);$categories[]=['account_id'=>$id,'category'=>$c['category'],'total_decimal'=>$total,'total'=>(float)$total,'monthly_avg'=>$average,'recent_avg'=>$recentAverage,'trend'=>forecast_trend($recentAverage,$average),'share_pct'=>$validShares?round($c['cents']/$totalExpenses*100,2):null];}
        $budgets=[];$budgetSources=[];$budgetSchema=budget_revision_schema($pdo);if($budgetSchema['state']==='partial')throw new ReportingProblem('Budget approval records need an integrity review.',503);foreach($projection as $month){$root=$budgetSchema['complete']?budget_root($pdo,$month):null;$approved=$root&&$root['approved_revision_id']?budget_published($pdo,(int)$root['approved_revision_id']):null;$budgets[$month]=$approved?budget_approved_category_rows($approved):[];$budgetSources[$month]=$approved?['id'=>$approved['id'],'hash'=>$approved['payload_hash'],'state'=>'Approved']:['state'=>'No approved budget'];}
        return ['version'=>FORECAST_VERSION,'basis'=>FORECAST_BASIS,'as_of'=>$today,'months'=>$months,'projection_months'=>$projection,'series'=>$series,'current_month'=>$current,'categories'=>$categories,'budgets'=>$budgets,'source_fingerprints'=>$sources,'approved_budget_sources'=>$budgetSources,
            'metrics'=>['total_expenses'=>(float)accounting_decimal($totalExpenses),'total_income'=>(float)accounting_decimal($totalIncome),'recent_avg_expenses'=>round($recent/300,2),'positive_expense_months'=>$positive,'runway_months'=>null,'donor_concentration'=>null,'solvency_rating'=>null]];
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
    foreach(['version','basis','as_of','months','projection_months','series','current_month','categories','budgets','source_fingerprints','approved_budget_sources'] as $key){
        if(!array_key_exists($key,$history)){throw new UnexpectedValueException('Incomplete forecast history.');}
    }
    // CHAR(40) cache identity; this is a content fingerprint, not a security token.
    return sha1(json_encode([$history['version'],$history['basis'],$history['as_of'],$history['months'],$history['projection_months'],$history['series'],$history['current_month'],$history['categories'],$history['budgets'],$history['source_fingerprints'],$history['approved_budget_sources']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
}
function forecast_history_for_ai(array $history): array
{
    return ['currency'=>'PHP','basis'=>$history['basis'],'as_of'=>$history['as_of'],'monthly_history'=>$history['series'],
        'current_month_to_date'=>$history['current_month'],'category_expenses'=>$history['categories'],'approved_budgets'=>$history['budgets'],'budget_approval_states'=>$history['approved_budget_sources'],
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
    if(!is_array($h)||!is_array($f)||($h['basis']??null)!==FORECAST_BASIS||($f['basis']??null)!==FORECAST_BASIS||($h['version']??null)!==FORECAST_VERSION||($f['version']??null)!==FORECAST_VERSION){return null;}
    try{if(!hash_equals(forecast_fingerprint($history),forecast_fingerprint($h))){return null;}}
    catch(Throwable $e){return null;}
    $peak=0;foreach($history['series'] as $p){$peak=max($peak,abs($p['expenses']));}
    if(!forecast_validate_projection($f,$history['projection_months'],$peak,$history['metrics']['recent_avg_expenses'])['valid']){return null;}
    return ['forecast'=>$f,'created_at'=>$r['created_at']];
}
