<?php
/** POST forecast endpoint; writes only disposable cache and refresh audit. */
session_start();
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/includes/csrf.php';
require_once __DIR__.'/includes/user_session.php';
require_once __DIR__.'/includes/logger.php';
require_once __DIR__.'/includes/forecast_query.php';
require_once __DIR__.'/includes/gemini_client.php';
if(is_file(__DIR__.'/config.php')){require_once __DIR__.'/config.php';}
header('Content-Type: application/json; charset=utf-8');
function forecast_respond(bool $ok,?array $data,string $error,int $status=200): void
{ http_response_code($status);echo json_encode(['ok'=>$ok,'data'=>$data,'error'=>$error],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit; }
function forecast_payload(array $history,?array $forecast,string $state,?string $generatedAt=null,string $note=''): array
{
    return ['version'=>2,'basis'=>FORECAST_BASIS,'state'=>$state,'as_of'=>$history['as_of'],'generated_at'=>$generatedAt,'note'=>$note,
        'history'=>$history['series'],'projection'=>$forecast['chart_data']??forecast_baseline_projection($history),
        'advisory'=>['reallocation_suggestion'=>$forecast['reallocation_suggestion']??'','funding_risk'=>$forecast['funding_risk']??'','risk_level'=>'UNKNOWN'],
        'metrics'=>$history['metrics'],'categories'=>$history['categories'],'current_month'=>$history['current_month'],'budgets'=>$history['budgets']];
}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');forecast_respond(false,null,'POST requests only.',405);}
try{$valid=user_session_validate($pdo);}catch(Throwable $e){error_log('Forecast session check: '.$e->getMessage());forecast_respond(false,null,'Account access is unavailable.',503);}
if(!$valid){forecast_respond(false,null,'Your session expired.',401);}
if(!in_array($_SESSION['Role']??'',['Admin','Management'],true)){forecast_respond(false,null,'Access denied.',403);}
if(!csrf_verify(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:null)){forecast_respond(false,null,'Reload the page and try again.',400);}
$action=$_POST['action']??'load';if(!is_string($action)||!in_array($action,['load','refresh'],true)){forecast_respond(false,null,'Invalid forecast action.',400);}
$userId=(int)$_SESSION['UserID'];
try{$history=forecast_fetch_history($pdo);}catch(Throwable $e){error_log('Forecast history unavailable: '.$e->getMessage());forecast_respond(false,null,'Unable to read journal history.',503);}
if(!forecast_history_is_sufficient($history)){forecast_respond(true,forecast_payload($history,null,'insufficient'),'');}
if($action==='load'){
    try{$cache=forecast_cache_load($pdo,$history);if($cache){forecast_respond(true,forecast_payload($history,$cache['forecast'],'cached',$cache['created_at']),'');}}
    catch(Throwable $e){error_log('Forecast cache unavailable: '.$e->getMessage());}
}
if(!gemini_is_configured()){forecast_respond(true,forecast_payload($history,null,'degraded',null,'AI advisory unavailable. Showing the signed trailing three-month net-expense baseline.'),'');}
$forecast=null;$state='degraded';$stamp=null;$note='';$held=false;
try{
    $lock='atikha_forecast_'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,20);
    $s=$pdo->prepare('SELECT GET_LOCK(?,0)');$s->execute([$lock]);$held=(int)$s->fetchColumn()===1;
    if(!$held){$note='Forecast generation is already in progress. Showing the baseline.';}
    else{
        $s=$pdo->query('SELECT COUNT(*) FROM forecast_cache WHERE created_at>=NOW()-INTERVAL 60 SECOND');
        if((int)$s->fetchColumn()>0){$note='Generation is throttled for 60 seconds. Showing the baseline.';}
        else{
            // Durable attempt timestamp also throttles failed AI calls across users and reloads.
            $s=$pdo->prepare('INSERT INTO forecast_cache (Horizon_Months,Data_Fingerprint,History_JSON,Forecast_JSON,Model,GeneratedBy_UserID) VALUES (6,?,?,?,?,?)');
            $s->execute([forecast_fingerprint($history),json_encode($history,JSON_THROW_ON_ERROR),json_encode(['basis'=>FORECAST_BASIS,'generation_attempt'=>true],JSON_THROW_ON_ERROR),'generation_attempt',$userId]);
            $attempt=(int)$pdo->lastInsertId();
            $result=gemini_forecast_projection(forecast_history_for_ai($history));
            if(!$result['ok']){$note='AI advisory unavailable. Showing the trailing three-month net-expense baseline.';}
            else{
                $forecast=$result['data'];$state='fresh';
                $s=$pdo->prepare('UPDATE forecast_cache SET Forecast_JSON=?,Model=? WHERE ForecastID=?');
                $s->execute([json_encode($forecast,JSON_THROW_ON_ERROR),defined('GEMINI_MODEL')?GEMINI_MODEL:'unknown',$attempt]);
                $s=$pdo->prepare('SELECT created_at FROM forecast_cache WHERE ForecastID=?');$s->execute([$attempt]);$stamp=(string)$s->fetchColumn();
                if($action==='refresh'){log_system_action($pdo,$userId,'REFRESH','Forecast',null,null,['basis'=>FORECAST_BASIS,'fingerprint'=>forecast_fingerprint($history)]);}
            }
            $pdo->exec('DELETE FROM forecast_cache WHERE created_at<NOW()-INTERVAL 30 DAY');
        }
    }
}catch(Throwable $e){error_log('Forecast generation unavailable: '.$e->getMessage());$note=$forecast?'Forecast generated, but its cache could not be saved.':'Forecast generation unavailable. Showing the trailing three-month net-expense baseline.';}
finally{if($held){try{$s=$pdo->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}catch(Throwable $e){error_log('Forecast lock release failed: '.$e->getMessage());}}}
forecast_respond(true,forecast_payload($history,$forecast,$state,$stamp,$note),'');
