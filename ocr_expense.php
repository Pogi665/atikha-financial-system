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
$state=$attempt['state']??'';
$failureMessage='';
if($attempt&&$state==='Failed'){
    // Older completed attempts remain immutable; interpret a provider error for display only.
    $provider=json_decode($attempt['raw_response']??'',true);
    $failureMessage=is_array($provider)&&isset($provider['error'])
        ?gemini_receipt_failure_message(['raw'=>$attempt['raw_response']])
        :($attempt['error_message']?:'Automatic reading is unavailable. Continue manually or try again later.');
}
$suggestedAccount='Needs review';
if($data['suggested_debit_account_id']??null){foreach(journal_accounts($pdo) as $account){if((int)$account['CategoryID']===(int)$data['suggested_debit_account_id']){$suggestedAccount=($account['Account_Code']?$account['Account_Code'].' · ':'').$account['Name'];break;}}}
$statusLabel=!$receipt?'Ready to upload':($state==='Processed'?'Ready for review':($state==='Pending'?'Reading image':'Manual entry available'));
$statusStyle=$state==='Processed'?'ready':($state==='Failed'?'warning':'neutral');
layout_begin('Scan Receipt','ocr_expense',[],'<link rel="stylesheet" href="assets/css/ocr_expense.css?v='.filemtime(__DIR__.'/assets/css/ocr_expense.css').'">');
?>
<div class="ocr-workspace">
<header class="ocr-header"><div><p class="ocr-eyebrow">RECEIPT CAPTURE</p><h1>Scan Receipt</h1>
<p>Read a receipt photo, check its details, then continue in General Journal.</p></div>
<?php if($receipt):?><a class="ocr-secondary" href="ocr_expense.php">Scan another receipt</a><?php endif;?></header>
<?php if($error):?><p class="ocr-error" role="alert"><?= $escape($error) ?></p><?php endif;?>
<?php if(isset($_GET['discarded'])):?><p role="status">Receipt discarded.</p><?php endif;?>
<?php if($available):?>
<ol class="ocr-steps" aria-label="Receipt workflow"><li class="<?= $receipt?'is-complete':'is-current' ?>"><span>1</span>Upload image</li><li class="<?= $receipt?'is-current':'' ?>"><span>2</span>Check details</li><li><span>3</span>Review journal</li></ol>
<div class="ocr-grid">
<section class="ocr-card"><div class="ocr-card-heading"><h2><?= $receipt?'Original image':'Upload a receipt' ?></h2><span class="ocr-tag">Private image</span></div>
<?php if(!$receipt):?>
<form id="receipt-upload" method="post" enctype="multipart/form-data" action="ocr_expense.php">
<?= csrf_field() ?><input type="hidden" name="request_key" value="<?= $escape($requestKey) ?>"><input type="hidden" name="action" value="upload">
<div class="ocr-upload-area"><div class="ocr-upload-heading">Choose a clear receipt photo</div><p>Keep the date, merchant and total in the frame.</p>
<label for="receipt-image">Receipt image</label><input id="receipt-image" name="receipt_image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="ocr-file-help" required>
<p id="ocr-file-help" class="ocr-help">JPG, PNG or WebP · up to 8 MiB and 20 megapixels. Convert HEIC/HEIF first.</p></div>
<div id="ocr-local-preview" class="ocr-preview-frame" hidden><img id="ocr-selected-image" alt="Selected receipt preview"></div>
<p id="ocr-selected-file" class="ocr-help" hidden></p>
<button class="ocr-primary" type="submit">Upload and Read Receipt</button>
<p id="ocr-progress" role="status" aria-live="polite"></p>
</form>
<?php else:?>
<div class="ocr-preview-frame"><img class="ocr-preview" src="receipt_attachment.php?receipt_id=<?= (int)$receipt['ReceiptID'] ?>" alt="Uploaded receipt evidence"></div>
<div class="ocr-image-footer"><div><strong><?= $escape($receipt['Original_Filename'] ?: 'Receipt image') ?></strong><p class="ocr-help"><?= $escape(number_format((int)$receipt['File_Size']/1024,1)) ?> KiB · Original saved</p></div>
<a href="receipt_attachment.php?receipt_id=<?= (int)$receipt['ReceiptID'] ?>&amp;download=1">Download original</a></div>
<?php endif;?>
</section>
<section class="ocr-card"><div class="ocr-card-heading"><h2>Receipt details</h2><span class="ocr-tag ocr-tag-<?= $escape($statusStyle) ?>"><?= $escape($statusLabel) ?></span></div>
<?php if($receipt):?>
<?php if($failureMessage):?><div class="ocr-warning" role="status"><strong><?= str_contains($failureMessage,'service is busy')?'AI service is busy':'Automatic reading unavailable' ?></strong><p><?= $escape($failureMessage) ?></p></div><?php endif;?>
<?php if($state==='Pending'):?><p role="status">The image is being read. Reload this page shortly to check the result.</p><?php endif;?>
<?php if($data):?>
<p class="ocr-help">Compare these suggestions with the original image before continuing.</p>
<div class="ocr-amount"><span>Document total</span><strong><?= $escape(($data['currency']??'').' '.($data['total_amount']??'Needs review')) ?></strong><small>An amount due does not confirm that payment was made.</small></div>
<dl class="ocr-facts">
<?php foreach(['merchant'=>'Merchant','document_type'=>'Document type','reference'=>'Reference','date_text'=>'Printed date','transaction_date'=>'Suggested date','currency'=>'Currency','payment_text'=>'Payment information','suggested_debit_account_id'=>'Suggested expense account','classification_reason'=>'Why this account'] as $field=>$label):?>
<dt><?= $escape($label) ?></dt><dd><?= $escape($field==='suggested_debit_account_id'?$suggestedAccount:($data[$field]??'Needs review')) ?></dd>
<?php endforeach;?></dl>
<p class="ocr-help">AI reading confidence: <?= (int)round(($data['confidence']??0)*100) ?>%. This does not verify the document's authenticity.</p>
<?php if(($data['confidence']??0)<RECEIPT_LOW_CONFIDENCE):?><p class="ocr-warning">Low confidence: verify every field carefully.</p><?php endif;?>
<?php foreach($data['warnings']??[] as $warning):?><p class="ocr-warning"><?= $escape($warning) ?></p><?php endforeach;?>
<?php if($data['missing']??[]):?><p class="ocr-warning">Needs review: <?= $escape(implode(', ',$data['missing'])) ?></p><?php endif;?>
<?php endif;?>
<?php if(!$data&&$state!=='Pending'):?><div class="ocr-manual"><h3>Continue with the original image</h3><p>You can enter the date, amount and accounts manually in General Journal. The image stays attached for review.</p></div><?php endif;?>
<?php if($ready):?><a class="ocr-primary" href="general_journal.php?receipt_id=<?= (int)$receipt['ReceiptID'] ?>">Review in General Journal</a>
<p class="ocr-help">Confirm the date and amount, choose the actual credit account and enter the business purpose there. Nothing is posted automatically.</p><?php endif;?>
<?php if($receipt['JournalEntryID']===null):?>
<div class="ocr-actions">
<form method="post" action="ocr_expense.php" id="receipt-retry"><?= csrf_field() ?><input type="hidden" name="request_key" value="<?= $escape($requestKey) ?>"><input type="hidden" name="receipt_id" value="<?= (int)$receipt['ReceiptID'] ?>"><input type="hidden" name="action" value="retry"><button type="submit" <?= $state==='Pending'?'disabled':'' ?>>Retry Extraction</button><span class="ocr-help" id="ocr-retry-progress" role="status" aria-live="polite"></span></form>
<form method="post" action="ocr_expense.php" id="receipt-discard"><?= csrf_field() ?><input type="hidden" name="request_key" value="<?= $escape($requestKey) ?>"><input type="hidden" name="receipt_id" value="<?= (int)$receipt['ReceiptID'] ?>"><input type="hidden" name="action" value="discard"><button type="submit" class="ocr-danger">Discard image</button></form>
</div><?php endif;?>
<?php else:?><div class="ocr-empty"><h3>Your receipt details will appear here</h3><p>Upload an image to suggest the merchant, date, amount and expense account.</p><p class="ocr-help">You check the suggestions and choose the credit account in General Journal. Uploading an image does not post a financial record.</p></div><?php endif;?>
</section></div>
<?php endif;?></div>
<?php layout_end('<script src="assets/js/ocr_expense.js?v='.filemtime(__DIR__.'/assets/js/ocr_expense.js').'"></script>'); ?>
