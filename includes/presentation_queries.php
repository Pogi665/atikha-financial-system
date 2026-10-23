<?php
/** Read-only, scoped dashboard projections; canonical accounting checks remain authoritative. */
require_once __DIR__.'/dashboard_reporting.php';
require_once __DIR__.'/accounting_workspace.php';
function ui_dashboard_model(PDO $pdo,int $uid,string $month):array
{
    return accounting_read($pdo,function()use($pdo,$uid,$month){
        $actor=reporting_actor($pdo,$uid);$month=reporting_month($month);$today=accounting_today();
        [$from,$end]=reporting_month_range($month);$asof=min($end,$today);
        if($month>substr($today,0,7))throw new ReportingProblem('Choose this month or an earlier month.',400);
        $out=['month'=>$month,'from'=>$from,'as_of'=>$asof,'role'=>$actor['Role'],'cash'=>null,'outstanding'=>null,'received'=>null,'payments'=>null,'income'=>null,'expenses'=>null,'net'=>null,'drafts'=>null,'pending'=>null,'budget'=>null,'attention'=>[],'series'=>[],'errors'=>[]];
        try{
            if(accounting_integrity($pdo,$asof))throw new ReportingProblem('Posted balances need an integrity review.',503);
            $closing=accounting_kpis($pdo,$asof);$opening=accounting_kpis($pdo,(new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'));$out['income']=accounting_decimal(accounting_add(accounting_cents($closing['income']),-accounting_cents($opening['income'])));$out['expenses']=accounting_decimal(accounting_add(accounting_cents($closing['expenses']),-accounting_cents($opening['expenses'])));$out['net']=accounting_decimal(accounting_add(accounting_cents($out['income']),-accounting_cents($out['expenses'])));
            $months=[];$cursor=new DateTimeImmutable($month.'-01');for($n=5;$n>=0;$n--)$months[]=$cursor->modify('-'.$n.' months')->format('Y-m');$out['series']=accounting_monthly($pdo,$months);$last=count($out['series'])-1;$out['series'][$last]['income_decimal']=$out['income'];$out['series'][$last]['expenses_decimal']=$out['expenses'];
            if(stage1_schema($pdo)){
                $s=$pdo->prepare("SELECT j.source_book,SUM(CASE WHEN j.source_book='CRB' THEN l.debit_amount ELSE l.credit_amount END) amount FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id JOIN Categories c ON c.CategoryID=l.account_id WHERE j.status='posted' AND j.source_book IN ('CRB','CDB') AND c.Account_Type='Asset' AND c.Is_Cash_Account=1 AND j.entry_date>=? AND j.entry_date<? GROUP BY j.source_book");$s->execute([$from,accounting_next_day($asof)]);$out['received']=$out['payments']='0.00';foreach($s as $r)$out[$r['source_book']==='CRB'?'received':'payments']=accounting_decimal(accounting_cents($r['amount']));
            }
        }catch(Throwable $e){$out['errors'][]='Financial activity is unavailable. Try again or ask the Admin to check the posted records.';}
        try{$signals=dashboard_reporting_status($pdo,$uid,$asof);$out['cash']=$signals['cash'];$out['outstanding']=$signals['outstanding_advances'];$out['pending']=$signals['pending_reviews'];if($out['cash']===null)$out['attention'][]=['Reporting setup needs attention','Configure cash accounts before relying on a cash balance.',$actor['Role']==='Admin'?'report_setup.php':'report_bundles.php?component=income_statement','Setup needed'];}
        catch(Throwable $e){$out['errors'][]='Cash, advance or review information is unavailable. Refresh before relying on it.';}
        try{$out['budget']=budget_actual_model($pdo,$uid,$month,$asof);}catch(Throwable $e){$out['errors'][]='Approved budget information is unavailable. Try again.';}
        if($actor['Role']==='Admin'&&stage1_enabled($pdo)){
            try{$drafts=workspace_drafts($pdo,$uid,[]);$out['drafts']=count($drafts);if($drafts)$out['attention'][]=[count($drafts).' Drafts to Finish','Continue your unfinished private transactions.','accounting_drafts.php','Draft'];}catch(Throwable $e){$out['errors'][]='Your private drafts could not be loaded.';}
        }
        if(($out['pending']??0)>0)$out['attention'][]=[$out['pending'].' Financial Reviews Pending','Open the exact submitted item before making a decision.',$actor['Role']==='Management'?'management_reviews.php':'report_bundles.php?component=income_statement&month='.$month,'Awaiting Review'];
        if($actor['Role']==='Admin'){
            try{
                if(budget_revision_schema($pdo)['complete']){$q=$pdo->prepare("SELECT v.id,r.budget_month FROM budget_revisions v JOIN budget_revision_roots r ON r.id=v.root_id JOIN budget_review_events e ON e.revision_id=v.id AND e.decision='Returned' WHERE v.prepared_by=? AND NOT EXISTS(SELECT 1 FROM budget_revisions newer WHERE newer.root_id=v.root_id AND newer.revision_number>v.revision_number) ORDER BY v.id DESC LIMIT 5");$q->execute([$uid]);foreach($q as $r)$out['attention'][]=['Budget Returned / '.substr($r['budget_month'],0,7),'Read the reason before preparing a new revision.','budgets.php?revision_id='.(int)$r['id'],'Returned'];}
                if(periods_schema($pdo)['complete']){$q=$pdo->prepare("SELECT b.id,r.report_month FROM complete_report_bundles b JOIN complete_report_roots r ON r.id=b.root_id JOIN complete_report_reviews v ON v.bundle_id=b.id AND v.decision='Returned' WHERE b.prepared_by=? AND NOT EXISTS(SELECT 1 FROM complete_report_bundles newer WHERE newer.root_id=b.root_id AND newer.revision_number>b.revision_number) ORDER BY b.id DESC LIMIT 5");$q->execute([$uid]);foreach($q as $r)$out['attention'][]=['Reports Returned / '.substr($r['report_month'],0,7),'Read the reason and check Working Figures.','report_bundles.php?component=income_statement&bundle_id='.(int)$r['id'],'Returned'];}
            }catch(Throwable $e){$out['errors'][]='Returned submissions could not be checked. Open Reports or Budgets to verify their status.';}
        }
        try{if(stage2_schema($pdo)&&stage3_schema($pdo)){$state=advance_state($pdo,$today);$overdue=0;foreach($state['advances'] as $a){$summary=advance_summary($a);if($summary['overdue'])$overdue++;}if($overdue)$out['attention'][]=[$overdue.' Overdue Cash Advances','Review outstanding advances and their current deadlines.','cash_advances.php?overdue=1','Overdue'];}}catch(Throwable $e){$out['errors'][]='Current advance deadlines could not be checked.';}
        return $out;
    });
}
