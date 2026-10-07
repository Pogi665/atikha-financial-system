<?php
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/correction_posting.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try{
    $uid=(int)($_SESSION['UserID']??0);correction_guard($pdo,$uid);$method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET'){
        $action=journal_string($_GET,'action');
        if($action==='lists')$result=workspace_lists($pdo,$uid);
        elseif($action==='target')$result=accounting_read($pdo,fn()=>correction_entry_target($pdo,journal_id(journal_string($_GET,'journal_id'))));
        elseif($action==='draft')$result=accounting_read($pdo,fn()=>correction_public($pdo,$uid,correction_entry_draft($pdo,$uid,journal_id(journal_string($_GET,'draft_id')))));
        elseif($action==='documents'){$s=$pdo->prepare("SELECT r.ReceiptID id,r.Original_Filename name FROM Receipts r LEFT JOIN draft_evidence_reservations v ON v.receipt_id=r.ReceiptID WHERE r.UploadedBy_UserID=? AND r.ExpenseID IS NULL AND r.JournalEntryID IS NULL AND r.File_SHA256 IS NOT NULL AND r.OCR_Status NOT IN ('Pending','Discarded') AND v.receipt_id IS NULL ORDER BY r.ReceiptID DESC LIMIT 100");$s->execute([$uid]);$result=$s->fetchAll();}
        elseif($action==='reuse_candidates'){$d=correction_entry_draft($pdo,$uid,journal_id(journal_string($_GET,'draft_id')));$result=[];if($d['state']==='Draft'&&$d['correction_mode']==='reverse_replace'&&correction_entry_target($pdo,(int)$d['correction_target_journal_id'])['eligible']){$s=$pdo->prepare('SELECT a.*,r.Original_Filename FROM posted_evidence_associations a JOIN Receipts r ON r.ReceiptID=a.receipt_id LEFT JOIN correction_evidence_reservations v ON v.receipt_id=a.receipt_id WHERE a.journal_id=? AND v.receipt_id IS NULL ORDER BY a.id');$s->execute([$d['correction_target_journal_id']]);foreach($s->fetchAll() as $a)if(stage3_evidence_association_valid($pdo,$a))$result[]=['id'=>(int)$a['receipt_id'],'name'=>$a['Original_Filename']?:'Target image #'.$a['receipt_id']];}}
        else throw new JournalProblem('Unknown correction lookup.');
    }elseif($method==='POST'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>10*1024*1024)throw new JournalProblem('Request too large.',413);
        if(str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){$raw=file_get_contents('php://input',false,null,0,1048577);if(strlen($raw)>1048576)throw new JournalProblem('Request too large.',413);$r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($r))throw new JournalProblem('Invalid request.');}else $r=$_POST;
        correction_guard($pdo,$uid,$r);$action=journal_string($r,'action');
        $result=match($action){
            'save'=>correction_save($pdo,$uid,$r),'review'=>correction_review($pdo,$uid,$r),
            'attach'=>correction_attach($pdo,$uid,$r),'attach_reuse'=>correction_attach($pdo,$uid,$r,true),
            'remove'=>correction_remove_or_discard($pdo,$uid,$r,false),'discard'=>correction_remove_or_discard($pdo,$uid,$r,true),
            'upload'=>correction_upload($pdo,$uid,$r,$_FILES['image']??[]),'confirm_return_proof'=>correction_confirm($pdo,$uid,$r),
            'post'=>correction_post($pdo,$uid,$r),
            default=>throw new JournalProblem('Unknown correction action.')};
    }else{header('Allow: GET, POST');throw new JournalProblem('Method not allowed.',405);}
    echo workspace_json(['ok'=>true,'result'=>$result]);
}catch(JournalProblem $e){http_response_code($e->status);echo workspace_json(['ok'=>false,'error'=>$e->getMessage()]);}
catch(JsonException|InvalidArgumentException $e){http_response_code(400);echo workspace_json(['ok'=>false,'error'=>'Invalid correction request.']);}
catch(Throwable $e){error_log('Correction drafts: '.$e->getMessage());http_response_code(503);echo workspace_json(['ok'=>false,'error'=>'The correction request could not be completed. Your last saved draft is retained.']);}
