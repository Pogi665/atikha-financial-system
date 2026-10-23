<?php
/** Owner-private reporting setup drafts and immutable effective revisions. */
require_once __DIR__.'/reporting_common.php';
const REPORT_FLOW_LABELS=[
    'operating'=>'Operating income or cost','operating_asset'=>'Operating asset / working capital',
    'operating_liability'=>'Operating liability / working capital','investing'=>'Investing activity',
    'financing'=>'Financing activity','noncash_expense'=>'Noncash cost (for example depreciation)',
    'noncash_income'=>'Noncash income','review'=>'Mixed activity - review individual movements',
];
function report_configuration_schema(PDO $pdo): array
{
    $tables=['report_configuration_drafts','report_configuration_revisions','report_configuration_state'];
    $s=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('report_configuration_drafts','report_configuration_revisions','report_configuration_state')");$found=$s->fetchAll(PDO::FETCH_COLUMN);
    if(!$found)return ['state'=>'absent','complete'=>false];
    if(count($found)!==3)return ['state'=>'partial','complete'=>false];
    try{
        $pdo->query('SELECT id,owner_id,revision,request_key,creation_hash,payload,state,base_revision_id,submitted_revision_id,created_at,updated_at FROM report_configuration_drafts LIMIT 0');
        $pdo->query('SELECT id,revision_number,draft_id,effective_from,parent_revision_id,payload,payload_hash,draft_hash,prepared_by,prepared_at FROM report_configuration_revisions LIMIT 0');
        $row=$pdo->query('SELECT id,schema_version,current_revision_id,next_sequence FROM report_configuration_state WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if(!$row||(int)$row['schema_version']!==1||(int)$row['next_sequence']<1)return ['state'=>'partial','complete'=>false];
        $max=(int)$pdo->query('SELECT COALESCE(MAX(revision_number),0) FROM report_configuration_revisions')->fetchColumn();
        if((int)$row['next_sequence']!==$max+1||($max>0&&$row['current_revision_id']===null)||($max===0&&$row['current_revision_id']!==null))return ['state'=>'partial','complete'=>false];
        if($max>0){$s=$pdo->prepare('SELECT revision_number FROM report_configuration_revisions WHERE id=?');$s->execute([$row['current_revision_id']]);if((int)$s->fetchColumn()!==$max)return ['state'=>'partial','complete'=>false];}
        foreach(['report_configuration_drafts'=>[['owner_id','request_key']],'report_configuration_revisions'=>[['revision_number'],['draft_id']]] as $table=>$required){$indexes=[];foreach($pdo->query('SHOW INDEX FROM '.$table)->fetchAll(PDO::FETCH_ASSOC) as $i)if((int)$i['Non_unique']===0)$indexes[$i['Key_name']][(int)$i['Seq_in_index']]=$i['Column_name'];$unique=[];foreach($indexes as $columns){ksort($columns);$unique[]=array_values($columns);}foreach($required as $columns)if(!in_array($columns,$unique,true))return ['state'=>'partial','complete'=>false];}
        $expected=['r5_config_revision_no_update'=>['report_configuration_revisions','UPDATE'],'r5_config_revision_no_delete'=>['report_configuration_revisions','DELETE'],'r5_config_draft_update'=>['report_configuration_drafts','UPDATE'],'r5_config_draft_delete'=>['report_configuration_drafts','DELETE'],'r5_config_state_delete'=>['report_configuration_state','DELETE']];
        $triggers=[];foreach($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $t)$triggers[$t['Trigger']]=$t;
        foreach($expected as $name=>[$table,$event]){if(!isset($triggers[$name]))return ['state'=>'partial','complete'=>false];$t=$triggers[$name];if($t['Table']!==$table||$t['Event']!==$event||$t['Timing']!=='BEFORE'||!preg_match('/SIGNAL\s+SQLSTATE\s+[\x27\x22]45000/i',$t['Statement']))return ['state'=>'partial','complete'=>false];}
        $bodies=[
            'r5_config_revision_no_update'=>"BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Published report configuration is immutable'; END",
            'r5_config_revision_no_delete'=>"BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Published report configuration is immutable'; END",
            'r5_config_draft_update'=>"BEGIN IF OLD.state='Submitted' OR NEW.owner_id<>OLD.owner_id OR NEW.request_key<>OLD.request_key OR NOT (NEW.base_revision_id <=> OLD.base_revision_id) OR NEW.creation_hash<>OLD.creation_hash OR NEW.created_at<>OLD.created_at THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted configuration and draft identity are immutable'; END IF; END",
            'r5_config_draft_delete'=>"BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Report setup draft history is retained'; END",
            'r5_config_state_delete'=>"BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reporting coordination state is required'; END",
        ];$normalize=static fn($v)=>strtolower(preg_replace('/[\s`]+/u','',$v));foreach($bodies as $name=>$body)if($normalize($triggers[$name]['Statement'])!==$normalize($body))return ['state'=>'partial','complete'=>false];
        $s=$pdo->query("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'r5_config_%' AND REFERENCED_TABLE_NAME IS NOT NULL");$keys=[];
        foreach($s as $k)$keys[$k['CONSTRAINT_NAME']]=[$k['TABLE_NAME'],$k['COLUMN_NAME'],$k['REFERENCED_TABLE_NAME'],$k['REFERENCED_COLUMN_NAME']];
        foreach(['r5_config_owner'=>['report_configuration_drafts','owner_id','user_identities','UserID'],'r5_config_source_draft'=>['report_configuration_revisions','draft_id','report_configuration_drafts','id'],'r5_config_parent'=>['report_configuration_revisions','parent_revision_id','report_configuration_revisions','id'],'r5_config_preparer'=>['report_configuration_revisions','prepared_by','user_identities','UserID'],'r5_config_submitted_revision'=>['report_configuration_drafts','submitted_revision_id','report_configuration_revisions','id'],'r5_config_base_revision'=>['report_configuration_drafts','base_revision_id','report_configuration_revisions','id'],'r5_config_current'=>['report_configuration_state','current_revision_id','report_configuration_revisions','id']] as $name=>$shape)if(($keys[$name]??null)!==$shape)return ['state'=>'partial','complete'=>false];
        return ['state'=>'complete','complete'=>true];
    }catch(PDOException $e){return ['state'=>'partial','complete'=>false];}
}
function report_configuration_require(PDO $pdo): void
{
    if(!stage1_schema($pdo)||!stage2_schema($pdo)||!stage3_schema($pdo)||!report_configuration_schema($pdo)['complete'])throw new ReportingProblem('Reporting setup is unavailable until deployment is complete.',503);
}
function report_configuration_payload(array $r): array
{
    $from=reporting_text($r['effective_from']??'',10,'setup start date',true);
    try{accounting_date($from);}catch(Throwable $e){throw new ReportingProblem('Choose a valid setup start month.',400);}
    if(substr($from,8,2)!=='01')throw new ReportingProblem('Setup starts on the first day of a month.',400);
    $yearStart=$r['year_start_month']??'1';if(is_int($yearStart))$yearStart=(string)$yearStart;
    if(!is_string($yearStart)||!in_array($yearStart,array_map('strval',range(1,12)),true))throw new ReportingProblem('Choose a valid reporting-year start month.',400);
    $out=['effective_from'=>$from,'year_start_month'=>$yearStart,'cash_change_reason'=>reporting_text($r['cash_change_reason']??'',2000,'cash account change explanation'),'cash_accounts'=>[],'mappings'=>[],'designations'=>[],'cash_allocations'=>[],'noncash_reclasses'=>[]];
    foreach(['cash_accounts'=>1000,'mappings'=>2000,'designations'=>2000,'cash_allocations'=>2000,'noncash_reclasses'=>2000] as $key=>$limit)if(!is_array($r[$key]??null)||!array_is_list($r[$key])||count($r[$key])>$limit)throw new ReportingProblem('Use a valid list of '.$key.'.',400);
    $seen=[];foreach($r['cash_accounts'] as $id){$id=reporting_id($id,'cash account');if(isset($seen[$id]))throw new ReportingProblem('A cash account is selected twice.',400);$seen[$id]=true;$out['cash_accounts'][]=$id;}sort($out['cash_accounts'],SORT_NUMERIC);
    $seen=[];foreach($r['mappings'] as $m){if(!is_array($m))throw new ReportingProblem('Use valid account mappings.',400);$id=reporting_id($m['account_id']??null,'account');if(isset($seen[$id]))throw new ReportingProblem('Map each account once.',400);$seen[$id]=true;$flow=reporting_text($m['flow']??'',32,'cash-flow treatment');if($flow!==''&&!isset(REPORT_FLOW_LABELS[$flow]))throw new ReportingProblem('Choose a listed cash-flow treatment.',400);$out['mappings'][]=['account_id'=>$id,'section'=>reporting_text($m['section']??'',100,'statement section'),'flow'=>$flow];}
    usort($out['mappings'],fn($a,$b)=>$a['account_id']<=>$b['account_id']);
    $seen=[];foreach($r['designations'] as $d){if(!is_array($d))throw new ReportingProblem('Use valid reporting exceptions.',400);$id=reporting_id($d['journal_id']??null,'transaction');if(isset($seen[$id]))throw new ReportingProblem('Designate each transaction once.',400);$seen[$id]=true;$kind=reporting_text($d['kind']??'',16,'reporting purpose');if(!in_array($kind,['ordinary','opening','closing'],true))throw new ReportingProblem('Choose Ordinary, Opening or Closing.',400);$out['designations'][]=['journal_id'=>$id,'kind'=>$kind,'reason'=>reporting_text($d['reason']??'',2000,'reporting-purpose reason',true)];}
    usort($out['designations'],fn($a,$b)=>$a['journal_id']<=>$b['journal_id']);
    foreach(['cash_allocations','noncash_reclasses'] as $key){$seen=[];foreach($r[$key] as $a){if(!is_array($a))throw new ReportingProblem('Use valid transaction allocations.',400);$first=$key==='cash_allocations'?'cash_line_id':'from_line_id';$second=$key==='cash_allocations'?'counterpart_line_id':'to_line_id';$one=reporting_id($a[$first]??null,'source line');$two=reporting_id($a[$second]??null,'counterpart line');$pair=$one.':'.$two;if($one===$two||isset($seen[$pair]))throw new ReportingProblem('Use distinct source and counterpart lines without duplicate pairs.',400);$seen[$pair]=true;$row=[$first=>$one,$second=>$two,'amount'=>reporting_amount($a['amount']??'','allocation amount',true),'reason'=>reporting_text($a['reason']??'',2000,'allocation reason',true)];if($key==='cash_allocations'){$activity=reporting_text($a['activity']??'',16,'cash-flow activity',true);if(!in_array($activity,['operating','investing','financing','internal'],true))throw new ReportingProblem('Choose an available cash-flow activity.',400);$row['activity']=$activity;}$out[$key][]=$row;}usort($out[$key],fn($a,$b)=>strcmp(reporting_json($a),reporting_json($b)));}
    if(strlen(reporting_json($out))>1048576)throw new ReportingProblem('Reporting setup is too large.',400);return $out;
}
function report_configuration_empty(?string $from=null): array
{
    return ['effective_from'=>$from??substr(accounting_today(),0,7).'-01','year_start_month'=>'1','cash_change_reason'=>'','cash_accounts'=>[],'mappings'=>[],'designations'=>[],'cash_allocations'=>[],'noncash_reclasses'=>[]];
}
function report_configuration_current(PDO $pdo,string $asOf): ?array
{
    accounting_date($asOf);$s=$pdo->prepare('SELECT * FROM report_configuration_revisions WHERE effective_from<=? ORDER BY effective_from DESC,revision_number DESC LIMIT 1');$s->execute([$asOf]);$r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r)return null;$r['payload']=json_decode($r['payload'],true,64,JSON_THROW_ON_ERROR);
    if(!hash_equals($r['payload_hash'],hash('sha256',reporting_json($r['payload']))))throw new ReportingProblem('The saved reporting setup needs an integrity review.',503);return $r;
}
function report_configuration_draft(PDO $pdo,int $uid,int $id,bool $lock=false): array
{
    reporting_actor($pdo,$uid,['Admin']);
    $s=$pdo->prepare('SELECT * FROM report_configuration_drafts WHERE id=? AND owner_id=?'.($lock?' FOR UPDATE':''));$s->execute([$id,$uid]);$d=$s->fetch(PDO::FETCH_ASSOC);
    if(!$d)throw new ReportingProblem('That private setup draft is unavailable.',404);$d['payload']=json_decode($d['payload'],true,64,JSON_THROW_ON_ERROR);$d['payload_hash']=hash('sha256',reporting_json($d['payload']));return $d;
}
function report_configuration_save(PDO $pdo,int $uid,array $r): array
{
    reporting_csrf($r);report_configuration_require($pdo);if(!is_array($r['payload']??null))throw new ReportingProblem('Use a valid setup draft.',400);$payload=report_configuration_payload($r['payload']);$key=reporting_text($r['request_key']??'',64,'draft identity',true);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$key))throw new ReportingProblem('Reload the setup draft and try again.',400);
    $id=reporting_id($r['draft_id']??'','draft',true);$hash=hash('sha256',reporting_json($payload));
    return reporting_tx($pdo,$uid,['Admin'],function()use($pdo,$uid,$r,$payload,$key,$id,$hash){
        report_configuration_require($pdo);$root=$pdo->query('SELECT * FROM report_configuration_state WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if($id===null){
            $s=$pdo->prepare('SELECT id,creation_hash FROM report_configuration_drafts WHERE owner_id=? AND request_key=?');$s->execute([$uid,$key]);$old=$s->fetch(PDO::FETCH_ASSOC);
            if($old){if(!hash_equals($old['creation_hash'],$hash))throw new ReportingProblem('This draft identity was used with different contents.',409);return report_configuration_draft($pdo,$uid,(int)$old['id']);}
            $s=$pdo->prepare('INSERT INTO report_configuration_drafts(owner_id,request_key,creation_hash,payload,base_revision_id,created_at,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute([$uid,$key,$hash,reporting_json($payload),$root['current_revision_id']]);$id=(int)$pdo->lastInsertId();reporting_audit($pdo,$uid,AUDIT_ACTION_CREATE,'Report setup draft',$id,null,['state'=>'Draft']);
        }else{
            $d=report_configuration_draft($pdo,$uid,$id,true);
            if($d['request_key']!==$key||$d['state']!=='Draft'||(string)$d['revision']!==(string)($r['revision']??''))throw new ReportingProblem('This setup draft changed. Reload it before saving; your entries are retained.',409);
            if(!hash_equals($d['payload_hash'],$hash)){$s=$pdo->prepare('UPDATE report_configuration_drafts SET payload=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([reporting_json($payload),$id]);reporting_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Report setup draft',$id,['hash'=>$d['payload_hash']],['hash'=>$hash]);}
        }
        return report_configuration_draft($pdo,$uid,$id);
    });
}
/** Validate exceptional source lines without projecting private evidence/draft data. */
function report_configuration_validate(PDO $pdo,array $payload): array
{
    $accounts=[];foreach(accounting_accounts($pdo) as $a)$accounts[(int)$a['CategoryID']]=$a;
    if(!$payload['cash_accounts'])throw new ReportingProblem('Select the cash accounts before submitting setup.');
    $controls=[];if(stage1_schema($pdo))$controls=array_map('intval',$pdo->query('SELECT account_id FROM advance_control_designations')->fetchAll(PDO::FETCH_COLUMN));
    foreach($payload['cash_accounts'] as $id)if(!isset($accounts[$id])||$accounts[$id]['Account_Type']!=='Asset'||in_array($id,$controls,true))throw new ReportingProblem('Select existing asset accounts for cash; employee advance controls are not cash.');
    $mapped=[];foreach($payload['mappings'] as $m){$id=$m['account_id'];if(!isset($accounts[$id])||trim($m['section'])===''||($m['flow']===''&&!in_array($id,$payload['cash_accounts'],true)))throw new ReportingProblem('Complete the section and cash-flow treatment for each listed account.');$type=$accounts[$id]['Account_Type'];$flow=$m['flow'];
        if(($flow==='operating_asset'&&$type!=='Asset')||($flow==='operating_liability'&&$type!=='Liability')||($flow==='noncash_expense'&&$type!=='Expense')||($flow==='noncash_income'&&$type!=='Income')||($flow==='operating'&&!in_array($type,['Income','Expense'],true)))throw new ReportingProblem('Choose a cash-flow treatment consistent with the account type.');$mapped[$id]=$m;
    }
    foreach($payload['cash_accounts'] as $id)if(!isset($mapped[$id]))throw new ReportingProblem('Give every selected cash account a statement section.');
    $journalIds=array_column($payload['designations'],'journal_id');$lineIds=[];
    foreach($payload['cash_allocations'] as $a){$lineIds[]=$a['cash_line_id'];$lineIds[]=$a['counterpart_line_id'];}
    foreach($payload['noncash_reclasses'] as $a){$lineIds[]=$a['from_line_id'];$lineIds[]=$a['to_line_id'];}
    $lines=[];foreach(array_chunk(array_unique($lineIds),500) as $chunk){$s=$pdo->prepare("SELECT l.*,j.entry_date,j.status,j.transaction_kind FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id WHERE l.id IN (".implode(',',array_fill(0,count($chunk),'?')).')');$s->execute($chunk);foreach($s as $line)$lines[(int)$line['id']]=$line;}
    foreach(array_unique($lineIds) as $id)if(!isset($lines[$id])||$lines[$id]['status']!=='posted')throw new ReportingProblem('An allocation must refer to existing recorded transaction lines.');
    foreach($payload['designations'] as $designation){$id=$designation['journal_id'];$s=$pdo->prepare("SELECT source_book,transaction_kind FROM journal_entries WHERE id=? AND status='posted'");$s->execute([$id]);$j=$s->fetch(PDO::FETCH_ASSOC);if(!$j||$j['transaction_kind']==='correction_reversal')throw new ReportingProblem('Choose an original or replacement recorded transaction for a reporting purpose.');
        if($designation['kind']!=='ordinary'){
            $s=$pdo->prepare('SELECT 1 FROM cash_advance_operations WHERE journal_id=? LIMIT 1');$s->execute([$id]);
            if($s->fetchColumn()!==false||($j['source_book']!==null&&$j['source_book']!=='GJ'))throw new ReportingProblem('Opening and closing reporting purposes require ordinary General Journal entries; advance control operations keep their purpose.');
        }
    }
    $claims=[];$dependencies=[];
    foreach($payload['cash_allocations'] as $a){$cash=$lines[$a['cash_line_id']];$other=$lines[$a['counterpart_line_id']];$amount=accounting_cents($a['amount']);$cashNet=accounting_add(accounting_cents($cash['debit_amount']),-accounting_cents($cash['credit_amount']));$otherNet=accounting_add(accounting_cents($other['debit_amount']),-accounting_cents($other['credit_amount']));
        if(!in_array((int)$cash['account_id'],$payload['cash_accounts'],true)||!$cashNet||!$otherNet||(($cashNet>0)===($otherNet>0)))throw new ReportingProblem('Match a cash line with its opposite-side counterpart.');
        $internal=in_array((int)$other['account_id'],$payload['cash_accounts'],true);
        if(($a['activity']==='internal')!==$internal)throw new ReportingProblem('Internal transfers must connect two configured cash accounts.');
        if(!$internal&&$cash['journal_entry_id']===$other['journal_entry_id']){
            $flow=$mapped[(int)$other['account_id']]['flow']??'';
            $expected=['operating'=>'operating','operating_asset'=>'operating','operating_liability'=>'operating','investing'=>'investing','financing'=>'financing'][$flow]??null;
            if($flow!=='review'&&$expected!==$a['activity'])throw new ReportingProblem('Match the allocation to the configured counterpart activity, or explicitly configure a mixed account for review.');
        }
        if($cash['journal_entry_id']!==$other['journal_entry_id']){
            if($a['activity']!=='investing')throw new ReportingProblem('Cross-transaction cash allocation is limited to the supported advance investment trace.');
            $s=$pdo->prepare("SELECT r.advance_id,r.journal_id AS release_journal,l.journal_id AS liquidation_journal FROM cash_advance_operations r JOIN cash_advance_operations l ON l.advance_id=r.advance_id WHERE r.operation_kind='release' AND l.operation_kind='liquidation' AND r.journal_id=? AND l.journal_id=?");$s->execute([$cash['journal_entry_id'],$other['journal_entry_id']]);$link=$s->fetch(PDO::FETCH_ASSOC);
            if(!$link||$cashNet>=0||$otherNet<=0||($mapped[(int)$other['account_id']]['flow']??'')!=='investing')throw new ReportingProblem('Trace investment cash to its actual advance release and eligible purchase.');
            $s=$pdo->prepare('SELECT id FROM journal_corrections WHERE target_journal_id IN (?,?) LIMIT 1');$s->execute([$cash['journal_entry_id'],$other['journal_entry_id']]);
            if($s->fetchColumn()!==false)throw new ReportingProblem('This advance investment trace was corrected. Select the effective replacement transaction lines before submitting setup.');$dependencies[]=$link;
        }
        foreach([$a['cash_line_id'],$a['counterpart_line_id']] as $id){$claims[$id]=accounting_add($claims[$id]??0,$amount);$line=$lines[$id];$gross=max(accounting_cents($line['debit_amount']),accounting_cents($line['credit_amount']));if($claims[$id]>$gross)throw new ReportingProblem('Allocated amounts exceed a source line.');}
    }
    $noncashClaims=[];foreach($payload['noncash_reclasses'] as $a){$one=$lines[$a['from_line_id']];$two=$lines[$a['to_line_id']];$amount=accounting_cents($a['amount']);$oneNet=accounting_add(accounting_cents($one['debit_amount']),-accounting_cents($one['credit_amount']));$twoNet=accounting_add(accounting_cents($two['debit_amount']),-accounting_cents($two['credit_amount']));
        if($one['journal_entry_id']!==$two['journal_entry_id']||in_array((int)$one['account_id'],$payload['cash_accounts'],true)||in_array((int)$two['account_id'],$payload['cash_accounts'],true)||!$oneNet||!$twoNet||(($oneNet>0)===($twoNet>0)))throw new ReportingProblem('Match noncash opposite-side lines in the same transaction.');
        foreach([$a['from_line_id'],$a['to_line_id']] as $id){$noncashClaims[$id]=accounting_add($noncashClaims[$id]??0,$amount);if($noncashClaims[$id]>max(accounting_cents($lines[$id]['debit_amount']),accounting_cents($lines[$id]['credit_amount'])))throw new ReportingProblem('Noncash allocation exceeds its source line.');}
    }
    return ['account_labels'=>array_values($accounts),'exception_lines'=>array_values($lines),'advance_dependencies'=>$dependencies];
}
function report_configuration_submit(PDO $pdo,int $uid,array $r): array
{
    reporting_csrf($r);report_configuration_require($pdo);$id=reporting_id($r['draft_id']??null,'draft');$hash=reporting_text($r['payload_hash']??'',64,'saved draft identity',true);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$hash))throw new ReportingProblem('Save this setup draft before submitting.',400);
    return reporting_tx($pdo,$uid,['Admin'],function($actor)use($pdo,$uid,$r,$id,$hash){
        report_configuration_require($pdo);$peek=report_configuration_draft($pdo,$uid,$id);
        if(!hash_equals($peek['payload_hash'],$hash))throw new ReportingProblem('The submitted contents differ from the saved draft. Save and review again.',409);
        if($peek['state']==='Submitted'){$s=$pdo->prepare('SELECT id,draft_hash FROM report_configuration_revisions WHERE draft_id=?');$s->execute([$id]);$old=$s->fetch(PDO::FETCH_ASSOC);if(!$old||!hash_equals($old['draft_hash'],$hash)||(int)$old['id']!==(int)$peek['submitted_revision_id'])throw new ReportingProblem('The submitted setup needs an integrity review.',503);return ['id'=>(int)$old['id'],'duplicate'=>true];}
        $payload=report_configuration_payload($peek['payload']);
        $s=$pdo->prepare('SELECT MIN(effective_from) FROM report_configuration_revisions WHERE effective_from>?');$s->execute([$payload['effective_from']]);$next=$s->fetchColumn();$through=$next?(new DateTimeImmutable($next))->modify('-1 day')->format('Y-m-d'):null;
        reporting_basis_guard($pdo,$payload['effective_from'],$through);
        $root=$pdo->query('SELECT * FROM report_configuration_state WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);$d=report_configuration_draft($pdo,$uid,$id,true);
        if($d['state']!=='Draft'||(string)$d['revision']!==(string)($r['revision']??'')||$d['base_revision_id']!==$root['current_revision_id'])throw new ReportingProblem('Reporting setup changed. Keep this draft and start a new revision from the latest setup.',409);
        $details=report_configuration_validate($pdo,$payload);
        $prior=report_configuration_current($pdo,$payload['effective_from']);
        if($prior&&$prior['payload']['cash_accounts']!==$payload['cash_accounts']&&trim($payload['cash_change_reason'])==='')throw new ReportingProblem('Explain the cash-account change so reports can show a comparable opening balance.');
        $details['cash_comparison']=$prior&&$prior['payload']['cash_accounts']!==$payload['cash_accounts']?['revision_id'=>(int)$prior['id'],'cash_accounts'=>$prior['payload']['cash_accounts']]:null;
        $published=$payload+$details+['prepared_name'=>$actor['FullName'],'calculation_version'=>'reports_v1'];$publishedHash=hash('sha256',reporting_json($published));
        $s=$pdo->prepare('INSERT INTO report_configuration_revisions(revision_number,draft_id,effective_from,parent_revision_id,payload,payload_hash,draft_hash,prepared_by,prepared_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');$s->execute([$root['next_sequence'],$id,$payload['effective_from'],$root['current_revision_id'],reporting_json($published),$publishedHash,$hash,$uid]);$revision=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE report_configuration_drafts SET state='Submitted',submitted_revision_id=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$revision,$id]);
        $pdo->prepare('UPDATE report_configuration_state SET current_revision_id=?,next_sequence=next_sequence+1 WHERE id=1')->execute([$revision]);
        reporting_audit($pdo,$uid,AUDIT_ACTION_CREATE,'Report configuration',$revision,null,['draft_id'=>$id,'effective_from'=>$payload['effective_from'],'payload_hash'=>$publishedHash]);stage1_write_changed($pdo);
        return ['id'=>$revision,'duplicate'=>false];
    });
}
function report_configuration_workspace(PDO $pdo,int $uid,?int $draftId=null): array
{
    return accounting_read($pdo,function()use($pdo,$uid,$draftId){reporting_actor($pdo,$uid,['Admin']);report_configuration_require($pdo);$root=$pdo->query('SELECT current_revision_id FROM report_configuration_state WHERE id=1')->fetch(PDO::FETCH_ASSOC);$s=$pdo->prepare('SELECT id,state,updated_at,submitted_revision_id FROM report_configuration_drafts WHERE owner_id=? ORDER BY id DESC');$s->execute([$uid]);$drafts=$s->fetchAll(PDO::FETCH_ASSOC);$current=null;
        if($root['current_revision_id']){$q=$pdo->prepare('SELECT * FROM report_configuration_revisions WHERE id=?');$q->execute([$root['current_revision_id']]);$current=$q->fetch(PDO::FETCH_ASSOC);if(!$current)throw new ReportingProblem('Reporting setup needs an integrity review.',503);$current['payload']=json_decode($current['payload'],true,64,JSON_THROW_ON_ERROR);if(!hash_equals($current['payload_hash'],hash('sha256',reporting_json($current['payload']))))throw new ReportingProblem('Reporting setup needs an integrity review.',503);}
        $draft=$draftId?report_configuration_draft($pdo,$uid,$draftId):null;$payload=$draft['payload']??$current['payload']??report_configuration_empty();$ids=[];
        foreach($payload['cash_allocations'] as $a){$ids[]=$a['cash_line_id'];$ids[]=$a['counterpart_line_id'];}foreach($payload['noncash_reclasses'] as $a){$ids[]=$a['from_line_id'];$ids[]=$a['to_line_id'];}$sourceLines=[];
        foreach(array_chunk(array_values(array_unique($ids)),500) as $chunk){$q=$pdo->prepare("SELECT l.id,l.journal_entry_id,l.account_id,l.debit_amount,l.credit_amount,c.Name account_name,j.entry_date,j.description FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id JOIN Categories c ON c.CategoryID=l.account_id WHERE j.status='posted' AND l.id IN (".implode(',',array_fill(0,count($chunk),'?')).')');$q->execute($chunk);foreach($q as $line)$sourceLines[(int)$line['id']]=$line;}
        return ['accounts'=>accounting_accounts($pdo),'draft'=>$draft,'drafts'=>$drafts,'current'=>$current,'source_lines'=>$sourceLines];});
}
