<?php
require_once __DIR__ . '/transaction_details.php';

function ledger_completeness_notice(array $counts): void
{
    if ($counts['affected'] === 0) { return; }
    ?>
    <div class="ledger-completeness border border-amber-300 bg-amber-50 p-4 my-4 text-sm" role="status">
        <strong>Data completeness: <?= (int) $counts['affected'] ?> records need attention.</strong>
        <?= (int) $counts['missing_purpose'] ?> missing Purpose;
        <?= (int) $counts['unallocated'] ?> Unallocated.
        Each affected transaction is counted once. Legacy transactions with missing Purpose display “Not specified” and need updating.
        Unallocated is a legitimate status and may remain unchanged; confirm whether an allocation applies.
        Counts cover all selected records, including other pages.
    </div>
    <?php
}

function ledger_render_table(array $rows): void
{
    $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="ledger-table-wrap overflow-x-auto">
    <table class="ledger-table w-full text-sm text-left">
        <colgroup>
            <?php foreach ([9, 10, 13, 10, 11, 18, 14, 15] as $width): ?>
                <col style="width: <?= $width ?>%">
            <?php endforeach; ?>
        </colgroup>
        <thead class="bg-slate-50"><tr>
            <?php foreach (['Date', 'Transaction Type', 'Source/Payee', 'Amount', 'Category', 'Purpose', 'Allocation/Project Code', 'Organization Balance After Transaction'] as $label): ?>
                <th class="px-3 py-3 font-semibold"><?= $label === 'Allocation/Project Code' ? 'Allocation/<wbr>Project Code' : $escape($label) ?></th>
            <?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">No records match the selected period or filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr class="border-b border-slate-200" data-transaction="<?= $escape($row['txn_type'] . '-' . $row['record_id']) ?>">
                <td class="px-3 py-3"><?= $escape($row['txn_date']) ?></td>
                <td class="px-3 py-3"><?= $escape($row['txn_type']) ?></td>
                <td class="px-3 py-3"><?= $escape($row['party']) ?></td>
                <td class="px-3 py-3 text-right"><?= $escape(ledger_money($row['amount'])) ?></td>
                <td class="px-3 py-3"><?= $escape($row['category']) ?></td>
                <td class="px-3 py-3"><?= $escape(transaction_detail_label($row['purpose'], 'Not specified')) ?></td>
                <td class="px-3 py-3"><?= $escape(transaction_detail_label($row['project_code'], 'Unallocated')) ?></td>
                <td class="px-3 py-3 text-right"><?= $escape(ledger_money($row['remaining_balance'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}
