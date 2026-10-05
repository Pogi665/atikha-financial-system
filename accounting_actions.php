<?php
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/accounting_workspace.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try{
    $uid=(int)($_SESSION['UserID']??0);workspace_guard($pdo,$uid);
    $method=$_SERVER['REQUEST_METHOD'];
    if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');throw new JournalProblem('Method not allowed.',405);}
    if($method==='GET'){
        $action=journal_string($_GET,'action');
        if($action==='lists'){$result=workspace_lists($pdo,$uid);}
        elseif($action==='draft'){
            $d=workspace_draft($pdo,$uid,journal_id(journal_string($_GET,'draft_id')));$result=workspace_draft_public($d);
            if($d['state']==='Posted'){
                $s=$pdo->prepare('SELECT party_snapshot FROM journal_entries WHERE id=?');$s->execute([$d['posted_journal_id']]);$snapshot=$s->fetchColumn();
                $result['posted_party']=$snapshot===null?null:json_decode($snapshot,true,32,JSON_THROW_ON_ERROR);
                $s=$pdo->prepare('SELECT l.account_id,c.Name account_name,c.Account_Code account_code,l.fund_project_id,l.project_code_snapshot,l.project_name_snapshot FROM journal_entry_lines l JOIN Categories c ON c.CategoryID=l.account_id WHERE l.journal_entry_id=? ORDER BY l.id');
                $s->execute([$d['posted_journal_id']]);$result['posted_lines']=$s->fetchAll();
            }
            $result['document_details']=[];
            foreach($d['payload']['documents'] as $doc){
                $s=$pdo->prepare('SELECT ReceiptID,Original_Filename,Mime_Type,File_Size FROM Receipts WHERE ReceiptID=? AND UploadedBy_UserID=?');$s->execute([$doc['receipt_id'],$uid]);
                if($r=$s->fetch()){$result['document_details'][]=['id'=>(int)$r['ReceiptID'],'name'=>$r['Original_Filename']?:'Supporting image','size'=>(int)$r['File_Size']];}
            }
        }elseif($action==='drafts'){
            $result=workspace_drafts($pdo,$uid,$_GET);
        }elseif($action==='documents'){
            $s=$pdo->prepare("SELECT r.ReceiptID id,r.Original_Filename name FROM Receipts r LEFT JOIN draft_evidence_reservations v ON v.receipt_id=r.ReceiptID
                WHERE r.UploadedBy_UserID=? AND r.ExpenseID IS NULL AND r.JournalEntryID IS NULL AND r.File_SHA256 IS NOT NULL AND r.OCR_Status NOT IN ('Pending','Discarded') AND v.receipt_id IS NULL ORDER BY r.ReceiptID DESC LIMIT 100");$s->execute([$uid]);$result=$s->fetchAll();
        }else{throw new JournalProblem('Unknown lookup.',400);}
    }else{
        if((int)($_SERVER['CONTENT_LENGTH']??0)>10*1024*1024){throw new JournalProblem('Request too large.',413);}
        if(str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){
            $raw=file_get_contents('php://input',false,null,0,1048577);if(strlen($raw)>1048576){throw new JournalProblem('Request too large.',413);}
            $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($r)){throw new JournalProblem('Invalid request.',400);}
        }else{$r=$_POST;}
        workspace_guard($pdo,$uid,$r);$action=journal_string($r,'action');
        $result=match($action){
            'save'=>workspace_save($pdo,$uid,$r), 'review'=>workspace_review($pdo,$uid,$r), 'post'=>workspace_post($pdo,$uid,$r),
            'master'=>workspace_master_save($pdo,$uid,$r),
            'control'=>(function()use($pdo,$uid,$r){workspace_control($pdo,$uid,$r);return ['saved'=>true];})(),
            'attach'=>workspace_attach($pdo,$uid,$r,journal_id(journal_string($r,'receipt_id'))),
            'remove'=>workspace_remove_or_discard($pdo,$uid,$r,false), 'discard'=>workspace_remove_or_discard($pdo,$uid,$r,true),
            'upload'=>workspace_upload($pdo,$uid,$r,$_FILES['image']??[]),
            default=>throw new JournalProblem('Unknown accounting action.',400)
        };
    }
    echo workspace_json(['ok'=>true,'result'=>$result]);
}catch(JournalProblem $e){http_response_code($e->status);echo workspace_json(['ok'=>false,'error'=>$e->getMessage()]);}
catch(JsonException|InvalidArgumentException $e){http_response_code(400);echo workspace_json(['ok'=>false,'error'=>'Invalid accounting request.']);}
catch(Throwable $e){error_log('Accounting workspace: '.$e->getMessage());http_response_code(503);echo workspace_json(['ok'=>false,'error'=>'The request could not be completed. Your last saved draft is retained; retry or reload it.']);}
