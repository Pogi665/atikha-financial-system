"""Serial correction-comparison timing/profile on guarded synthetic workloads only."""
from pathlib import Path
import argparse,hashlib,json,os,re,secrets,shutil,socket,statistics,subprocess,sys,time
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve()
sys.path.insert(0,str(private/'journal-test-deps'))
from playwright.sync_api import sync_playwright
ap=argparse.ArgumentParser();ap.add_argument('--fixture',required=True);ap.add_argument('--label',required=True);ap.add_argument('--profile',action='store_true');a=ap.parse_args()
source=Path(a.fixture).resolve();assert source.is_relative_to(private);f=json.loads(source.read_text());db=f['database'];evidence=Path(f['receipt_root']).resolve()
assert re.fullmatch(r'atikha_test_stage1_perf[a-z0-9]+',db) and evidence.is_relative_to(private) and f['perf_integrity_passed']
php='C:/xampp/php/php.exe';run=private/('perf-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
transport=json.loads(subprocess.check_output([php],input='<?php echo json_encode(["url"=>ini_get("allow_url_fopen"),"disabled"=>ini_get("disable_functions")]);',text=True))
assert transport['url'] in ['','0'] and all(n in transport['disabled'].split(',') for n in ['curl_exec','mail','fsockopen','stream_socket_client']),'Disable external PHP transports before testing.'
source_pins={n:hashlib.sha256((root/n).read_bytes()).hexdigest() for n in ['journal_corrections.php','includes/journal_corrections.php','includes/accounting_query.php','includes/stage2_common.php','includes/stage3_common.php']}
php_version=subprocess.check_output([php,'-r','echo PHP_VERSION;'],text=True)
tracked=subprocess.check_output(['git','ls-files','*.php'],cwd=root,text=True).splitlines()
for name in tracked:
    if '/' not in name and not name.startswith('_') and name not in ['config.php','db_connect.php']:shutil.copy2(root/name,app/name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
(app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(evidence)+");",encoding='utf-8')
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
connection="<?php "+("require __DIR__.'/perf-pdo.php';" if a.profile else '')+"$pdo=new "+('PerfPDO' if a.profile else 'PDO')+'('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);'
(app/'db_connect.php').write_text(connection,encoding='utf-8')
projection="<?php session_start();require __DIR__.'/db_connect.php';require __DIR__.'/includes/journal_corrections.php';$start=hrtime(true);$detail=correction_posted_detail($pdo,(int)($_SESSION['UserID']??0),"+str(f['perf_target_journal'])+");header('Content-Type: application/json');echo json_encode("+("['wall_ms'=>(hrtime(true)-$start)/1e6,'sql'=>$GLOBALS['perf_sql']??[],'spans'=>$GLOBALS['perf_spans']??[],'projection_hash'=>hash('sha256',json_encode($detail))]" if a.profile else '$detail')+");"
(app/'perf-projection.php').write_text(projection,encoding='utf-8')
if a.profile:
    (app/'perf-pdo.php').write_text(r'''<?php
class PerfStatement extends PDOStatement{
    protected function __construct(){}
    public function execute(?array $params=null):bool{$t=hrtime(true);try{return parent::execute($params);}finally{PerfPDO::record($this->queryString,$t);}}
}
class PerfPDO extends PDO{
    public function __construct(string $dsn,?string $u=null,?string $p=null,?array $options=null){parent::__construct($dsn,$u,$p,$options);$this->setAttribute(PDO::ATTR_STATEMENT_CLASS,[PerfStatement::class]);}
    public static function record(string $sql,int $start):void{$caller=[];foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,9) as $f)if(!in_array($f['function'],['record','execute','query','exec']))$caller[]=$f['function'];$GLOBALS['perf_sql'][]=['ms'=>(hrtime(true)-$start)/1e6,'sql'=>$sql,'callers'=>$caller];}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{$t=hrtime(true);try{return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);}finally{self::record($query,$t);}}
    public function exec(string $statement):int|false{$t=hrtime(true);try{return parent::exec($statement);}finally{self::record($statement,$t);}}
}
''',encoding='utf-8')
    for file,name,params,args,ret in [
        ('journal_corrections.php','correction_posted_detail','PDO $pdo,int $uid,int $journalId','$pdo,$uid,$journalId','array'),
        ('journal_corrections.php','correction_chain','PDO $pdo,int $uid,int $journalId','$pdo,$uid,$journalId','array'),
        ('journal_corrections.php','correction_reader_guard','PDO $pdo,int $uid','$pdo,$uid','string'),
        ('accounting_query.php','accounting_records','PDO $pdo,array $filters','$pdo,$filters','array'),
        ('accounting_query.php','accounting_complete_journals','PDO $pdo,array $ids,bool $hasStage1','$pdo,$ids,$hasStage1','array')]:
        p=app/'includes'/file;s=p.read_text(encoding='utf-8');assert s.count('function '+name+'(')==1;s=s.replace('function '+name+'(','function perf_original_'+name+'(')
        s+='\nfunction '+name+'('+params+'):'+ret+'{$t=hrtime(true);$n=count($GLOBALS["perf_sql"]??[]);try{return perf_original_'+name+'('+args+');}finally{$GLOBALS["perf_spans"][]=["name"=>"'+name+'","ms"=>(hrtime(true)-$t)/1e6,"sql_count"=>count($GLOBALS["perf_sql"]??[])-$n];}}\n';p.write_text(s,encoding='utf-8')
def snapshot():
    code="<?php require "+ps(root/'scripts/cli_common.php')+";$p=cli_db("+ps(db)+");$p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$out=[];foreach($p->query(\"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME\")->fetchAll(PDO::FETCH_COLUMN) as $n){$h=[];$q=$p->query('SELECT * FROM `'.$n.'`');while($r=$q->fetch(PDO::FETCH_NUM))$h[]=hash('sha256',serialize($r));sort($h);$out[$n]=['rows'=>count($h),'hash'=>hash('sha256',implode('|',$h))];}$p->rollBack();echo json_encode($out);"
    return json.loads(subprocess.check_output([php],input=code,text=True,cwd=root))
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True,cwd=root))
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|forecast_ai|external_email|ocr_extract)~',$p)){http_response_code(403);exit;}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
rows=[];profiles={};projections={};errors=[];success=False;before=snapshot();(run/'before.json').write_text(json.dumps(before,indent=2))
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as pw:
        browser=pw.chromium.launch(channel='msedge',headless=True)
        for role,email in [('Admin','admin@example.invalid'),('Management','management@example.invalid')]:
            ctx=browser.new_context(viewport={'width':1366,'height':768});ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());ss=login(email);ctx.add_cookies([{'name':'PHPSESSID','value':ss['session'],'url':base}]);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
            if a.profile:
                response=ctx.request.get(base+'/perf-projection.php',timeout=120000);assert response.status==200;profiles[role]=response.json();print(a.label,role,'profile ms',profiles[role]['wall_ms'],'SQL count',len(profiles[role]['sql']),flush=True)
            else:
                url=base+'/journal_corrections.php?journal_id='+str(f['perf_target_journal'])
                for sample in range(6):
                    start=time.perf_counter();response=ctx.request.get(url,timeout=120000);http_ms=(time.perf_counter()-start)*1000;body=response.body();assert response.status==200
                    start=time.perf_counter();nav=page.goto(url,wait_until='domcontentloaded',timeout=120000);page.wait_for_selector('.correction-journal table',timeout=120000);visible=(time.perf_counter()-start)*1000;timing=page.evaluate("(()=>{const t=performance.getEntriesByType('navigation')[0];return {ttfb_ms:t.responseStart-t.requestStart,dom_ms:t.domContentLoadedEventEnd-t.startTime}})()")
                    assert nav.status==200 and page.locator('.correction-comparison').count()==f['perf_chain_length']
                    rows.append({'label':a.label,'role':role,'sample':sample,'first_or_repeat':'first' if sample==0 else 'repeat','http_status':response.status,'http_bytes':len(body),'http_ms':http_ms,'visible_ms':visible,**timing})
                    (run/(role.lower()+'-comparison.html')).write_bytes(body);print(a.label,role,sample,'HTTP',round(http_ms,3),'visible',round(visible,3),flush=True)
                response=ctx.request.get(base+'/perf-projection.php',timeout=120000);assert response.status==200;projections[role]=response.json()
            ctx.close()
        browser.close()
    assert snapshot()==before,'Timed/profile reads changed application tables';assert not errors,errors;success=True
finally:
    server.terminate();server.wait(timeout=10);log.close()
    result={'passed':success,'label':a.label,'profile':a.profile,'counts':f['perf_counts'],'target':f['perf_target_journal'],'chain_length':f['perf_chain_length'],'rows':rows,'errors':errors,'read_only_unchanged':success,'server_mode':'PHP built-in development server, one worker; headless Edge','php_version':php_version,'source_pins':source_pins,'external_transports_disabled':True}
    if rows:result['summary']={role:{'http_repeat_median_ms':statistics.median(r['http_ms'] for r in rows if r['role']==role and r['sample']>0),'http_slowest_ms':max(r['http_ms'] for r in rows if r['role']==role),'visible_repeat_median_ms':statistics.median(r['visible_ms'] for r in rows if r['role']==role and r['sample']>0),'visible_slowest_ms':max(r['visible_ms'] for r in rows if r['role']==role)} for role in ['Admin','Management']}
    (run/'results.json').write_text(json.dumps(result,indent=2),encoding='utf-8');(run/'profiles.json').write_text(json.dumps(profiles,indent=2),encoding='utf-8');(run/'projections.json').write_text(json.dumps(projections,indent=2),encoding='utf-8');print('PERF_RUN: '+str(run),flush=True)
