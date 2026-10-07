<?php
// Stage 1 UI/API remains disabled until migration 019 is separately deployed.
// Manual image evidence works independently of OCR_JOURNAL_ENABLED.
define('STAGE1_WORKSPACE_ENABLED', false);
// Requires complete migration 020 and Stage 1. Enable only after deployment checks.
define('STAGE2_ADVANCES_ENABLED', false);
// Stage 3 checkpoint 1 provides schema/readers only. Keep disabled until all checkpoints pass.
define('STAGE3_CORRECTIONS_ENABLED', false);
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

// External Email defaults From to SMTP_USERNAME. Set this only for an alias
// explicitly authorized by the SMTP provider. Replies use the sending user's Email.
// define('EXTERNAL_SMTP_FROM_EMAIL', 'approved-alias@example.com');
