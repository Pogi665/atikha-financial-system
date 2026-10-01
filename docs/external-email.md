# External Email

Open `external_email.php` from the **External Email** sidebar link. Active Admin
and Management users share outgoing history. New Email opens Compose View;
selecting a saved email opens Detail View without a composer underneath it.

## Sending and replies

- One external recipient and one optional attachment are supported.
- The authenticated SMTP mailbox is the default From address. An optional
  `EXTERNAL_SMTP_FROM_EMAIL` alias must first be authorized by the SMTP provider.
- Reply-To is the sending user's current account email. Replies arrive in that
  user's provider, not this application. No IMAP receiving is implemented.
- Message input is plain text. HTML email escapes that text and applies line
  breaks; AltBody preserves the plain-text version. History stores plain text.
- External SMTP uses authenticated TLS/SMTPS, certificate verification and
  PHPMailer `Timeout = 10`. The timeout is not a total request deadline.
- Existing MFA mail and board communications continue through their original flows.

## Send outcomes

The backend commits a send intent and audit record before contacting SMTP.
Unique signed submission keys prevent duplicate POSTs from sending twice.

- **Sent:** SMTP accepted the email and its outcome was saved. This is not proof
  of inbox delivery.
- **Failed:** SMTP did not accept the email, or preparation failed.
- **Unknown / Sending:** Acceptance is unconfirmed, including an interrupted
  request or failed outcome persistence. Check provider logs and sent mail before
  starting another attempt. These records are not automatically retried.

Sent shows accepted records; Send issues shows all other states. Original
messages and attachments remain stored for history. No automatic cleanup runs.

## Upload limits and protection

The application accepts PDF, JPG, PNG, DOC and DOCX up to 8 MB, using actual MIME
detection and random storage names. PHP `upload_max_filesize` and `post_max_size`
can impose lower limits; errors identify the effective setting. Allow multipart
overhead when configuring `post_max_size`.

`uploads/external/.htaccess` denies all direct web access. Apache must honor that
configuration. Downloads go through the authenticated `external_attachment.php`
endpoint. Database files contain only relative paths, not user-supplied filesystem
locations. Protected attachment content is excluded from Git.

## Migration 014 and recovery

`migrations/014_external_communications.sql` is additive and requires migrations
009 and 013. It creates only External_Communications and retains historical sender
references. MariaDB DDL commits independently.

Use `scripts/migrate_external_mail.php --database=<database>` for preflight.
Execution requires `--execute`; live execution also requires
`--backup-manifest=<verified-manifest>` and `--maintenance-confirmed`.
Place `.migration-private/external-email-maintenance.flag` before live execution
and remove it only after validation succeeds. The flag blocks this email module.
The runner verifies the backup and all existing table contents, indexes, foreign
key, and audit protections. Stop on failure; never disable foreign-key checks or
audit triggers. Restore/repair from the verified backup if DDL validation fails.

The verified backup for this implementation is recorded in the private
`.migration-private/external-email-run.json` file. It includes a full database
export, upload copy, SHA-256 manifest and disposable restoration evidence.

## Verification

- `scripts/test_external_mail.php --database=<atikha_test_database>` exercises
  production validation, persistence and controlled SMTP outcomes without sending
  external mail.
- `scripts/test_external_mail_http.py --database=<atikha_test_database>` serves a
  private application copy, with synthetic sessions and fake SMTP. It checks both
  roles, HTML and plain text MIME, attachments, PHP limits, modes and pagination.
  SMTP credentials and production sessions are never copied.
- `scripts/send_external_mail_smoke.php --database=atikha_finance --actor=<id>
  --to=<explicitly-authorized-test-address> --execute` performs a real send and
  retains its live history/audit record. Do not run it without a designated recipient.
- PHP and JavaScript syntax checks plus `git diff --check` cover the changed files.

To preserve existing stylesheet edits, build CSS to a temporary file with
`npm run build:css -- --output .migration-private/external-email-tailwind-generated.css`,
then run `node scripts/merge_external_mail_css.cjs`. The merger appends only absent
selectors and leaves existing CSS verbatim.

Browser visual verification requires an available browser connection. SMTP
acceptance, recipient inbox delivery, and Reply-To verification are distinct checks.
