<?php

/**
 * Audit trail writer.
 *
 * log_system_action() is the only way anything in this system writes to
 * audit_logs, and it only ever INSERTs. The table is append-only: nothing here
 * (or anywhere else) may UPDATE or DELETE a row. See
 * migrations/002_audit_trail.sql.
 */

const AUDIT_ACTION_CREATE = 'CREATE';
const AUDIT_ACTION_EDIT = 'EDIT';
const AUDIT_ACTION_DELETE = 'DELETE';
const AUDIT_ACTION_APPROVAL = 'APPROVAL';
const AUDIT_ACTION_REVIEW_REQUEST = 'REVIEW_REQUEST';
const AUDIT_ACTION_REVIEW_COMPLETE = 'REVIEW_COMPLETE';
const AUDIT_ACTION_LOGIN = 'LOGIN';
const AUDIT_ACTION_LOGOUT = 'LOGOUT';

// Any array key containing one of these fragments is dropped before encoding,
// so a careless caller can never push a credential into permanent storage.
const AUDIT_REDACTED_KEY_FRAGMENTS = ['password', 'passwd', 'secret', 'token', 'csrf', 'api_key', 'apikey'];

/**
 * Append one row to the audit trail.
 *
 * Returns false rather than throwing: a logging outage must not take down a
 * financial save. The exception is a caller that already has a transaction
 * open, where the INSERT joins that transaction and is rolled back with it.
 *
 * @param array<string, mixed>|string|null $old_values Decoded array or JSON string.
 * @param array<string, mixed>|string|null $new_values Decoded array or JSON string.
 */
function log_system_action(
    PDO $pdo,
    int $user_id,
    string $action_type,
    string $module,
    ?int $record_id = null,
    $old_values = null,
    $new_values = null,
    ?string $source_link = null
): bool {
    if ($user_id <= 0) {
        error_log('Audit log skipped: no authenticated user for ' . $action_type . '/' . $module);

        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs
                (user_id, action_type, module, record_id, old_values, new_values, source_link, ip_address)
             VALUES
                (:user_id, :action_type, :module, :record_id, :old_values, :new_values, :source_link, :ip_address)'
        );
        $stmt->execute([
            'user_id'     => $user_id,
            'action_type' => strtoupper(substr(trim($action_type), 0, 30)),
            'module'      => substr(trim($module), 0, 50),
            'record_id'   => $record_id !== null && $record_id > 0 ? $record_id : null,
            'old_values'  => audit_encode_values($old_values),
            'new_values'  => audit_encode_values($new_values),
            'source_link' => $source_link !== null && $source_link !== '' ? substr($source_link, 0, 255) : null,
            'ip_address'  => audit_client_ip(),
        ]);

        return true;
    } catch (PDOException $e) {
        error_log('Audit log insert failed: ' . $e->getMessage());

        // Rethrow inside a transaction so the caller's rollback covers both the
        // business write and the missing audit row.
        if ($pdo->inTransaction()) {
            throw $e;
        }

        return false;
    }
}

/**
 * Normalize a caller's before/after payload into a JSON string or null.
 *
 * @param array<string, mixed>|string|null $values
 */
function audit_encode_values($values): ?string
{
    if ($values === null) {
        return null;
    }

    if (is_string($values)) {
        $trimmed = trim($values);
        if ($trimmed === '') {
            return null;
        }

        // Already-encoded JSON passes through; anything else is wrapped so the
        // column always holds valid JSON.
        json_decode($trimmed);

        return json_last_error() === JSON_ERROR_NONE
            ? $trimmed
            : json_encode(['value' => $trimmed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!is_array($values)) {
        $values = ['value' => $values];
    }

    if ($values === []) {
        return null;
    }

    $encoded = json_encode(audit_redact($values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $encoded === false ? null : $encoded;
}

/**
 * Strip credential-looking keys at every depth.
 *
 * @param array<string, mixed> $values
 *
 * @return array<string, mixed>
 */
function audit_redact(array $values): array
{
    $clean = [];

    foreach ($values as $key => $value) {
        $needle = strtolower((string) $key);
        $isSecret = false;

        foreach (AUDIT_REDACTED_KEY_FRAGMENTS as $fragment) {
            if (strpos($needle, $fragment) !== false) {
                $isSecret = true;
                break;
            }
        }

        if ($isSecret) {
            continue;
        }

        $clean[$key] = is_array($value) ? audit_redact($value) : $value;
    }

    return $clean;
}

/**
 * The client IP, taken from REMOTE_ADDR only.
 *
 * X-Forwarded-For and friends are set by the client and cannot be trusted in
 * something meant to be used as evidence.
 */
function audit_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
        return $ip;
    }

    // CLI and cron have no remote address.
    return '0.0.0.0';
}

/**
 * Fields that actually changed, as {field: {from: ..., to: ...}}.
 *
 * Keeps EDIT rows small and makes the side-by-side diff meaningful instead of
 * showing two near-identical blobs.
 *
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 *
 * @return array<string, array{from: mixed, to: mixed}>
 */
function audit_diff(array $before, array $after): array
{
    $changes = [];

    foreach ($after as $key => $newValue) {
        $oldValue = $before[$key] ?? null;

        // Loose string comparison: DECIMAL columns come back as "1500.00"
        // while the submitted value is 1500, and that is not a change.
        if ((string) $oldValue !== (string) $newValue) {
            $changes[$key] = ['from' => $oldValue, 'to' => $newValue];
        }
    }

    return $changes;
}
