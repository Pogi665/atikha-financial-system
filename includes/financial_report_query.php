<?php
/** Coherent, exact monthly statements. No evidence projection or financial writer. */
require_once __DIR__.'/report_configuration.php';
require_once __DIR__.'/cash_advance.php';

function statement_line_net(array $line): int
{
    return accounting_add(accounting_cents((string)$line['debit_amount']),-accounting_cents((string)$line['credit_amount']));
}
function statement_kind(int $journal,array $designations,array $reversals,array $seen=[]): string
{
    if(isset($seen[$journal]))throw new ReportingProblem('A correction reporting dependency needs an integrity review.',503);
    $seen[$journal]=true;
    if(isset($reversals[$journal]))return statement_kind($reversals[$journal],$designations,$reversals,$seen);
    return $designations[$journal]['kind']??'ordinary';
}
function statement_operating_contribution(array $line,array $accounts,array $maps,array $cash): int
{
    $id=(int)$line['account_id'];if(isset($cash[$id]))return 0;$type=$accounts[$id]['Account_Type'];$flow=$maps[$id]['flow']??'';$net=statement_line_net($line);
    if(in_array($type,['Income','Expense'],true))return in_array($flow,['noncash_expense','noncash_income','investing','financing'],true)?0:-$net;
    return in_array($flow,['operating_asset','operating_liability'],true)?-$net:0;
}
/** Per-journal bridges prevent offsetting unexplained activity from hiding a mismatch. */
function statement_cash_flows(array $journals,array $accounts,array $configuration,array $kinds,array $reversals,array $allLines,array $advanceControls,int $opening,int $closing): array
{
    $cash=array_fill_keys($configuration['cash_accounts'],true);$maps=array_column($configuration['mappings'],null,'account_id');
    $allocations=$configuration['cash_allocations'];$reclasses=$configuration['noncash_reclasses'];$exclusions=[];$issues=[];$bridges=[];
    // An explicitly traced investment funded through an advance excludes its non-operating
    // portion from both release and later settlement working-capital changes.
    foreach($allocations as $allocation){$a=$allLines[$allocation['cash_line_id']]??null;$b=$allLines[$allocation['counterpart_line_id']]??null;if(!$a||!$b)continue;
        if((int)$a['journal_entry_id']!==(int)$b['journal_entry_id']){
            if(!isset($journals[(int)$a['journal_entry_id']])&&!isset($journals[(int)$b['journal_entry_id']]))continue;
            $amount=accounting_cents($allocation['amount']);
            foreach([(int)$a['journal_entry_id'],(int)$b['journal_entry_id']] as $jid){$candidates=[];foreach($allLines as $line)if((int)$line['journal_entry_id']===$jid&&isset($advanceControls[(int)$line['account_id']]))$candidates[]=$line;
                if(count($candidates)!==1){$issues[]='Review the advance investment allocation for transaction #'.$jid.'.';continue;}$line=$candidates[0];$id=(int)$line['line_id'];$exclusions[$id]=accounting_add($exclusions[$id]??0,$amount);if($exclusions[$id]>abs(statement_line_net($line)))$issues[]='Advance investment allocations exceed transaction #'.$jid.'.';
            }
        }
    }
    // An ordinary linked reversal receives the exact opposite reporting allocations.
    $reverseLines=$configuration['_reversal_line_map']??[];
    foreach($reverseLines as $reversalId=>$sourceMap){foreach($allocations as $allocation){$a=$allLines[$allocation['cash_line_id']]??null;$b=$allLines[$allocation['counterpart_line_id']]??null;
            if($a&&$b&&(int)$a['journal_entry_id']===(int)($reversals[$reversalId]??0)&&(int)$a['journal_entry_id']===(int)$b['journal_entry_id']&&isset($sourceMap[$allocation['cash_line_id']],$sourceMap[$allocation['counterpart_line_id']])){$copy=$allocation;$copy['cash_line_id']=$sourceMap[$allocation['cash_line_id']];$copy['counterpart_line_id']=$sourceMap[$allocation['counterpart_line_id']];$copy['inherited_reversal']=true;$allocations[]=$copy;}
        }
        foreach($reclasses as $reclass)if(isset($sourceMap[$reclass['from_line_id']],$sourceMap[$reclass['to_line_id']])){$copy=$reclass;$copy['from_line_id']=$sourceMap[$reclass['from_line_id']];$copy['to_line_id']=$sourceMap[$reclass['to_line_id']];$reclasses[]=$copy;}
        foreach($sourceMap as $old=>$new)if(isset($exclusions[$old]))$exclusions[$new]=$exclusions[$old];
    }
    $totals=['result'=>0,'noncash_adjustments'=>0,'working_capital'=>0,'reclassifications'=>0,'operating'=>0,'investing'=>0,'financing'=>0];$disclosures=[];
    foreach($journals as $jid=>$journal){$lines=$journal['lines'];$kind=$kinds[$jid]??'ordinary';$cashNet=0;$cashGross=0;$cashPositive=$cashNegative=false;$result=0;$noncash=0;$wc=0;$reclass=0;$investing=0;$financing=0;$cashLines=[];$other=[];$contribution=[];
        foreach($lines as $line){$id=(int)$line['line_id'];$account=(int)$line['account_id'];$net=statement_line_net($line);$flow=$maps[$account]['flow']??'';$type=$accounts[$account]['Account_Type'];
            if(isset($cash[$account])){$cashLines[$id]=$line;$cashNet=accounting_add($cashNet,$net);$cashGross=accounting_add($cashGross,abs($net));$cashPositive=$cashPositive||$net>0;$cashNegative=$cashNegative||$net<0;continue;}
            $other[$id]=$line;
            if($kind!=='ordinary'){$contribution[$id]=0;continue;}
            if(in_array($type,['Income','Expense'],true)){$result=accounting_add($result,-$net);if(in_array($flow,['noncash_expense','noncash_income','investing','financing'],true))$noncash=accounting_add($noncash,$net);}
            if(in_array($flow,['operating_asset','operating_liability'],true)){$wc=accounting_add($wc,-$net);if(isset($exclusions[$id]))$reclass=accounting_add($reclass,($net>0?1:-1)*$exclusions[$id]);}
            $contribution[$id]=statement_operating_contribution($line,$accounts,$maps,$cash);
        }
        foreach($reclasses as $a){if(!isset($other[$a['from_line_id']],$other[$a['to_line_id']]))continue;$amount=accounting_cents($a['amount']);foreach([$a['from_line_id'],$a['to_line_id']] as $id){$line=$other[$id];$net=statement_line_net($line);$c=statement_operating_contribution($line,$accounts,$maps,$cash);if($kind==='ordinary'&&$c!==0)$reclass=accounting_add($reclass,($net>0?1:-1)*$amount);} $disclosures[]=['journal_id'=>(int)$jid,'label'=>'Reviewed noncash reclassification','amount'=>$a['amount'],'reason'=>$a['reason']];}
        $explicit=array_values(array_filter($allocations,fn($a)=>isset($cashLines[$a['cash_line_id']])));$used=[];$assignedOperating=0;
        if($cashGross>0){
            if($explicit){
                foreach($explicit as $a){$line=$cashLines[$a['cash_line_id']];$amount=accounting_cents($a['amount']);$signed=statement_line_net($line)>0?$amount:-$amount;$used[$a['cash_line_id']]=accounting_add($used[$a['cash_line_id']]??0,$amount);
                    if($a['activity']==='internal'){$used[$a['counterpart_line_id']]=accounting_add($used[$a['counterpart_line_id']]??0,$amount);continue;}
                    if($a['activity']==='investing')$investing=accounting_add($investing,$signed);elseif($a['activity']==='financing')$financing=accounting_add($financing,$signed);else $assignedOperating=accounting_add($assignedOperating,$signed);
                    if($kind!=='ordinary'&&$a['activity']==='operating')$reclass=accounting_add($reclass,$signed);
                    $counter=$allLines[$a['counterpart_line_id']]??null;
                    if($counter&&$kind==='ordinary'&&($maps[(int)$counter['account_id']]['flow']??'')==='review'&&in_array($accounts[(int)$counter['account_id']]['Account_Type'],['Income','Expense'],true)&&$a['activity']!=='operating')$reclass=accounting_add($reclass,-$signed);
                    elseif($counter&&$kind==='ordinary'&&($maps[(int)$counter['account_id']]['flow']??'')==='review'&&!in_array($accounts[(int)$counter['account_id']]['Account_Type'],['Income','Expense'],true)&&$a['activity']==='operating')$reclass=accounting_add($reclass,$signed);
                }
                foreach($cashLines as $id=>$line)if(($used[$id]??0)!==abs(statement_line_net($line)))$issues[]='Complete the cash allocation for transaction #'.$jid.'.';
            }elseif(!$other){if($cashNet!==0)$issues[]='Review the cash transfer in transaction #'.$jid.'.';}
            elseif($kind!=='ordinary')$issues[]='Confirm the cash meaning of the opening/closing entry #'.$jid.' in reporting setup.';
            else{
                $activities=[];$sameDirection=false;
                foreach($other as $id=>$line){$flow=$maps[(int)$line['account_id']]['flow']??'';$net=statement_line_net($line);if($net!==0&&$cashNet!==0&&(($net>0)===($cashNet>0)))$sameDirection=true;
                    if($flow==='investing'){$investing=accounting_add($investing,-$net);$activities['investing']=true;}
                    elseif($flow==='financing'){$financing=accounting_add($financing,-$net);$activities['financing']=true;}
                    elseif($flow==='review'||$flow==='')$issues[]='Allocate the mixed or unmapped movement in transaction #'.$jid.'.';
                    else $activities['operating']=true;
                }
                if(($cashPositive&&$cashNegative)||($sameDirection&&count($activities)>1))$issues[]='Allocate the mixed cash/noncash movements in transaction #'.$jid.'.';
            }
        }elseif($kind==='ordinary'){
            $partial=accounting_add(accounting_add($result,$noncash),accounting_add($wc,$reclass));
            if($partial!==0&&!array_filter($reclasses,fn($a)=>isset($other[$a['from_line_id']],$other[$a['to_line_id']]))){
                // Two opposite-side noncash lines uniquely identify the bridge. This is
                // line provenance, not a residual inserted to make totals agree.
                if(count($other)===2){$operating=array_filter($other,fn($line)=>statement_operating_contribution($line,$accounts,$maps,$cash)!==0);
                    if(count($operating)===1){$line=array_values($operating)[0];$adjustment=-statement_operating_contribution($line,$accounts,$maps,$cash);$reclass=accounting_add($reclass,$adjustment);$disclosures[]=['journal_id'=>(int)$jid,'label'=>'Noncash counterpart transfer','amount'=>accounting_decimal(abs($adjustment)),'source_line_id'=>(int)$line['line_id']];}
                }
            }
            foreach($other as $line)if(in_array($maps[(int)$line['account_id']]['flow']??'',['investing','financing'],true))$disclosures[]=['journal_id'=>(int)$jid,'label'=>'Noncash investing / financing movement','account_id'=>(int)$line['account_id'],'amount'=>accounting_decimal(statement_line_net($line))];
        }
        $operating=accounting_add(accounting_add($result,$noncash),accounting_add($wc,$reclass));
        if($explicit&&$operating!==$assignedOperating)$issues[]='The indirect operating bridge needs review for transaction #'.$jid.'.';
        if(accounting_add(accounting_add($operating,$investing),$financing)!==$cashNet)$issues[]='Cash movements do not reconcile for transaction #'.$jid.'. Complete its reporting classification.';
        foreach(['result'=>$result,'noncash_adjustments'=>$noncash,'working_capital'=>$wc,'reclassifications'=>$reclass,'operating'=>$operating,'investing'=>$investing,'financing'=>$financing] as $key=>$amount)$totals[$key]=accounting_add($totals[$key],$amount);
        $bridges[]=['journal_id'=>(int)$jid,'cash_change'=>accounting_decimal($cashNet),'operating'=>accounting_decimal($operating),'investing'=>accounting_decimal($investing),'financing'=>accounting_decimal($financing),'reporting_purpose'=>$kind];
    }
    $net=accounting_add(accounting_add($totals['operating'],$totals['investing']),$totals['financing']);$difference=accounting_add(accounting_add($opening,$net),-$closing);
    if($difference!==0)$issues[]='Opening cash plus the reported cash flows does not equal closing cash. No balancing amount was added.';
    $out=[];foreach($totals as $key=>$value)$out[$key]=accounting_decimal($value);
    return $out+['opening_cash'=>accounting_decimal($opening),'closing_cash'=>accounting_decimal($closing),'net_cash_flow'=>accounting_decimal($net),'difference'=>accounting_decimal($difference),'complete'=>!$issues,'issues'=>array_values(array_unique($issues)),'transaction_bridge'=>$bridges,'noncash_disclosures'=>$disclosures];
}
function financial_report_model(PDO $pdo,int $uid,string $month,?string $cutoff=null): array
{
    [$from,$to]=reporting_month_range($month);
    if($cutoff===null)$cutoff=reporting_live_cutoff($month);
    if($cutoff!==null){try{$cutoff=accounting_date($cutoff);}catch(Throwable $e){throw new ReportingProblem('Choose a valid report cutoff.',400);}if($cutoff<$from||$cutoff>$to)throw new ReportingProblem('Keep the cutoff within the selected report month.',400);$to=$cutoff;}
    return accounting_read($pdo,function()use($pdo,$uid,$month,$from,$to){
        reporting_actor($pdo,$uid);report_configuration_require($pdo);$config=report_configuration_current($pdo,$from);$issues=[];$accounts=[];$state=[];$sourceValid=true;
        foreach(accounting_accounts($pdo) as $a){$id=(int)$a['CategoryID'];$accounts[$id]=$a;$state[$id]=['opening'=>0,'ending'=>0,'debit'=>0,'credit'=>0,'books'=>['CRB'=>[0,0],'CDB'=>[0,0],'GJ'=>[0,0]],'recognized'=>0];}
        $settings=$config['payload']??report_configuration_empty($from);$maps=array_column($settings['mappings'],null,'account_id');$designations=array_column($settings['designations'],null,'journal_id');
        if(!$config)$issues[]='Complete reporting setup for this month before treating these reports as complete.';
        $q=$pdo->prepare("SELECT DISTINCT l.account_id FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id WHERE j.status='posted' AND j.entry_date<=?");$q->execute([$to]);$relevant=array_fill_keys(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),true);$labels=[];foreach($accounts as $id=>$a)if(isset($relevant[$id]))$labels[$id]=array_intersect_key($a,array_flip(['CategoryID','Name','Account_Code','Account_Type']));
        $hash=hash_init('sha256');hash_update($hash,reporting_json(['reports_v2',$from,$to,$labels,$config?['id'=>$config['id'],'hash'=>$config['payload_hash']]:null]));
        $corrections=[];$reversals=[];$reverseMaps=[];
        $q=$pdo->prepare('SELECT * FROM journal_corrections WHERE accounting_date<=? ORDER BY id');$q->execute([$to]);
        foreach($q as $c){$corrections[]=$c;if(!stage3_correction_valid($pdo,$c)){$issues[]='Correction #'.$c['id'].' needs an integrity review.';$sourceValid=false;}$reversals[(int)$c['reversal_journal_id']]=(int)$c['target_journal_id'];$s=$pdo->prepare('SELECT original_line_id,reversal_line_id FROM journal_correction_lines WHERE correction_id=?');$s->execute([$c['id']]);$reverseMaps[(int)$c['reversal_journal_id']]=$s->fetchAll(PDO::FETCH_KEY_PAIR);}
        hash_update($hash,reporting_json($corrections));$settings['_reversal_line_map']=$reverseMaps;
        $s=$pdo->prepare("SELECT j.id AS journal_id,j.entry_date,j.reference,j.description,j.source_book,j.transaction_kind,j.posted_by_user_id,
            l.id AS line_id,l.account_id,l.debit_amount,l.credit_amount,l.fund_project_id,l.project_code_snapshot,l.project_name_snapshot
            FROM journal_entries j LEFT JOIN journal_entry_lines l ON l.journal_entry_id=j.id
            WHERE j.status='posted' AND j.entry_date<? ORDER BY j.entry_date,j.id,l.id");$s->execute([accounting_next_day($to)]);
        $allLines=[];$period=[];$kinds=[];$totals=[];$projects=[];$cashSet=array_fill_keys($settings['cash_accounts'],true);$openingCash=$closingCash=0;
        while($row=$s->fetch(PDO::FETCH_ASSOC)){
            hash_update($hash,reporting_json($row));$jid=(int)$row['journal_id'];$kind=$kinds[$jid]??=statement_kind($jid,$designations,$reversals);
            $totals[$jid]??=['count'=>0,'debit'=>0,'credit'=>0,'invalid'=>false];$id=(int)($row['account_id']??0);
            if($row['line_id']===null||!isset($accounts[$id])){$totals[$jid]['invalid']=true;continue;}
            $debit=accounting_cents((string)$row['debit_amount']);$credit=accounting_cents((string)$row['credit_amount']);$net=accounting_add($debit,-$credit);
            $totals[$jid]['count']++;$totals[$jid]['debit']=accounting_add($totals[$jid]['debit'],$debit);$totals[$jid]['credit']=accounting_add($totals[$jid]['credit'],$credit);
            if(!(($debit>0&&$credit===0)||($credit>0&&$debit===0))||!in_array($accounts[$id]['Account_Type'],ACCOUNTING_TYPES,true))$totals[$jid]['invalid']=true;
            $line=$row+['journal_entry_id'=>$jid];$allLines[(int)$row['line_id']]=$line;$state[$id]['ending']=accounting_add($state[$id]['ending'],$net);
            if(isset($cashSet[$id]))$closingCash=accounting_add($closingCash,$net);
            if($row['entry_date']<$from){$state[$id]['opening']=accounting_add($state[$id]['opening'],$net);if(isset($cashSet[$id]))$openingCash=accounting_add($openingCash,$net);continue;}
            $book=$row['source_book']??'GJ';if(!isset($state[$id]['books'][$book])){$issues[]='Choose the originating book for transaction #'.$jid.'.';$book='GJ';$sourceValid=false;}
            $state[$id]['debit']=accounting_add($state[$id]['debit'],$debit);$state[$id]['credit']=accounting_add($state[$id]['credit'],$credit);$state[$id]['books'][$book][0]=accounting_add($state[$id]['books'][$book][0],$debit);$state[$id]['books'][$book][1]=accounting_add($state[$id]['books'][$book][1],$credit);
            if($kind==='ordinary')$state[$id]['recognized']=accounting_add($state[$id]['recognized'],$net);
            $period[$jid]??=['entry_date'=>$row['entry_date'],'lines'=>[]];$period[$jid]['lines'][]=$line;
            $bucket=$row['fund_project_id']===null?'operations':'project:'.$row['fund_project_id'];$pk=$bucket.':'.$id;$projects[$pk]??=['bucket'=>$bucket,'project_id'=>$row['fund_project_id'],'project_name'=>$row['project_name_snapshot']??'Organization operations','project_code'=>$row['project_code_snapshot'],'account_id'=>$id,'account_name'=>$accounts[$id]['Name'],'debit'=>0,'credit'=>0,'recognized_expense'=>0];
            $projects[$pk]['debit']=accounting_add($projects[$pk]['debit'],$debit);$projects[$pk]['credit']=accounting_add($projects[$pk]['credit'],$credit);if($kind==='ordinary'&&$accounts[$id]['Account_Type']==='Expense')$projects[$pk]['recognized_expense']=accounting_add($projects[$pk]['recognized_expense'],$net);
        }
        foreach($totals as $jid=>$t)if($t['invalid']||$t['count']<2||$t['debit']<=0||$t['debit']!==$t['credit']){$issues[]='Recorded transaction #'.$jid.' needs an integrity review.';$sourceValid=false;}
        foreach($allLines as $line)if($line['transaction_kind']==='correction_reversal'&&!isset($reversals[(int)$line['journal_id']])){$issues[]='A generated reversal needs its correction link restored for review.';$sourceValid=false;}
        $worksheet=[];$income=[];$position=[];$totalD=$totalC=$incomeTotal=$expenseTotal=$assets=$liabilities=$equity=$unclosed=0;
        foreach($accounts as $id=>$a){$v=$state[$id];$type=$a['Account_Type'];$map=$maps[$id]??null;
            if(!isset($relevant[$id])&&$map===null)continue;
            if(($v['opening']!==0||$v['ending']!==0||$v['debit']!==0||$v['credit']!==0)&&(!$map||trim($map['section'])===''||(!isset($cashSet[$id])&&($map['flow']??'')==='')))$issues[]='Complete the statement section and cash-flow treatment for '.$a['Name'].'.';
            $row=['account_id'=>$id,'account_code'=>$a['Account_Code'],'account_name'=>$a['Name'],'account_type'=>$type,'is_active'=>(int)$a['Is_Active'],'section'=>$map['section']??'Needs setup','opening_debit'=>accounting_decimal(max(0,$v['opening'])),'opening_credit'=>accounting_decimal(max(0,-$v['opening'])),'ending_debit'=>accounting_decimal(max(0,$v['ending'])),'ending_credit'=>accounting_decimal(max(0,-$v['ending'])),'ending_net'=>accounting_decimal($v['ending']),'period_debit'=>accounting_decimal($v['debit']),'period_credit'=>accounting_decimal($v['credit']),'recognized_net'=>accounting_decimal($v['recognized'])];
            foreach($v['books'] as $book=>[$d,$c]){$row[$book.'_debit']=accounting_decimal($d);$row[$book.'_credit']=accounting_decimal($c);}
            $row['statement_debit']=accounting_decimal(in_array($type,['Income','Expense'],true)?max(0,$v['recognized']):0);$row['statement_credit']=accounting_decimal(in_array($type,['Income','Expense'],true)?max(0,-$v['recognized']):0);
            $row['position_debit']=accounting_decimal(in_array($type,['Asset','Liability','Equity'],true)?max(0,$v['ending']):0);$row['position_credit']=accounting_decimal(in_array($type,['Asset','Liability','Equity'],true)?max(0,-$v['ending']):0);
            $row['excluded_or_prior_result_bridge']=accounting_decimal(in_array($type,['Income','Expense'],true)?accounting_add($v['ending'],-$v['recognized']):0);$worksheet[]=$row;
            $totalD=accounting_add($totalD,max(0,$v['ending']));$totalC=accounting_add($totalC,max(0,-$v['ending']));
            if(in_array($type,['Income','Expense'],true)){$amount=$type==='Income'?-$v['recognized']:$v['recognized'];$income[]=$row+['amount'=>accounting_decimal($amount)];if($type==='Income'){$incomeTotal=accounting_add($incomeTotal,$amount);$unclosed=accounting_add($unclosed,-$v['ending']);}else{$expenseTotal=accounting_add($expenseTotal,$amount);$unclosed=accounting_add($unclosed,-$v['ending']);}}
            elseif($type==='Asset'){$assets=accounting_add($assets,$v['ending']);$position[]=$row+['amount'=>accounting_decimal($v['ending'])];}
            elseif($type==='Liability'){$liabilities=accounting_add($liabilities,-$v['ending']);$position[]=$row+['amount'=>accounting_decimal(-$v['ending'])];}
            elseif($type==='Equity'){$equity=accounting_add($equity,-$v['ending']);$position[]=$row+['amount'=>accounting_decimal(-$v['ending'])];}
        }
        $positionDifference=accounting_add($assets,-accounting_add(accounting_add($liabilities,$equity),$unclosed));
        $worksheetKeys=['opening_debit','opening_credit','CRB_debit','CRB_credit','CDB_debit','CDB_credit','GJ_debit','GJ_credit','ending_debit','ending_credit','statement_debit','statement_credit','position_debit','position_credit','excluded_or_prior_result_bridge'];$worksheetTotals=array_fill_keys($worksheetKeys,0);foreach($worksheet as $row)foreach($worksheetKeys as $key)$worksheetTotals[$key]=accounting_add($worksheetTotals[$key],accounting_cents($row[$key]));
        $result=accounting_add($incomeTotal,-$expenseTotal);$worksheetAdjustments=['statement_debit'=>accounting_decimal(max(0,$result)),'statement_credit'=>accounting_decimal(max(0,-$result)),'position_debit'=>accounting_decimal(max(0,-$unclosed)),'position_credit'=>accounting_decimal(max(0,$unclosed))];
        foreach(['statement_debit','statement_credit','position_debit','position_credit'] as $key)$worksheetTotals[$key]=accounting_add($worksheetTotals[$key],accounting_cents($worksheetAdjustments[$key]));
        if($worksheetTotals['statement_debit']!==$worksheetTotals['statement_credit']||$worksheetTotals['position_debit']!==$worksheetTotals['position_credit'])$issues[]='The worksheet statement columns need reconciliation.';
        foreach($worksheetTotals as &$v)$v=accounting_decimal($v);unset($v);
        if($totalD!==$totalC||$positionDifference!==0)$issues[]='The posted balances do not reconcile. No balancing amount was added.';
        $controls=array_fill_keys(array_map('intval',$pdo->query('SELECT account_id FROM advance_control_designations')->fetchAll(PDO::FETCH_COLUMN)),true);
        // Exceptional future settlement lines may identify the purpose of an earlier cash release.
        foreach($settings['exception_lines']??[] as $line){$id=(int)$line['id'];if(!isset($allLines[$id])){$s=$pdo->prepare('SELECT l.*,j.entry_date,j.status,j.transaction_kind FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id WHERE l.id=?');$s->execute([$id]);$current=$s->fetch(PDO::FETCH_ASSOC);if(!$current||reporting_json($current)!==reporting_json($line))$issues[]='A reporting-allocation dependency needs review.';else{$current['line_id']=$id;$allLines[$id]=$current;}}}
        foreach($settings['advance_dependencies']??[] as $dependency){$releaseDate=$pdo->prepare('SELECT entry_date FROM journal_entries WHERE id=?');$releaseDate->execute([$dependency['release_journal']]);$dependencyDate=$releaseDate->fetchColumn();if(!$dependencyDate){$issues[]='An advance investment release needs an integrity review.';$sourceValid=false;continue;}if($dependencyDate>$to)continue;foreach([$dependency['release_journal'],$dependency['liquidation_journal']] as $jid){$s=$pdo->prepare('SELECT l.*,j.entry_date,j.status,j.transaction_kind FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id WHERE l.journal_entry_id=?');$s->execute([$jid]);foreach($s as $line){$line['line_id']=(int)$line['id'];$allLines[(int)$line['id']]??=$line;}
            $s=$pdo->prepare('SELECT * FROM journal_corrections WHERE target_journal_id=? ORDER BY id');$s->execute([$jid]);$changes=$s->fetchAll(PDO::FETCH_ASSOC);hash_update($hash,reporting_json($changes));if($changes)$issues[]='An advance investment trace was corrected. Review its replacement lines in reporting setup.';
        }}
        $flows=statement_cash_flows($period,$accounts,$settings,$kinds,$reversals,$allLines,$controls,$openingCash,$closingCash);$issues=array_merge($issues,$flows['issues']);
        $aging=advance_state($pdo,$to);foreach($aging['reconciliation'] as $r)if(!$r['ok']){$issues[]='Advance control balances need reconciliation before reports can be complete.';$sourceValid=false;}
        $agingRows=[];foreach($aging['advances'] as $raw){$a=advance_summary($raw);$agingRows[]=['advance_id'=>$a['id'],'number'=>$a['number'],'employee'=>$a['employee'],'purpose'=>$a['purpose'],'due_date'=>$a['due_date'],'outstanding'=>$a['outstanding'],'released'=>$a['released'],'liquidated'=>$a['liquidated'],'returned'=>$a['returned'],'settlement_status'=>$a['status'],'overdue_days'=>$a['overdue_days'],'aging_bucket'=>$a['aging_bucket']];}
        $s=$pdo->prepare('SELECT e.id,e.advance_id,e.old_due_date,e.new_due_date,e.effective_date,e.created_by,e.created_at FROM cash_advance_due_changes e JOIN cash_advances a ON a.id=e.advance_id JOIN journal_entries j ON j.id=a.release_journal_id WHERE e.effective_date<=? AND j.entry_date<=? ORDER BY e.effective_date,e.id');$s->execute([$to,$to]);hash_update($hash,reporting_json([$agingRows,$s->fetchAll(PDO::FETCH_ASSOC)]));
        foreach($projects as &$row)foreach(['debit','credit','recognized_expense'] as $key)$row[$key]=accounting_decimal($row[$key]);unset($row);
        $cashBridge=null;
        if($config&&$from===$settings['effective_from']&&isset($settings['cash_comparison'])){$oldOpening=0;foreach($settings['cash_comparison']['cash_accounts'] as $id)$oldOpening=accounting_add($oldOpening,$state[$id]['opening']??0);$cashBridge=['prior_cash_set_opening'=>accounting_decimal($oldOpening),'membership_change'=>accounting_decimal(accounting_add($openingCash,-$oldOpening)),'comparable_opening'=>accounting_decimal($openingCash),'explanation'=>$settings['cash_change_reason']];if(trim($settings['cash_change_reason'])==='')$issues[]='Explain the change in cash accounts before completing the report.';}
        return ['month'=>$month,'from'=>$from,'as_of'=>$to,'source_valid'=>$sourceValid,'core_complete'=>!$issues,'bundle_submitted'=>false,'issues'=>array_values(array_unique($issues)),
            'configuration'=>$config?['id'=>(int)$config['id'],'revision'=>(int)$config['revision_number'],'effective_from'=>$config['effective_from'],'year_start_month'=>$settings['year_start_month'],'prepared_by'=>$settings['prepared_name']??'','hash'=>$config['payload_hash'],'cash_accounts'=>array_values(array_map(fn($id)=>['account_id'=>$id,'account_name'=>$accounts[$id]['Name']??'Unavailable account'],$settings['cash_accounts']))]:null,
            'worksheet'=>$worksheet,'worksheet_totals'=>$worksheetTotals,'worksheet_derived_results'=>$worksheetAdjustments,'trial_balance'=>['rows'=>$worksheet,'total_debits'=>accounting_decimal($totalD),'total_credits'=>accounting_decimal($totalC),'difference'=>accounting_decimal(accounting_add($totalD,-$totalC))],
            'income_statement'=>['rows'=>$income,'income'=>accounting_decimal($incomeTotal),'expenses'=>accounting_decimal($expenseTotal),'surplus_deficit'=>accounting_decimal(accounting_add($incomeTotal,-$expenseTotal))],
            'financial_position'=>['rows'=>$position,'assets'=>accounting_decimal($assets),'liabilities'=>accounting_decimal($liabilities),'posted_equity'=>accounting_decimal($equity),'unclosed_accumulated_result'=>accounting_decimal($unclosed),'difference'=>accounting_decimal($positionDifference)],
            'cash_flows'=>$flows,'cash_set_bridge'=>$cashBridge,'project_activity'=>array_values($projects),'advance_aging'=>$agingRows,'fingerprint'=>hash_final($hash),'basis'=>'reports_v2'];
    });
}
