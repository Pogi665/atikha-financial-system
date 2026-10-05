<?php
require_once __DIR__.'/accounting_workspace.php';require_once __DIR__.'/layout.php';
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit;}
header('Cache-Control: private, no-store');
try{workspace_guard($pdo,(int)$_SESSION['UserID']);$lists=workspace_lists($pdo,(int)$_SESSION['UserID']);}
catch(JournalProblem $e){http_response_code($e->status);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
$title=['CRB'=>'New cash receipt','CDB'=>'New cash payment','GJ'=>'New journal entry'][$workspaceBook];
$esc=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
layout_begin($title,['CRB'=>'crb','CDB'=>'cdb','GJ'=>'general_journal'][$workspaceBook],[], '<link rel="stylesheet" href="assets/css/accounting_workspace.css?v='.filemtime(__DIR__.'/../assets/css/accounting_workspace.css').'">');
?>
<div class="accounting-workspace" id="entry-workspace">
<header class="aw-header"><div><p class="aw-eyebrow">DAILY ACCOUNTING · <?= $workspaceBook ?></p><h1><?= $title ?></h1><p>Prepare an entry, save your draft, then review the complete debit and credit posting.</p></div><a href="accounting_drafts.php">My drafts</a></header>
<p id="aw-status" role="status" aria-live="polite"></p><p id="aw-error" role="alert" tabindex="-1"></p>
<div class="aw-columns"><form id="aw-entry" novalidate class="aw-card">
<h2>Transaction details <span id="aw-draft-label">Unsaved draft</span></h2>
<div class="aw-grid"><label>Date<input id="aw-date" type="date" value="<?= journal_today() ?>" min="1000-01-01" <?= $workspaceBook!=='GJ'?'max="'.journal_today().'"':'' ?>></label>
<label>Reference <small>Optional</small><input id="aw-reference" maxlength="100" placeholder="Voucher, bank or receipt reference"></label>
<label><?= $workspaceBook==='CRB'?'Payer':($workspaceBook==='CDB'?'Payee':'Party (optional)') ?><select id="aw-party"></select><button type="button" data-new-master="parties">+ Add party</button></label>
<label>Default project<select id="aw-default-project"></select><button type="button" id="aw-apply-project">Apply to all lines</button><button type="button" data-new-master="projects">+ Add project</button></label></div>
<label>Purpose<textarea id="aw-purpose" rows="2" maxlength="2000" placeholder="Explain this transaction"></textarea></label>
<?php if($workspaceBook!=='GJ'): ?>
<div class="aw-cash"><div class="aw-grid"><label><?= $workspaceBook==='CRB'?'Receive into':'Pay from' ?><select id="aw-cash-account"></select></label><label>Actual cash/bank amount (PHP)<input id="aw-cash-amount" inputmode="decimal" placeholder="0.00"></label><label>Cash line project<select id="aw-cash-project"></select></label></div></div>
<?php else: ?><label>Entry kind<select id="aw-kind"><option value="ordinary">General journal</option><option value="transfer">Internal cash transfer</option></select></label><?php endif; ?>
<div class="aw-section-heading"><h2><?= $workspaceBook==='CRB'?'Receipt allocations':($workspaceBook==='CDB'?'Payment allocations':'Journal lines') ?></h2><div><?php if($workspaceBook!=='GJ'): ?><label class="aw-inline"><input type="checkbox" id="aw-advanced"> Advanced debit/credit entry</label><?php endif; ?><button type="button" id="aw-add-line">+ Add line</button></div></div>
<p class="aw-help">Line projects determine project activity. Organization operations has no project tag.</p>
<div id="aw-lines"></div><div class="aw-totals" aria-live="polite"><span>Debit <strong id="aw-debits">₱0.00</strong></span><span>Credit <strong id="aw-credits">₱0.00</strong></span><span>Difference <strong id="aw-difference">₱0.00</strong></span></div>
<div class="aw-actions"><button type="button" id="aw-save">Save draft</button><button type="submit" class="aw-primary" id="aw-review">Review entry</button></div>
</form>
<aside class="aw-card"><h2>Supporting documents</h2><p class="aw-help">Optional for ordinary entries. Attached images must be manually reviewed. Photos remain private; uploading does not run OCR.</p>
<label class="aw-upload">Add image (JPG, PNG, WebP; up to 8 MB)<input type="file" id="aw-upload" accept="image/jpeg,image/png,image/webp"></label>
<button type="button" id="aw-upload-retry" hidden>Retry interrupted upload</button>
<div class="aw-grid"><label>Existing unposted image<select id="aw-existing"></select></label><button type="button" id="aw-attach">Attach selected</button></div>
<div id="aw-documents"></div><p class="aw-help">Monetary support covers noncash allocations. Informational documents do not add monetary coverage. Missing or partial support will be shown at review.</p></aside></div>
<section class="aw-card" id="aw-review-panel" hidden><h2>Review before posting</h2><p>This checks the accounting entry. It does not record management approval.</p><div id="aw-review-content"></div><div class="aw-actions"><button type="button" id="aw-back">Back to editing</button><button type="button" class="aw-primary" id="aw-post">Post <?= $workspaceBook==='CRB'?'receipt':($workspaceBook==='CDB'?'payment':'journal') ?></button></div></section>
<section class="aw-card" id="aw-posted" hidden><h2>Entry posted</h2><p id="aw-posted-message"></p><?php if($workspaceBook!=='GJ'): ?><a id="aw-posted-link" href="financial_records.php?view=<?= strtolower($workspaceBook) ?>&amp;from=&amp;to=">View <?= $workspaceBook==='CRB'?'Cash Receipts Book':'Cash Disbursements Book' ?></a> · <?php endif; ?><a id="aw-history-link" href="financial_records.php?from=&amp;to=">View Journal History</a> · <a href="<?= ['CRB'=>'cash_receipt.php','CDB'=>'cash_disbursement.php','GJ'=>'general_journal.php'][$workspaceBook] ?>">Prepare another entry</a></section>
<dialog id="aw-master-dialog"><form id="aw-master-form"><h2 id="aw-master-title">New master</h2><label>Name<input name="name" required maxlength="100"></label><label id="aw-master-code-label">Project code<input name="code" maxlength="30"></label><label id="aw-master-type-label">Party type<select name="party_type"><option value="person">Person</option><option value="organization">Organization</option></select></label><label>Description (optional)<textarea name="description" maxlength="2000"></textarea></label><p id="aw-master-error" role="alert"></p><div class="aw-actions"><button type="button" id="aw-master-cancel">Cancel</button><button class="aw-primary">Create and select</button></div></form></dialog>
</div>
<script type="application/json" id="workspace-data"><?= json_encode(['book'=>$workspaceBook,'csrf_token'=>csrf_token(),'lists'=>$lists,'draft_id'=>is_string($_GET['draft_id']??null)?$_GET['draft_id']:'','receipt_id'=>is_string($_GET['receipt_id']??null)?$_GET['receipt_id']:''],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php layout_end('<script src="assets/js/accounting_workspace.js?v='.filemtime(__DIR__.'/../assets/js/accounting_workspace.js').'"></script>'); ?>
