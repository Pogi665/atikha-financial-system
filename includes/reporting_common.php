<?php
/** Shared reporting/budget/period primitives. No schema application or provider calls. */
require_once __DIR__.'/accounting_query.php';
require_once __DIR__.'/stage1_common.php';
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/logger.php';

class ReportingProblem extends RuntimeException
{
    public int $status;
    public function __construct(string $message,int $status=422){parent::__construct($message);$this->status=$status;}
}
function reporting_json($value): string
{
    $sort=function($v)use(&$sort){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$item)$item=$sort($item);unset($item);return $v;};
    return json_encode($sort($value),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
function reporting_actor(PDO $pdo,int $uid,array $roles=['Admin','Management'],bool $lock=false): array
{
    if($uid<=0||$uid!==(int)($_SESSION['UserID']??0))throw new ReportingProblem('Please sign in again.',401);
    $s=$pdo->prepare('SELECT UserID,FullName,Role,Is_Active FROM Users WHERE UserID=?'.($lock?' FOR UPDATE':''));$s->execute([$uid]);$actor=$s->fetch(PDO::FETCH_ASSOC);
    if(!$actor||(int)$actor['Is_Active']!==1||!in_array($actor['Role'],$roles,true))throw new ReportingProblem('This action is not available for your account.',403);
    $_SESSION['Role']=$actor['Role'];$_SESSION['FullName']=$actor['FullName'];return $actor;
}
function reporting_csrf(array $r): void
{
    if(!csrf_verify(is_string($r['csrf_token']??null)?$r['csrf_token']:null))throw new ReportingProblem('Your session expired. Reload this task and try again.',400);
}
function reporting_text($value,int $max,string $label,bool $required=false): string
{
    if(!is_string($value)||!preg_match('//u',$value)||mb_strlen($value,'UTF-8')>$max||($required&&trim($value)===''))throw new ReportingProblem('Enter a valid '.$label.'.',400);
    return $value;
}
function reporting_id($value,string $label='record',bool $optional=false): ?int
{
    if($optional&&($value===''||$value===null))return null;
    if(is_int($value)&&$value>0&&$value<=4294967295)return $value;
    if(!is_string($value)||!preg_match('/\A[1-9][0-9]{0,9}\z/',$value)||(int)$value>4294967295)throw new ReportingProblem('Choose a valid '.$label.'.',400);
    return (int)$value;
}
function reporting_month($value): string
{
    $v=reporting_text($value,7,'report month',true);
    if(!preg_match('/\A[0-9]{4}-[0-9]{2}\z/',$v))throw new ReportingProblem('Choose a valid report month.',400);
    try{accounting_date($v.'-01');}catch(Throwable $e){throw new ReportingProblem('Choose a valid report month.',400);}return $v;
}
function reporting_month_range(string $month): array
{
    $month=reporting_month($month);return [$month.'-01',accounting_month_end((int)substr($month,0,4),(int)substr($month,5,2))];
}
/** Live actuals only. Null means the planning month has not begun. */
function reporting_actual_cutoff(string $month, ?string $today=null): ?string
{
    [$from,$end]=reporting_month_range($month);$today=$today===null?accounting_today():accounting_date($today);
    return $from>$today?null:min($end,$today);
}
function reporting_live_cutoff(string $month): string
{
    $cutoff=reporting_actual_cutoff($month);
    if($cutoff===null)throw new ReportingProblem('Actual reports are not available for a future month. Choose this month or an earlier month; use Budgets or Planning for future allocations.',422);
    return $cutoff;
}
function reporting_amount($value,string $label='amount',bool $positive=false): string
{
    $v=reporting_text($value,32,$label,true);
    try{$c=accounting_cents($v);}catch(Throwable $e){throw new ReportingProblem('Enter '.$label.' as an exact amount with at most two decimal places.',400);}
    if($positive&&$c<=0)throw new ReportingProblem('Enter a positive '.$label.'.',400);return accounting_decimal($c);
}
function reporting_audit(PDO $pdo,int $uid,string $action,string $entity,int $id,?array $before,array $after): void
{
    if(!log_system_action($pdo,$uid,$action,$entity,$id,$before,$after))throw new RuntimeException('The action and its audit record could not be saved.');
}
function reporting_tx(PDO $pdo,int $uid,array $roles,callable $operation)
{
    if($pdo->inTransaction())throw new LogicException('Reporting mutation owns its transaction.');
    $pdo->beginTransaction();
    try{
        $actor=reporting_actor($pdo,$uid,$roles,true);
        if(stage1_write_lock($pdo)===null)throw new ReportingProblem('Accounting coordination is unavailable. This action was not saved.',503);
        $result=$operation($actor);$pdo->commit();return $result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** A future installed period-control module must enforce basis changes even with UI flags off. */
function reporting_basis_guard(PDO $pdo,string $from,?string $to=null): void
{
    if(is_file(__DIR__.'/accounting_periods.php')){
        require_once __DIR__.'/accounting_periods.php';
        periods_basis_guard($pdo,$from,$to);
    }
}
