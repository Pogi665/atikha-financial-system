"""U2 follow-up current action traces and authoritative coverage display checks.
Copies code into a private app; never copies production config, sessions or receipts.
"""
from urllib.parse import urlencode
from datetime import datetime,timedelta
from decimal import Decimal
import argparse,hashlib,json,os,re,secrets,shutil,socket,struct,subprocess,sys,time,zlib
from pathlib import Path
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--browser',default='msedge');args=parser.parse_args()
fixture_path=Path(args.fixture).resolve()
if not fixture_path.is_relative_to(root/'.migration-private'):parser.error('Private disposable fixture required')
fixture=json.loads(fixture_path.read_text());db=fixture['database']
if not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',db):parser.error('Disposable database required')
php=r'C:\xampp\php\php.exe';evidence=Path(fixture['receipt_root']).resolve()
if not evidence.is_relative_to(root/'.migration-private'):parser.error('Private evidence required')
run=root/'.migration-private'/('u2followup-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(stage1=True,stage2=True,stage3=True,ocr=False):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',"+str(stage1).lower()+");define('STAGE2_ADVANCES_ENABLED',"+str(stage2).lower()+");define('STAGE3_CORRECTIONS_ENABLED',"+str(stage3).lower()+");define('OCR_JOURNAL_ENABLED',"+str(ocr).lower()+");define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
config()
def sql(query):
    code='<?php require '+ps(str(root/'scripts/cli_common.php'))+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
def png():
    def chunk(k,d):return struct.pack('>I',len(d))+k+d+struct.pack('>I',zlib.crc32(k+d)&0xffffffff)
    rows=b''.join(b'\0'+bytes([secrets.randbelow(255),50,100,255])*64 for _ in range(64))
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',64,64,8,6,0,0,0))+chunk(b'IDAT',zlib.compress(rows))+chunk(b'IEND',b'')
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php if(preg_match('~^/(?:uploads/|scripts/|includes/|vendor/|forecast_ai\\.php)~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(403);exit;}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)

checks=0;trace=[];errors=[];success=False

def check(ok,label):
    global checks
    if not ok:raise AssertionError(label)
    checks+=1;print('PASS: '+label,flush=True)
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as playwright:
        browser=playwright.chromium.launch(channel=args.browser,headless=True)
        ctx=browser.new_context(viewport={'width':1366,'height':768});ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
        session=login('admin@example.invalid');ctx.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}]);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        def ready():page.wait_for_function("document.getElementById('aw-existing') && (!document.getElementById('aw-existing').disabled || !document.getElementById('aw-posted').hidden)")
        def choose(control,value):
            label=control.locator('option[value="'+str(value)+'"]').text_content();combo=control.locator('..').locator('.aw-record-selector');combo.get_by_role('combobox').fill(label);combo.locator('[role="option"][data-value="'+str(value)+'"]').click()
        def capture(name):
            for w,h in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':w,'height':h});page.evaluate('window.scrollTo(0,0)');check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),name+' fits '+str(w));page.screenshot(path=str(run/(name+'-'+str(w)+'.png')),full_page=True)
            page.set_viewport_size({'width':1366,'height':768})
        def review():
            with page.expect_response(lambda r:r.url.endswith('/accounting_actions.php') and r.request.method=='POST' and r.request.post_data_json.get('action')=='review') as response:page.locator('#aw-review').click()
            data=response.value.json();assert data.get('ok'),data;page.wait_for_selector('#aw-review-panel:not([hidden])');ready();return data['result']
        def filled(route,account):
            page.goto(base+'/'+route);ready();choose(page.locator('#aw-party'),fixture['party']);page.locator('#aw-purpose').fill('Synthetic follow-up ordinary task');choose(page.locator('#aw-cash-account'),fixture['bank']);page.locator('#aw-cash-amount').fill('1000');choose(page.locator('[data-field="account_id"]').first,account)
        for task,route,account,side in [('payment','cash_disbursement.php',fixture['training'],'debit'),('receipt','cash_receipt.php',fixture['income'],'credit')]:
            # Preparation/navigation to the declared Dashboard start is not a measured task action.
            page.goto(base+'/dashboard.php',wait_until='networkidle');url=page.url;page.locator('.app-sidebar a[href="'+route+'"]').click();ready()
            events=[('navigation','Dashboard sidebar direct task to '+route)]
            check(page.url!=url and page.url.endswith('/'+route),'One navigation control reaches '+task+' editor')
            choose(page.locator('#aw-party'),fixture['party']);events.append(('field','Select party'))
            page.locator('#aw-purpose').fill('Synthetic follow-up '+task);events.append(('field','Enter purpose'))
            choose(page.locator('#aw-cash-account'),fixture['bank']);events.append(('field','Select cash or bank account'))
            page.locator('#aw-cash-amount').fill('1000');events.append(('field','Enter one common amount'))
            choose(page.locator('[data-field="account_id"]').first,account);events.append(('field','Select counterpart account'))
            check(page.locator('[data-field="'+side+'_amount"]').first.input_value()=='1000','One amount also sets '+task+' counterpart')
            r=review();events.append(('progression','Review (includes saving)'))
            check(r['coverage'][side]=={'eligible':'1000.00','covered':'0.00'},task+' authoritative amounts unchanged')
            check(page.locator('.aw-coverage').inner_text()=='No monetary support \u2014 '+side+': ₱0.00 supported of ₱1,000.00',task+' formats exact zero and thousand')
            capture(task+'-coverage')
            page.locator('#aw-post').focus();check(page.evaluate("document.activeElement.id==='aw-post'"),task+' confirmation has keyboard focus');page.keyboard.press('Enter');page.wait_for_selector('#aw-posted:not([hidden])');ready();events.append(('progression','Confirm and record'))
            href=page.locator('#aw-exact-link').get_attribute('href');jid=re.search(r'journal_id=(\d+)',href).group(1)
            check(sql('SELECT source_book FROM journal_entries WHERE id='+jid)[0]['source_book']==('CDB' if task=='payment' else 'CRB'),'Measured '+task+' ends at real exact recorded journal')
            trace.append({'task':task,'classification':'newly reproduced','revision':os.environ['ATIKHA_FOLLOWUP_BASELINE'],'source':'final production hashes recorded by orchestrator','navigation_controls':1,'page_changes':1,'fields':5,'progression':2,'required_evidence_actions':0,'optional_actions':0,'actions':events})
        filled('cash_disbursement.php',fixture['training']);page.locator('#aw-document-group summary').click();page.locator('#aw-upload').set_input_files({'name':'Synthetic follow-up proof.png','mimeType':'image/png','buffer':png()});page.wait_for_selector('[data-document]');ready();doc=page.locator('[data-document]')
        doc.locator('[data-doc-field="purpose"]').select_option('amount');doc.locator('[data-doc-field="declared_amount"]').fill('1000');doc.locator('[data-doc-field="accepted_amount"]').fill('400');doc.locator('[data-allocation]').fill('400');doc.locator('[data-doc-field="exclusion_reason"]').fill('Synthetic document includes an amount outside this entry.');doc.locator('[data-doc-field="reviewed"]').check();r=review()
        check(r['coverage']['status']=='Partially covered' and '₱400.00 supported of ₱1,000.00' in page.locator('.aw-coverage').inner_text(),'Partial support preserves the authoritative denominator');capture('payment-partial-coverage')
        page.locator('#aw-back').click();ready();doc.locator('[data-doc-field="accepted_amount"]').fill('1000');doc.locator('[data-allocation]').fill('1000');doc.locator('[data-doc-field="reviewed"]').check();r=review()
        check(r['coverage']['status']=='Fully covered' and '₱1,000.00 supported of ₱1,000.00' in page.locator('.aw-coverage').inner_text(),'Full support formats both exact values');capture('payment-full-coverage')
        # Malformed authoritative coverage must never use the editable empty-as-zero rule.
        def corrupt(route):
            if route.request.method=='POST' and route.request.post_data_json.get('action')=='review':
                response=route.fetch();data=response.json();assert data['ok'];data['result']['coverage']['debit']['covered']='';route.fulfill(response=response,json=data)
            else:route.continue_()
        page.locator('#aw-back').click();page.route('**/accounting_actions.php',corrupt);review();text=page.locator('.aw-coverage').inner_text();check('amount unavailable supported of ₱1,000.00' in text and '₱0.00' not in text,'Malformed covered amount is unavailable rather than fabricated zero');capture('coverage-unavailable');page.unroute('**/accounting_actions.php',corrupt)
        page.goto(base+'/general_journal.php');ready();today=page.locator('#aw-date').input_value()
        payload={'entry_date':today,'description':'Synthetic follow-up transfer','reference':'','party_id':'','default_project_id':'','cash_account_id':'','cash_amount':'','cash_project_id':'','transaction_kind':'transfer','documents':[],'lines':[{'client_id':secrets.token_hex(12),'account_id':str(fixture['bank']),'fund_project_id':'','debit_amount':'1000','credit_amount':''},{'client_id':secrets.token_hex(12),'account_id':str(fixture['bank']),'fund_project_id':'','debit_amount':'','credit_amount':'1000'}]}
        # Use only a known second synthetic cash account from the existing fixture list.
        response=ctx.request.get(base+'/accounting_actions.php?action=lists').json();assert response['ok'];cash=[a for a in response['result']['accounts'] if int(a['Is_Cash_Account']) and str(a['CategoryID'])!=str(fixture['bank'])];assert cash;payload['lines'][1]['account_id']=str(cash[0]['CategoryID'])
        saved=ctx.request.post(base+'/accounting_actions.php',data={'action':'save','csrf_token':session['csrf'],'draft_id':'','submission_key':secrets.token_hex(32),'source_book':'GJ','payload':payload}).json();assert saved['ok'],saved
        page.goto(base+'/general_journal.php?draft_id='+str(saved['result']['id']));ready();r=review();text=page.locator('.aw-coverage').inner_text()
        check(r['input']['transaction_kind']=='transfer' and r['coverage']['status']=='Not applicable' and all(r['coverage'][s]['eligible']=='0.00' for s in ['debit','credit']),'Transfer N/A conditions are server-reviewed')
        check('no eligible noncash line' in text and 'supported of' not in text and 'debit:' not in text,'Transfer explanation replaces zero-of-zero only');check(page.locator('#aw-review-content details table').is_visible(),'Transfer full journal remains inspectable');capture('transfer-not-applicable')
        def inconsistent(route):
            if route.request.method=='POST' and route.request.post_data_json.get('action')=='review':
                response=route.fetch();data=response.json();assert data['ok'];data['result']['coverage']['credit']['eligible']='0.01';route.fulfill(response=response,json=data)
            else:route.continue_()
        page.locator('#aw-back').click();page.route('**/accounting_actions.php',inconsistent);review();text=page.locator('.aw-coverage').inner_text();check('credit: ₱0.00 supported of ₱0.01' in text and 'no eligible noncash' not in text,'Inconsistent transfer eligibility remains visible');page.unroute('**/accounting_actions.php',inconsistent)
        check(not errors,'No uncaught JavaScript errors');ctx.close();browser.close();success=True
finally:
    server.terminate();server.wait(timeout=10);log.close();(run/'results.json').write_text(json.dumps({'passed':success,'checks':checks,'errors':errors,'action_traces':trace},indent=2),encoding='utf-8');print('U2_FOLLOWUP_RUN: '+str(run),flush=True)
