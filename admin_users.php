<?php

/**
 * User management console.
 *
 * Administrator-only. Creates authorized accounts and resolves the
 * admin-mediated password reset requests raised from the login page.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/user_roles.php';
require_once __DIR__ . '/includes/user_identities.php';

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
    </head>
    <body class="min-h-screen bg-slate-100 flex items-center justify-center p-8">
        <div class="max-w-md bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center">
            <h1 class="text-xl font-bold text-slate-900">Access Denied</h1>
            <p class="text-slate-600 mt-3 text-sm">
                User Management is restricted to System Administrators.
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

require_once __DIR__ . '/includes/users.php';
function users_escape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$adminId = (int) $_SESSION['UserID'];
$csrfToken = csrf_token();
$errorMessage = '';
$successMessage = $_SESSION['users_success'] ?? '';
unset($_SESSION['users_success']);
$query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$filterRole = is_string($_GET['role'] ?? null) && user_role_is_valid($_GET['role']) ? $_GET['role'] : '';
$status = in_array($_GET['status'] ?? '', ['active', 'disabled', 'all'], true) ? $_GET['status'] : 'all';
$listUrl = 'admin_users.php?' . http_build_query(['q' => $query, 'role' => $filterRole, 'status' => $status]);
$editId = users_id($_GET['edit'] ?? null);
$showForm = isset($_GET['new']) || $editId > 0;
$formFullName = $formEmail = $formRole = '';
$users = [];
$totalUsers = 0;
$unavailable = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = users_text($_POST, 'action');
        $showForm = in_array($action, ['create_user', 'update_user'], true);
        $editId = $action === 'update_user' ? users_id($_POST['user_id'] ?? null) : 0;
        $formFullName = users_text($_POST, 'full_name');
        $formEmail = users_text($_POST, 'email');
        $formRole = users_text($_POST, 'role');
        if (!csrf_verify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new InvalidArgumentException('Your session expired. Please reload the page and try again.');
        }
        users_save($pdo, $adminId, $action, $_POST);
        $_SESSION['users_success'] = ['create_user' => 'User account created.', 'update_user' => 'User account updated.',
            'disable_user' => 'User account disabled. Historical records are preserved.', 'reactivate_user' => 'User account reactivated.'][$action];
        header('Location: ' . $listUrl);
        exit;
    } catch (InvalidArgumentException $e) {
        $errorMessage = $e->getMessage();
    } catch (Throwable $e) {
        error_log('User save failed: ' . $e->getMessage());
        $errorMessage = 'The user account could not be saved. Please try again.';
    }
}
try {
    if ($editId > 0) {
        $editing = users_load($pdo, $editId);
        if (!$editing) { $errorMessage = 'That user could not be found.'; $showForm = false; }
        elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $formFullName = $editing['FullName']; $formEmail = $editing['Email']; $formRole = $editing['Role'];
        }
    }
    $conditions = []; $params = [];
    if ($query !== '') { $conditions[] = '(LOCATE(?, FullName) > 0 OR LOCATE(?, Email) > 0)'; $params[] = $query; $params[] = $query; }
    if ($filterRole !== '') { $conditions[] = 'Role = ?'; $params[] = $filterRole; }
    if ($status !== 'all') { $conditions[] = 'Is_Active = ?'; $params[] = $status === 'active' ? 1 : 0; }
    $stmt = $pdo->prepare('SELECT UserID, FullName, Email, Role, Is_Active, created_at FROM Users'
        . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY FullName, UserID');
    $stmt->execute($params); $users = $stmt->fetchAll();
    $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn();
} catch (PDOException $e) {
    error_log('User lookup failed: ' . $e->getMessage());
    $unavailable = true;
    $errorMessage = 'User accounts are unavailable. Ask your administrator to verify the database migration.';
}
$fieldClass = 'w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none';

$pendingResets = [];
$resetsUnavailable = false;

try {
    $resetStmt = $pdo->query(
        'SELECT pr.ResetID, pr.UserID, pr.Email, pr.RequestedAt, pr.ip_address, u.FullName
         FROM password_resets pr
         JOIN Users u ON u.UserID = pr.UserID
         WHERE pr.Status = \'pending\'
         ORDER BY pr.RequestedAt DESC'
    );
    $pendingResets = $resetStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Pending password reset lookup failed: ' . $e->getMessage());
    $resetsUnavailable = true;
}

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');

// Admin can use the Financial Operational Workspace; keep the links for them.
$canUseWorkspace = true;
$activePage = 'admin_users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 767px) {
            body.users-page > aside { position: static; width: 100%; }
            body.users-page > aside nav { max-height: 12rem; }
            body.users-page > .users-content { margin-left: 0; }
            .users-content > header { padding: 1rem; flex-wrap: wrap; gap: 1rem; }
        }
    </style>
</head>
<body class="users-page min-h-screen bg-slate-100">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="users-content ml-64 min-w-0 flex flex-col min-h-screen">
        <?php include __DIR__ . '/includes/header_bar.php'; ?>

        <main class="flex-1 min-w-0 p-4 md:p-8 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
                <p class="text-slate-600 mt-2">Manage user accounts, roles, and password reset requests.</p>
            </div>

            <?php if (!$unavailable): ?><a href="<?= users_escape($listUrl . '&new=1#user-form') ?>" class="inline-flex rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5 text-sm">Add User</a><?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div role="alert" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                    <p class="text-sm text-red-600 font-medium">
                        <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div role="status" class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                    <p class="text-sm text-emerald-700 font-medium">
                        <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <div id="resolve-banner" class="hidden rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                <p id="resolve-banner-text" class="text-sm text-emerald-700 font-medium"></p>
            </div>

            <?php if ($showForm && !$unavailable): ?>
            <section id="user-form" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4"><?= $editId ? 'Edit User' : 'Add User' ?></h2>
                <form method="POST" action="<?= users_escape($listUrl) ?>" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editId ? 'update_user' : 'create_user' ?>">
                    <input type="hidden" name="user_id" value="<?= $editId ?>">
                    <div><label for="full_name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                        <input id="full_name" name="full_name" required maxlength="255" value="<?= users_escape($formFullName) ?>" class="<?= $fieldClass ?>"></div>
                    <div><label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" id="email" name="email" required maxlength="255" value="<?= users_escape($formEmail) ?>" class="<?= $fieldClass ?>"></div>
                    <div><label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                        <select id="role" name="role" required class="<?= $fieldClass ?>">
                            <option value="">Select a role</option>
                            <?php foreach (USER_ROLE_LABELS as $value => $label): ?><option value="<?= users_escape($value) ?>" <?= $formRole === $value ? 'selected' : '' ?>><?= users_escape($label) ?></option><?php endforeach; ?>
                        </select></div>
                    <div><label for="password" class="block text-sm font-medium text-slate-700 mb-1"><?= $editId ? 'New password (optional)' : 'Initial Password' ?></label>
                        <input type="password" id="password" name="password" autocomplete="new-password" <?= $editId ? '' : 'required' ?> minlength="<?= USER_PASSWORD_MIN_LENGTH ?>" class="<?= $fieldClass ?>" aria-describedby="password-help">
                        <p id="password-help" class="mt-1 text-xs text-slate-500"><?= $editId ? 'Leave blank to keep the current password. ' : '' ?>At least <?= USER_PASSWORD_MIN_LENGTH ?> characters.</p></div>
                    <div class="md:col-span-2 flex items-center gap-4">
                        <button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5">Save User</button>
                        <a href="<?= users_escape($listUrl) ?>" class="text-sm text-slate-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <form method="GET" action="admin_users.php" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label for="search" class="block text-sm font-medium text-slate-700 mb-1">Name or email</label><input id="search" name="q" maxlength="100" value="<?= users_escape($query) ?>" class="<?= $fieldClass ?>"></div>
                    <div><label for="filter-role" class="block text-sm font-medium text-slate-700 mb-1">Role</label><select id="filter-role" name="role" class="<?= $fieldClass ?>"><option value="">All roles</option>
                        <?php foreach (USER_ROLE_LABELS as $value => $label): ?><option value="<?= users_escape($value) ?>" <?= $filterRole === $value ? 'selected' : '' ?>><?= users_escape($label) ?></option><?php endforeach; ?>
                    </select></div>
                    <div><label for="filter-status" class="block text-sm font-medium text-slate-700 mb-1">Status</label><select id="filter-status" name="status" class="<?= $fieldClass ?>">
                        <?php foreach (['all' => 'All users', 'active' => 'Active', 'disabled' => 'Disabled'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="flex items-end gap-4"><button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold px-5 py-2.5">Filter</button><a href="admin_users.php" class="py-2.5 text-sm text-slate-600 hover:underline">Reset</a></div>
                </form>
                <?php if (!$unavailable): ?>
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="User list">
                    <table class="w-full text-sm text-left">
                        <caption class="sr-only">User accounts</caption>
                        <thead class="bg-slate-50 border-y border-slate-200"><tr>
                            <?php foreach (['Full Name', 'Email', 'Role', 'Status', 'Created Date', 'Actions'] as $heading): ?><th scope="col" class="px-6 py-3 font-semibold text-slate-600 whitespace-nowrap"><?= $heading ?></th><?php endforeach; ?>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-200">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-medium text-slate-900 break-words"><?= users_escape($user['FullName']) ?><?php if ((int) $user['UserID'] === $adminId): ?><span class="block text-xs text-slate-500">Your account</span><?php endif; ?></td>
                                <td class="px-6 py-4 text-slate-600 break-words"><?= users_escape($user['Email']) ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= users_escape(user_role_label($user['Role'])) ?></td>
                                <td class="px-6 py-4"><span class="inline-block rounded px-2 py-1 text-xs <?= $user['Is_Active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= $user['Is_Active'] ? 'Active' : 'Disabled' ?></span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-600"><?= users_escape(date('M j, Y', strtotime($user['created_at']))) ?></td>
                                <td class="px-6 py-4"><div class="flex flex-wrap items-center gap-3">
                                    <a class="text-blue-700 hover:underline" href="<?= users_escape($listUrl . '&edit=' . (int) $user['UserID'] . '#user-form') ?>">Edit</a>
                                    <?php if ((int) $user['UserID'] !== $adminId): ?>
                                    <form method="POST" action="<?= users_escape($listUrl) ?>" onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?= $user['Is_Active'] ? 'Disable this user? Historical records will be preserved.' : 'Reactivate this user?' ?>">
                                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['UserID'] ?>"><input type="hidden" name="action" value="<?= $user['Is_Active'] ? 'disable_user' : 'reactivate_user' ?>">
                                        <button class="text-slate-700 hover:underline"><?= $user['Is_Active'] ? 'Disable' : 'Reactivate' ?></button>
                                    </form>
                                    <?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?><tr><td colspan="6" class="px-6 py-10 text-center text-slate-500"><?= $totalUsers === 0 ? 'No user accounts yet.' : 'No users match these filters.' ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">Pending Password Resets</h2>
                    <p class="text-sm text-slate-600 mt-1">
                        Requests raised from the login page. Verify the requester's identity before issuing a temporary password.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Date / Time</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Email</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">IP Address</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="resets-body">
                            <?php if ($resetsUnavailable): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                        Password reset requests are unavailable. Run migrations/004_password_resets.sql.
                                    </td>
                                </tr>
                            <?php elseif (empty($pendingResets)): ?>
                                <tr id="resets-empty">
                                    <td colspan="4" class="px-6 py-8 text-center text-slate-500">No pending password reset requests.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingResets as $reset): ?>
                                    <tr
                                        class="border-b border-slate-100 hover:bg-slate-50 transition"
                                        data-reset-row="<?= (int) $reset['ResetID'] ?>"
                                    >
                                        <td class="px-6 py-3 text-slate-700 whitespace-nowrap">
                                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($reset['RequestedAt'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900">
                                            <?= htmlspecialchars($reset['Email'], ENT_QUOTES, 'UTF-8') ?>
                                            <span class="block text-xs text-slate-500">
                                                <?= htmlspecialchars($reset['FullName'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-slate-700 font-mono text-xs">
                                            <?= htmlspecialchars($reset['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                class="js-resolve-reset rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition"
                                                data-reset-id="<?= (int) $reset['ResetID'] ?>"
                                                data-email="<?= htmlspecialchars($reset['Email'], ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                Resolve
                                            </button>
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

    <div
        id="resolve-modal"
        class="hidden fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-8"
    >
        <div class="w-full max-w-lg bg-white rounded-xl shadow-2xl">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Resolve Password Reset</h2>
                <button type="button" id="resolve-close" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>
            <form id="resolve-form" class="p-6 space-y-4">
                <input type="hidden" id="resolve-reset-id" value="">
                <div>
                    <p class="block text-sm font-medium text-slate-700 mb-1">Account</p>
                    <p id="resolve-email" class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5 text-slate-900"></p>
                </div>
                <div>
                    <label for="resolve-password" class="block text-sm font-medium text-slate-700 mb-1">New Temporary Password</label>
                    <input
                        type="text"
                        id="resolve-password"
                        required
                        minlength="<?= USER_PASSWORD_MIN_LENGTH ?>"
                        autocomplete="off"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                        placeholder="At least <?= USER_PASSWORD_MIN_LENGTH ?> characters"
                    >
                    <p class="text-xs text-slate-500 mt-1">
                        Shown in plain text so you can read it back to the user. It is hashed before storage and the action is recorded in the audit trail.
                    </p>
                </div>
                <p id="resolve-error" class="hidden text-sm text-red-600 font-medium"></p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        id="resolve-cancel"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        id="resolve-submit"
                        class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2 px-5 text-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
            const minLength = <?= (int) USER_PASSWORD_MIN_LENGTH ?>;

            const modal = document.getElementById('resolve-modal');
            const form = document.getElementById('resolve-form');
            const resetIdField = document.getElementById('resolve-reset-id');
            const emailLabel = document.getElementById('resolve-email');
            const passwordField = document.getElementById('resolve-password');
            const errorLine = document.getElementById('resolve-error');
            const submitButton = document.getElementById('resolve-submit');
            const banner = document.getElementById('resolve-banner');
            const bannerText = document.getElementById('resolve-banner-text');
            const tableBody = document.getElementById('resets-body');

            function showError(message) {
                errorLine.textContent = message;
                errorLine.classList.remove('hidden');
            }

            function clearError() {
                errorLine.textContent = '';
                errorLine.classList.add('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
                passwordField.value = '';
                clearError();
            }

            function openModal(resetId, email) {
                resetIdField.value = resetId;
                emailLabel.textContent = email;
                passwordField.value = '';
                clearError();
                modal.classList.remove('hidden');
                passwordField.focus();
            }

            function dropRow(resetId) {
                const row = tableBody.querySelector('[data-reset-row="' + resetId + '"]');
                if (row) {
                    row.remove();
                }

                if (!tableBody.querySelector('[data-reset-row]')) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.id = 'resets-empty';
                    emptyRow.innerHTML =
                        '<td colspan="4" class="px-6 py-8 text-center text-slate-500">No pending password reset requests.</td>';
                    tableBody.appendChild(emptyRow);
                }
            }

            document.querySelectorAll('.js-resolve-reset').forEach(function (button) {
                button.addEventListener('click', function () {
                    openModal(button.dataset.resetId, button.dataset.email);
                });
            });

            document.getElementById('resolve-close').addEventListener('click', closeModal);
            document.getElementById('resolve-cancel').addEventListener('click', closeModal);

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearError();

                const resetId = resetIdField.value;
                const newPassword = passwordField.value;

                if (newPassword.length < minLength) {
                    showError('The temporary password must be at least ' + minLength + ' characters.');
                    return;
                }

                const body = new URLSearchParams();
                body.set('reset_id', resetId);
                body.set('new_password', newPassword);
                body.set('csrf_token', csrfToken);

                submitButton.disabled = true;
                submitButton.textContent = 'Resetting…';

                fetch('resolve_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .catch(function () {
                        return { ok: false, error: 'The request could not be completed. Please try again.' };
                    })
                    .then(function (payload) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Reset Password';

                        if (!payload || !payload.ok) {
                            showError((payload && payload.error) || 'The request could not be completed. Please try again.');
                            return;
                        }

                        const email = emailLabel.textContent;
                        closeModal();
                        dropRow(resetId);

                        bannerText.textContent = 'Password reset for ' + email + '. The action was recorded in the audit trail.';
                        banner.classList.remove('hidden');
                    });
            });
        })();
    </script>
    <script src="assets/js/notifications.js"></script>
</body>
</html>
