<?php
declare(strict_types=1);
require_once __DIR__.'/database_phase4_probe.php';
$dir=$argv[1]??'';
if(!is_dir($dir)||!str_starts_with(p2b_path(realpath($dir)),p2b_path(realpath(__DIR__.'/../.migration-private')).'/'))throw new RuntimeException('Synthetic private test directory required.');
$checks=[];
function check4(string $name,bool $ok):void{global $checks;if(!$ok)throw new RuntimeException($name);$checks[]=$name;}
function reject4(string $name,callable $f):void{$bad=false;try{$f();}catch(Throwable $e){$bad=true;}check4($name,$bad);}
$manifest=__DIR__.'/../.migration-private/phase4-cutover-inputs.json';$hash=strtoupper(hash_file('sha256',$manifest));$s=p4_spec($manifest,$hash);
check4('actual pinned manifest accepted',count($s['Recoveries'])===3);
reject4('wrong manifest hash',fn()=>p4_spec($manifest,str_repeat('0',64)));
reject4('different manifest path',fn()=>p4_spec(__FILE__,$hash));
reject4('arbitrary role rejected',fn()=>p4_role($dir,$dir.'/inspection.json'));
reject4('old candidate role rejected',fn()=>p4_role(str_replace('/phase4-','/phase2b-','C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/phase4-20990101-010101-01234567'),$dir.'/inspection.json'));
file_put_contents($dir.'/candidate.pid','123');
$id=['port'=>'3306','datadir'=>'C:/xampp/mysql/data/','pid_file'=>$dir.'/candidate.pid','version'=>'10.4.32-MariaDB','ro'=>'1','recovery'=>'0','events'=>'OFF','binlog'=>'0'];
p4_identity($id,$s,$dir,'123');check4('normal port working identity accepted',true);
foreach(array_keys($id) as $key){$bad=$id;$bad[$key]='wrong';reject4('wrong identity '.$key,fn()=>p4_identity($bad,$s,$dir,'123'));}
reject4('wrong PID content',fn()=>p4_identity($id,$s,$dir,'124'));
reject4('invalid PID argument',fn()=>p4_identity($id,$s,$dir,'-1'));
$bad=$s;$bad['WorkingData']='C:/other';reject4('different datadir',fn()=>p4_identity($id,$bad,$dir,'123'));
$bad=$s['Recoveries'];$bad[0]['Hashes']=[str_repeat('0',64)];reject4('recovery metadata disagreement',fn()=>p3d_mapping($s['ReceiptMetadata'],$bad));
file_put_contents($dir.'/results.json',json_encode(['Passed'=>count($checks),'Checks'=>$checks,'SQLExecuted'=>false,'RealReceiptRecovered'=>false],JSON_PRETTY_PRINT));
echo 'PHASE4_PROBE_TESTS_PASSED: '.count($checks).PHP_EOL;
