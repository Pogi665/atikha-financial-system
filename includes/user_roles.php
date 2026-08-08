<?php

/**
 * Account role vocabulary and password policy.
 *
 * Users.Role is an ENUM('Admin', 'Management', 'Staff') and every access check
 * in the system compares against those short literals. The organizational job
 * titles below are presentation only -- never store or compare them.
 */

const USER_ROLE_LABELS = [
    'Admin'      => 'System Administrator',
    'Staff'      => 'Accounting/Administrative Staff',
    'Management' => 'Management and Board',
];

const USER_PASSWORD_MIN_LENGTH = 8;

/**
 * Whether a submitted role is one this system recognizes.
 *
 * Guards the ENUM: an unlisted value would otherwise be silently coerced to ''
 * by MySQL in non-strict mode, leaving an account with no usable role.
 */
function user_role_is_valid(string $role): bool
{
    return array_key_exists($role, USER_ROLE_LABELS);
}

/**
 * The display label for a stored role, falling back to the raw value.
 */
function user_role_label(string $role): string
{
    return USER_ROLE_LABELS[$role] ?? $role;
}
