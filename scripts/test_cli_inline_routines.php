<?php
/** Offline fake-PDO checks for the opt-in disposable fixture path. No server access. */
require __DIR__.'/cli_common.php';
define('ATIKHA_ISOLATED_TEST',true);putenv('ATIKHA_TEST_INLINE_ROUTINES=1');
class InlineFixtureStatement extends PDOStatement {
    public function __construct(private string $database) {}
    public function fetchColumn(int $column=0):mixed{return $this->database;}
    public function closeCursor():bool{return true;}
}
class InlineFixturePDO extends PDO {
    public array $commands=[];
    public function __construct(public string $database) {}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false {
        if($query!=='SELECT DATABASE()')$this->commands[]=$query;
        return new InlineFixtureStatement($this->database);
    }
}
$checks=0;
function inline_check(bool $ok,string $label):void{global $checks;cli_require($ok,$label);$checks++;echo "PASS: $label\n";}
$file=tempnam(sys_get_temp_dir(),'atikha-inline-test-');
try{
    file_put_contents($file,"USE atikha_finance;\nDROP PROCEDURE IF EXISTS fixture_body;\nDELIMITER $$\nCREATE PROCEDURE fixture_body()\nBEGIN\n SELECT 7;\nEND$$\nDELIMITER ;\nCALL fixture_body();\nDROP PROCEDURE fixture_body;\nSELECT 8;\n");
    $p=new InlineFixturePDO('atikha_test_stage1_offline');cli_sql_file($p,$file);
    inline_check(count($p->commands)===2,'Compatibility mode executes CALL body and following SQL only');
    inline_check(str_starts_with($p->commands[0],'BEGIN NOT ATOMIC')&&str_contains($p->commands[0],'SELECT 7'),'Same zero-argument preflight body runs at CALL');
    inline_check(trim($p->commands[1])==='SELECT 8','Statement ordering after CALL is retained');
    inline_check(!preg_grep('/CREATE PROCEDURE|DROP PROCEDURE|USE atikha_finance/i',$p->commands),'No stored-routine registration or live USE is executed');
    $working=new InlineFixturePDO('atikha_finance');try{cli_sql_file($working,$file);throw new RuntimeException('Working target unexpectedly accepted');}catch(RuntimeException $e){inline_check($e->getMessage()==='Inline routines require a disposable database.'&&$working->commands===[],'Working database is rejected before fixture SQL');}
    file_put_contents($file,"CALL unknown_body();\n");try{cli_sql_file($p,$file);throw new RuntimeException('Unknown CALL accepted');}catch(RuntimeException $e){inline_check($e->getMessage()==='Unknown isolated procedure body.','Unknown routine body fails closed');}
    file_put_contents($file,"CREATE PROCEDURE with_argument(IN n INT) SELECT n;\n");try{cli_sql_file($p,$file);throw new RuntimeException('Unsupported routine accepted');}catch(RuntimeException $e){inline_check($e->getMessage()==='Unsupported isolated routine shape.','Parameterized/unsupported routines fail closed');}
    putenv('ATIKHA_TEST_INLINE_ROUTINES=0');file_put_contents($file,"SELECT 9;\n");$normal=new InlineFixturePDO('atikha_test_stage1_offline');cli_sql_file($normal,$file);inline_check($normal->commands===['SELECT 9'],'Normal SQL path is unchanged without explicit opt-in');
}finally{unlink($file);putenv('ATIKHA_TEST_INLINE_ROUTINES');}
echo "Inline fixture guard checks: $checks\n";
