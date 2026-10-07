<?php
session_start();
require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin'],'Journal corrections');
require_once __DIR__.'/includes/correction_drafts.php';
try{
    $uid=(int)$_SESSION['UserID'];correction_guard($pdo,$uid);
    $draftId=journal_id(journal_string($_GET,'draft_id',true),true);
    $d=$draftId===null?null:correction_entry_draft($pdo,$uid,$draftId);
    $targetId=$d?(int)$d['correction_target_journal_id']:journal_id(journal_string($_GET,'journal_id'));
    $t=correction_entry_target($pdo,$targetId);$mode=$d?$d['correction_mode']:journal_string($_GET,'mode',true);
    if($mode==='')$mode='reverse_replace';if(!in_array($mode,['reverse_only','reverse_replace'],true))throw new JournalProblem('Choose a valid correction mode.');
    $requestedBook=$d?$d['source_book']:journal_string($_GET,'book',true);if($requestedBook==='')$requestedBook=$t['journal']['source_book']?:'GJ';
    $workspaceBook=correction_book($t,$mode,$requestedBook);
    $correctionConfig=['is_correction'=>true,'today'=>journal_today(),'endpoint'=>'journal_correction_actions.php','target'=>$t,'target_journal_id'=>$targetId,'mode'=>$mode,'initial_payload'=>$d?$d['payload']:correction_seed($t,$mode,$workspaceBook)];
    if($mode==='reverse_replace'&&$t['operation']){
        $a=$t['advance'];$advanceConfig=['workflow_kind'=>'advance_'.$t['operation']['operation_kind'],'advance_id'=>$a['id'],'advance'=>['party_id'=>$a['party_id'],'originating_project_id'=>$a['originating_project_id'],'number'=>advance_number((int)$a['id']),'employee'=>json_decode($a['party_snapshot'],true)['name'],'outstanding'=>'See advance register','due_date'=>$a['initial_due_date'],'original_party'=>json_decode($a['party_snapshot'],true),'original_project'=>$a['project_snapshot']===null?null:json_decode($a['project_snapshot'],true)]];
        if($t['operation']['operation_kind']==='release')unset($advanceConfig['advance']);
    }
}catch(JournalProblem $e){http_response_code($e->status);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
require __DIR__.'/includes/accounting_entry_page.php';
