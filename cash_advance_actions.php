<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/cash_advance.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
    $uid=(int)($_SESSION['UserID']??0);$role=advance_guard($pdo,$uid);$method=$_SERVER['REQUEST_METHOD'];
    if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');throw new JournalProblem('Method not allowed.',405);}
    if($method==='GET'){
        $action=journal_string($_GET,'action');if(!in_array($action,['register','advance'],true)&&$role!=='Admin')throw new JournalProblem('This lookup is private to accounting staff.',403);
        $result=match($action){
            'register'=>advance_register($pdo,$uid,$_GET),
            'advance'=>advance_detail($pdo,$uid,journal_id(journal_string($_GET,'advance_id')),journal_string($_GET,'as_of',true)),
            'draft'=>advance_draft_public($pdo,$uid,journal_id(journal_string($_GET,'draft_id'))),
            'lists'=>workspace_lists($pdo,$uid),
            'documents'=>(function()use($pdo,$uid){$s=$pdo->prepare("SELECT r.ReceiptID id,r.Original_Filename name FROM Receipts r LEFT JOIN draft_evidence_reservations v ON v.receipt_id=r.ReceiptID WHERE r.UploadedBy_UserID=? AND r.ExpenseID IS NULL AND r.JournalEntryID IS NULL AND r.File_SHA256 IS NOT NULL AND r.OCR_Status NOT IN ('Pending','Discarded') AND v.receipt_id IS NULL ORDER BY r.ReceiptID DESC LIMIT 100");$s->execute([$uid]);return $s->fetchAll();})(),
            default=>throw new JournalProblem('Unknown advance lookup.',400)
        };
    }else{
        if((int)($_SERVER['CONTENT_LENGTH']??0)>10*1024*1024)throw new JournalProblem('Request too large.',413);
        if(str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){$raw=file_get_contents('php://input',false,null,0,1048577);if(strlen($raw)>1048576)throw new JournalProblem('Request too large.',413);$r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($r))throw new JournalProblem('Invalid request.');}else $r=$_POST;
        advance_guard($pdo,$uid,$r,true);$action=journal_string($r,'action');
        if(in_array($action,['upload','attach','remove','discard'],true)){advance_draft(workspace_draft($pdo,$uid,journal_id(journal_string($r,'draft_id'))));}
        $result=match($action){
            'save'=>advance_save($pdo,$uid,$r),'review'=>advance_review($pdo,$uid,$r),'post'=>advance_post($pdo,$uid,$r),
            'confirm_return_proof'=>advance_confirm($pdo,$uid,$r),'extend_due'=>advance_extend_due($pdo,$uid,$r),
            'upload'=>workspace_upload($pdo,$uid,$r,$_FILES['image']??[]),
            'attach'=>workspace_attach($pdo,$uid,$r,journal_id(journal_string($r,'receipt_id'))),
            'remove'=>workspace_remove_or_discard($pdo,$uid,$r,false),'discard'=>workspace_remove_or_discard($pdo,$uid,$r,true),
            default=>throw new JournalProblem('Unknown advance action.',400)
        };
    }
    echo workspace_json(['ok'=>true,'result'=>$result]);
}catch(JournalProblem $e){http_response_code($e->status===422?400:$e->status);echo workspace_json(['ok'=>false,'error'=>$e->getMessage()]);}
catch(JsonException|InvalidArgumentException $e){http_response_code(400);echo workspace_json(['ok'=>false,'error'=>'Invalid advance request.']);}
catch(Throwable $e){error_log('Cash advances: '.$e->getMessage());http_response_code(503);echo workspace_json(['ok'=>false,'error'=>'The advance action could not be completed. Your saved draft is retained; retry or reload it.']);}
