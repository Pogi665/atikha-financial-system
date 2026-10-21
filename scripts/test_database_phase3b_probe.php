<?php
/** Pure metadata tests; no SQL, server startup, or recovery utility invocation. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3b_probe.php';
$checks=0;
function bcheck(string $label,bool $ok):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function breject(string $label,callable $f):void{$bad=false;try{$f();}catch(Throwable $e){$bad=true;}bcheck($label,$bad);}
function bbytes(int $state=936,int $rows=1,int $trid=0):string{
    $s=str_repeat("\0",512);$s=substr_replace($s,"\xfe\xfe\x09\x03",0,4);
    $s=substr_replace($s,pack('n',299),12,2);$s[18]=chr(2);
    $s=substr_replace($s,pack('n',$state),26,2);$s=substr_replace($s,pack('J',$rows),49,8);
    $s=substr_replace($s,pack('J',$trid),137,8);return $s;
}
$before=p3b_header(bbytes());$after=p3b_header(bbytes(40));
bcheck('big-endian original header decoded',$before['rows']===1 && $before['changed']===936 && $before['open_count']===0);
p3b_header_pair($before,$after,1);bcheck('normalized metadata accepted',true);
p3b_pre_header($before,1);bcheck('known moved-state accepted before mutation',true);
foreach([1,2,4,64,1024,32768] as $flag){$bad=$before;$bad['changed']|=$flag;breject('unsafe state blocks normalization before mutation '.$flag,fn()=>p3b_pre_header($bad,1));}
foreach([0,8,16,32,24,40,48,56] as $stats){p3b_header_pair($before,p3b_header(bbytes($stats)),1);bcheck('only ordinary statistics state accepted '.$stats,true);}
foreach([1,2,4,64,128,256,512,1024,32768] as $flag){breject('damaged/unrecognized state rejected '.$flag,fn()=>p3b_header_pair($before,p3b_header(bbytes(40|$flag)),1));}
foreach(['rows'=>2,'deleted'=>1,'checksum'=>'changed','uuid_sha256'=>'changed','open_count'=>1,'create_trid'=>1] as $key=>$value){
    $bad=$after;$bad[$key]=$value;breject('protected metadata rejected '.$key,fn()=>p3b_header_pair($before,$bad,1));
}
foreach(['rows'=>2,'open_count'=>1,'changed'=>40] as $key=>$value){$bad=$before;$bad[$key]=$value;breject('before-state rejected '.$key,fn()=>p3b_header_pair($bad,$after,1));}
breject('negative baseline count rejected',fn()=>p3b_header_pair($before,$after,-1));
breject('truncated header rejected',fn()=>p3b_header(substr(bbytes(),0,144)));
$bad=bbytes();$bad[0]='x';breject('unknown format rejected',fn()=>p3b_header($bad));
$bad=bbytes();$bad[18]=chr(3);breject('unexpected key count rejected',fn()=>p3b_header($bad));
$bad=substr_replace(bbytes(),pack('n',300),12,2);breject('unreviewed base offset rejected',fn()=>p3b_header($bad));
$bad=substr_replace(bbytes(),str_repeat("\xff",8),49,8);breject('unsigned overflow rejected',fn()=>p3b_header($bad));
$base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
$file=$base.'phase2b-20261021-001938-c6362668/index-trial/data/mysql/tables_priv.MAI';
$handle=fopen($file,'rb');try{$raw=fread($handle,512);}finally{fclose($handle);}
$real=p3b_header($raw);bcheck('actual offline source matches prior reviewed metadata',$real['changed']===936 && $real['rows']===1 && $real['create_trid']===0 && $real['open_count']===0);
foreach([$file,'C:/xampp/mysql/data/mysql/tables_priv.MAI',__FILE__] as $path){
    breject('metadata CLI rejects protected/outside file',fn()=>p3b_metadata_main(['metadata'=>$path,'output'=>__DIR__.'/unexpected-header.json']));
}
bcheck('no output written to repository',!file_exists(__DIR__.'/unexpected-header.json'));
echo json_encode(['Passed'=>$checks,'SQLExecuted'=>false,'ServerStarted'=>false,'NormalizationRun'=>false],JSON_PRETTY_PRINT)."\n";
