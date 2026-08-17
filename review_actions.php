<?php

/**
 * Review workflow JSON endpoint: send for review / mark as reviewed.
 */

session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/report_snapshots.php';

header('Content-Type: application/json; charset=utf-8');

const REVIEW_WORKSPACE_ROLES = ['Staff', 'Admin'];
const REVIEW_MANAGEMENT_ROLES = ['Management'];

/**
 * @param array<string, mixed>|null $data
 */
function review_respond(bool $ok, ?array $data, string $error, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    review_respond(false, null, 'This endpoint accepts POST requests only.', 405);
}

if (empty($_SESSION['UserID'])) {
    review_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    review_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

$userId = (int) $_SESSION['UserID'];
$role = (string) ($_SESSION['Role'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$entityType = (string) ($_POST['entity_type'] ?? '');
$entityId = (int) ($_POST['entity_id'] ?? 0);
$reviewNotes = isset($_POST['review_notes']) ? trim((string) $_POST['review_notes']) : '';
$reportMonth = (int) ($_POST['report_month'] ?? 0);
$reportYear = (int) ($_POST['report_year'] ?? 0);

if ($action === 'send_for_review') {
    if (!in_array($role, REVIEW_WORKSPACE_ROLES, true)) {
        review_respond(false, null, 'Sending items for review is restricted to Staff and Administrators.', 403);
    }

    if ($entityType === 'report') {
        if ($reportMonth < 1 || $reportMonth > 12 || $reportYear < 2000) {
            review_respond(false, null, 'Invalid report period.', 400);
        }

        $result = report_snapshot_upsert($pdo, $reportMonth, $reportYear, $userId);
        if (!$result['ok']) {
            review_respond(false, null, $result['error'], 500);
        }

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_REQUEST,
            'Reports',
            $result['report_id'],
            null,
            ['report_month' => $reportMonth, 'report_year' => $reportYear]
        );

        review_respond(true, [
            'entity_type'   => 'report',
            'entity_id'     => $result['report_id'],
            'review_status' => 'Requested',
        ], '');
    }

    if ($entityId <= 0) {
        review_respond(false, null, 'Invalid record.', 400);
    }

    if ($entityType === 'expense') {
        $stmt = $pdo->prepare(
            'SELECT ExpenseID, Payee, Category, Amount, Review_Status
             FROM Expenses WHERE ExpenseID = :id'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if ($row === false) {
            review_respond(false, null, 'That expense could not be found.', 404);
        }

        $update = $pdo->prepare(
            'UPDATE Expenses SET Review_Status = :status, Review_Notes = NULL WHERE ExpenseID = :id'
        );
        $update->execute(['status' => 'Requested', 'id' => $entityId]);

        notification_notify_management(
            $pdo,
            'Expense review requested: ' . $row['Payee'] . ' (₱' . number_format((float) $row['Amount'], 2) . ').',
            'management_reviews.php?entity=expense&id=' . $entityId
        );

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_REQUEST,
            'Expenses',
            $entityId,
            ['review_status' => $row['Review_Status']],
            ['review_status' => 'Requested']
        );

        review_respond(true, ['entity_type' => 'expense', 'entity_id' => $entityId, 'review_status' => 'Requested'], '');
    }

    if ($entityType === 'fund') {
        $stmt = $pdo->prepare(
            'SELECT FundID, Source_Donor, Category, Amount, Review_Status
             FROM Incoming_Funds WHERE FundID = :id'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if ($row === false) {
            review_respond(false, null, 'That incoming fund could not be found.', 404);
        }

        $update = $pdo->prepare(
            'UPDATE Incoming_Funds SET Review_Status = :status, Review_Notes = NULL WHERE FundID = :id'
        );
        $update->execute(['status' => 'Requested', 'id' => $entityId]);

        notification_notify_management(
            $pdo,
            'Incoming fund review requested: ' . $row['Source_Donor'] . ' (₱' . number_format((float) $row['Amount'], 2) . ').',
            'management_reviews.php?entity=fund&id=' . $entityId
        );

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_REQUEST,
            'Incoming_Funds',
            $entityId,
            ['review_status' => $row['Review_Status']],
            ['review_status' => 'Requested']
        );

        review_respond(true, ['entity_type' => 'fund', 'entity_id' => $entityId, 'review_status' => 'Requested'], '');
    }

    review_respond(false, null, 'Unknown entity type.', 400);
}

if ($action === 'mark_reviewed') {
    if (!in_array($role, REVIEW_MANAGEMENT_ROLES, true)) {
        review_respond(false, null, 'Marking items as reviewed is restricted to Management.', 403);
    }

    if ($entityId <= 0) {
        review_respond(false, null, 'Invalid record.', 400);
    }

    $notesValue = $reviewNotes !== '' ? $reviewNotes : null;

    if ($entityType === 'expense') {
        $stmt = $pdo->prepare(
            'SELECT ExpenseID, Payee, RecordedBy_UserID, Review_Status
             FROM Expenses WHERE ExpenseID = :id'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if ($row === false) {
            review_respond(false, null, 'That expense could not be found.', 404);
        }

        $update = $pdo->prepare(
            'UPDATE Expenses
             SET Review_Status = :status, Review_Notes = :notes
             WHERE ExpenseID = :id'
        );
        $update->execute(['status' => 'Reviewed', 'notes' => $notesValue, 'id' => $entityId]);

        notification_create(
            $pdo,
            (int) $row['RecordedBy_UserID'],
            null,
            'Your expense "' . $row['Payee'] . '" has been reviewed by Management.',
            'expenses.php'
        );

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_COMPLETE,
            'Expenses',
            $entityId,
            ['review_status' => $row['Review_Status']],
            ['review_status' => 'Reviewed', 'review_notes' => $notesValue]
        );

        review_respond(true, ['entity_type' => 'expense', 'entity_id' => $entityId, 'review_status' => 'Reviewed'], '');
    }

    if ($entityType === 'fund') {
        $stmt = $pdo->prepare(
            'SELECT FundID, Source_Donor, RecordedBy_UserID, Review_Status
             FROM Incoming_Funds WHERE FundID = :id'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if ($row === false) {
            review_respond(false, null, 'That incoming fund could not be found.', 404);
        }

        $update = $pdo->prepare(
            'UPDATE Incoming_Funds
             SET Review_Status = :status, Review_Notes = :notes
             WHERE FundID = :id'
        );
        $update->execute(['status' => 'Reviewed', 'notes' => $notesValue, 'id' => $entityId]);

        notification_create(
            $pdo,
            (int) $row['RecordedBy_UserID'],
            null,
            'Your incoming fund "' . $row['Source_Donor'] . '" has been reviewed by Management.',
            'funds.php'
        );

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_COMPLETE,
            'Incoming_Funds',
            $entityId,
            ['review_status' => $row['Review_Status']],
            ['review_status' => 'Reviewed', 'review_notes' => $notesValue]
        );

        review_respond(true, ['entity_type' => 'fund', 'entity_id' => $entityId, 'review_status' => 'Reviewed'], '');
    }

    if ($entityType === 'report') {
        $stmt = $pdo->prepare(
            'SELECT ReportID, Report_Month, Report_Year, SubmittedBy_UserID, Review_Status
             FROM Reports WHERE ReportID = :id'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if ($row === false) {
            review_respond(false, null, 'That report could not be found.', 404);
        }

        $update = $pdo->prepare(
            'UPDATE Reports
             SET Review_Status = :status, Review_Notes = :notes
             WHERE ReportID = :id'
        );
        $update->execute(['status' => 'Reviewed', 'notes' => $notesValue, 'id' => $entityId]);

        $periodLabel = date('F Y', mktime(0, 0, 0, (int) $row['Report_Month'], 1, (int) $row['Report_Year']));
        notification_create(
            $pdo,
            (int) $row['SubmittedBy_UserID'],
            null,
            'Your monthly report for ' . $periodLabel . ' has been reviewed by Management.',
            'reports.php?month=' . str_pad((string) $row['Report_Month'], 2, '0', STR_PAD_LEFT) . '&year=' . $row['Report_Year']
        );

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_COMPLETE,
            'Reports',
            $entityId,
            ['review_status' => $row['Review_Status']],
            ['review_status' => 'Reviewed', 'review_notes' => $notesValue]
        );

        review_respond(true, ['entity_type' => 'report', 'entity_id' => $entityId, 'review_status' => 'Reviewed'], '');
    }

    if ($entityType === 'board') {
        $stmt = $pdo->prepare(
            'SELECT CommunicationID, Subject, Sender_UserID, Review_Status
             FROM Board_Communications WHERE CommunicationID = :id'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if ($row === false) {
            review_respond(false, null, 'That message could not be found.', 404);
        }

        $update = $pdo->prepare(
            'UPDATE Board_Communications
             SET Review_Status = :status
             WHERE CommunicationID = :id'
        );
        $update->execute(['status' => 'Reviewed', 'id' => $entityId]);

        notification_create(
            $pdo,
            (int) $row['Sender_UserID'],
            null,
            'Your board message "' . $row['Subject'] . '" has been reviewed by Management.',
            'board_messages.php'
        );

        log_system_action(
            $pdo,
            $userId,
            AUDIT_ACTION_REVIEW_COMPLETE,
            'Board_Communications',
            $entityId,
            ['review_status' => $row['Review_Status']],
            ['review_status' => 'Reviewed', 'review_notes' => $notesValue]
        );

        review_respond(true, ['entity_type' => 'board', 'entity_id' => $entityId, 'review_status' => 'Reviewed'], '');
    }

    review_respond(false, null, 'Unknown entity type.', 400);
}

review_respond(false, null, 'Unknown action.', 400);
