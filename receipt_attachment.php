<?php
/** Authenticated evidence bytes. Direct storage URLs are denied by Apache. */
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/require_role.php';
require_once __DIR__.'/includes/receipt_ocr.php';
require_login();
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
try {
    receipt_require_schema($pdo);
    $id=journal_id(journal_string($_GET,'receipt_id'));
    $journal=journal_id(journal_string($_GET,'journal_id',true),true);
    $draft=journal_id(journal_string($_GET,'draft_id',true),true);
    if($journal!==null&&$draft!==null)throw new JournalProblem('Choose one evidence context.',400);
    $draftAllowed=false;if($draft!==null){require_once __DIR__.'/includes/correction_drafts.php';$draftAllowed=correction_draft_download_allowed($pdo,(int)$_SESSION['UserID'],$draft,$id);}
    $s=$pdo->prepare('SELECT r.*,j.status AS journal_status FROM Receipts r LEFT JOIN journal_entries j ON j.id=r.JournalEntryID WHERE r.ReceiptID=?');
    $s->execute([$id]);$r=$s->fetch();
    $role=$_SESSION['Role'];
    $allowed=$r&&$r['ExpenseID']===null&&$r['OCR_Status']!=='Discarded'&&(
        ($draft!==null?$draftAllowed:(($r['JournalEntryID']===null&&$journal===null&&$role==='Admin'&&(int)$r['UploadedBy_UserID']===(int)$_SESSION['UserID'])
        ||($r['JournalEntryID']!==null&&$journal!==null&&receipt_posted_download_allowed($pdo,$id,$journal)))));
    if(!$allowed){throw new JournalProblem('Evidence not found or access restricted.',404);}
    $path=receipt_file_verify($r);
    if(!isset(ALLOWED_RECEIPT_MIMES[$r['Mime_Type']])){throw new JournalProblem('Evidence unavailable.',415);}
    $handle=fopen($path,'rb');
    if(!$handle){throw new JournalProblem('Evidence unavailable.',404);}
    // Verify the actual handle to close the path replacement window.
    $ctx=hash_init('sha256');hash_update_stream($ctx,$handle);
    if(!hash_equals($r['File_SHA256'],hash_final($ctx))){fclose($handle);throw new JournalProblem('Evidence has changed.',409);}
    rewind($handle);
    header('Content-Type: '.$r['Mime_Type']);
    header('Content-Disposition: '.(isset($_GET['download'])?'attachment':'inline').'; filename="receipt-'.$id.'.'.ALLOWED_RECEIPT_MIMES[$r['Mime_Type']].'"');
    header('Content-Length: '.fstat($handle)['size']);
    if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
    if($_SERVER['REQUEST_METHOD']!=='HEAD'){fpassthru($handle);}fclose($handle);
} catch(JournalProblem $e){http_response_code($e->status);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
catch(Throwable $e){error_log('Receipt evidence download failed: '.$e->getMessage());http_response_code(503);echo 'Receipt evidence is temporarily unavailable.';}
