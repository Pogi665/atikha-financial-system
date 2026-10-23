<?php
session_start();require_once __DIR__.'/db_connect.php';require_once __DIR__.'/includes/require_role.php';require_login();require_once __DIR__.'/includes/layout.php';require_once __DIR__.'/includes/presentation_queries.php';
$month=substr(accounting_today(),0,7);$data=null;$error='';
try{$month=reporting_month($_GET['month']??$month);$data=ui_dashboard_model($pdo,(int)$_SESSION['UserID'],$month);}catch(ReportingProblem $e){http_response_code($e->status);$error=$e->getMessage();}catch(Throwable $e){http_response_code(503);$error='Dashboard information is unavailable. Please try again.';error_log('Dashboard projection unavailable.');}
$admin=($_SESSION['Role']??'')==='Admin';$period=(new DateTimeImmutable($month.'-01'))->format('F Y');$money=static fn($v)=>$v===null?'Unavailable':accounting_money((string)$v);
layout_begin('Dashboard','dashboard',['https://cdn.jsdelivr.net/npm/chart.js']);
ui_page_heading($admin?'Dashboard':'Management Dashboard','', $admin?'<a class="ui-primary" href="new_transaction.php">'.ui_icon('plus').'New Transaction</a>':'');
?><form method="get" class="ui-dashboard-period"><label for="ui-dashboard-month">Period</label><input type="month" name="month" id="ui-dashboard-month" value="<?= ui_escape($month) ?>" max="<?= substr(accounting_today(),0,7) ?>" required><button class="ui-button" type="submit">Apply</button></form>
<?php if($error): ?><p role="alert"><?= ui_escape($error) ?> <a href="dashboard.php">Try Again</a></p><?php endif; ?>
<?php if($data): ?>
<?php foreach($data['errors'] as $message): ?><p role="alert"><?= ui_escape($message) ?></p><?php endforeach; ?>
<?php
$budget=$data['budget'];$util=$budget&&$budget['utilization']!==null?number_format($budget['utilization'],2).'%':'N/A';
$cards=$admin?[
 ['Cash Balance',$money($data['cash']),'As of '.$data['as_of'],'Recorded balance of configured cash/bank accounts; not a live bank-feed balance.'],
 ['Money Received',$money($data['received']),$period,'Gross originating Cash Receipts Book cash-side activity. Includes corrected originals and replacements; this is not recognized income or net bank movement.'],
 ['Payments',$money($data['payments']),$period,'Gross originating Cash Disbursements Book cash-side activity. Includes corrected originals and replacements; General Journal supplies correction offsets.'],
 ['Outstanding Cash Advances',$money($data['outstanding']),'As of '.$data['as_of'],'Released funds still to settle. A release creates an advance asset, not an expense.'],
 ['Drafts to Finish',$data['drafts']===null?'Unavailable':(string)$data['drafts'],'Current / Your Private Drafts',''],
 ['Review Items',$data['pending']===null?'Unavailable':(string)$data['pending'],'Current / Financial Reviews','Budget submissions, complete monthly reports and supported Trial Balance copies. Historical Board messages are excluded.']
]:[
 ['Cash Balance',$money($data['cash']),'As of '.$data['as_of'],'Recorded balance of configured cash/bank accounts; not a live bank-feed balance.'],
 ['Income',$money($data['income']),$period,'Recognized income from posted journal lines, including corrections.'],
 ['Expenses',$money($data['expenses']),$period,'Signed recognized expenses, not cash-payment totals.'],
 ['Net Result',$money($data['net']),$period,'Recognized income less recognized expenses.'],
 ['Budget Utilization',$util,$budget&&$budget['approved']?$period:'No Approved Budget','Uses the effective approved budget and signed recognized expense actuals. Missing allocation is not zero.'],
 ['Pending Reviews',$data['pending']===null?'Unavailable':(string)$data['pending'],'Current / Financial Reviews','Open the exact submitted item before making a decision.']
];
?><section class="ui-kpis" aria-label="Financial overview"><?php foreach($cards as $n=>[$label,$value,$scope,$help]): ?><div class="ui-kpi<?= $n===0?' ui-kpi-cash':'' ?><?= $label==='Net Result'&&$data['net']!==null&&accounting_cents($data['net'])<0?' ui-kpi-deficit':'' ?>"><div class="ui-kpi-label"><?= ui_escape($label) ?><?php if($help!=='')ui_help($label.' basis',$help); ?></div><strong><?= ui_escape($value) ?></strong><span><?= ui_escape($scope) ?></span></div><?php endforeach; ?></section>
<section class="ui-attention"><h2>Needs Attention</h2><?php if(!$data['attention']&&!$data['errors']): ?><p class="ui-quiet">No current items need your attention.</p><?php endif; ?><?php foreach($data['attention'] as [$title,$detail,$url,$state]): ?><div class="ui-attention-row"><div><strong><?= ui_escape($title) ?></strong><p><?= ui_escape($detail) ?></p></div><?= ui_badge($state,$state==='Overdue'?'error':'pending') ?><a class="ui-button" href="<?= ui_escape($url) ?>">Open<?= ui_icon('chevron') ?></a></div><?php endforeach; ?></section>
<?php if($admin): ?><section><h2>Quick Actions</h2><div class="ui-quick-actions"><a class="ui-primary" href="cash_disbursement.php"><?= ui_icon('plus') ?>Record a Payment</a><a class="ui-button" href="cash_receipt.php"><?= ui_icon('plus') ?>Record Money Received</a><a class="ui-button" href="cash_advances.php">Cash Advances</a><a class="ui-button" href="financial_records.php">Find a Transaction</a></div></section><?php else: ?>
<div class="ui-dashboard-charts"><section class="ui-card"><h2>Income vs Expenses</h2><p class="ui-quiet"><?= ui_escape($data['series']?$data['series'][0]['month'].' to '.$month:$period) ?></p><div class="ui-chart"><canvas id="ui-income-chart" role="img" aria-label="Income versus expenses. Exact amounts are in the table below."></canvas></div><p data-chart-status class="ui-quiet"></p><details><summary>Recorded Amounts</summary><table><caption class="ui-quiet">Recognized Monthly Amounts (PHP)</caption><thead><tr><th scope="col">Month</th><th scope="col">Income</th><th scope="col">Expenses</th></tr></thead><tbody><?php foreach($data['series'] as $row): ?><tr><th scope="row"><?= ui_escape($row['month']) ?></th><td><?= ui_escape($money($row['income_decimal'])) ?></td><td><?= ui_escape($money($row['expenses_decimal'])) ?></td></tr><?php endforeach; ?></tbody></table></details></section>
<section class="ui-card"><h2>Budget vs Actual</h2><?php if($budget&&$budget['approved_total']!==null): ?><div class="ui-chart"><canvas id="ui-budget-chart" role="img" aria-label="Approved budget versus recognized actual. Exact values are below."></canvas></div><p data-chart-status class="ui-quiet"></p><p>Approved: <strong><?= ui_escape($money($budget['approved_total'])) ?></strong> &middot; Actual: <strong><?= ui_escape($money($budget['actual_total'])) ?></strong></p><?php elseif($budget): ?><p>No Approved Budget</p><?php else: ?><p role="alert">Budget information is unavailable.</p><?php endif; ?><a class="ui-button" href="budgets.php?month=<?= ui_escape($month) ?>">Open Budgets</a></section></div>
<?php endif; ?>
<script type="application/json" id="ui-dashboard-data"><?= json_encode(['series'=>$data['series'],'budget'=>$budget?['approved_total'=>$budget['approved_total'],'actual_total'=>$budget['actual_total']]:null],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?><?php layout_end('<script src="assets/js/dashboard.js" defer></script>'); ?>
