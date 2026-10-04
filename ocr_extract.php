<?php
/** POST-only evidence intake. This endpoint never posts a journal. */
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/user_session.php';
require_once __DIR__.'/includes/receipt_ocr.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');throw new JournalProblem('POST required.',405);}
    if(!user_session_validate($pdo)){throw new JournalProblem('Please sign in again.',401);}
    if($_SESSION['Role']!=='Admin'){throw new JournalProblem('Scanning receipts requires an Admin.',403);}
    $uid=(int)$_SESSION['UserID'];$action=journal_string($_POST,'action',true)?:'upload';
    if($action==='upload'){$id=receipt_upload($pdo,$uid,$_POST,$_FILES['receipt_image']??[]);}
    elseif($action==='retry'){$id=journal_id(journal_string($_POST,'receipt_id'));}
    else{throw new JournalProblem('Unsupported action.',400);}
    $attempt=receipt_extract($pdo,$uid,$id,$_POST);
    echo json_encode(['ok'=>true,'data'=>['receipt_id'=>$id,'attempt_id'=>(int)$attempt['id'],'status'=>$attempt['state'],
        'workspace_url'=>'ocr_expense.php?receipt='.$id,'review_url'=>'general_journal.php?receipt_id='.$id,
        'warning'=>$attempt['error_message']],'error'=>''],JSON_THROW_ON_ERROR);
} catch(JournalProblem $e){http_response_code($e->status);echo json_encode(['ok'=>false,'data'=>isset($id)?['workspace_url'=>'ocr_expense.php?receipt='.$id]:null,'error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Receipt intake endpoint failed: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'data'=>isset($id)?['workspace_url'=>'ocr_expense.php?receipt='.$id]:null,'error'=>'Receipt intake is temporarily unavailable. Reload the workspace to recover saved evidence.']);}
