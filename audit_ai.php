<?php

/**
 * AI monitoring endpoint for the audit trail.
 *
 * POST-only, Administrator-only, JSON in and out. Reads audit_logs and never
 * writes to it.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit_query.php';
require_once __DIR__ . '/includes/gemini_client.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<string, mixed>|null $data
 */
function audit_ai_respond(bool $ok, ?array $data, string $error, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    audit_ai_respond(false, null, 'This endpoint accepts POST requests only.', 405);
}

if (empty($_SESSION['UserID'])) {
    audit_ai_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

if (($_SESSION['Role'] ?? '') !== 'Admin') {
    audit_ai_respond(false, null, 'AI monitoring is restricted to System Administrators.', 403);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    audit_ai_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

if (!gemini_is_configured()) {
    audit_ai_respond(false, null, 'AI features are not configured. Add your Gemini API key to config.php.');
}

$action = $_POST['action'] ?? '';
$filters = audit_filters_from_request($_POST);

try {
    if ($action === 'anomaly_scan') {
        // Always the most recent entries system-wide: an anomaly the current
        // filters happen to exclude is exactly the one worth catching.
        $logs = audit_fetch_logs($pdo, audit_filters_from_request([]), AUDIT_SCAN_LIMIT);
        $result = gemini_audit_anomaly_scan(audit_logs_for_ai($logs));

        audit_ai_respond($result['ok'], $result['data'], $result['error']);
    }

    if ($action === 'summary') {
        $logs = audit_fetch_logs($pdo, $filters, AUDIT_SUMMARY_LIMIT);
        $result = gemini_audit_summary(
            audit_logs_for_ai($logs),
            audit_filters_describe($filters)
        );

        audit_ai_respond($result['ok'], $result['data'], $result['error']);
    }
} catch (PDOException $e) {
    error_log('Audit AI query failed: ' . $e->getMessage());
    audit_ai_respond(false, null, 'Unable to read the audit trail. Please try again.', 500);
}

audit_ai_respond(false, null, 'Unknown action.', 400);
