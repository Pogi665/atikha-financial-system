<?php
/** Ordinary and dedicated advance correction bundles. The caller owns one coordinated transaction. */
require_once __DIR__.'/correction_drafts.php';

function correction_request(PDO $pdo,array $d,array $payload): array
{
    // Recovery is independent of current master eligibility, files and session review tokens.
    $operation=null;$original=null;
    if($d['advance_id']!==null){$q=$pdo->prepare('SELECT * FROM cash_advance_operations WHERE journal_id=?');$q->execute([$d['correction_target_journal_id']]);$operation=$q->fetch();if(!$operation||(int)$operation['advance_id']!==(int)$d['advance_id'])throw new JournalProblem('Correction advance context is inconsistent.',409);$original=advance_load($pdo,(int)$operation['advance_id']);}
    $p=correction_payload($payload,['operation'=>$operation],$d['correction_mode']);
    $copy=$d;$copy['payload']=$p;
    $input=$operation?correction_canonical($pdo,$copy,['operation'=>$operation,'advance'=>$original]):correction_canonical_payload($copy);
    $request=['target_journal_id'=>(int)$d['correction_target_journal_id'],'mode'=>$d['correction_mode'],
        'source_book'=>$d['source_book'],'entry_date'=>$p['entry_date'],'reason'=>$p['reason'],
        'backdate_reason'=>$p['backdate_reason'],'replacement'=>$input];
    if($operation)$request+=['workflow_kind'=>'advance_'.$operation['operation_kind'],'original_advance_id'=>(int)$d['advance_id'],'advance_payload'=>$p['replacement']];
    return $request;
}
function correction_canonical_payload(array $d): ?array
{
    if($d['correction_mode']==='reverse_only')return null;
    $input=workspace_canonical($d,$d['payload']['replacement']);
    foreach($input['documents'] as $i=>&$doc)$doc['re_review_reason']=$d['payload']['replacement']['documents'][$i]['re_review_reason'];
    unset($doc);return $input;
}
function correction_result(PDO $pdo,array $c,bool $duplicate): array
{
    return ['id'=>(int)($c['replacement_journal_id']??$c['reversal_journal_id']),'correction_id'=>(int)$c['id'],
        'target_journal_id'=>(int)$c['target_journal_id'],'reversal_journal_id'=>(int)$c['reversal_journal_id'],
        'replacement_journal_id'=>$c['replacement_journal_id']===null?null:(int)$c['replacement_journal_id'],'duplicate'=>$duplicate]+advance_correction_identity($pdo,$c);
}
function correction_insert_journal(PDO $pdo,int $uid,array $header,array $lines,string $key,string $hash,?string $partySnapshot): array
{
    if(!$pdo->inTransaction())throw new LogicException('A correction journal requires an owned transaction.');
    $s=$pdo->prepare("INSERT INTO journal_entries(entry_date,reference,description,status,submission_key,submission_hash,posted_by_user_id,source_book,transaction_kind,party_id,party_snapshot,created_at,updated_at) VALUES(?,?,?,'posted',?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $s->execute([$header['entry_date'],$header['reference'],$header['description'],$key,$hash,$uid,$header['source_book'],$header['transaction_kind'],$header['party_id'],$partySnapshot]);
    $id=(int)$pdo->lastInsertId();$ids=[];
    $s=$pdo->prepare('INSERT INTO journal_entry_lines(journal_entry_id,account_id,debit_amount,credit_amount,fund_project_id,project_code_snapshot,project_name_snapshot) VALUES(?,?,?,?,?,?,?)');
    foreach($lines as $l){$s->execute([$id,$l['account_id'],$l['debit_amount'],$l['credit_amount'],$l['fund_project_id'],$l['project_code_snapshot'],$l['project_name_snapshot']]);$ids[$l['client_id']]=(int)$pdo->lastInsertId();}
    return ['id'=>$id,'line_ids'=>$ids];
}
function correction_post(PDO $pdo,int $uid,array $r): array
{
    correction_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));$key=journal_string($r,'submission_key');
    if(!is_array($r['payload']??null))throw new JournalProblem('Correction payload must be an object.');
    return workspace_tx($pdo,$uid,function($version)use($pdo,$uid,$id,$key,$r){
        $d=correction_entry_draft($pdo,$uid,$id,true);
        if(!hash_equals($d['submission_key'],$key))throw new JournalProblem('Draft submission key does not match.',409);
        // Persisted operation identity, never a submitted flag, selects the allowed writer.
        if($d['state']!=='Posted'){$t=correction_entry_target($pdo,(int)$d['correction_target_journal_id'],true);correction_eligible($t);}
        $request=correction_request($pdo,$d,$r['payload']);$hash=hash('sha256',workspace_json($request));
        if($d['state']==='Posted'){
            $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE draft_id=? AND submission_key=?');$s->execute([$id,$key]);$c=$s->fetch();
            if(!$c||!hash_equals($c['request_hash'],$hash)||!stage3_correction_valid($pdo,$c))throw new JournalProblem('Posted correction content conflicts with this request.',409);
            if($d['advance_id']!==null)advance_reconciled(advance_state($pdo,journal_today()));
            return correction_result($pdo,$c,true);
        }
        workspace_revision($d,$r);
        if(workspace_json($request)!==workspace_json(correction_request($pdo,$d,$d['payload'])))throw new JournalProblem('Save and review your latest changes before posting.',409);
        $t=correction_entry_target($pdo,(int)$d['correction_target_journal_id'],true);correction_eligible($t);
        $comparison=correction_prepare_review($pdo,$uid,$d,$t,$version);$prepared=$comparison['prepared'];$input=$comparison['input'];
        $token=journal_string($r,'review_token');
        if(!preg_match('/\A([0-9]{10})\.([a-f0-9]{64})\z/',$token,$m)||!isset($_SESSION['workspace_review_secret'])||time()>(int)$m[1]||!hash_equals(workspace_token($d,$comparison['fingerprint'],(int)$m[1]),$token))throw new JournalProblem('Review expired or accounting data changed. Review the correction again.',409);
        // Exact reversals retain inactive references. Only replacements use active eligible accounts.
        $accounts=array_values(array_unique(array_column($t['lines'],'account_id')));sort($accounts,SORT_NUMERIC);
        foreach($accounts as $account)if(!account_load($pdo,(int)$account,true))throw new JournalProblem('Original account reference is missing.',409);
        $reverse=$comparison['reversal'];$reverseLines=[];
        foreach($reverse['lines'] as $l)$reverseLines[]=$l+['client_id'=>'original_'.$l['id']];
        $reverseHeader=['entry_date'=>$d['payload']['entry_date'],'reference'=>'REV-'.$t['journal']['id'],'description'=>$reverse['description'],'source_book'=>'GJ','transaction_kind'=>'correction_reversal','party_id'=>$t['journal']['party_id']];
        $v=correction_insert_journal($pdo,$uid,$reverseHeader,$reverseLines,hash('sha256',$key.':reversal'),hash('sha256',workspace_json([$reverseHeader,$reverseLines])),$t['journal']['party_snapshot']);
        $n=null;
        if($input){$lines=[];foreach($input['lines'] as $l){$pr=$l['fund_project_id']===null?null:$prepared['projects'][$l['fund_project_id']];$lines[]=$l+['project_code_snapshot'=>$pr['code']??null,'project_name_snapshot'=>$pr['name']??null];}
            $snapshot=$prepared['party']===null?null:workspace_json(array_intersect_key($prepared['party'],array_flip(['id','code','name','party_type'])));
            $n=correction_insert_journal($pdo,$uid,$input,$lines,hash('sha256',$key.':replacement'),hash('sha256',workspace_json($input)),$snapshot);
        }
        $replacementAdvanceId=null;
        if($t['operation']&&$input)$replacementAdvanceId=advance_write_operation($pdo,$uid,$d,$input,$prepared,$n,$t['operation']['operation_kind']==='release'?null:(int)$t['advance']['id'],false);
        $identity=$t['operation']?['original_advance_id'=>(int)$t['advance']['id'],'replacement_advance_id'=>$t['operation']['operation_kind']==='release'?$replacementAdvanceId:null]:[];
        $snapshot=['request'=>$request,'original'=>$t['journal'],'original_lines'=>$t['lines'],'reversal'=>$reverse,'replacement'=>$input,'coverage'=>$prepared['coverage']??null,'reviewed_by'=>$uid,'reviewed_at'=>gmdate('Y-m-d H:i:s'),'fingerprint'=>$comparison['fingerprint'],'advance_effect'=>$comparison['timeline']]+$identity;
        $s=$pdo->prepare('INSERT INTO journal_corrections(target_journal_id,reversal_journal_id,replacement_journal_id,root_journal_id,parent_correction_id,draft_id,submission_key,mode,accounting_date,reason,backdate_reason,request_hash,review_snapshot,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $s->execute([$t['journal']['id'],$v['id'],$n['id']??null,$t['root_journal_id'],$t['parent_correction_id'],$id,$key,$d['correction_mode'],$d['payload']['entry_date'],$d['payload']['reason'],$d['payload']['backdate_reason'],$hash,workspace_json($snapshot),$uid]);$cid=(int)$pdo->lastInsertId();
        $s=$pdo->prepare('INSERT INTO journal_correction_lines(correction_id,original_line_id,reversal_line_id) VALUES(?,?,?)');
        foreach($t['lines'] as $l)$s->execute([$cid,$l['id'],$v['line_ids']['original_'.$l['id']]]);
        if($t['operation']){
            $op=$t['operation'];$pdo->prepare('INSERT INTO cash_advance_operation_reversals(original_operation_id,correction_id,reversal_journal_id,control_line_id) VALUES(?,?,?,?)')->execute([$op['id'],$cid,$v['id'],$v['line_ids']['original_'.$op['control_line_id']]]);
            $pdo->prepare('UPDATE cash_advances SET revision=revision+1 WHERE id=?')->execute([$t['advance']['id']]);
        }
        if($input)foreach($input['documents'] as $doc){
            $receipt=$prepared['receipts'][(int)$doc['receipt_id']];$source=$receipt['source_association']['id']??null;
            if($source===null){$s=$pdo->prepare('UPDATE Receipts SET JournalEntryID=?,Posted_File_SHA256=File_SHA256 WHERE ReceiptID=? AND JournalEntryID IS NULL');$s->execute([$n['id'],$doc['receipt_id']]);if($s->rowCount()!==1)throw new JournalProblem('Evidence was posted by another request.',409);}
            $amount=$doc['purpose']==='amount';
            $s=$pdo->prepare('INSERT INTO posted_evidence_associations(journal_id,receipt_id,purpose,support_side,declared_amount,accepted_amount,exclusion_reason,reviewed_by,reviewed_at,review_snapshot,source_association_id,correction_id) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?,?,?)');
            $s->execute([$n['id'],$doc['receipt_id'],$doc['purpose'],$amount?$doc['support_side']:null,$amount?$doc['declared_amount']:null,$amount?$doc['accepted_amount']:null,$doc['exclusion_reason'],$uid,workspace_json($doc+['sha256'=>$receipt['File_SHA256'],'attempt_version'=>$receipt['attempt_version'],'coverage'=>$prepared['coverage']]),$source,$cid]);$association=(int)$pdo->lastInsertId();
            $s=$pdo->prepare('INSERT INTO evidence_allocations(association_id,line_id,amount) VALUES(?,?,?)');foreach($doc['allocations'] as $a)$s->execute([$association,$n['line_ids'][$a['client_id']],$a['amount']]);
        }
        $pdo->prepare('DELETE FROM draft_evidence_reservations WHERE draft_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM correction_evidence_reservations WHERE draft_id=?')->execute([$id]);
        $pdo->prepare("UPDATE journal_drafts SET state='Posted',posted_journal_id=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$n['id']??$v['id'],$id]);
        $released=correction_release_stale_reservations($pdo,$id);
        $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE id=?');$s->execute([$cid]);$c=$s->fetch();
        if(!stage3_correction_valid($pdo,$c))throw new JournalProblem('Correction bundle integrity failed.',409);
        workspace_audit($pdo,$uid,AUDIT_ACTION_CREATE,'Journal Correction',$cid,null,$snapshot+['reversal_journal_id'=>$v['id'],'replacement_journal_id'=>$n['id']??null,'stale_reuse_reservations_released'=>$released]);
        workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Correction Draft',$id,['state'=>'Draft'],['state'=>'Posted','correction_id'=>$cid]);
        if($t['operation'])advance_reconciled(advance_state($pdo,journal_today()));
        stage1_write_changed($pdo);return correction_result($pdo,$c,false);
    });
}
