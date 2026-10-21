<?php
/** Phase 3-B: pinned existing logical reader plus read-only offline header verification. */
declare(strict_types=1);
require_once __DIR__.'/database_phase3a_corrected_probe.php';

function p3b_header(string $bytes): array {
    // MariaDB 10.4.32 maria_def.h state header; ma_open.c _ma_state_info_read;
    // myisampack.h big-endian mi_uint*korr; LSN_STORE_SIZE=7. Never writes bytes.
    p2b_require(strlen($bytes)>=315 && substr($bytes,0,4)==="\xfe\xfe\x09\x03",'Unreviewed Aria header version/size.');
    $u=function(int $offset,int $length)use($bytes):int{
        $value=0;
        for($i=0;$i<$length;$i++){
            p2b_require($value<=intdiv(PHP_INT_MAX-255,256),'Header integer outside supported range.');
            $value=$value*256+ord($bytes[$offset+$i]);
        }
        return $value;
    };
    $base=$u(12,2);p2b_require($base===299 && ord($bytes[18])===2,'Unexpected target header layout/index count.');
    return ['open_count'=>$u(24,2),'changed'=>$u(26,2),'rows'=>$u(49,8),'deleted'=>$u(57,8),
        'checksum'=>bin2hex(substr($bytes,129,8)),'create_trid'=>$u(137,8),
        'uuid_sha256'=>hash('sha256',substr($bytes,$base,16))];
}
function p3b_pre_header(array $header,int $rows): void {
    p2b_require($rows>=0 && $header['rows']===$rows,'Offline header count differs from full logical scan.');
    p2b_require($header['open_count']===0,'Offline table is not closed.');
    p2b_require(($header['changed'] & 512)!==0 && ($header['changed'] & ~0x3b8)===0,'Unexpected pre-normalization state.');
}
function p3b_header_pair(array $before,array $after,int $rows): void {
    p3b_pre_header($before,$rows);
    p2b_require($after['rows']===$rows && $after['open_count']===0,'Normalized count/closed state differs.');
    foreach(['deleted','checksum','uuid_sha256'] as $key)p2b_require($before[$key]===$after[$key],'Offline normalization changed protected header content.');
    // Only ordinary not-analyzed/not-optimized/not-sorted statistics may remain.
    // Unknown state bits, moved/not-movable/not-zerofilled, crashed/in-repair all fail closed.
    p2b_require(($after['changed'] & ~0x38)===0 && $after['create_trid']===0,'Normalization did not clear recovery/damage state.');
}
function p3b_metadata_main(array $opt): int {
    p2b_require(PHP_SAPI==='cli' && PHP_INT_SIZE===8,'CLI 64-bit PHP required.');
    $base='C:/xampp/mysql/recovery-exports/diagnostic-20261020-032731-25c55e2d/';
    $file=realpath($opt['metadata']??'');$out=$opt['output']??'';
    p2b_require(is_string($file) && preg_match('~\A'.preg_quote(strtolower($base),'~').'phase2b-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}/normalization/data/mysql/tables_priv\.mai\z~D',p2b_path($file))===1,'Offline metadata requires fresh normalization target.');
    $role=dirname(dirname(dirname($file)));
    p2b_require(in_array(basename($out),['header-before.json','header-after.json'],true) && p2b_path((string)realpath(dirname($out)))===p2b_path($role.'/logs'),'Metadata output outside normalization logs.');
    p2b_require(!is_link($file) && is_file($file),'Ordinary target required.');
    $h=fopen($file,'rb');p2b_require($h!==false,'Cannot read header.');
    try{$bytes=fread($h,512);}finally{fclose($h);}
    p2b_require(is_string($bytes),'Header read failed.');$header=p3b_header($bytes);
    if(isset($opt['before'])){
        $prior=realpath($opt['before']);
        p2b_require($prior!==false && p2b_path($prior)===p2b_path($role.'/logs/header-before.json') && basename($out)==='header-after.json','Wrong metadata before-image.');
        p2b_require(isset($opt['rows']) && preg_match('/\A[0-9]{1,6}\z/D',$opt['rows'])===1,'Explicit row count required.');
        $before=json_decode(file_get_contents($prior),true,32,JSON_THROW_ON_ERROR);
        p3b_header_pair($before,$header,(int)$opt['rows']);
    }else {
        p2b_require(basename($out)==='header-before.json','Before image required for post-normalization check.');
        p3b_pre_header($header,$header['rows']);
    }
    p2b_json_write($out,$header);return 0;
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $o=getopt('',['metadata:','output:','before:','rows:']);
    if(isset($o['metadata'])){
        try{exit(p3b_metadata_main($o));}catch(Throwable $e){fwrite(STDERR,"Phase 3-B offline metadata check failed; retain private evidence.\n");exit(1);}
    }
    exit(p3c_main());
}
