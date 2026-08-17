<?php

/**
 * Review status UI helpers.
 */

require_once __DIR__ . '/notifications.php';
function review_render_status_badge(string $status): string
{
    $label = notification_review_status_label($status);
    $class = notification_review_status_badge_class($status);

    return '<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium '
        . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Whether Staff/Admin can send an item for review.
 */
function review_can_send(string $status): bool
{
    return $status === 'None' || $status === 'Reviewed';
}
