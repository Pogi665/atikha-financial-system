<?php
/** Lossless immutable envelope storage; no accounting calculation or schema change. */
const REPORT_BUNDLE_MAX_JSON_BYTES=16777216;
function report_bundle_storage_encode(array $payload,int $packetLimit):string
{
    $json=reporting_json($payload);
    if(strlen($json)>REPORT_BUNDLE_MAX_JSON_BYTES)throw new ReportingProblem('This complete report is too large to save safely. Ask the administrator to arrange a validated larger report. Nothing was submitted.',413);
    if(strlen($json)>262144){
        if(!function_exists('gzdeflate'))throw new ReportingProblem('Saving a large complete report is unavailable. Ask the administrator to check report storage support. Nothing was submitted.',503);
        $compressed=gzdeflate($json,6);
        if($compressed===false)throw new ReportingProblem('The complete report could not be prepared for safe storage. Nothing was submitted.',503);
        $json=reporting_json(['storage_format'=>'report_envelope_deflate_v1','json_sha256'=>hash('sha256',$json),'data'=>base64_encode($compressed)]);
    }
    // Leave room for the statement, identifiers, and other bound values. Never send an oversized packet.
    if(strlen($json)>REPORT_BUNDLE_MAX_JSON_BYTES||$packetLimit<=65536||strlen($json)>$packetLimit-65536)throw new ReportingProblem('This complete report is too large for the current safe connection limit. Ask the administrator to arrange a validated larger report. Nothing was submitted.',413);
    return $json;
}
function report_bundle_storage_decode(string $stored,string $hash):array
{
    try{
        if(strlen($stored)>REPORT_BUNDLE_MAX_JSON_BYTES)throw new UnexpectedValueException('Stored bound');
        $value=json_decode($stored,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($value)||!hash_equals($hash,hash('sha256',reporting_json($value))))throw new UnexpectedValueException('Stored hash');
        if(isset($value['storage_format'])){
            $keys=array_keys($value);sort($keys);
            if($keys!==['data','json_sha256','storage_format']||$value['storage_format']!=='report_envelope_deflate_v1'||!is_string($value['data'])||!is_string($value['json_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$value['json_sha256'])||!function_exists('gzinflate'))throw new UnexpectedValueException('Storage format');
            $bytes=base64_decode($value['data'],true);if($bytes===false||base64_encode($bytes)!==$value['data'])throw new UnexpectedValueException('Storage encoding');
            $json=@gzinflate($bytes,REPORT_BUNDLE_MAX_JSON_BYTES);
            if($json===false||strlen($json)>REPORT_BUNDLE_MAX_JSON_BYTES||!hash_equals($value['json_sha256'],hash('sha256',$json)))throw new UnexpectedValueException('Original hash');
            $value=json_decode($json,true,64,JSON_THROW_ON_ERROR);if(!is_array($value)||reporting_json($value)!==$json)throw new UnexpectedValueException('Original canonical envelope');
        }
        return $value;
    }catch(Throwable $e){throw new ReportingProblem('The frozen complete reports need an integrity review.',503);}
}
