<?php
/** Read-only account ledger. Search/page never changes financial balances. */
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/stage1_common.php';

class LedgerProblem extends RuntimeException
{
    public int $status;
    public function __construct(string $message, int $status=400) { parent::__construct($message); $this->status=$status; }
}
function ledger_actor(PDO $pdo,int $uid): array
{
    if($uid<=0||($uid!==(int)($_SESSION['UserID']??0)))throw new LedgerProblem('Please sign in again.',401);
    $s=$pdo->prepare('SELECT UserID,FullName,Role,Is_Active FROM Users WHERE UserID=?');$s->execute([$uid]);$a=$s->fetch(PDO::FETCH_ASSOC);
    if(!$a||(int)$a['Is_Active']!==1||!in_array($a['Role'],['Admin','Management'],true))throw new LedgerProblem('Access restricted.',403);
    $_SESSION['Role']=$a['Role'];$_SESSION['FullName']=$a['FullName'];return $a;
}
function ledger_request(array $r): array
{
    $today=accounting_today();
    $defaults=['account_id'=>'','from'=>substr($today,0,7).'-01','to'=>$today,'search'=>'','page'=>'1','page_size'=>'25'];
    $out=[];
    foreach($defaults as $key=>$default){
        $v=array_key_exists($key,$r)?$r[$key]:$default;
        if(!is_string($v)||!preg_match('//u',$v))throw new LedgerProblem('Choose valid ledger filters.');
        $out[$key]=$v;
    }
    if($out['account_id']!==''&&(!preg_match('/\A[1-9][0-9]{0,9}\z/',$out['account_id'])||(int)$out['account_id']>4294967295))throw new LedgerProblem('Choose a valid account.');
    try{accounting_date($out['from']);accounting_date($out['to']);}catch(InvalidArgumentException $e){throw new LedgerProblem('Choose valid From and To dates.');}
    if($out['from']>$out['to'])throw new LedgerProblem('From must be on or before To.');
    if(mb_strlen($out['search'],'UTF-8')>1000)throw new LedgerProblem('Search must be at most 1,000 characters.');
    if(!preg_match('/\A[1-9][0-9]{0,8}\z/',$out['page']))throw new LedgerProblem('Choose a valid page.');
    if(!in_array($out['page_size'],['25','50','100'],true))throw new LedgerProblem('Choose 25, 50 or 100 rows per page.');
    return $out;
}
function ledger_url(array $criteria,string $page='general_ledger.php'): string
{
    return $page.'?'.http_build_query(array_intersect_key($criteria,array_flip(['account_id','from','to','search','page','page_size'])),'','&',PHP_QUERY_RFC3986);
}
function ledger_transaction_url(int $journalId,array $criteria): string
{
    $context=[];foreach(['account_id','from','to','search','page','page_size'] as $key)$context['ledger_'.$key]=$criteria[$key];
    return 'journal_transaction.php?'.http_build_query(['journal_id'=>$journalId]+$context,'','&',PHP_QUERY_RFC3986);
}
function ledger_return_url(array $input): ?string
{
    if(!array_key_exists('ledger_account_id',$input))return null;
    $r=[];foreach(['account_id','from','to','search','page','page_size'] as $key)if(array_key_exists('ledger_'.$key,$input))$r[$key]=$input['ledger_'.$key];
    try{$r=ledger_request($r);return $r['account_id']!==''?ledger_url($r):null;}catch(Throwable $e){return null;}
}
function ledger_balance(string $amount): string
{
    $c=accounting_cents($amount);return accounting_money(accounting_decimal(abs($c))).($c>0?' Debit':($c<0?' Credit':''));
}
/** All dependencies, including actor/account/financial checks, share one snapshot. */
function ledger_read(PDO $pdo,int $uid,array $request,bool $fullPeriod=false,bool $allMatching=false): array
{
    $r=ledger_request($request);
    if($fullPeriod){$r['search']='';$r['page']='1';}
    return accounting_read($pdo,function()use($pdo,$uid,$r,$fullPeriod,$allMatching){
        ledger_actor($pdo,$uid);
        if(!stage1_schema($pdo))throw new LedgerProblem('The ledger is unavailable until accounting setup is complete.',503);
        $accounts=accounting_accounts($pdo);$account=null;
        foreach($accounts as $a)if((string)$a['CategoryID']===$r['account_id'])$account=$a;
        $result=['criteria'=>$r,'accounts'=>$accounts,'account'=>$account,'rows'=>[],
            'opening'=>'0.00','period_debits'=>'0.00','period_credits'=>'0.00','ending'=>'0.00',
            'period_count'=>0,'matching_count'=>0,'pages'=>1,'page_adjusted'=>false,'full_period'=>$fullPeriod,
            'generated_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s').' Asia/Manila'];
        if($r['account_id']==='')return $result;
        if(!$account)throw new LedgerProblem('That account is unavailable. Choose an existing account.',404);
        if(!in_array($account['Account_Type'],ACCOUNTING_TYPES,true))throw new LedgerProblem('This account needs a valid accounting classification.',503);
        $end=accounting_next_day($r['to']);
        // Validate complete contributing journals, including counterpart and opening lines.
        $s=$pdo->prepare("SELECT j.id FROM journal_entries j LEFT JOIN journal_entry_lines l ON l.journal_entry_id=j.id
            LEFT JOIN Categories c ON c.CategoryID=l.account_id
            WHERE j.status='posted' AND j.entry_date<? AND EXISTS
            (SELECT 1 FROM journal_entry_lines t WHERE t.journal_entry_id=j.id AND t.account_id=?)
            GROUP BY j.id HAVING COUNT(l.id)<2 OR SUM(l.debit_amount)<>SUM(l.credit_amount)
            OR SUM(l.debit_amount)<=0 OR SUM(CASE WHEN c.CategoryID IS NOT NULL
            AND c.Account_Type IN ('Asset','Liability','Equity','Income','Expense')
            AND ((l.debit_amount>0 AND l.credit_amount=0) OR (l.credit_amount>0 AND l.debit_amount=0)) THEN 0 ELSE 1 END)>0 LIMIT 1");
        $s->execute([$end,$r['account_id']]);
        if($s->fetchColumn()!==false)throw new LedgerProblem('A recorded transaction affecting this account needs an integrity review. The ledger cannot be displayed.',503);
        $s=$pdo->prepare("SELECT COALESCE(SUM(l.debit_amount-l.credit_amount),0) FROM journal_entry_lines l
            JOIN journal_entries j ON j.id=l.journal_entry_id WHERE l.account_id=? AND j.status='posted' AND j.entry_date<?");
        $s->execute([$r['account_id'],$r['from']]);$opening=accounting_cents((string)$s->fetchColumn());
        $cte="WITH movements AS (SELECT j.id AS journal_id,l.id AS line_id,j.entry_date,j.reference,j.description,
            COALESCE(j.source_book,'Legacy General Journal') AS source_book,
            CASE WHEN JSON_VALID(j.party_snapshot) THEN JSON_UNQUOTE(JSON_EXTRACT(j.party_snapshot,'$.name')) ELSE NULL END AS party_name,
            l.project_code_snapshot,l.project_name_snapshot,l.debit_amount,l.credit_amount,
            SUM(l.debit_amount-l.credit_amount) OVER (ORDER BY j.entry_date,j.id,l.id ROWS UNBOUNDED PRECEDING) AS movement_balance
            FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id
            WHERE l.account_id=? AND j.status='posted' AND j.entry_date>=? AND j.entry_date<?) ";
        // LOCATE treats %, underscore and backslash literally; no wildcard expansion.
        $match="(?='' OR LOCATE(?,CONCAT_WS(' ',journal_id,source_book,reference,description,party_name,project_code_snapshot,project_name_snapshot))>0)";
        $params=[$r['account_id'],$r['from'],$end,$r['search'],$r['search']];
        $s=$pdo->prepare($cte."SELECT COUNT(*) AS total,COALESCE(SUM(CASE WHEN $match THEN 1 ELSE 0 END),0) AS matched,
            COALESCE(SUM(debit_amount),0) AS debits,COALESCE(SUM(credit_amount),0) AS credits FROM movements");
        $s->execute($params);$summary=$s->fetch(PDO::FETCH_ASSOC);
        $debit=accounting_cents((string)$summary['debits']);$credit=accounting_cents((string)$summary['credits']);
        $pages=max(1,(int)ceil((int)$summary['matched']/(int)$r['page_size']));$page=min((int)$r['page'],$pages);
        $result['page_adjusted']=$page!==(int)$r['page'];$r['page']=(string)$page;
        $sql=$cte."SELECT * FROM movements WHERE $match ORDER BY entry_date,journal_id,line_id";
        if(!$fullPeriod&&!$allMatching)$sql.=' LIMIT '.(int)$r['page_size'].' OFFSET '.(($page-1)*(int)$r['page_size']);
        $s=$pdo->prepare($sql);$s->execute($params);$rows=[];
        while($row=$s->fetch(PDO::FETCH_ASSOC)){
            $row['running_balance']=accounting_decimal(accounting_add($opening,accounting_cents((string)$row['movement_balance'])));
            unset($row['movement_balance']);$row['journal_id']=(int)$row['journal_id'];$row['line_id']=(int)$row['line_id'];
            $row['debit_amount']=accounting_decimal(accounting_cents((string)$row['debit_amount']));
            $row['credit_amount']=accounting_decimal(accounting_cents((string)$row['credit_amount']));
            $row['transaction_url']=ledger_transaction_url($row['journal_id'],$r);$rows[]=$row;
        }
        return array_replace($result,['criteria'=>$r,'rows'=>$rows,'opening'=>accounting_decimal($opening),
            'period_debits'=>accounting_decimal($debit),'period_credits'=>accounting_decimal($credit),
            'ending'=>accounting_decimal(accounting_add(accounting_add($opening,$debit),-$credit)),
            'period_count'=>(int)$summary['total'],'matching_count'=>(int)$summary['matched'],'pages'=>$pages]);
    });
}
