<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit_query.php';
require_once __DIR__ . '/includes/gemini_client.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

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
    </head>
    <body class="min-h-screen bg-slate-100 flex items-center justify-center p-8">
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
    $errorMessage = 'The audit_logs table is not available. Run migrations/002_audit_trail.sql against the database.';
}

$aiConfigured = gemini_is_configured();
$filtersActive = audit_filters_are_active($filters);
$truncated = count($logs) >= AUDIT_PAGE_LIMIT;

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');

$exportQuery = http_build_query(array_merge(
    array_filter($filters, static function ($value) {
        return $value !== '' && $value !== 0;
    }),
    ['export' => 'csv']
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-100">
    <aside class="fixed inset-y-0 left-0 w-64 bg-slate-800 text-slate-100 flex flex-col">
        <div class="px-6 py-6 border-b border-slate-700">
            <h2 class="text-lg font-bold tracking-tight">Atikha Finance</h2>
            <p class="text-slate-400 text-xs mt-1">Management System</p>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1">
            <a
                href="dashboard.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Dashboard
            </a>
            <a
                href="funds.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Incoming Funds
            </a>
            <a
                href="expenses.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Expenses
            </a>
            <a
                href="ocr_expense.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Scan Receipt
            </a>
            <a
                href="reports.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Reports
            </a>
            <a
                href="audit_trail.php"
                class="block rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-medium text-white"
            >
                Audit Trail
            </a>
        </nav>
    </aside>

    <div class="ml-64 flex flex-col min-h-screen">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Signed in as</p>
                <p class="text-slate-900 font-semibold">
                    <?= $fullName ?>
                    <span class="text-slate-400 font-normal">·</span>
                    <span class="text-slate-600 font-medium text-sm"><?= $role ?></span>
                </p>
            </div>
            <a
                href="logout.php"
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
            >
                Logout
            </a>
        </header>

        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">System Audit Trail</h1>
                    <p class="text-slate-600 mt-2">
                        Append-only record of every action taken in the system.
                        Entries can never be edited or removed.
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-sm text-slate-500">Total entries</p>
                    <p class="text-2xl font-bold text-slate-900"><?= number_format($totalCount) ?></p>
                </div>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                    <p class="text-sm text-red-600 font-medium">
                        <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!$aiConfigured): ?>
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                    <p class="text-sm text-amber-800 font-medium">
                        AI monitoring is unavailable: add your Gemini API key to config.php to enable
                        the anomaly scan and audit summary.
                    </p>
                </div>
            <?php endif; ?>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <form method="GET" action="audit_trail.php" class="grid grid-cols-6 gap-4 items-end">
                    <div class="col-span-2">
                        <label for="q" class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="User, IP, or changed value"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        >
                    </div>
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-slate-700 mb-1">User</label>
                        <select
                            id="user_id"
                            name="user_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        >
                            <option value="">All users</option>
                            <?php foreach ($options['users'] as $user): ?>
                                <option
                                    value="<?= (int) $user['UserID'] ?>"
                                    <?= $filters['user_id'] === (int) $user['UserID'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($user['FullName'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="module" class="block text-sm font-medium text-slate-700 mb-1">Module</label>
                        <select
                            id="module"
                            name="module"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        >
                            <option value="">All modules</option>
                            <?php foreach ($options['modules'] as $module): ?>
                                <option
                                    value="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $filters['module'] === $module ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="action_type" class="block text-sm font-medium text-slate-700 mb-1">Action</label>
                        <select
                            id="action_type"
                            name="action_type"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        >
                            <option value="">All actions</option>
                            <?php foreach ($options['actions'] as $actionOption): ?>
                                <option
                                    value="<?= htmlspecialchars($actionOption, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $filters['action_type'] === $actionOption ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($actionOption, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-4 text-sm transition"
                        >
                            Filter
                        </button>
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-slate-700 mb-1">From</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        >
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-slate-700 mb-1">To</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        >
                    </div>
                    <div class="col-span-4 flex items-center justify-end gap-3">
                        <?php if ($filtersActive): ?>
                            <a href="audit_trail.php" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition">
                                Clear filters
                            </a>
                        <?php endif; ?>
                        <button
                            type="button"
                            id="btn-scan"
                            <?= $aiConfigured && !$tableMissing ? '' : 'disabled' ?>
                            class="rounded-lg bg-purple-700 hover:bg-purple-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold py-2.5 px-4 text-sm transition"
                        >
                            Run Anomaly Scan
                        </button>
                        <button
                            type="button"
                            id="btn-summary"
                            <?= $aiConfigured && !$tableMissing ? '' : 'disabled' ?>
                            class="rounded-lg bg-blue-700 hover:bg-blue-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold py-2.5 px-4 text-sm transition"
                        >
                            Generate Audit Summary
                        </button>
                        <a
                            href="audit_trail.php?<?= htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') ?>"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition"
                        >
                            Export to CSV
                        </a>
                    </div>
                </form>
            </section>

            <section
                id="scan-panel"
                class="hidden bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden"
            >
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Suspicious Activity</h2>
                        <p id="scan-subtitle" class="text-sm text-slate-500 mt-1"></p>
                    </div>
                    <span id="scan-risk" class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"></span>
                </div>
                <div class="p-6 space-y-3">
                    <p id="scan-assessment" class="text-sm text-slate-700"></p>
                    <div id="scan-findings" class="space-y-3"></div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Activity Log</h2>
                    <p class="text-sm text-slate-500">
                        Showing <?= number_format(count($logs)) ?> <?= count($logs) === 1 ? 'entry' : 'entries' ?><?= $truncated ? ' (most recent ' . number_format(AUDIT_PAGE_LIMIT) . '; narrow the filters to see older activity)' : '' ?>
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Timestamp</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">User</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Action</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Module</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Record</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">IP Address</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Evidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                        <?= $tableMissing
                                            ? 'The audit trail is not initialized yet.'
                                            : ($filtersActive ? 'No entries match these filters.' : 'No audit entries recorded yet.') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition" id="log-<?= (int) $log['id'] ?>">
                                        <td class="px-6 py-3 text-slate-700 whitespace-nowrap">
                                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $log['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <p class="text-slate-900 font-medium">
                                                <?= htmlspecialchars((string) $log['FullName'], ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                <?= htmlspecialchars((string) $log['Role'], ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= audit_badge_classes((string) $log['action_type']) ?>">
                                                <?= htmlspecialchars((string) $log['action_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-slate-700">
                                            <?= htmlspecialchars((string) $log['module'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-500">
                                            <?= $log['record_id'] !== null ? '#' . (int) $log['record_id'] : '—' ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-500 font-mono text-xs">
                                            <?= htmlspecialchars((string) $log['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap space-x-2">
                                            <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                                <button
                                                    type="button"
                                                    class="js-view-shift rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition"
                                                    data-shift="<?= htmlspecialchars(json_encode([
                                                        'id'     => (int) $log['id'],
                                                        'user'   => (string) $log['FullName'],
                                                        'action' => (string) $log['action_type'],
                                                        'module' => (string) $log['module'],
                                                        'at'     => (string) $log['created_at'],
                                                        'old'    => $log['old_values'],
                                                        'new'    => $log['new_values'],
                                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                                                >
                                                    View Shift
                                                </button>
                                            <?php endif; ?>
                                            <?php if (audit_source_is_linkable($log['source_link'])): ?>
                                                <a
                                                    href="<?= htmlspecialchars((string) $log['source_link'], ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 transition"
                                                >
                                                    Source Asset
                                                </a>
                                            <?php elseif (!empty($log['source_link'])): ?>
                                                <span class="text-xs text-slate-400">
                                                    <?= htmlspecialchars((string) $log['source_link'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div id="shift-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-8">
        <div class="w-full max-w-4xl bg-white rounded-xl shadow-2xl">
            <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Value Shift</h2>
                    <p id="shift-meta" class="text-sm text-slate-500 mt-1"></p>
                </div>
                <button type="button" class="js-close-shift text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 grid grid-cols-2 gap-4">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Before</h3>
                    <pre id="shift-old" class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-xs text-slate-800 whitespace-pre-wrap break-words max-h-96 overflow-y-auto"></pre>
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">After</h3>
                    <pre id="shift-new" class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-xs text-slate-800 whitespace-pre-wrap break-words max-h-96 overflow-y-auto"></pre>
                </div>
                <div class="col-span-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Changed Fields</h3>
                    <div id="shift-changes" class="space-y-1 text-sm text-slate-700"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 flex justify-end">
                <button
                    type="button"
                    class="js-close-shift rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <div id="summary-modal" class="hidden fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-8">
        <div class="w-full max-w-2xl bg-white rounded-xl shadow-2xl">
            <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Executive Audit Summary</h2>
                    <p id="summary-meta" class="text-sm text-slate-500 mt-1"></p>
                </div>
                <button type="button" class="js-close-summary text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <p id="summary-text" class="text-sm text-slate-800 leading-relaxed"></p>
                <ul id="summary-highlights" class="list-disc list-inside space-y-1 text-sm text-slate-700"></ul>
                <p class="text-xs text-slate-400">
                    Generated by AI from the filtered log entries. Verify against the underlying records
                    before relying on it.
                </p>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 flex justify-end">
                <button
                    type="button"
                    class="js-close-summary rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const csrfToken = <?= json_encode($csrfToken) ?>;
            const filters = <?= json_encode($filters) ?>;

            // -----------------------------------------------------------------
            // View Shift: side-by-side old vs new
            // -----------------------------------------------------------------
            const shiftModal = document.getElementById('shift-modal');
            const shiftOld = document.getElementById('shift-old');
            const shiftNew = document.getElementById('shift-new');
            const shiftMeta = document.getElementById('shift-meta');
            const shiftChanges = document.getElementById('shift-changes');

            function parseValues(raw) {
                if (!raw) {
                    return null;
                }
                try {
                    return JSON.parse(raw);
                } catch (e) {
                    return { value: raw };
                }
            }

            function pretty(values) {
                return values === null ? '(none)' : JSON.stringify(values, null, 2);
            }

            document.querySelectorAll('.js-view-shift').forEach(function (button) {
                button.addEventListener('click', function () {
                    const shift = JSON.parse(button.dataset.shift);
                    const oldValues = parseValues(shift.old);
                    const newValues = parseValues(shift.new);

                    shiftMeta.textContent = shift.action + ' · ' + shift.module
                        + ' · ' + shift.user + ' · ' + shift.at + ' · log #' + shift.id;
                    shiftOld.textContent = pretty(oldValues);
                    shiftNew.textContent = pretty(newValues);

                    shiftChanges.textContent = '';
                    const keys = new Set([
                        ...Object.keys(oldValues || {}),
                        ...Object.keys(newValues || {}),
                    ]);
                    let changed = 0;

                    keys.forEach(function (key) {
                        const before = oldValues ? oldValues[key] : undefined;
                        const after = newValues ? newValues[key] : undefined;

                        if (JSON.stringify(before) === JSON.stringify(after)) {
                            return;
                        }

                        changed += 1;
                        const row = document.createElement('div');
                        row.className = 'rounded-lg bg-amber-50 border border-amber-200 px-3 py-2';
                        row.textContent = key + ': ' + JSON.stringify(before ?? null)
                            + '  \u2192  ' + JSON.stringify(after ?? null);
                        shiftChanges.appendChild(row);
                    });

                    if (changed === 0) {
                        const row = document.createElement('p');
                        row.className = 'text-slate-500';
                        row.textContent = 'No field-level differences recorded for this entry.';
                        shiftChanges.appendChild(row);
                    }

                    shiftModal.classList.remove('hidden');
                });
            });

            // -----------------------------------------------------------------
            // AI: anomaly scan and executive summary
            // -----------------------------------------------------------------
            const scanButton = document.getElementById('btn-scan');
            const summaryButton = document.getElementById('btn-summary');
            const scanPanel = document.getElementById('scan-panel');
            const scanRisk = document.getElementById('scan-risk');
            const scanSubtitle = document.getElementById('scan-subtitle');
            const scanAssessment = document.getElementById('scan-assessment');
            const scanFindings = document.getElementById('scan-findings');
            const summaryModal = document.getElementById('summary-modal');
            const summaryText = document.getElementById('summary-text');
            const summaryMeta = document.getElementById('summary-meta');
            const summaryHighlights = document.getElementById('summary-highlights');

            const riskClasses = {
                NONE: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                LOW: 'bg-slate-100 text-slate-700 border-slate-300',
                MEDIUM: 'bg-amber-50 text-amber-800 border-amber-300',
                HIGH: 'bg-red-50 text-red-700 border-red-300',
            };

            const severityClasses = {
                LOW: 'bg-slate-50 border-slate-200',
                MEDIUM: 'bg-amber-50 border-amber-200',
                HIGH: 'bg-red-50 border-red-200',
            };

            function callEndpoint(action, button, label) {
                const body = new URLSearchParams();
                body.set('action', action);
                body.set('csrf_token', csrfToken);

                Object.keys(filters).forEach(function (key) {
                    if (filters[key] !== '' && filters[key] !== 0) {
                        body.set(key, filters[key]);
                    }
                });

                button.disabled = true;
                button.textContent = 'Working\u2026';

                return fetch('audit_ai.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                    .then(function (response) { return response.json(); })
                    .catch(function () {
                        return { ok: false, error: 'The request could not be completed. Please try again.' };
                    })
                    .then(function (payload) {
                        button.disabled = false;
                        button.textContent = label;

                        return payload;
                    });
            }

            scanButton.addEventListener('click', function () {
                callEndpoint('anomaly_scan', scanButton, 'Run Anomaly Scan').then(function (payload) {
                    scanPanel.classList.remove('hidden');
                    scanFindings.textContent = '';

                    if (!payload.ok) {
                        scanRisk.className = 'rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide '
                            + riskClasses.MEDIUM;
                        scanRisk.textContent = 'Unavailable';
                        scanSubtitle.textContent = '';
                        scanAssessment.textContent = payload.error || 'The scan could not be completed.';

                        return;
                    }

                    const data = payload.data;
                    scanRisk.className = 'rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide '
                        + (riskClasses[data.risk_level] || riskClasses.LOW);
                    scanRisk.textContent = data.risk_level === 'NONE' ? 'No concerns' : data.risk_level + ' risk';
                    scanSubtitle.textContent = 'Reviewed the most recent ' + data.scanned + ' entries.';
                    scanAssessment.textContent = data.assessment || '';

                    if (!data.findings.length) {
                        const empty = document.createElement('p');
                        empty.className = 'text-sm text-slate-500';
                        empty.textContent = 'Nothing unusual was identified in these entries.';
                        scanFindings.appendChild(empty);

                        return;
                    }

                    data.findings.forEach(function (finding) {
                        const card = document.createElement('div');
                        card.className = 'rounded-lg border px-4 py-3 '
                            + (severityClasses[finding.severity] || severityClasses.LOW);

                        const heading = document.createElement('p');
                        heading.className = 'text-sm font-semibold text-slate-900';
                        heading.textContent = finding.severity + ' · ' + finding.title;
                        card.appendChild(heading);

                        const detail = document.createElement('p');
                        detail.className = 'text-sm text-slate-700 mt-1';
                        detail.textContent = finding.detail;
                        card.appendChild(detail);

                        if (finding.log_ids && finding.log_ids.length) {
                            const refs = document.createElement('p');
                            refs.className = 'text-xs text-slate-500 mt-2';
                            refs.textContent = 'Entries: ';

                            finding.log_ids.forEach(function (id, index) {
                                const link = document.createElement('a');
                                link.href = '#log-' + id;
                                link.className = 'underline hover:text-slate-800';
                                link.textContent = '#' + id;
                                refs.appendChild(link);

                                if (index < finding.log_ids.length - 1) {
                                    refs.appendChild(document.createTextNode(', '));
                                }
                            });

                            card.appendChild(refs);
                        }

                        scanFindings.appendChild(card);
                    });
                });
            });

            summaryButton.addEventListener('click', function () {
                callEndpoint('summary', summaryButton, 'Generate Audit Summary').then(function (payload) {
                    summaryHighlights.textContent = '';

                    if (!payload.ok) {
                        summaryMeta.textContent = '';
                        summaryText.textContent = payload.error || 'The summary could not be generated.';
                        summaryModal.classList.remove('hidden');

                        return;
                    }

                    summaryMeta.textContent = payload.data.counted + ' entries · ' + payload.data.filters;
                    summaryText.textContent = payload.data.summary;

                    payload.data.highlights.forEach(function (highlight) {
                        const item = document.createElement('li');
                        item.textContent = highlight;
                        summaryHighlights.appendChild(item);
                    });

                    summaryModal.classList.remove('hidden');
                });
            });

            // -----------------------------------------------------------------
            // Modal dismissal
            // -----------------------------------------------------------------
            function closeAll() {
                shiftModal.classList.add('hidden');
                summaryModal.classList.add('hidden');
            }

            document.querySelectorAll('.js-close-shift, .js-close-summary').forEach(function (button) {
                button.addEventListener('click', closeAll);
            });

            [shiftModal, summaryModal].forEach(function (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeAll();
                    }
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAll();
                }
            });
        })();
    </script>
</body>
</html>
