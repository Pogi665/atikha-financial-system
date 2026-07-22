<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';

$categories = ['Donation', 'Grant', 'Fundraiser', 'Sponsorship', 'Other'];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceDonor = isset($_POST['source_donor']) ? trim($_POST['source_donor']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $amount = $_POST['amount'] ?? '';
    $dateReceived = $_POST['date_received'] ?? '';

    $amountValid = is_numeric($amount) && (float) $amount > 0;
    $dateValid = $dateReceived !== '' && strtotime($dateReceived) !== false;
    $categoryValid = in_array($category, $categories, true);

    if ($sourceDonor === '' || !$categoryValid || !$amountValid || !$dateValid) {
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
                'source_donor'   => $sourceDonor,
                'category'       => $category,
                'amount'         => round((float) $amount, 2),
                'date_received'  => $dateReceived,
                'recorded_by'    => (int) $_SESSION['UserID'],
            ]);

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            error_log('Failed to insert incoming fund: ' . $e->getMessage());
            $errorMessage = 'Unable to save the record. Please try again.';
        }
    }
}

try {
    $stmt = $pdo->query(
        'SELECT Date_Received, Source_Donor, Category, Amount
         FROM Incoming_Funds
         ORDER BY Date_Received DESC'
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
                href="reports.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Reports
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

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Add New Record</h2>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="grid grid-cols-2 gap-4">
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-slate-500">No incoming fund records yet.</td>
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
