from test_pointer import test_pointer
"""Guarded synthetic Edge report/setup checks; not production login or client acceptance."""
from pathlib import Path
import hashlib,json,os,re,secrets,shutil,socket,statistics,subprocess,sys,time
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve()
sys.path.insert(0,str(private/'journal-test-deps'))
from playwright.sync_api import sync_playwright
pointer_path=test_pointer(private/'stages4-7-current.json');pointer=json.loads(pointer_path.read_text(encoding='utf-8'))
fixture=Path(pointer['g2_ui_fixture']).resolve();assert fixture.is_relative_to(private)
f=json.loads(fixture.read_text(encoding='utf-8'));db=f['database'];assert re.fullmatch(r'atikha_test_stage1_s5[a-z0-9]+',db)
evidence=Path(f['receipt_root']).resolve();assert evidence.is_relative_to(private)
case=json.loads(fixture.with_name('stage5-reports-result.json').read_text(encoding='utf-8'));assert case['passed']
env=os.environ.copy();env.update(pointer['environment']);php='C:/xampp/php/php.exe'
run=private/('stages5-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
def php_json(body):
 r=subprocess.run([php],input='<?php '+body,capture_output=True,text=True,encoding='utf-8',env=env,timeout=90)
 assert r.returncode==0 and not r.stderr,'Private PHP fixture read failed';return json.loads(r.stdout)
guard=php_json('echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);')
assert guard[0] in ['','0'] and all(n in guard[1].split(',') for n in ['curl_exec','mail','fsockopen','stream_socket_client'])
for source in root.glob('*.php'):
 if source.name not in ['config.php','db_connect.php'] and not source.name.startswith('_'):shutil.copy2(source,app/source.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
(app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',false);define('STAGE2_ADVANCES_ENABLED',false);define('STAGE3_CORRECTIONS_ENABLED',false);define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(evidence)+');',encoding='utf-8')
dsn='mysql:host='+env.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,env.get('ATIKHA_DB_USER','root'),env.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def sql(query):return php_json('require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());')
def fingerprint():return {r['TABLE_NAME']:hashlib.sha256(json.dumps(sql('SELECT * FROM `'+r['TABLE_NAME']+'` ORDER BY 1'),sort_keys=True).encode()).hexdigest() for r in sql('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE="BASE TABLE"')}
before=fingerprint();sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base='http://127.0.0.1:'+str(port)
router=run/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|forecast_ai|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|external_email|send_email)~',$p)){http_response_code(403);exit;}return false;",encoding='utf-8')
log=(run/'server.private.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S','127.0.0.1:'+str(port),'-t',str(app),str(router)],stdout=log,stderr=log,env=env)
checks=[];screens=[];errors=[];timings=[];passed=False
def check(ok,label):assert ok,label;checks.append(label);print('PASS: '+label,flush=True)
try:
 for _ in range(60):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.2):break
  except OSError:time.sleep(.1)
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  def context(email):
   ctx=browser.new_context(viewport={'width':1366,'height':768},service_workers='block');ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
   s=json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True,encoding='utf-8',env=env));ctx.add_cookies([{'name':'PHPSESSID','value':s['session'],'url':base}]);return ctx
  admin=context('admin@example.invalid');manager=context('management@example.invalid');anon=browser.new_context()
  page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  def capture(name):
   for w,h in [(1366,768),(1920,1080)]:
    page.set_viewport_size({'width':w,'height':h});page.evaluate("async()=>{scrollTo({top:0,behavior:'instant'});await new Promise(requestAnimationFrame);await new Promise(requestAnimationFrame)}");check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),name+' no document overflow '+str(w));target=run/(name+'-'+str(w)+'.png');page.screenshot(path=str(target),full_page=True);screens.append(str(target))
    if name=='report-management-configuration':
     check(page.locator('.app-sidebar').bounding_box()['y']==0,'Management navigation fixed at viewport top '+str(w));top=run/(name+'-viewport-'+str(w)+'.png');page.screenshot(path=str(top));screens.append(str(top))
   page.set_viewport_size({'width':1366,'height':768})
  address=base+'/financial_statements.php?month='+case['month']
  for role,ctx in [('Admin',admin),('Management',manager)]:
   samples=[]
   for n in range(6):
    start=time.perf_counter();response=ctx.request.get(address+'&format=json');result=response.json();elapsed=(time.perf_counter()-start)*1000
    check(response.status==200 and result['data']['core_complete'],role+' coherent report sample '+str(n));samples.append(elapsed)
   check(statistics.median(samples[1:])<=2000,role+' repeated core HTTP target');visible=ctx.new_page();visible_samples=[]
   for n in range(6):
    start=time.perf_counter();r=visible.goto(address);visible.locator('#fr-state').wait_for(state='visible');visible_samples.append((time.perf_counter()-start)*1000);assert r.status==200
   visible.close();check(statistics.median(visible_samples[1:])<=3000,role+' repeated visible navigation target');timings.append({'role':role,'http_samples_ms':samples,'median_ms':statistics.median(samples[1:]),'slowest_http_ms':max(samples),'target_ms':2000,'visible_samples_ms':visible_samples,'visible_median_ms':statistics.median(visible_samples[1:]),'slowest_visible_ms':max(visible_samples),'visible_target_ms':3000,'source_journals':len(sql('SELECT id FROM journal_entries')),'source_lines':len(sql('SELECT id FROM journal_entry_lines')),'server_mode':'Normal MariaDB3306 / single PHP built-in / headless Edge / synthetic small connected report'})
  check(anon.request.get(address+'&format=json').status==401,'Anonymous report JSON denied')
  check(manager.request.get(base+'/report_setup.php').status==403,'Management setup page denied')
  check(manager.request.get(base+'/report_setup.php?format=lines&journal_id=1').status==403,'Management allocation source catalogue denied')
  check(manager.request.post(base+'/report_setup_actions.php',data={'action':'save'}).status==403,'Management direct setup mutation denied')
  check(admin.request.get(base+'/report_setup_actions.php').status==405,'Setup mutation GET denied')
  check(admin.request.post(address).status==405,'Live report POST denied')
  check(admin.request.get(base+'/financial_statements.php?month[]=2026-09&format=json').status==400,'Malformed month scalar rejected')
  check(admin.request.post(base+'/report_setup_actions.php',data='{"action":"save"}',headers={'Content-Type':'application/json'}).status==400,'Missing setup CSRF rejected')
  check(fingerprint()==before,'Read/denial paths preserve every synthetic table and audit row')
  for tab in ['worksheet','trial_balance','income','position','cash_flows','projects','aging']:
   response=page.goto(address+'&report='+tab);check(response.status==200 and page.locator('#fr-state').inner_text().startswith('Live core reports reconciled'),tab+' readable live state')
   if tab in ['worksheet','income','position','cash_flows']:capture('report-'+tab)
  page.goto(address+'&report=worksheet&format=print');page.emulate_media(media='print');capture('worksheet-print');page.pdf(path=str(run/'worksheet-print.pdf'),landscape=True,prefer_css_page_size=True,print_background=True);page.emulate_media(media='screen')
  page.goto(base+'/report_setup.php');check(page.get_by_role('link',name='Prepare setup revision',exact=True).is_visible(),'Setup primary action visible');capture('report-setup-checklist')
  page.get_by_role('link',name='Prepare setup revision',exact=True).click();page.locator('#rs-effective').wait_for();check(page.get_by_role('button',name='Save and close',exact=True).count()==1 and page.get_by_role('button',name='Save draft',exact=True).count()==0,'One Save and close control')
  capture('report-setup-draft');page.get_by_role('button',name='Save and close',exact=True).click();page.wait_for_url('**report_setup.php?saved=1');check('Draft saved' in page.locator('[role=status]').inner_text(),'Save and close lands on clear saved-draft list')
  page.get_by_role('link',name='Resume draft',exact=True).first.click();page.locator('#rs-effective').wait_for();check(page.locator('#rs-status').inner_text().startswith('Draft saved'),'Owner can resume setup draft')
  page.get_by_role('button',name='Review setup',exact=True).click();page.locator('#rs-review').wait_for(state='visible');check(page.locator('#rs-fields').evaluate('(el)=>el.disabled') and page.locator('#rs-effective').is_disabled(),'Review distinguishes read-only draft before submission');capture('report-setup-review')
  page.get_by_role('button',name='Submit setup',exact=True).click();page.wait_for_function('document.querySelector("#rs-status")?.textContent.startsWith("Submitted")');check(page.get_by_role('button',name='Submit setup',exact=True).count()==0,'Submitted revision has no repeat submit authoring control')
  after=fingerprint();changed={k for k in before if before[k]!=after[k]};check(changed<= {'report_configuration_drafts','report_configuration_revisions','report_configuration_state','accounting_write_state','audit_logs'},'Setup writes affect only configuration coordination and audit; posted financial source unchanged')
  page.close();page=manager.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(address+'&report=aging');check(page.get_by_role('link',name='Reporting setup',exact=True).count()==0,'Management report has no setup authoring action');check(page.locator('a[href*="receipt_image"],a[href*="attachment"],img[src*="receipt"]').count()==0,'Management report has no proof UI');capture('report-management-aging')
  page.goto(address+'&report=cash_flows');page.get_by_text('Reporting setup summary',exact=True).click();check('Cash accounts:' in page.locator('.finance-reports').inner_text(),'Management can review configuration summary');capture('report-management-configuration')
  check(not errors,'No browser JavaScript errors');browser.close();passed=True
finally:
 server.terminate()
 try:server.wait(timeout=10)
 except subprocess.TimeoutExpired:server.kill();server.wait(timeout=10)
 log.close();pins={str(p.relative_to(root)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for p in list((root/'includes').glob('report*.php'))+[root/'includes/financial_report_query.php',root/'financial_statements.php',root/'report_setup.php',root/'report_setup_actions.php',root/'assets/js/report_setup.js',root/'assets/css/financial_reports.css',root/'includes/nav.php',root/'reports.php']}
 (run/'result.json').write_text(json.dumps({'passed':passed,'checks':checks,'screenshots':screens,'javascript_errors':errors,'timings':timings,'source_pins':pins,'working_mutation':False,'human_acceptance':False},indent=2),encoding='utf-8')
 if passed:pointer['g2_browser_run']=str(run);pointer_path.write_text(json.dumps(pointer,indent=2),encoding='utf-8')
 print(json.dumps({'passed':passed,'checks':len(checks),'evidence':str(run)}),flush=True)
