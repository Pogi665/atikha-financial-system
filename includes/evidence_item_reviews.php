<?php
/** Private evidence detail, never journal allocation or monetary coverage. */
require_once __DIR__.'/reporting_common.php';
function item_review_triggers():array
{
    $out=[];foreach(['r7_review'=>['evidence_item_reviews','Confirmed evidence details are immutable'],'r7_link'=>['evidence_item_review_links','Posted item review links are immutable']] as $prefix=>[$table,$message])foreach(['update'=>'UPDATE','delete'=>'DELETE'] as $suffix=>$event)$out[$prefix.'_'.$suffix]=[$table,$event,"BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='$message'; END"];
    $out['r7_root_delete']=['evidence_item_review_roots','DELETE',"BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Evidence review sequence history is retained'; END"];return $out;
}
function item_review_schema(PDO $pdo):string
{
    $found=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('evidence_item_review_roots','evidence_item_reviews','evidence_item_review_links')")->fetchAll(PDO::FETCH_COLUMN);if(!$found)return 'absent';if(count($found)!==3)return 'partial';
    try{
        foreach(['evidence_item_review_roots'=>'id,receipt_id,context_key,draft_id,next_revision','evidence_item_reviews'=>'id,root_id,revision_number,actor_id,actor_name,confirmed_at,source_hash,attempt_id,request_key,request_hash,payload_hash,payload','evidence_item_review_links'=>'association_id,review_id'] as $table=>$cols)$pdo->query("SELECT $cols FROM $table LIMIT 0");
        $triggers=[];foreach($pdo->query('SHOW TRIGGERS') as $t)$triggers[$t['Trigger']]=$t;$normal=static fn($s)=>strtolower(preg_replace('/[\s`]+/u','',$s));foreach(item_review_triggers() as $name=>[$table,$event,$body])if(!isset($triggers[$name])||$triggers[$name]['Table']!==$table||$triggers[$name]['Timing']!=='BEFORE'||$triggers[$name]['Event']!==$event||$normal($triggers[$name]['Statement'])!==$normal($body))return 'partial';
        foreach(['evidence_item_review_roots'=>[['receipt_id','context_key']],'evidence_item_reviews'=>[['root_id','revision_number'],['actor_id','request_key']],'evidence_item_review_links'=>[['association_id']]] as $table=>$required){$indexes=[];foreach($pdo->query('SHOW INDEX FROM '.$table) as $i)if((int)$i['Non_unique']===0)$indexes[$i['Key_name']][(int)$i['Seq_in_index']]=$i['Column_name'];$unique=[];foreach($indexes as $cols){ksort($cols);$unique[]=array_values($cols);}foreach($required as $cols)if(!in_array($cols,$unique,true))return 'partial';}
        $keys=[];foreach($pdo->query("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'r7_%'") as $k)$keys[$k['CONSTRAINT_NAME']]=[$k['TABLE_NAME'],$k['COLUMN_NAME'],$k['REFERENCED_TABLE_NAME'],$k['REFERENCED_COLUMN_NAME']];$fold=(int)$pdo->query('SELECT @@lower_case_table_names')->fetchColumn()!==0;
        foreach(['r7_root_receipt'=>['evidence_item_review_roots','receipt_id','Receipts','ReceiptID'],'r7_root_draft'=>['evidence_item_review_roots','draft_id','journal_drafts','id'],'r7_review_root'=>['evidence_item_reviews','root_id','evidence_item_review_roots','id'],'r7_review_actor'=>['evidence_item_reviews','actor_id','user_identities','UserID'],'r7_review_attempt'=>['evidence_item_reviews','attempt_id','receipt_ocr_attempts','id'],'r7_link_association'=>['evidence_item_review_links','association_id','posted_evidence_associations','id'],'r7_link_review'=>['evidence_item_review_links','review_id','evidence_item_reviews','id']] as $name=>$target){$actual=$keys[$name]??[];if($fold&&isset($actual[2])){$actual[2]=strtolower($actual[2]);$target[2]=strtolower($target[2]);}if($actual!==$target)return 'partial';}
        if($pdo->query('SELECT r.id FROM evidence_item_review_roots r LEFT JOIN evidence_item_reviews v ON v.root_id=r.id GROUP BY r.id,r.next_revision HAVING r.next_revision<>COALESCE(MAX(v.revision_number),0)+1 OR COUNT(v.id)<>COALESCE(MAX(v.revision_number),0) LIMIT 1')->fetchColumn()!==false)return 'partial';return 'complete';
    }catch(Throwable $e){return 'partial';}
}
function item_review_require(PDO $pdo,bool $optional=false):bool
{
    $state=item_review_schema($pdo);if($state==='absent'&&$optional)return false;if($state!=='complete')throw new JournalProblem('Item review setup needs a deployment check. Your financial record was not changed.',503);return true;
}
function item_review_payload(array $r):array
{
    $out=[];foreach(['merchant'=>255,'reference'=>100,'currency'=>3,'date'=>10,'incomplete_reason'=>1000] as $key=>$max)$out[$key]=reporting_text($r[$key]??'',$max,str_replace('_',' ',$key));
    if($out['currency']!=='PHP')throw new JournalProblem('Confirm PHP; currency conversion is not supported.');if($out['date']!=='')try{accounting_date($out['date']);}catch(Throwable $e){throw new JournalProblem('Enter the verified document date or leave it blank.');}
    $out['declared_total']=reporting_amount($r['declared_total']??'','document total',true);$out['complete']=($r['complete']??false)===true;
    if(!is_array($r['items']??null)||!array_is_list($r['items'])||count($r['items'])>100)throw new JournalProblem('Use at most 100 item rows.');$out['items']=[];$sum=0;
    foreach($r['items'] as $row){if(!is_array($row))throw new JournalProblem('Check the item rows.');$item=['description'=>reporting_text($row['description']??'',500,'item description',true),'quantity'=>reporting_text($row['quantity']??'',40,'printed quantity'),'amount'=>reporting_amount($row['amount']??'','item amount')];$c=accounting_cents($item['amount']);if($c<0)throw new JournalProblem('Enter nonnegative item amounts; explain discounts in an incomplete itemization.');$sum=accounting_add($sum,$c);$out['items'][]=$item;}
    $out['item_total']=accounting_decimal($sum);if($out['complete']&&(!$out['items']||$out['item_total']!==$out['declared_total']))throw new JournalProblem('Complete item rows must add exactly to the document total. Otherwise choose Incomplete and explain the difference.');if(!$out['complete']&&trim($out['incomplete_reason'])==='')throw new JournalProblem('Explain missing items, tax, discounts or differences.');if(($r['confirmed']??false)!==true)throw new JournalProblem('Inspect the original image and confirm the details.');return $out;
}
function item_review_context(PDO $pdo,int $uid,int $receiptId,?int $draftId,bool $lock=false):array
{
    reporting_actor($pdo,$uid,['Admin']);require_once __DIR__.'/correction_drafts.php';
    if($draftId===null){$image=receipt_owned($pdo,$receiptId,$uid,$lock);stage1_reserved_guard($pdo,$receiptId);if($image['JournalEntryID']!==null)throw new JournalProblem('Posted details are read-only. Use the correction draft to review a reused image.',409);}
    else{$d=workspace_draft($pdo,$uid,$draftId,$lock);if($d['state']!=='Draft')throw new JournalProblem('This draft is already recorded.',409);$docs=(int)$d['payload_version']===4?($d['payload']['replacement']['documents']??[]):$d['payload']['documents'];if(!in_array($receiptId,array_map('intval',array_column($docs,'receipt_id')),true))throw new JournalProblem('This image does not belong to the draft.',404);if((int)$d['payload_version']===4)$image=correction_receipt($pdo,$uid,$d,$receiptId,$lock);else{$image=receipt_owned($pdo,$receiptId,$uid,$lock);$s=$pdo->prepare('SELECT draft_id FROM draft_evidence_reservations WHERE receipt_id=?');$s->execute([$receiptId]);if((int)$s->fetchColumn()!==$draftId)throw new JournalProblem('The image reservation changed.',409);}}
    receipt_file_verify($image);return ['image'=>$image,'key'=>$draftId===null?'intake':'draft:'.$draftId,'draft_id'=>$draftId];
}
function item_review_current(PDO $pdo,int $receiptId,string $context):?array
{
    $s=$pdo->prepare('SELECT v.*,r.receipt_id,r.context_key FROM evidence_item_reviews v JOIN evidence_item_review_roots r ON r.id=v.root_id WHERE r.receipt_id=? AND r.context_key=? ORDER BY v.revision_number DESC LIMIT 1');$s->execute([$receiptId,$context]);$v=$s->fetch(PDO::FETCH_ASSOC);if(!$v)return null;$payload=json_decode($v['payload'],true,32,JSON_THROW_ON_ERROR);if(!hash_equals($v['payload_hash'],hash('sha256',reporting_json($payload))))throw new JournalProblem('Confirmed item details need an integrity review.',503);$v['data']=$payload;return $v;
}
function item_review_epoch(PDO $pdo,int $receiptId):int
{
    if(!item_review_require($pdo,true))return 0;$s=$pdo->prepare('SELECT COUNT(*) FROM evidence_item_reviews v JOIN evidence_item_review_roots r ON r.id=v.root_id WHERE r.receipt_id=?');$s->execute([$receiptId]);return (int)$s->fetchColumn();
}
function item_review_confirm(PDO $pdo,int $uid,array $r):array
{
    reporting_csrf($r);$rid=reporting_id($r['receipt_id']??null);$draft=reporting_id($r['draft_id']??null,'draft',true);$key=reporting_text($r['request_key']??'',64,'confirmation identity',true);if(!preg_match('/\A[a-f0-9]{64}\z/',$key))throw new JournalProblem('Reload the item review.',400);$expected=$r['expected_revision']??null;if(!is_int($expected)||$expected<0)throw new JournalProblem('Reload the current item review.',400);$payload=item_review_payload($r['payload']??[]);$hash=hash('sha256',reporting_json([$rid,$draft,$expected,$payload]));item_review_require($pdo);
    return reporting_tx($pdo,$uid,['Admin'],function($actor)use($pdo,$uid,$rid,$draft,$key,$expected,$payload,$hash){item_review_require($pdo);$s=$pdo->prepare('SELECT id,request_hash FROM evidence_item_reviews WHERE actor_id=? AND request_key=?');$s->execute([$uid,$key]);if($old=$s->fetch(PDO::FETCH_ASSOC)){if(!hash_equals($old['request_hash'],$hash))throw new JournalProblem('That confirmation identity has different details.',409);return ['id'=>(int)$old['id'],'duplicate'=>true];}
        $context=item_review_context($pdo,$uid,$rid,$draft,true);$current=item_review_current($pdo,$rid,$context['key']);if((int)($current['revision_number']??0)!==$expected)throw new JournalProblem('These details changed in another window. Reload before confirming.',409);
        $pdo->prepare('INSERT INTO evidence_item_review_roots(receipt_id,context_key,draft_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$rid,$context['key'],$draft]);$root=(int)$pdo->lastInsertId();$s=$pdo->prepare('SELECT next_revision FROM evidence_item_review_roots WHERE id=? FOR UPDATE');$s->execute([$root]);$revision=(int)$s->fetchColumn();$a=receipt_latest_attempt($pdo,$rid);$json=reporting_json($payload);
        $s=$pdo->prepare('INSERT INTO evidence_item_reviews(root_id,revision_number,actor_id,actor_name,confirmed_at,source_hash,attempt_id,request_key,request_hash,payload_hash,payload) VALUES(?,?,?,?,UTC_TIMESTAMP(),?,?,?,?,?,?)');$s->execute([$root,$revision,$uid,$actor['FullName'],$context['image']['File_SHA256'],$a['id']??null,$key,$hash,hash('sha256',$json),$json]);$id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE evidence_item_review_roots SET next_revision=next_revision+1 WHERE id=?')->execute([$root]);
        if($a&&$a['state']==='Pending'){$pdo->prepare("UPDATE receipt_ocr_attempts SET state='Failed',completed_at=UTC_TIMESTAMP(),error_message='Manual review completed; automatic result superseded' WHERE id=? AND state='Pending'")->execute([$a['id']]);$pdo->prepare("UPDATE Receipts SET OCR_Status='Failed' WHERE ReceiptID=? AND JournalEntryID IS NULL")->execute([$rid]);}
        reporting_audit($pdo,$uid,AUDIT_ACTION_EDIT,'Evidence item review',$id,null,['receipt_id'=>$rid,'draft_id'=>$draft,'revision'=>$revision,'payload_hash'=>hash('sha256',$json)]);stage1_write_changed($pdo);return ['id'=>$id,'revision'=>$revision,'duplicate'=>false];
    });
}
/** Called only after the writer has locked the actual owned receipt. Never expose via Management models. */
function item_review_resource(PDO $pdo,array $receipt,array $d,array $doc):?array
{
    if(!item_review_require($pdo,true))return null;$rid=(int)$receipt['ReceiptID'];$v=item_review_current($pdo,$rid,'draft:'.$d['id']);if(!$v&&$receipt['JournalEntryID']===null)$v=item_review_current($pdo,$rid,'intake');
    if(!$v&&isset($receipt['source_association'])){$s=$pdo->prepare('SELECT review_id FROM evidence_item_review_links WHERE association_id=?');$s->execute([$receipt['source_association']['id']]);if($s->fetchColumn()!==false)throw new JournalProblem('Review the reused image item details again for this correction.',409);}
    if(!$v)return null;if(!hash_equals($receipt['File_SHA256'],$v['source_hash']))throw new JournalProblem('The reviewed image changed. Inspect it again.',409);if($doc['purpose']==='amount'&&$v['data']['declared_total']!==$doc['declared_amount'])throw new JournalProblem('The document amount differs from its confirmed item review. Check both before reviewing the journal.',409);return ['id'=>(int)$v['id'],'payload_hash'=>$v['payload_hash'],'revision'=>(int)$v['revision_number']];
}
function item_review_link(PDO $pdo,int $association,?array $review):void
{
    if($review!==null)$pdo->prepare('INSERT INTO evidence_item_review_links(association_id,review_id) VALUES(?,?)')->execute([$association,$review['id']]);
}
function item_review_posted_model(PDO $pdo,int $uid,int $rid,int $journal):array
{
    reporting_actor($pdo,$uid,['Admin']);item_review_require($pdo);require_once __DIR__.'/receipt_ocr.php';
    if(!receipt_posted_download_allowed($pdo,$rid,$journal))throw new JournalProblem('Recorded evidence is unavailable or access restricted.',404);
    $s=$pdo->prepare('SELECT * FROM posted_evidence_associations WHERE receipt_id=? AND journal_id=?');$s->execute([$rid,$journal]);$association=$s->fetch(PDO::FETCH_ASSOC);if(!$association||!stage3_evidence_association_valid($pdo,$association))throw new JournalProblem('Recorded evidence needs an integrity review.',503);
    $s=$pdo->prepare('SELECT v.* FROM evidence_item_review_links l JOIN evidence_item_reviews v ON v.id=l.review_id WHERE l.association_id=?');$s->execute([$association['id']]);$v=$s->fetch(PDO::FETCH_ASSOC);if(!$v)throw new JournalProblem('No item review was recorded for this image.',404);
    $s=$pdo->prepare('SELECT * FROM Receipts WHERE ReceiptID=?');$s->execute([$rid]);$image=$s->fetch(PDO::FETCH_ASSOC);receipt_file_verify($image);$data=json_decode($v['payload'],true,32,JSON_THROW_ON_ERROR);
    return ['read_only'=>true,'receipt_id'=>$rid,'draft_id'=>null,'revision'=>(int)$v['revision_number'],'name'=>$image['Original_Filename'],'image_url'=>'receipt_attachment.php?receipt_id='.$rid.'&journal_id='.$journal,'return_url'=>'journal_transaction.php?journal_id='.$journal,'data'=>$data,'confirmed_by'=>$v['actor_name'],'confirmed_at'=>$v['confirmed_at']];
}
function item_review_urls(PDO $pdo,array $journals):array
{
    if(!$journals||!stage2_private_viewer($pdo)||!item_review_require($pdo,true))return [];$out=[];
    foreach(array_chunk($journals,500) as $ids){$s=$pdo->prepare("SELECT a.journal_id,a.receipt_id FROM posted_evidence_associations a JOIN evidence_item_review_links l ON l.association_id=a.id JOIN journal_entries j ON j.id=a.journal_id AND j.status='posted' WHERE a.journal_id IN (".implode(',',array_fill(0,count($ids),'?')).')');$s->execute(array_values($ids));foreach($s as $row)$out[(int)$row['journal_id']][(int)$row['receipt_id']]='evidence_item_review.php?receipt_id='.$row['receipt_id'].'&journal_id='.$row['journal_id'];}return $out;
}
function item_review_association_valid(PDO $pdo,array $association,array $image):bool
{
    try{if(!item_review_require($pdo,true))return true;$snapshot=$association['review_snapshot']===null?[]:json_decode($association['review_snapshot'],true,64,JSON_THROW_ON_ERROR);$expected=$snapshot['item_review']??null;$s=$pdo->prepare('SELECT v.*,r.receipt_id FROM evidence_item_review_links l JOIN evidence_item_reviews v ON v.id=l.review_id JOIN evidence_item_review_roots r ON r.id=v.root_id WHERE l.association_id=?');$s->execute([$association['id']]);$v=$s->fetch(PDO::FETCH_ASSOC);if(!$expected)return !$v;if(!$v||(int)$v['id']!==($expected['id']??null)||(int)$v['revision_number']!==($expected['revision']??null)||(int)$v['receipt_id']!==(int)$association['receipt_id']||!hash_equals($image['File_SHA256'],$v['source_hash'])||!hash_equals($v['payload_hash'],$expected['payload_hash']??''))return false;$payload=json_decode($v['payload'],true,32,JSON_THROW_ON_ERROR);return hash_equals($v['payload_hash'],hash('sha256',reporting_json($payload)));}catch(Throwable $e){return false;}
}
