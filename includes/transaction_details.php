<?php

/** User-authored metadata only. Never populate these fields from OCR or notes. */
function transaction_details_input(array $post): ?array
{
    if (!is_string($post['purpose'] ?? null) || !is_string($post['project_code'] ?? '')) {
        return null;
    }
    $purpose = trim($post['purpose']);
    $project = trim($post['project_code'] ?? '');
    if ($purpose === '' || mb_strlen($purpose) > 1000 || mb_strlen($project) > 50) {
        return null;
    }
    return ['purpose' => $purpose, 'project_code' => $project === '' ? null : $project];
}

function transaction_detail_label(?string $value, string $empty): string
{
    return trim($value ?? '') === '' ? $empty : $value;
}

/** Shared fields for creation, editing, and OCR confirmation. */
function transaction_details_fields(string $prefix, string $fieldClass, array $values = [], bool $includeProject = true): void
{
    $escape = static fn ($v) => htmlspecialchars(is_scalar($v) ? (string) $v : '', ENT_QUOTES, 'UTF-8');
    ?>
    <div class="col-span-2">
        <label for="<?= $escape($prefix) ?>purpose" class="block text-sm font-medium text-slate-700 mb-1">Purpose</label>
        <textarea id="<?= $escape($prefix) ?>purpose" name="purpose" required maxlength="1000" rows="2" class="<?= $escape($fieldClass) ?>"><?= $escape($values['purpose'] ?? '') ?></textarea>
    </div>
    <?php if ($includeProject): ?>
    <div class="col-span-2">
        <label for="<?= $escape($prefix) ?>project" class="block text-sm font-medium text-slate-700 mb-1">Allocation/Project Code (optional)</label>
        <input id="<?= $escape($prefix) ?>project" name="project_code" maxlength="50" class="<?= $escape($fieldClass) ?>" value="<?= $escape($values['project_code'] ?? '') ?>">
        <p class="text-xs text-slate-500">Leave blank for Unallocated.</p>
    </div>
    <?php endif;
}
