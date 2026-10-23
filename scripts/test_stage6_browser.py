from test_pointer import test_pointer
"""Real isolated Edge budget/approval workflows and dashboard reads; no real login/provider."""
from pathlib import Path
import hashlib,json,os,re,secrets,shutil,socket,statistics,subprocess,sys,time
from urllib.parse import urlparse,parse_qs
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();sys.path.insert(0,str(private/'journal-test-deps'))
from playwright.sync_api import sync_playwright
pointer_path=test_pointer(private/'stages4-7-current.json');pointer=json.loads(pointer_path.read_text(encoding='utf-8'));fixture=Path(pointer['g3_fixture']).resolve();assert fixture.is_relative_to(private)
f=json.loads(fixture.read_text(encoding='utf-8'));db=f['database'];assert re.fullmatch(r'atikha_test_stage1_s5[a-z0-9]+',db);case=json.loads(fixture.with_name('stage6-budget-result.json').read_text(encoding='utf-8'));basecase=json.loads(fixture.with_name('stage5-reports-result.json').read_text(encoding='utf-8'));assert case['passed']
evidence=Path(f['receipt_root']).resolve();assert evidence.is_relative_to(private);env=os.environ.copy();env.update(pointer['environment']);php='C:/xampp/php/php.exe'
run=private/('stages6-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
def php_json(body):
 r=subprocess.run([php],input='<?php '+body,capture_output=True,text=True,encoding='utf-8',env=env,timeout=90);assert r.returncode==0 and not r.stderr,'Private fixture query failed';return json.loads(r.stdout)
guard=php_json('echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);');assert guard[0] in ['','0'] and all(n in guard[1].split(',') for n in ['curl_exec','mail','fsockopen','stream_socket_client'])
for src in root.glob('*.php'):
 if src.name not in ['config.php','db_connect.php'] and not src.name.startswith('_'):shutil.copy2(src,app/src.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
(app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',false);define('STAGE2_ADVANCES_ENABLED',false);define('STAGE3_CORRECTIONS_ENABLED',false);define('OCR_JOURNAL_ENABLED',false);define('GEMINI_API_KEY','');define('RECEIPT_UPLOAD_DIR',"+ps(evidence)+');',encoding='utf-8')
dsn='mysql:host='+env.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4';(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,env.get('ATIKHA_DB_USER','root'),env.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def sql(query):return php_json('require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());')
def fingerprint():return {r['TABLE_NAME']:hashlib.sha256(json.dumps(sql('SELECT * FROM `'+r['TABLE_NAME']+'` ORDER BY 1'),sort_keys=True).encode()).hexdigest() for r in sql('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE="BASE TABLE"')}
before=fingerprint();sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base='http://127.0.0.1:'+str(port)
router=run/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|external_email|send_email)~',$p)){http_response_code(403);exit;}return false;",encoding='utf-8')
log=(run/'server.private.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S','127.0.0.1:'+str(port),'-t',str(app),str(router)],stdout=log,stderr=log,env=env)
checks=[];screens=[];errors=[];timings=[];passed=False;review_request=None
def check(ok,label):assert ok,label;checks.append(label);print('PASS: '+label,flush=True)
try:
 for _ in range(60):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.2):break
  except OSError:time.sleep(.1)
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  def context(email):
   ctx=browser.new_context(viewport={'width':1366,'height':768},service_workers='block');ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());s=json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True,encoding='utf-8',env=env));ctx.add_cookies([{'name':'PHPSESSID','value':s['session'],'url':base}]);return ctx
  admin=context('admin@example.invalid');manager=context('management@example.invalid');anon=browser.new_context();page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  def capture(name):
   for w,h in [(1366,768),(1920,1080)]:
    page.set_viewport_size({'width':w,'height':h});page.evaluate("async()=>{scrollTo({top:0,behavior:'instant'});await new Promise(requestAnimationFrame);await new Promise(requestAnimationFrame)}");check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),name+' no document overflow '+str(w));target=run/(name+'-'+str(w)+'.png');page.screenshot(path=str(target),full_page=True);screens.append(str(target))
   page.set_viewport_size({'width':1366,'height':768})
  address=base+'/budgets.php?month='+case['month'];expense=basecase['accounts']['expense'];project=case['project_a']
  check(anon.request.get(address+'&format=json').status==401,'Anonymous budget JSON denied');check(manager.request.get(address+'&new=1').status==403,'Management budget preparation denied');check(admin.request.get(base+'/budget_actions.php').status==405,'Budget mutation GET denied');check(admin.request.post(base+'/budget_actions.php',data='{"action":"save"}',headers={'Content-Type':'application/json'}).status==400,'Missing budget CSRF denied')
  page.goto(address);check('Effective approved budget' in page.locator('#bw-state').inner_text(),'Effective approved state visible before preparation');capture('budget-admin-actuals');page.get_by_role('link',name='Prepare budget',exact=True).click();page.locator('#bw-grid input').first.wait_for()
  check(page.get_by_role('button',name='Save and close',exact=True).count()==1 and page.get_by_role('button',name='Save draft',exact=True).count()==0,'One Save and close action')
  ops=page.locator(f'input[data-account="{expense}"][data-project="operations"]');ops.fill('-1');page.get_by_role('button',name='Save and close',exact=True).click();page.locator('#bw-error').wait_for(state='visible');check(ops.input_value()=='-1' and 'zero or positive' in page.locator('#bw-error').inner_text() and ops.evaluate("el=>el.classList.contains('budget-input-error')"),'Server validation identifies exact cell and retains entered value')
  ops.fill('10,000');check(page.locator('#bw-error').is_hidden(),'Editing the invalid cell clears its obsolete validation message');page.locator('#bw-project').select_option(str(project));page.get_by_role('button',name='Add column',exact=True).click();page.locator(f'input[data-account="{expense}"][data-project="{project}"]').fill('4000');check('14,000.00' in page.locator('#bw-total').inner_text(),'Worksheet draft total uses exact grouped editable values');capture('budget-editable-grid')
  attempts=[]
  def lose_response(route):
   payload=route.request.post_data_json;attempts.append(payload);route.fetch();route.abort()
  page.route('**/budget_actions.php',lose_response,times=1);page.get_by_role('button',name='Save and close',exact=True).click();page.wait_for_function("!document.querySelector('#bw-save').disabled");check('could not be confirmed' in page.locator('#bw-error').inner_text() and page.locator('#bw-form').count()==1,'Uncertain save response stays on entered draft and explains recovery');key=attempts[0]['request_key'];check(len(attempts)==1 and len(sql("SELECT id FROM budget_revision_drafts WHERE request_key='"+key+"'"))==1,'Lost response never automatically replays a committed save')
  page.get_by_role('button',name='Save and close',exact=True).click();page.wait_for_url('**saved=1');check('Draft saved' in page.locator('.fr-status[role=status]').inner_text(),'Explicit retry recovers save then closes to draft list');check(len(sql("SELECT id FROM budget_revision_drafts WHERE request_key='"+key+"'"))==1,'Recovered save has one private draft identity')
  page.get_by_role('link',name='Resume draft',exact=True).first.click();page.locator('#bw-grid input').first.wait_for();draft_id=parse_qs(urlparse(page.url).query)['draft_id'][0];check(page.locator(f'input[data-account="{expense}"][data-project="operations"]').input_value()=='10000.00','Resumed draft restores canonical amount');check(manager.request.get(base+'/budgets.php?draft_id='+draft_id+'&format=json').status==403,'Management cannot access owner-private draft directly')
  def submit():
   page.get_by_role('button',name='Review budget',exact=True).click();page.locator('#bw-review').wait_for(state='visible');check(page.locator('#bw-grid input').first.is_disabled(),'Budget Review disables editable grid');page.get_by_role('button',name='Submit for Approval',exact=True).click();page.wait_for_url('**revision_id=*');return int(parse_qs(urlparse(page.url).query)['revision_id'][0])
  candidate=submit();check('Submitted' in page.locator('#bw-selected').inner_text(),'Submitted immutable budget state visible');check(admin.request.get(address+'&format=json').json()['data']['actuals']['approved_total']=='12000.00','Pending 14000 leaves approved 12000 authoritative');capture('budget-admin-submitted')
  page.close();page=manager.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(base+'/management_reviews.php');link=page.locator(f'a[href="budgets.php?revision_id={candidate}"]');check(link.count()==1 and 'Open' in link.inner_text(),'Management finds pending budget from review queue');capture('budget-review-queue');link.click();page.get_by_role('button',name='Approve budget',exact=True).wait_for(state='visible');check(page.get_by_role('button',name='Approve budget',exact=True).is_visible(),'Management approval task prominent')
  page.get_by_role('button',name='Return with reason',exact=True).click();check('reason' in page.locator('#bw-error').inner_text(),'Return explains required reason');page.locator('#bw-reason').fill('Adjust the project allocation in a new revision');page.get_by_role('button',name='Return with reason',exact=True).click();page.wait_for_function("document.querySelector('#bw-selected')?.textContent.includes('Returned')");check(manager.request.get(address+'&format=json').json()['data']['actuals']['approved_total']=='12000.00','Returning pending budget preserves prior effective approval');capture('budget-returned')
  page.close();page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(base+'/budgets.php?revision_id='+str(candidate));page.get_by_role('link',name='Prepare new revision from this budget',exact=True).click();page.locator('#bw-grid input').first.wait_for();page.locator(f'input[data-account="{expense}"][data-project="operations"]').fill('11000.00');replacement=submit();check(replacement!=candidate,'Returned content creates separate submitted revision')
  page.close();page=manager.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(base+'/budgets.php?revision_id='+str(replacement));capture('budget-management-approval')
  with page.expect_request(lambda r:r.url.endswith('budget_actions.php') and r.method=='POST') as sent:page.get_by_role('button',name='Approve budget',exact=True).click()
  review_request=sent.value.post_data_json;page.wait_for_function("document.querySelector('#bw-selected')?.textContent.includes('Approved')");check('Effective approved version' in page.locator('#bw-selected').inner_text(),'Approval visibly switches effective approved version');check(manager.request.get(address+'&format=json').json()['data']['actuals']['approved_total']=='15000.00','Approved 15000 becomes authoritative live budget');retry=manager.request.post(base+'/budget_actions.php',data=json.dumps(review_request),headers={'Content-Type':'application/json'});check(retry.status==200 and retry.json()['data']['duplicate'],'Exact approval response-loss retry recovers committed result');capture('budget-management-approved')
  page.goto(address+'&format=print');page.emulate_media(media='print');capture('budget-print');page.pdf(path=str(run/'budget-print.pdf'),landscape=True,prefer_css_page_size=True,print_background=True);page.emulate_media(media='screen')
  for role,ctx in [('Admin',admin),('Management',manager)]:
   samples=[];visible_samples=[];view=ctx.new_page()
   for n in range(6):
    start=time.perf_counter();r=ctx.request.get(address+'&format=json');samples.append((time.perf_counter()-start)*1000);assert r.status==200 and r.json()['data']['actuals']['approved_total']=='15000.00'
    start=time.perf_counter();r=view.goto(address);view.locator('#bw-state').wait_for(state='visible');visible_samples.append((time.perf_counter()-start)*1000);assert r.status==200
   view.close();check(statistics.median(samples[1:])<=2000,role+' budget HTTP repeat target');check(statistics.median(visible_samples[1:])<=3000,role+' budget visible repeat target');timings.append({'role':role,'http_samples_ms':samples,'visible_samples_ms':visible_samples,'http_median_ms':statistics.median(samples[1:]),'visible_median_ms':statistics.median(visible_samples[1:]),'http_slowest_ms':max(samples),'visible_slowest_ms':max(visible_samples),'server_mode':'single PHP built-in / normal MariaDB3306 / headless Edge / small synthetic mixed case'})
  after=fingerprint();changed={k for k in before if before[k]!=after[k]};check(changed<={'budget_revision_roots','budget_revision_drafts','budget_revisions','budget_revision_allocations','budget_review_events','audit_logs','Notifications','notifications','accounting_write_state'},'Budget authoring and decisions preserve every posted financial/evidence/master table')
  for role,ctx in [('Admin',admin),('Management',manager)]:
   page.close();page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   requests=[];page.on('request',lambda r:requests.append(r.url));page.goto(base+'/dashboard.php',wait_until='networkidle');check(not any('forecast_ai.php' in u for u in requests),'Offline '+role+' dashboard makes no forecasting request');check(page.locator('.ui-kpi').count()==6 and 'Cash Balance' in page.locator('.ui-kpis').inner_text(),'Both-role dashboard has scoped financial signals');capture('budget-dashboard-'+role.lower())
  check(not errors,'No budget/dashboard browser JavaScript errors');browser.close();passed=True
finally:
 server.terminate()
 try:server.wait(timeout=10)
 except subprocess.TimeoutExpired:server.kill();server.wait(timeout=10)
 log.close();files=['budgets.php','budget_actions.php','dashboard.php','management_reviews.php','review_actions.php','forecast_ai.php','includes/budget_revisions.php','includes/budget_actuals.php','includes/dashboard_reporting.php','includes/budget_query.php','includes/forecast_query.php','includes/financial_report_query.php','includes/management_dashboard.php','includes/gemini_client.php','includes/nav.php','assets/js/budget_workspace.js','assets/js/management-dashboard.js','assets/css/budget_workspace.css'];pins={n:hashlib.sha256((root/n).read_bytes()).hexdigest() for n in files};(run/'result.json').write_text(json.dumps({'passed':passed,'checks':checks,'screenshots':screens,'javascript_errors':errors,'timings':timings,'source_pins':pins,'provider_called':False,'human_acceptance':False},indent=2),encoding='utf-8')
 if passed:pointer['g3_browser_run']=str(run);pointer_path.write_text(json.dumps(pointer,indent=2),encoding='utf-8')
 print(json.dumps({'passed':passed,'checks':len(checks),'evidence':str(run)}),flush=True)
