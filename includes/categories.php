<?php

/**
 * Category lookup backed by the Categories table.
 *
 * Expenses.Category and Incoming_Funds.Category are still VARCHAR columns, so
 * pages validate a submitted category against these names rather than an ID.
 */

const CATEGORY_TYPE_EXPENSE = 'Expense';
const CATEGORY_TYPE_FUND = 'Fund';

/**
 * Active category names for the given type, alphabetically, with the catch-all
 * ("Miscellaneous" / "Other") pushed last so it never looks like a default.
 *
 * @return string[]
 */
function fetch_category_names(PDO $pdo, string $type = CATEGORY_TYPE_EXPENSE): array
{
    if (!in_array($type, [CATEGORY_TYPE_EXPENSE, CATEGORY_TYPE_FUND], true)) {
        $type = CATEGORY_TYPE_EXPENSE;
    }

    $catchAll = $type === CATEGORY_TYPE_EXPENSE ? 'Miscellaneous' : 'Other';

    $stmt = $pdo->prepare(
        'SELECT Name
         FROM Categories
         WHERE Type = :type AND Is_Active = 1
         ORDER BY Name = :catch_all ASC, Name ASC'
    );
    $stmt->execute([
        'type'      => $type,
        'catch_all' => $catchAll,
    ]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Same as fetch_category_names() but degrades to a hardcoded list if the
 * Categories table is missing, so an un-migrated database still renders a
 * usable page instead of a fatal error.
 *
 * @return string[]
 */
function fetch_category_names_safe(PDO $pdo, string $type = CATEGORY_TYPE_EXPENSE): array
{
    try {
        $names = fetch_category_names($pdo, $type);
        if (!empty($names)) {
            return $names;
        }
    } catch (PDOException $e) {
        error_log('Failed to fetch categories: ' . $e->getMessage());
    }

    return $type === CATEGORY_TYPE_FUND
        ? ['Donation', 'Fundraiser', 'Grant', 'Sponsorship', 'Other']
        : [
            'Equipment',
            'Event Costs',
            'Meals',
            'Office Supplies',
            'Payroll',
            'Professional Fees',
            'Transportation',
            'Travel',
            'Utilities',
            'Miscellaneous',
        ];
}
