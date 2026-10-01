<?php
/** Explicit, CLI-only live smoke send. Uses the same durable backend as the form. */
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/external_mail.php';
try {
    $options = getopt('', ['database:', 'actor:', 'to:', 'execute']);
    cli_require(isset($options['execute']), 'No mail sent. Supply --execute and an explicitly authorized test recipient.');
    $pdo = cli_db($options['database'] ?? '');
    $actor = (int) ($options['actor'] ?? 0);
    $to = $options['to'] ?? '';
    cli_require((bool) filter_var($to, FILTER_VALIDATE_EMAIL), 'A designated test email address is required.');
    $_SESSION = [];
    $input = ['submission_key' => external_submission_key(), 'to_email' => $to,
        'subject' => 'Atikha External Email - SMTP verification',
        'message_body' => "This is the approved External Email module verification message.\n\n"
            . "This second paragraph checks HTML line breaks and the plain-text fallback.\n"
            . "Replies are directed to the sending account's email address.\n\n"
            . "No financial records or credentials are included in this test."];
    $result = external_mail_submit($pdo, $actor, $input, null);
    $row = external_mail_attempt($pdo, $input['submission_key'], $actor);
    cli_require($row !== null, 'Durable send record could not be verified.');
    echo json_encode(['communication_id' => $result['id'], 'status' => $result['status'],
        'persisted_status' => $row['Send_Status'], 'acceptance_timestamp' => $row['Sent_At'],
        'failure_code' => $row['Failure_Code'], 'recipient' => $row['To_Email'],
        'has_message_id' => $row['SMTP_Message_ID'] !== '', 'reply_to_saved' => $row['Reply_To_Email'] !== ''],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if ($result['status'] !== 'Sent') { exit(2); }
} catch (Throwable $e) {
    // Avoid exposing database details or SMTP credentials in operational output.
    fwrite(STDERR, 'Smoke send could not complete. Check account, database and secure SMTP configuration.' . PHP_EOL);
    exit(1);
}
