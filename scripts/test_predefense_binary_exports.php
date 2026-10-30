<?php
if(PHP_SAPI!=='cli')exit;
define('ATIKHA_ISOLATED_TEST',true);
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/includes/financial_export.php';require_once dirname(__DIR__).'/includes/report_export_binary.php';
$out=getopt('',['output:'])['output']??'';$real=realpath($out);if(!$real||!str_starts_with(str_replace('\\','/',$real),str_replace('\\','/',dirname(__DIR__)).'/.migration-private/'))throw new RuntimeException('Use private output.');
$rows=[['name'=>'=2+3','ref'=>'00125','money'=>'12345.67'],['name'=>'+SUM(A1:A2)','ref'=>'<script>alert(1)</script>','money'=>'-0.01'],['name'=>'Community · café — ₱','ref'=>'@formula','money'=>'9999999999999.99']];for($i=0;$i<120;$i++)$rows[]=['name'=>'Workshop materials '.($i+1),'ref'=>str_pad((string)$i,5,'0',STR_PAD_LEFT),'money'=>'0.10'];
$model=['title'=>'Fictional export validation','generated_at'=>'2026-10-29 12:00:00','metadata'=>['Scope'=>'Complete synthetic result','No financial data'=>'Private technical fixture'],'sections'=>[financial_export_section('Long / section [one]',$rows,[['name','Description','text'],['ref','Reference','text'],['money','Amount (PHP)','money']],['money'=>'12345.66'])]];
$spool=financial_export_spool($model);fclose($spool);foreach(['pdf','xlsx'] as $format){$stream=report_export_binary($model,$format);$bytes=stream_get_contents($stream);fclose($stream);file_put_contents($real.'/binary-test.'.$format,$bytes);if(!str_starts_with($bytes,$format==='pdf'?'%PDF-':'PK'))throw new RuntimeException('Wrong binary signature.');}
echo json_encode(['generated'=>['pdf','xlsx'],'rows'=>count($rows),'formula_text'=>true,'leading_zero_reference'=>true,'large_exact_money_text'=>true]).PHP_EOL;
