<?php
/** Read-only business presentation of COMPLETE, already authorized journal projections. */
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/financial_search.php';
require_once __DIR__.'/receipt_ocr.php';

function transaction_date(string $value):string
{
    if(trim($value)==='')return 'Not recorded';
    try{return (new DateTimeImmutable($value))->format('M j, Y');}catch(Throwable $e){return $value;}
}
function transaction_time(string $value,bool $utc=false):string
{
    if(trim($value)==='')return 'Not recorded';
    try{$d=new DateTimeImmutable($value,new DateTimeZone($utc?'UTC':'Asia/Manila'));if($utc)$d=$d->setTimezone(new DateTimeZone('Asia/Manila'));return $d->format('M j, Y · g:i A');}catch(Throwable $e){return $value;}
}
function transaction_project_label(array $line):string
{
    return ($line['fund_project_id']??null)===null?'Organization operations':(($line['project_name_snapshot']??'')?:'Project label not recorded');
}
function transaction_summary(array $journal):array
{
    $h=$journal['header'];$book=$h['source_book']??'';$kind=$h['transaction_kind']??'';
    $party=!empty($h['party_snapshot'])?json_decode($h['party_snapshot'],true,64,JSON_THROW_ON_ERROR):[];
    $cash=array_values(array_filter($journal['lines'],static fn($l)=>(int)($l['is_cash_account']??0)===1));
    $allocations=array_values(array_filter($journal['lines'],static fn($l)=>(int)($l['is_cash_account']??0)!==1));
    $amount=null;$type='Transaction';$cashName='';
    $balanced=(string)$journal['debit_cents']===(string)$journal['credit_cents'];
    if($kind==='ordinary'&&in_array($book,['CDB','CRB'],true)&&count($cash)===1&&$balanced){
        $side=$book==='CDB'?'credit_amount':'debit_amount';$other=$book==='CDB'?'debit_amount':'credit_amount';
        if(accounting_cents($cash[0][$side])>0&&accounting_cents($cash[0][$other])===0){$amount=accounting_decimal(accounting_cents($cash[0][$side]));$type=$book==='CDB'?'Payment':'Receipt';$cashName=$cash[0]['account_name'];}
    }
    if($book==='GJ'&&$kind==='ordinary')$type='Journal Entry';
    $type=['transfer'=>'Internal Transfer','correction_reversal'=>'Reversal','advance_release'=>'Advance Release','advance_liquidation'=>'Liquidation','advance_return'=>'Cash Return'][$kind]??$type;
    $projects=[];$projectIds=[];
    foreach($journal['lines'] as $l){$id=$l['fund_project_id']??null;$label=transaction_project_label($l);$projects[$label]=true;$projectIds[$id===null?'0':(string)$id]=true;}
    $status='Recorded';foreach($journal['corrections']??[] as $c)if($c['role']==='Corrected original')$status='Corrected';
    return ['journal_id'=>(int)$h['journal_id'],'type'=>$type,'book'=>$book?:'Legacy General Journal','kind'=>$kind,'entry_date'=>$h['entry_date'],'date_label'=>transaction_date($h['entry_date']),
        'reference'=>trim((string)($h['reference']??''))===''?'Not provided':$h['reference'],'party'=>($party['name']??'')?:'Not recorded','purpose'=>$h['description'],'project'=>implode(', ',array_keys($projects)),
        'project_ids'=>array_map('strval',array_keys($projectIds)),'amount'=>$amount,'amount_text'=>$amount!==null?accounting_money($amount):($kind==='transfer'?'Internal transfer':(count($cash)>1?'Multiple cash movements':($type==='Journal Entry'?'Journal entry':'Amount not summarized'))),
        'cash_account'=>$cashName,'allocations'=>$allocations,'status'=>$status,'recorded_by'=>$h['posted_by']??'Historical actor','recorded_at'=>$h['recorded_at']??'',
        'transaction_url'=>'journal_transaction.php?journal_id='.(int)$h['journal_id']];
}

/** Account/type matching selects journals; search, project and paging never truncate counterpart lines. */
function transaction_records_model(PDO $pdo,int $uid,array $get,bool $all=false):array
{
    return accounting_read($pdo,function()use($pdo,$uid,$get,$all){
        $actor=$pdo->prepare('SELECT Role,Is_Active FROM Users WHERE UserID=?');$actor->execute([$uid]);$user=$actor->fetch();
        if($uid<=0||(int)($_SESSION['UserID']??0)!==$uid||!$user||!(int)$user['Is_Active']||!in_array($user['Role'],['Admin','Management'],true))throw new JournalProblem('Access restricted.',403);
        foreach(['view','book','project_id','search','sort','page','format'] as $k)if(isset($get[$k])&&!is_string($get[$k]))throw new InvalidArgumentException('Choose a single valid filter.');
        $context=$get['view']??'records';if(!in_array($context,['records','cdb','crb'],true))throw new InvalidArgumentException('Choose a listed transaction view.');
        $accounts=accounting_accounts($pdo);$input=$get;$input['view']='records';$input['format']='html';$r=accounting_records_request($input,$accounts,accounting_today());
        if($r['errors'])throw new InvalidArgumentException(implode(' ',array_values($r['errors'])));
        $reset=($get['reset']??'')==='1';$book=$reset?'':($get['book']??'');if(!in_array($book,['','CDB','CRB','GJ'],true))throw new InvalidArgumentException('Choose a listed transaction type.');
        if($context!=='records')$book=strtoupper($context);
        $project=$reset?'':($get['project_id']??'');$projects=stage1_schema($pdo)?$pdo->query('SELECT id,code,name,is_active FROM projects ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC):[];
        if($project!==''&&$project!=='0'&&!in_array($project,array_map('strval',array_column($projects,'id')),true))throw new InvalidArgumentException('Choose an available project.');
        $filters=$r['filters'];$filters['context']=$context;$data=accounting_records($pdo,$filters);$rows=[];$matched=[];
        foreach($data['journals'] as $id=>$j){
            $s=transaction_summary($j);if($book!==''&&$s['book']!==$book)continue;
            if($project!==''&&!in_array($project,$s['project_ids'],true))continue;
            $hay=[$s['journal_id'],'#'.$s['journal_id'],'Journal #'.$s['journal_id'],$s['type'],$s['entry_date'],$s['reference'],$s['party'],$s['purpose'],$s['project'],$s['amount_text'],$s['status']];
            foreach($j['lines'] as $l)array_push($hay,$l['account_name'],$l['account_code']??'',$l['project_code_snapshot']??'');
            foreach($j['corrections']??[] as $c)array_push($hay,'Correction #'.$c['id'],$c['role'],$c['reason']);
            if(!financial_search_history($r['state']['search'],$hay))continue;
            $rows[]=$s;$matched[$id]=$j;
        }
        $keys=['entry_date','type','reference','party','purpose','amount'];$order=$r['state']['order']?:[[0,'desc']];
        usort($rows,static function($a,$b)use($keys,$order){foreach($order as [$col,$direction]){$key=$keys[$col];$cmp=$key==='amount'&&$a['amount']!==null&&$b['amount']!==null?accounting_cents($a['amount'])<=>accounting_cents($b['amount']):financial_search_compare((string)$a[$key],(string)$b[$key]);if($cmp)return $direction==='asc'?$cmp:-$cmp;}return $b['journal_id']<=>$a['journal_id'];});
        $count=count($rows);$pages=max(1,(int)ceil($count/10));$page=min($r['filters']['page'],$pages);$visible=$all?$rows:array_slice($rows,($page-1)*10,10);$visibleJournals=[];
        foreach($visible as $row)$visibleJournals[$row['journal_id']]=$matched[$row['journal_id']];
        return ['ok'=>true,'context'=>$context,'rows'=>$visible,'journals'=>$visibleJournals,'matching_count'=>$count,'page'=>$page,'pages'=>$pages,
            'criteria'=>array_diff_key($filters,['context'=>1])+['view'=>$context,'book'=>$book,'project_id'=>$project,'search'=>$r['state']['search'],'sort'=>$get['sort']??'0:desc'],
            'accounts'=>$accounts,'projects'=>$projects,'generated_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('M j, Y · g:i A'),'complete_book'=>$data['completeBook']];
    });
}
