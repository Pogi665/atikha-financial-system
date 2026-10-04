<?php
/** Frozen Trial Balance submissions. Legacy Reports are never rewritten. */
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/logger.php';
require_once __DIR__.'/notifications.php';
class ReportProblem extends RuntimeException
{
    public int $status;
    public function __construct(string $message,int $status=422) { parent::__construct($message); $this->status=$status; }
}
function report_snapshot_available(PDO $pdo): bool
{
    try { $pdo->query('SELECT id FROM trial_balance_snapshots LIMIT 0'); $pdo->query('SELECT snapshot_id FROM trial_balance_snapshot_lines LIMIT 0'); return true; }
    catch (PDOException $e) { return false; }
}
function report_submission_token(int $year,int $month,string $fingerprint): string
{
    if (!is_string($_SESSION['trial_balance_secret']??null)) { $_SESSION['trial_balance_secret']=bin2hex(random_bytes(32)); }
    $nonce=bin2hex(random_bytes(16));
    return $nonce.substr(hash_hmac('sha256',$nonce.':'.(int)($_SESSION['UserID']??0).":$year:$month:$fingerprint",$_SESSION['trial_balance_secret']),0,32);
}
function report_submission_valid(string $key,int $year,int $month,string $fingerprint): bool
{
    if (!preg_match('/\A[a-f0-9]{64}\z/',$key)||!preg_match('/\A[a-f0-9]{64}\z/',$fingerprint)||!is_string($_SESSION['trial_balance_secret']??null)) { return false; }
    $expected=substr(hash_hmac('sha256',substr($key,0,32).':'.(int)($_SESSION['UserID']??0).":$year:$month:$fingerprint",$_SESSION['trial_balance_secret']),0,32);
    return hash_equals($expected,substr($key,32));
}
function report_actor(PDO $pdo,int $userId,string $role): void
{
    if ($userId<=0||(int)($_SESSION['UserID']??0)!==$userId) { throw new ReportProblem('Your session expired.',401); }
    $s=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=? FOR UPDATE'); $s->execute([$userId]); $u=$s->fetch(PDO::FETCH_ASSOC);
    if (!$u||!(int)$u['Is_Active']) { throw new ReportProblem('Your account is unavailable.',401); }
    if ($u['Role']!==$role) { throw new ReportProblem('This review action is not permitted for your role.',403); }
}
function report_snapshot_submit(PDO $pdo,int $userId,array $input): array
{
    foreach (['csrf_token','report_year','report_month','submission_key','source_fingerprint'] as $key) {
        if (!isset($input[$key])||!is_string($input[$key])) { throw new ReportProblem('Invalid report submission.',400); }
    }
    if (!csrf_verify($input['csrf_token'])) { throw new ReportProblem('Reload the page and try again.',400); }
    if (!ctype_digit($input['report_year'])||!ctype_digit($input['report_month'])) { throw new ReportProblem('Invalid report period.',400); }
    $year=(int)$input['report_year']; $month=(int)$input['report_month']; $asOf=accounting_month_end($year,$month);
    $key=$input['submission_key']; $fingerprint=$input['source_fingerprint'];
    if (!report_submission_valid($key,$year,$month,$fingerprint)) { throw new ReportProblem('Invalid submission signature. Reload the report.',400); }
    if (!report_snapshot_available($pdo)) { throw new ReportProblem('Trial Balance reviews require migration 017.',503); }
    if ($pdo->inTransaction()) { throw new LogicException('Snapshot submission owns its transaction.'); }
    // Serialize revision allocation only; journal writers do not use this lock.
    $lock='atikha_tb_'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,16)."_$year"."_$month";
    $s=$pdo->prepare('SELECT GET_LOCK(?,5)'); $s->execute([$lock]);
    if ((int)$s->fetchColumn()!==1) { throw new ReportProblem('Another submission is in progress. Retry shortly.',409); }
    $duplicate=false;
    try {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction(); report_actor($pdo,$userId,'Admin');
        $s=$pdo->prepare('SELECT * FROM trial_balance_snapshots WHERE submission_key=?'); $s->execute([$key]); $old=$s->fetch(PDO::FETCH_ASSOC);
        if ($old) {
            if ((int)$old['submitted_by_user_id']!==$userId||(int)$old['report_year']!==$year||(int)$old['report_month']!==$month||!hash_equals($old['source_fingerprint'],$fingerprint)) {
                throw new ReportProblem('Submission key has already been used for different content.',409);
            }
            $id=(int)$old['id']; $duplicate=true;
        } else {
            $tb=accounting_trial_balance($pdo,$asOf);
            if (!hash_equals($tb['fingerprint'],$fingerprint)) { throw new ReportProblem('The report changed. Reload it before submitting.',409); }
            if ($tb['difference']!=='0.00'||$tb['invalid_journals']) { throw new ReportProblem('Posted journal integrity checks failed. This report cannot be submitted.'); }
            $s=$pdo->prepare('SELECT COALESCE(MAX(revision),0) FROM trial_balance_snapshots WHERE report_year=? AND report_month=?'); $s->execute([$year,$month]);
            $revision=(int)$s->fetchColumn()+1;
            $s=$pdo->prepare('INSERT INTO trial_balance_snapshots (report_year,report_month,revision,as_of,accounting_basis,captured_at,source_fingerprint,submission_key,submitted_by_user_id,total_debits,total_credits) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),?,?,?,?,?)');
            $s->execute([$year,$month,$revision,$asOf,ACCOUNTING_BASIS,$fingerprint,$key,$userId,$tb['total_debits'],$tb['total_credits']]); $id=(int)$pdo->lastInsertId();
            $s=$pdo->prepare('INSERT INTO trial_balance_snapshot_lines (snapshot_id,account_id,account_code,account_name,account_type,normal_balance,is_active,is_cash_account,debit_balance,credit_balance) VALUES (?,?,?,?,?,?,?,?,?,?)');
            foreach ($tb['rows'] as $r) { $s->execute([$id,$r['CategoryID'],$r['Account_Code'],$r['Name'],$r['Account_Type'],$r['Normal_Balance'],$r['Is_Active'],$r['Is_Cash_Account'],$r['debit_balance'],$r['credit_balance']]); }
            if (!log_system_action($pdo,$userId,AUDIT_ACTION_REVIEW_REQUEST,'trial_balance_snapshots',$id,null,['year'=>$year,'month'=>$month,'revision'=>$revision,'source_fingerprint'=>$fingerprint])) { throw new RuntimeException('Snapshot audit insert failed.'); }
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    finally { $s=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $s->execute([$lock]); }
    $warning='';
    if (!$duplicate&&!notification_notify_management($pdo,"Trial Balance $year-".str_pad((string)$month,2,'0',STR_PAD_LEFT)." revision $revision submitted for review.",'reports.php?snapshot_id='.$id)) {
        $warning='Snapshot saved, but its notification could not be delivered. It remains available in the review queue.';
    }
    return ['id'=>$id,'duplicate'=>$duplicate,'warning'=>$warning];
}
function report_snapshot_review(PDO $pdo,int $userId,array $input): array
{
    foreach (['csrf_token','entity_id'] as $key) { if (!is_string($input[$key]??null)) { throw new ReportProblem('Invalid review request.',400); } }
    if (!csrf_verify($input['csrf_token'])) { throw new ReportProblem('Reload the page and try again.',400); }
    if (!ctype_digit($input['entity_id'])||(int)$input['entity_id']<1||(int)$input['entity_id']>4294967295) { throw new ReportProblem('Invalid snapshot.',400); }
    if (isset($input['review_notes'])&&!is_string($input['review_notes'])) { throw new ReportProblem('Invalid review notes.',400); }
    $notes=trim($input['review_notes']??''); if (mb_strlen($notes)>10000||!mb_check_encoding($notes,'UTF-8')) { throw new ReportProblem('Review notes must be valid text of at most 10,000 characters.',400); }
    $id=(int)$input['entity_id']; $duplicate=false;
    if ($pdo->inTransaction()) { throw new LogicException('Snapshot review owns its transaction.'); }
    try {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction(); report_actor($pdo,$userId,'Management');
        $s=$pdo->prepare('SELECT * FROM trial_balance_snapshots WHERE id=? FOR UPDATE'); $s->execute([$id]); $old=$s->fetch(PDO::FETCH_ASSOC);
        if (!$old) { throw new ReportProblem('Snapshot not found.',404); }
        if ($old['review_status']==='Reviewed') { $duplicate=true; }
        else {
            $loaded=report_snapshot_load_id($pdo,$id);
            if ($loaded['difference']!=='0.00'||$loaded['invalid_journals']) { throw new ReportProblem('Snapshot integrity checks failed.'); }
            $s=$pdo->prepare("UPDATE trial_balance_snapshots SET review_status='Reviewed',reviewed_by_user_id=?,reviewed_at=UTC_TIMESTAMP(),review_notes=? WHERE id=? AND review_status='Requested'");
            $s->execute([$userId,$notes===''?null:$notes,$id]);
            if (!log_system_action($pdo,$userId,AUDIT_ACTION_REVIEW_COMPLETE,'trial_balance_snapshots',$id,['review_status'=>'Requested'],['review_status'=>'Reviewed','review_notes'=>$notes])) { throw new RuntimeException('Review audit insert failed.'); }
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    $warning='';
    if (!$duplicate&&!notification_create($pdo,(int)$old['submitted_by_user_id'],null,'Your Trial Balance revision '.$old['revision'].' has been reviewed.','reports.php?snapshot_id='.$id)) { $warning='Review saved, but its notification could not be delivered.'; }
    return ['id'=>$id,'duplicate'=>$duplicate,'warning'=>$warning];
}
function report_snapshot_load_id(PDO $pdo,int $id): ?array
{
    return accounting_read($pdo,function () use ($pdo,$id) {
        $s=$pdo->prepare('SELECT s.*,u.FullName AS submitter_name,v.FullName AS reviewer_name FROM trial_balance_snapshots s JOIN user_identities u ON u.UserID=s.submitted_by_user_id LEFT JOIN user_identities v ON v.UserID=s.reviewed_by_user_id WHERE s.id=?');
        $s->execute([$id]); $header=$s->fetch(PDO::FETCH_ASSOC); if (!$header) { return null; }
        $s=$pdo->prepare("SELECT account_id AS CategoryID,account_code AS Account_Code,account_name AS Name,account_type AS Account_Type,normal_balance AS Normal_Balance,is_active AS Is_Active,is_cash_account AS Is_Cash_Account,debit_balance,credit_balance FROM trial_balance_snapshot_lines WHERE snapshot_id=? ORDER BY FIELD(account_type,'Asset','Liability','Equity','Income','Expense'),account_code IS NULL,account_code,account_name,account_id");
        $s->execute([$id]); $rows=$s->fetchAll(PDO::FETCH_ASSOC); $d=$c=0;
        foreach ($rows as &$r) { $dc=accounting_cents($r['debit_balance']); $cc=accounting_cents($r['credit_balance']); $r['net']=accounting_decimal(accounting_add($dc,-$cc)); $d=accounting_add($d,$dc); $c=accounting_add($c,$cc); } unset($r);
        $bad=accounting_decimal($d)!==accounting_decimal(accounting_cents($header['total_debits']))||accounting_decimal($c)!==accounting_decimal(accounting_cents($header['total_credits']))||!$rows;
        return ['header'=>$header,'rows'=>$rows,'as_of'=>$header['as_of'],'basis'=>$header['accounting_basis'],
            'fingerprint'=>$header['source_fingerprint'],'total_debits'=>accounting_decimal($d),'total_credits'=>accounting_decimal($c),
            'difference'=>accounting_decimal(accounting_add($d,-$c)),'invalid_journals'=>$bad?['snapshot']:[]];
    });
}
