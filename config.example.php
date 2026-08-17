<?php
/**
 * Example configuration — copy to config.php and fill in real values.
 *
 * config.php is gitignored and holds local secrets (Gemini API key, SMTP, etc.).
 */

// Gemini AI (optional — used by OCR, forecast, audit features)
// define('GEMINI_API_KEY', 'your-gemini-api-key');

// SMTP (required for email MFA login codes)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your@email.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@atikha.org');
define('SMTP_FROM_NAME', 'Atikha Financial System');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
