<?php
require_once __DIR__.'/../includes/financial_export.php';
$o=getopt('',['baseline:']);$base=realpath($o['baseline']??'');$private=realpath(__DIR__.'/../.migration-private');if(!$base||!str_starts_with($base,$private.DIRECTORY_SEPARATOR))exit(2);require $base;
$values=['','plain','0','-1','=SUM(A1:A2)','+1','@text',"tab\tend","line\r\nend",'comma,value','"quoted"',"trailing "," leading","slash\\back",'PHP 5,000.00','<script>literal</script>',hex2bin('e282b1')];
$alphabet="ab012,\"\r\n\t=+-@ ";mt_srand(4167);for($i=0;$i<1000;$i++){$v='';for($j=0;$j<mt_rand(0,120);$j++)$v.=$alphabet[mt_rand(0,strlen($alphabet)-1)];$values[]=$v;}
foreach($values as $value)if(financial_export_cell($value)!==baseline_export_cell($value))throw new RuntimeException('CSV byte parity failed');echo 'PASS: '.count($values)." adversarial/generated cells byte-identical to baseline native CSV encoding\n";
