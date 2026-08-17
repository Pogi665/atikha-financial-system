<?php

/**
 * Outbound email via PHPMailer + SMTP (settings in config.php).
 */

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

if (is_file(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
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
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls';
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = $port > 0 ? $port : 587;
        $mail->SMTPAuth = true;
        $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->CharSet = PHPMailer::CHARSET_UTF8;
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
