<?php
/** External email validation, protected storage and durable send history. */
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/board_uploads.php';

const EXTERNAL_UPLOAD_DIR = __DIR__ . '/../uploads/external';
const EXTERNAL_BODY_MAX_BYTES = 65535;

function external_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '0' || $value === '-1') { return 0; }
    if (!preg_match('/\A(\d+(?:\.\d+)?)\s*([kmg]?)\z/i', $value, $m)) { return 0; }
    $power = array_search(strtolower($m[2]), ['', 'k', 'm', 'g'], true);
    return (int) ((float) $m[1] * (1024 ** $power));
}

function external_post_limit_error(array $server): string
{
    $setting = (string) ini_get('post_max_size');
    $limit = external_ini_bytes($setting);
    $length = isset($server['CONTENT_LENGTH']) && is_scalar($server['CONTENT_LENGTH'])
        ? (int) $server['CONTENT_LENGTH'] : 0;
    return $limit > 0 && $length > $limit
        ? 'PHP rejected this request because it exceeds post_max_size (' . $setting
            . '). Choose a smaller attachment or ask the administrator to increase the PHP upload limit.' : '';
}

function external_upload_error(int $code): string
{
    if ($code === UPLOAD_ERR_INI_SIZE) {
        return 'PHP rejected this attachment because it exceeds this server\'s upload_max_filesize limit of '
            . ini_get('upload_max_filesize')
            . '. Choose a smaller file or ask the administrator to increase the PHP upload limit.';
    }
    if ($code === UPLOAD_ERR_FORM_SIZE) { return 'This attachment exceeds the application\'s 8 MB limit.'; }
    return board_upload_error_message($code);
}

function external_attachment_path(string $path): string
{
    if (!preg_match('~\Auploads/external/[a-f0-9]{32}\.(pdf|jpg|png|doc|docx)\z~', $path)) {
        throw new RuntimeException('Invalid attachment reference.');
    }
    $root = realpath(EXTERNAL_UPLOAD_DIR);
    $absolute = realpath(__DIR__ . '/../' . $path);
    if ($root === false || $absolute === false || dirname($absolute) !== $root || !is_file($absolute)) {
        throw new RuntimeException('The saved attachment is unavailable.');
    }
    return $absolute;
}

function external_store_upload(?array $file): ?array
{
    if ($file === null) { return null; }
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new InvalidArgumentException('Choose one attachment only.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    if ($file['error'] !== UPLOAD_ERR_OK) { throw new InvalidArgumentException(external_upload_error($file['error'])); }
    if (!is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('The upload could not be verified. Please choose the file again.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size <= 0) { throw new InvalidArgumentException('The uploaded file is empty.'); }
    if ($size > MAX_BOARD_FILE_BYTES) { throw new InvalidArgumentException('The application attachment limit is 8 MB.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ALLOWED_BOARD_MIMES[$mime])) {
        throw new InvalidArgumentException('Only PDF, JPG, PNG, DOC, or DOCX files are accepted.');
    }
    // Refuse unprotected storage rather than silently create a public directory.
    if (!is_dir(EXTERNAL_UPLOAD_DIR) || !is_file(EXTERNAL_UPLOAD_DIR . '/.htaccess')) {
        throw new RuntimeException('Protected attachment storage is unavailable.');
    }
    $name = basename(str_replace('\\', '/', $file['name']));
    $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
    $name = mb_strcut($name, 0, 240, 'UTF-8');
    if ($name === '' || $name === '.' || $name === '..') { $name = 'attachment.' . ALLOWED_BOARD_MIMES[$mime]; }
    $path = 'uploads/external/' . bin2hex(random_bytes(16)) . '.' . ALLOWED_BOARD_MIMES[$mime];
    $absolute = __DIR__ . '/../' . $path;
    if (!move_uploaded_file($file['tmp_name'], $absolute)) { throw new RuntimeException('The server could not store the attachment.'); }
    return ['path' => $path, 'absolute_path' => external_attachment_path($path),
        'name' => $name, 'mime' => $mime, 'size' => $size];
}

function external_submission_key(): string
{
    if (empty($_SESSION['external_send_secret'])) { $_SESSION['external_send_secret'] = bin2hex(random_bytes(32)); }
    $nonce = bin2hex(random_bytes(16));
    return $nonce . substr(hash_hmac('sha256', $nonce, $_SESSION['external_send_secret']), 0, 32);
}

function external_submission_valid(string $key): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/', $key) && is_string($_SESSION['external_send_secret'] ?? null)
        && hash_equals(substr(hash_hmac('sha256', substr($key, 0, 32), $_SESSION['external_send_secret']), 0, 32), substr($key, 32));
}

function external_input(array $input, string $name): string
{
    if (!isset($input[$name]) || !is_string($input[$name])) { throw new InvalidArgumentException('Invalid email form.'); }
    return trim($input[$name]);
}

function external_mail_attempt(PDO $pdo, string $key, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM External_Communications WHERE Submission_Key = ? AND Sender_UserID = ?');
    $stmt->execute([$key, $userId]);
    return $stmt->fetch() ?: null;
}

/** SMTP can never be rolled back; commit an intent before invoking the transport. */
function external_mail_submit(PDO $pdo, int $userId, array $input, ?array $file, ?callable $sender = null): array
{
    $stmt = $pdo->prepare('SELECT FullName, Email, Role, Is_Active FROM Users WHERE UserID = ?');
    $stmt->execute([$userId]);
    $actor = $stmt->fetch();
    if (!$actor || (int) $actor['Is_Active'] !== 1 || !in_array($actor['Role'], ['Admin', 'Management'], true)) {
        throw new InvalidArgumentException('Account access is unavailable.');
    }
    $key = external_input($input, 'submission_key');
    if (!external_submission_valid($key)) { throw new InvalidArgumentException('This compose session expired. Please reload and try again.'); }
    $existing = external_mail_attempt($pdo, $key, $userId);
    if ($existing) { return ['id' => (int) $existing['CommunicationID'], 'status' => $existing['Send_Status'], 'duplicate' => true]; }
    $to = external_input($input, 'to_email');
    $subject = external_input($input, 'subject');
    $body = external_input($input, 'message_body');
    if (strlen($to) > 254 || !filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\x00-\x1f\x7f]/', $to)) {
        throw new InvalidArgumentException('Enter one valid recipient email address.');
    }
    if ($subject === '' || !mb_check_encoding($subject, 'UTF-8') || mb_strlen($subject, 'UTF-8') > 255
        || preg_match('/[\x00-\x1f\x7f]/', $subject)) {
        throw new InvalidArgumentException('Enter a subject of up to 255 characters without line breaks.');
    }
    if ($body === '' || !mb_check_encoding($body, 'UTF-8') || strlen($body) > EXTERNAL_BODY_MAX_BYTES || str_contains($body, "\0")) {
        throw new InvalidArgumentException('Enter a message body of up to 65,535 UTF-8 bytes.');
    }
    if (!filter_var($actor['Email'], FILTER_VALIDATE_EMAIL) || strlen($actor['Email']) > 254
        || preg_match('/[\r\n\x00]/', $actor['Email'])) {
        throw new InvalidArgumentException('Your account email must be valid before it can receive replies.');
    }
    $config = external_mail_configuration();
    if (!$config['ready']) { throw new RuntimeException('Secure SMTP is not configured. Contact the administrator.'); }
    $attachment = external_store_upload($file);
    $domain = substr(strrchr($config['from_email'], '@'), 1);
    $messageId = '<external.' . bin2hex(random_bytes(24)) . '@' . $domain . '>';
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO External_Communications
            (Sender_UserID, To_Email, From_Email, Reply_To_Email, Subject, Message_Body, File_Path,
             Attachment_Name, Attachment_Mime, Attachment_Size, SMTP_Message_ID, Submission_Key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $to, $config['from_email'], $actor['Email'], $subject, $body,
            $attachment['path'] ?? null, $attachment['name'] ?? null, $attachment['mime'] ?? null,
            $attachment['size'] ?? null, $messageId, $key]);
        $id = (int) $pdo->lastInsertId();
        log_system_action($pdo, $userId, AUDIT_ACTION_CREATE, 'External_Communications', $id, null,
            ['send_status' => 'Sending', 'has_attachment' => $attachment !== null], 'external_email.php?id=' . $id);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($attachment) { unlink($attachment['absolute_path']); }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            $existing = external_mail_attempt($pdo, $key, $userId);
            if ($existing) { return ['id' => (int) $existing['CommunicationID'], 'status' => $existing['Send_Status'], 'duplicate' => true]; }
        }
        error_log('External email: intent persistence failed');
        throw new RuntimeException('Unable to save this email. No email was sent.');
    }
    try {
        $result = ($sender ?? 'send_external_email')(['to_email' => $to, 'reply_to_email' => $actor['Email'],
            'sender_name' => $actor['FullName'], 'subject' => $subject, 'body' => $body,
            'message_id' => $messageId, 'attachment' => $attachment]);
        if (!in_array($result['outcome'] ?? '', ['Sent', 'Failed', 'Unknown'], true)) {
            throw new RuntimeException('Invalid transport outcome.');
        }
    } catch (Throwable $e) {
        $result = ['outcome' => 'Unknown', 'error_code' => 'smtp_outcome_unknown'];
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE External_Communications SET Send_Status = :status,
            Sent_At = CASE WHEN :accepted = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
            Failure_Code = :failure WHERE CommunicationID = :id AND Send_Status = 'Sending'");
        $failure = in_array($result['error_code'] ?? '', ['smtp_configuration', 'smtp_outcome_unknown', 'smtp_rejected_or_unavailable'], true)
            ? $result['error_code'] : null;
        $stmt->execute(['status' => $result['outcome'], 'accepted' => $result['outcome'] === 'Sent' ? 1 : 0,
            'failure' => $failure, 'id' => $id]);
        if ($stmt->rowCount() !== 1) { throw new RuntimeException('Send state changed unexpectedly.'); }
        log_system_action($pdo, $userId, AUDIT_ACTION_EDIT, 'External_Communications', $id,
            ['send_status' => 'Sending'], ['send_status' => $result['outcome'], 'failure_code' => $failure],
            'external_email.php?id=' . $id);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('External email: outcome persistence failed for communication ' . $id);
        return ['id' => $id, 'status' => 'Unknown', 'confirmation_incomplete' => true];
    }
    return ['id' => $id, 'status' => $result['outcome'], 'duplicate' => false];
}

function external_status_message(string $status): string
{
    if ($status === 'Sent') { return 'The SMTP server accepted this email. Inbox delivery is not yet verified.'; }
    if ($status === 'Failed') { return 'This email was not accepted by SMTP. Check the email and SMTP settings before starting a new attempt.'; }
    return 'The send outcome is unconfirmed. Check the provider\'s sent mail or SMTP logs before sending again.';
}
