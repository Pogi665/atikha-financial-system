<?php
/** Shared report navigation only; financial models and frozen readers remain authoritative. */
function ui_report_navigation(string $component,callable $url,bool $print=false):void {
    if($print)return;
    $primary=['income_statement'=>'Income & Expenses','financial_position'=>'Financial Position','cash_flows'=>'Cash Flow','budget_actuals'=>'Budget vs Actual'];
    $details=['worksheet'=>'Monthly Worksheet','trial_balance'=>'Trial Balance','project_activity'=>'Project Activity','advance_aging'=>'Advance Aging'];
    ?><nav class="ui-report-tabs fr-no-print" aria-label="Reports"><?php foreach($primary as $key=>$label): ?><a href="<?= ui_escape($url($key)) ?>"<?= $key===$component?' aria-current="page"':'' ?>><?= ui_escape($label) ?></a><?php endforeach; ?></nav><details class="ui-accounting-details fr-no-print"<?= isset($details[$component])?' open':'' ?>><summary>Supporting Accounting Schedules</summary><nav aria-label="Supporting reports"><?php foreach($details as $key=>$label): ?><a class="ui-button" href="<?= ui_escape($url($key)) ?>"<?= $key===$component?' aria-current="page"':'' ?>><?= ui_escape($label) ?></a><?php endforeach; ?><p>Other Accounting Records</p><a class="ui-button" href="general_ledger.php">General Ledger</a><a class="ui-button" href="financial_records.php?view=crb">Cash Receipts Book</a><a class="ui-button" href="financial_records.php?view=cdb">Cash Disbursements Book</a><a class="ui-button" href="financial_records.php">Find a Transaction</a><a class="ui-button" href="reports.php">Saved Trial Balance Copies</a></nav></details><?php
}
