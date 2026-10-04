<?php
/** Call after authentication, before any legacy write or upload. */
function legacy_entry_guard(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: general_journal.php', true, 302);
        exit;
    }
    http_response_code(410);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Entry workflow retired</title>'
        . '<p>This transaction workflow has been retired. <a href="general_journal.php">Open General Journal</a>.</p></html>';
    exit;
}
