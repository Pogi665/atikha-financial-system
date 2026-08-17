<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/review_ui.php';
require_once __DIR__ . '/includes/require_role.php';

require_login();
require_role(['Staff', 'Admin'], 'Incoming Funds');

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
        'SELECT FundID, Source_Donor, Category, Project_Code, Amount, Date_Received, RecordedBy_UserID
         FROM Incoming_Funds
         WHERE FundID = :fund_id'
    );
    $stmt->execute(['fund_id' => $fundId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return array{source_donor: string, category: string, project_code: ?string,
 *               amount: string, date_received: string}|null
 */
function read_fund_input(array $post, array $categories): ?array
{
    $sourceDonor = isset($post['source_donor']) ? trim((string) $post['source_donor']) : '';
    $category = isset($post['category']) ? trim((string) $post['category']) : '';
    $projectCode = isset($post['project_code']) ? trim((string) $post['project_code']) : '';
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
        // Optional. Stored as NULL rather than '' so "no project" reads the
        // same on the rows that predate this column.
        'project_code'  => $projectCode === '' ? null : mb_substr($projectCode, 0, 50),
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
                    (Source_Donor, Category, Project_Code, Amount, Date_Received, RecordedBy_UserID)
                 VALUES
                    (:source_donor, :category, :project_code, :amount, :date_received, :recorded_by)'
            );
            $stmt->execute([
                'source_donor'  => $input['source_donor'],
                'category'      => $input['category'],
                'project_code'  => $input['project_code'],
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
                'project_code'  => $before['Project_Code'],
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
                     Project_Code = :project_code,
                     Amount = :amount,
                     Date_Received = :date_received
                 WHERE FundID = :fund_id'
            );
            $stmt->execute([
                'source_donor'  => $input['source_donor'],
                'category'      => $input['category'],
                'project_code'  => $input['project_code'],
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
                        'project_code'  => $before['Project_Code'],
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
        'SELECT FundID, Date_Received, Source_Donor, Category, Project_Code, Amount, Review_Status, Review_Notes
         FROM Incoming_Funds
         ORDER BY Date_Received DESC, FundID DESC'
    );
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch incoming funds: ' . $e->getMessage());
    $records = [];
    $errorMessage = $errorMessage ?: 'Unable to load records. Please try again later.';
}

// The verification panel reads off the list already in memory.
$recentRecords = array_slice($records, 0, 10);
$recentTotal = array_sum(array_map(static fn (array $row): float => (float) $row['Amount'], $recentRecords));

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');
$reviewStatus = static fn (array $row): string => (string) ($row['Review_Status'] ?? 'None');

// Workspace theme tokens, kept in one place so the form and the edit modal
// cannot drift apart.
$fieldClass = 'w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400'
    . ' focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-0 outline-none transition';
$primaryButtonClass = 'inline-flex items-center justify-center rounded-lg bg-emerald-600 hover:bg-emerald-700'
    . ' text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-emerald-500'
    . ' focus:ring-offset-2';
$activePage = 'funds';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Funds — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-50">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="ml-64 flex flex-col min-h-screen js-review-root" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <?php include __DIR__ . '/includes/header_bar.php'; ?>

        <main class="flex-1 p-8 space-y-6">
            <div id="review-flash" class="hidden"></div>
            <div class="border-l-4 border-emerald-600 pl-4">
                <h1 class="text-2xl font-bold text-slate-900">Incoming Funds</h1>
                <p class="text-slate-600 mt-1">
                    Log incoming donations and grants, then verify them against the running list.
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
                        <h2 class="text-base font-semibold text-slate-900">Log Incoming Funds</h2>
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
                            <label for="date_received" class="block text-sm font-medium text-slate-700 mb-1">Date Received</label>
                            <input
                                type="date"
                                id="date_received"
                                name="date_received"
                                required
                                value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= $fieldClass ?>"
                            >
                        </div>

                        <div>
                            <label for="source_donor" class="block text-sm font-medium text-slate-700 mb-1">Source / Donor</label>
                            <input
                                type="text"
                                id="source_donor"
                                name="source_donor"
                                required
                                maxlength="255"
                                class="<?= $fieldClass ?>"
                                placeholder="Donor or funding source"
                            >
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
                            <label for="project_code" class="block text-sm font-medium text-slate-700 mb-1">
                                Project Code
                                <span class="text-slate-400 font-normal">(optional)</span>
                            </label>
                            <input
                                type="text"
                                id="project_code"
                                name="project_code"
                                maxlength="50"
                                class="<?= $fieldClass ?>"
                                placeholder="e.g. ATK-2026-01"
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
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Source / Donor</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Project</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRecords)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-10 text-center text-slate-500">
                                            Nothing logged yet. Use the form to record your first incoming fund.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentRecords as $row): ?>
                                        <tr class="border-b border-slate-100 last:border-0 hover:bg-emerald-50/40 transition">
                                            <td class="px-6 py-3 text-slate-700 whitespace-nowrap">
                                                <?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Received'])), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="px-6 py-3 text-slate-900 font-medium">
                                                <?= htmlspecialchars($row['Source_Donor'], ENT_QUOTES, 'UTF-8') ?>
                                                <span class="block text-xs font-normal text-slate-500">
                                                    <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-3">
                                                <?php if (($row['Project_Code'] ?? '') !== ''): ?>
                                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                                        <?= htmlspecialchars((string) $row['Project_Code'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">&mdash;</span>
                                                <?php endif; ?>
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
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Source / Donor</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Category</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Project</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Amount</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Review</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">No incoming fund records yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr class="border-b border-slate-100 last:border-0 hover:bg-emerald-50/40 transition">
                                        <td class="px-6 py-3 text-slate-700 whitespace-nowrap">
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['Date_Received'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900">
                                            <?= htmlspecialchars($row['Source_Donor'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-700">
                                            <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if (($row['Project_Code'] ?? '') !== ''): ?>
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                                    <?= htmlspecialchars((string) $row['Project_Code'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-slate-900 font-semibold text-right whitespace-nowrap">
                                            &#8369;<?= htmlspecialchars(number_format((float) $row['Amount'], 2), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <?= review_render_status_badge($reviewStatus($row)) ?>
                                        </td>
                                        <td class="px-6 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                class="js-edit-fund rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-emerald-50 hover:border-emerald-400 hover:text-emerald-700 transition"
                                                data-record="<?= htmlspecialchars(json_encode([
                                                    'id'            => (int) $row['FundID'],
                                                    'source_donor'  => $row['Source_Donor'],
                                                    'category'      => $row['Category'],
                                                    'project_code'  => $row['Project_Code'],
                                                    'amount'        => $row['Amount'],
                                                    'date_received' => $row['Date_Received'],
                                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                Edit
                                            </button>
                                            <?php if (review_can_send($reviewStatus($row))): ?>
                                                <button
                                                    type="button"
                                                    class="js-send-review rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100 transition"
                                                    data-entity-type="fund"
                                                    data-entity-id="<?= (int) $row['FundID'] ?>"
                                                >
                                                    Send for Review
                                                </button>
                                            <?php endif; ?>
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
                    <label for="edit-date" class="block text-sm font-medium text-slate-700 mb-1">Date Received</label>
                    <input
                        type="date"
                        id="edit-date"
                        name="date_received"
                        required
                        class="<?= $fieldClass ?>"
                    >
                </div>
                <div>
                    <label for="edit-source" class="block text-sm font-medium text-slate-700 mb-1">Source / Donor</label>
                    <input
                        type="text"
                        id="edit-source"
                        name="source_donor"
                        required
                        maxlength="255"
                        class="<?= $fieldClass ?>"
                    >
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
                <div class="col-span-2">
                    <label for="edit-project" class="block text-sm font-medium text-slate-700 mb-1">
                        Project Code
                        <span class="text-slate-400 font-normal">(optional)</span>
                    </label>
                    <input
                        type="text"
                        id="edit-project"
                        name="project_code"
                        maxlength="50"
                        class="<?= $fieldClass ?>"
                        placeholder="e.g. ATK-2026-01"
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

            document.querySelectorAll('.js-edit-fund').forEach(function (button) {
                button.addEventListener('click', function () {
                    const record = JSON.parse(button.dataset.record);

                    document.getElementById('edit-fund-id').value = record.id;
                    document.getElementById('edit-source').value = record.source_donor;
                    document.getElementById('edit-category').value = record.category;
                    document.getElementById('edit-project').value = record.project_code || '';
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
    <script src="assets/js/notifications.js"></script>
    <script src="assets/js/review_actions.js"></script>
</body>
</html>
