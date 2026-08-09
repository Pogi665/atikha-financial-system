<?php

/**
 * Predictive forecast endpoint for the dashboard card.
 *
 * POST-only, JSON in and out. The dashboard renders without it and fetches it
 * afterwards, so nothing here is allowed to be fatal: every failure path still
 * returns a drawable chart built from the trailing average.
 *
 * Reads Expenses and Incoming_Funds. The only table it writes is
 * forecast_cache, which is disposable.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/forecast_query.php';
require_once __DIR__ . '/includes/gemini_client.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json; charset=utf-8');

// Refreshing spends API tokens, so it is not something Staff can trigger.
const FORECAST_REFRESH_ROLES = ['Admin', 'Management'];

// Shortest gap between two live generations. Guards against a double-click
// turning into two paid calls.
const FORECAST_REFRESH_THROTTLE_SECONDS = 60;

// Cache rows are only useful for 24 hours; a month of them is plenty of history
// for anyone inspecting what the model has been saying.
const FORECAST_PRUNE_DAYS = 30;

/**
 * @param array<string, mixed>|null $data
 */
function forecast_respond(bool $ok, ?array $data, string $error, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * The response body the card renders, in one shape for every path.
 *
 * @param array<string, mixed>      $history
 * @param array<string, mixed>|null $forecast
 */
function forecast_payload(
    array $history,
    ?array $forecast,
    string $state,
    ?string $generatedAt = null,
    string $note = ''
): array {
    return [
        'state'        => $state,
        'as_of'        => $history['as_of'],
        'generated_at' => $generatedAt,
        'note'         => $note,
        'history'      => $history['series'],
        'projection'   => $forecast['chart_data'] ?? forecast_baseline_projection($history),
        'advisory'     => [
            'reallocation_suggestion' => $forecast['reallocation_suggestion'] ?? '',
            'funding_risk'            => $forecast['funding_risk'] ?? '',
            'risk_level'              => $forecast['risk_level'] ?? '',
        ],
        'metrics'      => $history['metrics'],
        'categories'   => $history['categories'],
        'current_month'=> $history['current_month'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    forecast_respond(false, null, 'This endpoint accepts POST requests only.', 405);
}

if (empty($_SESSION['UserID'])) {
    forecast_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    forecast_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

$userId = (int) $_SESSION['UserID'];
$isRefresh = ($_POST['action'] ?? 'load') === 'refresh';

if ($isRefresh && !in_array($_SESSION['Role'] ?? '', FORECAST_REFRESH_ROLES, true)) {
    forecast_respond(
        false,
        null,
        'Recalculating the forecast is restricted to Administrators and Management.',
        403
    );
}

try {
    $history = forecast_fetch_history($pdo);
} catch (PDOException $e) {
    error_log('Forecast aggregation failed: ' . $e->getMessage());
    forecast_respond(false, null, 'Unable to read the financial history. Please try again.', 500);
}

// Two months of expenses is the floor for a projection to mean anything. Stop
// before spending a call on a straight line through one point.
if (!forecast_history_is_sufficient($history)) {
    forecast_respond(true, forecast_payload($history, null, 'insufficient'), '');
}

// A missing forecast_cache table means migration 003 has not been run. That
// costs the caching, not the feature.
$cacheAvailable = true;

if (!$isRefresh) {
    try {
        // The interval is a constant, not input: inlined because MySQL will not
        // accept a placeholder in an INTERVAL expression.
        $cached = $pdo->query(
            'SELECT Forecast_JSON, created_at
             FROM forecast_cache
             WHERE created_at >= NOW() - INTERVAL ' . FORECAST_TTL_HOURS . ' HOUR
             ORDER BY created_at DESC
             LIMIT 1'
        )->fetch();

        if ($cached !== false) {
            $decoded = json_decode((string) $cached['Forecast_JSON'], true);

            if (is_array($decoded)) {
                forecast_respond(
                    true,
                    forecast_payload($history, $decoded, 'cached', (string) $cached['created_at']),
                    ''
                );
            }
        }
    } catch (PDOException $e) {
        error_log('Forecast cache read failed: ' . $e->getMessage());
        $cacheAvailable = false;
    }
}

if ($isRefresh && !forecast_refresh_is_allowed($pdo)) {
    forecast_respond(
        false,
        null,
        'A forecast was just generated. Please wait a moment before recalculating.',
        429
    );
}

if (!gemini_is_configured()) {
    forecast_respond(
        true,
        forecast_payload(
            $history,
            null,
            'degraded',
            null,
            'AI advisory is unavailable: add your Gemini API key to config.php. '
                . 'The projection below is a trailing three-month average.'
        ),
        ''
    );
}

$result = gemini_forecast_projection(forecast_history_for_ai($history));

if (!$result['ok']) {
    forecast_respond(
        true,
        forecast_payload(
            $history,
            null,
            'degraded',
            null,
            $result['error'] . ' The projection below is a trailing three-month average.'
        ),
        ''
    );
}

// Always the database clock. PHP and MySQL can sit in different time zones, and
// a fresh forecast that reports a different hour than the cached read of the
// same row would look like a bug to anyone watching the card.
$generatedAt = $cacheAvailable
    ? forecast_store($pdo, $history, $result['data'], $userId)
    : null;

if ($generatedAt === null) {
    $generatedAt = forecast_server_time($pdo);
}

if ($isRefresh) {
    log_system_action(
        $pdo,
        $userId,
        'REFRESH',
        'Forecast',
        null,
        null,
        [
            'horizon_months' => FORECAST_HORIZON_MONTHS,
            'risk_level'     => $result['data']['risk_level'],
            'fingerprint'    => forecast_fingerprint($history),
        ],
        'dashboard.php'
    );
}

forecast_respond(true, forecast_payload($history, $result['data'], 'fresh', $generatedAt), '');

/**
 * Has enough time passed since the last live generation?
 *
 * Returns true when the check cannot be made, so a missing cache table blocks
 * caching rather than the button.
 */
function forecast_refresh_is_allowed(PDO $pdo): bool
{
    try {
        $recent = $pdo->query(
            'SELECT COUNT(*)
             FROM forecast_cache
             WHERE created_at >= NOW() - INTERVAL ' . FORECAST_REFRESH_THROTTLE_SECONDS . ' SECOND'
        )->fetchColumn();

        return (int) $recent === 0;
    } catch (PDOException $e) {
        error_log('Forecast throttle check failed: ' . $e->getMessage());

        return true;
    }
}

/**
 * The database's own clock, so timestamps agree with stored created_at values.
 */
function forecast_server_time(PDO $pdo): string
{
    try {
        return (string) $pdo->query('SELECT NOW()')->fetchColumn();
    } catch (PDOException $e) {
        error_log('Forecast timestamp read failed: ' . $e->getMessage());

        return date('Y-m-d H:i:s');
    }
}

/**
 * Persist one generated forecast as the new 24-hour cache.
 *
 * A failure here is logged and swallowed: the caller already has a forecast to
 * return, and losing the cache is not worth losing the response over.
 *
 * @param array<string, mixed> $history
 * @param array<string, mixed> $forecast
 *
 * @return string|null The stored created_at, or null when nothing was written.
 */
function forecast_store(PDO $pdo, array $history, array $forecast, int $userId): ?string
{
    $historyJson = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $forecastJson = json_encode($forecast, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($historyJson === false || $forecastJson === false) {
        error_log('Forecast cache write skipped: payload could not be encoded.');

        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO forecast_cache
                (Horizon_Months, Data_Fingerprint, History_JSON, Forecast_JSON, Model, GeneratedBy_UserID)
             VALUES
                (:horizon, :fingerprint, :history, :forecast, :model, :user_id)'
        );
        $stmt->execute([
            'horizon'     => FORECAST_HORIZON_MONTHS,
            'fingerprint' => forecast_fingerprint($history),
            'history'     => $historyJson,
            'forecast'    => $forecastJson,
            'model'       => defined('GEMINI_MODEL') ? (string) GEMINI_MODEL : 'unknown',
            'user_id'     => $userId > 0 ? $userId : null,
        ]);

        $stored = $pdo->prepare('SELECT created_at FROM forecast_cache WHERE ForecastID = :id');
        $stored->execute(['id' => (int) $pdo->lastInsertId()]);
        $createdAt = $stored->fetchColumn();

        $pdo->exec(
            'DELETE FROM forecast_cache WHERE created_at < NOW() - INTERVAL ' . FORECAST_PRUNE_DAYS . ' DAY'
        );

        return $createdAt === false ? null : (string) $createdAt;
    } catch (PDOException $e) {
        error_log('Forecast cache write failed: ' . $e->getMessage());

        return null;
    }
}
