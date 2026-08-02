<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';

$categories = fetch_category_names_safe($pdo, CATEGORY_TYPE_FUND);
$userId = (int) $_SESSION['UserID'];
$isAdmin = ($_SESSION['Role'] ?? '') === 'Admin';
$csrfToken = csrf_token();

$errorMessage = '';
$successMessage = '';

if (isset($_GET['created'])) {
    $successMessage = 'Incoming fund saved successfully.';
} elseif (isset($_GET['updated'])) {
    $successMessage = 'Incoming fund updated. The change was recorded in the audit trail.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Incoming fund deleted. The record was preserved in the audit trail.';
}

/**
 * Load one fund as its own pre-image for the audit log.
 */
function load_fund(PDO $pdo, int $fundId): ?array
{
    if ($fundId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT FundID, Source_Donor, Category, Amount, Date_Received, RecordedBy_UserID
         FROM Incoming_Funds
         WHERE FundID = :fund_id'
    );
    $stmt->execute(['fund_id' => $fundId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return array{source_donor: string, category: string, amount: string, date_received: string}|null
 */
function read_fund_input(array $post, array $categories): ?array
{
    $sourceDonor = isset($post['source_donor']) ? trim((string) $post['source_donor']) : '';
    $category = isset($post['category']) ? trim((string) $post['category']) : '';
    $amount = $post['amount'] ?? '';
    $dateReceived = $post['date_received'] ?? '';

    $amountValid = is_numeric($amount) && (float) $amount > 0;
    $dateValid = is_string($dateReceived) && $dateReceived !== '' && strtotime($dateReceived) !== false;
    $categoryValid = in_array($category, $categories, true);

    if ($sourceDonor === '' || !$categoryValid || !$amountValid || !$dateValid) {
        return null;
    }

    return [
        'source_donor'  => $sourceDonor,
        'category'      => $category,
        'amount'        => number_format(round((float) $amount, 2), 2, '.', ''),
        'date_received' => (string) $dateReceived,
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
    $input = read_fund_input($_POST, $categories);

    if ($input === null) {
        $errorMessage = 'Please fill in all fields with valid values.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO Incoming_Funds
                    (Source_Donor, Category, Amount, Date_Received, RecordedBy_UserID)
                 VALUES
                    (:source_donor, :category, :amount, :date_received, :recorded_by)'
            );
            $stmt->execute([
                'source_donor'  => $input['source_donor'],
                'category'      => $input['category'],
                'amount'        => $input['amount'],
                'date_received' => $input['date_received'],
                'recorded_by'   => $userId,
            ]);

            log_system_action(
                $pdo,
                $userId,
                AUDIT_ACTION_CREATE,
                'Funds',
                (int) $pdo->lastInsertId(),
                null,
                $input
            );

            header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
            exit;
        } catch (PDOException $e) {
            error_log('Failed to insert incoming fund: ' . $e->getMessage());
            $errorMessage = 'Unable to save the record. Please try again.';
        }
    }
}

if ($action === 'update') {
    $fundId = (int) ($_POST['fund_id'] ?? 0);
    $input = read_fund_input($_POST, $categories);

    try {
        $before = load_fund($pdo, $fundId);

        if ($before === null) {
            $errorMessage = 'That incoming fund could not be found.';
        } elseif ($input === null) {
            $errorMessage = 'Please fill in all fields with valid values.';
        } else {
            $oldValues = [
                'source_donor'  => $before['Source_Donor'],
                'category'      => $before['Category'],
                'amount'        => $before['Amount'],
                'date_received' => $before['Date_Received'],
            ];
            $changes = audit_diff($oldValues, $input);

            if ($changes === []) {
                // Nothing moved, so there is nothing to attest to.
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE Incoming_Funds
                 SET Source_Donor = :source_donor,
                     Category = :category,
                     Amount = :amount,
                     Date_Received = :date_received
                 WHERE FundID = :fund_id'
            );
            $stmt->execute([
                'source_donor'  => $input['source_donor'],
                'category'      => $input['category'],
                'amount'        => $input['amount'],
                'date_received' => $input['date_received'],
                'fund_id'       => $fundId,
            ]);

            log_system_action(
                $pdo,
                $userId,
                AUDIT_ACTION_EDIT,
                'Funds',
                $fundId,
                $oldValues,
                $input
            );

            header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Failed to update incoming fund: ' . $e->getMessage());
        $errorMessage = 'Unable to update the record. Please try again.';
    }
}

if ($action === 'delete') {
    if (!$isAdmin) {
        $errorMessage = 'Only a System Administrator may delete financial records.';
    } else {
        $fundId = (int) ($_POST['fund_id'] ?? 0);

        try {
            $before = load_fund($pdo, $fundId);

            if ($before === null) {
                $errorMessage = 'That incoming fund could not be found.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM Incoming_Funds WHERE FundID = :fund_id');
                $stmt->execute(['fund_id' => $fundId]);

                log_system_action(
                    $pdo,
                    $userId,
                    AUDIT_ACTION_DELETE,
                    'Funds',
                    $fundId,
                    [
                        'source_donor'  => $before['Source_Donor'],
                        'category'      => $before['Category'],
                        'amount'        => $before['Amount'],
                        'date_received' => $before['Date_Received'],
                        'recorded_by'   => (int) $before['RecordedBy_UserID'],
                    ],
                    null
                );

                header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Failed to delete incoming fund: ' . $e->getMessage());
            $errorMessage = 'Unable to delete the record. Please try again.';
        }
    }
}

try {
    $stmt = $pdo->query(
        'SELECT FundID, Date_Received, Source_Donor, Category, Amount
         FROM Incoming_Funds
         ORDER BY Date_Received DESC, FundID DESC'
    );
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch incoming funds: ' . $e->getMessage());
    $records = [];
    $errorMessage = $errorMessage ?: 'Unable to load records. Please try again later.';
}

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Funds — Atikha Financial System</title>
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
                class="block rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-medium text-white"
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
            <?php if ($isAdmin): ?>
                <a
                    href="audit_trail.php"
                    class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
                >
                    Audit Trail
                </a>
            <?php endif; ?>
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
                <h1 class="text-2xl font-bold text-slate-900">Incoming Funds</h1>
                <p class="text-slate-600 mt-2">Record and view incoming donations and grants.</p>
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

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Add New Record</h2>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="grid grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div>
                        <label for="source_donor" class="block text-sm font-medium text-slate-700 mb-1">Source / Donor</label>
                        <input
                            type="text"
                            id="source_donor"
                            name="source_donor"
                            required
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                            placeholder="Donor or funding source"
                        >
                    </div>
                    <div>
                        <label for="category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select
                            id="category"
                            name="category"
                            required
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
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
                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            required
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                            placeholder="0.00"
                        >
                    </div>
                    <div>
                        <label for="date_received" class="block text-sm font-medium text-slate-700 mb-1">Date Received</label>
                        <input
                            type="date"
                            id="date_received"
                            name="date_received"
                            required
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition"
                        >
                    </div>
                    <div class="col-span-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-slate-600 focus:ring-offset-2"
                        >
                            Save Record
                        </button>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">All Records</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Date</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Source / Donor</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Category</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Amount</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">No incoming fund records yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                        <td class="px-6 py-3 text-slate-700">
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Received'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900">
                                            <?= htmlspecialchars($row['Source_Donor'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-700">
                                            <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900 font-medium text-right">
                                            ₱<?= htmlspecialchars(number_format((float) $row['Amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                class="js-edit-fund rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition"
                                                data-record="<?= htmlspecialchars(json_encode([
                                                    'id'            => (int) $row['FundID'],
                                                    'source_donor'  => $row['Source_Donor'],
                                                    'category'      => $row['Category'],
                                                    'amount'        => $row['Amount'],
                                                    'date_received' => $row['Date_Received'],
                                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                Edit
                                            </button>
                                            <?php if ($isAdmin): ?>
                                                <form
                                                    method="POST"
                                                    action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="inline"
                                                    onsubmit="return confirm('Delete this incoming fund? The record will be kept in the audit trail.');"
                                                >
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="fund_id" value="<?= (int) $row['FundID'] ?>">
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
                <h2 class="text-lg font-semibold text-slate-900">Edit Incoming Fund</h2>
                <button type="button" id="edit-close" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="fund_id" id="edit-fund-id" value="">
                <div class="col-span-2">
                    <p class="text-xs text-slate-500">
                        Both the previous and the new values are written to the audit trail.
                    </p>
                </div>
                <div>
                    <label for="edit-source" class="block text-sm font-medium text-slate-700 mb-1">Source / Donor</label>
                    <input
                        type="text"
                        id="edit-source"
                        name="source_donor"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                    >
                </div>
                <div>
                    <label for="edit-category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                    <select
                        id="edit-category"
                        name="category"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
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
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
                    >
                </div>
                <div>
                    <label for="edit-date" class="block text-sm font-medium text-slate-700 mb-1">Date Received</label>
                    <input
                        type="date"
                        id="edit-date"
                        name="date_received"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 outline-none transition"
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
                        class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2 px-5 text-sm transition"
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

            document.querySelectorAll('.js-edit-fund').forEach(function (button) {
                button.addEventListener('click', function () {
                    const record = JSON.parse(button.dataset.record);

                    document.getElementById('edit-fund-id').value = record.id;
                    document.getElementById('edit-source').value = record.source_donor;
                    document.getElementById('edit-category').value = record.category;
                    document.getElementById('edit-amount').value = record.amount;
                    document.getElementById('edit-date').value = record.date_received;

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
