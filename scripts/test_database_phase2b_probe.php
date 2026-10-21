<?php
/** Offline synthetic tests only: no PDO connection, real tables, receipts or servers. */
declare(strict_types=1);
require_once __DIR__.'/database_phase2b_probe.php';
$count=0;
function check2b(string $name,bool $ok):void{global $count;if(!$ok)throw new RuntimeException('FAILED: '.$name);$count++;}
function reject2b(string $name,callable $f):void{$rejected=false;try{$f();}catch(Throwable $e){$rejected=true;}check2b($name,$rejected);}
final class P2BFakeStatement extends PDOStatement {
    private array $rows;private int $pos=0;
    public function __construct(array $rows){$this->rows=$rows;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return $this->rows[$this->pos++]??false;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function fetchColumn(int $column=0):mixed{$r=$this->rows[0]??[];return array_values($r)[$column]??false;}
    public function closeCursor():bool{return true;}
}
final class P2BFakePDO extends PDO {
    public array $queries=[];public string $orphanCount='0';public bool $badCheck=false;
    public function __construct(){}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{
        $this->queries[]=$query;
        if(str_contains($query,'KEY_COLUMN_USAGE'))return new P2BFakeStatement([
            ['TABLE_NAME'=>'child','CONSTRAINT_NAME'=>'fk','COLUMN_NAME'=>'a','REFERENCED_TABLE_SCHEMA'=>'atikha_finance','REFERENCED_TABLE_NAME'=>'parent','REFERENCED_COLUMN_NAME'=>'x'],
            ['TABLE_NAME'=>'child','CONSTRAINT_NAME'=>'fk','COLUMN_NAME'=>'b','REFERENCED_TABLE_SCHEMA'=>'atikha_finance','REFERENCED_TABLE_NAME'=>'parent','REFERENCED_COLUMN_NAME'=>'y']]);
        if(str_starts_with($query,'SELECT COUNT(*)'))return new P2BFakeStatement([[$this->orphanCount]]);
        if(str_starts_with($query,'CHECK TABLE'))return new P2BFakeStatement([['Msg_type'=>$this->badCheck?'warning':'status','Msg_text'=>$this->badCheck?'Needs review':'OK']]);
        if(str_contains($query,'TABLE_SCHEMA IN'))return new P2BFakeStatement([['TABLE_SCHEMA'=>'mysql','TABLE_NAME'=>'db','TABLE_TYPE'=>'BASE TABLE'],['TABLE_SCHEMA'=>'atikha_finance','TABLE_NAME'=>'test','TABLE_TYPE'=>'BASE TABLE']]);
        if(str_contains($query,'information_schema.TABLES'))return new P2BFakeStatement([['TABLE_NAME'=>'test','TABLE_TYPE'=>'BASE TABLE','engine'=>'InnoDB']]);
        if(str_starts_with($query,'SHOW CREATE'))return new P2BFakeStatement([['test','CREATE TABLE `test` (`value` blob)']]);
        if(str_starts_with($query,'SHOW FULL COLUMNS'))return new P2BFakeStatement([['Field'=>'value','Type'=>'blob']]);
        if(str_starts_with($query,'SELECT *'))return new P2BFakeStatement([[null,'secret',"\0\xff"],['0','secret','']]);
        if(str_starts_with($query,'SHOW GRANTS'))return new P2BFakeStatement(['GRANT SYNTHETIC ONLY']);
        return new P2BFakeStatement([]);
    }
}
$root=__DIR__.'/../.migration-private/phase2b-php-tests-'.bin2hex(random_bytes(5));
mkdir($root,0700,true);mkdir($root.'/receipts');file_put_contents($root.'/receipts/example.png',"synthetic bytes\0\xff");
check2b('safe identifier',p2b_ident('journal_entries')==='`journal_entries`');
reject2b('identifier injection blocked',fn()=>p2b_ident('users; DROP DATABASE mysql'));
reject2b('identifier quotes blocked',fn()=>p2b_ident('a`b'));
check2b('path normalization',p2b_path('C:\\test\\data\\')==='c:/test/data');
check2b('row null differs from empty',p2b_row_hash([null])!==p2b_row_hash(['']));
check2b('row null differs from text',p2b_row_hash([null])!==p2b_row_hash(['N;']));
check2b('row values framed',p2b_row_hash(['ab','c'])!==p2b_row_hash(['a','bc']));
check2b('binary bytes matter',p2b_row_hash(["\0\xff"])!==p2b_row_hash(["\0\xfe"]));
reject2b('nested values rejected',fn()=>p2b_row_hash([['unsupported']]));
check2b('multiset ignores physical order',p2b_multiset(['b','a'])===p2b_multiset(['a','b']));
check2b('duplicate multiplicity preserved',p2b_multiset(['a','a'])!==p2b_multiset(['a']));
p2b_check_rows([['Msg_type'=>'status','Msg_text'=>'OK']]);check2b('check OK accepted',true);
reject2b('no check result rejected',fn()=>p2b_check_rows([]));
reject2b('check warning rejected',fn()=>p2b_check_rows([['Msg_type'=>'warning','Msg_text'=>'x'],['Msg_type'=>'status','Msg_text'=>'OK']]));
reject2b('unsupported check rejected',fn()=>p2b_check_rows([['Msg_type'=>'note','Msg_text'=>'Not supported']]));
reject2b('check corrupt rejected',fn()=>p2b_check_rows([['Msg_type'=>'error','Msg_text'=>'Corrupt']]));
check2b('preserved attachment resolved',realpath($root.'/receipts/example.png')===p2b_evidence_path($root,'uploads/receipts/example.png'));
reject2b('missing attachment rejected',fn()=>p2b_evidence_path($root,'uploads/receipts/missing.png'));
reject2b('attachment traversal rejected',fn()=>p2b_evidence_path($root,'uploads/../outside.txt'));
reject2b('absolute attachment rejected',fn()=>p2b_evidence_path($root,'C:/other/private.txt'));
reject2b('URL attachment rejected',fn()=>p2b_evidence_path($root,'https://example.com/private.png'));
reject2b('backslash traversal rejected',fn()=>p2b_evidence_path($root,'uploads/receipts/..\\outside'));
p2b_json_write($root.'/evidence.json',['synthetic'=>true]);check2b('new evidence written',is_file($root.'/evidence.json'));
reject2b('evidence overwrite rejected',fn()=>p2b_json_write($root.'/evidence.json',['synthetic'=>false]));
$a=['table'=>['rows'=>2,'hash'=>'one','schema'=>'CREATE a']];p2b_compare($a,$a);check2b('exact comparison accepted',true);
$b=$a;$b['table']['rows']=1;reject2b('restore row loss rejected',fn()=>p2b_compare($a,$b));
$b=$a;$b['table']['hash']='two';reject2b('restore byte change rejected',fn()=>p2b_compare($a,$b));
$b=$a;$b['table']['schema']='CREATE b';reject2b('restore schema change rejected',fn()=>p2b_compare($a,$b));
$p=new P2BFakePDO();$fk=p2b_orphans($p);check2b('composite FK grouped',count($fk)===1);
check2b('composite FK NULL semantics',str_contains($p->queries[1],'c.`a` IS NOT NULL AND c.`b` IS NOT NULL'));
check2b('composite FK matches every part',str_contains($p->queries[1],'r.`x`=c.`a` AND r.`y`=c.`b`'));
$p->orphanCount='1';reject2b('orphan blocks inspection',fn()=>p2b_orphans($p));
$p=new P2BFakePDO();$n=p2b_table_checks($p,$root.'/checks.jsonl');check2b('table checks serial inventory', $n===2 && count($p->queries)===3);
$p=new P2BFakePDO();$p->badCheck=true;reject2b('table checks stop at first failure',fn()=>p2b_table_checks($p,$root.'/bad-checks.jsonl'));check2b('second check not attempted after failure',count($p->queries)===2);
$p=new P2BFakePDO();$snapshot=p2b_snapshot($p);check2b('snapshot has all synthetic rows',$snapshot['tables']['test']['rows']===2);
check2b('snapshot excludes raw secrets',!str_contains(json_encode($snapshot,JSON_THROW_ON_ERROR),'secret'));
check2b('snapshot covers system grants',count($snapshot['permissions'])===9);
check2b('snapshot covers object groups',count($snapshot['objects'])===5);
check2b('snapshot SQL is read only',count(array_filter($p->queries,fn($q)=>!preg_match('/\A(?:SELECT|SHOW) /',$q)))===0);
define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/../includes/journal_corrections.php';
$loaded=array_map(fn($v)=>strtolower(basename($v)),get_included_files());
check2b('integrity libraries load without working config',!in_array('config.php',$loaded,true) && !in_array('db_connect.php',$loaded,true));
check2b('integrity libraries do not start session',session_status()===PHP_SESSION_NONE);
check2b('shared exact-centavo reader retained',accounting_cents('1234567890.01')===123456789001);
p2b_json_write($root.'/results.json',['passed'=>$count,'database_connected'=>false,'server_started'=>false,'synthetic_only'=>true]);
echo json_encode(['passed'=>$count,'private_evidence'=>realpath($root),'database_connected'=>false],JSON_PRETTY_PRINT)."\n";
