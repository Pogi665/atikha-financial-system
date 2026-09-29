<?php
session_start();
require_once __DIR__ . '/includes/require_role.php';
require_login();
require_role(['Admin'], 'Chart of Accounts');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/accounts.php';

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
$form = ['name' => '', 'type' => '', 'detail_type' => '', 'description' => ''];
$query = mb_substr(account_text($_GET, 'q'), 0, 100);
$type = account_text($_GET, 'type');
if (!in_array($type, ['Fund', 'Expense'], true)) { $type = ''; }
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
        header('Location: admin_accounts.php?' . http_build_query(['q' => $query, 'type' => $type, 'status' => $status]));
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
try {
    if ($editId > 0) {
        $editing = account_load($pdo, $editId);
        if ($editing === null) {
            $errorMessage = 'That account could not be found.';
            $showForm = false;
        } else {
            $form['name'] = $editing['Name'];
            $form['type'] = $editing['Type'];
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $form['detail_type'] = $editing['Detail_Type'] ?? '';
                $form['description'] = $editing['Description'] ?? '';
            }
        }
    }
    $conditions = [];
    $params = [];
    if ($query !== '') { $conditions[] = 'LOCATE(?, Name) > 0'; $params[] = $query; }
    if ($type !== '') { $conditions[] = 'Type = ?'; $params[] = $type; }
    if ($status !== 'all') { $conditions[] = 'Is_Active = ?'; $params[] = $status === 'active' ? 1 : 0; }
    $stmt = $pdo->prepare('SELECT CategoryID, Name, Type, Detail_Type, Description, Is_Active FROM Categories'
        . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . " ORDER BY Type = 'Fund' DESC, Name ASC");
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();
    $totalAccounts = (int) $pdo->query('SELECT COUNT(*) FROM Categories')->fetchColumn();
} catch (PDOException $e) {
    error_log('Chart of Accounts lookup failed: ' . $e->getMessage());
    $unavailable = true;
    $errorMessage = 'Accounts are unavailable. Ask your system administrator to verify the database and Chart of Accounts migration.';
}
$filterArgs = ['q' => $query, 'type' => $type, 'status' => $status];
$listUrl = 'admin_accounts.php?' . http_build_query($filterArgs);
$fieldClass = 'w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart of Accounts — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Keep the shared sidebar intact while allowing this page to fit small screens. */
        @media (max-width: 767px) {
            body.accounts-page > aside { position: static; width: 100%; }
            body.accounts-page > aside nav { max-height: 12rem; }
            body.accounts-page > .accounts-content { margin-left: 0; }
            .accounts-content > header { padding: 1rem; flex-wrap: wrap; gap: 1rem; }
        }
    </style>
</head>
<body class="accounts-page min-h-screen bg-slate-100">
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="accounts-content ml-64 min-w-0 flex flex-col min-h-screen">
        <?php include __DIR__ . '/includes/header_bar.php'; ?>
        <main class="flex-1 min-w-0 p-4 md:p-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h1 class="text-2xl font-bold text-slate-900">Chart of Accounts</h1>
                    <p class="text-sm text-slate-600 mt-2">Manage income and expense accounts used in transaction categories.</p></div>
                <?php if (!$unavailable): ?>
                    <a href="<?= accounts_escape($listUrl . '&new=1#account-form') ?>" class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5 text-sm">Add New Account</a>
                <?php endif; ?>
            </div>
            <?php if ($errorMessage !== ''): ?><p role="alert" class="rounded-lg bg-red-50 border border-red-200 p-4 text-red-700"><?= accounts_escape($errorMessage) ?></p><?php endif; ?>
            <?php if ($successMessage !== ''): ?><p role="status" class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-emerald-700"><?= accounts_escape($successMessage) ?></p><?php endif; ?>

            <?php if ($showForm && !$unavailable): ?>
            <section id="account-form" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4"><?= $editId > 0 ? 'Edit Account' : 'Add New Account' ?></h2>
                <form method="POST" action="<?= accounts_escape($listUrl) ?>" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                    <input type="hidden" name="account_id" value="<?= $editId ?>">
                    <div><label for="account-name" class="block text-sm font-medium text-slate-700 mb-1">Account Name</label>
                        <input id="account-name" name="name" required maxlength="100" value="<?= accounts_escape($form['name']) ?>" <?= $editId > 0 ? 'readonly' : '' ?> class="<?= $fieldClass ?>"></div>
                    <div><label for="account-type" class="block text-sm font-medium text-slate-700 mb-1">Account Type</label>
                        <select id="account-type" name="type" required <?= $editId > 0 ? 'disabled' : '' ?> class="<?= $fieldClass ?>">
                            <option value="">Select account type</option>
                            <?php foreach (['Fund' => 'Income', 'Expense' => 'Expense'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $form['type'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <?php if ($editId > 0): ?><p class="md:col-span-2 text-sm text-slate-500">Name and type identify historical transactions and cannot be changed. Disable this account and create a replacement if needed.</p><?php endif; ?>
                    <div><label for="detail-type" class="block text-sm font-medium text-slate-700 mb-1">Detail Type (optional)</label>
                        <input id="detail-type" name="detail_type" maxlength="100" value="<?= accounts_escape($form['detail_type']) ?>" class="<?= $fieldClass ?>"></div>
                    <div><label for="description" class="block text-sm font-medium text-slate-700 mb-1">Description (optional)</label>
                        <textarea id="description" name="description" maxlength="2000" rows="3" class="<?= $fieldClass ?>"><?= accounts_escape($form['description']) ?></textarea></div>
                    <div class="md:col-span-2 flex items-center gap-4">
                        <button type="submit" class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5">Save Account</button>
                        <a href="<?= accounts_escape($listUrl) ?>" class="text-sm text-slate-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <form method="GET" action="admin_accounts.php" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label for="search" class="block text-sm font-medium text-slate-700 mb-1">Account name</label><input id="search" name="q" maxlength="100" value="<?= accounts_escape($query) ?>" class="<?= $fieldClass ?>" placeholder="Search accounts"></div>
                    <div><label for="filter-type" class="block text-sm font-medium text-slate-700 mb-1">Account type</label><select id="filter-type" name="type" class="<?= $fieldClass ?>"><option value="">All types</option>
                        <?php foreach (['Fund' => 'Income', 'Expense' => 'Expense'] as $value => $label): ?><option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></div>
                    <div><label for="filter-status" class="block text-sm font-medium text-slate-700 mb-1">Status</label><select id="filter-status" name="status" class="<?= $fieldClass ?>">
                        <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All accounts'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="flex items-end gap-4"><button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5">Filter</button><a href="admin_accounts.php" class="py-2.5 text-sm text-slate-600 hover:underline">Reset</a></div>
                </form>
                <?php if (!$unavailable): ?>
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Account list">
                    <table class="w-full text-sm text-left">
                        <caption class="sr-only">Income and expense accounts</caption>
                        <thead class="bg-slate-50 border-y border-slate-200"><tr>
                            <?php foreach (['Account Name', 'Account Type', 'Detail Type / Description', 'Actions'] as $heading): ?><th scope="col" class="px-6 py-3 font-semibold text-slate-600 whitespace-nowrap"><?= $heading ?></th><?php endforeach; ?>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-200">
                        <?php $group = ''; foreach ($accounts as $account): $label = $account['Type'] === 'Fund' ? 'Income' : 'Expense'; ?>
                            <?php if ($group !== $account['Type']): $group = $account['Type']; ?><tr class="bg-slate-50"><th colspan="4" scope="rowgroup" class="px-6 py-3 text-slate-800 font-semibold"><?= $label ?></th></tr><?php endif; ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-medium text-slate-900 break-words"><?= accounts_escape($account['Name']) ?><?php if (!$account['Is_Active']): ?> <span class="inline-block rounded bg-slate-100 px-2 py-1 text-xs text-slate-500">Inactive</span><?php endif; ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= $label ?></td>
                                <td class="px-6 py-4 text-slate-600 break-words"><p><?= accounts_escape($account['Detail_Type'] ?? 'Not specified') ?></p><p class="mt-1 whitespace-pre-wrap"><?= accounts_escape($account['Description'] ?? 'Not specified') ?></p></td>
                                <td class="px-6 py-4"><div class="flex flex-wrap items-center gap-3">
                                    <a class="text-blue-700 hover:underline" href="<?= accounts_escape($listUrl . '&edit=' . (int) $account['CategoryID'] . '#account-form') ?>">Edit</a>
                                    <form method="POST" action="<?= accounts_escape($listUrl) ?>" onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?= $account['Is_Active'] ? 'Disable this account for new transactions? Historical records will be preserved.' : 'Reactivate this account for new transactions?' ?>">
                                        <?= csrf_field() ?><input type="hidden" name="account_id" value="<?= (int) $account['CategoryID'] ?>"><input type="hidden" name="action" value="<?= $account['Is_Active'] ? 'disable' : 'enable' ?>">
                                        <button class="text-slate-700 hover:underline whitespace-nowrap"><?= $account['Is_Active'] ? 'Disable' : 'Reactivate' ?></button>
                                    </form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$accounts): ?><tr><td colspan="4" class="px-6 py-10 text-center text-slate-500"><?= $totalAccounts === 0 ? 'No accounts yet. Add your first income or expense account.' : 'No accounts match these filters.' ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="assets/js/notifications.js"></script>
</body>
</html>
