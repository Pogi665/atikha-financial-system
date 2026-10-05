<?php
/** Shared posted-journal read model. Financial values stay exact until charting. */
const ACCOUNTING_TYPES = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
const ACCOUNTING_BASIS = 'posted_journal_v1';

function accounting_today(?DateTimeImmutable $instant = null): string
{
    return ($instant ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
}
function accounting_date(string $date): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Manila'));
    if (!$d || $d->format('Y-m-d') !== $date || $date < '1000-01-01' || $date > '9998-12-31') {
        throw new InvalidArgumentException('Invalid accounting date.');
    }
    return $date;
}
function accounting_month_end(int $year, int $month): string
{
    if ($year < 1000 || $year > 9998 || $month < 1 || $month > 12) { throw new InvalidArgumentException('Invalid report period.'); }
    return (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('Asia/Manila')))->format('Y-m-t');
}
function accounting_next_day(string $date): string
{
    return (new DateTimeImmutable(accounting_date($date)))->modify('+1 day')->format('Y-m-d');
}
function accounting_cents(string $amount): int
{
    if (PHP_INT_SIZE < 8 || !preg_match('/\A(-?)([0-9]+)(?:\.([0-9]{1,2}))?\z/', $amount, $m)) {
        throw new UnexpectedValueException('Invalid accounting amount.');
    }
    $digits = ltrim($m[2] . str_pad($m[3] ?? '', 2, '0'), '0');
    $digits = $digits === '' ? '0' : $digits;
    $limit = (string) PHP_INT_MAX;
    if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
        throw new OverflowException('Accounting total exceeds the supported centavo range.');
    }
    return ($m[1] === '-' ? -1 : 1) * (int) $digits;
}
function accounting_add(int $a, int $b): int
{
    if (($b > 0 && $a > PHP_INT_MAX - $b) || ($b < 0 && $a < -PHP_INT_MAX - $b)) {
        throw new OverflowException('Accounting total exceeds the supported centavo range.');
    }
    return $a + $b;
}
function accounting_decimal(int $cents): string
{
    if ($cents === PHP_INT_MIN) { throw new OverflowException('Unsupported accounting amount.'); }
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}
function accounting_money(string $amount): string
{
    $c = accounting_cents($amount);
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) intdiv(abs($c), 100));
    return ($c < 0 ? '-' : '') . '₱' . $whole . '.' . str_pad((string) (abs($c) % 100), 2, '0', STR_PAD_LEFT);
}
function accounting_read(PDO $pdo, callable $operation)
{
    $own = !$pdo->inTransaction();
    if ($own) { $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction(); }
    try {
        $result = $operation();
        if ($own) { $pdo->commit(); }
        return $result;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
function accounting_accounts(PDO $pdo): array
{
    return $pdo->query("SELECT CategoryID,Name,Account_Code,Account_Type,Normal_Balance,Is_Cash_Account,Is_Active
        FROM Categories ORDER BY FIELD(Account_Type,'Asset','Liability','Equity','Income','Expense'),
        Account_Code IS NULL,Account_Code,Name,CategoryID")->fetchAll(PDO::FETCH_ASSOC);
}
function accounting_integrity(PDO $pdo, string $asOf): array
{
    $s = $pdo->prepare("SELECT j.id FROM journal_entries j LEFT JOIN journal_entry_lines l ON l.journal_entry_id=j.id
        WHERE j.status='posted' AND j.entry_date < :end GROUP BY j.id
        HAVING COUNT(l.id)<2 OR COALESCE(SUM(l.debit_amount),0)<>COALESCE(SUM(l.credit_amount),0)
        OR COALESCE(SUM(l.debit_amount),0)<=0
        OR SUM(CASE WHEN (l.debit_amount>0 AND l.credit_amount=0) OR (l.credit_amount>0 AND l.debit_amount=0) THEN 0 ELSE 1 END)>0
        ORDER BY j.id");
    $s->execute(['end' => accounting_next_day($asOf)]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}
function accounting_trial_balance(PDO $pdo, string $asOf): array
{
    accounting_date($asOf);
    return accounting_read($pdo, function () use ($pdo, $asOf) {
        $s = $pdo->prepare("WITH account_totals AS (
            SELECT l.account_id,SUM(l.debit_amount-l.credit_amount) AS net
            FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id
            WHERE j.status='posted' AND j.entry_date < :end GROUP BY l.account_id
        ) SELECT c.CategoryID,c.Name,c.Account_Code,c.Account_Type,c.Normal_Balance,c.Is_Active,c.Is_Cash_Account,
            COALESCE(t.net,0) AS net FROM Categories c LEFT JOIN account_totals t ON t.account_id=c.CategoryID
        ORDER BY FIELD(c.Account_Type,'Asset','Liability','Equity','Income','Expense'),c.Account_Code IS NULL,c.Account_Code,c.Name,c.CategoryID");
        $s->execute(['end' => accounting_next_day($asOf)]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC); $debit = $credit = 0;
        foreach ($rows as &$row) {
            if (!in_array($row['Account_Type'], ACCOUNTING_TYPES, true)) { throw new UnexpectedValueException('Unclassified account.'); }
            $net = accounting_cents((string) $row['net']);
            $row['net'] = accounting_decimal($net);
            $row['debit_balance'] = accounting_decimal(max(0, $net));
            $row['credit_balance'] = accounting_decimal(max(0, -$net));
            $debit = accounting_add($debit, max(0, $net)); $credit = accounting_add($credit, max(0, -$net));
        }
        unset($row);
        // Hash the complete source, not just net balances: offsetting postings must invalidate a submission.
        $hash = hash_init('sha256');
        hash_update($hash, json_encode([ACCOUNTING_BASIS,$asOf,$rows], JSON_THROW_ON_ERROR));
        $s = $pdo->prepare("SELECT j.id,j.entry_date,j.reference,j.description,j.posted_by_user_id,
            l.id AS line_id,l.account_id,l.debit_amount,l.credit_amount,l.fund_project_id
            FROM journal_entries j LEFT JOIN journal_entry_lines l ON l.journal_entry_id=j.id
            WHERE j.status='posted' AND j.entry_date < :end ORDER BY j.id,l.id");
        $s->execute(['end' => accounting_next_day($asOf)]);
        while ($row = $s->fetch(PDO::FETCH_ASSOC)) { hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)); }
        return ['as_of'=>$asOf,'basis'=>ACCOUNTING_BASIS,'rows'=>$rows,'total_debits'=>accounting_decimal($debit),
            'total_credits'=>accounting_decimal($credit),'difference'=>accounting_decimal(accounting_add($debit,-$credit)),
            'invalid_journals'=>accounting_integrity($pdo,$asOf),'fingerprint'=>hash_final($hash)];
    });
}
function accounting_kpis(PDO $pdo, string $asOf): array
{
    $s = $pdo->prepare("SELECT c.Account_Type,SUM(l.debit_amount-l.credit_amount) AS net
        FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id JOIN Categories c ON c.CategoryID=l.account_id
        WHERE j.status='posted' AND j.entry_date < :end GROUP BY c.Account_Type");
    $s->execute(['end'=>accounting_next_day($asOf)]);
    $result = ['assets'=>'0.00','income'=>'0.00','expenses'=>'0.00'];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $key = ['Asset'=>'assets','Income'=>'income','Expense'=>'expenses'][$r['Account_Type']] ?? null;
        if ($key) { $n=accounting_cents((string)$r['net']); $result[$key]=accounting_decimal($r['Account_Type']==='Income' ? -$n : $n); }
    }
    return $result;
}
function accounting_months(int $count, ?string $today = null): array
{
    if ($count < 1 || $count > 120) { throw new InvalidArgumentException('Invalid month count.'); }
    $cursor = new DateTimeImmutable(substr(accounting_date($today ?? accounting_today()),0,7).'-01');
    $cursor=$cursor->modify('-'.$count.' months'); $out=[];
    for ($i=0;$i<$count;$i++) { $out[]=$cursor->format('Y-m'); $cursor=$cursor->modify('+1 month'); }
    return $out;
}
function accounting_monthly(PDO $pdo, array $months): array
{
    if (!$months) { throw new InvalidArgumentException('No months selected.'); }
    foreach ($months as $i=>$month) {
        accounting_date($month.'-01');
        if ($i && (new DateTimeImmutable($months[$i-1].'-01'))->modify('+1 month')->format('Y-m')!==$month) {
            throw new InvalidArgumentException('Months must be consecutive.');
        }
    }
    return accounting_read($pdo,function () use ($pdo,$months) {
        $start=$months[0].'-01'; $end=(new DateTimeImmutable(end($months).'-01'))->modify('+1 month')->format('Y-m-d');
        $opening=accounting_kpis($pdo,(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d'));
        $balance=accounting_cents($opening['assets']); $map=[];
        $s=$pdo->prepare("SELECT DATE_FORMAT(j.entry_date,'%Y-%m') AS month,c.Account_Type,SUM(l.debit_amount-l.credit_amount) AS net
            FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id JOIN Categories c ON c.CategoryID=l.account_id
            WHERE j.status='posted' AND j.entry_date>=:start AND j.entry_date<:end GROUP BY month,c.Account_Type");
        $s->execute(['start'=>$start,'end'=>$end]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $map[$r['month']][$r['Account_Type']]=accounting_cents((string)$r['net']); }
        $result=[];
        foreach ($months as $month) {
            $balance=accounting_add($balance,$map[$month]['Asset']??0);
            $income=accounting_decimal(-($map[$month]['Income']??0)); $expense=accounting_decimal($map[$month]['Expense']??0);
            $result[]=['month'=>$month,'income'=>(float)$income,'expenses'=>(float)$expense,'balance'=>(float)accounting_decimal($balance),
                'income_decimal'=>$income,'expenses_decimal'=>$expense,'balance_decimal'=>accounting_decimal($balance)];
        }
        return $result;
    });
}
function accounting_expenses(PDO $pdo, string $start, string $endExclusive): array
{
    accounting_date($start); accounting_date($endExclusive);
    $s=$pdo->prepare("SELECT c.CategoryID,c.Name AS category,SUM(l.debit_amount-l.credit_amount) AS total
        FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id JOIN Categories c ON c.CategoryID=l.account_id
        WHERE j.status='posted' AND c.Account_Type='Expense' AND j.entry_date>=:start AND j.entry_date<:end
        GROUP BY c.CategoryID,c.Name ORDER BY total DESC,c.Name,c.CategoryID");
    $s->execute(['start'=>$start,'end'=>$endExclusive]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['total']=accounting_decimal(accounting_cents((string)$r['total'])); } unset($r);
    return $rows;
}
function accounting_records_filters(array $get): array
{
    foreach (['view','from','to','account_id','type','page','category','filter_category','filter_type'] as $key) {
        if (isset($get[$key])&&!is_string($get[$key])) { throw new InvalidArgumentException('Invalid record filter.'); }
    }
    $context=$get['view']??'records';
    if (!in_array($context,['records','crb','cdb'],true)) { throw new InvalidArgumentException('Invalid ledger view.'); }
    $explicit=array_key_exists('from',$get)||array_key_exists('to',$get);
    $from=$explicit ? ($get['from']??'') : substr(accounting_today(),0,7).'-01';
    $to=$explicit ? ($get['to']??'') : accounting_today();
    if ($from!=='') { accounting_date($from); } if ($to!=='') { accounting_date($to); }
    if ($from!==''&&$to!==''&&$from>$to) { throw new InvalidArgumentException('From date must not follow Through date.'); }
    $type=$get['type']??''; if ($type==='Incoming') { $type='Income'; }
    if ($type!==''&&!in_array($type,ACCOUNTING_TYPES,true)) { throw new InvalidArgumentException('Invalid account type.'); }
    $account=$get['account_id']??'';
    if ($account!==''&&(!ctype_digit($account)||(int)$account<1||(int)$account>4294967295)) { throw new InvalidArgumentException('Invalid account.'); }
    $page=$get['page']??'1'; if (!ctype_digit($page)||(int)$page<1||(int)$page>1000000) { throw new InvalidArgumentException('Invalid page.'); }
    return ['context'=>$context,'from'=>$from,'to'=>$to,'type'=>$type,'account_id'=>$account,'page'=>(int)$page];
}
/** Main Financial Records request only; cash-book parsing remains unchanged. */
function accounting_records_request(array $get, array $accounts, string $today): array
{
    accounting_date($today);
    $defaults = ['from'=>substr($today,0,7).'-01','to'=>$today];
    $errors = [];
    foreach (['view','from','to','type','account_id','page','search','sort','reset','format','category','filter_category','filter_type','period'] as $key) {
        if (array_key_exists($key,$get) && !is_string($get[$key])) {
            $errors[$key] = 'Choose a single valid value.';
        }
    }
    $value = static fn(string $key, string $default=''): string => isset($get[$key]) && is_string($get[$key]) ? $get[$key] : $default;
    if ($value('view','records') !== 'records') { $errors['view']='Invalid ledger view.'; }
    if (!in_array($value('format','html'),['html','json'],true)) { $errors['format']='Invalid response format.'; }
    if (!in_array($value('reset','0'),['0','1'],true)) { $errors['reset']='Invalid reset request.'; }
    $reset = $value('reset') === '1';
    $explicit = !$reset && (array_key_exists('from',$get) || array_key_exists('to',$get));
    $draft = ['from'=>$explicit?$value('from'):$defaults['from'], 'to'=>$explicit?$value('to'):$defaults['to'],
        'type'=>$reset?'':$value('type'), 'account_id'=>$reset?'':$value('account_id')];
    if ($draft['type']==='Incoming') { $draft['type']='Income'; }
    foreach (['from'=>'From','to'=>'To'] as $key=>$label) {
        if ($draft[$key] !== '') {
            try { accounting_date($draft[$key]); }
            catch (InvalidArgumentException $e) { $errors[$key]="$label must be a valid date between 1000-01-01 and 9998-12-31. Rejected value: ".$draft[$key]; }
        }
    }
    if (!isset($errors['from']) && !isset($errors['to']) && $draft['from']!=='' && $draft['to']!=='' && $draft['from']>$draft['to']) {
        $errors['to']='To must be on or after From.';
    }
    if ($draft['type']!=='' && !in_array($draft['type'],ACCOUNTING_TYPES,true)) { $errors['type']='Choose a valid account type.'; }
    $byId=[];
    foreach ($accounts as $account) { $byId[(string)$account['CategoryID']]=$account; }
    if (!$reset && (array_key_exists('category',$get)||array_key_exists('filter_category',$get)||array_key_exists('filter_type',$get))) {
        $name=$value('filter_category',$value('category'));
        $legacyType=$value('filter_type',$draft['type']);
        $legacyType=['Fund'=>'Income','Incoming'=>'Income'][$legacyType]??$legacyType;
        $matches=array_values(array_filter($accounts,static fn($a)=>$a['Name']===$name&&($legacyType===''||$a['Account_Type']===$legacyType)));
        if ($name==='' || count($matches)!==1 || ($legacyType!==''&&!in_array($legacyType,ACCOUNTING_TYPES,true))
            || (isset($get['category'],$get['filter_category']) && $value('category')!==$name)) {
            $errors['account_id']='This account link is ambiguous or unavailable. Select an account by ID.';
        } else {
            $match=$matches[0];
            if (($draft['account_id']!=='' && $draft['account_id']!==(string)$match['CategoryID'])
                || ($draft['type']!=='' && $draft['type']!==$match['Account_Type'])) {
                $errors['account_id']='The account link conflicts with the selected account or type.';
            } else { $draft['account_id']=(string)$match['CategoryID']; $draft['type']=$match['Account_Type']; }
        }
    }
    if ($draft['account_id']!=='') {
        $id=$draft['account_id'];
        if (!ctype_digit($id) || strlen($id)>10 || (int)$id<1 || (int)$id>4294967295) {
            $errors['account_id']='Choose a valid account identifier.';
        } elseif (!isset($byId[$id])) { $errors['account_id']='Account not found. Select an available account.'; }
        elseif ($draft['type']!=='' && $byId[$id]['Account_Type']!==$draft['type']) { $errors['account_id']='This account does not match the selected type.'; }
    }
    $page=$reset?'1':$value('page','1');
    if (!ctype_digit($page) || strlen($page)>7 || (int)$page<1 || (int)$page>1000000) { $errors['page']='Invalid page.'; }
    $search=$reset?'':$value('search');
    if (!mb_check_encoding($search,'UTF-8') || mb_strlen($search)>1000) { $errors['search']='Search must contain valid text of at most 1,000 characters.'; }
    $sort=$value('sort','0:desc'); $order=[];
    foreach (explode(',',$sort) as $pair) {
        if (!preg_match('/\A([0-5]):(asc|desc)\z/',$pair,$m) || isset($order[(int)($m[1]??-1)])) {
            $errors['sort']='Invalid table sorting.'; break;
        }
        $order[(int)$m[1]]=[$m[1]+0,$m[2]];
    }
    $filters=$draft+['context'=>'records','page'=>isset($errors['page'])?1:(int)$page];
    return ['filters'=>$filters,'draft'=>$draft,'errors'=>$errors,'defaults'=>$defaults,
        'dateMode'=>$explicit?'explicit':'default','state'=>['search'=>$search,'order'=>array_values($order),'page'=>$filters['page']],
        'notice'=>array_key_exists('period',$get)?'Period is no longer used. The date filters shown here apply.':''];
}
function accounting_records(PDO $pdo, array $filters): array
{
    return accounting_read($pdo,function () use ($pdo,$filters) {
        $where=["j.status='posted'"]; $params=[];
        if ($filters['from']!=='') { $where[]='j.entry_date>=:start'; $params['start']=$filters['from']; }
        if ($filters['to']!=='') { $where[]='j.entry_date<:end'; $params['end']=accounting_next_day($filters['to']); }
        if ($filters['account_id']!=='') { $where[]='c.CategoryID=:account'; $params['account']=$filters['account_id']; }
        if ($filters['type']!=='') { $where[]='c.Account_Type=:type'; $params['type']=$filters['type']; }
        if ($filters['context']!=='records') {
            $where[]="c.Account_Type='Asset' AND c.Is_Cash_Account=1";
            $where[]=$filters['context']==='crb' ? 'l.debit_amount>0' : 'l.credit_amount>0';
        }
        $projection="SELECT j.id AS journal_id,j.entry_date,j.reference,j.description,j.posted_by_user_id,
            u.FullName AS posted_by,l.id AS line_id,l.account_id,c.Name AS account_name,c.Account_Code AS account_code,
            c.Account_Type AS account_type,l.debit_amount,l.credit_amount,l.fund_project_id
            FROM journal_entries j JOIN journal_entry_lines l ON l.journal_entry_id=j.id
            JOIN Categories c ON c.CategoryID=l.account_id LEFT JOIN user_identities u ON u.UserID=j.posted_by_user_id";
        $s=$pdo->prepare($projection.' WHERE '.implode(' AND ',$where).' ORDER BY j.entry_date DESC,j.id DESC,l.id ASC');
        $s->execute($params); $rows=$s->fetchAll(PDO::FETCH_ASSOC); $journals=[];
        $ids=array_unique(array_column($rows,'journal_id'));
        foreach (array_chunk($ids,500) as $chunk) {
            $s=$pdo->prepare($projection." WHERE j.status='posted' AND j.id IN (".implode(',',array_fill(0,count($chunk),'?')).') ORDER BY j.id,l.id');
            $s->execute(array_values($chunk));
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $id=$r['journal_id'];
                if (!isset($journals[$id])) { $journals[$id]=['header'=>$r,'lines'=>[],'debit_cents'=>0,'credit_cents'=>0]; }
                $journals[$id]['lines'][]=$r;
                $journals[$id]['debit_cents']=accounting_add($journals[$id]['debit_cents'],accounting_cents($r['debit_amount']));
                $journals[$id]['credit_cents']=accounting_add($journals[$id]['credit_cents'],accounting_cents($r['credit_amount']));
            }
        }
        foreach ($journals as &$j) { $j['debit_cents']=(string)$j['debit_cents']; $j['credit_cents']=(string)$j['credit_cents']; } unset($j);
        foreach ($rows as &$r) { $r['debit_cents']=(string)accounting_cents($r['debit_amount']); $r['credit_cents']=(string)accounting_cents($r['credit_amount']); } unset($r);
        require_once __DIR__.'/receipt_ocr.php';
        $attachments=receipt_journal_metadata($pdo,array_keys($journals));
        foreach($journals as $id=>&$journal){$journal['attachments']=$attachments[$id]??[];}unset($journal);
        return ['rows'=>$rows,'journals'=>$journals];
    });
}
