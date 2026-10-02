<?php
require_once __DIR__ . '/transaction_details.php';

/** Separate renderer so reports retain their original table and print contract. */
function ledger_records_table(string $context, string $clearUrl): void
{
    $escape = static fn ($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $date = ['crb' => 'Date received', 'cdb' => 'Date incurred'][$context] ?? 'Date';
    $party = ['crb' => 'Received from', 'cdb' => 'Paid to'][$context] ?? 'Source / payee';
    ?>
    <div class="records-completeness">
        <p id="records-completeness-counts" role="status"></p>
        <details><summary>About data completeness</summary>
            <p>Missing purpose requires attention and displays as Not specified. Unallocated may be legitimate; confirm whether an internal project applies. Each affected transaction is counted once. Counts follow all active filters and search across every page.</p>
        </details>
    </div>
    <p>Running figures are cumulative net recorded cash flow, including transactions hidden by filters. They are not verified cash available or bank balances.</p>
    <div class="records-table-scroll">
        <table id="records-table" class="display" aria-label="Transactions">
            <thead><tr>
                <?php foreach ([$date, 'Transaction type', 'Reference number', $party, 'Amount', 'View', 'Search fields', 'Chronological order'] as $label): ?>
                    <th><?= $escape($label) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <p id="records-page" role="status"></p>
    <p id="records-empty-help" hidden>No records match. <a href="<?= $escape($clearUrl) ?>">Clear filters</a> to see all recorded dates in this view.</p>
    <noscript><p role="alert">Enable JavaScript to search and view this table. No records are hidden by a server page limit.</p></noscript>
    <dialog id="transaction-dialog" aria-labelledby="transaction-dialog-title">
        <div class="records-dialog-heading"><h2 id="transaction-dialog-title">Transaction details</h2><button type="button" id="transaction-close">Close</button></div>
        <dl id="transaction-details"></dl>
        <div id="transaction-documents"></div>
    </dialog>
    <?php
}

function ledger_completeness_notice(array $counts): void
{
    if ($counts['affected'] === 0) { return; }
    ?>
    <div class="ledger-completeness bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg mb-6 text-sm text-amber-700" role="status">
        <strong class="text-amber-800 font-bold">Data completeness: <?= (int) $counts['affected'] ?> records need attention.</strong>
        <?= (int) $counts['missing_purpose'] ?> missing Purpose;
        <?= (int) $counts['unallocated'] ?> Unallocated.
        Each affected transaction is counted once. Legacy transactions with missing Purpose display “Not specified” and need updating.
        Unallocated is a legitimate status and may remain unchanged; confirm whether an internal project applies.
        Counts cover all selected records, including other pages.
    </div>
    <?php
}

function ledger_render_table(array $rows): void
{
    $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="ledger-table-wrap overflow-x-auto">
    <table class="ledger-table w-full text-left border-collapse">
        <colgroup>
            <?php foreach ([8, 10, 10, 12, 9, 11, 14, 12, 14] as $width): ?>
                <col style="width: <?= $width ?>%">
            <?php endforeach; ?>
        </colgroup>
        <thead class="bg-slate-50 border-b border-slate-200"><tr>
            <?php foreach (['Date', 'Transaction Type', 'Ref No.', 'Source/Payee', 'Amount', 'Category', 'Purpose', 'Internal Project', 'Organization Balance After Transaction'] as $label): ?>
                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider"><?= $escape($label) ?></th>
            <?php endforeach; ?>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="px-4 py-6 text-center text-sm text-slate-600 whitespace-nowrap">No records match the selected period or filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr class="hover:bg-slate-50 transition-colors" data-transaction="<?= $escape($row['txn_type'] . '-' . $row['record_id']) ?>">
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape($row['txn_date']) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape($row['txn_type']) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape($row['reference_number'] ?? '') ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape($row['party']) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap text-right <?= $row['txn_type'] === 'Incoming' ? 'text-emerald-600 font-semibold' : 'text-rose-600 font-semibold' ?>"><?= $escape(ledger_money($row['amount'])) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape($row['category']) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape(transaction_detail_label($row['purpose'], 'Not specified')) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $escape(transaction_detail_label($row['project_code'], 'Unallocated')) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap text-right"><?= $escape(ledger_money($row['remaining_balance'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}
