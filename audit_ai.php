<?php
/** Protected deprecated provider endpoint. Retained audit_trail.php?tab=alerts; no provider/cache write. */
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/user_session.php';require_once __DIR__.'/includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
try{if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['ok'=>false,'error'=>'Use POST.']);exit;}
if(!user_session_validate($pdo)){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Please sign in again.']);exit;}
if(!in_array($_SESSION['Role']??'',['Admin'],true)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Access denied.']);exit;}
if(!csrf_verify(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:null)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Reload and try again.']);exit;}
http_response_code(410);echo json_encode(['ok'=>false,'error'=>'Provider-based analysis is retired. Open Audit Alerts for the current explainable view.','data'=>['url'=>'audit_trail.php?tab=alerts']]);
}catch(Throwable $e){error_log('Deprecated advisory endpoint: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Account access is temporarily unavailable.']);}
