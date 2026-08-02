<?php

/**
 * CSRF token helpers.
 *
 * Uses the same $_SESSION['csrf_token'] key that ocr_expense.php already
 * issues, so a token minted on one page stays valid on the others.
 * Callers must have started the session first.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $submitted): bool
{
    if (!is_string($submitted) || $submitted === '') {
        return false;
    }

    $expected = $_SESSION['csrf_token'] ?? '';

    return is_string($expected) && $expected !== '' && hash_equals($expected, $submitted);
}

/**
 * Hidden field for a POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}
