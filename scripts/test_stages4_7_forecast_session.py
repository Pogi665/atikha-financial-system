"""Deterministic authenticated forecast/session concurrency on two owned isolated servers."""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
from urllib.parse import urlencode
from urllib.request import Request,urlopen
from urllib.error import HTTPError
import hashlib,json,socket,subprocess,time
from stages4_7_browser import ROOT,PRIVATE,IsolatedBrowserApp,PHP,ps
pp=PRIVATE/'stages4-7-current.json';pointer=json.loads(pp.read_text());fixture=Path(pointer['g6_fixture']).resolve();app=IsolatedBrowserApp(pointer,fixture,'stages4-7-forecast-session');second=None;log=None;passed=False;checks=[];measurements=[];before=app.fingerprint()
def check(ok,label):assert ok,label;checks.append(label);print('PASS: '+label,flush=True)
def request(base,path,session,data=None):
 req=Request(base+path,data=urlencode(data).encode() if data is not None else None,headers={'Cookie':'PHPSESSID='+session})
 try:
  with urlopen(req,timeout=15) as response:return response.status,json.loads(response.read())
 except HTTPError as e:return e.code,json.loads(e.read())
try:
 source=(app.app/'forecast_ai.php').read_text(encoding='utf-8');needle='try{$history=forecast_fetch_history($pdo);';assert source.count(needle)==1 and source.index('session_write_close();')<source.index(needle)
 hook="$g6=$_POST['_g6_barrier']??'';if(in_array($g6,['admin','management'],true)){$dir="+ps(app.run)+";file_put_contents($dir.'/'.$g6.'.ready',(string)session_status());$until=microtime(true)+10;while(!is_file($dir.'/'.$g6.'.release')&&microtime(true)<$until){usleep(10000);}if(!is_file($dir.'/'.$g6.'.release'))throw new RuntimeException('Synthetic barrier timed out');}"
 (app.app/'forecast_ai.php').write_text(source.replace(needle,hook+needle),encoding='utf-8');sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();other='http://127.0.0.1:'+str(port);log=(app.run/'second-server.private.log').open('w',encoding='utf-8');second=subprocess.Popen([PHP,'-d','session.save_path='+str(app.sessions),'-S','127.0.0.1:'+str(port),'-t',str(app.app),str(app.run/'router.php')],env=app.env,stdout=log,stderr=log)
 for _ in range(60):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.2):break
  except OSError:time.sleep(.1)
 for role in ['admin','management']:
  login=json.loads(subprocess.check_output([PHP,str(app.app/'scripts/http_fixture.php'),'--database='+app.db,'--sessions='+str(app.sessions),'--email='+role+'@example.invalid','--test-login'],env=app.env,text=True,encoding='utf-8'));session=login['session'];marker=app.run/(role+'.ready');release=app.run/(role+'.release')
  status,body=request(app.base,'/forecast_ai.php',session,{'action':'load','csrf_token':'invalid','_g6_barrier':role});check(status==400 and not marker.exists(),role+' rejected CSRF never reaches advisory barrier')
  with ThreadPoolExecutor(max_workers=2) as pool:
   pending=pool.submit(request,app.base,'/forecast_ai.php',session,{'action':'load','csrf_token':login['csrf'],'_g6_barrier':role})
   try:
    deadline=time.monotonic()+8
    while not marker.exists() and time.monotonic()<deadline:time.sleep(.01)
    check(marker.exists() and marker.read_text()=='1',role+' authenticated forecast reaches deterministic barrier with session closed')
    started=time.perf_counter();parallel=pool.submit(request,other,'/budgets.php?month=2026-09&format=json',session);status,body=parallel.result(timeout=4);elapsed=(time.perf_counter()-started)*1000;check(status==200 and body['ok'] and not pending.done() and not release.exists(),role+' same-session protected financial request finishes before forecast is released');measurements.append({'role':role,'parallel_financial_ms':elapsed,'forecast_held_until_financial_completed':True})
   finally:release.write_text('release',encoding='utf-8')
   status,body=pending.result(timeout=15);check(status==200 and body['ok'] and body['data']['state'] in ['insufficient','degraded','cached'],role+' forecast completes with existing advisory/source checks and no provider')
 check(app.fingerprint()==before,'Both concurrent read families preserve every synthetic table and audit row');passed=True
finally:
 if second is not None:
  second.terminate();second.wait(timeout=10)
 if log is not None:log.close()
 app.close();result={'passed':passed,'checks':checks,'measurements':measurements,'source_sha256':hashlib.sha256((ROOT/'forecast_ai.php').read_bytes()).hexdigest(),'coordination':'Separate owned PHP processes share the same isolated session directory and exact session; file barrier after real authentication and explicit session release; second protected request completes before release','test_copy_hook':'Pause only; no authorization, source, financial or provider check replaced','provider_called':False,'working_data_used':False};(app.run/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');p=json.loads(pp.read_text());p['g6_forecast_session_run']=str(app.run);pp.write_text(json.dumps(p,indent=2),encoding='utf-8');print('Forecast shared-session '+('PASS' if passed else 'FAIL'))
