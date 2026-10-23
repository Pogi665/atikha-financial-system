<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/budget_revisions.php';header('Cache-Control: private, no-store');header('Content-Type: application/json; charset=utf-8');
try{
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');throw new ReportingProblem('Use the budget task buttons to save or review.',405);}
    $uid=(int)($_SESSION['UserID']??0);reporting_actor($pdo,$uid);$bytes=file_get_contents('php://input',false,null,0,1100001);if(strlen($bytes)>1100000)throw new ReportingProblem('The budget draft is too large.',413);
    try{$r=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new ReportingProblem('Send a valid budget request.',400);}if(!is_array($r))throw new ReportingProblem('Send a valid budget request.',400);
    switch($r['action']??''){case 'save':$out=budget_save($pdo,$uid,$r);break;case 'submit':$out=budget_submit($pdo,$uid,$r);break;case 'review':$out=budget_review($pdo,$uid,$r);break;default:throw new ReportingProblem('Choose a listed budget action.',400);}
    echo json_encode(['ok'=>true,'data'=>$out],JSON_THROW_ON_ERROR);
}catch(ReportingProblem $e){http_response_code($e->status);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'cell'=>$e instanceof BudgetCellProblem?$e->cell:null]);}
catch(Throwable $e){http_response_code(503);error_log('Monthly budget action unavailable.');echo json_encode(['ok'=>false,'error'=>'The budget response could not be confirmed. Keep your entries and check the saved revision before trying again.']);}
