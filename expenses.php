<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/require_role.php';

require_login();
require_role(['Staff', 'Admin'], 'The Staff Operational Workspace');

$categories = fetch_category_names_safe($pdo, CATEGORY_TYPE_EXPENSE);
$userId = (int) $_SESSION['UserID'];
$isAdmin = ($_SESSION['Role'] ?? '') === 'Admin';

$csrfToken = csrf_token();

$errorMessage = '';
$successMessage = '';

if (isset($_GET['saved']) || isset($_GET['created'])) {
    $successMessage = 'Expense saved successfully.';
} elseif (isset($_GET['updated'])) {
    $successMessage = 'Expense updated. The change was recorded in the audit trail.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Expense deleted. The record was preserved in the audit trail.';
}

/**
 * Load one expense as its own pre-image for the audit log.
 */
function load_expense(PDO $pdo, int $expenseId): ?array
{
    if ($expenseId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ExpenseID, Payee, Category, Amount, Date_Incurred, RecordedBy_UserID
         FROM Expenses
         WHERE ExpenseID = :expense_id'
    );
    $stmt->execute(['expense_id' => $expenseId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return array{payee: string, category: string, amount: string, date_incurred: string}|null
 */
function read_expense_input(array $post, array $categories): ?array
{
    $payee = isset($post['payee']) ? trim((string) $post['payee']) : '';
    $category = isset($post['category']) ? trim((string) $post['category']) : '';
    $amount = $post['amount'] ?? '';
    $dateIncurred = $post['date_incurred'] ?? '';

    $amountValid = is_numeric($amount) && (float) $amount > 0;
    $dateValid = is_string($dateIncurred) && $dateIncurred !== '' && strtotime($dateIncurred) !== false;
    $categoryValid = in_array($category, $categories, true);

    if ($payee === '' || !$categoryValid || !$amountValid || !$dateValid) {
        return null;
    }

    return [
        'payee'         => $payee,
        'category'      => $category,
        'amount'        => number_format(round((float) $amount, 2), 2, '.', ''),
        'date_incurred' => (string) $dateIncurred,
    ];
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Your session expired. Please try again.';
        $action = '';
    } elseif ($action === '') {
        // Older markup posted without an explicit action.
        $action = 'create';
    }
}

if ($action === 'create') {
    $input = read_expense_input($_POST, $categories);

    if ($input === null) {
        $errorMessage = 'Please fill in all fields with valid values.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO Expenses
                    (Payee, Category, Amount, Date_Incurred, RecordedBy_UserID)
                 VALUES
                    (:payee, :category, :amount, :date_incurred, :recorded_by)'
            );
            $stmt->execute([
                'payee'         => $input['payee'],
                'category'      => $input['category'],
                'amount'        => $input['amount'],
                'date_incurred' => $input['date_incurred'],
                'recorded_by'   => $userId,
            ]);

            log_system_action(
                $pdo,
                $userId,
                AUDIT_ACTION_CREATE,
                'Expenses',
                (int) $pdo->lastInsertId(),
                null,
                $input
            );

            header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
            exit;
        } catch (PDOException $e) {
            error_log('Failed to insert expense: ' . $e->getMessage());
            $errorMessage = 'Unable to save the record. Please try again.';
        }
    }
}

if ($action === 'update') {
    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    $input = read_expense_input($_POST, $categories);

    try {
        $before = load_expense($pdo, $expenseId);

        if ($before === null) {
            $errorMessage = 'That expense could not be found.';
        } elseif ($input === null) {
            $errorMessage = 'Please fill in all fields with valid values.';
        } else {
            $oldValues = [
                'payee'         => $before['Payee'],
                'category'      => $before['Category'],
                'amount'        => $before['Amount'],
                'date_incurred' => $before['Date_Incurred'],
            ];
            $changes = audit_diff($oldValues, $input);

            if ($changes === []) {
                // Nothing moved, so there is nothing to attest to.
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE Expenses
                 SET Payee = :payee,
                     Category = :category,
                     Amount = :amount,
                     Date_Incurred = :date_incurred
                 WHERE ExpenseID = :expense_id'
            );
            $stmt->execute([
                'payee'         => $input['payee'],
                'category'      => $input['category'],
                'amount'        => $input['amount'],
                'date_incurred' => $input['date_incurred'],
                'expense_id'    => $expenseId,
            ]);

            log_system_action(
                $pdo,
                $userId,
                AUDIT_ACTION_EDIT,
                'Expenses',
                $expenseId,
                $oldValues,
                $input
            );

            header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Failed to update expense: ' . $e->getMessage());
        $errorMessage = 'Unable to update the record. Please try again.';
    }
}

if ($action === 'delete') {
    if (!$isAdmin) {
        $errorMessage = 'Only a System Administrator may delete financial records.';
    } else {
        $expenseId = (int) ($_POST['expense_id'] ?? 0);

        try {
            $before = load_expense($pdo, $expenseId);

            if ($before === null) {
                $errorMessage = 'That expense could not be found.';
            } else {
                // Receipts.ExpenseID is ON DELETE SET NULL, so the scanned
                // image survives as evidence even though the expense is gone.
                $stmt = $pdo->prepare('DELETE FROM Expenses WHERE ExpenseID = :expense_id');
                $stmt->execute(['expense_id' => $expenseId]);

                log_system_action(
                    $pdo,
                    $userId,
                    AUDIT_ACTION_DELETE,
                    'Expenses',
                    $expenseId,
                    [
                        'payee'         => $before['Payee'],
                        'category'      => $before['Category'],
                        'amount'        => $before['Amount'],
                        'date_incurred' => $before['Date_Incurred'],
                        'recorded_by'   => (int) $before['RecordedBy_UserID'],
                    ],
                    null
                );

                header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Failed to delete expense: ' . $e->getMessage());
            $errorMessage = 'Unable to delete the record. Please try again.';
        }
    }
}

try {
    $stmt = $pdo->query(
        'SELECT ExpenseID, Date_Incurred, Payee, Category, Amount
         FROM Expenses
         ORDER BY Date_Incurred DESC, ExpenseID DESC'
    );
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch expenses: ' . $e->getMessage());
    $records = [];
    $errorMessage = $errorMessage ?: 'Unable to load records. Please try again later.';
}

// The verification panel reads off the list already in memory.
$recentRecords = array_slice($records, 0, 10);
$recentTotal = array_sum(array_map(static fn (array $row): float => (float) $row['Amount'], $recentRecords));

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');

// Workspace theme tokens, kept in one place so the form and the edit modal
// cannot drift apart.
$fieldClass = 'w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400'
    . ' focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-0 outline-none transition';
$primaryButtonClass = 'inline-flex items-center justify-center rounded-lg bg-emerald-600 hover:bg-emerald-700'
    . ' text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-emerald-500'
    . ' focus:ring-offset-2';
$activePage = 'expenses';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-50">
    <?php include __DIR__ . '/includes/nav.php'; ?>

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
            <div class="border-l-4 border-emerald-600 pl-4">
                <h1 class="text-2xl font-bold text-slate-900">Expenses</h1>
                <p class="text-slate-600 mt-1">
                    Record organizational expenditures, then verify them against the running list.
                </p>
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

            <div class="grid grid-cols-3 gap-6 items-start">
                <section class="col-span-1 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <h2 class="text-base font-semibold text-slate-900">Add New Record</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Every entry is written to the audit trail.</p>
                    </div>
                    <form
                        method="POST"
                        action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                        class="p-6 space-y-4"
                    >
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div>
                            <label for="payee" class="block text-sm font-medium text-slate-700 mb-1">Payee</label>
                            <input
                                type="text"
                                id="payee"
                                name="payee"
                                required
                                maxlength="255"
                                class="<?= $fieldClass ?>"
                                placeholder="Vendor or recipient"
                            >
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                            <select
                                id="category"
                                name="category"
                                required
                                class="<?= $fieldClass ?>"
                            >
                                <option value="" disabled selected>Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500">&#8369;</span>
                                <input
                                    type="number"
                                    id="amount"
                                    name="amount"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="<?= $fieldClass ?> pl-9"
                                    placeholder="0.00"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="date_incurred" class="block text-sm font-medium text-slate-700 mb-1">Date Incurred</label>
                            <input
                                type="date"
                                id="date_incurred"
                                name="date_incurred"
                                required
                                value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= $fieldClass ?>"
                            >
                        </div>

                        <button
                            type="submit"
                            class="<?= $primaryButtonClass ?> w-full"
                        >
                            Save Record
                        </button>
                    </form>
                </section>

                <section class="col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Recently Logged</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                The <?= count($recentRecords) ?> newest entries, for quick verification.
                            </p>
                        </div>
                        <?php if (!empty($recentRecords)): ?>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Shown total</p>
                                <p class="text-lg font-bold text-emerald-700">
                                    &#8369;<?= htmlspecialchars(number_format($recentTotal, 2), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-left">
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Date</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Payee</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRecords)): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-10 text-center text-slate-500">
                                            Nothing logged yet. Use the form to record your first expense.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentRecords as $row): ?>
                                        <tr class="border-b border-slate-100 last:border-0 hover:bg-emerald-50/40 transition">
                                            <td class="px-6 py-3 text-slate-700 whitespace-nowrap">
                                                <?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Incurred'])), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="px-6 py-3 text-slate-900 font-medium">
                                                <?= htmlspecialchars($row['Payee'], ENT_QUOTES, 'UTF-8') ?>
                                                <span class="block text-xs font-normal text-slate-500">
                                                    <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 text-slate-900 font-semibold text-right whitespace-nowrap">
                                                &#8369;<?= htmlspecialchars(number_format((float) $row['Amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900">All Records</h2>
                    <span class="text-xs text-slate-500"><?= count($records) ?> total</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Date</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Payee</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Category</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Amount</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">No expense records yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr class="border-b border-slate-100 last:border-0 hover:bg-emerald-50/40 transition">
                                        <td class="px-6 py-3 text-slate-700 whitespace-nowrap">
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Incurred'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900">
                                            <?= htmlspecialchars($row['Payee'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-700">
                                            <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900 font-semibold text-right whitespace-nowrap">
                                            &#8369;<?= htmlspecialchars(number_format((float) $row['Amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                class="js-edit-expense rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-emerald-50 hover:border-emerald-400 hover:text-emerald-700 transition"
                                                data-record="<?= htmlspecialchars(json_encode([
                                                    'id'            => (int) $row['ExpenseID'],
                                                    'payee'         => $row['Payee'],
                                                    'category'      => $row['Category'],
                                                    'amount'        => $row['Amount'],
                                                    'date_incurred' => $row['Date_Incurred'],
                                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                Edit
                                            </button>
                                            <?php if ($isAdmin): ?>
                                                <form
                                                    method="POST"
                                                    action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="inline"
                                                    onsubmit="return confirm('Delete this expense? The record will be kept in the audit trail.');"
                                                >
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="expense_id" value="<?= (int) $row['ExpenseID'] ?>">
                                                    <button
                                                        type="submit"
                                                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 hover:border-red-300 transition"
                                                    >
                                                        Delete
                                                    </button>
                                                </form>
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

    <div
        id="edit-modal"
        class="hidden fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-8"
    >
        <div class="w-full max-w-lg bg-white rounded-xl shadow-2xl">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Edit Expense</h2>
                <button type="button" id="edit-close" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="expense_id" id="edit-expense-id" value="">
                <div class="col-span-2">
                    <p class="text-xs text-slate-500">
                        Both the previous and the new values are written to the audit trail.
                    </p>
                </div>
                <div>
                    <label for="edit-payee" class="block text-sm font-medium text-slate-700 mb-1">Payee</label>
                    <input
                        type="text"
                        id="edit-payee"
                        name="payee"
                        required
                        class="<?= $fieldClass ?>"
                    >
                </div>
                <div>
                    <label for="edit-category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                    <select
                        id="edit-category"
                        name="category"
                        required
                        class="<?= $fieldClass ?>"
                    >
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit-amount" class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                    <input
                        type="number"
                        id="edit-amount"
                        name="amount"
                        step="0.01"
                        min="0.01"
                        required
                        class="<?= $fieldClass ?>"
                    >
                </div>
                <div>
                    <label for="edit-date" class="block text-sm font-medium text-slate-700 mb-1">Date Incurred</label>
                    <input
                        type="date"
                        id="edit-date"
                        name="date_incurred"
                        required
                        class="<?= $fieldClass ?>"
                    >
                </div>
                <div class="col-span-2 flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        id="edit-cancel"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="<?= $primaryButtonClass ?> py-2 px-5 text-sm"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('edit-modal');

            function closeModal() {
                modal.classList.add('hidden');
            }

            document.querySelectorAll('.js-edit-expense').forEach(function (button) {
                button.addEventListener('click', function () {
                    const record = JSON.parse(button.dataset.record);

                    document.getElementById('edit-expense-id').value = record.id;
                    document.getElementById('edit-payee').value = record.payee;
                    document.getElementById('edit-category').value = record.category;
                    document.getElementById('edit-amount').value = record.amount;
                    document.getElementById('edit-date').value = record.date_incurred;

                    modal.classList.remove('hidden');
                });
            });

            document.getElementById('edit-close').addEventListener('click', closeModal);
            document.getElementById('edit-cancel').addEventListener('click', closeModal);

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
        })();
    </script>
</body>
</html>
