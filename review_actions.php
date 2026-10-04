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

const REVIEW_WORKSPACE_ROLES = ['Admin'];
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

require_once __DIR__ . '/includes/user_session.php';
try {
    $sessionValid = user_session_validate($pdo);
} catch (Throwable $e) {
    error_log('Session validation failed: ' . $e->getMessage());
    review_respond(false, null, 'Account access is temporarily unavailable. Please try again.', 503);
}

if (!$sessionValid) {
    review_respond(false, null, 'Your session expired. Please sign in again.', 401);
}

if (!csrf_verify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
    review_respond(false, null, 'Your session expired. Please reload the page and try again.', 400);
}

$userId = (int) $_SESSION['UserID'];
$role = (string) ($_SESSION['Role'] ?? '');
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$entityType = is_string($_POST['entity_type'] ?? null) ? $_POST['entity_type'] : '';
if (in_array($entityType, ['fund', 'expense'], true)) {
    review_respond(false, null, 'Legacy transaction reviews have been retired. Use General Journal.', 410);
}
if ($entityType === 'report') {
    review_respond(false, null, 'Legacy summary reports are read-only. Submit a frozen Trial Balance revision.', 410);
}
if ($entityType === 'trial_balance') {
    try {
        if ($action === 'send_for_review') {
            $result = report_snapshot_submit($pdo, $userId, $_POST);
            $status = 'Requested';
        } elseif ($action === 'mark_reviewed') {
            $result = report_snapshot_review($pdo, $userId, $_POST);
            $status = 'Reviewed';
        } else { review_respond(false, null, 'Unknown review action.', 400); }
        review_respond(true, ['entity_type'=>'trial_balance','entity_id'=>$result['id'],
            'review_status'=>$status,'duplicate'=>$result['duplicate'],'warning'=>$result['warning'],
            'snapshot_url'=>'reports.php?snapshot_id='.$result['id']], '');
    } catch (ReportProblem $e) { review_respond(false, null, $e->getMessage(), $e->status); }
    catch (InvalidArgumentException $e) { review_respond(false, null, $e->getMessage(), 400); }
    catch (Throwable $e) { error_log('Trial Balance review failed: '.$e->getMessage()); review_respond(false, null, 'Unable to save the Trial Balance review. No partial changes were saved.', 503); }
}
if ($action !== 'mark_reviewed' || $entityType !== 'board') {
    review_respond(false, null, 'Unknown review action or entity.', 400);
}
if (!in_array($role, REVIEW_MANAGEMENT_ROLES, true)) {
    review_respond(false, null, 'Marking items as reviewed is restricted to Management.', 403);
}
if (!is_string($_POST['entity_id'] ?? null) || !ctype_digit($_POST['entity_id']) || (int)$_POST['entity_id'] < 1
    || (isset($_POST['review_notes']) && !is_string($_POST['review_notes']))) {
    review_respond(false, null, 'Invalid review record or notes.', 400);
}
$entityId=(int)$_POST['entity_id'];
$reviewNotes=trim($_POST['review_notes']??'');
$notesValue=$reviewNotes!==''?$reviewNotes:null;
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
