<?php
/** Complete-schema operational profile. NEW disposable databases/private evidence only. */
require_once __DIR__.'/cli_common.php';
function cp5manifest(PDO $p): array
{
    $out=[];foreach(['journal_entries','journal_entry_lines','journal_drafts','Receipts','receipt_ocr_attempts','posted_evidence_associations','evidence_allocations','audit_logs','cash_advances','cash_advance_operations','cash_advance_due_changes','cash_advance_operation_reversals','journal_corrections','journal_correction_lines','correction_evidence_reservations','draft_evidence_reservations','accounting_write_state'] as $table)$out[$table]=hash('sha256',json_encode($p->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));return $out;
}
$options=getopt('',['database:','gate-worker:']);$cp5db=$options['database']??'';
cli_require(is_string($cp5db)&&(bool)preg_match('/\Aatikha_test_stage1_[a-z0-9]+\z/',$cp5db),'NEW guarded Stage 1 disposable database required.');
if(isset($options['gate-worker'])){
    define('ATIKHA_ISOLATED_TEST',true);$base=realpath(__DIR__.'/../.migration-private');$path=realpath($options['gate-worker']);cli_require($path&&$base&&str_starts_with($path,$base.DIRECTORY_SEPARATOR),'Private worker context required.');$f=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    $evidence=realpath($f['receipt_root']);cli_require($evidence&&str_starts_with($evidence,$base.DIRECTORY_SEPARATOR)&&$f['database']===$cp5db,'Private matching evidence required.');
    foreach(['STAGE1_WORKSPACE_ENABLED','STAGE2_ADVANCES_ENABLED','STAGE3_CORRECTIONS_ENABLED'] as $i=>$flag)define($flag,$f['flags'][$i]);define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',$evidence);
    require_once __DIR__.'/../includes/correction_posting.php';$_SESSION=$f['session'];$p=cli_db($cp5db);cli_require(stage3_schema($p)&&stage2_schema($p),'Worker must use complete 021 schema.');$before=cp5manifest($p);$r=$f['request'];$r['csrf_token']=csrf_token();
    try{
        correction_guard($p,$f['uid'],$r);
        $result=match($f['action']){
            'post'=>correction_post($p,$f['uid'],$r),'save'=>correction_save($p,$f['uid'],$r),'review'=>correction_review($p,$f['uid'],$r),
            'draft'=>correction_public($p,$f['uid'],correction_entry_draft($p,$f['uid'],(int)$r['draft_id'])),
            'target'=>correction_entry_target($p,(int)$r['target_journal_id']),
            'attach'=>correction_attach($p,$f['uid'],$r),'reuse'=>correction_attach($p,$f['uid'],$r,true),
            'remove'=>correction_remove_or_discard($p,$f['uid'],$r,false),'discard'=>correction_remove_or_discard($p,$f['uid'],$r,true),
            'upload'=>correction_upload($p,$f['uid'],$r,[]),'confirm'=>correction_confirm($p,$f['uid'],$r),
            default=>throw new RuntimeException('Unknown test action.')};
        echo workspace_json(['ok'=>true,'result'=>$result,'unchanged'=>cp5manifest($p)===$before]);
    }catch(JournalProblem $e){echo workspace_json(['ok'=>false,'status'=>$e->status,'error'=>$e->getMessage(),'unchanged'=>cp5manifest($p)===$before]);}exit;
}
putenv('ATIKHA_TEST_INTEGRATED=1');
require __DIR__.'/test_stage3_checkpoint4.php';
$regression+=['stage3_checkpoint4'=>$checks];$checks=0;
function cp5worker(PDO $p,string $db,string $run,int $uid,array $flags,string $action,array $r): array
{
    $path=$run.'/cp5-worker-'.bin2hex(random_bytes(5)).'.json';file_put_contents($path,workspace_json(['database'=>$db,'receipt_root'=>RECEIPT_UPLOAD_DIR,'uid'=>$uid,'session'=>$_SESSION,'flags'=>$flags,'action'=>$action,'request'=>$r]));
    $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--database='.$db,'--gate-worker='.$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cli_require(proc_close($proc)===0&&$err==='','Gate worker failed: '.$err);return json_decode($out,true,64,JSON_THROW_ON_ERROR);
}
function cp5correct(PDO $p,int $uid,int $journal,string $mode='reverse_replace'): array
{
    $d=cdsave($p,$uid,correction_target($p,$journal),$mode);$pv=$d['payload'];$pv['reason']='Synthetic integrated recovery check';$d=cdupdate($p,$uid,$d,$pv);$r=cp3request($p,$uid,$d);return correction_post($p,$uid,$r)+['draft'=>$d,'request'=>$r];
}
try{
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
    st(stage1_schema($p)&&stage2_schema($p)&&stage3_schema($p),'All integrated operational suites completed on full 019/020/021');
    foreach(['CRB','CDB','GJ','transfer'] as $book){
        $pv=$book==='CRB'?payload([line('income',$income,'','25.00')],(int)$party['id'],$bank,'25.00'):($book==='CDB'?payload([line('cost',$training,'25.00')],(int)$party['id'],$bank,'25.00'):payload([line('debit',$book==='transfer'?$petty:$training,'25.00'),line('credit',$book==='transfer'?$bank:$tax,'','25.00')]));if($book==='transfer')$pv['transaction_kind']='transfer';
        $d=save($p,$uid,$book==='transfer'?'GJ':$book,$pv);$r=post_request($p,$uid,$d);$j=workspace_post($p,$uid,$r);$c=cp5correct($p,$uid,$j['id']);
        $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$r['csrf_token']=csrf_token();$r['review_token']='1000000000.'.str_repeat('0',64);$before=cp5manifest($p);$retry=workspace_post($p,$uid,$r);
        st($retry['duplicate']&&$retry['id']===$j['id']&&$retry['id']!==$c['replacement_journal_id']&&cp5manifest($p)===$before,'Original '.$book.' request recovers original journal after correction/session change/expired review without writes');
        $bad=$r;$bad['payload']['description']='Different retry';no($p,fn()=>workspace_post($p,$uid,$bad),'Changed original '.$book.' retry after correction',409);
        cp5correct($p,$uid,$c['replacement_journal_id']);$before=cp5manifest($p);st(workspace_post($p,$uid,$r)['id']===$j['id']&&cp5manifest($p)===$before,'Original '.$book.' recovery remains unchanged through second correction');
        $reverseDraft=save($p,$uid,$book==='transfer'?'GJ':$book,$pv);$reverseRequest=post_request($p,$uid,$reverseDraft);$reverse=workspace_post($p,$uid,$reverseRequest);cp5correct($p,$uid,$reverse['id'],'reverse_only');$before=cp5manifest($p);st(workspace_post($p,$uid,$reverseRequest)['id']===$reverse['id']&&cp5manifest($p)===$before,'Original '.$book.' recovery after reversal only');
    }
    if(correction_target($p,$stage1LegacyPost['id'])['eligible'])cp5correct($p,$uid,$stage1LegacyPost['id'],'reverse_only');$_SESSION=$stage1LegacySession;$stage1LegacyRequest['csrf_token']=csrf_token();$before=cp5manifest($p);$legacyRetry=journal_post($p,$uid,$stage1LegacyRequest);st($legacyRetry['duplicate']&&$legacyRetry['id']===$stage1LegacyPost['id']&&cp5manifest($p)===$before,'Legacy scalar original recovery after correction preserves original identity and state within its original session contract');
    $ordinary=cp3payment($p,$uid,(int)$party['id'],$bank,$training);$od=cp3draft($p,$uid,$ordinary['id']);$or=cp3request($p,$uid,$od);$off=cp5worker($p,$db,$run,$uid,[true,false,true],'post',$or);st($off['ok']&&!$off['result']['duplicate'],'Ordinary correction posts with Stage 2 flag off and complete installed schema');
    $or['review_token']='1000000000.'.str_repeat('0',64);$off=cp5worker($p,$db,$run,$uid,[true,false,true],'post',$or);st($off['ok']&&$off['result']['duplicate']&&$off['unchanged'],'Ordinary correction recovers with Stage 2 off after target is corrected');
    foreach(['release','liquidation','return'] as $kind){
        $rel=avrelease($p,$uid,$employeeId,$bank,$ctrl,'100.00');$target=$kind==='release'?$rel:($kind==='liquidation'?cp4liq($p,$uid,$rel['advance_id'],$training,'80.00',journal_today()):cp4return($p,$uid,$rel['advance_id'],$bank,'80.00',journal_today()));
        foreach(['reverse_only','reverse_replace'] as $mode){$cd=cdsave($p,$uid,correction_target($p,$target['id']),$mode);$pv=$cd['payload'];$pv['reason']='Synthetic paused workflow';$cd=cdupdate($p,$uid,$cd,$pv);$req=identity($cd)+['target_journal_id'=>(string)$target['id'],'mode'=>$mode,'source_book'=>$cd['source_book'],'payload'=>$cd['payload'],'receipt_id'=>'1','upload_key'=>bin2hex(random_bytes(32)),'review_token'=>'1000000000.'.str_repeat('0',64)];
            foreach(['target','draft','save','review','attach','reuse','remove','discard','upload','confirm','post'] as $action){$out=cp5worker($p,$db,$run,$uid,[true,false,true],$action,$req);st(!$out['ok']&&$out['status']===503&&$out['unchanged'],'Stage 2 off blocks '.$kind.' '.$mode.' '.$action.' with no writes');}
        }
    }
    // A successful advance correction remains recoverable after its target is ineligible.
    $rel=avrelease($p,$uid,$employeeId,$bank,$ctrl,'100.00');$cd=cp4draft($p,$uid,$rel['id'],journal_today(),'90.00');$qr=cp3request($p,$uid,$cd);$done=correction_post($p,$uid,$qr);$qr['review_token']='1000000000.'.str_repeat('0',64);
    $out=cp5worker($p,$db,$run,$uid,[true,false,true],'post',$qr);st(!$out['ok']&&$out['status']===503&&$out['unchanged'],'Successful advance retry pauses with Stage 2 off');
    $out=cp5worker($p,$db,$run,$uid,[true,true,true],'post',$qr);st($out['ok']&&$out['result']['duplicate']&&$out['result']['correction_id']===$done['correction_id']&&$out['unchanged'],'Reenabled advance retry recovers original bundle despite already-corrected target');
    $replacement=cp4draft($p,$uid,$done['replacement_journal_id'],journal_today(),'85.00');$out=cp5worker($p,$db,$run,$uid,[true,false,true],'draft',identity($replacement));st(!$out['ok']&&$out['status']===503&&$out['unchanged'],'Replacement advance provenance still requires Stage 2');
    foreach([[true,true,false],[false,true,true]] as $flags){$out=cp5worker($p,$db,$run,$uid,$flags,'post',$or);st(!$out['ok']&&$out['status']===503&&$out['unchanged'],'Stage 1/3 entry flags deny recovery/mutation without writes');}
    $_SESSION=['UserID'=>$uid,'Role'=>'Admin'];st(correction_integrity($p)===[],'Final integrated journal/evidence/advance integrity valid');
    file_put_contents($run.'/stage3-checkpoint5-fixture.json',workspace_json(['database'=>$db,'receipt_root'=>RECEIPT_UPLOAD_DIR,'bank'=>$bank,'income'=>$income,'training'=>$training,'petty'=>$petty,'tax'=>$tax,'party'=>$party['id'],'advance_journal'=>$done['replacement_journal_id'],'advance_draft'=>$replacement['id'],'ordinary_journal'=>$ordinary['id']]));
    echo "Stage 3 checkpoint 5 checks: $checks\nIntegrated suite counts: ".workspace_json($regression+['stage3_checkpoint5'=>$checks])."\nCheckpoint 5 fixture: $run/stage3-checkpoint5-fixture.json\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
