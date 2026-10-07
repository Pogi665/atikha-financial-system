<?php
/** Synthetic fixtures only. Existing Stage 1/2 suites run before applying 021. */
$readerOptions=getopt('',['database:','privacy-worker:']);
if(isset($readerOptions['privacy-worker'])){
    require_once __DIR__.'/cli_common.php';$workerDb=$readerOptions['database']??'';cli_require(is_string($workerDb)&&(bool)preg_match('/\Aatikha_test_stage1_[a-z0-9]+\z/',$workerDb),'Disposable privacy worker required.');
    define('ATIKHA_ISOLATED_TEST',true);define('STAGE1_WORKSPACE_ENABLED',false);define('STAGE2_ADVANCES_ENABLED',false);define('STAGE3_CORRECTIONS_ENABLED',false);
    require_once __DIR__.'/../includes/journal_corrections.php';$p=cli_db($workerDb);$journal=journal_id($readerOptions['privacy-worker']);
    $uid=(int)$p->query("SELECT UserID FROM Users WHERE Email='management@example.invalid'")->fetchColumn();$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
    $s=$p->prepare('SELECT receipt_id FROM posted_evidence_associations WHERE journal_id=?');$s->execute([$journal]);$receipt=(int)$s->fetchColumn();
    $records=accounting_records($p,accounting_records_filters(['from'=>'','to'=>'']));$j=$records['journals'][$journal];
    echo workspace_json(['flags_disabled'=>!stage3_enabled($p)&&!stage2_enabled($p)&&!stage1_enabled($p),'metadata_hidden'=>receipt_journal_metadata($p,[$journal],'Admin')===[],
        'download_denied'=>!receipt_posted_download_allowed($p,$receipt,$journal),'attachments_hidden'=>$j['attachments']===[],'coverage'=>$j['evidence_coverage']['status'],'chain'=>correction_chain($p,$uid,$journal)]);exit;
}
require __DIR__.'/test_stage2.php';
require_once __DIR__.'/../includes/journal_corrections.php';
$prior=['stage1'=>$stage1Checks,'stage2'=>$checks];$checks=0;
function s3insert(PDO $p,string $table,array $row): int
{
    unset($row['id']);$fields=array_keys($row);$s=$p->prepare('INSERT INTO '.$table.' (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');$s->execute(array_values($row));return (int)$p->lastInsertId();
}
function s3manifest(PDO $p): array
{
    $out=[];foreach(['journal_entries','journal_entry_lines','Receipts','posted_evidence_associations','evidence_allocations','journal_drafts','cash_advances','cash_advance_operations','cash_advance_due_changes','audit_logs'] as $t){
        $rows=$p->query('SELECT * FROM '.$t.' ORDER BY '.(['Receipts'=>'ReceiptID','evidence_allocations'=>'association_id,line_id'][$t]??'id'))->fetchAll();
        foreach($rows as &$r){if($t==='journal_drafts')unset($r['correction_target_journal_id'],$r['correction_mode']);if($t==='posted_evidence_associations')unset($r['source_association_id'],$r['correction_id']);}unset($r);
        $out[$t]=[count($rows),hash('sha256',workspace_json($rows))];
    }return $out;
}
/** Fixture builder, NOT an application writer; all records are confined to the new test DB. */
function s3fixture(PDO $p,int $uid,int $target,bool $replace=true,?string $newAmount=null,bool $omitMapping=false): array
{
    $s=$p->prepare('SELECT * FROM journal_entries WHERE id=?');$s->execute([$target]);$o=$s->fetch();
    $s=$p->prepare('SELECT * FROM journal_entry_lines WHERE journal_entry_id=? ORDER BY id');$s->execute([$target]);$lines=$s->fetchAll();
    $s=$p->prepare('SELECT * FROM cash_advance_operations WHERE journal_id=?');$s->execute([$target]);$operation=$s->fetch();
    $s=$p->prepare('SELECT * FROM journal_corrections WHERE replacement_journal_id=?');$s->execute([$target]);$parent=$s->fetch();
    $key=bin2hex(random_bytes(32));$v=$o;$v['submission_key']=hash('sha256',$key.':reversal');$v['submission_hash']=hash('sha256','fixture reversal '.$key);$v['source_book']='GJ';$v['transaction_kind']='correction_reversal';$v['posted_by_user_id']=$uid;$v['description']='Fixture exact reversal';$vid=s3insert($p,'journal_entries',$v);
    $map=[];foreach($lines as $l){$r=$l;$r['journal_entry_id']=$vid;$r['debit_amount']=$l['credit_amount'];$r['credit_amount']=$l['debit_amount'];$map[(int)$l['id']]=s3insert($p,'journal_entry_lines',$r);}
    $nid=null;$newLines=[];
    if($replace){$n=$o;$n['submission_key']=hash('sha256',$key.':replacement');$n['submission_hash']=hash('sha256','fixture replacement '.$key);$n['posted_by_user_id']=$uid;$n['description']='Fixture replacement';$nid=s3insert($p,'journal_entries',$n);
        foreach($lines as $l){$r=$l;$r['journal_entry_id']=$nid;if($newAmount!==null){if(accounting_cents($r['debit_amount'])>0)$r['debit_amount']=$newAmount;else $r['credit_amount']=$newAmount;}$newLines[(int)$l['id']]=s3insert($p,'journal_entry_lines',$r);}
    }
    $draft=s3insert($p,'journal_drafts',['owner_id'=>$uid,'source_book'=>$replace?$o['source_book']:'GJ','payload_version'=>4,'payload'=>'{"entry_date":"'.$o['entry_date'].'","reason":"Fixture","backdate_reason":"","replacement":null}',
        'creation_hash'=>hash('sha256',$key),'submission_key'=>$key,'state'=>'Posted','posted_journal_id'=>$nid??$vid,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),'workflow_kind'=>'correction','advance_id'=>$operation?$operation['advance_id']:null,'return_confirmation'=>null,'correction_target_journal_id'=>$target,'correction_mode'=>$replace?'reverse_replace':'reverse_only']);
    $cid=s3insert($p,'journal_corrections',['target_journal_id'=>$target,'reversal_journal_id'=>$vid,'replacement_journal_id'=>$nid,'root_journal_id'=>$parent?$parent['root_journal_id']:$target,'parent_correction_id'=>$parent?$parent['id']:null,'draft_id'=>$draft,'submission_key'=>$key,'mode'=>$replace?'reverse_replace':'reverse_only','accounting_date'=>$o['entry_date'],'reason'=>'Fixture correction','backdate_reason'=>'','request_hash'=>hash('sha256','fixture '.$key),'review_snapshot'=>'{"private":"secret-review"}','created_by'=>$uid,'created_at'=>gmdate('Y-m-d H:i:s')]);
    foreach($map as $original=>$reversing){if($omitMapping){$omitMapping=false;continue;}$p->prepare('INSERT INTO journal_correction_lines VALUES(?,?,?)')->execute([$cid,$original,$reversing]);}
    $newAdvance=null;
    if($operation){s3insert($p,'cash_advance_operation_reversals',['original_operation_id'=>$operation['id'],'correction_id'=>$cid,'reversal_journal_id'=>$vid,'control_line_id'=>$map[(int)$operation['control_line_id']]]);
        if($replace){$a=$operation['advance_id'];if($operation['operation_kind']==='release'){$s=$p->prepare('SELECT * FROM cash_advances WHERE id=?');$s->execute([$a]);$new=$s->fetch();$new['release_journal_id']=$nid;$new['created_by']=$uid;$newAdvance=$a=s3insert($p,'cash_advances',$new);}
            $op=$operation;$op['advance_id']=$a;$op['journal_id']=$nid;$op['draft_id']=$draft;$op['control_line_id']=$newLines[(int)$operation['control_line_id']];unset($op['release_advance_id']);s3insert($p,'cash_advance_operations',$op);
        }
    }
    return ['correction'=>$cid,'target'=>$target,'reversal'=>$vid,'replacement'=>$nid,'draft'=>$draft,'mapping'=>$map,'new_lines'=>$newLines,'new_advance'=>$newAdvance];
}
function s3preflight(string $script,string $db): array
{
    $pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/'.$script,'--database='.$db],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
    cli_require($out!==''&&$err==='','Preflight failed unexpectedly: '.$err);return [$code,json_decode($out,true,64,JSON_THROW_ON_ERROR)];
}
function s3reject(callable $operation,string $label): void
{
    try{$operation();}catch(PDOException $e){st($e->getCode()==='45000',$label.' rejected by immutable trigger');return;}
    throw new RuntimeException('Unexpected acceptance: '.$label);
}
try{
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$before=s3manifest($p);
    [$code,$pre]=s3preflight('preflight_stage3.php',$db);
    if(getenv('ATIKHA_TEST_INTEGRATED')!=='1'){
        st($code===0&&$pre['schema_ready_for_021']&&!$pre['writes_performed'],'Read-only pre-021 preflight accepts existing Stage 1/2 fixtures');
        cli_sql_file($p,__DIR__.'/../migrations/021_journal_corrections.sql');
        st(stage3_schema($p)&&s3manifest($p)===$before,'021 completes schema and preserves original row values/counts/hashes');
    }else st($code===0&&$pre['schema_complete']&&!$pre['writes_performed'],'Integrated preflight accepts complete 021 before Checkpoint 1 operational cases');
    st(!stage3_enabled($p),'Checkpoint 1 does not activate Stage 3');
    [$code,$post]=s3preflight('preflight_stage3.php',$db);st($code===0&&$post['schema_complete']&&!$post['schema_ready_for_021']&&$pre['preservation_manifest']===$post['preservation_manifest'],'Post-021 preflight passes and preservation manifests remain identical');
    foreach(["UPDATE journal_entries SET description='bad' WHERE id=".$old['id'],"DELETE FROM journal_entries WHERE id=".$old['id'],"UPDATE journal_entry_lines SET debit_amount=2 WHERE journal_entry_id=".$old['id'],"DELETE FROM journal_entry_lines WHERE journal_entry_id=".$old['id']] as $sql)no($p,fn()=>$p->exec($sql),'Immutable posted journal/line');
    $d=save($p,$uid,'CDB',payload([line('cost',$training,'1000.00')],(int)$party['id'],$bank,'1000.00'));$d=workspace_attach($p,$uid,request(identity($d)),stage_image($p,$uid));$pl=$d['payload'];$doc=&$pl['documents'][0];$doc['reviewed']=true;$doc['purpose']='amount';$doc['support_side']='debit';$doc['declared_amount']=$doc['accepted_amount']='1000.00';$doc['allocations']=[['client_id'=>'cost','amount'=>'1000.00']];unset($doc);$d=workspace_save($p,$uid,request(identity($d)+['source_book'=>'CDB','payload'=>$pl]));$postRequest=post_request($p,$uid,$d);$posted=workspace_post($p,$uid,$postRequest);
    st(workspace_post($p,$uid,$postRequest)['duplicate'],'Existing ordinary recovery remains compatible after 021');
    $p->beginTransaction();$bundle=s3fixture($p,$uid,$posted['id'],true,'900.00');
    $a=$p->query('SELECT * FROM posted_evidence_associations WHERE journal_id='.$posted['id'])->fetch();$original=$a;$a['journal_id']=$bundle['replacement'];$a['source_association_id']=$original['id'];$a['correction_id']=$bundle['correction'];$a['declared_amount']=$a['accepted_amount']='900.00';$a['review_snapshot']='{"private":"replacement-review"}';$a['exclusion_reason']='Corrected accepted amount';$association=s3insert($p,'posted_evidence_associations',$a);
    $originalCost=(int)$p->query('SELECT line_id FROM evidence_allocations WHERE association_id='.$original['id'])->fetchColumn();$p->prepare('INSERT INTO evidence_allocations VALUES(?,?,?)')->execute([$association,$bundle['new_lines'][$originalCost],'900.00']);
    st(correction_integrity($p)===[],'Exact ordinary correction and evidence lineage pass integrity inspection');
    $docs=receipt_journal_evidence_internal($p,[$posted['id'],$bundle['replacement']]);st($docs[$posted['id']][0]['review']['accepted_amount']==='1000.00'&&$docs[$bundle['replacement']][0]['review']['accepted_amount']==='900.00','Association reader retains original review and uses replacement review');
    st(str_ends_with($docs[$bundle['replacement']][0]['url'],'journal_id='.$bundle['replacement']),'Replacement attachment URL uses its own journal association');
    st(receipt_posted_download_allowed($p,(int)$original['receipt_id'],$bundle['replacement'])&&!receipt_posted_download_allowed($p,(int)$original['receipt_id'],$old['id']),'Posted association download accepts valid reuse and rejects guessed journal');
    st((int)$p->query('SELECT JournalEntryID FROM Receipts WHERE ReceiptID='.$original['receipt_id'])->fetchColumn()===(int)$posted['id'],'Reused image primary journal remains unchanged');
    $_SESSION=['UserID'=>(int)$users['management@example.invalid'],'Role'=>'Admin'];$chain=correction_chain($p,(int)$_SESSION['UserID'],$bundle['replacement']);st(count($chain)===1&&!str_contains(workspace_json($chain),'secret-review'),'Trusted Management receives financial chain summary without private snapshots');
    st((bool)receipt_journal_metadata($p,[$bundle['replacement']],'Admin'),'Ordinary correction evidence retains existing Management permissions');
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$again=s3fixture($p,$uid,$bundle['replacement'],false);st(count(correction_chain($p,$uid,$bundle['target']))===2&&correction_integrity($p)===[],'Repeated chain retains root/parent and reverse-only mappings');
    foreach(['journal_corrections'=>'id='.$bundle['correction'],'journal_correction_lines'=>'correction_id='.$bundle['correction']] as $table=>$where){s3reject(fn()=>$p->exec('DELETE FROM '.$table.' WHERE '.$where),'Immutable '.$table);}
    $p->rollBack();
    st(s3manifest($p)===$before||correction_integrity($p)===[],'Fixture rollback removes correction records and retains normal postings');
    $ordinaryJournal=(int)$posted['id'];$beforeBad=s3manifest($p);$p->beginTransaction();$bad=s3fixture($p,$uid,$ordinaryJournal,true,null,true);
    st((bool)correction_integrity($p),'Missing exact line mapping is detected');
    $_SESSION=['UserID'=>(int)$users['management@example.invalid'],'Role'=>'Admin'];st(receipt_journal_metadata($p,[$ordinaryJournal])===[]&&!receipt_posted_download_allowed($p,(int)$original['receipt_id'],$ordinaryJournal),'Damaged correction lineage fails closed for Management even with forged session role');
    $p->rollBack();$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];st(s3manifest($p)===$beforeBad,'Rollback restores original data after malformed fixture');
    // Replacement release uses a NEW advance, whereas its v4 draft refers to the OLD one.
    $fresh=avrelease($p,$uid,(int)$employee2['id'],$bank,$ctrl,'100.00');$freshDraft=workspace_draft($p,$uid,(int)$fresh['draft']['id']);
    $sensitiveReceipt=(int)($pl['documents'][0]['receipt_id']);
    $s=$p->prepare('SELECT * FROM posted_evidence_associations WHERE journal_id=?');$s->execute([$gpost['id']]);$sensitiveAssociation=$s->fetch();
    $p->beginTransaction();$av=s3fixture($p,$uid,(int)$fresh['id'],true,'90.00');
    st($av['new_advance']!==$fresh['advance_id']&&(int)$p->query('SELECT advance_id FROM journal_drafts WHERE id='.$av['draft'])->fetchColumn()===(int)$fresh['advance_id']&&correction_integrity($p)===[],'V4 replacement release/new advance reconciles independently from the original draft advance');
    // Liquidation correction retains the original advance and reuses its image association.
    $liquid=s3fixture($p,$uid,(int)$gpost['id'],true);$a=$sensitiveAssociation;$a['journal_id']=$liquid['replacement'];$a['source_association_id']=$sensitiveAssociation['id'];$a['correction_id']=$liquid['correction'];$newEvidence=s3insert($p,'posted_evidence_associations',$a);
    $s=$p->prepare('SELECT * FROM evidence_allocations WHERE association_id=?');$s->execute([$sensitiveAssociation['id']]);foreach($s->fetchAll() as $allocation)$p->prepare('INSERT INTO evidence_allocations VALUES(?,?,?)')->execute([$newEvidence,$liquid['new_lines'][(int)$allocation['line_id']],$allocation['amount']]);
    st(correction_integrity($p)===[],'V4 replacement liquidation and operation reversal pass bidirectional reconciliation');
    $sensitiveReceipt=(int)$sensitiveAssociation['receipt_id'];$family=[$liquid['target'],$liquid['reversal'],$liquid['replacement']];
    $_SESSION=['UserID'=>(int)$users['management@example.invalid'],'Role'=>'Admin'];
    st(count(stage2_sensitive_journals($p,$family))===3&&receipt_journal_metadata($p,$family,'Admin')===[],'Management privacy propagates through original, GJ reversal and replacement');
    st(!receipt_posted_download_allowed($p,$sensitiveReceipt,$liquid['target'])&&!receipt_posted_download_allowed($p,$sensitiveReceipt,$liquid['replacement']),'Both primary and reused private advance download routes deny Management');
    $records=accounting_records($p,accounting_records_filters(['from'=>'','to'=>'']));$replacement=$records['journals'][$liquid['replacement']];st($replacement['attachments']===[]&&$replacement['evidence_coverage']['status']==='Fully covered','Management books/history retain correct gross liquidation coverage after redaction');
    st($records['journals'][$liquid['reversal']]['evidence_coverage']['status']==='Reversal — original evidence referenced; no new claim','Reversal coverage is labelled without a new expenditure claim');
    st(!str_contains(workspace_json(correction_chain($p,(int)$_SESSION['UserID'],$liquid['replacement'])),'secret-review'),'Advance chain summaries exclude private correction snapshots');
    $_SESSION=['UserID'=>$uid,'Role'=>'Management'];st(receipt_posted_download_allowed($p,$sensitiveReceipt,$liquid['replacement']),'Active Admin can read replacement proof using trusted database identity');
    foreach(['cash_advance_operation_reversals'=>'correction_id='.$liquid['correction'],'posted_evidence_associations'=>'id='.$newEvidence] as $table=>$where)s3reject(fn()=>$p->exec('DELETE FROM '.$table.' WHERE '.$where),'Immutable '.$table);
    $p->rollBack();$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
    // Reservations are schema/read protected here; actual stale cleanup writer belongs to checkpoint 3.
    $s=$p->prepare('SELECT * FROM posted_evidence_associations WHERE journal_id=?');$s->execute([$ordinaryJournal]);$a=$s->fetch();$p->beginTransaction();
    $reservationDraft=s3insert($p,'journal_drafts',['owner_id'=>$uid,'source_book'=>'CDB','payload_version'=>4,'payload'=>'{}','creation_hash'=>hash('sha256','reservation'),'submission_key'=>bin2hex(random_bytes(32)),'state'=>'Draft','created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),'workflow_kind'=>'correction','advance_id'=>null,'correction_target_journal_id'=>$ordinaryJournal,'correction_mode'=>'reverse_replace']);
    $p->prepare('INSERT INTO correction_evidence_reservations VALUES(?,?,?,UTC_TIMESTAMP())')->execute([$a['receipt_id'],$reservationDraft,$a['id']]);
    st(correction_integrity($p)===[],'Eligible owned posted-image reservation passes integrity');
    try{stage1_reserved_guard($p,(int)$a['receipt_id']);throw new RuntimeException('Reservation bypass accepted');}catch(JournalProblem $e){st($e->status===409,'Existing receipt actions respect correction reservations');}
    s3fixture($p,$uid,$ordinaryJournal,false);st((bool)array_filter(correction_integrity($p),fn($x)=>str_contains($x,'stale correction evidence reservation')),'Stale reused-image reservation is explicitly detected');$p->rollBack();
    $created=$p->query('SHOW CREATE TRIGGER s3_mapping_no_delete')->fetch();$p->exec('DROP TRIGGER s3_mapping_no_delete');
    st(stage3_schema_state($p)['state']==='partial','Missing immutable trigger produces partial schema state');
    [$code,$partial]=s3preflight('preflight_stage3.php',$db);st($code===1&&!$partial['schema_ready_for_021']&&!$partial['checks_passed']&&!$partial['writes_performed'],'Partial migration preflight rejects unsafe rerun without writes');
    $_SESSION=['UserID'=>(int)$users['management@example.invalid'],'Role'=>'Management'];st(!receipt_posted_download_allowed($p,$sensitiveReceipt,(int)$gpost['id']),'Partial schema fails closed for downloads');
    $p->exec($created['SQL Original Statement']);$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];st(stage3_schema($p)&&correction_integrity($p)===[],'Restoring disposable trigger returns complete valid schema');
    [$code,$s2post]=s3preflight('preflight_stage2.php',$db);st($code===0&&$s2post['checks_passed'],'Existing Stage 2 preflight accepts complete Stage 3 schema');
    [$code,$final]=s3preflight('preflight_stage3.php',$db);st($code===0&&$final['checks_passed'],'Final disposable preflight passes with no residual correction fixtures');
    // Commit one valid synthetic chain solely to test a separate flags-disabled reader process.
    $p->beginTransaction();$last=s3fixture($p,$uid,(int)$gpost['id'],true);$a=$sensitiveAssociation;$a['journal_id']=$last['replacement'];$a['source_association_id']=$sensitiveAssociation['id'];$a['correction_id']=$last['correction'];$newEvidence=s3insert($p,'posted_evidence_associations',$a);
    $s=$p->prepare('SELECT * FROM evidence_allocations WHERE association_id=?');$s->execute([$sensitiveAssociation['id']]);foreach($s->fetchAll() as $allocation)$p->prepare('INSERT INTO evidence_allocations VALUES(?,?,?)')->execute([$newEvidence,$last['new_lines'][(int)$allocation['line_id']],$allocation['amount']]);$p->commit();
    $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--database='.$db,'--privacy-worker='.$last['replacement']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cli_require(proc_close($proc)===0&&$err==='','Privacy worker failed: '.$err);$off=json_decode($out,true,64,JSON_THROW_ON_ERROR);
    st($off['flags_disabled']&&$off['metadata_hidden']&&$off['download_denied']&&$off['attachments_hidden']&&$off['coverage']==='Fully covered'&&count($off['chain'])===1,'Existing readers/download policy preserve advance privacy and coverage with every UI flag disabled');
    [$code,$final]=s3preflight('preflight_stage3.php',$db);st($code===0&&$final['checks_passed'],'Committed synthetic correction chain passes read-only Stage 3 preflight');
    [$code,$final]=s3preflight('preflight_stage2.php',$db);st($code===0&&$final['checks_passed'],'Earlier Stage 2 preflight accepts valid reused evidence and version 4 operation links');
    echo "Stage 3 checkpoint 1 checks: $checks (Stage 1 {$prior['stage1']}, Stage 2 {$prior['stage2']})\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
