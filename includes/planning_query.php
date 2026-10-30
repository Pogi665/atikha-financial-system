<?php
require_once __DIR__.'/budget_actuals.php';
require_once __DIR__.'/planning_methods.php';
/** Minimum expense projection after the existing full reader validates the entire
 * source through the cutoff. Reuses statement_kind/statement_line_net verbatim.
 * It does not omit corrected originals or infer reporting designations. */
function planning_history(PDO $pdo,int $uid,string $first,string $cutoff):array
{
    $cursor=(new DateTimeImmutable($cutoff))->modify('-12 months');$from=max($first,$cursor->format('Y-m'));
    if($from>=$cutoff)return [[],[],[],[],[]];
    $last=(new DateTimeImmutable($cutoff))->modify('-1 month')->format('Y-m');
    $verified=financial_report_model($pdo,$uid,$last,reporting_month_range($last)[1]);
    if(!$verified['source_valid'])throw new ReportingProblem('Recorded accounting source needs an integrity review before planning can be shown.',503);
    $types=array_column($verified['worksheet'],'account_type','account_id');
    $q=$pdo->prepare("SELECT j.id journal_id,j.entry_date,l.id line_id,l.account_id,c.Name account_name,l.debit_amount,l.credit_amount,l.fund_project_id project_id,l.project_name_snapshot project_name FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id JOIN Categories c ON c.CategoryID=l.account_id WHERE j.status='posted' AND j.entry_date>=? AND j.entry_date<? AND c.Account_Type='Expense' ORDER BY j.entry_date,j.id,l.id");$q->execute([$from.'-01',$cutoff]);$lines=[];foreach($q as $row)$lines[substr($row['entry_date'],0,7)][]=$row;
    $q=$pdo->prepare('SELECT reversal_journal_id,target_journal_id FROM journal_corrections WHERE accounting_date<? ORDER BY id');$q->execute([$cutoff]);$reversals=[];foreach($q as $r)$reversals[(int)$r['reversal_journal_id']]=(int)$r['target_journal_id'];
    $months=[];$series=[];$buckets=[];$sources=[];$warnings=[];$checked=[];
    while($cursor->format('Y-m-d')<$cutoff){$month=$cursor->format('Y-m');$cursor=$cursor->modify('+1 month');if($month<$first)continue;$config=report_configuration_current($pdo,$month.'-01');
        if(!$config){$months=[];$series=[];$buckets=[];$sources=[];$warnings[]='Some earlier history has no reporting setup. The most recent configured period is shown.';continue;}
        if(!isset($checked[$config['id']])){report_configuration_validate($pdo,$config['payload']);$checked[$config['id']]=true;}
        $designations=array_column($config['payload']['designations'],null,'journal_id');$total=0;$hash=hash_init('sha256');hash_update($hash,reporting_json([$verified['fingerprint'],$config['payload_hash'],$month]));
        foreach($lines[$month]??[] as $row){hash_update($hash,reporting_json($row));if(($types[$row['account_id']]??null)!=='Expense')throw new ReportingProblem('Expense history needs an account integrity review.',503);$amount=statement_kind((int)$row['journal_id'],$designations,$reversals)==='ordinary'?statement_line_net($row):0;$total=accounting_add($total,$amount);$project=$row['project_id']===null?null:(int)$row['project_id'];$key=$row['account_id'].':'.($project??'operations');$buckets[$key]??=['account_id'=>(int)$row['account_id'],'project_id'=>$project,'account'=>$row['account_name'],'project'=>$row['project_name']??'Organization operations','history'=>[]];$buckets[$key]['history'][$month]=accounting_add($buckets[$key]['history'][$month]??0,$amount);}
        $months[]=$month;$series[]=$total;$sources[$month]=hash_final($hash);
    }
    return [$months,$series,$buckets,$sources,$warnings];
}
function planning_model(PDO $pdo,int $uid,string $month,?DateTimeImmutable $instant=null): array
{
    // An explicit clock is for deterministic service tests, never a request parameter.
    $month=reporting_month($month);$today=accounting_today($instant);if($month>substr((new DateTimeImmutable($today))->modify('first day of next month')->format('Y-m-d'),0,7))throw new ReportingProblem('Choose this month or the next month.',400);
    return accounting_read($pdo,function()use($pdo,$uid,$month,$today){
        $actor=reporting_actor($pdo,$uid);$cutoff=min($month.'-01',substr($today,0,7).'-01');$earliest=$pdo->query("SELECT MIN(entry_date) FROM journal_entries WHERE status='posted'")->fetchColumn();
        $start=$earliest?substr($earliest,0,7):null;$months=[];$series=[];$buckets=[];$sources=[];$warnings=[];
        // Exclude the ambiguous first recording month. Never fabricate preceding zeroes.
        if($start){$first=(new DateTimeImmutable($start.'-01'))->modify('+1 month')->format('Y-m');[$months,$series,$buckets,$sources,$warnings]=planning_history($pdo,$uid,$first,$cutoff);}
        $overall=planning_estimate($series);$detail=[];$covered=0;$masters=accounting_accounts($pdo);$active=[];foreach($masters as $a)$active[(int)$a['CategoryID']]=$a['Account_Type']==='Expense'&&(int)$a['Is_Active']===1;
        $projects=$pdo->query('SELECT id,code,name FROM projects WHERE is_active=1 ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);$activeProjects=array_fill_keys(array_map('intval',array_column($projects,'id')),true);
        $budget=budget_actual_model($pdo,$uid,$month);$approved=[];foreach($budget['rows'] as $row)$approved[$row['account_id'].':'.($row['project_id']??'operations')]=$row['approved_amount'];
        foreach($buckets as $key=>$bucket){$values=[];$begun=false;foreach($months as $m){if(array_key_exists($m,$bucket['history']))$begun=true;if($begun)$values[]=$bucket['history'][$m]??0;}$estimate=planning_estimate($values);$eligible=($active[$bucket['account_id']]??false)&&($bucket['project_id']===null||isset($activeProjects[$bucket['project_id']]))&&$estimate['state']==='available'&&$estimate['amount']>0;if($estimate['state']==='available')$covered++;$detail[]=['key'=>$key,'account_id'=>$bucket['account_id'],'project_id'=>$bucket['project_id'],'account'=>$bucket['account'],'project'=>$bucket['project'],'estimate'=>$estimate,'approved'=>$approved[$key]??null,'eligible'=>$eligible];}
        foreach($projects as $project){$found=false;foreach($detail as $d)if($d['project_id']===(int)$project['id']){$found=true;break;}if(!$found)$detail[]=['key'=>'project:'.$project['id'],'account_id'=>null,'project_id'=>(int)$project['id'],'account'=>'No recorded expense history','project'=>$project['name'],'estimate'=>planning_estimate([]),'approved'=>null,'eligible'=>false];}
        $basis=count($months)?[$months[0],$months[count($months)-1]]:[];$fp=hash('sha256',reporting_json([$month,$sources,$budget['fingerprint'],$overall,$detail]));
        return ['month'=>$month,'role'=>$actor['Role'],'overall'=>$overall,'history'=>array_map(static fn($m,$v)=>['month'=>$m,'amount'=>accounting_decimal($v)],$months,$series),'basis'=>$basis,'complete_months'=>count($months),'details'=>$detail,'coverage'=>$covered<count($detail)?'Partial detailed coverage':'Detailed coverage available','approved_total'=>$budget['approved_total'],'approved_revision_id'=>$budget['approved']['id']??null,'approved_lines'=>$budget['approved']['payload']['lines']??[],'fingerprint'=>$fp,'warnings'=>array_values(array_unique($warnings)),'version'=>'deterministic_v1','sources'=>$sources];
    });
}
