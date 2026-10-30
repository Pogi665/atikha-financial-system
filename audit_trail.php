<?php
session_start();
require_once __DIR__.'/includes/presentation.php';

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit_query.php';


if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

require_once __DIR__ . '/includes/require_role.php';
require_login();

// Role-based access control. A signed-in non-admin gets a plain refusal rather
// than a redirect, so the denial is unambiguous.
if (($_SESSION['Role'] ?? '') !== 'Admin') {
    http_response_code(403);
    $deniedName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Access Denied — Atikha Financial System</title>
        <script src="https://cdn.tailwindcss.com"></script>
    <?= ui_assets() ?>
</head>
    <body class="atikha-ui min-h-screen bg-slate-100 flex items-center justify-center p-8">
        <div class="max-w-md bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center">
            <h1 class="text-xl font-bold text-slate-900">Access Denied</h1>
            <p class="text-slate-600 mt-3 text-sm">
                The System Audit Trail is restricted to System Administrators.
                <?= $deniedName !== '' ? 'You are signed in as ' . $deniedName . '.' : '' ?>
            </p>
            <a
                href="dashboard.php"
                class="inline-flex items-center rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5 text-sm mt-6 transition"
            >
                Back to Dashboard
            </a>
        </div>
    <script src="assets/js/atikha_ui.js" defer></script>
</body>
    </html>
    <?php
    exit;
}

$csrfToken = csrf_token();
$filters = audit_filters_from_request($_GET);
$tableMissing = false;

/**
 * Tailwind classes for an action badge.
 */
function audit_badge_classes(string $action): string
{
    switch (strtoupper($action)) {
        case 'CREATE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'EDIT':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'APPROVAL':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'DELETE':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'LOGIN':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        case 'LOGOUT':
            return 'bg-slate-50 text-slate-500 border-slate-200';
        default:
            return 'bg-amber-50 text-amber-700 border-amber-200';
    }
}

/**
 * A source_link is only turned into an anchor when it points inside the uploads
 * directory. Anything else (an error reference, a free-form note) is shown as
 * text so a stored value can never become an arbitrary outbound link.
 */
function audit_source_is_linkable(?string $link): bool
{
    if ($link === null || $link === '') {
        return false;
    }

    return strncmp($link, 'uploads/', 8) === 0 && strpos($link, '..') === false;
}

// ---------------------------------------------------------------------------
// CSV export of the currently filtered logs. Runs before any output so the
// download headers are the first thing sent.
// ---------------------------------------------------------------------------
if (($_GET['export'] ?? '') === 'csv') {
    try {
        $exportRows = audit_fetch_logs($pdo, $filters, AUDIT_EXPORT_LIMIT);
    } catch (PDOException $e) {
        error_log('Audit CSV export failed: ' . $e->getMessage());
        header('Location: audit_trail.php?error=export');
        exit;
    }

    $filename = 'audit_logs_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // Excel needs the BOM to read the peso sign and other UTF-8 text.
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Atikha Financial System — System Audit Trail']);
    fputcsv($out, ['Exported', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Filters', audit_filters_describe($filters)]);
    fputcsv($out, ['Rows', count($exportRows)]);
    fputcsv($out, []);
    fputcsv($out, [
        'Log ID',
        'Timestamp',
        'User',
        'Role',
        'Action',
        'Module',
        'Record ID',
        'IP Address',
        'Old Values',
        'New Values',
        'Source Link',
    ]);

    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['FullName'],
            $row['Role'],
            $row['action_type'],
            $row['module'],
            $row['record_id'],
            $row['ip_address'],
            $row['old_values'],
            $row['new_values'],
            $row['source_link'],
        ]);
    }

    fclose($out);
    exit;
}

$errorMessage = isset($_GET['error']) ? 'Unable to export the logs. Please try again.' : '';
$logs = [];
$options = ['users' => [], 'modules' => [], 'actions' => []];
$totalCount = 0;

try {
    $logs = audit_fetch_logs($pdo, $filters, AUDIT_PAGE_LIMIT);
    $options = audit_filter_options($pdo);
    $totalCount = audit_total_count($pdo);
} catch (PDOException $e) {
    error_log('Failed to load audit logs: ' . $e->getMessage());
    $tableMissing = true;
    $errorMessage = 'Audit history is unavailable. Ask the System Administrator to check deployment.';
}


$filtersActive = audit_filters_are_active($filters);
$truncated = count($logs) >= AUDIT_PAGE_LIMIT;

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');

// Admin can use the Financial Operational Workspace; keep the links for them.
$canUseWorkspace = true;
$activePage = 'audit_trail';

$exportQuery = http_build_query(array_merge(
    array_filter($filters, static function ($value) {
        return $value !== '' && $value !== 0;
    }),
    ['export' => 'csv']
));
require_once __DIR__.'/includes/layout.php';
$tab=($_GET['tab']??'')==='alerts'?'alerts':'trail';$alertModel=null;
if($tab==='alerts'){require_once __DIR__.'/includes/audit_alerts.php';try{$alertModel=audit_alert_model($pdo,(int)$_SESSION['UserID']);}catch(Throwable $problem){$errorMessage='Audit Alerts could not be checked. Raw audit history is preserved.';error_log($problem->getMessage());}}
layout_begin('Audit Trail','audit_trail',[],'<link rel="stylesheet" href="assets/css/audit_trail.css?v='.filemtime(__DIR__.'/assets/css/audit_trail.css').'">');$e='ui_escape';
?>
<section class="audit-page"><header class="ui-page-heading"><div><h1>Audit Trail</h1><p>Review recorded activity and the changes behind it.</p></div><a class="ui-button" href="audit_trail.php?<?= $e($exportQuery) ?>">Export History CSV</a></header><nav class="audit-tabs" aria-label="Audit views"><a href="audit_trail.php"<?= $tab==='trail'?' aria-current="page"':'' ?>>Activity History</a><a href="audit_trail.php?tab=alerts"<?= $tab==='alerts'?' aria-current="page"':'' ?>>Audit Alerts</a></nav>
<?php if($errorMessage): ?><p role="alert"><?= $e($errorMessage) ?></p><?php endif; ?>
<?php if($tab==='alerts'): ?><p>These explainable patterns are advisory, not proof of wrongdoing. No automatic account or financial action is taken.</p>
<?php if($alertModel): ?><p>Review period from <?= $e(ui_date($alertModel['from'])) ?> &middot; <?= $alertModel['examined'] ?> recorded events examined.</p><?php if($alertModel['limited']): ?><p role="alert">Only the most recent 5,000 events in this period were examined. This is not a complete review.</p><?php endif;foreach($alertModel['alerts'] as $alert): ?><article class="audit-alert"><h2><?= $e($alert['severity']) ?></h2><p><?= $e($alert['reason']) ?></p><details><summary>Supporting Records</summary><p><?= $e($alert['log_ids']?'Audit events: '.implode(', ',$alert['log_ids']):'See the relevant recorded activity and reasons in Activity History.') ?></p><?php foreach($alert['corrections']??[] as $correction): ?><p><a class="ui-button" href="journal_corrections.php?journal_id=<?= (int)$correction['target_journal_id'] ?>">View Correction #<?= (int)$correction['id'] ?></a> Original #<?= (int)$correction['target_journal_id'] ?><br><?= $e($correction['reason']) ?></p><?php endforeach; ?><p>Rule: <?= $e($alert['rule']) ?> / <?= $e($alert['policy_version']) ?>. Observed: <?= (int)$alert['observed_count'] ?>; threshold: <?= (int)$alert['threshold'] ?><?= isset($alert['window_seconds'])?' in '.(int)($alert['window_seconds']/60).' minutes':'' ?>.</p></details></article><?php endforeach;if(!$alertModel['alerts']): ?><p>No enabled patterns were found in the examined records. This is not a security certification.</p><?php endif; ?><details><summary>Monitoring Coverage</summary><ul><?php foreach($alertModel['coverage'] as $limitation): ?><li><?= $e($limitation) ?></li><?php endforeach; ?></ul><p>Rule version: <?= $e($alertModel['policy']) ?></p></details><?php endif; ?>
<?php else: ?><form method="get" class="audit-controls"><label>Search<input name="q" maxlength="100" value="<?= $e($filters['q']) ?>"></label><label>User<select name="user_id"><option value="0">All users</option><?php foreach($options['users'] as $user): ?><option value="<?= (int)$user['UserID'] ?>"<?= (int)$filters['user_id']===(int)$user['UserID']?' selected':'' ?>><?= $e($user['FullName']) ?></option><?php endforeach; ?></select></label><label>Area<select name="module"><option value="">All areas</option><?php foreach($options['modules'] as $value): ?><option<?= $filters['module']===$value?' selected':'' ?>><?= $e($value) ?></option><?php endforeach; ?></select></label><label>From<input name="date_from" type="date" value="<?= $e($filters['date_from']) ?>"></label><label>To<input name="date_to" type="date" value="<?= $e($filters['date_to']) ?>"></label><label>Activity<select name="action_type"><option value="">All activity</option><?php foreach($options['actions'] as $value): ?><option value="<?= $e($value) ?>"<?= $filters['action_type']===$value?' selected':'' ?>><?= $e(ucwords(strtolower(str_replace('_',' ',$value)))) ?></option><?php endforeach; ?></select></label><div class="ui-actions"><button class="ui-button ui-button-primary">Apply Filters</button><a class="ui-button" href="audit_trail.php">Reset</a></div></form>
<p><?= count($logs) ?> events shown<?= $truncated?' - Result limit reached; narrow the filters for a complete view.':'' ?></p><table class="audit-table"><thead><tr><th>Date &amp; Time</th><th>User</th><th>Activity</th><th>Area</th><th>Record</th><th>Details</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td data-label="Date & Time"><?= $e(ui_datetime($log['created_at'],'local')) ?></td><td data-label="User"><?= $e($log['FullName']) ?><small><?= $e(user_role_label($log['Role'])) ?></small></td><td data-label="Activity"><?= $e(ucwords(strtolower(str_replace('_',' ',$log['action_type'])))) ?></td><td data-label="Area"><?= $e($log['module']) ?></td><td data-label="Record"><?= $log['record_id']?'#'.(int)$log['record_id']:'Not recorded' ?></td><td data-label="Details"><button class="ui-button" type="button" data-audit-details="<?= (int)$log['id'] ?>">View Changes</button></td></tr><?php endforeach;if(!$logs): ?><tr><td colspan="6">No recorded activity matches these filters.</td></tr><?php endif; ?></tbody></table>
<dialog id="audit-detail" class="audit-dialog" aria-labelledby="audit-detail-title"><header><h2 id="audit-detail-title">Activity Details</h2><button class="ui-button" type="button" data-audit-close aria-label="Close activity details">Close</button></header><div id="audit-detail-content"></div></dialog><script type="application/json" id="audit-records"><?php $displayLogs=$logs;$redact=function($value)use(&$redact){if(!is_array($value))return $value;foreach($value as $key=>&$item){if(preg_match('/password|secret|(?:^|_)mfa(?:_|$)|(?:^|_)token(?:_|$)|csrf|api.?key/i',(string)$key))$item='[redacted]';else $item=$redact($item);}unset($item);return $value;};foreach($displayLogs as &$displayLog)foreach(['old_values','new_values'] as $field){$value=json_decode($displayLog[$field]??'null',true);if(is_array($value))$displayLog[$field]=json_encode($redact($value),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}unset($displayLog); ?><?= json_encode($displayLogs,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?></section><?php layout_end('<script src="assets/js/audit_trail.js?v='.filemtime(__DIR__.'/assets/js/audit_trail.js').'" defer></script>'); ?>
