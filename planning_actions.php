<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/planning_budget.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
try{
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ReportingProblem('Use POST to create a budget draft.',405);
    $r=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);if(!is_array($r))throw new ReportingProblem('Check the planning request.',400);reporting_csrf($r);$uid=(int)($_SESSION['UserID']??0);reporting_actor($pdo,$uid,['Admin']);
    if(($r['action']??'')!=='create_budget')throw new ReportingProblem('Choose a supported planning action.',400);
    $out=planning_budget_create($pdo,$uid,$r);echo json_encode(['ok'=>true,'data'=>['id'=>$out['id'],'url'=>'budgets.php?draft_id='.$out['id']]],JSON_THROW_ON_ERROR);
}catch(Throwable $e){$known=$e instanceof ReportingProblem||$e instanceof JournalProblem;http_response_code($known?$e->status:503);if(!$known)error_log('Planning action: '.$e->getMessage());echo json_encode(['ok'=>false,'error'=>$known?$e->getMessage():'The draft result could not be confirmed. Check your saved budget drafts before trying again.']);}
