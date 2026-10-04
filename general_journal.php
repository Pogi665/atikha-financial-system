<?php
session_start();
require_once __DIR__ . '/includes/require_role.php';
require_login();
require_role(['Admin'], 'General Journal');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/journal.php';
require_once __DIR__ . '/includes/layout.php';

function journal_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function journal_form_text(array $data, string $key, int $limit): string
{
    return is_string($data[$key] ?? null) ? mb_substr($data[$key], 0, $limit) : '';
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
header('Cache-Control: no-store');
$error = ''; $unavailable = false; $accounts = [];
$success = is_string($_SESSION['journal_success'] ?? null) ? $_SESSION['journal_success'] : '';
unset($_SESSION['journal_success']);
$form = ['entry_date' => journal_today(), 'reference' => '', 'description' => ''];
$blank = ['account_id' => '', 'fund_project_id' => '', 'debit_amount' => '', 'credit_amount' => ''];
$lines = [$blank, $blank];
$key = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['entry_date' => 32, 'reference' => 100, 'description' => 2000] as $field => $limit) {
        $form[$field] = journal_form_text($_POST, $field, $limit);
    }
    if (is_array($_POST['lines'] ?? null)) {
        $lines = [];
        foreach (array_slice($_POST['lines'], 0, JOURNAL_MAX_LINES) as $raw) {
            $line = $blank;
            if (is_array($raw)) {
                foreach (array_keys($blank) as $field) { $line[$field] = journal_form_text($raw, $field, 32); }
            }
            $lines[] = $line;
        }
        while (count($lines) < 2) { $lines[] = $blank; }
    }
    $candidate = is_string($_POST['submission_key'] ?? null) ? $_POST['submission_key'] : '';
    if (journal_submission_valid($candidate)) { $key = $candidate; }
    try {
        $result = journal_post($pdo, (int) $_SESSION['UserID'], $_POST);
        $_SESSION['journal_success'] = 'Journal entry #' . $result['id']
            . ($result['duplicate'] ? ' was already posted. No duplicate was created.' : ' posted successfully.');
        header('Location: general_journal.php', true, 303); exit;
    } catch (JournalProblem $e) {
        http_response_code($e->status); $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Journal posting failed: ' . $e->getMessage());
        http_response_code(500); $error = 'The entry could not be posted. Your entries are retained; please try again.';
    }
}
try {
    journal_require_schema($pdo);
    $accounts = journal_accounts($pdo);
} catch (Throwable $e) {
    error_log('Journal setup unavailable: ' . $e->getMessage());
    http_response_code(503); $unavailable = true;
    $error = 'Journal posting is temporarily unavailable. Contact your system administrator.';
}
if ($key === '') { $key = journal_submission_key(); }
$groups = array_fill_keys(JOURNAL_ACCOUNT_TYPES, []);
$knownIds = [];
foreach ($accounts as $account) {
    $groups[$account['Account_Type']][] = $account;
    $knownIds[(string) $account['CategoryID']] = true;
}
function journal_options(array $groups, string $selected = '', bool $unavailableSelection = false): void
{
    echo '<option value="">Select account</option>';
    if ($unavailableSelection && $selected !== '') {
        echo '<option selected value="' . journal_escape($selected) . '" data-unavailable="1">Unavailable account — select another</option>';
    }
    foreach ($groups as $type => $accounts) {
        if (!$accounts) { continue; }
        echo '<optgroup label="' . journal_escape($type) . '">';
        foreach ($accounts as $account) {
            $id = (string) $account['CategoryID'];
            $label = ($account['Account_Code'] !== null ? $account['Account_Code'] . ' — ' : '') . $account['Name'];
            echo '<option value="' . $id . '"' . ($selected === $id ? ' selected' : '') . '>' . journal_escape($label) . '</option>';
        }
        echo '</optgroup>';
    }
}
function journal_row(array $groups, array $line, bool $unknown = false): void
{
    ?>
    <tr data-journal-line>
        <td><select data-field="account_id" required aria-label="Account"><?php journal_options($groups, $line['account_id'], $unknown); ?></select></td>
        <td><input data-field="fund_project_id" inputmode="numeric" maxlength="10" value="<?= journal_escape($line['fund_project_id']) ?>" placeholder="Optional ID" aria-label="Fund/Project ID"></td>
        <td><input data-amount="debit_amount" inputmode="decimal" maxlength="32" value="<?= journal_escape($line['debit_amount']) ?>" placeholder="0.00" aria-label="Debit"><input type="hidden" data-field="debit_amount" value="<?= journal_escape($line['debit_amount']) ?>"></td>
        <td><input data-amount="credit_amount" inputmode="decimal" maxlength="32" value="<?= journal_escape($line['credit_amount']) ?>" placeholder="0.00" aria-label="Credit"><input type="hidden" data-field="credit_amount" value="<?= journal_escape($line['credit_amount']) ?>"></td>
        <td><button type="button" data-remove-line class="journal-secondary" aria-label="Remove line">Remove</button></td>
    </tr>
    <?php
}
layout_begin('General Journal', 'general_journal', [], '<link rel="stylesheet" href="assets/css/general_journal.css?v=' . filemtime(__DIR__ . '/assets/css/general_journal.css') . '">', 'journal-page min-h-screen bg-slate-100');
?>
<div><h1 class="text-2xl font-bold text-slate-900">General Journal</h1><p class="mt-2 text-sm text-slate-600">Record a balanced entry in Philippine pesos.</p></div>
<?php if ($error !== ''): ?><div role="alert" class="journal-alert"><?= journal_escape($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div role="status" class="journal-success"><?= journal_escape($success) ?></div><?php endif; ?>
<?php if (!$unavailable): ?>
<form id="journal-form" method="post" action="general_journal.php">
    <?= csrf_field() ?><input type="hidden" name="submission_key" value="<?= journal_escape($key) ?>">
    <div class="journal-header-fields">
        <div><label for="entry-date">Entry Date</label><input id="entry-date" name="entry_date" type="date" min="1000-01-01" max="9999-12-31" required value="<?= journal_escape($form['entry_date']) ?>"><small>Defaults to today in Asia/Manila.</small></div>
        <div><label for="journal-reference">Reference (optional)</label><input id="journal-reference" name="reference" maxlength="100" value="<?= journal_escape($form['reference']) ?>"></div>
        <div class="journal-description"><label for="journal-description">Description</label><textarea id="journal-description" name="description" required maxlength="2000" rows="3"><?= journal_escape($form['description']) ?></textarea></div>
    </div>
    <div class="journal-table-wrap"><table class="journal-lines"><caption class="sr-only">Journal entry lines</caption><thead><tr><th>Account</th><th>Fund/Project</th><th>Debit</th><th>Credit</th><th>Action</th></tr></thead>
        <tbody id="journal-lines"><?php foreach ($lines as $line): journal_row($groups, $line, !isset($knownIds[$line['account_id']])); endforeach; ?></tbody>
    </table></div>
    <div class="journal-line-controls"><button id="add-journal-line" type="button" class="journal-secondary">Add Line</button><small>2–100 lines. Fund/Project accepts an optional positive numeric ID.</small></div>
    <div class="journal-totals" aria-live="polite"><div>Total Debits<strong id="total-debits">₱0.00</strong></div><div>Total Credits<strong id="total-credits">₱0.00</strong></div><div>Difference<strong id="journal-difference">₱0.00</strong></div></div>
    <p id="journal-validation" class="journal-validation" role="status">Complete at least two lines with equal Debits and Credits.</p>
    <button id="post-journal" class="journal-primary" type="submit" disabled>Post Entry</button>
    <noscript><p class="journal-alert">Enable JavaScript to enter and post journal entries.</p></noscript>
    <input id="journal-line-count" type="hidden" name="line_count" value="<?= count($lines) ?>">
    <!-- Last successful control: absence indicates a truncated PHP request. -->
    <input type="hidden" name="form_complete" value="1">
</form>
<template id="journal-line-template"><?php journal_row($groups, $blank); ?></template>
<?php endif; ?>
<?php layout_end('<script src="assets/js/general_journal.js?v=' . filemtime(__DIR__ . '/assets/js/general_journal.js') . '"></script>'); ?>
