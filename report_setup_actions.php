<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/report_configuration.php';
header('Cache-Control: private, no-store');header('Content-Type: application/json; charset=utf-8');
try{
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');throw new ReportingProblem('Use the reporting setup buttons to save changes.',405);}
    reporting_actor($pdo,(int)($_SESSION['UserID']??0),['Admin']);
    $bytes=file_get_contents('php://input',false,null,0,1100001);if(strlen($bytes)>1100000)throw new ReportingProblem('This setup draft is too large.',413);
    try{$r=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new ReportingProblem('Send a valid setup request.',400);}
    if(!is_array($r))throw new ReportingProblem('Send a valid setup request.',400);$uid=(int)$_SESSION['UserID'];
    if(($r['action']??'')==='save')$out=report_configuration_save($pdo,$uid,$r);
    elseif(($r['action']??'')==='submit')$out=report_configuration_submit($pdo,$uid,$r);
    else throw new ReportingProblem('Choose Save and close or Submit setup.',400);
    echo json_encode(['ok'=>true,'data'=>$out],JSON_THROW_ON_ERROR);
}catch(ReportingProblem $e){http_response_code($e->status);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);error_log('Report setup action unavailable.');echo json_encode(['ok'=>false,'error'=>'The setup response could not be confirmed. Keep this page open and check your saved drafts before trying again.']);}
