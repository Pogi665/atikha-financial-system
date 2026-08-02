<?php

/**
 * Read side of the audit trail.
 *
 * Every SELECT against audit_logs lives here so the page, the CSV export and
 * the AI endpoint all agree on what "the currently filtered logs" means.
 * Nothing in this file writes: the table is append-only.
 */

const AUDIT_PAGE_LIMIT = 500;
const AUDIT_EXPORT_LIMIT = 5000;
const AUDIT_SCAN_LIMIT = 100;
const AUDIT_SUMMARY_LIMIT = 200;

/**
 * Whitelist the filter inputs. Unknown or malformed values are dropped rather
 * than corrected, so a hand-edited URL cannot widen the query.
 *
 * @param array<string, mixed> $request
 *
 * @return array{q: string, user_id: int, module: string, action_type: string, date_from: string, date_to: string}
 */
function audit_filters_from_request(array $request): array
{
    $text = static function ($value): string {
        return is_string($value) ? trim($value) : '';
    };

    $date = static function ($value): string {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $parsed = DateTime::createFromFormat('Y-m-d', $value);

        return $parsed instanceof DateTime ? $parsed->format('Y-m-d') : '';
    };

    return [
        'q'           => substr($text($request['q'] ?? ''), 0, 100),
        'user_id'     => (int) ($request['user_id'] ?? 0),
        'module'      => substr($text($request['module'] ?? ''), 0, 50),
        'action_type' => strtoupper(substr($text($request['action_type'] ?? ''), 0, 30)),
        'date_from'   => $date($request['date_from'] ?? ''),
        'date_to'     => $date($request['date_to'] ?? ''),
    ];
}

function audit_filters_are_active(array $filters): bool
{
    foreach ($filters as $value) {
        if ($value !== '' && $value !== 0) {
            return true;
        }
    }

    return false;
}

/**
 * Human-readable description of the active filters, used in the AI prompts and
 * the CSV header row.
 */
function audit_filters_describe(array $filters, array $userNames = []): string
{
    $parts = [];

    if ($filters['q'] !== '') {
        $parts[] = 'search "' . $filters['q'] . '"';
    }
    if ($filters['user_id'] > 0) {
        $parts[] = 'user ' . ($userNames[$filters['user_id']] ?? ('#' . $filters['user_id']));
    }
    if ($filters['module'] !== '') {
        $parts[] = 'module ' . $filters['module'];
    }
    if ($filters['action_type'] !== '') {
        $parts[] = 'action ' . $filters['action_type'];
    }
    if ($filters['date_from'] !== '') {
        $parts[] = 'from ' . $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $parts[] = 'to ' . $filters['date_to'];
    }

    return $parts === [] ? 'no filters (all activity)' : implode(', ', $parts);
}

/**
 * Newest-first log rows joined to the acting user.
 *
 * @return array<int, array<string, mixed>>
 */
function audit_fetch_logs(PDO $pdo, array $filters, int $limit = AUDIT_PAGE_LIMIT): array
{
    $where = [];
    $params = [];

    if ($filters['user_id'] > 0) {
        $where[] = 'a.user_id = :user_id';
        $params['user_id'] = $filters['user_id'];
    }
    if ($filters['module'] !== '') {
        $where[] = 'a.module = :module';
        $params['module'] = $filters['module'];
    }
    if ($filters['action_type'] !== '') {
        $where[] = 'a.action_type = :action_type';
        $params['action_type'] = $filters['action_type'];
    }
    if ($filters['date_from'] !== '') {
        $where[] = 'a.created_at >= :date_from';
        $params['date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if ($filters['date_to'] !== '') {
        $where[] = 'a.created_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    if ($filters['q'] !== '') {
        $where[] = '(u.FullName LIKE :q OR a.module LIKE :q OR a.action_type LIKE :q'
            . ' OR a.ip_address LIKE :q OR a.old_values LIKE :q OR a.new_values LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sql = 'SELECT a.id, a.user_id, a.action_type, a.module, a.record_id,
                   a.old_values, a.new_values, a.source_link, a.ip_address, a.created_at,
                   u.FullName, u.Role
            FROM audit_logs a
            INNER JOIN Users u ON u.UserID = a.user_id';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . max(1, $limit);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Distinct values for the filter bar dropdowns.
 *
 * @return array{users: array<int, array{UserID: int, FullName: string}>, modules: string[], actions: string[]}
 */
function audit_filter_options(PDO $pdo): array
{
    $options = ['users' => [], 'modules' => [], 'actions' => []];

    try {
        $stmt = $pdo->query(
            'SELECT DISTINCT u.UserID, u.FullName
             FROM audit_logs a
             INNER JOIN Users u ON u.UserID = a.user_id
             ORDER BY u.FullName ASC'
        );
        $options['users'] = $stmt->fetchAll();

        $stmt = $pdo->query('SELECT DISTINCT module FROM audit_logs ORDER BY module ASC');
        $options['modules'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->query('SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type ASC');
        $options['actions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Failed to load audit filter options: ' . $e->getMessage());
    }

    return $options;
}

function audit_total_count(PDO $pdo): int
{
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    } catch (PDOException $e) {
        error_log('Failed to count audit logs: ' . $e->getMessage());

        return 0;
    }
}

/**
 * Compact a row set into the shape the Gemini prompts consume.
 *
 * Drops the fields the model has no use for and truncates the JSON blobs, which
 * keeps a 200-row payload comfortably small.
 *
 * @param array<int, array<string, mixed>> $logs
 *
 * @return array<int, array<string, mixed>>
 */
function audit_logs_for_ai(array $logs, int $valueLimit = 400): array
{
    $compact = [];

    foreach ($logs as $log) {
        $clip = static function ($value) use ($valueLimit) {
            if ($value === null || $value === '') {
                return null;
            }

            return substr((string) $value, 0, $valueLimit);
        };

        $compact[] = [
            'id'         => (int) $log['id'],
            'at'         => (string) $log['created_at'],
            'user'       => (string) $log['FullName'],
            'role'       => (string) $log['Role'],
            'action'     => (string) $log['action_type'],
            'module'     => (string) $log['module'],
            'record_id'  => $log['record_id'] !== null ? (int) $log['record_id'] : null,
            'ip'         => (string) $log['ip_address'],
            'old_values' => $clip($log['old_values']),
            'new_values' => $clip($log['new_values']),
        ];
    }

    return $compact;
}
