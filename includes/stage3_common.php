<?php
/** Read-only Stage 3 schema/provenance primitives. Never depend on a UI flag. */
require_once __DIR__.'/accounting_query.php';
const STAGE3_TABLES = ['journal_corrections','journal_correction_lines','cash_advance_operation_reversals','correction_evidence_reservations'];
const STAGE3_TRIGGERS = ['s3_corrections_no_update','s3_corrections_no_delete','s3_mapping_no_update','s3_mapping_no_delete','s3_operations_no_update','s3_operations_no_delete','s3_journal_no_update','s3_journal_no_delete','s3_line_no_update','s3_line_no_delete'];
const STAGE3_FOREIGN_KEYS = ['fk_s3_target','fk_s3_reversal','fk_s3_replacement','fk_s3_root','fk_s3_parent','fk_s3_draft','fk_s3_actor','fk_s3_mapping_correction','fk_s3_mapping_original','fk_s3_mapping_reversal','fk_s3_op_original','fk_s3_op_correction','fk_s3_op_journal','fk_s3_op_line','fk_s3_reservation_receipt','fk_s3_reservation_draft','fk_s3_reservation_source','fk_s3_draft_target','fk_s3_evidence_source','fk_s3_evidence_correction'];
function stage3_table(PDO $pdo,string $table): bool
{
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (bool)$s->fetchColumn();
}
function stage3_schema_state(PDO $pdo): array
{
    $tables=$pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('".implode("','",STAGE3_TABLES)."')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $cols=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='journal_drafts' AND COLUMN_NAME IN ('correction_target_journal_id','correction_mode','workflow_kind')) OR (TABLE_NAME='posted_evidence_associations' AND COLUMN_NAME IN ('source_association_id','correction_id')))")->fetchAll();
    $triggerRows=$pdo->query("SELECT TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 's3_%'")->fetchAll();$triggers=array_column($triggerRows,'TRIGGER_NAME');
    $columns=[];$present=count($tables)+count($triggers);
    foreach($cols as $c){$columns[$c['TABLE_NAME'].'.'.$c['COLUMN_NAME']]=$c['COLUMN_TYPE'];if($c['COLUMN_NAME']!=='workflow_kind')$present++;elseif(str_contains($c['COLUMN_TYPE'],"'correction'"))$present++;}
    if(!$present)return ['state'=>'absent','complete'=>false,'problems'=>[]];
    $problems=[];
    foreach(STAGE3_TABLES as $t)if(($tables[$t]??null)!=='InnoDB')$problems[]='Missing or non-InnoDB table '.$t;
    $newColumns=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('".implode("','",STAGE3_TABLES)."')")->fetchAll();$names=[];
    foreach($newColumns as $c)$names[$c['TABLE_NAME']][]=$c['COLUMN_NAME'];
    foreach(['journal_corrections'=>['id','target_journal_id','reversal_journal_id','replacement_journal_id','root_journal_id','parent_correction_id','draft_id','submission_key','mode','accounting_date','reason','backdate_reason','request_hash','review_snapshot','created_by','created_at'],'journal_correction_lines'=>['correction_id','original_line_id','reversal_line_id'],'cash_advance_operation_reversals'=>['id','original_operation_id','correction_id','reversal_journal_id','control_line_id'],'correction_evidence_reservations'=>['receipt_id','draft_id','source_association_id','created_at']] as $t=>$fields)foreach($fields as $field)if(!in_array($field,$names[$t]??[],true))$problems[]='Missing column '.$t.'.'.$field;
    foreach(['journal_drafts.correction_target_journal_id'=>'int(10) unsigned','journal_drafts.correction_mode'=>"enum('reverse_only','reverse_replace')",'posted_evidence_associations.source_association_id'=>'int(10) unsigned','posted_evidence_associations.correction_id'=>'int(10) unsigned'] as $key=>$type){
        // Integer display widths differ between supported MariaDB versions.
        $actual=$columns[$key]??'';$ok=str_starts_with($type,'int')?(bool)preg_match('/\Aint(?:\(\d+\))? unsigned\z/',$actual):$actual===$type;
        if(!$ok)$problems[]='Missing or incompatible column '.$key;
    }
    if(($columns['journal_drafts.workflow_kind']??'')!=="enum('ordinary','advance_release','advance_liquidation','advance_return','correction')")$problems[]='Incompatible draft workflow enum';
    foreach(STAGE3_TRIGGERS as $t)if(!in_array($t,$triggers,true))$problems[]='Missing trigger '.$t;
    foreach($triggerRows as $t){if(!in_array($t['TRIGGER_NAME'],STAGE3_TRIGGERS,true))continue;
        $name=$t['TRIGGER_NAME'];$table=str_starts_with($name,'s3_corrections')?'journal_corrections':(str_starts_with($name,'s3_mapping')?'journal_correction_lines':(str_starts_with($name,'s3_operations')?'cash_advance_operation_reversals':(str_starts_with($name,'s3_journal')?'journal_entries':'journal_entry_lines')));
        if($t['EVENT_OBJECT_TABLE']!==$table||$t['ACTION_TIMING']!=='BEFORE'||$t['EVENT_MANIPULATION']!==(str_ends_with($name,'update')?'UPDATE':'DELETE')||!preg_match('/SIGNAL\s+SQLSTATE\s+[\x27\x22]45000[\x27\x22]/i',$t['ACTION_STATEMENT']))$problems[]='Incompatible immutable trigger '.$name;
    }
    $fks=$pdo->query("SELECT CONSTRAINT_NAME,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'fk_s3_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach(STAGE3_FOREIGN_KEYS as $k)if(($fks[$k]??null)!=='RESTRICT')$problems[]='Missing restrictive foreign key '.$k;
    $checks=$pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'chk_s3_%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['chk_s3_mode','chk_s3_distinct','chk_s3_review','chk_s3_reason','chk_s3_mapping_distinct','chk_s3_draft_context','chk_s3_evidence_source'] as $c)if(!in_array($c,$checks,true))$problems[]='Missing check '.$c;
    $indexes=$pdo->query("SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0 AND TABLE_NAME IN ('journal_corrections','journal_correction_lines','cash_advance_operation_reversals','correction_evidence_reservations') GROUP BY TABLE_NAME,INDEX_NAME")->fetchAll();
    $unique=[];foreach($indexes as $i)$unique[$i['TABLE_NAME']][]=$i['cols'];
    foreach(['journal_corrections'=>['id','target_journal_id','reversal_journal_id','replacement_journal_id','draft_id','submission_key'],'journal_correction_lines'=>['original_line_id','reversal_line_id'],'cash_advance_operation_reversals'=>['id','original_operation_id','correction_id','reversal_journal_id','control_line_id'],'correction_evidence_reservations'=>['receipt_id']] as $t=>$keys)foreach($keys as $k)if(!in_array($k,$unique[$t]??[],true))$problems[]='Missing unique key '.$t.'.'.$k;
    return ['state'=>$problems?'partial':'complete','complete'=>!$problems,'problems'=>$problems];
}
function stage3_schema(PDO $pdo): bool {return stage3_schema_state($pdo)['complete'];}
function stage3_enabled(PDO $pdo): bool
{
    return defined('STAGE3_CORRECTIONS_ENABLED')&&STAGE3_CORRECTIONS_ENABLED===true&&stage1_enabled($pdo)&&stage2_schema($pdo)&&stage3_schema($pdo);
}
/** Cross-table facts that FKs cannot enforce; no private snapshot is projected. */
function stage3_correction_valid(PDO $pdo,array $c): bool
{
    $s=$pdo->prepare('SELECT * FROM journal_entries WHERE id IN (?,?,?)');$s->execute([$c['target_journal_id'],$c['reversal_journal_id'],$c['replacement_journal_id']]);$j=[];foreach($s as $r)$j[(int)$r['id']]=$r;
    $o=$j[(int)$c['target_journal_id']]??null;$v=$j[(int)$c['reversal_journal_id']]??null;$n=$c['replacement_journal_id']===null?null:($j[(int)$c['replacement_journal_id']]??null);
    if(!$o||!$v||$o['status']!=='posted'||$v['status']!=='posted'||$o['transaction_kind']==='correction_reversal'||$v['transaction_kind']!=='correction_reversal'||$v['source_book']!=='GJ'||$v['entry_date']!==$c['accounting_date']||$c['accounting_date']<$o['entry_date']||trim($c['reason'])===''||(int)$v['posted_by_user_id']!==(int)$c['created_by'])return false;
    if($v['party_id']!==$o['party_id']||$v['party_snapshot']!==$o['party_snapshot'])return false;
    if(($c['mode']==='reverse_replace')!==($n!==null)||($n&&($n['status']!=='posted'||$n['entry_date']!==$c['accounting_date']||$n['transaction_kind']==='correction_reversal'||(int)$n['posted_by_user_id']!==(int)$c['created_by'])))return false;
    $s=$pdo->prepare('SELECT * FROM journal_drafts WHERE id=?');$s->execute([$c['draft_id']]);$d=$s->fetch();
    if(!$d||(int)$d['payload_version']!==4||$d['workflow_kind']!=='correction'||$d['state']!=='Posted'||(int)$d['owner_id']!==(int)$c['created_by']||(int)$d['correction_target_journal_id']!==(int)$o['id']||$d['correction_mode']!==$c['mode']||(int)$d['posted_journal_id']!==(int)($n['id']??$v['id'])||$d['submission_key']!==$c['submission_key']||$d['source_book']!==($n['source_book']??'GJ'))return false;
    if($c['parent_correction_id']===null){if((int)$c['root_journal_id']!==(int)$o['id'])return false;
        $s=$pdo->prepare('SELECT COUNT(*) FROM journal_corrections WHERE reversal_journal_id=? OR replacement_journal_id=?');$s->execute([$o['id'],$o['id']]);if((int)$s->fetchColumn())return false;
    }else{
        $seen=[(int)$c['id']=>true];$child=$c;
        while($child['parent_correction_id']!==null){$id=(int)$child['parent_correction_id'];if(isset($seen[$id]))return false;$seen[$id]=true;
            $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE id=?');$s->execute([$id]);$parent=$s->fetch();
            if(!$parent||(int)$parent['replacement_journal_id']!==(int)$child['target_journal_id']||(int)$parent['root_journal_id']!==(int)$c['root_journal_id']||$parent['accounting_date']>$child['accounting_date'])return false;$child=$parent;
        }if((int)$child['root_journal_id']!==(int)$child['target_journal_id'])return false;
    }
    $s=$pdo->prepare('SELECT * FROM journal_entry_lines WHERE journal_entry_id IN (?,?) ORDER BY id');$s->execute([$o['id'],$v['id']]);$original=[];$reversal=[];
    foreach($s as $l){if((int)$l['journal_entry_id']===(int)$o['id'])$original[(int)$l['id']]=$l;else $reversal[(int)$l['id']]=$l;}
    $s=$pdo->prepare('SELECT * FROM journal_correction_lines WHERE correction_id=?');$s->execute([$c['id']]);$maps=$s->fetchAll();
    if(count($original)<2||count($original)!==count($reversal)||count($maps)!==count($original))return false;
    $debit=$credit=0;
    foreach($maps as $m){$a=$original[(int)$m['original_line_id']]??null;$b=$reversal[(int)$m['reversal_line_id']]??null;if(!$a||!$b)return false;
        foreach(['account_id','fund_project_id','project_code_snapshot','project_name_snapshot'] as $k)if($a[$k]!==$b[$k])return false;
        if($a['debit_amount']!==$b['credit_amount']||$a['credit_amount']!==$b['debit_amount'])return false;
        $debit=accounting_add($debit,accounting_cents($a['debit_amount']));$credit=accounting_add($credit,accounting_cents($a['credit_amount']));
    }return $debit>0&&$debit===$credit;
}
/** Propagate sensitive status across the whole family. Partial/damaged links fail closed. */
function stage3_sensitive_journals(PDO $pdo,array $ids): array
{
    if(!$ids)return [];$state=stage3_schema_state($pdo);
    if($state['state']==='partial')return array_fill_keys(array_map('intval',$ids),true);
    if(!$state['complete'])return stage2_sensitive_journals_direct($pdo,$ids);
    $corrections=$pdo->query('SELECT * FROM journal_corrections ORDER BY id')->fetchAll();$all=array_fill_keys(array_map('intval',$ids),true);$edges=[];$bad=[];
    foreach($corrections as $c){$members=array_filter([$c['target_journal_id'],$c['reversal_journal_id'],$c['replacement_journal_id'],$c['root_journal_id']],fn($id)=>$id!==null);$members=array_map('intval',$members);
        foreach($members as $a){$all[$a]=true;foreach($members as $b)$edges[$a][$b]=true;}
        if(!stage3_correction_valid($pdo,$c))foreach($members as $a)$bad[$a]=true;
    }
    $sensitive=stage2_sensitive_journals_direct($pdo,array_keys($all))+$bad;
    // An unlinked generated reversal must never acquire less-restricted access.
    $known=array_column($corrections,'reversal_journal_id');foreach(array_chunk(array_keys($all),500) as $chunk){$s=$pdo->prepare("SELECT id FROM journal_entries WHERE transaction_kind='correction_reversal' AND id IN (".implode(',',array_fill(0,count($chunk),'?')).')');$s->execute($chunk);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id)if(!in_array($id,$known))$sensitive[(int)$id]=true;}
    $queue=array_keys($sensitive);for($i=0;$i<count($queue);$i++)foreach($edges[$queue[$i]]??[] as $id=>$_)if(!isset($sensitive[$id])){$sensitive[$id]=true;$queue[]=$id;}
    return array_intersect_key($sensitive,array_fill_keys(array_map('intval',$ids),true));
}
/** Validate the complete source-association lineage; do not trust a file hash alone. */
function stage3_evidence_association_valid(PDO $pdo,array $association): bool
{
    $seen=[];$a=$association;$receipt=(int)$a['receipt_id'];
    while(true){$id=(int)$a['id'];if(isset($seen[$id])||(int)$a['receipt_id']!==$receipt)return false;$seen[$id]=true;
        $s=$pdo->prepare("SELECT r.JournalEntryID,r.ExpenseID,r.File_SHA256,r.Posted_File_SHA256,r.OCR_Status,j.status FROM Receipts r JOIN journal_entries j ON j.id=? WHERE r.ReceiptID=?");$s->execute([$a['journal_id'],$receipt]);$r=$s->fetch();
        if(!$r||$r['ExpenseID']!==null||$r['status']!=='posted'||$r['OCR_Status']==='Discarded'||!$r['File_SHA256']||$r['File_SHA256']!==$r['Posted_File_SHA256'])return false;
        if(($a['correction_id']??null)===null)return ($a['source_association_id']??null)===null&&(int)$r['JournalEntryID']===(int)$a['journal_id'];
        $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE id=?');$s->execute([$a['correction_id']]);$c=$s->fetch();
        if(!$c||(int)$c['replacement_journal_id']!==(int)$a['journal_id']||!stage3_correction_valid($pdo,$c))return false;
        if($a['source_association_id']===null)return (int)$r['JournalEntryID']===(int)$a['journal_id'];
        $s=$pdo->prepare('SELECT * FROM posted_evidence_associations WHERE id=?');$s->execute([$a['source_association_id']]);$source=$s->fetch();
        if(!$source||(int)$source['journal_id']!==(int)$c['target_journal_id']||(int)$source['receipt_id']!==$receipt)return false;$a=$source;
    }
}
function stage3_receipt_sensitive(PDO $pdo,int $receipt): bool
{
    $s=$pdo->prepare('SELECT JournalEntryID FROM Receipts WHERE ReceiptID=?');$s->execute([$receipt]);$ids=$s->fetchAll(PDO::FETCH_COLUMN);
    if(stage1_schema($pdo)){$s=$pdo->prepare('SELECT journal_id FROM posted_evidence_associations WHERE receipt_id=?');$s->execute([$receipt]);$ids=array_merge($ids,$s->fetchAll(PDO::FETCH_COLUMN));}
    $ids=array_values(array_unique(array_filter($ids,fn($id)=>$id!==null)));
    return (bool)stage3_sensitive_journals($pdo,$ids);
}
