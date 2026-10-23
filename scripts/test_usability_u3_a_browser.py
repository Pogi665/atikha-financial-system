from browser_workspace_helpers import open_advance_filters
"""U3-A real PHP/Edge checks on a fresh guarded synthetic full-schema clone.
Fixture preparation/extension writes are separate from fingerprinted read phases.
No working config, sessions, evidence or provider requests are used.
"""
from pathlib import Path
from urllib.parse import urlencode
import argparse,copy,hashlib,json,os,re,secrets,shutil,socket,subprocess,sys,time
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--browser',default='msedge');args=parser.parse_args()
private=(root/'.migration-private').resolve();path=Path(args.fixture).resolve()
if not path.is_relative_to(private):parser.error('Private fixture required')
fixture=json.loads(path.read_text(encoding='utf-8'));db=fixture['database'];evidence=Path(fixture['receipt_root']).resolve()
if not re.fullmatch(r'atikha_test_stage1_u3a[a-z0-9]+',db) or not evidence.is_relative_to(private):parser.error('Fresh guarded U3-A clone/private evidence required')
php=r'C:\xampp\php\php.exe';run=private/('u3a-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
prep=subprocess.run([php,str(root/'scripts/test_usability_u3_a_fixture.php'),'--fixture='+str(path)],capture_output=True,text=True)
(run/'preparation.stderr.log').write_text(prep.stderr)
if prep.returncode:raise RuntimeError(prep.stderr)
cases=json.loads(prep.stdout);(run/'cases.json').write_text(prep.stdout)
for p in root.glob('*.php'):
    if p.name not in ['config.php','db_connect.php']:shutil.copy2(p,app/p.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);')
def config(stage1=True,stage2=True,stage3=True):
    (app/'config.php').write_text('<?php '+''.join('define('+ps(k)+','+str(v).lower()+');' for k,v in [('STAGE1_WORKSPACE_ENABLED',stage1),('STAGE2_ADVANCES_ENABLED',stage2),('STAGE3_CORRECTIONS_ENABLED',stage3),('OCR_JOURNAL_ENABLED',False)])+'define(\'RECEIPT_UPLOAD_DIR\','+ps(evidence)+');')
config()
def sql(query):
    code='<?php require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def state():
    tables=sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
    return hashlib.sha256(json.dumps({r['TABLE_NAME']:sql('SELECT * FROM `'+r['TABLE_NAME']+'` ORDER BY 1') for r in tables},sort_keys=True).encode()).hexdigest()
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads|scripts|includes|vendor)/~',$p)||preg_match('~^/(forecast_ai|ocr_extract|gemini|anomaly_scan|audit_summary|external_email_actions|send_email)~',$p)){http_response_code(403);exit('Blocked isolated provider/action boundary');}return false;")
log=(run/'server.log').open('w');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0;errors=[]
def check(ok,label):
    global checks
    if not ok:raise AssertionError(label)
    checks+=1;print('PASS: '+label,flush=True)
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email):
            c=browser.new_context(viewport={'width':1366,'height':768});c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda r:r.abort());session=login(email);c.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}]);return c
        admin=context('admin@example.invalid');management=context('management@example.invalid');page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        def loaded():page.wait_for_function("document.getElementById('ca-status').textContent.includes('loaded')")
        def apply():page.locator('#ca-filters button').click();loaded()
        def capture(label):
            for w,h in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':w,'height':h});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),label+' fits '+str(w));page.screenshot(path=str(run/(label+'-'+str(w)+'.png')),full_page=True)
        before=state();page.goto(base+'/cash_advances.php');loaded()
        check(page.locator('[data-nav-key="cash_advances"][aria-current="page"]').count()==1,'Advance register retains U2-C current location')
        check(page.locator('#ca-health').is_visible() and not page.locator('#ca-health').evaluate("e=>e.classList.contains('ca-health-warning')"),'Healthy independent reconciliation is visible')
        check(not page.locator('#ca-reconciliation-details').get_attribute('open'),'Full healthy reconciliation is secondary')
        page.locator('#ca-health-link').click();check(page.locator('#ca-reconciliation-details').get_attribute('open') is not None and page.locator('#ca-reconciliation-details summary').evaluate('e=>e===document.activeElement'),'Reconciliation shortcut opens details and focuses its summary');page.locator('#ca-reconciliation-details summary').click()
        page.locator('[name="search"]').fill(cases['marker']);apply()
        check(page.locator('#ca-rows tr').count()==25 and '26 matching' in page.locator('#ca-page').inner_text(),'Real synthetic matching advances use two 25-row pages')
        check('PHP 12,500.00' in page.locator('#ca-totals').inner_text() and 'all 26 matching' in page.locator('#ca-totals-basis').inner_text(),'All-page totals retain exact server amounts with separators')
        check(cases['marker'] in page.locator('#ca-rows').inner_text() and '<training & community>' in page.locator('#ca-rows').inner_text(),'Purpose and long project labels render as safe literal text')
        check('No posted advances' not in page.locator('#ca-rows').inner_text(),'Populated results are distinct from empty state')
        page.locator('.ca-scope-details>summary').click();scope=page.locator('#ca-applied-filters').inner_text();health=page.locator('#ca-health-message').inner_text()
        page.locator('[name="search"]').fill('unsaved filter');page.locator('#ca-next').click();loaded()
        check(page.locator('#ca-rows tr').count()==1 and 'rows 26-26 of 26' in page.locator('#ca-print-scope').inner_text(),'Real second page retains all-match count and page-only scope')
        check(page.locator('#ca-applied-filters').inner_text()==scope and 'Changes not applied' in page.locator('#ca-filter-status').inner_text(),'Pagination retains applied filters while labelling unapplied edits')
        page.locator('#ca-prev').click();loaded();page.locator('[name="search"]').fill(cases['marker']);apply();capture('admin-register')
        actual=admin.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'register','search':cases['marker']})).json()['result']
        for selector in ['#ca-totals','#ca-rows','#ca-basis','#ca-applied-filters','#ca-print-scope','#ca-health-message']:
            check(bool(page.locator(selector).inner_text()),'Successful snapshot contains '+selector)
        old={s:page.locator(s).inner_text() for s in ['#ca-totals','#ca-rows','#ca-basis','#ca-applied-filters','#ca-print-scope','#ca-health-message']}
        def fail(route):route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'error':'Synthetic temporary read failure'}))
        page.route('**/cash_advance_actions.php?*',fail);page.locator('[name="as_of"]').fill(cases['release_date']);page.locator('[name="search"]').fill('failed filter');page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-error').textContent.includes('previously')")
        check(all(page.locator(s).inner_text()==v for s,v in old.items()),'Failed refresh preserves rows/totals/date/filters/reconciliation atomically');capture('register-refresh-failed');page.unroute('**/cash_advance_actions.php?*',fail)
        malformed=copy.deepcopy(actual);malformed['reconciliation']=[{'ok':False,'errors':[]}]
        def malformed_response(route):route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'result':malformed}))
        page.route('**/cash_advance_actions.php?*',malformed_response);page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-error').textContent.includes('previously')")
        check(all(page.locator(s).inner_text()==v for s,v in old.items()),'Malformed success response cannot partially replace the applied snapshot');page.unroute('**/cash_advance_actions.php?*',malformed_response)
        page.evaluate("() => {window.u3fetch=window.fetch;window.pending=[];window.fetch=(url,options)=>String(url).includes('action=register')?new Promise(resolve=>window.pending.push(resolve)):window.u3fetch(url,options);}")
        page.locator('[name="search"]').fill('older');page.locator('#ca-filters button').click();page.locator('[name="search"]').fill('newer');page.locator('#ca-filters button').click();page.wait_for_function('window.pending.length===2')
        newest={**actual,'rows':[],'count':0,'pages':1,'page':1};page.evaluate('(d)=>window.pending[1](new Response(JSON.stringify({ok:true,result:d}),{status:200}))',newest);loaded();page.evaluate('(d)=>window.pending[0](new Response(JSON.stringify({ok:true,result:d}),{status:200}))',actual);page.evaluate('()=>new Promise(r=>setTimeout(r,0))')
        check('Search: newer' in page.locator('#ca-applied-filters').inner_text() and 'all 0 matching' in page.locator('#ca-totals-basis').inner_text(),'Deliberately late response cannot replace newer no-match result');page.evaluate('()=>{window.fetch=window.u3fetch;}')
        page.locator('[name="as_of"]').fill(cases['today']);page.locator('[name="search"]').fill('U3A-NO-SUCH-ADVANCE');apply();check('No posted advances match' in page.locator('#ca-rows').inner_text(),'Actual no-match query has contextual empty guidance');capture('register-no-matches')
        # Presentation double preserves all real amounts but injects a discrepancy outside a filtered account.
        warning=copy.deepcopy(actual);warning['reconciliation'][-1]['ok']=False;warning['reconciliation'][-1]['errors']=['Synthetic unlinked control line outside displayed filters'];warning['reconciliation'][-1]['difference']='1.00'
        def discrepancy(route):route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'result':warning}))
        page.route('**/cash_advance_actions.php?*',discrepancy);open_advance_filters(page);page.locator('[name="control_account_id"]').select_option(str(cases['controls'][0]));page.locator('[name="search"]').fill(cases['marker']);apply()
        check(page.locator('#ca-health').evaluate("e=>e.classList.contains('ca-health-warning')") and 'Integrity discrepancy' in page.locator('#ca-health-message').inner_text(),'Unfiltered discrepancy remains prominent outside display filters')
        page.locator('#ca-health-link').click();check('outside displayed filters' in page.locator('#ca-reconciliation').inner_text(),'Full discrepancy details remain inspectable');capture('register-discrepancy-double');page.unroute('**/cash_advance_actions.php?*',discrepancy)
        page.goto(base+'/cash_advances.php');loaded();page.locator('[name="search"]').fill(cases['marker']);apply()
        page.emulate_media(media='print');page.wait_for_function("[...document.querySelectorAll('details[data-ca-print]')].every(d=>d.open)")
        check(page.locator('#ca-filters').is_hidden() and page.locator('#ca-print-scope').is_visible() and page.locator('#ca-totals-basis').is_visible(),'Printed register keeps scope/totals and hides mutation/filter controls');capture('register-print');page.emulate_media(media='screen');page.wait_for_function("!document.getElementById('ca-reconciliation-details').open");check(not page.locator('#ca-reconciliation-details').get_attribute('open'),'Print restores disclosure state on return to screen')
        first=str(cases['ids'][0]);page.goto(base+'/cash_advances.php?advance_id='+first)
        check('PHP 10,000.00' in page.locator('.ca-advance-summary').inner_text() and page.locator('.ca-operation').count()==1,'Detail shows posted release and outstanding without counting drafts')
        check('Not overdue at this date' in page.locator('.ca-status-line').inner_text(),'Due today is separate from overdue status')
        check('advance_id='+first in page.get_by_role('link',name='Record liquidation',exact=True).get_attribute('href') and 'advance_id='+first in page.get_by_role('link',name='Return unused cash',exact=True).get_attribute('href'),'Both settlement actions bind the original advance identity')
        check(page.locator('#ca-extension-panel').get_attribute('open') is None,'Deadline extension is secondary')
        capture('admin-advance-detail')
        journal_link=page.get_by_role('link',name=re.compile('View recorded transaction #')).first;response=page.goto(base+'/'+journal_link.get_attribute('href'));check(response.status==200 and page.locator('.correction-details').count()==1,'Timeline opens exact existing journal independently of list filters')
        page.goto(base+'/cash_advances.php?advance_id='+first+'&as_of='+cases['release_date'])
        check('Historical view' in page.locator('.ca-date-basis').inner_text() and page.get_by_role('link',name='Record liquidation',exact=True).count()==0 and page.locator('#ca-extension').count()==0,'Historical view has no settlement/extension actions')
        check('as_of='+cases['release_date'] in page.get_by_role('link',name='Back to advance register').get_attribute('href'),'Detail Back retains the applied accounting date')
        check(page.get_by_role('link',name="View today's advance").count()==1,'Historical view offers explicit current-date navigation');capture('advance-historical')
        for status,aid in cases['samples'].items():
            page.goto(base+'/cash_advances.php?advance_id='+str(aid));check(page.locator('.ca-settlement-status strong').inner_text().lower().replace(' ','_')==status,'Real detail retains '+status+' lifecycle status')
            if status in ['settled','cancelled','replaced']:check(page.get_by_role('link',name='Record liquidation',exact=True).count()==0,'No settlement action for '+status)
            if status in ['cancelled','replaced']:check(page.get_by_role('link',name='View posted correction',exact=True).count()>0,'Retired advance retains posted correction comparison')
        if 'settled' in cases['samples']:
            page.goto(base+'/cash_advances.php?advance_id='+str(cases['samples']['settled']));check(page.locator('.ca-operation').count()>=2,'Settled timeline retains all original posted operations');capture('advance-settled')
        page=management.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(base+'/cash_advances.php');loaded();capture('management-register')
        check(page.locator('[data-nav-key="payment"]').count()==0 and page.locator('a[href="cash_advance_entry.php?workflow_kind=advance_release"]').count()==0,'Management gets read navigation without authoring tasks')
        page.goto(base+'/cash_advances.php?advance_id='+first);check(page.locator('#ca-extension').count()==0 and page.get_by_role('link',name='Record liquidation',exact=True).count()==0,'Management cannot extend or start settlement')
        raw=management.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':first})).json()['result']
        check('external_approval' not in raw and all('private_review' not in op and 'attachments' not in op for op in raw['operations']),'Management detail JSON excludes private review/images/approval context')
        capture('management-detail');page.emulate_media(media='print');page.wait_for_function("[...document.querySelectorAll('details[data-ca-print]')].every(d=>d.open)");check(page.locator('.app-sidebar').is_hidden() and page.locator('.ca-journal table').first.is_visible(),'Printed Management detail includes full journals and hides sidebar');capture('management-detail-print');page.emulate_media(media='screen')
        for s1,s2 in [(False,False),(True,False),(False,True)]:
            config(s1,s2,True);check(management.request.get(base+'/cash_advances.php').status==503,'Advance surface retains Stage 1/2 gate '+str((s1,s2)))
        config()
        # Explicit response doubles cover an entirely empty register and a first-load failure.
        page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        empty={**actual,'rows':[],'count':0,'pages':1,'page':1,'totals':{k:'0.00' for k in actual['totals']},'reconciliation':[]}
        def empty_html(route):
            response=route.fetch();body=response.text();assert '"has_posted_advances":true' in body
            route.fulfill(response=response,body=body.replace('"has_posted_advances":true','"has_posted_advances":false',1))
        def empty_result(route):route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'result':empty}))
        page.route('**/cash_advances.php',empty_html);page.route('**/cash_advance_actions.php?*',empty_result);page.goto(base+'/cash_advances.php');loaded()
        check('No posted advances yet' in page.locator('#ca-rows').inner_text() and 'PHP 0.00' in page.locator('#ca-totals').inner_text(),'Empty-register presentation double differs from a filtered no-match result');capture('register-empty-double')
        page.unroute('**/cash_advances.php',empty_html);page.unroute('**/cash_advance_actions.php?*',empty_result);page.route('**/cash_advance_actions.php?*',fail);page.goto(base+'/cash_advances.php');page.wait_for_function("document.getElementById('ca-error').textContent.includes('have not loaded')")
        check(page.locator('#ca-print').is_disabled() and 'No successful' in page.locator('#ca-health-message').inner_text(),'First-load failure cannot claim reconciliation or print a successful register');page.unroute('**/cash_advance_actions.php?*',fail)
        check(page.locator('#ca-health').get_attribute('data-state')=='unavailable' and page.locator('#ca-health').evaluate("e=>getComputedStyle(e).backgroundColor")== 'rgb(241, 245, 249)' and not page.locator('#ca-health').evaluate("e=>e.classList.contains('ca-health-warning')"),'Initial HTTP failure has neutral unavailable styling instead of healthy/discrepancy styling')
        check('not verified' in page.locator('#ca-health-message').inner_text() and page.locator('#ca-health-link').is_hidden() and page.locator('#ca-reconciliation-details').is_hidden() and 'No successful' in page.locator('#ca-reconciliation-basis').text_content(),'Initial HTTP failure hides unverified reconciliation link/details and labels its basis')
        page.locator('#ca-health-link').evaluate('e=>e.click()');check(page.locator('#ca-reconciliation-details').get_attribute('open') is None,'Initial failure cannot open an empty reconciliation disclosure');capture('register-first-load-unavailable')
        def invalid_initial(route):route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'result':malformed}))
        page.route('**/cash_advance_actions.php?*',invalid_initial);page.goto(base+'/cash_advances.php');page.wait_for_function("document.getElementById('ca-health').dataset.state==='unavailable'")
        check(page.locator('#ca-health-link').is_hidden() and page.locator('#ca-reconciliation-details').is_hidden() and page.locator('#ca-print').is_disabled(),'Unverifiable first HTTP response cannot expose verified details/printing');page.unroute('**/cash_advance_actions.php?*',invalid_initial)
        page.locator('#ca-filters button').click();loaded();check(page.locator('#ca-health').get_attribute('data-state')=='healthy' and page.locator('#ca-health-link').is_visible() and page.locator('#ca-reconciliation-details').is_visible(),'Real successful retry restores verified health and full reconciliation')
        check(state()==before,'All 34 application tables unchanged across real read/navigation/print and response-double phases')
        # Deliberate isolated deadline writes follow, outside the read-only fingerprint pass.
        page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(base+'/cash_advances.php?advance_id='+first);page.locator('#ca-extension-panel summary').click()
        page.locator('#ca-extension [name="due_date"]').fill('2099-01-01');page.locator('#ca-extension [name="reason"]').fill('Synthetic audited extension')
        failed_before=state()
        def extension_failure(route):route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'error':'Synthetic extension failure'}))
        page.route('**/cash_advance_actions.php',extension_failure);page.locator('#ca-extension button').click();page.wait_for_function("document.getElementById('ca-error').textContent.includes('Synthetic extension failure')")
        check(page.locator('#ca-extension [name="reason"]').input_value()=='Synthetic audited extension' and page.locator('#ca-extension [name="due_date"]').input_value()=='2099-01-01','Failed extension retains entered fields');check(state()==failed_before,'Failed extension has no application writes');capture('extension-failed');page.unroute('**/cash_advance_actions.php',extension_failure)
        # Capture a second tab's old revision before the successful extension.
        stale=admin.new_page();stale.goto(base+'/cash_advances.php?advance_id='+first);stale.locator('#ca-extension-panel summary').click();stale.locator('[name="due_date"]').fill('2099-02-01');stale.locator('[name="reason"]').fill('Synthetic stale request')
        with page.expect_navigation(wait_until='domcontentloaded'):
            page.locator('#ca-extension button').click()
        page.wait_for_function("document.querySelector('.ca-status-line')?.textContent.includes('2099-01-01')")
        check('Synthetic audited extension' in page.locator('details[data-ca-print]').all_text_contents() or 'Synthetic audited extension' in page.locator('#advance-register').text_content(),'Successful extension is preserved in deadline history')
        saved=state();stale.locator('#ca-extension button').click();stale.wait_for_function("document.getElementById('ca-error').textContent.includes('changed')")
        check(stale.locator('[name="reason"]').input_value()=='Synthetic stale request' and state()==saved,'Stale revision fails without replacing deadline/history or entered fields')
        check(not errors,'U3-A pages have no uncaught JavaScript errors')
        (run/'results.json').write_text(json.dumps({'checks':checks,'browser':browser.version,'database':db,'page_errors':errors,'read_only_tables':34,'fixture_preparation':'26 real normal-API releases plus synthetic masters','presentation_doubles':['malformed/stale/failed response','out-of-filter reconciliation warning'],'screenshots':sorted(p.name for p in run.glob('*.png'))},indent=2))
        browser.close();print('U3-A checks: '+str(checks));print('Private artifacts: '+str(run))
finally:server.terminate();server.wait(timeout=10);log.close()
