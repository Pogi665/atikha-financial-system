<?php
/** OCR evidence intake. No financial INSERTs belong in this service. */
if (is_file(__DIR__ . '/../config.php')) { require_once __DIR__ . '/../config.php'; }
require_once __DIR__ . '/journal.php';
require_once __DIR__ . '/receipts.php';
require_once __DIR__ . '/gemini_client.php';

const RECEIPT_SCHEMA_VERSION = 'journal_receipt_v1';

function receipt_schema_available(PDO $pdo): bool
{
    $s = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='receipts' AND COLUMN_NAME IN ('JournalEntryID','File_SHA256','Posted_File_SHA256','Upload_Key')");
    if ((int) $s->fetchColumn() !== 4) { return false; }
    return (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='receipt_ocr_attempts'")->fetchColumn();
}
function receipt_require_schema(PDO $pdo): void
{
    if (!receipt_schema_available($pdo)) { throw new JournalProblem('Receipt evidence is unavailable until migration 018 is deployed.', 503); }
}
function receipt_enabled(PDO $pdo): bool
{
    return defined('OCR_JOURNAL_ENABLED') && OCR_JOURNAL_ENABLED === true && receipt_schema_available($pdo);
}
function receipt_secret(): string
{
    if (!is_string($_SESSION['receipt_intake_secret'] ?? null)) {
        $_SESSION['receipt_intake_secret'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['receipt_intake_secret'];
}
function receipt_request_key(): string
{
    $nonce = bin2hex(random_bytes(16));
    return $nonce . substr(hash_hmac('sha256', $nonce . ':' . (int) ($_SESSION['UserID'] ?? 0), receipt_secret()), 0, 32);
}
function receipt_request_valid(string $key): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/D', $key) && is_string($_SESSION['receipt_intake_secret'] ?? null)
        && hash_equals(substr($key, 32), substr(hash_hmac('sha256', substr($key, 0, 32) . ':'
            . (int) ($_SESSION['UserID'] ?? 0), $_SESSION['receipt_intake_secret']), 0, 32));
}
function receipt_request_guard(PDO $pdo, int $userId, array $post): string
{
    if (!receipt_enabled($pdo)) { throw new JournalProblem('Receipt scanning is unavailable. Contact your administrator.', 503); }
    if ((int) ($_SESSION['UserID'] ?? 0) !== $userId || $userId <= 0) { throw new JournalProblem('Please sign in again.', 401); }
    if (!csrf_verify(is_string($post['csrf_token'] ?? null) ? $post['csrf_token'] : null)) { throw new JournalProblem('Reload the workspace and try again.', 400); }
    $key = journal_string($post, 'request_key');
    if (!receipt_request_valid($key)) { throw new JournalProblem('This upload request expired. Reload the workspace.', 400); }
    return $key;
}
function receipt_actor_lock(PDO $pdo, int $userId): void
{
    $s = $pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=? FOR UPDATE'); $s->execute([$userId]);
    $u = $s->fetch();
    if (!$u || $u['Role'] !== 'Admin' || (int) $u['Is_Active'] !== 1) { throw new JournalProblem('Scanning receipts requires an active Admin.', 403); }
}
function receipt_owned(PDO $pdo, int $id, int $userId, bool $lock = false): array
{
    $s = $pdo->prepare('SELECT * FROM Receipts WHERE ReceiptID=? AND UploadedBy_UserID=?' . ($lock ? ' FOR UPDATE' : ''));
    $s->execute([$id, $userId]); $r = $s->fetch();
    if (!$r || $r['ExpenseID'] !== null || $r['OCR_Status'] === 'Discarded' || !is_string($r['File_SHA256'])) {
        throw new JournalProblem('Receipt not found or unavailable.', 404);
    }
    return $r;
}
function receipt_file_verify(array $r): string
{
    $path = transaction_receipt_path($r['File_Path']);
    if (!$path || !hash_equals($r['File_SHA256'], (string) hash_file('sha256', $path))
        || filesize($path) !== (int) $r['File_Size']
        || (new finfo(FILEINFO_MIME_TYPE))->file($path) !== $r['Mime_Type']) {
        throw new JournalProblem('Receipt evidence is missing or has changed. Posting is blocked.', 409);
    }
    return $path;
}
function receipt_latest_attempt(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('SELECT * FROM receipt_ocr_attempts WHERE receipt_id=? ORDER BY id DESC LIMIT 1');
    $s->execute([$id]); return $s->fetch() ?: null;
}
function receipt_duplicate(PDO $pdo, string $hash): ?int
{
    $s = $pdo->prepare('SELECT JournalEntryID FROM Receipts WHERE Posted_File_SHA256=?');
    $s->execute([$hash]); $id = $s->fetchColumn(); return $id === false ? null : (int) $id;
}
function receipt_audit(PDO $pdo, int $userId, string $action, int $id, array $data): void
{
    if (!log_system_action($pdo, $userId, $action, 'OCR Evidence', $id, null, $data, 'ocr_expense.php?receipt=' . $id)) {
        throw new RuntimeException('Evidence audit unavailable.');
    }
}
function receipt_upload(PDO $pdo, int $userId, array $post, array $file): int
{
    $key = receipt_request_guard($pdo, $userId, $post);
    $stored = store_uploaded_receipt($file);
    if (!$stored['ok']) { throw new JournalProblem($stored['error'], 400); }
    $newPath = transaction_receipt_path($stored['path']);
    try {
        $pdo->beginTransaction(); receipt_actor_lock($pdo, $userId);
        $s = $pdo->prepare('SELECT ReceiptID,UploadedBy_UserID,File_SHA256 FROM Receipts WHERE Upload_Key=?'); $s->execute([$key]);
        if ($old = $s->fetch()) {
            if ((int) $old['UploadedBy_UserID'] !== $userId || !hash_equals((string) $old['File_SHA256'], $stored['sha256'])) {
                throw new JournalProblem('This upload request was used with different contents.', 409);
            }
            $pdo->commit();
            if ($newPath && !unlink($newPath)) { error_log('Could not remove redundant receipt upload.'); }
            return (int) $old['ReceiptID'];
        }
        if ($duplicate = receipt_duplicate($pdo, $stored['sha256'])) {
            throw new JournalProblem('This image is already evidence for journal #' . $duplicate . '. Open Journal History.', 409);
        }
        $s = $pdo->prepare('INSERT INTO Receipts (File_Path,Original_Filename,Mime_Type,File_Size,File_SHA256,Upload_Key,UploadedBy_UserID) VALUES (?,?,?,?,?,?,?)');
        $s->execute([$stored['path'], $stored['original'], $stored['mime'], $stored['size'], $stored['sha256'], $key, $userId]);
        $id = (int) $pdo->lastInsertId();
        // A recoverable manual proposal exists even if extraction cannot start
        // (rate limit, network failure, or interrupted AJAX response).
        $s=$pdo->prepare("INSERT INTO receipt_ocr_attempts (receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,error_message)
            VALUES (?,?,?,'Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,'manual',?,?)");
        $s->execute([$id,$userId,hash('sha256',$key.':manual'),$stored['sha256'],hash('sha256','manual'),RECEIPT_SCHEMA_VERSION,'No automatic extraction has completed. Manual journal entry is available.']);
        $pdo->prepare("UPDATE Receipts SET OCR_Status='Failed' WHERE ReceiptID=?")->execute([$id]);
        receipt_audit($pdo, $userId, AUDIT_ACTION_CREATE, $id, ['entity'=>'receipt','sha256'=>$stored['sha256'],'file_size'=>$stored['size'],'mime'=>$stored['mime'],'original_filename'=>$stored['original']]);
        $pdo->commit(); return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($newPath && !unlink($newPath)) { error_log('Could not remove orphan receipt upload.'); }
        throw $e;
    }
}
function receipt_extract(PDO $pdo, int $userId, int $id, array $post): array
{
    $key = receipt_request_guard($pdo, $userId, $post);
    $pdo->beginTransaction();
    try {
        receipt_actor_lock($pdo, $userId); $r = receipt_owned($pdo, $id, $userId, true);
        if ($r['JournalEntryID'] !== null) { throw new JournalProblem('Posted evidence cannot be reprocessed.', 409); }
        $path = receipt_file_verify($r);
        $s = $pdo->prepare('SELECT * FROM receipt_ocr_attempts WHERE request_key=?'); $s->execute([$key]);
        if ($existing = $s->fetch()) {
            if ((int) $existing['receipt_id'] !== $id || (int) $existing['requested_by_user_id'] !== $userId) { throw new JournalProblem('Request already used.', 409); }
            $pdo->commit(); return $existing;
        }
        $latest = receipt_latest_attempt($pdo, $id);
        if ($latest && $latest['state'] === 'Pending') {
            $started = new DateTimeImmutable($latest['started_at'], new DateTimeZone('UTC'));
            if (time() - $started->getTimestamp() < 90) { throw new JournalProblem('This receipt is already being read. Wait before retrying.', 409); }
            $s = $pdo->prepare("UPDATE receipt_ocr_attempts SET state='Failed',completed_at=UTC_TIMESTAMP(),error_message=? WHERE id=? AND state='Pending'");
            $s->execute(['Extraction timed out. Retry or enter the journal manually.', $latest['id']]);
            receipt_audit($pdo, $userId, AUDIT_ACTION_EDIT, $id, ['attempt_id'=>$latest['id'],'state'=>'Failed','reason'=>'expired processing lease']);
        }
        $s = $pdo->prepare("SELECT COUNT(*) FROM receipt_ocr_attempts WHERE requested_by_user_id=? AND model<>'manual' AND started_at>UTC_TIMESTAMP()-INTERVAL 5 MINUTE");
        $s->execute([$userId]);
        if ((int) $s->fetchColumn() >= 5) { throw new JournalProblem('Five extraction requests are allowed per five minutes. Please wait.', 429); }
        $accounts = array_values(array_filter(journal_accounts($pdo), static fn($a) => $a['Account_Type'] === 'Expense'));
        $catalog = hash('sha256', json_encode($accounts, JSON_THROW_ON_ERROR));
        $model = defined('GEMINI_MODEL') ? (string) GEMINI_MODEL : 'gemini-2.0-flash';
        $s = $pdo->prepare('INSERT INTO receipt_ocr_attempts (receipt_id,requested_by_user_id,request_key,started_at,source_hash,catalog_fingerprint,model,schema_version) VALUES (?,?,?,UTC_TIMESTAMP(),?,?,?,?)');
        $s->execute([$id,$userId,$key,$r['File_SHA256'],$catalog,$model,RECEIPT_SCHEMA_VERSION]); $attemptId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE Receipts SET OCR_Status='Pending' WHERE ReceiptID=?")->execute([$id]);
        receipt_audit($pdo, $userId, AUDIT_ACTION_CREATE, $id, ['attempt_id'=>$attemptId,'state'=>'Pending','schema_version'=>RECEIPT_SCHEMA_VERSION,'catalog_fingerprint'=>$catalog,'model'=>$model]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    // Never hold database/session locks during a network request.
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    try { $ocr = gemini_extract_receipt($path, $r['Mime_Type'], $accounts); }
    catch (Throwable $e) { error_log('OCR extraction failed: ' . $e->getMessage()); $ocr = ['ok'=>false,'data'=>null,'raw'=>'','error'=>'Extraction failed. Enter the details manually.']; }
    $error = $ocr['ok'] ? null : 'Automatic extraction was unavailable. Retry or enter the details manually.';
    $pdo->beginTransaction();
    try {
        receipt_actor_lock($pdo, $userId); $current = receipt_owned($pdo, $id, $userId, true);
        if ($current['JournalEntryID'] !== null) { throw new JournalProblem('Receipt is already posted.', 409); }
        receipt_file_verify($current);
        $latest = receipt_latest_attempt($pdo, $id);
        if (!$latest || (int) $latest['id'] !== $attemptId || $latest['state'] !== 'Pending') {
            throw new JournalProblem('A newer extraction replaced this request. Reload the receipt.', 409);
        }
        $state = $ocr['ok'] ? 'Processed' : 'Failed';
        $s = $pdo->prepare('UPDATE receipt_ocr_attempts SET state=?,completed_at=UTC_TIMESTAMP(),raw_response=?,normalized_json=?,error_message=? WHERE id=?');
        $s->execute([$state,$ocr['raw'] ?: null,$ocr['data'] === null ? null : json_encode($ocr['data'], JSON_THROW_ON_ERROR),$error,$attemptId]);
        $pdo->prepare('UPDATE Receipts SET OCR_Status=? WHERE ReceiptID=?')->execute([$state,$id]);
        receipt_audit($pdo, $userId, AUDIT_ACTION_EDIT, $id, ['attempt_id'=>$attemptId,'state'=>$state,'error'=>$error]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    return receipt_latest_attempt($pdo, $id);
}
function receipt_intake_signature(array $r, array $a): string
{
    $payload = [(int) $r['ReceiptID'],(int) $r['UploadedBy_UserID'],(int) $a['id'],$r['File_SHA256'],$a['schema_version'],hash('sha256', $a['normalized_json'] ?? '')];
    return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), receipt_secret());
}
function receipt_review(PDO $pdo, int $id, int $userId): array
{
    receipt_require_schema($pdo); $r = receipt_owned($pdo, $id, $userId);
    if ($r['JournalEntryID'] !== null) { throw new JournalProblem('Receipt already belongs to journal #' . (int) $r['JournalEntryID'] . '.', 409); }
    receipt_file_verify($r); $a = receipt_latest_attempt($pdo, $id);
    if (!$a || $a['state'] === 'Pending') { throw new JournalProblem('Extraction is not complete. Return to Scan Receipt to retry.', 409); }
    return ['receipt'=>$r,'attempt'=>$a,'data'=>$a['normalized_json'] === null ? [] : json_decode($a['normalized_json'],true,512,JSON_THROW_ON_ERROR),
        'intake_signature'=>receipt_intake_signature($r,$a)];
}
function receipt_post_context(array $post): ?array
{
    if (!array_key_exists('receipt_id', $post)) {
        foreach (['receipt_attempt_id','receipt_hash','intake_signature','confirmed_currency'] as $field) {
            if (array_key_exists($field, $post)) { throw new JournalProblem('Incomplete receipt context. Reload the journal.',400); }
        }
        return null;
    }
    $id = journal_id(journal_string($post, 'receipt_id'));
    $attempt = journal_id(journal_string($post, 'receipt_attempt_id'));
    $hash = journal_string($post, 'receipt_hash'); $signature = journal_string($post, 'intake_signature');
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $hash) || !preg_match('/\A[a-f0-9]{64}\z/D', $signature)
        || !is_string($_SESSION['receipt_intake_secret'] ?? null)) { throw new JournalProblem('Invalid receipt review context.',400); }
    if (journal_string($post, 'confirmed_currency') !== 'PHP') { throw new JournalProblem('Confirm PHP currency. Currency conversion is not supported.'); }
    // Signature is verified under the receipt lock. Its value is not part of the
    // canonical accounting hash; receipt identity/version and review choices are.
    return ['receipt_id'=>$id,'attempt_id'=>$attempt,'file_hash'=>$hash,'currency'=>'PHP'];
}
function receipt_lock_for_post(PDO $pdo, int $userId, array $context, string $signature): array
{
    receipt_require_schema($pdo); $r = receipt_owned($pdo,$context['receipt_id'],$userId,true);
    if ($r['JournalEntryID'] !== null) { throw new JournalProblem('This receipt has already been posted.',409); }
    receipt_file_verify($r); $a = receipt_latest_attempt($pdo,(int)$r['ReceiptID']);
    if (!$a || (int)$a['id'] !== $context['attempt_id'] || $a['state'] === 'Pending'
        || !hash_equals($r['File_SHA256'],$context['file_hash'])
        || !hash_equals($a['source_hash'],$r['File_SHA256'])
        || !hash_equals(receipt_intake_signature($r,$a),$signature)) {
        throw new JournalProblem('Receipt proposal changed or expired. Reload and review it again.',409);
    }
    if ($duplicate=receipt_duplicate($pdo,$r['File_SHA256'])) { throw new JournalProblem('Identical evidence is already attached to journal #'.$duplicate.'.',409); }
    return ['receipt'=>$r,'attempt'=>$a,'data'=>$a['normalized_json']===null?[]:json_decode($a['normalized_json'],true,512,JSON_THROW_ON_ERROR)];
}
function receipt_link_post(PDO $pdo, int $id, array $review, array $input): array
{
    $r=$review['receipt']; $a=$review['attempt'];
    $s=$pdo->prepare('UPDATE Receipts SET JournalEntryID=?,Posted_File_SHA256=File_SHA256 WHERE ReceiptID=? AND JournalEntryID IS NULL AND OCR_Status<>\'Discarded\'');
    $s->execute([$id,$r['ReceiptID']]);
    if ($s->rowCount()!==1) { throw new JournalProblem('Receipt could not be linked.',409); }
    $debits=0; foreach($input['lines'] as $line){$debits+=journal_amount($line['debit_amount']);}
    $d=$review['data']; $original=['entry_date'=>$d['transaction_date']??null,'reference'=>$d['reference']??null,'total_amount'=>$d['total_amount']??null,'debit_account_id'=>$d['suggested_debit_account_id']??null,'currency'=>$d['currency']??null];
    $debitAccounts=array_values(array_column(array_filter($input['lines'],static fn($l)=>journal_amount($l['debit_amount'])>0),'account_id'));
    $final=['entry_date'=>$input['entry_date'],'reference'=>$input['reference'],'total_amount'=>ledger_decimal($debits),'debit_account_id'=>$debitAccounts[0],'currency'=>'PHP'];
    $changes=[];foreach($original as $k=>$value){if($value!==$final[$k]){$changes[$k]=['extracted'=>$value,'posted'=>$final[$k]];}}
    return ['receipt_id'=>(int)$r['ReceiptID'],'attempt_id'=>(int)$a['id'],'file_hash'=>$r['File_SHA256'],
        'uploaded_by_user_id'=>(int)$r['UploadedBy_UserID'],'schema_version'=>$a['schema_version'],'model'=>$a['model'],
        'proposal'=>$d,'corrections'=>$changes,'final'=>$final,'final_debit_account_ids'=>$debitAccounts];
}
function receipt_discard(PDO $pdo,int $userId,int $id,array $post): void
{
    receipt_request_guard($pdo,$userId,$post); $pdo->beginTransaction();
    try {
        receipt_actor_lock($pdo,$userId); $r=receipt_owned($pdo,$id,$userId,true);
        if($r['JournalEntryID']!==null){throw new JournalProblem('Posted evidence cannot be discarded.',409);}
        $path=transaction_receipt_path($r['File_Path']); $a=receipt_latest_attempt($pdo,$id);
        if($a&&$a['state']==='Pending'){$pdo->prepare("UPDATE receipt_ocr_attempts SET state='Failed',completed_at=UTC_TIMESTAMP(),error_message='Receipt discarded' WHERE id=?")->execute([$a['id']]);}
        $pdo->prepare("UPDATE Receipts SET OCR_Status='Discarded' WHERE ReceiptID=?")->execute([$id]);
        receipt_audit($pdo,$userId,AUDIT_ACTION_DELETE,$id,['discarded'=>true,'image_removed'=>false,'deletion_pending'=>true]);
        $pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    $removed=$path!==null&&unlink($path);
    receipt_audit($pdo,$userId,AUDIT_ACTION_DELETE,$id,['discarded'=>true,'image_removed'=>$removed,'deletion_pending'=>false]);
    if(!$removed){throw new JournalProblem('Receipt discarded, but its file could not be removed. Contact your administrator.',500);}
}
/** No storage paths or raw model output are exposed in history responses. */
function receipt_journal_metadata(PDO $pdo,array $ids): array
{
    if(!$ids||!receipt_schema_available($pdo)){return [];}
    $out=[];
    foreach(array_chunk($ids,500) as $chunk){
        $s=$pdo->prepare("SELECT r.ReceiptID,r.JournalEntryID,r.Original_Filename,r.Mime_Type,r.File_Size,r.File_SHA256,u.FullName AS uploaded_by
            FROM Receipts r JOIN journal_entries j ON j.id=r.JournalEntryID AND j.status='posted'
            LEFT JOIN user_identities u ON u.UserID=r.UploadedBy_UserID WHERE r.JournalEntryID IN (".implode(',',array_fill(0,count($chunk),'?')).') ORDER BY r.ReceiptID');
        $s->execute(array_values($chunk));
        foreach($s as $r){$out[$r['JournalEntryID']][]=['id'=>(int)$r['ReceiptID'],'name'=>$r['Original_Filename']?:'Supporting receipt','mime'=>$r['Mime_Type'],
            'size'=>(int)$r['File_Size'],'sha256'=>$r['File_SHA256'],'uploaded_by'=>$r['uploaded_by'],
            'url'=>'receipt_attachment.php?receipt_id='.(int)$r['ReceiptID'].'&journal_id='.(int)$r['JournalEntryID']];}
    }
    return $out;
}
