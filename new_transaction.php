<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_role(['Admin'],'New Transaction');require_once __DIR__.'/includes/layout.php';require_once __DIR__.'/includes/receipt_ocr.php';
$stage1=stage1_enabled($pdo);$scan=receipt_enabled($pdo);layout_begin('New Transaction','new_transaction');ui_page_heading('New Transaction','Choose what you want to record.');
$sections=[];
if($stage1)$sections[]=['Choose a transaction','',[
 ['cash_disbursement.php','Record a Payment','Record a payment made to a supplier, partner, employee, or other payee.','paid'],
 ['cash_receipt.php','Record Money Received','Record money received from a partner, project, or other source.','received']
],true];
$other=[['general_journal.php','General Journal Entry','Record adjustments or other journal entries.','book']];
if($stage1)$other[]=['advanced_accounting.php','Advanced Accounting Entry','Multiple allocations or detailed debits and credits for payments and receipts.','book'];
if($scan)$other[]=['ocr_expense.php','Scan Receipt','Review receipt details and continue to General Journal.','scan'];
$sections[]=['Other accounting tasks','Other accounting tasks',$other,false];
if($stage1)$sections[]=['Continue existing work','Continue existing work',[
 ['accounting_drafts.php','My Drafts','Resume transactions you saved earlier.','file']
],false];
?><div class="ui-task-launcher"><?php foreach($sections as [$name,$heading,$tasks,$primary]): ?><section aria-label="<?= ui_escape($name) ?>"><?php if($heading): ?><h2><?= ui_escape($heading) ?></h2><?php endif; ?><div class="ui-launcher-grid<?= $primary?' ui-launcher-primary':'' ?>"><?php foreach($tasks as [$url,$label,$description,$icon]): ?><a class="ui-task" href="<?= ui_escape($url) ?>"><span class="ui-task-icon"><?= ui_icon($icon) ?></span><div><strong><?= ui_escape($label) ?></strong><p><?= ui_escape($description) ?></p></div><?= ui_icon('chevron') ?></a><?php endforeach; ?></div></section><?php endforeach; ?></div><?php if(!$stage1||!$scan): ?><p class="ui-quiet">Some tasks are unavailable until their existing accounting or receipt setup is enabled.</p><?php endif; ?><?php layout_end(); ?>
