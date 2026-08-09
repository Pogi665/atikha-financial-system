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

$adminId = (int) $_SESSION['UserID'];
$csrfToken = csrf_token();

$errorMessage = '';
$successMessage = '';

// Repopulated after a rejected submission so the admin does not retype
// everything. The password is deliberately never echoed back.
$formFullName = '';
$formEmail = '';
$formRole = '';

if (isset($_GET['created'])) {
    $successMessage = 'User account created successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    $formFullName = trim((string) ($_POST['full_name'] ?? ''));
    $formEmail = trim((string) ($_POST['email'] ?? ''));
    $formRole = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session expired. Please reload the page and try again.';
    } elseif ($formFullName === '') {
        $errorMessage = 'Full name is required.';
    } elseif (filter_var($formEmail, FILTER_VALIDATE_EMAIL) === false) {
        $errorMessage = 'Enter a valid email address.';
    } elseif (!user_role_is_valid($formRole)) {
        $errorMessage = 'Select a valid role.';
    } elseif (strlen($password) < USER_PASSWORD_MIN_LENGTH) {
        $errorMessage = 'The initial password must be at least ' . USER_PASSWORD_MIN_LENGTH . ' characters.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO Users
                    (FullName, Role, Email, Password)
                 VALUES
                    (:full_name, :role, :email, :password)'
            );
            $stmt->execute([
                'full_name' => $formFullName,
                'role'      => $formRole,
                'email'     => $formEmail,
                'password'  => password_hash($password, PASSWORD_DEFAULT),
            ]);

            log_system_action(
                $pdo,
                $adminId,
                AUDIT_ACTION_CREATE,
                'Users',
                (int) $pdo->lastInsertId(),
                null,
                [
                    'full_name' => $formFullName,
                    'email'     => $formEmail,
                    'role'      => $formRole,
                ]
            );

            header('Location: admin_users.php?created=1');
            exit;
        } catch (PDOException $e) {
            // Users.Email is UNIQUE; a collision is an ordinary input mistake,
            // not a server fault.
            if ($e->getCode() === '23000') {
                $errorMessage = 'An account with that email already exists.';
            } else {
                error_log('User creation failed: ' . $e->getMessage());
                $errorMessage = 'The account could not be created. Please try again.';
            }
        }
    }
}

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

// Admin can use the Staff Operational Workspace; keep the links for them.
$canUseWorkspace = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management — Atikha Financial System</title>
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
            <?php if ($canUseWorkspace): ?>
                <a
                    href="funds.php"
                    class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
                >
                    Incoming Funds
                </a>
            <?php endif; ?>
            <a
                href="expenses.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Expenses
            </a>
            <?php if ($canUseWorkspace): ?>
                <a
                    href="ocr_expense.php"
                    class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
                >
                    Scan Receipt
                </a>
            <?php endif; ?>
            <a
                href="reports.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Reports
            </a>
            <a
                href="admin_users.php"
                class="block rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-medium text-white"
            >
                User Management
            </a>
            <a
                href="audit_trail.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
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

        <main class="flex-1 p-8 space-y-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
                <p class="text-slate-600 mt-2">Create authorized accounts and resolve password reset requests.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                    <p class="text-sm text-red-600 font-medium">
                        <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                    <p class="text-sm text-emerald-700 font-medium">
                        <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <div id="resolve-banner" class="hidden rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3">
                <p id="resolve-banner-text" class="text-sm text-emerald-700 font-medium"></p>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Create New User</h2>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="grid grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="create_user">
                    <?= csrf_field() ?>
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            required
                            value="<?= htmlspecialchars($formFullName, ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                            placeholder="Juan dela Cruz"
                        >
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            value="<?= htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                            placeholder="name@atikha.org"
                        >
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                        <select
                            id="role"
                            name="role"
                            required
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                        >
                            <option value="" disabled <?= $formRole === '' ? 'selected' : '' ?>>Select a role</option>
                            <?php foreach (USER_ROLE_LABELS as $roleValue => $roleLabel): ?>
                                <option
                                    value="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $formRole === $roleValue ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Initial Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            minlength="<?= USER_PASSWORD_MIN_LENGTH ?>"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                            placeholder="At least <?= USER_PASSWORD_MIN_LENGTH ?> characters"
                        >
                    </div>
                    <div class="col-span-2 flex items-center gap-4">
                        <button
                            type="submit"
                            class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-slate-600 focus:ring-offset-2"
                        >
                            Create User
                        </button>
                        <p class="text-xs text-slate-500">
                            The password is hashed before storage and is never shown again. Share it with the user directly.
                        </p>
                    </div>
                </form>
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
</body>
</html>
