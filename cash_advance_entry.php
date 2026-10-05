<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin'],'Cash advances');require_once __DIR__.'/includes/cash_advance.php';
try{
    $uid=(int)$_SESSION['UserID'];advance_guard($pdo,$uid,null,true);$draftId=journal_id(journal_string($_GET,'draft_id',true),true);
    if($draftId!==null){$d=workspace_draft($pdo,$uid,$draftId);advance_draft($d);$kind=$d['workflow_kind'];$target=$d['advance_id']===null?null:(int)$d['advance_id'];}
    else{$kind=journal_string($_GET,'workflow_kind',true)?:'advance_release';$target=journal_id(journal_string($_GET,'advance_id',true),true);}
    $books=['advance_release'=>'CDB','advance_liquidation'=>'GJ','advance_return'=>'CRB'];if(!isset($books[$kind])||($draftId===null&&(($kind==='advance_release')!==($target===null))))throw new JournalProblem('Choose an operation from the advance register.');
    $context=$target===null?null:advance_detail($pdo,$uid,$target,journal_today());$workspaceBook=$books[$kind];
    $advanceConfig=['workflow_kind'=>$kind,'advance_id'=>$target===null?'':(string)$target,'advance'=>$context,'endpoint'=>'cash_advance_actions.php'];
}catch(JournalProblem $e){http_response_code($e->status);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
require __DIR__.'/includes/accounting_entry_page.php';
