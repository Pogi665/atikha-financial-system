<?php
/** Explicit advisory selection -> existing private budget writer. Never submits. */
require_once __DIR__.'/planning_query.php';
function planning_budget_payload(array $selection,?array $base):array
{
    $payload=budget_revision_payload($selection);$lines=[];
    if($base){if($base['month']!==$payload['month'])throw new ReportingProblem('Use the approved revision for this month.',409);foreach($base['lines'] as $line)$lines[$line['account_id'].':'.($line['project_id']??'operations')]=array_intersect_key($line,array_flip(['account_id','project_id','amount']));}
    foreach($payload['lines'] as $line)$lines[$line['account_id'].':'.($line['project_id']??'operations')]=$line;
    $payload['lines']=array_values($lines);return budget_revision_payload($payload);
}
function planning_budget_create(PDO $pdo,int $uid,array $r):array
{
    reporting_csrf($r);reporting_actor($pdo,$uid,['Admin']);$selection=budget_revision_payload($r['payload']??[]);$key=reporting_text($r['request_key']??'',64,'draft identity',true);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$key))throw new ReportingProblem('Reload Planning before creating the draft.',400);
    $s=$pdo->prepare('SELECT copied_revision_id FROM budget_revision_drafts WHERE owner_id=? AND request_key=?');$s->execute([$uid,$key]);$old=$s->fetch(PDO::FETCH_ASSOC);
    $root=$old?null:budget_root($pdo,$selection['month']);$copy=$old?$old['copied_revision_id']:($root['approved_revision_id']??null);$copy=$copy===null?null:(int)$copy;
    $base=$copy?budget_published($pdo,$copy)['payload']:null;$payload=planning_budget_payload($selection,$base);
    $guard=function(array $lockedRoot)use($pdo,$uid,$r,$selection,$copy){
        if(($lockedRoot['approved_revision_id']===null?null:(int)$lockedRoot['approved_revision_id'])!==$copy)throw new ReportingProblem('The approved budget changed. Refresh Planning before creating its revision.',409);
        $model=planning_model($pdo,$uid,$selection['month']);
        if(!is_string($r['fingerprint']??null)||!hash_equals($model['fingerprint'],$r['fingerprint']))throw new ReportingProblem('The recorded figures changed. Refresh Planning before creating the draft.',409);
        $eligible=[];foreach($model['details'] as $row)if($row['eligible']&&$row['estimate']['amount']>0)$eligible[$row['account_id'].':'.($row['project_id']??'operations')]=accounting_decimal($row['estimate']['amount']);
        if(!$selection['lines'])throw new ReportingProblem('Select at least one positive recommended allocation.',422);
        foreach($selection['lines'] as $line)if(($eligible[$line['account_id'].':'.($line['project_id']??'operations')]??null)!==$line['amount'])throw new ReportingProblem('Use the selected positive recommendations. Zero and manual-planning estimates cannot prefill a draft.',422);
    };
    // budget_save recovers identical committed requests before the new-create guard.
    return budget_save($pdo,$uid,['payload'=>$payload,'request_key'=>$key,'copied_revision_id'=>$copy===null?'':(string)$copy,'draft_id'=>'','csrf_token'=>$r['csrf_token']],$guard);
}
