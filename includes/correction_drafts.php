<?php
/** Private correction drafts, evidence reservations and reviewed comparisons. */
require_once __DIR__.'/journal_corrections.php';
require_once __DIR__.'/cash_advance.php';

function correction_guard(PDO $pdo,int $uid,?array $request=null): void
{
    workspace_guard($pdo,$uid,$request);
    if(!stage3_enabled($pdo))throw new JournalProblem('Corrections are not enabled. Complete Stage 3 deployment first.',503);
}
/** Entry gates use persisted provenance; shared posted readers remain flag independent. */
function correction_workflow_guard(PDO $pdo,int $targetId): void
{
    $s=$pdo->prepare('SELECT operation_kind FROM cash_advance_operations WHERE journal_id=?');$s->execute([$targetId]);
    if($s->fetchColumn()!==false&&!stage2_enabled($pdo))throw new JournalProblem('Advance corrections are paused while Cash Advances is disabled. Your saved draft is retained.',503);
}
function correction_entry_draft(PDO $pdo,int $uid,int $id,bool $lock=false): array
{
    $d=correction_draft($pdo,$uid,$id,$lock);correction_workflow_guard($pdo,(int)$d['correction_target_journal_id']);return $d;
}
function correction_entry_target(PDO $pdo,int $id,bool $lock=false): array
{
    correction_workflow_guard($pdo,$id);return correction_target($pdo,$id,$lock);
}
function correction_draft(PDO $pdo,int $uid,int $id,bool $lock=false): array
{
    $d=workspace_draft($pdo,$uid,$id,$lock);
    if((int)$d['payload_version']!==4||$d['workflow_kind']!=='correction')throw new JournalProblem('Use the original workspace for this draft.',409);
    $s=$pdo->prepare('SELECT advance_id FROM cash_advance_operations WHERE journal_id=?');$s->execute([$d['correction_target_journal_id']]);$advance=$s->fetchColumn();
    if($advance===false?$d['advance_id']!==null:(int)$d['advance_id']!==(int)$advance)throw new JournalProblem('Correction advance context is inconsistent.',409);
    return $d;
}
/** Derive all correction identity from persisted posted records, never from draft flags. */
function correction_target(PDO $pdo,int $id,bool $lock=false): array
{
    $s=$pdo->prepare("SELECT * FROM journal_entries WHERE id=? AND status='posted'".($lock?' FOR UPDATE':''));$s->execute([$id]);$j=$s->fetch();
    if(!$j)throw new JournalProblem('Posted target journal not found.',404);
    $s=$pdo->prepare('SELECT l.*,c.Name account_name,c.Account_Code account_code,c.Is_Cash_Account FROM journal_entry_lines l JOIN Categories c ON c.CategoryID=l.account_id WHERE l.journal_entry_id=? ORDER BY l.id');$s->execute([$id]);$lines=$s->fetchAll();
    $s=$pdo->prepare('SELECT COUNT(*) FROM journal_entry_lines WHERE journal_entry_id=?');$s->execute([$id]);if((int)$s->fetchColumn()!==count($lines))throw new JournalProblem('Target has missing account references.',409);
    $debit=$credit=0;foreach($lines as $l){$dr=journal_amount($l['debit_amount']);$cr=journal_amount($l['credit_amount']);if(($dr>0)===($cr>0))throw new JournalProblem('Target contains an invalid journal line.',409);$debit=accounting_add($debit,$dr);$credit=accounting_add($credit,$cr);}
    if(count($lines)<2||count($lines)>100||$debit<=0||$debit!==$credit)throw new JournalProblem('Target journal is incomplete or unbalanced.',409);
    $s=$pdo->prepare('SELECT * FROM posted_evidence_associations WHERE journal_id=? ORDER BY receipt_id');$s->execute([$id]);foreach($s->fetchAll() as $association)if(!stage3_evidence_association_valid($pdo,$association))throw new JournalProblem('Target evidence provenance is damaged.',409);
    $s=$pdo->prepare('SELECT DISTINCT r.* FROM Receipts r LEFT JOIN posted_evidence_associations a ON a.receipt_id=r.ReceiptID WHERE r.JournalEntryID=? OR a.journal_id=? ORDER BY r.ReceiptID');$s->execute([$id,$id]);foreach($s->fetchAll() as $image)receipt_file_verify($image);
    $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE target_journal_id=?');$s->execute([$id]);$corrected=$s->fetch();
    if($corrected&&!stage3_correction_valid($pdo,$corrected))throw new JournalProblem('Target correction chain is damaged.',409);
    $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE replacement_journal_id=?');$s->execute([$id]);$parent=$s->fetch();
    if($parent&&!stage3_correction_valid($pdo,$parent))throw new JournalProblem('Target correction lineage is damaged.',409);
    $s=$pdo->prepare('SELECT * FROM cash_advance_operations WHERE journal_id=?');$s->execute([$id]);$operation=$s->fetch()?:null;
    $advance=$operation?advance_load($pdo,(int)$operation['advance_id'],$lock):null;
    $controls=array_map('intval',$pdo->query('SELECT account_id FROM advance_control_designations')->fetchAll(PDO::FETCH_COLUMN));
    $hasControl=false;foreach($lines as $l)if(in_array((int)$l['account_id'],$controls,true))$hasControl=true;
    if(($hasControl||str_starts_with($j['transaction_kind']??'','advance_'))&&!$operation&&$j['transaction_kind']!=='correction_reversal')throw new JournalProblem('Target has unrecognized advance/control usage.',409);
    $blockers=[];
    if($operation){if(correction_control_integrity($pdo))throw new JournalProblem('Advance control integrity must be resolved before correction.',409);
        if($operation['operation_kind']==='release'){$s=$pdo->prepare("SELECT o.id,o.journal_id,o.operation_kind,j.entry_date FROM cash_advance_operations o JOIN journal_entries j ON j.id=o.journal_id LEFT JOIN cash_advance_operation_reversals r ON r.original_operation_id=o.id WHERE o.advance_id=? AND o.operation_kind<>'release' AND r.id IS NULL ORDER BY j.entry_date,o.id");$s->execute([$advance['id']]);$blockers=$s->fetchAll();}}
    $reason=$corrected?'This journal has already been corrected. This draft cannot be posted.':($j['transaction_kind']==='correction_reversal'?'Generated reversals cannot be corrected.':($blockers?'Reverse the effective settlements first. A replacement settlement still depends on this release.':''));
    return ['journal'=>$j,'lines'=>$lines,'operation'=>$operation,'advance'=>$advance,'root_journal_id'=>$parent?(int)$parent['root_journal_id']:$id,'parent_correction_id'=>$parent?(int)$parent['id']:null,'eligible'=>$reason==='','ineligible_reason'=>$reason,'blockers'=>$blockers,'correction'=>$corrected?:null];
}
function correction_eligible(array $target): void {if(!$target['eligible'])throw new JournalProblem($target['ineligible_reason'],409);}
function correction_book(array $target,string $mode,string $book): string
{
    if($mode==='reverse_only')return 'GJ';
    if($target['operation'])return ['release'=>'CDB','liquidation'=>'GJ','return'=>'CRB'][$target['operation']['operation_kind']];
    if(!in_array($book,['CRB','CDB','GJ'],true))throw new JournalProblem('Choose a valid replacement book.');return $book;
}
function correction_payload(array $p,array $target,string $mode): array
{
    $out=[];foreach(['entry_date'=>10,'reason'=>2000,'backdate_reason'=>2000] as $k=>$max){$v=journal_string($p,$k,true);if(mb_strlen($v)>$max)throw new JournalProblem('Correction field exceeds its length limit.');$out[$k]=$v;}
    if($mode==='reverse_only'){if(($p['replacement']??null)!==null)throw new JournalProblem('A reversal-only draft cannot contain a replacement.');$out['replacement']=null;return $out;}
    if(!is_array($p['replacement']??null))throw new JournalProblem('Replacement draft must be an object.');
    $raw=$p['replacement'];$out['replacement']=$target['operation']?advance_payload($raw):workspace_payload($raw);
    // The bundle has one accounting date. Nested date cannot independently change it.
    $out['replacement']['entry_date']=$out['entry_date'];
    foreach($out['replacement']['documents'] as $i=>&$doc){$reason=journal_string($raw['documents'][$i],'re_review_reason',true);if(mb_strlen($reason)>2000)throw new JournalProblem('Document re-review reason is too long.');$doc['re_review_reason']=$reason;}unset($doc);
    if(strlen(workspace_json($out))>1048576)throw new JournalProblem('Correction draft exceeds the supported size.');return $out;
}
function correction_seed(array $t,string $mode,string $book): array
{
    $j=$t['journal'];$p=['entry_date'=>journal_today(),'reason'=>'','backdate_reason'=>'','replacement'=>null];if($mode==='reverse_only')return $p;
    $r=['entry_date'=>$p['entry_date'],'reference'=>$j['reference']??'','description'=>$j['description'],'party_id'=>$j['party_id']===null?'':(string)$j['party_id'],'default_project_id'=>'','cash_account_id'=>'','cash_amount'=>'','cash_project_id'=>'','transaction_kind'=>in_array($j['transaction_kind'],['ordinary','transfer'],true)?$j['transaction_kind']:'ordinary','lines'=>[],'documents'=>[]];
    $cashFound=false;$op=$t['operation'];$a=$t['advance'];
    foreach($t['lines'] as $l){if($op&&(int)$l['id']===(int)$op['control_line_id'])continue;
        if($book!=='GJ'&&!$cashFound&&(int)$l['Is_Cash_Account']===1&&journal_amount($l[$book==='CRB'?'debit_amount':'credit_amount'])>0){$r['cash_account_id']=(string)$l['account_id'];$r['cash_amount']=$l[$book==='CRB'?'debit_amount':'credit_amount'];$r['cash_project_id']=$l['fund_project_id']===null?'':(string)$l['fund_project_id'];$cashFound=true;continue;}
        $r['lines'][]=['client_id'=>'original_'.$l['id'],'account_id'=>(string)$l['account_id'],'fund_project_id'=>$l['fund_project_id']===null?'':(string)$l['fund_project_id'],'debit_amount'=>$l['debit_amount'],'credit_amount'=>$l['credit_amount']];
    }
    if($op){$r+=['control_account_id'=>(string)$a['control_account_id'],'due_date'=>journal_today(),'approval_name'=>'','approval_date'=>'','approval_reference'=>'','line_notes'=>[]];$r['party_id']=(string)$a['party_id'];$r['default_project_id']=$r['cash_project_id']=$a['originating_project_id']===null?'':(string)$a['originating_project_id'];if($op['operation_kind']!=='liquidation')$r['lines']=[];}
    $p['replacement']=$r;return $p;
}
function correction_documents(array $d): array {return $d['payload']['replacement']['documents']??[];}
function correction_update(PDO $pdo,int $uid,array $d,array $payload,?string $confirmation=null,?string $state=null): array
{
    $pdo->prepare('UPDATE journal_drafts SET payload=?,return_confirmation=?,state=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([workspace_json($payload),$confirmation,$state??$d['state'],$d['id']]);
    workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Correction Draft',(int)$d['id'],['revision'=>$d['revision']],['revision'=>(int)$d['revision']+1,'state'=>$state??$d['state']]);
    return workspace_draft_public(correction_draft($pdo,$uid,(int)$d['id']));
}
function correction_save(PDO $pdo,int $uid,array $r): array
{
    if(!is_array($r['payload']??null))throw new JournalProblem('Correction payload must be an object.');
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id',true),true);$targetId=journal_id(journal_string($r,'target_journal_id'));$mode=journal_string($r,'mode');
    if(!in_array($mode,['reverse_only','reverse_replace'],true))throw new JournalProblem('Choose reversal only or reversal and replacement.');
    $key=journal_string($r,'submission_key');if(!preg_match('/\A[a-f0-9]{64}\z/',$key))throw new JournalProblem('Invalid durable submission key.');
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$targetId,$mode,$key){
        $d=$id===null?null:correction_entry_draft($pdo,$uid,$id,true);
        if($d){workspace_revision($d,$r);if((int)$d['correction_target_journal_id']!==$targetId||$d['correction_mode']!==$mode||$d['submission_key']!==$key)throw new JournalProblem('Correction identity and mode cannot change.',409);}
        $t=correction_entry_target($pdo,$targetId,true);correction_eligible($t);$book=correction_book($t,$mode,journal_string($r,'source_book'));$p=correction_payload($r['payload']??[],$t,$mode);$hash=hash('sha256',workspace_json([$targetId,$mode,$book,$p]));
        if($d){if(workspace_document_ids($p['replacement']??['documents'=>[]])!==workspace_document_ids($d['payload']['replacement']??['documents'=>[]]))throw new JournalProblem('Use attach/remove to change reservations.',409);
            $confirmation=$d['return_confirmation'];if($confirmation!==null&&workspace_json($p)!==workspace_json($d['payload']))$confirmation=null;
            $pdo->prepare('UPDATE journal_drafts SET source_book=? WHERE id=?')->execute([$book,$id]);return correction_update($pdo,$uid,$d,$p,$confirmation);}
        if(correction_documents(['payload'=>$p]))throw new JournalProblem('Save before attaching documents.');
        $s=$pdo->prepare('SELECT id,owner_id,creation_hash FROM journal_drafts WHERE submission_key=?');$s->execute([$key]);if($old=$s->fetch()){if((int)$old['owner_id']!==$uid||!hash_equals($hash,$old['creation_hash']))throw new JournalProblem('Draft creation key conflicts with another request.',409);return workspace_draft_public(correction_entry_draft($pdo,$uid,(int)$old['id']));}
        $pdo->prepare("INSERT INTO journal_drafts(owner_id,source_book,payload_version,payload,creation_hash,submission_key,workflow_kind,advance_id,correction_target_journal_id,correction_mode,created_at,updated_at) VALUES(?,?,4,?, ?,?,'correction',?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$uid,$book,workspace_json($p),$hash,$key,$t['advance']['id']??null,$targetId,$mode]);$id=(int)$pdo->lastInsertId();
        workspace_audit($pdo,$uid,AUDIT_ACTION_CREATE,'Correction Draft',$id,null,['target_journal_id'=>$targetId,'mode'=>$mode]);return workspace_draft_public(correction_entry_draft($pdo,$uid,$id));
    });
}
function correction_source(PDO $pdo,array $d,int $receiptId,bool $lock=false): array
{
    $s=$pdo->prepare('SELECT a.* FROM posted_evidence_associations a WHERE a.journal_id=? AND a.receipt_id=?'.($lock?' FOR UPDATE':''));$s->execute([$d['correction_target_journal_id'],$receiptId]);$a=$s->fetch();
    if(!$a||!stage3_evidence_association_valid($pdo,$a))throw new JournalProblem('Image is not valid evidence of this exact target.',409);return $a;
}
function correction_receipt(PDO $pdo,int $uid,array $d,int $receiptId,bool $lock=false,bool $allowReleased=false): array
{
    $s=$pdo->prepare('SELECT * FROM Receipts WHERE ReceiptID=?'.($lock?' FOR UPDATE':''));$s->execute([$receiptId]);$r=$s->fetch();if(!$r||$r['ExpenseID']!==null||in_array($r['OCR_Status'],['Pending','Discarded'],true))throw new JournalProblem('Evidence unavailable.',409);
    $posted=$r['JournalEntryID']!==null;$table=$posted?'correction_evidence_reservations':'draft_evidence_reservations';$s=$pdo->prepare('SELECT * FROM '.$table.' WHERE receipt_id=?');$s->execute([$receiptId]);$v=$s->fetch();
    if($posted){$a=correction_source($pdo,$d,$receiptId,$lock);
        if(!$v&&$allowReleased){$s=$pdo->prepare('SELECT * FROM journal_corrections WHERE target_journal_id=?');$s->execute([$d['correction_target_journal_id']]);$c=$s->fetch();if(!$c||!stage3_correction_valid($pdo,$c)||(int)$c['draft_id']===(int)$d['id'])throw new JournalProblem('Unexplained missing reuse reservation.',409);}
        elseif(!$v||(int)$v['draft_id']!==(int)$d['id']||(int)$v['source_association_id']!==(int)$a['id'])throw new JournalProblem('Posted image is reserved to another draft or its reservation changed.',409);
        $r['source_association']=$a;
    }elseif(!$v||(int)$v['draft_id']!==(int)$d['id']||(int)$r['UploadedBy_UserID']!==$uid)throw new JournalProblem('Unposted image reservation changed.',409);
    receipt_file_verify($r);$attempt=receipt_latest_attempt($pdo,$receiptId);$r['attempt_version']=$attempt['id']??null;return $r;
}
function correction_draft_download_allowed(PDO $pdo,int $uid,int $draftId,int $receiptId): bool
{
    try{if(correction_reader_guard($pdo,$uid)!=='Admin')return false;$d=correction_draft($pdo,$uid,$draftId);if($d['state']!=='Draft'||!in_array($receiptId,workspace_document_ids($d['payload']['replacement']??['documents'=>[]]),true))return false;correction_eligible(correction_target($pdo,(int)$d['correction_target_journal_id']));correction_receipt($pdo,$uid,$d,$receiptId);return true;}catch(Throwable $e){return false;}
}
function correction_public(PDO $pdo,int $uid,array $d): array
{
    $t=correction_entry_target($pdo,(int)$d['correction_target_journal_id']);$out=workspace_draft_public($d);$out['target']=$t;$out['document_details']=[];
    if($d['state']==='Draft')foreach(correction_documents($d) as $doc){$r=correction_receipt($pdo,$uid,$d,(int)$doc['receipt_id'],false,!$t['eligible']);$url='receipt_attachment.php?receipt_id='.$r['ReceiptID'];$url.=$t['eligible']?'&draft_id='.$d['id']:($r['JournalEntryID']!==null?'&journal_id='.$d['correction_target_journal_id']:'');$out['document_details'][]=['id'=>(int)$r['ReceiptID'],'name'=>$r['Original_Filename']?:'Supporting image','size'=>(int)$r['File_Size'],'reused'=>$r['JournalEntryID']!==null,'prior_review'=>$r['source_association']??null,'url'=>$url];}
    if($d['state']==='Posted'){
        $out['chain']=correction_chain($pdo,$uid,(int)$d['posted_journal_id']);
        $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE draft_id=?');$s->execute([$d['id']]);$c=$s->fetch();if(!$c||!stage3_correction_valid($pdo,$c))throw new JournalProblem('Posted correction is inconsistent.',409);
        $out['posted_result']=advance_correction_identity($pdo,$c);
        $out['posted_snapshot']=json_decode($c['review_snapshot'],true,64,JSON_THROW_ON_ERROR);$out['posted_bundle']=[];
        foreach(['reversal'=>'reversal_journal_id','replacement'=>'replacement_journal_id'] as $kind=>$field){if($c[$field]===null)continue;$s=$pdo->prepare('SELECT l.*,c.Name account_name FROM journal_entry_lines l JOIN Categories c ON c.CategoryID=l.account_id WHERE l.journal_entry_id=? ORDER BY l.id');$s->execute([$c[$field]]);$out['posted_bundle'][$kind]=['journal_id'=>(int)$c[$field],'lines'=>$s->fetchAll()];}
    }
    return $out;
}
function correction_document_blank(int $id): array {return ['receipt_id'=>(string)$id,'reviewed'=>false,'purpose'=>'supporting','support_side'=>'','declared_amount'=>'','accepted_amount'=>'','exclusion_reason'=>'','allocations'=>[],'re_review_reason'=>''];}
function correction_attach(PDO $pdo,int $uid,array $r,bool $reuse=false): array
{
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));$rid=journal_id(journal_string($r,'receipt_id'));
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$rid,$reuse){$d=correction_entry_draft($pdo,$uid,$id,true);workspace_revision($d,$r);correction_eligible(correction_entry_target($pdo,(int)$d['correction_target_journal_id'],true));$p=$d['payload'];if($p['replacement']===null)throw new JournalProblem('Reversal-only drafts do not claim evidence.');if(count($p['replacement']['documents'])>=20)throw new JournalProblem('A draft supports up to 20 images.');
        if($reuse){$a=correction_source($pdo,$d,$rid,true);$s=$pdo->prepare('SELECT * FROM Receipts WHERE ReceiptID=? FOR UPDATE');$s->execute([$rid]);$image=$s->fetch();receipt_file_verify($image);stage1_reserved_guard($pdo,$rid);$pdo->prepare('INSERT INTO correction_evidence_reservations(receipt_id,draft_id,source_association_id,created_at) VALUES(?,?,?,UTC_TIMESTAMP())')->execute([$rid,$id,$a['id']]);}
        else{$image=receipt_owned($pdo,$rid,$uid,true);stage1_reserved_guard($pdo,$rid);if($image['JournalEntryID']!==null||$image['OCR_Status']==='Pending'||receipt_duplicate($pdo,$image['File_SHA256']))throw new JournalProblem('Image is posted or processing.',409);receipt_file_verify($image);$pdo->prepare('INSERT INTO draft_evidence_reservations(receipt_id,draft_id,created_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$rid,$id]);}
        $p['replacement']['documents'][]=correction_document_blank($rid);return correction_update($pdo,$uid,$d,$p);
    });
}
function correction_remove_or_discard(PDO $pdo,int $uid,array $r,bool $discard): array
{
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));$rid=$discard?null:journal_id(journal_string($r,'receipt_id'));
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$rid,$discard){$d=correction_entry_draft($pdo,$uid,$id,true);workspace_revision($d,$r);$p=$d['payload'];$ids=workspace_document_ids($p['replacement']??['documents'=>[]]);if(!$discard&&!in_array($rid,$ids,true))throw new JournalProblem('Image is not attached to this draft.',404);
        foreach($ids as $doc){if(!$discard&&$doc!==$rid)continue;$image=correction_receipt($pdo,$uid,$d,$doc,true,true);$posted=$image['JournalEntryID']!==null;$table=$posted?'correction_evidence_reservations':'draft_evidence_reservations';$pdo->prepare('DELETE FROM '.$table.' WHERE receipt_id=? AND draft_id=?')->execute([$doc,$id]);if($discard&&!$posted)$pdo->prepare("UPDATE Receipts SET OCR_Status='Discarded' WHERE ReceiptID=? AND JournalEntryID IS NULL")->execute([$doc]);}
        if($p['replacement']!==null)$p['replacement']['documents']=array_values(array_filter($p['replacement']['documents'],fn($x)=>!$discard&&(int)$x['receipt_id']!==$rid));return correction_update($pdo,$uid,$d,$p,null,$discard?'Discarded':'Draft');
    });
}
/** Called by the later atomic writer after its correction and line maps exist. No public action. */
function correction_release_stale_reservations(PDO $pdo,int $winnerDraftId): int
{
    if(!$pdo->inTransaction())throw new LogicException('Stale reservation cleanup requires the correction transaction.');
    $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE draft_id=?');$s->execute([$winnerDraftId]);$c=$s->fetch();
    if(!$c||!stage3_correction_valid($pdo,$c))throw new JournalProblem('Cleanup requires a valid successful correction.',409);
    $s=$pdo->prepare("DELETE v FROM correction_evidence_reservations v JOIN journal_drafts d ON d.id=v.draft_id WHERE d.state='Draft' AND d.workflow_kind='correction' AND d.correction_target_journal_id=? AND d.id<>?");$s->execute([$c['target_journal_id'],$winnerDraftId]);return $s->rowCount();
}
function correction_upload(PDO $pdo,int $uid,array $r,array $file): array
{
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));$key=journal_string($r,'upload_key');if(!preg_match('/\A[a-f0-9]{64}\z/',$key))throw new JournalProblem('Invalid upload key.');
    $d=correction_entry_draft($pdo,$uid,$id);if($d['state']!=='Draft'||$d['payload']['replacement']===null)throw new JournalProblem('This draft cannot receive evidence.',409);correction_eligible(correction_entry_target($pdo,(int)$d['correction_target_journal_id']));
    $stored=store_uploaded_receipt($file);if(!$stored['ok'])throw new JournalProblem($stored['error']);$path=transaction_receipt_path($stored['path']);$keep=false;
    try{return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$key,$stored,&$keep){$d=correction_entry_draft($pdo,$uid,$id,true);correction_eligible(correction_entry_target($pdo,(int)$d['correction_target_journal_id'],true));$p=$d['payload'];
        $s=$pdo->prepare('SELECT r.*,v.draft_id FROM Receipts r LEFT JOIN draft_evidence_reservations v ON v.receipt_id=r.ReceiptID WHERE r.Upload_Key=?');$s->execute([$key]);if($old=$s->fetch()){if((int)$old['UploadedBy_UserID']!==$uid||(int)$old['draft_id']!==$id||!hash_equals($old['File_SHA256'],$stored['sha256']))throw new JournalProblem('Upload key conflicts with another request.',409);return workspace_draft_public($d);}
        workspace_revision($d,$r);if(count($p['replacement']['documents'])>=20)throw new JournalProblem('A draft supports up to 20 images.');if(receipt_duplicate($pdo,$stored['sha256']))throw new JournalProblem('This image is already posted. Attach the eligible target image instead.',409);
        $pdo->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,Upload_Key,UploadedBy_UserID,OCR_Status) VALUES(?,?,?,?,?,?,?,'Failed')")->execute([$stored['path'],$stored['original'],$stored['mime'],$stored['size'],$stored['sha256'],$key,$uid]);$rid=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Manual correction evidence; no extraction requested')")->execute([$rid,$uid,hash('sha256',$key.':manual'),$stored['sha256'],hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
        $pdo->prepare('INSERT INTO draft_evidence_reservations(receipt_id,draft_id,created_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$rid,$id]);$p['replacement']['documents'][]=correction_document_blank($rid);$out=correction_update($pdo,$uid,$d,$p);$keep=true;return $out;
    });}catch(Throwable $e){try{$s=$pdo->prepare('SELECT ReceiptID FROM Receipts WHERE File_Path=?');$s->execute([$stored['path']]);$keep=$s->fetchColumn()!==false;}catch(Throwable $ignored){$keep=true;}throw $e;}finally{if(!$keep&&$path)@unlink($path);}
}
/** Trusted adapter: the real owned v4 draft remains authoritative for reservations. */
function correction_resources(PDO $pdo,int $uid,array $d,array $input): array
{
    $loaded=correction_entry_draft($pdo,$uid,(int)$d['id']);if(workspace_json($loaded['payload'])!==workspace_json($d['payload']))throw new JournalProblem('Save the current correction before review.',409);
    $t=correction_entry_target($pdo,(int)$loaded['correction_target_journal_id']);correction_eligible($t);$party=null;$projects=[];$receipts=[];$accounts=[];
    $ordinary=$t['operation']===null;$settlement=$t['operation']&&$t['operation']['operation_kind']!=='release';
    $partyAllow=$ordinary?($t['journal']['party_id']??null):($settlement?$t['advance']['party_id']:null);
    if($input['party_id']!==null){$s=$pdo->prepare('SELECT * FROM parties WHERE id=? FOR UPDATE');$s->execute([$input['party_id']]);$party=$s->fetch();if(!$party||(!(int)$party['is_active']&&(int)$partyAllow!==(int)$party['id']))throw new JournalProblem('Replacement party must be active or an allowed original reference.');if(!(int)$party['is_active'])$party=json_decode($ordinary?$t['journal']['party_snapshot']:$t['advance']['party_snapshot'],true,32,JSON_THROW_ON_ERROR);}
    $allowed=[];if($ordinary){foreach($t['lines'] as $l)if($l['fund_project_id']!==null)$allowed[(int)$l['fund_project_id']]=['id'=>(int)$l['fund_project_id'],'code'=>$l['project_code_snapshot'],'name'=>$l['project_name_snapshot']];}
    // Keep the linked settlement's historical employee/project snapshots even if active.
    if($settlement){$party=json_decode($t['advance']['party_snapshot'],true,32,JSON_THROW_ON_ERROR);if($t['advance']['originating_project_id']!==null)$allowed[(int)$t['advance']['originating_project_id']]=json_decode($t['advance']['project_snapshot'],true,32,JSON_THROW_ON_ERROR);}
    $ids=array_values(array_unique(array_filter(array_column($input['lines'],'fund_project_id'),fn($v)=>$v!==null)));sort($ids,SORT_NUMERIC);
    foreach($ids as $id){$s=$pdo->prepare('SELECT * FROM projects WHERE id=? FOR UPDATE');$s->execute([$id]);$project=$s->fetch();if(!$project||(!(int)$project['is_active']&&!isset($allowed[$id])))throw new JournalProblem('Replacement project must be active or an allowed original reference.');$projects[$id]=(!$project['is_active']||($settlement&&isset($allowed[$id])))?$allowed[$id]:$project;}
    $ids=array_map('intval',array_column($input['documents'],'receipt_id'));sort($ids,SORT_NUMERIC);$hashes=[];
    foreach($ids as $id){$r=correction_receipt($pdo,$uid,$d,$id,true);if(isset($hashes[$r['File_SHA256']]))throw new JournalProblem('Identical evidence cannot be attached twice.',409);if($r['JournalEntryID']===null&&receipt_duplicate($pdo,$r['File_SHA256']))throw new JournalProblem('An unposted image duplicates posted evidence.',409);$hashes[$r['File_SHA256']]=true;$receipts[$id]=$r;}
    foreach($input['documents'] as $doc){$prior=$receipts[(int)$doc['receipt_id']]['source_association']??null;if(!$prior)continue;$changed=$prior['purpose']!==$doc['purpose'];foreach(['declared_amount','accepted_amount'] as $field){$old=$prior[$field]??'';$new=$doc[$field];if($old!==''&&$new!==''?$old!==$new:$old!==$new)$changed=true;}if($changed&&($doc['re_review_reason']??'')==='')throw new JournalProblem('Explain changes to a reused image’s purpose or declared/accepted amount.');}
    $replacementIds=array_column($input['lines'],'account_id');$ids=array_values(array_unique(array_merge($replacementIds,array_column($t['lines'],'account_id'))));sort($ids,SORT_NUMERIC);foreach($ids as $id){$a=account_load($pdo,$id,true);if(!in_array($id,$replacementIds,true)){if(!$a)throw new JournalProblem('Original account reference is missing.',409);continue;}if(!$a||!(int)$a['Is_Active']||!in_array($a['Account_Type'],JOURNAL_ACCOUNT_TYPES,true)||!in_array($a['Normal_Balance'],['Debit','Credit'],true)||((int)$a['Is_Cash_Account']===1&&$a['Account_Type']!=='Asset'))throw new JournalProblem('Replacement accounts must be active and eligible.');$accounts[$id]=$a;}
    return compact('party','projects','receipts','accounts');
}
function correction_canonical(PDO $pdo,array $d,array $t): ?array
{
    if($d['correction_mode']==='reverse_only')return null;$p=$d['payload']['replacement'];
    if(!$t['operation']){$input=workspace_canonical($d,$p);}
    else{$adapter=$d;$adapter['payload_version']=3;$adapter['workflow_kind']='advance_'.$t['operation']['operation_kind'];$adapter['payload']=$p;$input=advance_canonical($pdo,$adapter,$p,$t['operation']['operation_kind']==='release'?null:$t['advance']);}
    foreach($input['documents'] as $i=>&$doc)$doc['re_review_reason']=$p['documents'][$i]['re_review_reason'];unset($doc);return $input;
}
function correction_proof_context(array $d,array $input,array $resources): array
{
    if($input['transaction_kind']!=='advance_return'||!$input['documents'])throw new JournalProblem('Return replacement requires reviewed proof.');$proof=[];
    foreach($input['documents'] as $doc){if($doc['purpose']!=='supporting')throw new JournalProblem('Return proof must be informational.');$proof[]=$doc+['sha256'=>$resources['receipts'][(int)$doc['receipt_id']]['File_SHA256']];}
    return ['draft_id'=>(int)$d['id'],'target_journal_id'=>(int)$d['correction_target_journal_id'],'mode'=>$d['correction_mode'],'workflow_kind'=>'advance_return','advance_id'=>(int)$d['advance_id'],'amount'=>$input['advance_context']['reduction'],'cash_account_id'=>$input['lines'][0]['account_id'],'entry_date'=>$input['entry_date'],'proof'=>$proof];
}
function correction_confirm(PDO $pdo,int $uid,array $r): array
{
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id){$d=correction_entry_draft($pdo,$uid,$id,true);workspace_revision($d,$r);$t=correction_entry_target($pdo,(int)$d['correction_target_journal_id'],true);correction_eligible($t);correction_dates($d,$t);$input=correction_canonical($pdo,$d,$t);if(!$input)throw new JournalProblem('Reversal-only drafts have no return confirmation.');$resources=correction_resources($pdo,$uid,$d,$input);$context=correction_proof_context($d,$input,$resources);$confirmation=['context'=>$context,'fingerprint'=>hash('sha256',workspace_json($context)),'confirmed_by'=>$uid,'confirmed_at'=>gmdate('Y-m-d H:i:s')];workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Correction Return Proof',$id,null,$confirmation);return correction_update($pdo,$uid,$d,$d['payload'],workspace_json($confirmation));});
}
function correction_dates(array $d,array $t): void
{
    $p=$d['payload'];advance_date($p['entry_date']);if($p['entry_date']<$t['journal']['entry_date'])throw new JournalProblem('Correction date cannot precede the target accounting date.');if(trim($p['reason'])==='')throw new JournalProblem('Enter the correction reason.');if($p['entry_date']<journal_today()&&trim($p['backdate_reason'])==='')throw new JournalProblem('Explain the earlier correction date.');
}
function correction_reversal(array $t,string $date): array
{
    $lines=[];foreach($t['lines'] as $l){$r=$l;$r['debit_amount']=$l['credit_amount'];$r['credit_amount']=$l['debit_amount'];$r['project_name']=$l['project_name_snapshot']??'Organization operations';$lines[]=$r;}
    return ['source_book'=>'GJ','entry_date'=>$date,'description'=>'Reversal of journal #'.$t['journal']['id'],'lines'=>$lines];
}
function correction_prepare_review(PDO $pdo,int $uid,array $d,array $t,string $version): array
{
    correction_dates($d,$t);$input=correction_canonical($pdo,$d,$t);$prepared=null;
    if($input){if(!$t['operation'])$prepared=workspace_prepare($pdo,$uid,$d,$input,$version);
        else{$adapter=$d;$adapter['payload_version']=3;$adapter['workflow_kind']='advance_'.$t['operation']['operation_kind'];$adapter['payload']=$d['payload']['replacement'];$prepared=advance_prepare($pdo,$uid,$adapter,$input,$version,$t['operation']['operation_kind']==='release'?null:$t['advance'],$d);}}
    $lines=[];if($prepared)foreach($input['lines'] as $l)$lines[]=$l+['account_name'=>$prepared['accounts'][$l['account_id']]['Name'],'project_name'=>$l['fund_project_id']===null?'Organization operations':$prepared['projects'][$l['fund_project_id']]['name']];
    $timeline=$t['operation']?($prepared['timeline']??advance_lifecycle_candidate($pdo,$d,$input,$t)):null;
    $reversal=correction_reversal($t,$d['payload']['entry_date']);$fingerprint=hash('sha256',workspace_json([$version,$d['revision'],$d['payload'],$t['journal'],$t['lines'],$prepared['fingerprint']??null,$timeline]));
    return compact('input','prepared','lines','reversal','fingerprint','timeline');
}
function correction_review(PDO $pdo,int $uid,array $r): array
{
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));
    return workspace_tx($pdo,$uid,function($version)use($pdo,$uid,$r,$id){$d=correction_entry_draft($pdo,$uid,$id,true);workspace_revision($d,$r);$t=correction_entry_target($pdo,(int)$d['correction_target_journal_id'],true);correction_eligible($t);
        $review=correction_prepare_review($pdo,$uid,$d,$t,$version);extract($review);
        return ['input'=>$input??['entry_date'=>$d['payload']['entry_date'],'description'=>$d['payload']['reason']],'lines'=>$lines,'party'=>$prepared['party']??null,'coverage'=>$prepared['coverage']??['status'=>'Reversal / original evidence reference','debit'=>['eligible'=>'0.00','covered'=>'0.00'],'credit'=>['eligible'=>'0.00','covered'=>'0.00']],'original'=>$t['lines'],'reversal'=>$reversal,'mode'=>$d['correction_mode'],'reason'=>$d['payload']['reason'],'token'=>workspace_token($d,$fingerprint,time()+900),'posting_available'=>true,'advance_effect'=>$timeline,'original_advance_id'=>$t['advance']===null?null:(int)$t['advance']['id'],'notice'=>'Posting commits the exact reversal and optional replacement together. The original remains unchanged.','net_explanation'=>$input?'Original and GJ reversal cancel at the correction date; the replacement is the corrected ledger effect. Cash books retain gross original/replacement activity; their totals alone are not the corrected net amount.':'Original and GJ reversal cancel to zero at the correction date. This does not record an actual return of money.'];
    });
}
