<?php
require_once __DIR__ . '/cli_common.php';
// Defining fixture configuration prevents loading local SMTP secrets.
define('SMTP_HOST', 'fixture.example.invalid');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'system@example.invalid');
define('SMTP_PASSWORD', 'fixture-only');
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_FROM_NAME', 'Fixture system');
require_once __DIR__ . '/../includes/external_mail.php';
require_once __DIR__ . '/external_mail_fixture.php';
require_once __DIR__ . '/migrate_external_mail.php';
$checks = 0;
function email_check(bool $condition, string $label): void
{
    global $checks;
    cli_require($condition, $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function email_reject(callable $action, string $label): void
{
    try { $action(); } catch (InvalidArgumentException | RuntimeException $e) { email_check(true, $label); return; }
    email_check(false, $label);
}
try {
    $options = getopt('', ['database:']);
    $database = $options['database'] ?? '';
    cli_require((bool) preg_match('/\Aatikha_test_[a-z0-9_]+\z/', $database), 'Tests require a disposable database.');
    $pdo = cli_db($database);
    external_schema_validate($pdo);
    $users = [];
    foreach (['Admin','Management'] as $role) {
        $email = strtolower($role) . '-' . bin2hex(random_bytes(5)) . '@example.invalid';
        $id = (int) $pdo->query('SELECT GREATEST((SELECT COALESCE(MAX(UserID),0) FROM Users),
            (SELECT COALESCE(MAX(UserID),0) FROM user_identities)) + 1')->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO Users (UserID, FullName, Email, Role, Password) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$id, 'Fixture ' . $role, $email, $role, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $users[$role] = $id;
        $pdo->prepare('INSERT INTO user_identities (UserID, FullName, Email, Role) VALUES (?, ?, ?, ?)')
            ->execute([$users[$role], 'Fixture ' . $role, $email, $role]);
    }
    $_SESSION = [];
    $input = ['submission_key' => external_submission_key(), 'to_email' => 'recipient@example.invalid',
        'subject' => 'Fixture subject', 'message_body' => "<script>alert(1)</script>\nSecond line & text"];
    $captured = new FixtureExternalSMTP();
    $calls = 0;
    $sender = static function (array $message) use ($captured, &$calls): array { $calls++; return send_external_email($message, $captured); };
    $result = external_mail_submit($pdo, $users['Admin'], $input, null, $sender);
    email_check($result['status'] === 'Sent' && $calls === 1, 'Admin send accepted by controlled transport');
    $row = external_mail_attempt($pdo, $input['submission_key'], $users['Admin']);
    email_check($row['Message_Body'] === $input['message_body'] && $row['Sent_At'] !== null
        && $row['To_Email'] === $input['to_email'], 'Original text, recipient and acceptance timestamp logged');
    email_check($row['From_Email'] === SMTP_USERNAME && $row['Reply_To_Email'] !== SMTP_USERNAME,
        'Authorized SMTP From and user Reply-To logged separately');
    email_check(str_contains($captured->mime, 'text/html') && str_contains($captured->mime, 'text/plain')
        && str_contains($captured->mime, '&lt;script&gt;') && str_contains($captured->mime, '<br />')
        && str_contains($captured->mime, $row['SMTP_Message_ID']), 'Escaped HTML, line breaks, plain-text fallback and fixed Message-ID in MIME');
    email_check($captured->Timeout === 10 && $captured->Timelimit === 10, 'External transport has 10-second timeout');
    $repeat = external_mail_submit($pdo, $users['Admin'], $input, null, $sender);
    email_check($repeat['duplicate'] && $repeat['id'] === $result['id'] && $calls === 1, 'Duplicate submission never invokes SMTP again');
    foreach (['auth' => 'Failed', 'reject' => 'Failed', 'unknown' => 'Unknown', 'quit' => 'Sent'] as $mode => $expected) {
        $attempt = $input; $attempt['submission_key'] = external_submission_key();
        $outcome = external_mail_submit($pdo, $users['Management'], $attempt, null,
            static fn(array $message): array => send_external_email($message, new FixtureExternalSMTP($mode)));
        email_check($outcome['status'] === $expected, 'SMTP mode ' . $mode . ' classified as ' . $expected);
    }
    $beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM External_Communications')->fetchColumn();
    foreach ([['to_email' => "recipient@example.invalid\r\nBcc:x@example.invalid"], ['to_email' => 'a@example.invalid,b@example.invalid'],
        ['subject' => "subject\nInjected"], ['subject' => str_repeat('x', 256)], ['message_body' => ''],
        ['message_body' => str_repeat('x', 65536)], ['submission_key' => str_repeat('0',64)], ['subject' => []]] as $bad) {
        email_reject(fn() => external_mail_submit($pdo, $users['Admin'], array_replace($input, ['submission_key' => external_submission_key()], $bad), null, $sender), 'Invalid input rejected before SMTP');
    }
    email_check((int) $pdo->query('SELECT COUNT(*) FROM External_Communications')->fetchColumn() === $beforeCount && $calls === 1,
        'Invalid input creates no history or transmission');
    $pdo->exec('UPDATE Users SET Is_Active=0 WHERE UserID=' . $users['Management']);
    email_reject(fn() => external_mail_submit($pdo, $users['Management'], $input, null, $sender), 'Inactive account rejected');
    $pdo->exec('UPDATE Users SET Is_Active=1 WHERE UserID=' . $users['Management']);
    email_check(str_contains(external_upload_error(UPLOAD_ERR_INI_SIZE), 'upload_max_filesize')
        && str_contains(external_upload_error(UPLOAD_ERR_INI_SIZE), (string) ini_get('upload_max_filesize')), 'PHP upload-size error names effective server limit');
    email_check(external_store_upload(['error' => UPLOAD_ERR_NO_FILE]) === null, 'Optional upload accepts no file');
    email_reject(fn() => external_store_upload(['error' => [UPLOAD_ERR_OK]]), 'Multiple-file upload structure rejected');
    email_reject(fn() => external_store_upload(['error' => UPLOAD_ERR_PARTIAL]), 'Interrupted upload rejected');
    email_reject(fn() => external_attachment_path('uploads/external/../../config.php'), 'Attachment path traversal rejected');
    email_check(external_ini_bytes('8M') === 8388608 && external_ini_bytes('512K') === 524288
        && external_ini_bytes('0') === 0, 'PHP upload limits parsed');
    $postLimit = external_ini_bytes((string) ini_get('post_max_size'));
    email_check($postLimit === 0 || str_contains(external_post_limit_error(['CONTENT_LENGTH' => $postLimit + 1]), 'post_max_size'), 'Oversized request detected before CSRF');
    $pdo->exec("CREATE TRIGGER fixture_email_initial_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture audit outage'");
    try {
        email_reject(fn() => external_mail_submit($pdo, $users['Admin'], array_replace($input, ['submission_key' => external_submission_key()]), null, $sender), 'Initial audit outage prevents SMTP');
        email_check((int) $pdo->query('SELECT COUNT(*) FROM External_Communications')->fetchColumn() === $beforeCount && $calls === 1,
            'Initial persistence failure rolls back intent');
    } finally { $pdo->exec('DROP TRIGGER fixture_email_initial_audit'); }
    $pdo->exec("CREATE TRIGGER fixture_email_final_audit BEFORE INSERT ON audit_logs FOR EACH ROW BEGIN IF NEW.module='External_Communications' AND NEW.action_type='EDIT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture final audit outage'; END IF; END");
    try {
        $finalInput = array_replace($input, ['submission_key' => external_submission_key()]);
        $outcome = external_mail_submit($pdo, $users['Admin'], $finalInput, null, $sender);
        $saved = external_mail_attempt($pdo, $finalInput['submission_key'], $users['Admin']);
        email_check($outcome['status'] === 'Unknown' && $outcome['confirmation_incomplete'] && $saved['Send_Status'] === 'Sending',
            'Post-acceptance persistence failure retains durable intent and reports uncertainty');
        external_mail_submit($pdo, $users['Admin'], $finalInput, null, $sender);
        email_check($calls === 2, 'Unconfirmed accepted attempt cannot be resent by duplicate POST');
    } finally { $pdo->exec('DROP TRIGGER fixture_email_final_audit'); }
    echo 'PASS: ' . $checks . " external email backend checks; no external mail sent.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
