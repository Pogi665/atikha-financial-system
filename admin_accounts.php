<?php
session_start();
require_once __DIR__ . '/includes/require_role.php';
require_login();
require_role(['Admin'], 'Chart of Accounts');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/accounts.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
header('Cache-Control: no-store');

function accounts_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$csrfToken = csrf_token();
$activePage = 'admin_accounts';
$errorMessage = '';
$successMessage = $_SESSION['accounts_success'] ?? '';
unset($_SESSION['accounts_success']);
$unavailable = false;
$editId = account_id($_GET, 'edit');
$showForm = isset($_GET['new']) || $editId > 0;
$form = ['name' => '', 'account_type' => '', 'account_code' => '', 'normal_balance' => 'Debit', 'is_cash_account' => '0', 'detail_type' => '', 'description' => ''];
$accountingLocked = $codeLocked = false;
$query = mb_substr(account_text($_GET, 'q'), 0, 100);
$type = account_text($_GET, 'type');
if (!in_array($type, ACCOUNT_TYPES, true)) { $type = ''; }
$status = account_text($_GET, 'status');
if (!in_array($status, ['active', 'inactive', 'all'], true)) { $status = 'active'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = account_text($_POST, 'action');
    $editId = $action === 'update' ? account_id($_POST) : 0;
    $showForm = in_array($action, ['create', 'update'], true);
    foreach (array_keys($form) as $key) { $form[$key] = account_text($_POST, $key); }
    try {
        if (!csrf_verify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new InvalidArgumentException('Your session expired. Please reload the page and try again.');
        }
        account_save($pdo, (int) $_SESSION['UserID'], $action, $_POST);
        $_SESSION['accounts_success'] = ['create' => 'Account created.', 'update' => 'Account details updated.',
            'disable' => 'Account disabled. Historical records are preserved.', 'enable' => 'Account reactivated.'][$action];
        header('Location: admin_accounts.php?' . http_build_query(['q' => $query, 'type' => $type, 'status' => $status]), true, 303);
        exit;
    } catch (InvalidArgumentException $e) {
        $errorMessage = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Chart of Accounts save failed: ' . $e->getMessage());
        $errorMessage = 'The account could not be saved. Please try again.';
    }
}

$accounts = [];
$totalAccounts = 0;
$groupedAccounts = array_fill_keys(ACCOUNT_TYPES, []);
try {
    if ($editId > 0) {
        $editing = account_load($pdo, $editId);
        if ($editing === null) {
            $errorMessage = 'That account could not be found.';
            $showForm = false;
        } else {
            $form['name'] = $editing['Name'];
            $form['account_type'] = $editing['Account_Type'];
            $accountingLocked = account_has_posted_lines($pdo, $editId);
            $codeLocked = $accountingLocked && $editing['Account_Code'] !== null;
            if ($codeLocked) { $form['account_code'] = $editing['Account_Code']; }
            if ($accountingLocked) {
                $form['normal_balance'] = $editing['Normal_Balance'];
                $form['is_cash_account'] = (string) $editing['Is_Cash_Account'];
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $form['account_code'] = $editing['Account_Code'] ?? '';
                $form['normal_balance'] = $editing['Normal_Balance'];
                $form['is_cash_account'] = (string) $editing['Is_Cash_Account'];
                $form['detail_type'] = $editing['Detail_Type'] ?? '';
                $form['description'] = $editing['Description'] ?? '';
            }
        }
    }
    $conditions = [];
    $params = [];
    if ($query !== '') { $conditions[] = '(LOCATE(?, Name) > 0 OR LOCATE(?, Account_Code) > 0)'; $params[] = $query; $params[] = $query; }
    if ($type !== '') { $conditions[] = 'Account_Type = ?'; $params[] = $type; }
    if ($status !== 'all') { $conditions[] = 'Is_Active = ?'; $params[] = $status === 'active' ? 1 : 0; }
    $stmt = $pdo->prepare('SELECT CategoryID, Name, Account_Type, Account_Code, Normal_Balance, Is_Cash_Account, Detail_Type, Description, Is_Active FROM Categories'
        . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . " ORDER BY FIELD(Account_Type,'Asset','Liability','Equity','Income','Expense'), Name ASC");
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();
    foreach ($accounts as $account) {
        $groupedAccounts[$account['Account_Type']][] = $account;
    }
    $totalAccounts = (int) $pdo->query('SELECT COUNT(*) FROM Categories')->fetchColumn();
} catch (PDOException $e) {
    error_log('Chart of Accounts lookup failed: ' . $e->getMessage());
    http_response_code(503);
    $unavailable = true;
    $errorMessage = 'Accounts are unavailable. Ask your system administrator to verify the database and Chart of Accounts migration.';
}
$filterArgs = ['q' => $query, 'type' => $type, 'status' => $status];
$listUrl = 'admin_accounts.php?' . http_build_query($filterArgs);
$fieldClass = 'w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none';
$filterSignature = sha1(http_build_query($filterArgs));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart of Accounts — Atikha Financial System</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>">
    <link rel="stylesheet" href="assets/css/admin_accounts.css?v=<?= filemtime(__DIR__ . '/assets/css/admin_accounts.css') ?>">
</head>
<body class="accounts-page min-h-screen bg-slate-100" data-filter-signature="<?= accounts_escape($filterSignature) ?>">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="accounts-content ml-64 min-w-0 flex flex-col min-h-screen">
        <?php include __DIR__ . '/includes/header_bar.php'; ?>
        <main class="flex-1 min-w-0 p-4 md:p-8">
            <div class="accounts-page-header">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Administration</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900">Chart of Accounts</h1>
                    <p class="mt-2 text-sm text-slate-600">Manage accounts for double-entry journal posting.</p>
                    <p class="mt-3 text-sm text-slate-500"><span class="font-semibold text-slate-700"><?= $totalAccounts ?></span> total accounts <span class="text-slate-400">&middot;</span> <span class="font-semibold text-slate-700"><?= count($accounts) ?></span> matching current filters</p>
                </div>
                <?php if (!$unavailable): ?>
                    <a id="add-account-button" href="<?= accounts_escape($listUrl . '&new=1') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Add New Account</a>
                <?php endif; ?>
            </div>
            <?php if ($errorMessage !== ''): ?><div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"><?= accounts_escape($errorMessage) ?></div><?php endif; ?>
            <?php if ($successMessage !== ''): ?><div role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"><?= accounts_escape($successMessage) ?></div><?php endif; ?>

            <?php if ($showForm && !$unavailable): ?>
            <div id="account-modal" class="accounts-overlay is-open" data-open-modal="account" aria-hidden="false">
                <div class="accounts-dialog" role="dialog" aria-modal="true" aria-labelledby="account-modal-title" aria-describedby="account-modal-description">
                    <div class="accounts-dialog-header"><div><h2 id="account-modal-title" class="text-lg font-semibold text-slate-900"><?= $editId > 0 ? 'Edit Account' : 'Add New Account' ?></h2><p id="account-modal-description" class="mt-1 text-sm text-slate-500"><?= $editId > 0 ? 'Update account details while preserving its historical identity.' : 'Create an account for future journal entries.' ?></p></div><button type="button" class="accounts-dialog-close" data-close-account-modal aria-label="Close account form">&times;</button></div>
                <form id="account-form" method="POST" action="<?= accounts_escape($listUrl) ?>" class="accounts-form-grid" data-account-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                    <input type="hidden" name="account_id" value="<?= $editId ?>">
                    <div class="accounts-form-summary <?= $errorMessage !== '' ? '' : 'hidden' ?>" data-form-summary role="alert" tabindex="-1"><?= accounts_escape($errorMessage) ?></div>
                    <div><label for="account-name" class="accounts-label">Account Name <?= $editId > 0 ? '<span class="accounts-lock" aria-label="Locked">&#128274;</span>' : '' ?></label>
                        <input id="account-name" name="name" required maxlength="100" value="<?= accounts_escape($form['name']) ?>" <?= $editId > 0 ? 'readonly aria-describedby="account-identity-help"' : '' ?> class="<?= $fieldClass ?>"></div>
                    <div><label for="account-type" class="accounts-label">Account Type <?= $editId > 0 ? '<span class="accounts-lock" aria-label="Locked">&#128274;</span>' : '' ?></label>
                        <select id="account-type" name="account_type" required <?= $editId > 0 ? 'disabled aria-describedby="account-identity-help"' : '' ?> class="<?= $fieldClass ?>">
                            <option value="">Select account type</option>
                            <?php foreach (ACCOUNT_TYPES as $value): ?>
                            <option value="<?= $value ?>" <?= $form['account_type'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <?php if ($editId > 0): ?><p id="account-identity-help" class="md:col-span-2 text-xs text-slate-500">Name and type identify historical transactions and cannot be changed. Disable this account and create a replacement if needed.</p><?php endif; ?>
                    <div><label for="account-code" class="accounts-label">Account Code (optional)</label>
                        <input id="account-code" name="account_code" maxlength="30" value="<?= accounts_escape($form['account_code']) ?>" <?= $codeLocked ? 'readonly' : '' ?> class="<?= $fieldClass ?>"></div>
                    <div><label for="normal-balance" class="accounts-label">Normal Balance</label>
                        <select id="normal-balance" name="normal_balance" required <?= $accountingLocked ? 'disabled' : '' ?> class="<?= $fieldClass ?>">
                            <?php foreach (['Debit', 'Credit'] as $value): ?><option value="<?= $value ?>" <?= $form['normal_balance'] === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?>
                        </select><?php if ($accountingLocked): ?><input type="hidden" name="normal_balance" value="<?= accounts_escape($editing['Normal_Balance']) ?>"><?php endif; ?></div>
                    <div><label for="cash-account" class="accounts-label">Cash Account</label>
                        <select id="cash-account" name="is_cash_account" required <?= $accountingLocked ? 'disabled' : '' ?> class="<?= $fieldClass ?>">
                            <option value="0" <?= $form['is_cash_account'] === '0' ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= $form['is_cash_account'] === '1' ? 'selected' : '' ?>>Yes (Asset accounts only)</option>
                        </select><?php if ($accountingLocked): ?><input type="hidden" name="is_cash_account" value="<?= (int) $editing['Is_Cash_Account'] ?>"><?php endif; ?></div>
                    <p class="md:col-span-2 text-xs text-slate-500">Normal balance and cash status are locked after the first posted entry. An unassigned code may then be assigned once.</p>
                    <div><label for="detail-type" class="accounts-label">Detail Type (optional)</label>
                        <input id="detail-type" name="detail_type" maxlength="100" value="<?= accounts_escape($form['detail_type']) ?>" class="<?= $fieldClass ?>"></div>
                    <div><label for="description" class="accounts-label">Description (optional)</label>
                        <textarea id="description" name="description" maxlength="2000" rows="3" class="<?= $fieldClass ?>"><?= accounts_escape($form['description']) ?></textarea></div>
                    <div class="accounts-dialog-footer"><button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"><?= $editId > 0 ? 'Save Changes' : 'Create Account' ?></button><button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2" data-close-account-modal>Cancel</button></div>
                </form>
                </div>
            </div>
            <?php endif; ?>

            <section class="accounts-directory overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <form method="GET" action="admin_accounts.php" class="accounts-filter-toolbar">
                    <div><label for="search" class="accounts-label">Search</label><input id="search" name="q" maxlength="100" value="<?= accounts_escape($query) ?>" class="<?= $fieldClass ?>" placeholder="Search accounts"></div>
                    <div><label for="filter-type" class="accounts-label">Account Type</label><select id="filter-type" name="type" class="<?= $fieldClass ?>"><option value="">All types</option>
                        <?php foreach (ACCOUNT_TYPES as $value): ?><option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?>
                    </select></div>
                    <div><label for="filter-status" class="accounts-label">Status</label><select id="filter-status" name="status" class="<?= $fieldClass ?>">
                        <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All accounts'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="accounts-filter-actions"><button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Filter</button><a href="admin_accounts.php" data-reset-filters class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Reset</a></div>
                </form>
                <?php if (!$unavailable): ?>
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Account list">
                    <table class="w-full text-sm text-left">
                        <caption class="sr-only">Chart of Accounts</caption>
                        <thead class="bg-slate-50 border-y border-slate-200"><tr>
                            <?php foreach (['Account', 'Description', 'Status', 'Actions'] as $heading): ?><th scope="col" class="px-6 py-3 font-semibold text-slate-600 whitespace-nowrap"><?= $heading ?></th><?php endforeach; ?>
                        </tr></thead>
                        <?php foreach (ACCOUNT_TYPES as $groupType): $label = $groupType; $groupAccounts = $groupedAccounts[$groupType]; $groupId = strtolower($groupType) . '-accounts'; ?>
                            <tbody>
                            <tr class="accounts-group-row"><th colspan="4" scope="rowgroup"><button type="button" class="accounts-group-toggle" data-group-toggle="<?= accounts_escape($groupType) ?>" aria-expanded="true" aria-controls="<?= accounts_escape($groupId) ?>"><span><?= $label ?></span><span class="accounts-group-count"><?= count($groupAccounts) ?></span><span class="accounts-group-chevron" aria-hidden="true">&#9662;</span></button></th></tr>
                            </tbody>
                            <tbody id="<?= accounts_escape($groupId) ?>" data-account-group="<?= accounts_escape($groupType) ?>" class="divide-y divide-slate-200">
                            <?php foreach ($groupAccounts as $account): $detail = trim((string) ($account['Detail_Type'] ?? '')); $description = trim((string) ($account['Description'] ?? '')); ?>
                                <tr class="account-row">
                                    <td class="account-cell"><p class="break-words font-semibold text-slate-900"><?= accounts_escape($account['Name']) ?></p><div class="account-accounting-tags"><span><?= accounts_escape($account['Account_Code'] ?? 'Code unassigned') ?></span><span><?= accounts_escape($account['Normal_Balance']) ?> normal</span><?php if ($account['Is_Cash_Account']): ?><span>Cash</span><?php endif; ?></div><?php if ($detail !== ''): ?><p class="mt-1 break-words text-xs text-slate-500"><?= accounts_escape($detail) ?></p><?php endif; ?></td>
                                    <td class="account-cell break-words whitespace-pre-wrap text-slate-600"><?= $description !== '' ? accounts_escape($description) : '<span class="text-slate-400">&mdash;</span>' ?></td>
                                    <td class="account-cell"><span class="status-pill <?= $account['Is_Active'] ? 'status-active' : 'status-disabled' ?>"><span class="status-dot" aria-hidden="true"></span><?= $account['Is_Active'] ? 'Active' : 'Disabled' ?></span></td>
                                    <td class="account-cell"><div class="flex flex-wrap items-center gap-2"><a class="account-edit-button" href="<?= accounts_escape($listUrl . '&edit=' . (int) $account['CategoryID']) ?>" data-modal-opener="edit">Edit</a><div class="relative"><button type="button" class="account-action-menu-button" aria-haspopup="true" aria-expanded="false" aria-label="More actions for <?= accounts_escape($account['Name']) ?>">More <span aria-hidden="true">&#9662;</span></button><div class="account-action-menu hidden absolute right-0 z-20 mt-2 w-44 rounded-lg border border-slate-200 bg-white p-1 shadow-lg"><form method="POST" action="<?= accounts_escape($listUrl) ?>" data-status-form data-account-name="<?= accounts_escape($account['Name']) ?>" data-status-action="<?= $account['Is_Active'] ? 'disable' : 'enable' ?>"><?= csrf_field() ?><input type="hidden" name="account_id" value="<?= (int) $account['CategoryID'] ?>"><input type="hidden" name="action" value="<?= $account['Is_Active'] ? 'disable' : 'enable' ?>"><button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><?= $account['Is_Active'] ? 'Disable account' : 'Enable account' ?></button></form></div></div></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        <?php endforeach; ?>
                        <?php if (!$accounts): ?><tbody><tr><td colspan="4" class="px-6 py-10 text-center text-slate-500"><p><?= $totalAccounts === 0 ? 'No accounts yet. Add your first account.' : 'No accounts match these filters.' ?></p><?php if ($totalAccounts > 0): ?><a href="admin_accounts.php" class="mt-3 inline-flex text-sm font-semibold text-slate-700 underline">Reset filters</a><?php endif; ?></td></tr></tbody><?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <div id="status-modal" class="accounts-overlay" aria-hidden="true"><div class="accounts-dialog accounts-status-dialog" role="dialog" aria-modal="true" aria-labelledby="status-modal-title" aria-describedby="status-modal-description"><div class="accounts-dialog-header"><h2 id="status-modal-title" class="text-lg font-semibold text-slate-900">Confirm account change</h2><button type="button" class="accounts-dialog-close" data-close-status-modal aria-label="Close confirmation">&times;</button></div><div class="space-y-4 p-6"><p id="status-modal-description" class="text-sm leading-6 text-slate-600"></p><div class="accounts-dialog-footer"><button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2" data-close-status-modal>Cancel</button><button type="button" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2" id="status-confirm">Confirm</button></div></div></div></div>
    <script src="assets/js/admin_accounts.js"></script>
    <script src="assets/js/notifications.js"></script>
</body>
</html>
