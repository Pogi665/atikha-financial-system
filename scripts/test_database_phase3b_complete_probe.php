<?php
/** Offline synthetic files/PDO doubles only; never executes SQL or restores real receipt paths. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3b_complete_probe.php';
set_error_handler(fn(int $level,string $message)=>throw new ErrorException($message,0,$level));
$n=0;function dt(string $label,bool $ok):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function dr(string $label,callable $fn):void{$bad=false;try{$fn();}catch(Throwable $e){$bad=true;}dt($label,$bad);}
$run=__DIR__.'/../.migration-private/phase3d-php-tests-'.bin2hex(random_bytes(5));mkdir($run);
foreach(['original','original/board','original/receipts','rehearsal','rehearsal/receipts'] as $dir)mkdir($run.'/'.$dir);
file_put_contents($run.'/rehearsal/receipts/keep.txt','existing bytes');
$im=imagecreatetruecolor(3,3);imagefill($im,0,0,0x225588);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
$hash=hash('sha256',$png);$spec=[];$metadata=[];
for($i=1;$i<=3;$i++){
    file_put_contents($run.'/original/board/source'.$i.'.png',$png);
    $r=['ReceiptID'=>(string)$i,'PublicPath'=>'uploads/receipts/recovered'.$i.'.png','SourceRelative'=>'board/source'.$i.'.png','Hashes'=>[$hash],'Bytes'=>(string)strlen($png),'Mime'=>'image/png'];$spec[]=$r;
    $metadata[]=['ReceiptID'=>(string)$i,'File_Path'=>$r['PublicPath'],'File_Size'=>$r['Bytes'],'Mime_Type'=>$r['Mime'],'File_SHA256'=>$hash,'Posted_File_SHA256'=>null];
}
p3d_mapping($metadata,$spec);dt('recovery map binds exact original metadata',true);
foreach(['PublicPath'=>'uploads/receipts/different.png','ReceiptID'=>'9','Bytes'=>'1','Mime'=>'text/plain','Hashes'=>[str_repeat('0',64)]] as $key=>$value){$bad=$spec;$bad[1][$key]=$value;dr('metadata mismatch rejected '.$key,fn()=>p3d_mapping($metadata,$bad));}
$bad=$metadata;$bad[]=$bad[0];dr('duplicate metadata rejected',fn()=>p3d_mapping($bad,$spec));
$bad=$spec;$bad[1]['ReceiptID']='1';dr('duplicate recovery identity rejected',fn()=>p3d_mapping($metadata,$bad));
$plan=p3d_recovery_plan($run.'/original',$run.'/rehearsal',$spec);dt('complete three-file preflight accepted',count($plan)===3);
dt('preflight did not create any destination',!file_exists($plan[0]['target']) && !file_exists($plan[2]['target']));
foreach(['uploads/../escape.png','uploads/receipts/../escape.png','C:/escape.png','uploads/receipts/a:stream','uploads/receipts/','uploads\\receipts\\file.png'] as $path){$bad=$spec;$bad[2]['PublicPath']=$path;dr('unsafe destination rejected',fn()=>p3d_recovery_plan($run.'/original',$run.'/rehearsal',$bad));}
foreach(['PublicPath'=>$spec[0]['PublicPath'],'ReceiptID'=>'1','SourceRelative'=>'../escape.png','Hashes'=>[],'Bytes'=>'1','Mime'=>'text/plain'] as $key=>$value){$bad=$spec;$bad[2][$key]=$value;dr('unsafe/unproven recovery rejected '.$key,fn()=>p3d_recovery_plan($run.'/original',$run.'/rehearsal',$bad));}
dr('partial recovery specification rejected',fn()=>p3d_recovery_plan($run.'/original',$run.'/rehearsal',array_slice($spec,0,2)));
dr('overlapping upload roots rejected',fn()=>p3d_recovery_plan($run.'/original',$run.'/original/receipts',$spec));
dr('same upload root rejected',fn()=>p3d_recovery_plan($run.'/original',$run.'/original',$spec));
dr('missing upload root rejected',fn()=>p3d_recovery_plan($run.'/absent',$run.'/rehearsal',$spec));
$bad=$spec;$bad[0]['PublicPath']='uploads/absent/file.png';dr('missing destination parent rejected',fn()=>p3d_recovery_plan($run.'/original',$run.'/rehearsal',$bad));
$bad=$spec;$bad[0]['Hashes'][]=str_repeat('1',64);dr('all stored hashes must agree with bytes',fn()=>p3d_recovery_plan($run.'/original',$run.'/rehearsal',$bad));
dt('failed preflights left all destinations absent',!file_exists($plan[0]['target']) && !file_exists($plan[2]['target']));
$copied=p3d_copy_verified($plan);dt('three synthetic exact-byte files recovered',count($copied)===3);
for($i=0;$i<3;$i++){
    dt('recovered hash/size matches recorded metadata '.$i,hash_file('sha256',$plan[$i]['target'])===$hash && filesize($plan[$i]['target'])===strlen($png));
    dt('source bytes unchanged '.$i,file_get_contents($plan[$i]['source'])===$png);
}
dt('existing rehearsal files unchanged',file_get_contents($run.'/rehearsal/receipts/keep.txt')==='existing bytes');
dr('second recovery cannot overwrite filenames',fn()=>p3d_recovery_plan($run.'/original',$run.'/rehearsal',$spec));
dr('exclusive-create also blocks stale-plan overwrite',fn()=>p3d_copy_verified($plan));
mkdir($run.'/race');mkdir($run.'/race/receipts');$race=p3d_recovery_plan($run.'/original',$run.'/race',$spec);
file_put_contents($race[0]['source'],'changed after preflight');dr('source modification after preflight rejected',fn()=>p3d_copy_verified($race));
dt('tampered source created no recovery destination',!file_exists($race[0]['target']));file_put_contents($race[0]['source'],$png);
file_put_contents($race[0]['target'],'racing existing destination');dr('existing destination after preflight cannot be overwritten',fn()=>p3d_copy_verified($race));
dt('existing destination bytes retained',file_get_contents($race[0]['target'])==='racing existing destination');
final class P3DStmt extends PDOStatement {public function __construct(private array $rows){}public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}}
final class P3DPDO extends PDO {public function __construct(private array $rows){}public function query(string $sql,?int $mode=null,mixed ...$args):PDOStatement|false{if($sql!=='SELECT ReceiptID,File_Path,File_Size,Mime_Type,File_SHA256,Posted_File_SHA256 FROM receipts ORDER BY ReceiptID')throw new RuntimeException('Unexpected SQL in double');return new P3DStmt($this->rows);}}
p3d_metadata(new P3DPDO($metadata),$metadata);dt('database metadata comparison accepts exact export rows',true);
foreach(array_keys($metadata[0]) as $key){$bad=$metadata;$bad[0][$key]='different';dr('database metadata mismatch blocks acceptance '.$key,fn()=>p3d_metadata(new P3DPDO($bad),$metadata));}
dr('missing database receipt rejected',fn()=>p3d_metadata(new P3DPDO(array_slice($metadata,0,2)),$metadata));
$base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/phase2b-20990101-000000-abcdef12';
dt('fresh prepare-files role accepted',p2b_path(p3d_role($base,true))===p2b_path($base));
foreach(['validation','restore'] as $role)dt('fresh inspection role accepted '.$role,p2b_path(p3d_role($base.'/'.$role,false))===p2b_path($base));
foreach(['C:/xampp/mysql/data',$base.'/capture',$base.'/normalization',$base.'/restore/..','C:/outside/validation'] as $path)dr('invalid inspection role rejected',fn()=>p3d_role($path,false));
dr('inspection subrole cannot prepare files',fn()=>p3d_role($base.'/validation',true));
$manifest=__DIR__.'/../.migration-private/phase3b-completion-inputs.json';$pin='E48A737DD82A574A10714394CAA6BDAB7592E265227FFE296BC335BE811CD4F1';
$actual=p3d_spec($manifest,$pin);dt('actual private original-export mapping accepted without copying files',count($actual['Recoveries'])===3);
dr('manifest tamper pin rejected',fn()=>p3d_spec($manifest,str_repeat('0',64)));
dr('outside manifest rejected',fn()=>p3d_spec(__FILE__,$pin));
file_put_contents($run.'/results.json',json_encode(['Passed'=>$n,'SQLExecuted'=>false,'ServerStarted'=>false,'RealReceiptRestored'=>false,'SyntheticCopiesOnly'=>true],JSON_PRETTY_PRINT));
echo json_encode(['Passed'=>$n,'Evidence'=>realpath($run),'SQLExecuted'=>false,'ServerStarted'=>false,'RealReceiptRestored'=>false],JSON_PRETTY_PRINT)."\n";
