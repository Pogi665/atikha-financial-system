<?php
/** Evidence workspace; financial posting is exclusively in General Journal. */
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/require_role.php';
require_once __DIR__.'/includes/receipt_ocr.php';
require_once __DIR__.'/includes/layout.php';
require_login();require_role(['Admin'],'Scan Receipt');
header('Cache-Control: no-store');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$error='';$ready=false;$receipt=null;$attempt=null;$data=[];$available=false;
$uid=(int)$_SESSION['UserID'];$receiptId=null;
try {
    $available=receipt_enabled($pdo);
    if(!$available){throw new JournalProblem('Scan Receipt is unavailable until migration 018 and protected-storage deployment are completed.',503);}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=journal_string($_POST,'action');
        if($action==='upload'){$receiptId=receipt_upload($pdo,$uid,$_POST,$_FILES['receipt_image']??[]);receipt_extract($pdo,$uid,$receiptId,$_POST);}
        elseif($action==='retry'){$receiptId=journal_id(journal_string($_POST,'receipt_id'));receipt_extract($pdo,$uid,$receiptId,$_POST);}
        elseif($action==='discard'){$receiptId=journal_id(journal_string($_POST,'receipt_id'));receipt_discard($pdo,$uid,$receiptId,$_POST);header('Location: ocr_expense.php?discarded=1',true,303);exit;}
        else{throw new JournalProblem('Unsupported action.',400);}
        header('Location: ocr_expense.php?receipt='.$receiptId,true,303);exit;
    }
    if(isset($_GET['receipt'])){$receiptId=journal_id(journal_string($_GET,'receipt'));}
    if($receiptId){
        $receipt=receipt_owned($pdo,$receiptId,$uid);
        if($receipt['JournalEntryID']!==null){throw new JournalProblem('This receipt is posted in journal #'.(int)$receipt['JournalEntryID'].'. Use Journal History.',409);}
        receipt_file_verify($receipt);
        $attempt=receipt_latest_attempt($pdo,$receiptId);
        $data=$attempt&&$attempt['normalized_json']!==null?json_decode($attempt['normalized_json'],true,512,JSON_THROW_ON_ERROR):[];
        $ready=$attempt&&$attempt['state']!=='Pending';
    }
} catch(JournalProblem $e){http_response_code($e->status);$error=$e->getMessage();}
catch(Throwable $e){error_log('Receipt workspace failed: '.$e->getMessage());http_response_code(503);$error='Receipt workspace is temporarily unavailable.';}
$requestKey=receipt_request_key();
layout_begin('Scan Receipt','ocr_expense',[],'<link rel="stylesheet" href="assets/css/ocr_expense.css?v='.filemtime(__DIR__.'/assets/css/ocr_expense.css').'">');
?>
<div class="ocr-workspace">
<h1 class="text-2xl font-bold text-slate-900">Scan Receipt</h1>
<p>Upload evidence, review what the AI reads, then prepare a balanced General Journal entry.</p>
<?php if($error):?><p class="ocr-error" role="alert"><?= $escape($error) ?></p><?php endif;?>
<?php if(isset($_GET['discarded'])):?><p role="status">Receipt discarded.</p><?php endif;?>
<?php if($available):?>
<div class="ocr-grid">
<section class="ocr-card"><h2>Receipt evidence</h2>
<?php if(!$receipt):?>
<form id="receipt-upload" method="post" enctype="multipart/form-data" action="ocr_expense.php">
<?= csrf_field() ?><input type="hidden" name="request_key" value="<?= $escape($requestKey) ?>"><input type="hidden" name="action" value="upload">
<label for="receipt-image">Choose or capture a receipt photo</label>
<input id="receipt-image" name="receipt_image" type="file" accept="image/jpeg,image/png,image/webp" required>
<p>JPG, PNG or WebP, up to 8 MiB and 20 megapixels. Convert HEIC/HEIF first.</p>
<button class="ocr-primary" type="submit">Upload and Read Receipt</button>
<p id="ocr-progress" role="status" aria-live="polite"></p>
</form>
<?php else:?>
<img class="ocr-preview" src="receipt_attachment.php?receipt_id=<?= (int)$receipt['ReceiptID'] ?>" alt="Uploaded receipt evidence">
<p><?= $escape($receipt['Original_Filename'] ?: 'Receipt image') ?> ? <?= (int)$receipt['File_Size'] ?> bytes</p>
<a href="receipt_attachment.php?receipt_id=<?= (int)$receipt['ReceiptID'] ?>&amp;download=1">Download original</a>
<?php endif;?>
</section>
<section class="ocr-card"><h2>Extraction proposal</h2>
<p>Storing or reading an image does not post a financial record. Verify the original image; confidence does not prove authenticity.</p>
<?php if($receipt):?>
<p>Status: <strong><?= $escape($attempt['state']??'Awaiting extraction') ?></strong></p>
<?php if($attempt&&$attempt['error_message']):?><p class="ocr-warning"><?= $escape($attempt['error_message']) ?></p><?php endif;?>
<?php if($data):?>
<dl class="ocr-facts">
<?php foreach(['merchant'=>'Merchant','document_type'=>'Document type','reference'=>'Printed reference','date_text'=>'Printed date','transaction_date'=>'Proposed date','currency'=>'Currency','total_amount'=>'Document total','payment_text'=>'Printed payment information','suggested_debit_account_id'=>'Suggested Expense account ID','classification_reason'=>'Classification explanation'] as $field=>$label):?>
<dt><?= $escape($label) ?></dt><dd><?= $escape($data[$field]??'Needs review') ?></dd>
<?php endforeach;?></dl>
<p>Extraction confidence: <?= (int)round(($data['confidence']??0)*100) ?>%</p>
<?php if(($data['confidence']??0)<RECEIPT_LOW_CONFIDENCE):?><p class="ocr-warning">Low confidence: verify every field carefully.</p><?php endif;?>
<?php foreach($data['warnings']??[] as $warning):?><p class="ocr-warning"><?= $escape($warning) ?></p><?php endforeach;?>
<?php if($data['missing']??[]):?><p class="ocr-warning">Needs review: <?= $escape(implode(', ',$data['missing'])) ?></p><?php endif;?>
<?php endif;?>
<?php if($ready):?><a class="ocr-primary" href="general_journal.php?receipt_id=<?= (int)$receipt['ReceiptID'] ?>">Review in General Journal</a>
<p>Select the actual credit account and enter the business purpose before clicking Post Entry.</p><?php endif;?>
<?php if($receipt['JournalEntryID']===null):?>
<div class="ocr-actions">
<form method="post" action="ocr_expense.php"><?= csrf_field() ?><input type="hidden" name="request_key" value="<?= $escape($requestKey) ?>"><input type="hidden" name="receipt_id" value="<?= (int)$receipt['ReceiptID'] ?>"><input type="hidden" name="action" value="retry"><button type="submit">Retry Extraction</button></form>
<form method="post" action="ocr_expense.php" id="receipt-discard"><?= csrf_field() ?><input type="hidden" name="request_key" value="<?= $escape($requestKey) ?>"><input type="hidden" name="receipt_id" value="<?= (int)$receipt['ReceiptID'] ?>"><input type="hidden" name="action" value="discard"><button type="submit">Discard Unposted Receipt</button></form>
</div><?php endif;?>
<a href="ocr_expense.php">Scan another receipt</a>
<?php else:?><p>Upload a receipt to begin. You will choose the credit account yourself.</p><?php endif;?>
</section></div>
<?php endif;?></div>
<?php layout_end('<script src="assets/js/ocr_expense.js?v='.filemtime(__DIR__.'/assets/js/ocr_expense.js').'"></script>'); ?>
