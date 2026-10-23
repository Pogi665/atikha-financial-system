<?php
/** Independent small financial examples; no DB, configuration or network. */
define('ATIKHA_ISOLATED_TEST',true);require_once __DIR__.'/../includes/financial_report_query.php';
$accounts=[1=>['Account_Type'=>'Asset'],2=>['Account_Type'=>'Expense'],3=>['Account_Type'=>'Income'],4=>['Account_Type'=>'Asset'],5=>['Account_Type'=>'Liability'],6=>['Account_Type'=>'Equity'],7=>['Account_Type'=>'Expense'],8=>['Account_Type'=>'Asset'],9=>['Account_Type'=>'Asset'],10=>['Account_Type'=>'Income'],11=>['Account_Type'=>'Expense']];
$flows=[1=>'',2=>'operating',3=>'operating',4=>'investing',5=>'operating_liability',6=>'financing',7=>'noncash_expense',8=>'operating_asset',9=>'',10=>'noncash_income',11=>'review'];
$cfg=report_configuration_empty('2026-01-01');$cfg['cash_accounts']=[1,9];foreach($flows as $id=>$flow)$cfg['mappings'][]=['account_id'=>$id,'flow'=>$flow,'section'=>'Synthetic'];$count=0;
function c5line(int $id,int $a,string $d,string $c,int $j=1):array{return ['line_id'=>$id,'journal_entry_id'=>$j,'account_id'=>$a,'debit_amount'=>$d,'credit_amount'=>$c];}
function c5case(string $name,array $lines,array $expect,bool $complete=true,array $extra=[],string $kind='ordinary',array $outside=[],array $controls=[]):void{global $cfg,$accounts,$count;$all=$outside;foreach($lines as $l)$all[$l['line_id']]=$l;$closing=100000000000000;$net=0;foreach($lines as $l)if(in_array($l['account_id'],[1,9],true))$net=accounting_add($net,statement_line_net($l));$closing=accounting_add($closing,$net);$r=statement_cash_flows([1=>['lines'=>$lines]],$accounts,array_replace($cfg,$extra),[1=>$kind],[],$all,$controls,100000000000000,$closing);
    if($r['complete']!==$complete)throw new RuntimeException($name.': completeness '.json_encode($r));foreach($expect as $key=>$value)if($r[$key]!==$value)throw new RuntimeException($name.': '.$key.' '.json_encode($r));$count++;echo "PASS: $name\n";
}
c5case('Operating receipt',[c5line(1,1,'5000.00','0'),c5line(2,3,'0','5000.00')],['result'=>'5000.00','operating'=>'5000.00']);
c5case('Recognized refund retains contra expense sign',[c5line(1,1,'100.00','0'),c5line(2,2,'0','100.00')],['result'=>'100.00','operating'=>'100.00']);
c5case('Withholding gross expense and retained liability',[c5line(1,1,'0','4000.00'),c5line(2,2,'4500.00','0'),c5line(3,5,'0','500.00')],['result'=>'-4500.00','working_capital'=>'500.00','operating'=>'-4000.00']);
c5case('Expense accrual noncash',[c5line(1,2,'1000.00','0'),c5line(2,5,'0','1000.00')],['result'=>'-1000.00','working_capital'=>'1000.00','operating'=>'0.00']);
c5case('Payment of accrued liability',[c5line(1,5,'1000.00','0'),c5line(2,1,'0','1000.00')],['result'=>'0.00','working_capital'=>'-1000.00','operating'=>'-1000.00']);
c5case('Depreciation adds back exact noncash cost',[c5line(1,7,'100.00','0'),c5line(2,4,'0','100.00')],['result'=>'-100.00','noncash_adjustments'=>'100.00','operating'=>'0.00']);
c5case('In-kind noncash income',[c5line(1,4,'500.00','0'),c5line(2,10,'0','500.00')],['result'=>'500.00','noncash_adjustments'=>'-500.00','operating'=>'0.00']);
c5case('Equipment acquired on operating credit traced separately',[c5line(1,4,'8000.00','0'),c5line(2,5,'0','8000.00')],['working_capital'=>'8000.00','reclassifications'=>'-8000.00','operating'=>'0.00','investing'=>'0.00']);
c5case('Equipment cash purchase',[c5line(1,4,'8000.00','0'),c5line(2,1,'0','8000.00')],['operating'=>'0.00','investing'=>'-8000.00']);
c5case('Explicit equity cash financing',[c5line(1,1,'10000.00','0'),c5line(2,6,'0','10000.00')],['operating'=>'0.00','financing'=>'10000.00']);
c5case('Internal bank to petty cash transfer',[c5line(1,1,'0','2000.00'),c5line(2,9,'2000.00','0')],['operating'=>'0.00','net_cash_flow'=>'0.00']);
c5case('GJ operating advance cash adjustment',[c5line(1,8,'100.00','0'),c5line(2,1,'0','100.00')],['working_capital'=>'-100.00','operating'=>'-100.00']);
c5case('Unclassified initialization cash blocks completeness',[c5line(1,1,'10000.00','0'),c5line(2,6,'0','10000.00')],[],false,[],'opening');
$allocation=['cash_line_id'=>1,'counterpart_line_id'=>2,'amount'=>'10000.00','reason'=>'Synthetic contributed opening capital','activity'=>'financing'];
c5case('Explicit classified opening cash excludes operating result',[c5line(1,1,'10000.00','0'),c5line(2,6,'0','10000.00')],['result'=>'0.00','financing'=>'10000.00'],true,['cash_allocations'=>[$allocation]],'opening');
$allocation['amount']='9999.99';c5case('One cent missing allocation blocks completeness',[c5line(1,1,'10000.00','0'),c5line(2,6,'0','10000.00')],[],false,['cash_allocations'=>[$allocation]],'opening');
$allocation['amount']='10000.00';$allocation['activity']='operating';c5case('Explicit operating opening cash has a source-based classification bridge',[c5line(1,1,'10000.00','0'),c5line(2,3,'0','10000.00')],['result'=>'0.00','operating'=>'10000.00','reclassifications'=>'10000.00'],true,['cash_allocations'=>[$allocation]],'opening');
$reviewMaps=$cfg['mappings'];foreach($reviewMaps as &$mapping)if($mapping['account_id']===8)$mapping['flow']='review';unset($mapping);c5case('Reviewed mixed asset operating portion follows explicit cash allocation',[c5line(1,1,'0','100.00'),c5line(2,8,'100.00','0')],['operating'=>'-100.00','reclassifications'=>'-100.00'],true,['mappings'=>$reviewMaps,'cash_allocations'=>[['cash_line_id'=>1,'counterpart_line_id'=>2,'amount'=>'100.00','reason'=>'Synthetic reviewed operating advance','activity'=>'operating']]]);
$mixed=[c5line(1,1,'0','500.00'),c5line(2,11,'200.00','0'),c5line(3,4,'300.00','0')];c5case('Mixed account requires actual allocation',$mixed,[],false);
c5case('Reviewed mixed account operating allocation reconciles',$mixed,['result'=>'-200.00','operating'=>'-200.00','investing'=>'-300.00'],true,['cash_allocations'=>[['cash_line_id'=>1,'counterpart_line_id'=>2,'amount'=>'200.00','activity'=>'operating','reason'=>'Synthetic cost'],['cash_line_id'=>1,'counterpart_line_id'=>3,'amount'=>'300.00','activity'=>'investing','reason'=>'Synthetic equipment']]]);
$large=[c5line(1,1,'0','999999999999.99'),c5line(2,4,'999999999999.99','0')];c5case('Large exact amount never multiplies monetary signs',$large,['investing'=>'-999999999999.99']);
$cross=[['cash_line_id'=>1,'counterpart_line_id'=>3,'amount'=>'8000.00','activity'=>'investing','reason'=>'Future supported equipment'],['cash_line_id'=>1,'counterpart_line_id'=>2,'amount'=>'2000.00','activity'=>'operating','reason'=>'Operating remainder']];
$future=[3=>c5line(3,4,'8000.00','0',2),4=>c5line(4,8,'0','8000.00',2)];
c5case('Cross-month release investment excluded from operating advance',[c5line(1,1,'0','10000.00'),c5line(2,8,'10000.00','0')],['working_capital'=>'-10000.00','reclassifications'=>'8000.00','operating'=>'-2000.00','investing'=>'-8000.00'],true,['cash_allocations'=>$cross],'ordinary',$future,[8=>true]);
// Re-number both journals so the settled month is the selected period.
$cross[0]['cash_line_id']=10;$cross[0]['counterpart_line_id']=1;$cross[1]['cash_line_id']=10;$cross[1]['counterpart_line_id']=11;
c5case('Later equipment liquidation has no new cash outflow',[c5line(1,4,'8000.00','0'),c5line(2,8,'0','8000.00')],['working_capital'=>'8000.00','reclassifications'=>'-8000.00','operating'=>'0.00','investing'=>'0.00'],true,['cash_allocations'=>$cross],'ordinary',[10=>c5line(10,1,'0','10000.00',2),11=>c5line(11,8,'10000.00','0',2)],[8=>true]);
c5case('Unused operating advance returned later',[c5line(1,1,'2000.00','0'),c5line(2,8,'0','2000.00')],['operating'=>'2000.00','investing'=>'0.00']);
echo "RESULT: $count independent cash-flow examples passed\n";
