<?php
/** Flag-independent posted correction readers and integrity inspection. */
require_once __DIR__.'/accounting_workspace.php';
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/cash_advance_lifecycle.php';

function correction_reader_guard(PDO $pdo,int $uid): string
{
    if($uid<=0||(int)($_SESSION['UserID']??0)!==$uid)throw new JournalProblem('Please sign in again.',401);
    $s=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=?');$s->execute([$uid]);$u=$s->fetch();
    if(!$u||(int)$u['Is_Active']!==1||!in_array($u['Role'],['Admin','Management'],true))throw new JournalProblem('Access restricted.',403);
    if(!stage3_schema($pdo))throw new JournalProblem('Correction schema is unavailable or incomplete.',503);
    return $u['Role'];
}
/** Financial chain summary only: never include drafts, hashes, review/approval snapshots or evidence. */
function correction_chain(PDO $pdo,int $uid,int $journalId): array
{
    correction_reader_guard($pdo,$uid);
    return accounting_read($pdo,function()use($pdo,$journalId){
        $s=$pdo->prepare("SELECT id FROM journal_entries WHERE id=? AND status='posted'");$s->execute([$journalId]);if(!$s->fetchColumn())throw new JournalProblem('Posted journal not found.',404);
        $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE target_journal_id=? OR reversal_journal_id=? OR replacement_journal_id=?');$s->execute([$journalId,$journalId,$journalId]);$c=$s->fetch();if(!$c)return [];
        $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE root_journal_id=? ORDER BY accounting_date,id');$s->execute([$c['root_journal_id']]);$rows=$s->fetchAll();$out=[];
        foreach($rows as $row){if(!stage3_correction_valid($pdo,$row))throw new JournalProblem('Correction chain integrity failed.',409);
            $out[]=['id'=>(int)$row['id'],'target_journal_id'=>(int)$row['target_journal_id'],'reversal_journal_id'=>(int)$row['reversal_journal_id'],
                'replacement_journal_id'=>$row['replacement_journal_id']===null?null:(int)$row['replacement_journal_id'],
                'root_journal_id'=>(int)$row['root_journal_id'],'parent_correction_id'=>$row['parent_correction_id']===null?null:(int)$row['parent_correction_id'],
                'mode'=>$row['mode'],'accounting_date'=>$row['accounting_date'],'reason'=>$row['reason'],'created_at'=>$row['created_at']];
        }return $out;
    });
}

/** Public financial metadata only. Never return review snapshots, receipt hashes or draft owners. */
function correction_metadata(PDO $pdo,array $journalIds): array
{
    if(!$journalIds||!stage3_schema($pdo))return [];$wanted=array_fill_keys(array_map('intval',$journalIds),true);$out=[];
    foreach($pdo->query('SELECT * FROM journal_corrections ORDER BY id') as $c){
        foreach(['target_journal_id'=>'Corrected original','reversal_journal_id'=>'Generated GJ reversal','replacement_journal_id'=>'Replacement'] as $key=>$role){
            if($c[$key]===null||!isset($wanted[(int)$c[$key]]))continue;
            if(!stage3_correction_valid($pdo,$c))throw new JournalProblem('Correction chain integrity failed.',409);
            $out[(int)$c[$key]][]=['id'=>(int)$c['id'],'role'=>$role,'root_journal_id'=>(int)$c['root_journal_id'],
                'parent_correction_id'=>$c['parent_correction_id']===null?null:(int)$c['parent_correction_id'],
                'target_journal_id'=>(int)$c['target_journal_id'],'reversal_journal_id'=>(int)$c['reversal_journal_id'],
                'replacement_journal_id'=>$c['replacement_journal_id']===null?null:(int)$c['replacement_journal_id'],
                'accounting_date'=>$c['accounting_date'],'created_at'=>$c['created_at'],'reason'=>$c['reason']];
        }
    }return $out;
}
function correction_posted_detail(PDO $pdo,int $uid,int $journalId): array
{
    correction_reader_guard($pdo,$uid);
    return accounting_read($pdo,function()use($pdo,$uid,$journalId){
        $chain=correction_chain($pdo,$uid,$journalId);if(!$chain)throw new JournalProblem('Posted correction not found.',404);
        $ids=[];foreach($chain as $c)$ids=array_merge($ids,array_filter([$c['target_journal_id'],$c['reversal_journal_id'],$c['replacement_journal_id']],fn($v)=>$v!==null));
        // Existing reader computes coverage before applying association-aware privacy redaction.
        $records=accounting_records($pdo,accounting_records_filters(['from'=>'','to'=>'']));$journals=[];
        foreach(array_unique($ids) as $id){if(!isset($records['journals'][$id]))throw new JournalProblem('Correction journal is unavailable.',409);$journals[$id]=$records['journals'][$id];}
        return ['chain'=>$chain,'journals'=>$journals];
    });
}

/** All posted designated lines must be linked, even if offsetting errors net to zero. */
function correction_control_integrity(PDO $pdo): array
{
    $m=advance_lifecycle_model($pdo);$state=advance_lifecycle_state($pdo,'9998-12-31',$m);$problems=$m['problems'];
    foreach($state['reconciliation'] as $r)if(!$r['ok'])$problems[]='Control account #'.$r['account_id'].' ledger/register mismatch';
    return array_values(array_unique($problems));
}

/** Database-only inspection; file-byte restoration/verification remains separate. */
function correction_integrity(PDO $pdo): array
{
    $problems=[];$state=stage3_schema_state($pdo);
    if($state['state']==='partial')return array_merge(['Partial 021: stop and inspect/recover; never rerun blindly.'],$state['problems']);
    if(accounting_integrity($pdo,'9998-12-31'))$problems[]='Posted journal balance/line integrity failed';
    if((int)$pdo->query('SELECT COUNT(*) FROM Receipts WHERE JournalEntryID IS NOT NULL AND (File_SHA256 IS NULL OR Posted_File_SHA256 IS NULL OR File_SHA256<>Posted_File_SHA256)')->fetchColumn())$problems[]='Posted evidence hashes are inconsistent';
    $associations=$pdo->query('SELECT * FROM posted_evidence_associations ORDER BY id')->fetchAll();
    foreach($associations as $a){if(!stage3_evidence_association_valid($pdo,$a))$problems[]='Invalid evidence association #'.$a['id'];
        $s=$pdo->prepare('SELECT x.amount,l.* FROM evidence_allocations x JOIN journal_entry_lines l ON l.id=x.line_id WHERE x.association_id=?');$s->execute([$a['id']]);$sum=0;$allocations=$s->fetchAll();
        foreach($allocations as $l){$amount=accounting_cents($l['amount']);$sum=accounting_add($sum,$amount);
            if((int)$l['journal_entry_id']!==(int)$a['journal_id']||$amount<=0||$a['purpose']!=='amount'||$amount>accounting_cents($l[($a['support_side']??'debit').'_amount']))$problems[]='Invalid allocation for association #'.$a['id'];
        }
        if($a['purpose']==='amount'&&($sum!==accounting_cents($a['accepted_amount'])||!$allocations))$problems[]='Accepted amount/allocation mismatch #'.$a['id'];
    }
    if((int)$pdo->query("SELECT COUNT(*) FROM draft_evidence_reservations v JOIN Receipts r ON r.ReceiptID=v.receipt_id JOIN journal_drafts d ON d.id=v.draft_id WHERE d.state<>'Draft' OR r.JournalEntryID IS NOT NULL OR r.UploadedBy_UserID<>d.owner_id OR r.OCR_Status='Discarded'")->fetchColumn())$problems[]='Unposted evidence reservation is inconsistent';
    if($state['complete']){
        $corrections=$pdo->query('SELECT * FROM journal_corrections ORDER BY id')->fetchAll();
        foreach($corrections as $c)if(!stage3_correction_valid($pdo,$c))$problems[]='Invalid correction #'.$c['id'];
        if((int)$pdo->query("SELECT COUNT(*) FROM journal_entries j WHERE j.transaction_kind='correction_reversal' AND NOT EXISTS(SELECT 1 FROM journal_corrections c WHERE c.reversal_journal_id=j.id)")->fetchColumn())$problems[]='Unlinked generated reversal journal';
        foreach($pdo->query("SELECT d.* FROM journal_drafts d WHERE d.workflow_kind='correction' OR d.payload_version=4") as $d){
            $s=$pdo->prepare('SELECT * FROM journal_entries WHERE id=?');$s->execute([$d['correction_target_journal_id']]);$target=$s->fetch();
            $s=$pdo->prepare('SELECT advance_id FROM cash_advance_operations WHERE journal_id=?');$s->execute([$d['correction_target_journal_id']]);$advance=$s->fetchColumn();
            if(!$target||$target['status']!=='posted'||$target['transaction_kind']==='correction_reversal'||(int)$d['payload_version']!==4||$d['workflow_kind']!=='correction'||($advance===false?$d['advance_id']!==null:(int)$d['advance_id']!==(int)$advance))$problems[]='Invalid correction draft context #'.$d['id'];
            $s=$pdo->prepare('SELECT COUNT(*) FROM journal_corrections WHERE draft_id=?');$s->execute([$d['id']]);if(($d['state']==='Posted')!==((int)$s->fetchColumn()===1))$problems[]='Correction draft/post link mismatch #'.$d['id'];
            if($d['state']==='Draft'){
                $payload=json_decode($d['payload'],true,64,JSON_THROW_ON_ERROR);$documents=$payload['replacement']['documents']??[];
                foreach($documents as $doc){
                    $receipt=(int)($doc['receipt_id']??0);$s=$pdo->prepare('SELECT * FROM Receipts WHERE ReceiptID=?');$s->execute([$receipt]);$image=$s->fetch();$valid=(bool)$image;
                    if($image&&$image['JournalEntryID']!==null){
                        $s=$pdo->prepare('SELECT * FROM posted_evidence_associations WHERE journal_id=? AND receipt_id=?');$s->execute([$d['correction_target_journal_id'],$receipt]);$source=$s->fetch();
                        $s=$pdo->prepare('SELECT * FROM correction_evidence_reservations WHERE receipt_id=?');$s->execute([$receipt]);$reservation=$s->fetch();
                        $valid=$source&&stage3_evidence_association_valid($pdo,$source);
                        if($reservation)$valid=$valid&&(int)$reservation['draft_id']===(int)$d['id']&&(int)$reservation['source_association_id']===(int)$source['id'];
                        else{$s=$pdo->prepare('SELECT * FROM journal_corrections WHERE target_journal_id=?');$s->execute([$d['correction_target_journal_id']]);$winner=$s->fetch();$valid=$valid&&$winner&&(int)$winner['draft_id']!==(int)$d['id']&&stage3_correction_valid($pdo,$winner);}
                    }elseif($image){$s=$pdo->prepare('SELECT draft_id FROM draft_evidence_reservations WHERE receipt_id=?');$s->execute([$receipt]);$valid=(int)$s->fetchColumn()===(int)$d['id']&&(int)$image['UploadedBy_UserID']===(int)$d['owner_id'];}
                    if(!$valid)$problems[]='Missing or invalid correction draft document reservation #'.$d['id'].'/'.$receipt;
                }
            }
        }
        foreach($pdo->query('SELECT v.*,d.state,d.workflow_kind,d.payload_version,d.correction_target_journal_id,d.correction_mode FROM correction_evidence_reservations v JOIN journal_drafts d ON d.id=v.draft_id') as $v){
            $s=$pdo->prepare('SELECT * FROM posted_evidence_associations WHERE id=?');$s->execute([$v['source_association_id']]);$a=$s->fetch();
            $s=$pdo->prepare('SELECT COUNT(*) FROM journal_corrections WHERE target_journal_id=?');$s->execute([$v['correction_target_journal_id']]);
            if($v['state']!=='Draft'||$v['workflow_kind']!=='correction'||(int)$v['payload_version']!==4||$v['correction_mode']!=='reverse_replace'||!$a||(int)$a['receipt_id']!==(int)$v['receipt_id']||(int)$a['journal_id']!==(int)$v['correction_target_journal_id']||!stage3_evidence_association_valid($pdo,$a)||(int)$s->fetchColumn())$problems[]='Invalid or stale correction evidence reservation #'.$v['receipt_id'];
        }
    }
    return array_values(array_unique(array_merge($problems,correction_control_integrity($pdo))));
}
