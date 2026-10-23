<?php
/** Guarded callers supply isolated PDO, fake actors and private evidence. */
function s47r(array $r):array{return ['csrf_token'=>csrf_token()]+$r;}
function s47id(array $d):array{return ['draft_id'=>(string)$d['id'],'revision'=>(string)$d['revision'],'submission_key'=>$d['submission_key']];}
function s47line(string $id,int $account,string $debit='',string $credit=''):array{return ['client_id'=>$id,'account_id'=>(string)$account,'fund_project_id'=>'','debit_amount'=>$debit,'credit_amount'=>$credit];}
function s47payload(string $date,array $lines,int $cash=0,string $amount=''):array{global $s47party;return ['entry_date'=>$date,'reference'=>'S4%_\\ <script>','description'=>'Synthetic ledger training','party_id'=>(string)$s47party,'default_project_id'=>'','cash_account_id'=>$cash?(string)$cash:'','cash_amount'=>$amount,'cash_project_id'=>'','transaction_kind'=>'ordinary','lines'=>$lines,'documents'=>[]];}
function s47ordinary(PDO $p,int $uid,string $book,array $v):array{
    $d=workspace_save($p,$uid,s47r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'source_book'=>$book,'payload'=>$v]));
    $r=workspace_review($p,$uid,s47r(s47id($d)));return workspace_post($p,$uid,s47r(s47id($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]));
}
function s47advance(PDO $p,int $uid,string $kind,array $v,?int $advance=null):array{return advance_save($p,$uid,s47r(['draft_id'=>'','submission_key'=>bin2hex(random_bytes(32)),'workflow_kind'=>$kind,'advance_id'=>$advance===null?'':(string)$advance,'payload'=>$v]));}
function s47advancepost(PDO $p,int $uid,array $d):array{$r=advance_review($p,$uid,s47r(s47id($d)));return advance_post($p,$uid,s47r(s47id($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]));}
function s47support(PDO $p,int $uid,array $d,bool $money,string $amount="8000.00",string $client="cost"):array{
    $im=imagecreatetruecolor(24,24);imagefill($im,0,0,random_int(0,0xffffff));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$name=bin2hex(random_bytes(16)).'.png';file_put_contents(RECEIPT_UPLOAD_DIR.'/'.$name,$bytes);
    $q=$p->prepare("INSERT INTO Receipts(File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,UploadedBy_UserID,OCR_Status) VALUES(?,?,'image/png',?,?,?,'Failed')");$q->execute(['uploads/receipts/'.$name,'Synthetic S4 proof.png',strlen($bytes),hash('sha256',$bytes),$uid]);$rid=(int)$p->lastInsertId();
    $q=$p->prepare("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message) VALUES(?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,'Synthetic manual review')");$q->execute([$rid,$uid,bin2hex(random_bytes(32)),hash('sha256',$bytes),hash('sha256','manual'),RECEIPT_SCHEMA_VERSION]);
    $d=workspace_attach($p,$uid,s47r(s47id($d)),$rid);$v=$d['payload'];$doc=&$v['documents'][count($v['documents'])-1];$doc['reviewed']=true;
    if($money){$doc['purpose']='amount';$doc['support_side']='debit';$doc['declared_amount']=$doc['accepted_amount']=$amount;$doc['allocations']=[['client_id'=>$client,'amount'=>$amount]];}unset($doc);
    return advance_save($p,$uid,s47r(s47id($d)+['workflow_kind'=>$d['workflow_kind'],'advance_id'=>(string)$d['advance_id'],'payload'=>$v]));
}
function s47manifest(PDO $p):string{
    $out=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){cli_require((bool)preg_match('/\A[a-zA-Z0-9_]+\z/',$t),'Identifier');$rows=[];foreach($p->query('SELECT * FROM `'.$t.'`')->fetchAll(PDO::FETCH_NUM) as $r)$rows[]=hash('sha256',serialize($r));sort($rows);$out[$t]=hash('sha256',implode('|',$rows));}ksort($out);return hash('sha256',json_encode($out));
}
