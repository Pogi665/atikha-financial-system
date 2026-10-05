<?php
/** Stage 1: persistent private drafts, explicit review, atomic journal/evidence posting. */
require_once __DIR__ . '/receipt_ocr.php';
require_once __DIR__ . '/accounts.php';

function workspace_guard(PDO $pdo, int $uid, ?array $request = null): void
{
    if (!stage1_enabled($pdo)) { throw new JournalProblem('The new accounting workspace is not enabled.',503); }
    if ($uid <= 0 || (int)($_SESSION['UserID'] ?? 0) !== $uid) { throw new JournalProblem('Please sign in again.',401); }
    if ($request !== null && !csrf_verify(is_string($request['csrf_token'] ?? null) ? $request['csrf_token'] : null)) {
        throw new JournalProblem('Your session expired. Reload and try again.',400);
    }
    $s=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=?'); $s->execute([$uid]); $u=$s->fetch();
    if (!$u || $u['Role']!=='Admin' || (int)$u['Is_Active']!==1) { throw new JournalProblem('An active System Administrator is required.',403); }
}
function workspace_json($v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
/** Staff-facing saved dates are Manila dates; persisted draft timestamps remain UTC. */
function workspace_drafts(PDO $pdo,int $uid,array $request): array
{
    require_once __DIR__.'/accounting_query.php';
    try{$book=journal_string($request,'source_book',true);$from=journal_string($request,'from',true);$to=journal_string($request,'to',true);}
    catch(JournalProblem $e){throw new JournalProblem('Invalid draft filter. '.$e->getMessage(),400);}
    if($book!==''&&!in_array($book,['CRB','CDB','GJ'],true)){throw new JournalProblem('Invalid draft entry type.',400);}
    foreach(['from'=>$from,'to'=>$to] as $name=>$date){if($date!==''){try{accounting_date($date);}catch(InvalidArgumentException $e){throw new JournalProblem('Invalid last saved '.$name.' date.',400);}}}
    if($from!==''&&$to!==''&&$from>$to){throw new JournalProblem('Last saved from must be on or before last saved to.',400);}
    $where=['owner_id=?',"state='Draft'"];$args=[$uid];
    if($book!==''){$where[]='source_book=?';$args[]=$book;}
    foreach(['from'=>$from,'to'=>$to] as $name=>$date){if($date!==''){
        $boundary=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Manila'));
        if($name==='to'){$boundary=$boundary->modify('+1 day');}
        $utc=$boundary->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if($utc<'1000-01-01 00:00:00'||$utc>'9999-12-31 23:59:59'){throw new JournalProblem('Last saved '.$name.' date is outside the supported timestamp range.',400);}
        $where[]='updated_at '.($name==='from'?'>=':'<').' ?';$args[]=$utc;
    }}
    $s=$pdo->prepare('SELECT id,source_book,revision,updated_at,JSON_UNQUOTE(JSON_EXTRACT(payload,\'$.entry_date\')) entry_date,JSON_UNQUOTE(JSON_EXTRACT(payload,\'$.description\')) description FROM journal_drafts WHERE '.implode(' AND ',$where).' ORDER BY updated_at DESC,id DESC');
    $s->execute($args);$rows=$s->fetchAll();
    foreach($rows as &$row){
        $saved=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['updated_at'],new DateTimeZone('UTC'));
        if(!$saved||$saved->format('Y-m-d H:i:s')!==$row['updated_at']){throw new UnexpectedValueException('Invalid persisted draft timestamp.');}
        $row['updated_at_display']=$saved->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
    }unset($row);return $rows;
}
function workspace_tx(PDO $pdo, int $uid, callable $operation, bool $coordinate = true)
{
    if ($pdo->inTransaction()) { throw new LogicException('Workspace operation must own its transaction.'); }
    $pdo->beginTransaction();
    try {
        receipt_actor_lock($pdo,$uid);
        $version=$coordinate ? stage1_write_lock($pdo) : null;
        $out=$operation($version); $pdo->commit(); return $out;
    } catch(Throwable $e) { if($pdo->inTransaction()){$pdo->rollBack();} throw $e; }
}
function workspace_audit(PDO $pdo,int $uid,string $action,string $entity,int $id,$before,$after): void
{
    if(!log_system_action($pdo,$uid,$action,$entity,$id,$before,$after,'accounting_drafts.php')){
        throw new RuntimeException('Accounting audit could not be saved.');
    }
}
function workspace_draft(PDO $pdo,int $uid,int $id,bool $lock=false): array
{
    $s=$pdo->prepare('SELECT * FROM journal_drafts WHERE id=? AND owner_id=?'.($lock?' FOR UPDATE':''));$s->execute([$id,$uid]);$r=$s->fetch();
    if(!$r){throw new JournalProblem('Draft not found.',404);}
    if((int)$r['payload_version']!==2){throw new JournalProblem('This draft uses an unsupported payload version. Contact your administrator.',409);}
    $r['payload']=json_decode($r['payload'],true,64,JSON_THROW_ON_ERROR);return $r;
}
function workspace_revision(array $d,array $request): void
{
    if($d['state']!=='Draft'){throw new JournalProblem('This draft is no longer editable.',409);}
    if((string)$d['revision'] !== journal_string($request,'revision')){
        throw new JournalProblem('This draft changed in another window. Reload it before saving; your unsaved values are retained.',409);
    }
}
function workspace_payload(array $p): array
{
    // Drafts may be incomplete. Bound and whitelist the shape without claiming validity.
    $limits=['entry_date'=>10,'reference'=>100,'description'=>2000,'party_id'=>10,'default_project_id'=>10,
        'cash_account_id'=>10,'cash_amount'=>32,'cash_project_id'=>10,'transaction_kind'=>32];
    $out=[];
    foreach($limits as $k=>$max){$v=journal_string($p,$k,true);if(mb_strlen($v)>$max){throw new JournalProblem('Draft field exceeds its length limit.');}$out[$k]=$v;}
    $out['transaction_kind']=$out['transaction_kind'] ?: 'ordinary';
    $out['lines']=[];$out['documents']=[];
    foreach(['lines'=>100,'documents'=>20] as $k=>$limit){
        if(!is_array($p[$k]??null)||count($p[$k])>$limit||!array_is_list($p[$k])){throw new JournalProblem('Invalid draft lines or documents.');}
    }
    $ids=[];
    foreach($p['lines'] as $l){
        if(!is_array($l)){throw new JournalProblem('Invalid draft line.');}
        $id=journal_string($l,'client_id');
        if(!preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/',$id)||isset($ids[$id])||$id==='cash'){throw new JournalProblem('Each line needs a unique stable identifier.');}
        $ids[$id]=true;$row=['client_id'=>$id];
        foreach(['account_id'=>10,'fund_project_id'=>10,'debit_amount'=>32,'credit_amount'=>32] as $k=>$max){
            $v=journal_string($l,$k,true);if(strlen($v)>$max){throw new JournalProblem('Invalid draft line value.');}$row[$k]=$v;
        }$out['lines'][]=$row;
    }
    $receipts=[];
    foreach($p['documents'] as $d){
        if(!is_array($d)){throw new JournalProblem('Invalid document.');}
        $id=journal_id(journal_string($d,'receipt_id'));if(isset($receipts[$id])){throw new JournalProblem('A document is attached twice.');}$receipts[$id]=true;
        $row=['receipt_id'=>(string)$id,'reviewed'=>($d['reviewed']??false)===true];
        foreach(['purpose'=>20,'support_side'=>6,'declared_amount'=>32,'accepted_amount'=>32,'exclusion_reason'=>2000] as $k=>$max){
            $v=journal_string($d,$k,true);if(mb_strlen($v)>$max){throw new JournalProblem('Invalid document review.');}$row[$k]=$v;
        }
        if(!is_array($d['allocations']??null)||count($d['allocations'])>100){throw new JournalProblem('Invalid document allocations.');}
        $row['allocations']=[];
        foreach($d['allocations'] as $a){
            if(!is_array($a)){throw new JournalProblem('Invalid document allocation.');}
            $line=journal_string($a,'client_id');$amount=journal_string($a,'amount');
            if(strlen($line)>64||strlen($amount)>32){throw new JournalProblem('Invalid allocation.');}
            $row['allocations'][]=['client_id'=>$line,'amount'=>$amount];
        }$out['documents'][]=$row;
    }
    if(strlen(workspace_json($out))>1048576){throw new JournalProblem('Draft exceeds the supported size.');}return $out;
}
function workspace_document_ids(array $p): array {$ids=array_map('intval',array_column($p['documents'],'receipt_id'));sort($ids,SORT_NUMERIC);return $ids;}
function workspace_draft_public(array $d): array
{
    return array_intersect_key($d,array_flip(['id','source_book','payload_version','payload','revision','submission_key','state','posted_journal_id','updated_at']));
}
function workspace_save(PDO $pdo,int $uid,array $request): array
{
    workspace_guard($pdo,$uid,$request);$p=workspace_payload($request['payload']??[]);
    $book=journal_string($request,'source_book');if(!in_array($book,['CRB','CDB','GJ'],true)){throw new JournalProblem('Choose a valid book.');}
    $key=journal_string($request,'submission_key');if(!preg_match('/\A[a-f0-9]{64}\z/',$key)){throw new JournalProblem('Invalid durable submission key.');}
    $id=journal_id(journal_string($request,'draft_id',true),true);$creationHash=hash('sha256',workspace_json([$book,$p]));
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$request,$p,$book,$key,$id,$creationHash){
        if($id===null){
            if($p['documents']){throw new JournalProblem('Save the draft before attaching documents.');}
            $s=$pdo->prepare('SELECT id,owner_id,creation_hash FROM journal_drafts WHERE submission_key=?');$s->execute([$key]);
            if($old=$s->fetch()){
                if((int)$old['owner_id']!==$uid||!hash_equals($old['creation_hash'],$creationHash)){throw new JournalProblem('Draft creation key was used with different contents.',409);}
                return workspace_draft_public(workspace_draft($pdo,$uid,(int)$old['id']));
            }
            $s=$pdo->prepare('INSERT INTO journal_drafts(owner_id,source_book,payload,creation_hash,submission_key,created_at,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $s->execute([$uid,$book,workspace_json($p),$creationHash,$key]);$id=(int)$pdo->lastInsertId();
            workspace_audit($pdo,$uid,AUDIT_ACTION_CREATE,'Journal Draft',$id,null,['source_book'=>$book]);
        }else{
            $d=workspace_draft($pdo,$uid,$id,true);workspace_revision($d,$request);
            if($key!==$d['submission_key']||$book!==$d['source_book']){throw new JournalProblem('Draft identity cannot change.',409);}
            if(workspace_document_ids($p)!==workspace_document_ids($d['payload'])){throw new JournalProblem('Use document attach/remove actions to change reservations.',409);}
            $s=$pdo->prepare('UPDATE journal_drafts SET payload=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([workspace_json($p),$id]);
            workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Journal Draft',$id,['revision'=>$d['revision']],['revision'=>(int)$d['revision']+1]);
        }return workspace_draft_public(workspace_draft($pdo,$uid,$id));
    },false);
}
function workspace_lists(PDO $pdo,int $uid): array
{
    workspace_guard($pdo,$uid);$accounts=journal_accounts($pdo);
    $designated=array_map('intval',$pdo->query('SELECT account_id FROM advance_control_designations')->fetchAll(PDO::FETCH_COLUMN));
    return ['accounts'=>array_values(array_filter($accounts,fn($a)=>!in_array((int)$a['CategoryID'],$designated,true))),
        'projects'=>$pdo->query('SELECT id,code,name,is_active,revision,description FROM projects ORDER BY name,id')->fetchAll(),
        'parties'=>$pdo->query('SELECT id,code,name,party_type,reference,description,is_active,revision FROM parties ORDER BY name,id')->fetchAll(),
        'controls'=>$designated,'all_accounts'=>$accounts];
}
function workspace_master_save(PDO $pdo,int $uid,array $r): array
{
    workspace_guard($pdo,$uid,$r);$kind=journal_string($r,'kind');
    if(!in_array($kind,['projects','parties'],true)){throw new JournalProblem('Invalid master type.');}
    $id=journal_id(journal_string($r,'id',true),true);$name=journal_string($r,'name');$description=journal_string($r,'description',true);
    if($name===''||mb_strlen($name)>100||mb_strlen($description)>2000){throw new JournalProblem('Enter a name of at most 100 characters and description of at most 2,000.');}
    $active=journal_string($r,'is_active');if(!in_array($active,['0','1'],true)){throw new JournalProblem('Invalid active status.');}
    $code=$kind==='projects'?strtoupper(journal_string($r,'code')):'';
    if($kind==='projects'&&!preg_match('/\A[A-Z0-9][A-Z0-9_-]{0,29}\z/',$code)){throw new JournalProblem('Project code must contain 1–30 letters, numbers, hyphens or underscores.');}
    $type=journal_string($r,'party_type',true);$ref=journal_string($r,'reference',true);
    if($kind==='parties'&&(!in_array($type,['person','organization'],true)||mb_strlen($ref)>100)){throw new JournalProblem('Select a party type and a reference of at most 100 characters.');}
    try{return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$kind,$id,$name,$description,$active,$code,$type,$ref){
        $before=null;
        if($id!==null){
            $s=$pdo->prepare("SELECT * FROM $kind WHERE id=? FOR UPDATE");$s->execute([$id]);$before=$s->fetch();
            if(!$before){throw new JournalProblem('Master record not found.',404);}
            if((string)$before['revision']!==journal_string($r,'revision')){throw new JournalProblem('This master changed. Reload before saving.',409);}
            if($kind==='projects'&&$code!==$before['code']){
                $s=$pdo->prepare('SELECT id FROM journal_entry_lines WHERE fund_project_id=? LIMIT 1');$s->execute([$id]);
                if($s->fetchColumn()!==false){throw new JournalProblem('Project codes are immutable after posting.');}
            }
        }
        if($id===null){
            if($kind==='projects'){$s=$pdo->prepare('INSERT INTO projects(code,name,description,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute([$code,$name,$description,$active,$uid]);}
            else{$s=$pdo->prepare('INSERT INTO parties(code,name,party_type,reference,description,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute(['PTY-'.bin2hex(random_bytes(10)),$name,$type,$ref,$description,$active,$uid]);}
            $id=(int)$pdo->lastInsertId();
        }else{
            if($kind==='projects'){$s=$pdo->prepare('UPDATE projects SET code=?,name=?,description=?,is_active=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$code,$name,$description,$active,$id]);}
            else{$s=$pdo->prepare('UPDATE parties SET name=?,party_type=?,reference=?,description=?,is_active=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$name,$type,$ref,$description,$active,$id]);}
        }
        $s=$pdo->prepare("SELECT * FROM $kind WHERE id=?");$s->execute([$id]);$after=$s->fetch();
        workspace_audit($pdo,$uid,$before?AUDIT_ACTION_EDIT:AUDIT_ACTION_CREATE,ucfirst($kind),$id,$before,$after);stage1_write_changed($pdo);return $after;
    });}catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062){throw new JournalProblem('This project code is already in use. Check inactive projects.',409);}throw $e;}
}
function workspace_control(PDO $pdo,int $uid,array $r): void
{
    workspace_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'account_id'));$reason=journal_string($r,'reason');
    if($reason===''||mb_strlen($reason)>2000){throw new JournalProblem('Enter a designation change reason.');}
    $remove=journal_string($r,'operation')==='remove';
    workspace_tx($pdo,$uid,function()use($pdo,$uid,$id,$reason,$remove){
        $a=account_load($pdo,$id,true);
        if(!$a||account_has_posted_lines($pdo,$id)){throw new JournalProblem('Stage 1 designation changes require an unused account.');}
        $s=$pdo->prepare('SELECT account_id FROM advance_control_designations WHERE account_id=?');$s->execute([$id]);$exists=$s->fetchColumn()!==false;
        if($remove){if(!$exists){throw new JournalProblem('Account is not designated.',409);}$pdo->prepare('DELETE FROM advance_control_designations WHERE account_id=?')->execute([$id]);}
        else{
            if($exists){throw new JournalProblem('Account is already designated.',409);}
            if($a['Account_Type']!=='Asset'||$a['Normal_Balance']!=='Debit'||(int)$a['Is_Cash_Account']!==0||(int)$a['Is_Active']!==1){throw new JournalProblem('Designate an active, noncash Asset account with Debit normal balance.');}
            $pdo->prepare('INSERT INTO advance_control_designations(account_id,designated_by,designated_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$id,$uid]);
        }
        workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Advance Control',$id,['designated'=>$exists],['designated'=>!$remove,'reason'=>$reason]);stage1_write_changed($pdo);
    });
}
function workspace_canonical(array $d,array $payload): array
{
    $p=workspace_payload($payload);$raw=$p['lines'];$clientIds=array_column($raw,'client_id');
    if($d['source_book']!=='GJ'){
        $side=$d['source_book']==='CRB'?'debit_amount':'credit_amount';
        array_unshift($raw,['account_id'=>$p['cash_account_id'],'fund_project_id'=>$p['cash_project_id'],
            'debit_amount'=>$side==='debit_amount'?$p['cash_amount']:'','credit_amount'=>$side==='credit_amount'?$p['cash_amount']:'']);
        array_unshift($clientIds,'cash');
    }
    $input=journal_input(['entry_date'=>$p['entry_date'],'reference'=>$p['reference'],'description'=>$p['description'],
        'lines'=>$raw,'line_count'=>(string)count($raw),'form_complete'=>'1']);
    if($d['source_book']!=='GJ'&&$input['entry_date']>journal_today()){throw new JournalProblem('Cash receipt and payment dates cannot be in the future.');}
    if(!in_array($p['transaction_kind'],['ordinary','transfer'],true)||($p['transaction_kind']==='transfer'&&$d['source_book']!=='GJ')){throw new JournalProblem('Cash transfers belong in the General Journal.');}
    $party=journal_id($p['party_id'],true);
    if($d['source_book']!=='GJ'&&$party===null){throw new JournalProblem('Choose the payer or payee.');}
    foreach($input['lines'] as $i=>&$l){$l['client_id']=$clientIds[$i];}unset($l);
    $documents=$p['documents'];
    foreach($documents as &$doc){
        if(!$doc['reviewed']||!in_array($doc['purpose'],['amount','supporting'],true)){throw new JournalProblem('Review each attached document or remove it before posting.');}
        if($doc['purpose']==='amount'){
            if(!in_array($doc['support_side'],['debit','credit'],true)){throw new JournalProblem('Choose the side supported by this document.');}
            $declared=journal_amount($doc['declared_amount']);$accepted=journal_amount($doc['accepted_amount']);
            if($accepted<=0||$accepted>$declared){throw new JournalProblem('Accepted evidence must be positive and no more than its confirmed PHP total.');}
            if($accepted<$declared&&$doc['exclusion_reason']===''){throw new JournalProblem('Explain the excluded document amount.');}
            $doc['declared_amount']=ledger_decimal($declared);$doc['accepted_amount']=ledger_decimal($accepted);
            $sum=0;$seen=[];foreach($doc['allocations'] as &$a){
                $c=journal_amount($a['amount']);if($c<=0||isset($seen[$a['client_id']])){throw new JournalProblem('Use one positive allocation per supported line.');}
                $seen[$a['client_id']]=true;$sum+=$c;$a['amount']=ledger_decimal($c);
            }unset($a);
            if($sum!==$accepted){throw new JournalProblem('Document allocations must exactly equal the accepted amount.');}
        }else{
            if($doc['allocations']||$doc['accepted_amount']!==''||$doc['declared_amount']!==''||$doc['support_side']!==''){throw new JournalProblem('Supporting documents do not claim monetary coverage.');}
        }
    }unset($doc);
    return $input+['source_book'=>$d['source_book'],'transaction_kind'=>$p['transaction_kind'],'party_id'=>$party,'documents'=>$documents];
}
function workspace_prepare(PDO $pdo,int $uid,array $d,array $input,string $version): array
{
    $party=null;$projects=[];$receipts=[];$accounts=[];
    // Master locks before evidence, then sorted account rows. All configuration writers share the barrier.
    if($input['party_id']!==null){
        $s=$pdo->prepare('SELECT id,code,name,party_type,is_active,revision FROM parties WHERE id=? FOR UPDATE');$s->execute([$input['party_id']]);$party=$s->fetch();
        if(!$party||(int)$party['is_active']!==1){throw new JournalProblem('The selected payer/payee is inactive or unavailable.');}
    }
    $projectIds=array_values(array_unique(array_filter(array_column($input['lines'],'fund_project_id'),fn($v)=>$v!==null)));sort($projectIds,SORT_NUMERIC);
    foreach($projectIds as $id){$s=$pdo->prepare('SELECT id,code,name,is_active,revision FROM projects WHERE id=? FOR UPDATE');$s->execute([$id]);$r=$s->fetch();if(!$r||(int)$r['is_active']!==1){throw new JournalProblem('A project is inactive or unavailable.');}$projects[$id]=$r;}
    $docIds=array_map('intval',array_column($input['documents'],'receipt_id'));sort($docIds,SORT_NUMERIC);
    $hashes=[];
    foreach($docIds as $id){
        $r=receipt_owned($pdo,$id,$uid,true);$s=$pdo->prepare('SELECT draft_id FROM draft_evidence_reservations WHERE receipt_id=?');$s->execute([$id]);
        if((int)$s->fetchColumn()!==(int)$d['id']||$r['JournalEntryID']!==null){throw new JournalProblem('Evidence is no longer reserved to this draft.',409);}
        if($r['OCR_Status']==='Pending'){throw new JournalProblem('Wait for document processing to finish.',409);}
        receipt_file_verify($r);if(isset($hashes[$r['File_SHA256']])||receipt_duplicate($pdo,$r['File_SHA256'])){throw new JournalProblem('Identical document evidence cannot be claimed twice.',409);}
        $hashes[$r['File_SHA256']]=true;$a=receipt_latest_attempt($pdo,$id);$r['attempt_version']=$a['id']??null;$receipts[$id]=$r;
    }
    $ids=array_values(array_unique(array_column($input['lines'],'account_id')));sort($ids,SORT_NUMERIC);
    foreach($ids as $id){$a=account_load($pdo,$id,true);if(!$a||(int)$a['Is_Active']!==1||!in_array($a['Account_Type'],JOURNAL_ACCOUNT_TYPES,true)||!in_array($a['Normal_Balance'],['Debit','Credit'],true)||!in_array((int)$a['Is_Cash_Account'],[0,1],true)||((int)$a['Is_Cash_Account']===1&&$a['Account_Type']!=='Asset')){throw new JournalProblem('An account is unavailable or inactive.');}$accounts[$id]=$a;}
    stage1_control_guard($pdo,$ids);$cash=[];$eligible=['debit'=>0,'credit'=>0];$covered=['debit'=>0,'credit'=>0];$byClient=[];
    foreach($input['lines'] as $l){
        $byClient[$l['client_id']]=$l;$a=$accounts[$l['account_id']];
        if((int)$a['Is_Cash_Account']===1){$cash[]=$l;}
        else{foreach(['debit','credit'] as $side){$eligible[$side]+=journal_amount($l[$side.'_amount']);}}
    }
    if($input['source_book']!=='GJ'){
        $side=$input['source_book']==='CRB'?'debit_amount':'credit_amount';
        if(count($cash)!==1||$cash[0]['client_id']!=='cash'||journal_amount($cash[0][$side])<=0){throw new JournalProblem('A cash book entry requires exactly one cash/bank line in the correct direction. Use General Journal for multiple cash accounts.');}
    }
    if($input['transaction_kind']==='transfer'&&(count($input['lines'])!==2||count($cash)!==2||$cash[0]['account_id']===$cash[1]['account_id'])){throw new JournalProblem('A transfer requires two different cash accounts, equal opposing amounts, and no other lines.');}
    $perLine=[];
    foreach($input['documents'] as $doc){if($doc['purpose']!=='amount'){continue;}$side=$doc['support_side'];
        if(($input['source_book']==='CRB'&&$side!=='credit')||($input['source_book']==='CDB'&&$side!=='debit')){throw new JournalProblem('Document coverage must support the noncash allocation side of this cash book.');}
        foreach($doc['allocations'] as $a){
            $l=$byClient[$a['client_id']]??null;$amount=journal_amount($a['amount']);
            if(!$l||(int)$accounts[$l['account_id']]['Is_Cash_Account']===1||journal_amount($l[$side.'_amount'])<=0){throw new JournalProblem('Evidence allocations must support an existing noncash line on the chosen side.');}
            $perLine[$a['client_id']]=($perLine[$a['client_id']]??0)+$amount;
            if($perLine[$a['client_id']]>journal_amount($l[$side.'_amount'])){throw new JournalProblem('Combined evidence exceeds the supported line amount.');}$covered[$side]+=$amount;
        }
    }
    $sides=$input['source_book']==='GJ'?['debit','credit']:[$input['source_book']==='CRB'?'credit':'debit'];$denom=$support=0;
    foreach($sides as $side){$denom+=$eligible[$side];$support+=$covered[$side];}
    $status=$denom===0?'Not applicable':($support===$denom?'Fully covered':($support>0?'Partially covered':'No monetary support'));
    $coverage=['status'=>$status,'debit'=>['eligible'=>ledger_decimal($eligible['debit']),'covered'=>ledger_decimal($covered['debit'])],
        'credit'=>['eligible'=>ledger_decimal($eligible['credit']),'covered'=>ledger_decimal($covered['credit'])]];
    $evidenceVersions=array_map(fn($r)=>[$r['ReceiptID'],$r['File_SHA256'],$r['attempt_version']],$receipts);
    return ['input'=>$input,'party'=>$party,'projects'=>$projects,'receipts'=>$receipts,'accounts'=>$accounts,'coverage'=>$coverage,
        'fingerprint'=>hash('sha256',workspace_json([$version,$d['revision'],$input,$party,$projects,$evidenceVersions,$accounts]))];
}
function workspace_token(array $d,string $fingerprint,int $expires): string
{
    if(!isset($_SESSION['workspace_review_secret'])){$_SESSION['workspace_review_secret']=bin2hex(random_bytes(32));}
    return $expires.'.'.hash_hmac('sha256',workspace_json([$d['id'],$d['owner_id'],$fingerprint,$expires]),$_SESSION['workspace_review_secret']);
}
function workspace_review(PDO $pdo,int $uid,array $r): array
{
    workspace_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));
    return workspace_tx($pdo,$uid,function($version)use($pdo,$uid,$id,$r){
        $d=workspace_draft($pdo,$uid,$id,true);workspace_revision($d,$r);$input=workspace_canonical($d,$d['payload']);
        $prepared=workspace_prepare($pdo,$uid,$d,$input,$version);
        $lines=[];foreach($input['lines'] as $l){$lines[]=$l+['account_name'=>$prepared['accounts'][$l['account_id']]['Name'],
            'project_name'=>$l['fund_project_id']===null?'Organization operations':$prepared['projects'][$l['fund_project_id']]['name']];}
        return ['input'=>$input,'lines'=>$lines,'party'=>$prepared['party'],'coverage'=>$prepared['coverage'],
            'token'=>workspace_token($d,$prepared['fingerprint'],time()+900)];
    });
}
function workspace_post(PDO $pdo,int $uid,array $r): array
{
    workspace_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));$key=journal_string($r,'submission_key');
    return workspace_tx($pdo,$uid,function($version)use($pdo,$uid,$id,$key,$r){
        $d=workspace_draft($pdo,$uid,$id,true);
        if(!hash_equals($d['submission_key'],$key)){throw new JournalProblem('Draft submission key does not match.',409);}
        $input=workspace_canonical($d,$r['payload']??[]);$hash=hash('sha256',workspace_json($input));
        // A successful retry is durable: authenticate/own/compare before token expiry or new master eligibility.
        if($d['state']==='Posted'){
            $existing=journal_existing($pdo,$key,$uid,$hash);
            if(!$existing||(int)$existing['id']!==(int)$d['posted_journal_id']){throw new JournalProblem('Posted draft is inconsistent.',409);}return $existing;
        }
        workspace_revision($d,$r);
        if(workspace_json($input)!==workspace_json(workspace_canonical($d,$d['payload']))){throw new JournalProblem('Save and review your latest changes before posting.',409);}
        $prepared=workspace_prepare($pdo,$uid,$d,$input,$version);$token=journal_string($r,'review_token');
        if(!preg_match('/\A([0-9]{10})\.([a-f0-9]{64})\z/',$token,$m)||!isset($_SESSION['workspace_review_secret'])||time()>(int)$m[1]
            ||!hash_equals(workspace_token($d,$prepared['fingerprint'],(int)$m[1]),$token)){throw new JournalProblem('Review expired or accounting data changed. Review the draft again.',409);}
        $s=$pdo->prepare("INSERT INTO journal_entries(entry_date,reference,description,status,submission_key,submission_hash,posted_by_user_id,source_book,transaction_kind,party_id,party_snapshot,created_at,updated_at)
            VALUES(?,?,?,'posted',?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $s->execute([$input['entry_date'],$input['reference'],$input['description'],$key,$hash,$uid,$input['source_book'],$input['transaction_kind'],$input['party_id'],
            $prepared['party']===null?null:workspace_json(array_intersect_key($prepared['party'],array_flip(['id','code','name','party_type'])))]);
        $journal=(int)$pdo->lastInsertId();$lineIds=[];
        $s=$pdo->prepare('INSERT INTO journal_entry_lines(journal_entry_id,account_id,debit_amount,credit_amount,fund_project_id,project_code_snapshot,project_name_snapshot) VALUES(?,?,?,?,?,?,?)');
        foreach($input['lines'] as $l){$pr=$l['fund_project_id']===null?null:$prepared['projects'][$l['fund_project_id']];
            $s->execute([$journal,$l['account_id'],$l['debit_amount'],$l['credit_amount'],$l['fund_project_id'],$pr['code']??null,$pr['name']??null]);$lineIds[$l['client_id']]=(int)$pdo->lastInsertId();}
        foreach($input['documents'] as $doc){
            $receipt=$prepared['receipts'][(int)$doc['receipt_id']];
            $s=$pdo->prepare('UPDATE Receipts SET JournalEntryID=?,Posted_File_SHA256=File_SHA256 WHERE ReceiptID=? AND JournalEntryID IS NULL');$s->execute([$journal,$doc['receipt_id']]);
            if($s->rowCount()!==1){throw new JournalProblem('Evidence was posted by another request.',409);}
            $amount=$doc['purpose']==='amount';
            $s=$pdo->prepare('INSERT INTO posted_evidence_associations(journal_id,receipt_id,purpose,support_side,declared_amount,accepted_amount,exclusion_reason,reviewed_by,reviewed_at,review_snapshot) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?)');
            $s->execute([$journal,$doc['receipt_id'],$doc['purpose'],$amount?$doc['support_side']:null,$amount?$doc['declared_amount']:null,$amount?$doc['accepted_amount']:null,$doc['exclusion_reason'],$uid,
                workspace_json($doc+['sha256'=>$receipt['File_SHA256'],'attempt_version'=>$receipt['attempt_version'],'coverage'=>$prepared['coverage']])]);
            $association=(int)$pdo->lastInsertId();$s=$pdo->prepare('INSERT INTO evidence_allocations(association_id,line_id,amount) VALUES(?,?,?)');
            foreach($doc['allocations'] as $a){$s->execute([$association,$lineIds[$a['client_id']],$a['amount']]);}
        }
        $pdo->prepare('DELETE FROM draft_evidence_reservations WHERE draft_id=?')->execute([$id]);
        $pdo->prepare("UPDATE journal_drafts SET state='Posted',posted_journal_id=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$journal,$id]);
        workspace_audit($pdo,$uid,AUDIT_ACTION_CREATE,'General Journal',$journal,null,$input+['coverage'=>$prepared['coverage'],'draft_id'=>$id]);
        workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Journal Draft',$id,['state'=>'Draft'],['state'=>'Posted','journal_id'=>$journal]);stage1_write_changed($pdo);
        return ['id'=>$journal,'duplicate'=>false];
    });
}
function workspace_attach(PDO $pdo,int $uid,array $r,int $receiptId): array
{
    workspace_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$receiptId){
        $d=workspace_draft($pdo,$uid,$id,true);workspace_revision($d,$r);$p=$d['payload'];
        if(count($p['documents'])>=20){throw new JournalProblem('A draft supports up to 20 images.');}
        $receipt=receipt_owned($pdo,$receiptId,$uid,true);stage1_reserved_guard($pdo,$receiptId);
        if($receipt['JournalEntryID']!==null||$receipt['OCR_Status']==='Pending'||receipt_duplicate($pdo,$receipt['File_SHA256'])){throw new JournalProblem('Document is posted or still processing.',409);}
        receipt_file_verify($receipt);
        $pdo->prepare('INSERT INTO draft_evidence_reservations(receipt_id,draft_id,created_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$receiptId,$id]);
        $p['documents'][]=['receipt_id'=>(string)$receiptId,'reviewed'=>false,'purpose'=>'supporting','support_side'=>'','declared_amount'=>'','accepted_amount'=>'','exclusion_reason'=>'','allocations'=>[]];
        $pdo->prepare('UPDATE journal_drafts SET payload=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([workspace_json($p),$id]);
        workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Journal Draft',$id,null,['reserved_receipt_id'=>$receiptId]);return workspace_draft_public(workspace_draft($pdo,$uid,$id));
    },false);
}
function workspace_remove_or_discard(PDO $pdo,int $uid,array $r,bool $discard): array
{
    workspace_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));
    $receiptId=$discard?null:journal_id(journal_string($r,'receipt_id'));
    return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$receiptId,$discard){
        $d=workspace_draft($pdo,$uid,$id,true);workspace_revision($d,$r);$p=$d['payload'];$ids=workspace_document_ids($p);
        if(!$discard&&!in_array($receiptId,$ids,true)){throw new JournalProblem('Document is not in this draft.',404);}
        foreach($ids as $doc){if(!$discard&&$doc!==$receiptId){continue;}$receipt=receipt_owned($pdo,$doc,$uid,true);
            $s=$pdo->prepare('SELECT draft_id FROM draft_evidence_reservations WHERE receipt_id=?');$s->execute([$doc]);
            if((int)$s->fetchColumn()!==$id||$receipt['JournalEntryID']!==null){throw new JournalProblem('Evidence reservation changed.',409);}
            if($discard){$pdo->prepare("UPDATE Receipts SET OCR_Status='Discarded' WHERE ReceiptID=?")->execute([$doc]);}
            $pdo->prepare('DELETE FROM draft_evidence_reservations WHERE receipt_id=? AND draft_id=?')->execute([$doc,$id]);
        }
        $p['documents']=array_values(array_filter($p['documents'],fn($x)=>!$discard&&(int)$x['receipt_id']!==$receiptId));
        $pdo->prepare('UPDATE journal_drafts SET payload=?,state=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([workspace_json($p),$discard?'Discarded':'Draft',$id]);
        workspace_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Journal Draft',$id,null,['discarded'=>$discard,'removed_receipt_id'=>$receiptId,'images_physically_deleted'=>false]);
        return workspace_draft_public(workspace_draft($pdo,$uid,$id));
    },false);
}
function workspace_upload(PDO $pdo,int $uid,array $r,array $file): array
{
    workspace_guard($pdo,$uid,$r);$id=journal_id(journal_string($r,'draft_id'));$key=journal_string($r,'upload_key');
    if(!preg_match('/\A[a-f0-9]{64}\z/',$key)){throw new JournalProblem('Invalid upload key.');}
    $stored=store_uploaded_receipt($file);if(!$stored['ok']){throw new JournalProblem($stored['error'],400);}
    $path=transaction_receipt_path($stored['path']);$keep=false;
    try{return workspace_tx($pdo,$uid,function()use($pdo,$uid,$r,$id,$key,$stored,&$keep){
        $d=workspace_draft($pdo,$uid,$id,true);$p=$d['payload'];
        $s=$pdo->prepare('SELECT r.ReceiptID,r.UploadedBy_UserID,r.File_SHA256,v.draft_id FROM Receipts r LEFT JOIN draft_evidence_reservations v ON v.receipt_id=r.ReceiptID WHERE r.Upload_Key=?');$s->execute([$key]);
        if($old=$s->fetch()){
            if((int)$old['UploadedBy_UserID']!==$uid||(int)$old['draft_id']!==$id||!hash_equals($old['File_SHA256'],$stored['sha256'])){throw new JournalProblem('Upload key was used with different contents.',409);}
            return workspace_draft_public($d);
        }
        workspace_revision($d,$r);if(count($p['documents'])>=20){throw new JournalProblem('A draft supports up to 20 images.');}
        if(receipt_duplicate($pdo,$stored['sha256'])){throw new JournalProblem('This image is already posted evidence.',409);}
        $s=$pdo->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,Upload_Key,UploadedBy_UserID,OCR_Status) VALUES(?,?,?,?,?,?,?,'Failed')");
        $s->execute([$stored['path'],$stored['original'],$stored['mime'],$stored['size'],$stored['sha256'],$key,$uid]);$doc=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message)
            VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Manual evidence review; no extraction requested')");
        $s->execute([$doc,$uid,hash('sha256',$key.':manual'),$stored['sha256'],hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
        $pdo->prepare('INSERT INTO draft_evidence_reservations(receipt_id,draft_id,created_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$doc,$id]);
        $p['documents'][]=['receipt_id'=>(string)$doc,'reviewed'=>false,'purpose'=>'supporting','support_side'=>'','declared_amount'=>'','accepted_amount'=>'','exclusion_reason'=>'','allocations'=>[]];
        $pdo->prepare('UPDATE journal_drafts SET payload=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([workspace_json($p),$id]);
        workspace_audit($pdo,$uid,AUDIT_ACTION_CREATE,'Draft Evidence',$doc,null,['draft_id'=>$id,'sha256'=>$stored['sha256'],'size'=>$stored['size']]);
        $keep=true;return workspace_draft_public(workspace_draft($pdo,$uid,$id));
    },false);}catch(Throwable $e){
        // A lost connection at COMMIT may leave a durable receipt. Never delete its evidence blindly.
        try{$s=$pdo->prepare('SELECT ReceiptID FROM Receipts WHERE File_Path=?');$s->execute([$stored['path']]);$keep=$s->fetchColumn()!==false;}
        catch(Throwable $checkError){$keep=true;error_log('Upload outcome requires recovery; protected file retained.');}
        throw $e;
    }finally{if(!$keep&&$path){@unlink($path);}}
}
