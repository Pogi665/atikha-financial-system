<?php
/** One flag-independent operation model for registers, preflight and candidate posting. */
require_once __DIR__.'/stage3_common.php';

function advance_lifecycle_model(PDO $pdo): array
{
    $schema=stage3_schema_state($pdo);
    if($schema['state']==='partial')throw new JournalProblem('Correction schema is incomplete. Advance history cannot be safely calculated.',409);
    $s3=$schema['complete'];$advances=$ops=$byJournal=$lines=$linked=$events=$reversals=$corrections=$problems=[];
    foreach($pdo->query('SELECT a.*,j.entry_date release_date FROM cash_advances a JOIN journal_entries j ON j.id=a.release_journal_id ORDER BY a.id') as $a)$advances[(int)$a['id']]=$a;
    $controls=$pdo->query('SELECT d.account_id,c.Name account_name FROM advance_control_designations d JOIN Categories c ON c.CategoryID=d.account_id ORDER BY d.account_id')->fetchAll();
    foreach($pdo->query("SELECT l.*,j.entry_date,j.status,j.source_book,j.transaction_kind FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id JOIN advance_control_designations c ON c.account_id=l.account_id WHERE j.status='posted' ORDER BY l.id") as $l)$lines[(int)$l['id']]=$l;
    foreach($pdo->query('SELECT o.*,d.payload_version,d.workflow_kind,d.advance_id draft_advance,d.state draft_state,d.posted_journal_id,'.($s3?'d.correction_target_journal_id':'NULL AS correction_target_journal_id').' FROM cash_advance_operations o LEFT JOIN journal_drafts d ON d.id=o.draft_id ORDER BY o.id') as $o){$ops[(int)$o['id']]=$o;$byJournal[(int)$o['journal_id']]=$o;}
    if($s3)foreach($pdo->query('SELECT * FROM journal_corrections ORDER BY id') as $c)$corrections[(int)$c['id']]=$c;
    $validCorrections=[];foreach($corrections as $id=>$c)$validCorrections[$id]=stage3_correction_valid($pdo,$c);
    foreach($ops as $id=>&$o){
        $a=$advances[(int)$o['advance_id']]??null;$l=$lines[(int)$o['control_line_id']]??null;$kind=$o['operation_kind'];$book=['release'=>'CDB','liquidation'=>'GJ','return'=>'CRB'][$kind]??null;
        $valid=$a&&$l&&$book&&(int)$l['account_id']===(int)$a['control_account_id']&&(int)$l['journal_entry_id']===(int)$o['journal_id']&&$l['transaction_kind']==='advance_'.$kind&&$l['source_book']===$book&&$o['draft_state']==='Posted'&&(int)$o['posted_journal_id']===(int)$o['journal_id'];
        $o['correction_id']=null;
        if((int)$o['payload_version']===3){$valid=$valid&&$o['workflow_kind']==='advance_'.$kind&&(int)$o['draft_advance']===(int)$o['advance_id'];}
        elseif((int)$o['payload_version']===4&&$s3){
            $c=null;foreach($corrections as $candidate)if((int)$candidate['draft_id']===(int)$o['draft_id']&&(int)$candidate['replacement_journal_id']===(int)$o['journal_id']){$c=$candidate;break;}
            $original=$c?($byJournal[(int)$c['target_journal_id']]??null):null;
            $valid=$valid&&$o['workflow_kind']==='correction'&&$c&&$validCorrections[(int)$c['id']]&&$original&&$original['operation_kind']===$kind&&(int)$o['draft_advance']===(int)$original['advance_id']&&(int)$o['correction_target_journal_id']===(int)$original['journal_id']&&($kind==='release'?(int)$o['advance_id']!==(int)$original['advance_id']:(int)$o['advance_id']===(int)$original['advance_id']);
            $o['correction_id']=$c?(int)$c['id']:null;
        }else $valid=false;
        $debit=$l?accounting_cents($l['debit_amount']):0;$credit=$l?accounting_cents($l['credit_amount']):0;
        $valid=$valid&&($kind==='release'?($debit>0&&$credit===0&&(int)$a['release_journal_id']===(int)$o['journal_id']):($credit>0&&$debit===0&&$l['entry_date']>=$a['release_date']));
        if(!$valid){$problems[]='Invalid advance operation #'.$id;continue;}
        $line=(int)$o['control_line_id'];if(isset($linked[$line]))$problems[]='Multiply linked control line #'.$line;$linked[$line]=true;
        $o['entry_date']=$l['entry_date'];$o['debit_amount']=$l['debit_amount'];$o['credit_amount']=$l['credit_amount'];$o['amount_cents']=$kind==='release'?$debit:$credit;
        $events[(int)$o['advance_id']][$l['entry_date']][]=['kind'=>$kind,'amount'=>$o['amount_cents'],'count'=>1,'operation_id'=>$id];
    }unset($o);
    if($s3)foreach($pdo->query('SELECT * FROM cash_advance_operation_reversals ORDER BY id') as $r){
        $o=$ops[(int)$r['original_operation_id']]??null;$l=$lines[(int)$r['control_line_id']]??null;$c=$corrections[(int)$r['correction_id']]??null;
        $valid=$o&&isset($o['amount_cents'])&&$l&&$c&&$validCorrections[(int)$c['id']]&&(int)$c['target_journal_id']===(int)$o['journal_id']&&(int)$c['reversal_journal_id']===(int)$r['reversal_journal_id']&&(int)$l['journal_entry_id']===(int)$r['reversal_journal_id'];
        $s=$pdo->prepare('SELECT COUNT(*) FROM journal_correction_lines WHERE correction_id=? AND original_line_id=? AND reversal_line_id=?');$s->execute([$r['correction_id'],$o['control_line_id']??null,$r['control_line_id']]);$valid=$valid&&(int)$s->fetchColumn()===1;
        if(!$valid){$problems[]='Invalid advance operation reversal #'.$r['id'];continue;}
        $line=(int)$r['control_line_id'];if(isset($linked[$line]))$problems[]='Multiply linked control line #'.$line;$linked[$line]=true;
        $r+=['entry_date'=>$l['entry_date'],'operation_kind'=>$o['operation_kind'],'advance_id'=>(int)$o['advance_id'],'amount_cents'=>$o['amount_cents'],'created_at'=>$c['created_at']];$reversals[(int)$o['id']]=$r;
        $events[(int)$o['advance_id']][$l['entry_date']][]=['kind'=>$o['operation_kind'],'amount'=>-$o['amount_cents'],'count'=>-1,'operation_id'=>(int)$o['id']];
    }
    foreach($lines as $id=>$l)if(!isset($linked[$id]))$problems[]='Unlinked or invalid posted control line #'.$id;
    foreach($corrections as $id=>$c){$original=$byJournal[(int)$c['target_journal_id']]??null;if(!$original)continue;
        if(!$validCorrections[$id]||!isset($reversals[(int)$original['id']]))$problems[]='Missing prescribed operation reversal for correction #'.$id;
        if($c['replacement_journal_id']!==null&&!isset($byJournal[(int)$c['replacement_journal_id']]))$problems[]='Missing prescribed replacement operation for correction #'.$id;
    }
    foreach($advances as $id=>$a)if(!isset($byJournal[(int)$a['release_journal_id']])||$byJournal[(int)$a['release_journal_id']]['operation_kind']!=='release'||(int)$byJournal[(int)$a['release_journal_id']]['advance_id']!==$id)$problems[]='Missing prescribed release for advance #'.$id;
    $problems=array_merge($problems,advance_lifecycle_errors($events));
    return compact('advances','ops','byJournal','lines','linked','events','reversals','corrections','controls','problems');
}

/** Date groups are indivisible; counts protect identity even when amounts happen to net to zero. */
function advance_lifecycle_errors(array $events): array
{
    $errors=[];
    foreach($events as $id=>$dated){ksort($dated);$amount=['release'=>0,'liquidation'=>0,'return'=>0];$counts=$amount;
        foreach($dated as $date=>$items){foreach($items as $e){$amount[$e['kind']]=accounting_add($amount[$e['kind']],$e['amount']);$counts[$e['kind']]+=$e['count'];}
            $out=accounting_add(accounting_add($amount['release'],-$amount['liquidation']),-$amount['return']);
            if(min($amount)<0||min($counts)<0||$out<0||!in_array($counts['release'],[0,1],true)||($counts['release']===0&&($counts['liquidation']!==0||$counts['return']!==0||$out!==0)))$errors[]='Invalid dated advance balance/lifecycle #'.$id.' on '.$date.' (outstanding PHP '.ledger_decimal($out).').';
        }
    }return $errors;
}
function advance_lifecycle_amounts(array $events,string $asof): array
{
    $amount=['release'=>0,'liquidation'=>0,'return'=>0];$counts=$amount;ksort($events);
    foreach($events as $date=>$items)if($date<=$asof)foreach($items as $e){$amount[$e['kind']]=accounting_add($amount[$e['kind']],$e['amount']);$counts[$e['kind']]+=$e['count'];}
    return ['amount'=>$amount,'counts'=>$counts,'outstanding'=>accounting_add(accounting_add($amount['release'],-$amount['liquidation']),-$amount['return'])];
}
function advance_lifecycle_state(PDO $pdo,string $asof,?array $model=null): array
{
    $m=$model??advance_lifecycle_model($pdo);$advances=[];$reconciliation=[];
    foreach($m['controls'] as $c)$reconciliation[(int)$c['account_id']]=['account_id'=>(int)$c['account_id'],'account_name'=>$c['account_name'],'register_cents'=>0,'ledger_cents'=>0,'errors'=>$m['problems']];
    foreach($m['advances'] as $id=>$a){if($a['release_date']>$asof)continue;$projection=advance_lifecycle_amounts($m['events'][$id]??[],$asof);
        $a['released_cents']=$projection['amount']['release'];$a['liquidated_cents']=$projection['amount']['liquidation'];$a['returned_cents']=$projection['amount']['return'];$a['outstanding_cents']=$projection['outstanding'];$a['release_count']=$projection['counts']['release'];$a['due_date']=$a['initial_due_date'];$a['operations']=[];
        $a['original_released_cents']=0;$a['lifecycle_date']=null;$a['replacement_advance_id']=null;$a['original_advance_id']=null;$a['lifecycle_correction_id']=null;
        foreach($m['ops'] as $o){if((int)$o['advance_id']!==$id||!isset($o['amount_cents']))continue;
            if($o['operation_kind']==='release'){$a['original_released_cents']=$o['amount_cents'];if($o['correction_id']!==null){$c=$m['corrections'][$o['correction_id']];$a['original_advance_id']=(int)$m['byJournal'][(int)$c['target_journal_id']]['advance_id'];}}
            if($o['entry_date']<=$asof){$o['is_reversal']=false;$a['operations'][]=$o;}
            $r=$m['reversals'][(int)$o['id']]??null;
            if($r&&$r['entry_date']<=$asof){$c=$m['corrections'][(int)$r['correction_id']];
                $a['operations'][]=array_replace($o,['journal_id'=>$r['reversal_journal_id'],'entry_date'=>$r['entry_date'],'created_at'=>$r['created_at'],'is_reversal'=>true,'correction_id'=>(int)$c['id']]);
                if($o['operation_kind']==='release'){$a['lifecycle_date']=$r['entry_date'];$a['lifecycle_correction_id']=(int)$c['id'];$replacement=$m['byJournal'][(int)$c['replacement_journal_id']]??null;$a['replacement_advance_id']=$replacement?(int)$replacement['advance_id']:null;}
            }
        }
        usort($a['operations'],fn($x,$y)=>strcmp($x['entry_date'],$y['entry_date'])?:((int)$x['journal_id']<=>(int)$y['journal_id']));
        $a['status']=$a['lifecycle_date']!==null?($a['replacement_advance_id']===null?'cancelled':'replaced'):($a['outstanding_cents']===0?'settled':($a['liquidated_cents']+$a['returned_cents']>0?'partially_settled':'outstanding'));
        $advances[$id]=$a;$account=(int)$a['control_account_id'];if(!isset($reconciliation[$account]))throw new JournalProblem('Advance control designation is missing.',409);$reconciliation[$account]['register_cents']=accounting_add($reconciliation[$account]['register_cents'],$a['outstanding_cents']);
    }
    $s=$pdo->prepare('SELECT * FROM cash_advance_due_changes WHERE effective_date<=? ORDER BY effective_date,id');$s->execute([$asof]);foreach($s as $e)if(isset($advances[(int)$e['advance_id']]))$advances[(int)$e['advance_id']]['due_date']=$e['new_due_date'];
    foreach($advances as &$a){$a['overdue_days']=$a['outstanding_cents']>0&&$asof>$a['due_date']?(int)(new DateTimeImmutable($a['due_date'],new DateTimeZone('Asia/Manila')))->diff(new DateTimeImmutable($asof,new DateTimeZone('Asia/Manila')))->days:0;$days=$a['overdue_days'];$a['aging_bucket']=$days===0?'Not overdue':($days<=30?'1–30':($days<=60?'31–60':($days<=90?'61–90':'>90')));}unset($a);
    foreach($m['lines'] as $l)if($l['entry_date']<=$asof){$account=(int)$l['account_id'];$reconciliation[$account]['ledger_cents']=accounting_add($reconciliation[$account]['ledger_cents'],accounting_cents($l['debit_amount'])-accounting_cents($l['credit_amount']));}
    foreach($reconciliation as &$r){$r['difference']=ledger_decimal($r['ledger_cents']-$r['register_cents']);$r['ledger_balance']=ledger_decimal($r['ledger_cents']);$r['register_balance']=ledger_decimal($r['register_cents']);$r['errors']=array_values(array_unique($r['errors']));$r['ok']=$r['ledger_cents']===$r['register_cents']&&!$r['errors'];unset($r['ledger_cents'],$r['register_cents']);}unset($r);
    return ['advances'=>$advances,'reconciliation'=>array_values($reconciliation),'problems'=>$m['problems']];
}

/** Trusted candidate only: callers derive target/kind/control lines from persisted workflow identity. */
function advance_lifecycle_candidate(PDO $pdo,array $d,?array $input,?array $target=null): array
{
    $m=advance_lifecycle_model($pdo);$state=advance_lifecycle_state($pdo,journal_today(),$m);
    if($m['problems'])throw new JournalProblem('Advance control integrity failed: '.$m['problems'][0],409);
    foreach($state['reconciliation'] as $r)if(!$r['ok'])throw new JournalProblem('Advance control reconciliation failed for '.$r['account_name'].'.',409);
    $events=$m['events'];$original=$target['operation']??null;$id=$original?(int)$original['advance_id']:($d['advance_id']===null?null:(int)$d['advance_id']);$date=$target?$d['payload']['entry_date']:$input['entry_date'];$before=$id===null?null:($state['advances'][$id]['outstanding_cents']??0);$remaining=$before;
    if($original){$op=$m['ops'][(int)$original['id']]??null;if(!$op||isset($m['reversals'][(int)$op['id']]))throw new JournalProblem('Advance operation is already reversed or invalid.',409);
        if($op['operation_kind']==='release')foreach($m['ops'] as $other)if((int)$other['advance_id']===$id&&$other['operation_kind']!=='release'&&!isset($m['reversals'][(int)$other['id']]))throw new JournalProblem('Reverse the effective settlements first. Blocking '.$other['operation_kind'].' journal #'.$other['journal_id'].' on '.$other['entry_date'].'.',409);
        $events[$id][$date][]=['kind'=>$op['operation_kind'],'amount'=>-$op['amount_cents'],'count'=>-1,'operation_id'=>(int)$op['id']];
        if($op['operation_kind']!=='release')$remaining=accounting_add($before,$op['amount_cents']);
    }elseif($id!==null){$a=$state['advances'][$id]??null;if(!$a||in_array($a['status'],['cancelled','replaced'],true))throw new JournalProblem('This advance no longer has an active release. Open its replacement if applicable.',409);}
    $newId=null;
    if($input){$kind=substr($input['transaction_kind'],8);if($kind==='release'){$newId=-(int)$d['id'];$events[$newId]=[];$candidateId=$newId;}else{$candidateId=$id;if($candidateId===null)throw new JournalProblem('Settlement requires a linked advance.',409);}
        $events[$candidateId][$input['entry_date']][]=['kind'=>$kind,'amount'=>journal_amount($input['advance_context']['reduction']),'count'=>1,'operation_id'=>0];
    }
    $errors=advance_lifecycle_errors($events);if($errors)throw new JournalProblem($errors[0].' Choose a valid date and amount; no financial entry was posted.',409);
    $after=$id===null?null:advance_lifecycle_amounts($events[$id]??[],journal_today())['outstanding'];
    $replacement=$newId===null?null:advance_lifecycle_amounts($events[$newId],journal_today())['outstanding'];
    return ['before'=>$before,'after'=>$after,'remaining'=>$remaining,'before_display'=>$before===null?null:ledger_decimal($before),'after_display'=>$after===null?null:ledger_decimal($after),'replacement_outstanding'=>$replacement,'replacement_display'=>$replacement===null?null:ledger_decimal($replacement),'accounting_date'=>$date,'earliest_affected_date'=>$date,'fingerprint'=>hash('sha256',json_encode([$m['events'],$events,$m['advances']],JSON_THROW_ON_ERROR))];
}
/** Committed operation links, not the v4 draft's original ID, determine result navigation. */
function advance_correction_identity(PDO $pdo,array $c): array
{
    $s=$pdo->prepare('SELECT advance_id,operation_kind FROM cash_advance_operations WHERE journal_id=?');$s->execute([$c['target_journal_id']]);$o=$s->fetch();if(!$o)return [];
    $original=(int)$o['advance_id'];$replacement=null;
    if($c['replacement_journal_id']!==null){$s->execute([$c['replacement_journal_id']]);$n=$s->fetch();if(!$n||$n['operation_kind']!==$o['operation_kind'])throw new JournalProblem('Replacement advance operation is inconsistent.',409);if($o['operation_kind']==='release')$replacement=(int)$n['advance_id'];elseif((int)$n['advance_id']!==$original)throw new JournalProblem('Replacement settlement identity is inconsistent.',409);}
    return ['original_advance_id'=>$original,'replacement_advance_id'=>$replacement,'advance_id'=>$replacement??$original];
}
