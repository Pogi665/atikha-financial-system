from test_pointer import test_pointer
"""Guarded Stage4 browser/read checks. Synthetic sessions are not client acceptance."""
from pathlib import Path
import argparse
import csv
import statistics
import hashlib
import json
import os
import re
import secrets
import shutil
import socket
import subprocess
import sys
import time
from urllib.parse import urlencode, urlparse, parse_qs

root = Path(__file__).resolve().parent.parent
private = (root / '.migration-private').resolve()
sys.path.insert(0, str(private / 'journal-test-deps'))
from playwright.sync_api import sync_playwright

ap = argparse.ArgumentParser()
ap.add_argument('--fixture')
ap.add_argument('--timing', type=int, choices=[500, 5000])
args = ap.parse_args()
pointer = json.loads((test_pointer(private/'stages4-7-current.json')).read_text(encoding='utf-8'))
fixture_arg = args.fixture or pointer['g1_perf_fixture_'+str(args.timing) if args.timing else 'g1_fixture']
path = Path(fixture_arg).resolve()
assert path.is_relative_to(private)
f = json.loads(path.read_text(encoding='utf-8'))
db = f['database']
assert re.fullmatch(r'atikha_test_stage1_s4g1[a-z0-9]+', db)
evidence = Path(f['receipt_root']).resolve()
assert evidence.is_relative_to(private)
case = json.loads(path.with_name('stage4-ledger-fixture.json').read_text(encoding='utf-8'))
assert case['database'] == db and case['passed']
env = os.environ.copy()
env.update(pointer['environment'])
php = 'C:/xampp/php/php.exe'
run = private / ('stages4-browser-' + secrets.token_hex(5))
app = run / 'app'
app.mkdir(parents=True)
sessions = run / 'sessions'
sessions.mkdir()
def ps(value):
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"
def php_json(body):
    result = subprocess.run([php], input='<?php ' + body, text=True, capture_output=True, encoding='utf-8', env=env, timeout=60)
    assert result.returncode == 0 and not result.stderr, 'Private PHP read failed'
    return json.loads(result.stdout)
guard = php_json('echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);')
assert guard[0] in ['', '0'] and all(name in guard[1].split(',') for name in ['curl_exec','mail','fsockopen','stream_socket_client'])
names = set(subprocess.check_output(['git','ls-files','*.php'],cwd=root,text=True).splitlines()) | {'general_ledger.php'}
for name in names:
    if '/' not in name and name not in ['config.php','db_connect.php'] and not name.startswith('_'):
        shutil.copy2(root / name, app / name)
for name in ['includes','assets','scripts','vendor']:
    shutil.copytree(root / name, app / name)
(app / 'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',false);define('STAGE2_ADVANCES_ENABLED',false);define('STAGE3_CORRECTIONS_ENABLED',false);define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(evidence)+');',encoding='utf-8')
dsn = 'mysql:host=' + env.get('ATIKHA_DB_HOST','127.0.0.1') + ';dbname=' + db + ';charset=utf8mb4'
(app / 'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(value) for value in [dsn,env.get('ATIKHA_DB_USER','root'),env.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def sql(query):
    return php_json('require '+ps(root / 'scripts/cli_common.php')+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());')
def fingerprint():
    return {row['TABLE_NAME']:hashlib.sha256(json.dumps(sql('SELECT * FROM `'+row['TABLE_NAME']+'` ORDER BY 1'),sort_keys=True).encode()).hexdigest() for row in sql('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE="BASE TABLE"')}
before = fingerprint()
sock = socket.socket(); sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]; sock.close()
base = 'http://127.0.0.1:' + str(port)
router = run / 'router.php'
router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|forecast_ai|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|external_email|send_email)~',$p)){http_response_code(403);exit;}return false;",encoding='utf-8')
log = (run / 'server.private.log').open('w',encoding='utf-8')
server = subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S','127.0.0.1:'+str(port),'-t',str(app),str(router)],stdout=log,stderr=log,env=env)
checks = []; errors = []; screenshots = []; timing_rows = []; timing_summary = []; passed = False
criteria = case['criteria']
def check(ok,label):
    assert ok,label
    checks.append(label)
    print('PASS: '+label,flush=True)
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2): break
        except OSError: time.sleep(.1)
    with sync_playwright() as pw:
        browser = pw.chromium.launch(channel='msedge',headless=True)
        def context(email):
            ctx=browser.new_context(viewport={'width':1366,'height':768},service_workers='block')
            ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
            session=json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True,encoding='utf-8',env=env))
            ctx.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}]); return ctx
        admin=context('admin@example.invalid');manager=context('management@example.invalid')
        page=admin.new_page();page.on('pageerror',lambda error:errors.append(str(error)))
        def apply():
            with page.expect_response(lambda response:'general_ledger.php?' in response.url and 'format=json' in response.url):page.locator('#gl-apply').click()
            page.wait_for_function("!document.getElementById('gl-apply').disabled")
        def capture(name):
            for width,height in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':width,'height':height});page.evaluate('scrollTo(0,0)')
                check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),name+' has no document overflow at '+str(width))
                target=run/(name+'-'+str(width)+'.png');page.screenshot(path=str(target),full_page=True);screenshots.append(str(target))
            page.set_viewport_size({'width':1366,'height':768})
        if args.timing:
            workload=json.loads(path.with_name('stage4-workload.json').read_text(encoding='utf-8'))
            check(workload['selected_period_lines']==args.timing,'Actual selected-account workload count is '+str(args.timing))
            for role,ctx in [('Admin',admin),('Management',manager)]:
                visible=ctx.new_page();visible.on('pageerror',lambda error:errors.append(str(error)))
                for variant,filters in [('first-page',criteria),('literal-search-page2',dict(criteria,search='S4',page='2'))]:
                    for mode in ['http-json','visible-navigation']:
                        group=[]
                        for sample in range(6):
                            address=base+'/general_ledger.php?'+urlencode(dict(filters,**({'format':'json'} if mode=='http-json' else {})))
                            started=time.perf_counter()
                            if mode=='http-json':
                                response=ctx.request.get(address);payload=response.json()
                                assert response.status==200 and payload['ok'] and payload['data']['period_count']==args.timing
                                response_bytes=len(response.body());ttfb=None
                            else:
                                response=visible.goto(address)
                                visible.locator('#gl-ending').wait_for(state='visible')
                                assert response.status==200 and '6,100.01' in visible.locator('#gl-ending').inner_text()
                                response_bytes=len(response.body());ttfb=visible.evaluate('performance.getEntriesByType("navigation")[0].responseStart')
                            elapsed=(time.perf_counter()-started)*1000
                            record={'workload_lines':args.timing,'role':role,'variant':variant,'mode':mode,'sample':sample,'first_sample':sample==0,'elapsed_ms':elapsed,'ttfb_ms':ttfb,'status':response.status,'response_bytes':response_bytes,'server_mode':'PHP built-in single process / normal MariaDB3306 / headless Edge'}
                            group.append(record);timing_rows.append(record)
                        median=statistics.median(row['elapsed_ms'] for row in group[1:])
                        summary={'workload_lines':args.timing,'role':role,'variant':variant,'mode':mode,'first_ms':group[0]['elapsed_ms'],'repeat_median_ms':median,'slowest_ms':max(row['elapsed_ms'] for row in group),'target_ms':2000 if mode=='http-json' else 3000}
                        timing_summary.append(summary)
                        check(median<=summary['target_ms'],role+' '+variant+' '+mode+' repeat median '+str(median)+'ms within engineering target')
                visible.close()
        else:
            response=page.goto(base+'/general_ledger.php');check(response.status==200 and page.locator('#gl-prompt').is_visible(),'Neutral account prompt, no default financial account')
            check(page.locator('[data-nav-key="general_ledger"]').is_visible(),'General Ledger visibly discoverable in existing navigation')
            page.select_option('#gl-account',criteria['account_id']);page.locator('[name=from]').fill(criteria['from']);page.locator('[name=to]').fill(criteria['to'])
            check(page.locator('#gl-sheet').is_hidden(),'Editing controls does not silently apply a financial filter')
            apply();check('6,100.01' in page.locator('#gl-ending').inner_text(),'Applied exact financial ending survives authoring flags off')
            check(page.locator('#gl-rows tr').count()==25,'Server first-page size is 25')
            check(page.locator('#gl-sheet input,#gl-sheet select,#gl-sheet textarea').count()==0,'Posted table has no directly editable financial cells')
            check(page.locator('#gl-print').is_visible() and page.locator('#gl-apply').bounding_box()['height']>=40,'Apply and full-period print are prominent')
            capture('ledger-admin')
            original=page.locator('#gl-rows').inner_text();ending=page.locator('#gl-ending').inner_text()
            page.locator('[name=search]').fill('no synthetic match 53e3');check(page.locator('#gl-print').get_attribute('aria-disabled')=='true','Unapplied edits disable stale print')
            apply();check(page.locator('#gl-rows tr').count()==1 and 'No movements match' in page.locator('#gl-rows').inner_text() and page.locator('#gl-ending').inner_text()==ending,'Empty search keeps complete-period ending and clear explanation')
            page.locator('[name=search]').fill('%_');apply();check(page.locator('#gl-rows tr').count()==25,'Literal percent/underscore search is not wildcard syntax')
            page.locator('[name=search]').fill('');apply()
            page.locator('[name=from]').fill('9998-01-01');apply()
            check('From must be on or before To' in page.locator('#gl-error').inner_text() and page.locator('#gl-rows').inner_text()==original,'Invalid applied dates preserve last successful rows and plain error')
            check(page.locator('[name=from]').input_value()=='9998-01-01','Failed request retains entered controls')
            page.locator('[name=from]').fill(criteria['from']);apply()
            with page.expect_response(lambda r:'format=json' in r.url):page.locator('#gl-next').click()
            page.wait_for_function("document.getElementById('gl-page-label').textContent.includes('Page 2')")
            data=admin.request.get(base+'/general_ledger.php?'+urlencode(dict(criteria,page='2',format='json'))).json()['data']
            check(page.locator('#gl-rows tr').count()==len(data['rows']) and data['rows'][0]['running_balance']!='0.00','Later-page visible rows retain chronological running balance')
            link=page.locator('#gl-rows a').first.get_attribute('href');page.goto(base+'/'+link)
            check(page.get_by_role('link',name='Back to General Ledger',exact=True).count()==1 and page.locator('.aw-table tbody tr').count()>=2,'View transaction opens complete journal and explicit ledger return')
            page.get_by_role('link',name='Back to General Ledger',exact=True).click();check(parse_qs(urlparse(page.url).query)['page']==['2'],'Returning restores the applied later page')
            page.goto(base+'/reports.php?'+urlencode({'year':criteria['from'][:4],'month':str(int(criteria['from'][5:7]))}))
            live=page.locator('.trial-table a').filter(has_text='S4 Synthetic Bank')
            check(live.count()==1 and parse_qs(urlparse(live.get_attribute('href')).query)['to']==[criteria['to']],'Live TB account drilldown uses the exact cutoff')
            page.goto(base+'/reports.php?snapshot_id='+str(case['snapshot_id']))
            check(page.locator('.trial-table tbody a[href^="general_ledger.php"]').count()==0 and 'Frozen revision' in page.locator('.statement-page').inner_text(),'Frozen TB rows do not imply a frozen ledger through a live link')
            full=admin.request.get(base+'/general_ledger.php?'+urlencode(dict(criteria,search='no match',page='999',format='print')))
            check(full.status==200 and full.text().count('<tr>')>25 and 'Full account period, all movements' in full.text(),'Print response includes full period rather than search/page subset')
            page.goto(base+'/general_ledger.php?'+urlencode(dict(criteria,format='print')));page.emulate_media(media='print');capture('ledger-print');page.pdf(path=str(run/'ledger-print.pdf'),landscape=True,print_background=True)
            page.emulate_media(media='screen');page.goto(base+'/general_ledger.php?'+urlencode(criteria))
            management=manager.new_page();management.on('pageerror',lambda error:errors.append(str(error)));management.goto(base+'/general_ledger.php?'+urlencode(criteria))
            check('6,100.01' in management.locator('#gl-ending').inner_text() and management.locator('#gl-sheet input').count()==0,'Management receives same read-only figures')
            managed=manager.request.get(base+'/general_ledger.php?'+urlencode(dict(criteria,format='json'))).json()
            check(managed['ok'] and not re.search(r'"(?:attachments|receipt_id|draft_id|file_path)"\s*:',json.dumps(managed)),'Management JSON has no private evidence/draft metadata')
            for width,height in [(1366,768),(1920,1080)]:
                management.set_viewport_size({'width':width,'height':height});check(management.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Management table contained at '+str(width));target=run/('ledger-management-'+str(width)+'.png');management.screenshot(path=str(target),full_page=True);screenshots.append(str(target))
            anonymous=browser.new_context();denied=anonymous.request.get(base+'/general_ledger.php?format=json');check(denied.status==401 and not denied.json()['ok'],'Anonymous direct JSON denied')
            check(admin.request.post(base+'/general_ledger.php?format=json').status==405,'Non-GET ledger request rejects without mutation')
            for invalid in ['account_id[]=1','account_id=4294967295','from=2026-02-30','search='+('a'*1001)]:
                r=admin.request.get(base+'/general_ledger.php?format=json&'+invalid);check(r.status in [400,404] and not r.json()['ok'],'Direct invalid selection rejects: '+invalid[:30])
            check(not errors,'No uncaught browser JavaScript errors')
        check(not errors,'No uncaught JavaScript errors in inspected mode')
        browser.close()
    check(fingerprint()==before,'All synthetic tables and audit history unchanged by browser/JSON/print reads')
    passed=True
finally:
    server.terminate()
    try:server.wait(timeout=10)
    except subprocess.TimeoutExpired:server.kill();server.wait(timeout=5)
    log.close()
    pins={name:hashlib.sha256((root/name).read_bytes()).hexdigest() for name in ['general_ledger.php','includes/general_ledger_query.php','assets/js/general_ledger.js','assets/css/general_ledger.css','includes/nav.php','reports.php','journal_transaction.php']}
    if timing_rows:
        with (run/'timings.csv').open('w',newline='',encoding='utf-8') as output:
            writer=csv.DictWriter(output,fieldnames=list(timing_rows[0]));writer.writeheader();writer.writerows(timing_rows)
        (run/'timing-summary.json').write_text(json.dumps(timing_summary,indent=2),encoding='utf-8')
    result={'timing_workload':args.timing,'timing_summary':timing_summary,'passed':passed,'checks':len(checks),'labels':checks,'screenshots':screenshots,'source_sha256':pins,'errors':errors,'synthetic_only':True,'authoring_flags_off':True,'human_walkthrough':False,'physical_device_or_client_acceptance':False}
    (run/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
    p=json.loads((test_pointer(private/'stages4-7-current.json')).read_text());p['g1_timing_'+str(args.timing) if args.timing else 'g1_browser_run']=str(run);(test_pointer(private/'stages4-7-current.json')).write_text(json.dumps(p,indent=2))
    print('Stage4 browser result: '+json.dumps({'passed':passed,'checks':len(checks),'run':str(run)}),flush=True)
