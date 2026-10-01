<?php

/**
 * Outbound email via PHPMailer + SMTP (settings in config.php).
 */

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('SMTP_HOST') && is_file(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

/** Shared transport configuration; callers choose content and sender headers. */
function create_smtp_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : '';
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
    $mail->Port = $port > 0 ? $port : 587;
    $mail->SMTPAuth = true;
    $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls';
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    return $mail;
}

/** Tracks the DATA acknowledgement even if the connection fails during QUIT. */
class ExternalMailSMTP extends SMTP
{
    public bool $dataAttempted = false;
    public bool $dataAccepted = false;
    public bool $dataRejected = false;

    public function data($msg_data)
    {
        $this->dataAttempted = true;
        $result = parent::data($msg_data);
        $this->dataAccepted = $result;
        $code = (int) ($this->getError()['smtp_code'] ?? 0);
        $this->dataRejected = !$result && $code >= 400 && $code <= 599;
        return $result;
    }
}

/** External mail uses the authenticated address unless an approved alias is set. */
function external_mail_configuration(): array
{
    $from = defined('EXTERNAL_SMTP_FROM_EMAIL') && EXTERNAL_SMTP_FROM_EMAIL !== ''
        ? (string) EXTERNAL_SMTP_FROM_EMAIL : (defined('SMTP_USERNAME') ? (string) SMTP_USERNAME : '');
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls';
    $ready = defined('SMTP_HOST') && SMTP_HOST !== '' && SMTP_HOST !== 'smtp.example.com'
        && defined('SMTP_USERNAME') && SMTP_USERNAME !== ''
        && defined('SMTP_PASSWORD') && SMTP_PASSWORD !== ''
        && filter_var($from, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n\x00]/', $from)
        && in_array($encryption, ['tls', 'ssl'], true);
    return ['ready' => (bool) $ready, 'from_email' => $from,
        'from_name' => defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'Atikha Financial System'];
}

/**
 * Send validated external content. Optional transport is a server-side test seam,
 * never obtained from HTTP input. No automatic retries are performed.
 */
function send_external_email(array $message, ?ExternalMailSMTP $transport = null): array
{
    $result = ['outcome' => 'Failed', 'message_id' => $message['message_id'] ?? '', 'error_code' => null];
    $config = external_mail_configuration();
    if (!$config['ready']) {
        return array_replace($result, ['error_code' => 'smtp_configuration']);
    }
    $transport = $transport ?? new ExternalMailSMTP();
    try {
        $mail = create_smtp_mailer();
        $mail->Timeout = 10;
        $transport->Timelimit = 10;
        $mail->SMTPDebug = 0;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true,
            'allow_self_signed' => false]];
        $mail->setSMTPInstance($transport);
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($message['to_email']);
        $mail->addReplyTo($message['reply_to_email'], $message['sender_name'] ?? '');
        $mail->Subject = $message['subject'];
        $mail->isHTML(true);
        $mail->Body = nl2br(htmlspecialchars($message['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $mail->AltBody = $message['body'];
        $mail->MessageID = $message['message_id'];
        if (!empty($message['attachment'])) {
            $file = $message['attachment'];
            $mail->addAttachment($file['absolute_path'], $file['name'], PHPMailer::ENCODING_BASE64,
                $file['mime'], 'attachment');
        }
        $mail->send();
        return array_replace($result, ['outcome' => 'Sent', 'message_id' => $mail->getLastMessageID()]);
    } catch (\Throwable $e) {
        // Prevent destructor cleanup from attempting QUIT again after an error.
        $transport->close();
        if ($transport->dataAccepted) {
            return array_replace($result, ['outcome' => 'Sent']);
        }
        $unknown = $transport->dataAttempted && !$transport->dataRejected;
        $code = $unknown ? 'smtp_outcome_unknown' : 'smtp_rejected_or_unavailable';
        // Do not include PHPMailer ErrorInfo: it can contain recipient or credential data.
        error_log('External email transport: ' . $code);
        return array_replace($result, ['outcome' => $unknown ? 'Unknown' : 'Failed', 'error_code' => $code]);
    }
}

/**
 * Send a plain-text email. Returns false on misconfiguration or send failure.
 */
function send_email(string $to, string $subject, string $body): bool
{
    if (!defined('SMTP_HOST') || SMTP_HOST === '' || SMTP_HOST === 'smtp.example.com') {
        error_log('Email send skipped: SMTP is not configured in config.php');

        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Email send skipped: invalid recipient address');

        return false;
    }

    $fromEmail = defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL !== ''
        ? SMTP_FROM_EMAIL
        : SMTP_USERNAME;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Atikha Financial System';
    try {
        $mail = create_smtp_mailer();
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();

        return true;
    } catch (MailerException $e) {
        error_log('Email send failed: ' . $e->getMessage());

        return false;
    }
}

/**
 * Send the MFA one-time password to a user.
 */
function send_mfa_email(string $to, string $fullName, string $code): bool
{
    $subject = 'Your Atikha login verification code';
    $body = "Hello {$fullName},\n\n"
        . "Your verification code is: {$code}\n\n"
        . "This code expires in 10 minutes.\n\n"
        . "If you did not attempt to sign in, you can ignore this email.\n\n"
        . "— Atikha Financial System";

    return send_email($to, $subject, $body);
}
