"""Read-only U3-A evidence/privacy and native-viewport checks on a synthetic clone.

Run after test_usability_u3_a_browser.py with its fixture. No preparation/posting
is performed here; all application-table rows are fingerprinted before/after.
"""
from pathlib import Path
from urllib.parse import urlencode
import argparse,hashlib,json,os,re,secrets,shutil,socket,subprocess,sys,time
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
ap=argparse.ArgumentParser();ap.add_argument('--fixture',required=True);args=ap.parse_args()
private=(root/'.migration-private').resolve();fixture=Path(args.fixture).resolve()
if not fixture.is_relative_to(private):ap.error('Private fixture required')
f=json.loads(fixture.read_text());db=f['database'];receipts=Path(f['receipt_root']).resolve()
if not re.fullmatch(r'atikha_test_stage1_u3a[a-z0-9]+',db) or not receipts.is_relative_to(private):ap.error('Guarded synthetic U3-A database/evidence required')
php='C:/xampp/php/php.exe';run=private/('u3a-readback-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
def sql(q):
    code='<?php require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(q)+')->fetchAll());'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def state():
    names=sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
    return hashlib.sha256(json.dumps({x['TABLE_NAME']:sql('SELECT * FROM `'+x['TABLE_NAME']+'` ORDER BY 1') for x in names},sort_keys=True).encode()).hexdigest()
before=state()
for p in root.glob('*.php'):
    if p.name not in ['config.php','db_connect.php']:shutil.copy2(p,app/p.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);')
def config(s1=True,s2=True):
    (app/'config.php').write_text('<?php define("STAGE1_WORKSPACE_ENABLED",'+str(s1).lower()+');define("STAGE2_ADVANCES_ENABLED",'+str(s2).lower()+');define("STAGE3_CORRECTIONS_ENABLED",true);define("OCR_JOURNAL_ENABLED",false);define("RECEIPT_UPLOAD_DIR",'+ps(receipts)+');')
config();sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php if(preg_match('~^/(uploads|scripts|includes|vendor)/|/(forecast|audit_ai|external_email|scan_receipt)~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(403);exit;}return false;")
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
        browser=p.chromium.launch(channel='msedge',headless=True)
        def context(email):
            ctx=browser.new_context(viewport={'width':1366,'height':768});ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
            s=json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True));ctx.add_cookies([{'name':'PHPSESSID','value':s['session'],'url':base}]);return ctx
        admin=context('admin@example.invalid');management=context('management@example.invalid');page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        aid=sql('SELECT o.advance_id FROM cash_advance_operations o JOIN posted_evidence_associations e ON e.journal_id=o.journal_id ORDER BY o.id LIMIT 1')[0]['advance_id']
        detail=admin.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':aid})).json()['result']
        docs=[doc for op in detail['operations'] for doc in op.get('attachments',[])]
        check(bool(docs),'Privacy fixture contains real associated synthetic image evidence')
        page.goto(base+'/cash_advances.php?advance_id='+str(aid));check(page.locator('a[href*="receipt_attachment"]').count()>0,'Admin detail exposes existing authorized evidence links')
        mp=management.new_page();mp.on('pageerror',lambda e:errors.append(str(e)));mp.goto(base+'/cash_advances.php?advance_id='+str(aid))
        raw=management.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':aid})).json()['result']
        check('external_approval' not in raw and all('private_review' not in op and 'attachments' not in op for op in raw['operations']),'Evidence-bearing Management JSON excludes private properties')
        check(mp.locator('a[href*="receipt_attachment"]').count()==0 and all(doc['url'] not in mp.content() for doc in docs),'Evidence-bearing Management HTML contains no evidence URLs')
        mp.emulate_media(media='print');mp.wait_for_function("[...document.querySelectorAll('details[data-ca-print]')].every(d=>d.open)")
        check(mp.locator('.ca-journal table').count()==len(raw['operations']) and mp.locator('a[href*="receipt_attachment"]').count()==0,'Evidence-bearing Management print retains complete journals without private links')
        for w,h in [(1366,768),(1920,1080)]:
            mp.set_viewport_size({'width':w,'height':h});mp.evaluate('window.scrollTo(0,0)');mp.screenshot(path=str(run/f'management-evidence-print-{w}.png'),full_page=True)
        mp.emulate_media(media='screen')
        for s1,s2 in [(True,True),(True,False),(False,False)]:
            config(s1,s2)
            for doc in docs:check(management.request.get(base+'/'+doc['url']).status==404,'Management download denied with Stage 1/2 '+str((s1,s2)))
        config()
        first=sql("SELECT a.id FROM cash_advances a JOIN journal_entries j ON j.id=a.release_journal_id WHERE j.reference LIKE 'U3A-%' ORDER BY a.id LIMIT 1")[0]['id']
        current=admin.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':first})).json()['result']
        from datetime import date,timedelta
        prior=(date.fromisoformat(current['as_of'])-timedelta(days=1)).isoformat()
        earlier=admin.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':first,'as_of':prior})).json()['result']
        check(current['due_date']=='2099-01-01' and earlier['due_date']==current['initial_due_date'] and not earlier['due_changes'],'Real extended deadline applies today and preserves prior-date deadline history')
        page.goto(base+'/cash_advances.php?advance_id='+str(first)+'&as_of='+prior)
        check(earlier['due_date'] in page.locator('.ca-status-line').inner_text() and '2099-01-01' not in page.locator('#advance-register').inner_text(),'Historical HTML displays the actual earlier deadline without future history')
        candidates=sql('SELECT o.advance_id, MIN(j.entry_date) first_date, MAX(j.entry_date) last_date FROM cash_advance_operations o JOIN journal_entries j ON j.id=o.journal_id GROUP BY o.advance_id HAVING MIN(j.entry_date)<MAX(j.entry_date) ORDER BY o.advance_id')
        historical=None
        for candidate in candidates:
            now=admin.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':candidate['advance_id']})).json()['result']
            old=admin.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'advance','advance_id':candidate['advance_id'],'as_of':candidate['first_date']})).json()['result']
            if now['status']=='settled' and now['outstanding']=='0.00' and old['outstanding']=='100.00':historical=(candidate,old);break
        check(historical is not None and historical[1]['status']=='outstanding','Actual settled fixture restates to PHP 100.00 outstanding at its release date')
        candidate,old=historical;page.goto(base+'/cash_advances.php?advance_id='+str(candidate['advance_id'])+'&as_of='+candidate['first_date'])
        check(page.locator('.ca-outstanding strong').inner_text()=='PHP 100.00' and page.locator('.ca-status-line .ca-badge').inner_text()=='Outstanding','Historical HTML retains the authoritative earlier balance and settlement state')
        for role,ctx in [('admin',admin),('management',management)]:
            pg=ctx.new_page();pg.on('pageerror',lambda e:errors.append(str(e)))
            for label,url in [('register','/cash_advances.php'),('detail','/cash_advances.php?advance_id='+str(first))]:
                pg.goto(base+url)
                if label=='register':pg.wait_for_function("document.getElementById('ca-status').textContent.includes('loaded')")
                for w,h in [(1366,768),(1920,1080)]:
                    pg.set_viewport_size({'width':w,'height':h});pg.evaluate('window.scrollTo(0,0)');pg.screenshot(path=str(run/f'{role}-{label}-viewport-{w}.png'))
                    check(pg.evaluate('document.documentElement.scrollWidth<=innerWidth'),role+' '+label+' native viewport has no horizontal overflow at '+str(w))
                if label=='detail' and role=='admin':
                    summary=pg.locator('#ca-extension-panel summary');summary.focus();check(summary.evaluate('e=>e===document.activeElement'),'Deadline disclosure accepts real keyboard focus');summary.press('Enter');check(pg.locator('#ca-extension').is_visible(),'Keyboard opens deadline disclosure')
        check(state()==before,'All 34 application tables unchanged across evidence-bearing readback')
        check(not errors,'Readback pages have no uncaught JavaScript errors')
        (run/'results.json').write_text(json.dumps({'checks':checks,'browser':browser.version,'database':db,'page_errors':errors,'read_only_tables':34,'screenshots':sorted(x.name for x in run.glob('*.png'))},indent=2));browser.close()
        print('U3-A readback checks: '+str(checks));print('Private artifacts: '+str(run))
finally:server.terminate();server.wait(timeout=10);log.close()
