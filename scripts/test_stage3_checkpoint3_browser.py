"""Checkpoint 3 browser/HTTP checks on an already-created disposable CP3 fixture.
Copies code into a private app; never copies production config, sessions or receipts.
"""
import argparse,json,os,re,secrets,shutil,socket,struct,subprocess,sys,time,zlib
from pathlib import Path
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--browser',default='msedge');args=parser.parse_args()
fixture_path=Path(args.fixture).resolve()
if not fixture_path.is_relative_to(root/'.migration-private'):parser.error('Private disposable fixture required')
fixture=json.loads(fixture_path.read_text());db=fixture['database']
if not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',db):parser.error('Disposable database required')
php=r'C:\xampp\php\php.exe';evidence=Path(fixture['receipt_root'])
run=root/'.migration-private'/('stage3-cp3-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(stage3=True):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',"+str(stage3).lower()+");define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
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
router=run/'router.php';router.write_text("<?php if(preg_match('~^/(uploads|scripts|includes|vendor)/~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(403);exit;}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0
def check(ok,label):
    global checks
    if not ok:raise AssertionError(label)
    checks+=1;print('PASS: '+label,flush=True)
def choose(control,value):
    label=control.locator('option[value="'+value+'"]').text_content();combo=control.locator('..').locator('.aw-record-selector')
    combo.get_by_role('combobox').fill(label);combo.locator('[role="option"][data-value="'+value+'"]').click()
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email):
            ctx=browser.new_context(viewport={'width':1366,'height':768});ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());session=login(email);ctx.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}]);return ctx,session
        ctx,session=context('admin@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def ready():
            page.wait_for_selector('#aw-party-search',state='attached');page.wait_for_function("document.getElementById('aw-save').disabled===false")
        def save():
            page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved') || document.getElementById('aw-error').textContent.length>0");check(not page.locator('#aw-error').inner_text(),'Draft saved without validation error')
        def call(action,values={},actor=None,csrf=None):return (actor or ctx).request.post(base+'/journal_correction_actions.php',data={'action':action,'csrf_token':csrf or session['csrf'],**values})
        if int(sql('SELECT COUNT(*) n FROM journal_corrections WHERE target_journal_id='+str(int(fixture['payment_target'])))[0]['n']):
            entry={'entry_date':time.strftime('%Y-%m-%d'),'reference':'','description':'Synthetic browser original','party_id':str(fixture['party']),'default_project_id':'','cash_account_id':str(fixture['bank']),'cash_amount':'1000.00','cash_project_id':'','transaction_kind':'ordinary','documents':[],'lines':[{'client_id':'browser_cost','account_id':str(fixture['training']),'fund_project_id':'','debit_amount':'1000.00','credit_amount':''}]}
            def ordinary(action,values):
                r=ctx.request.post(base+'/accounting_actions.php',data={'action':action,'csrf_token':session['csrf'],**values});check(r.ok,'Disposable ordinary fixture '+action);return r.json()['result']
            d=ordinary('save',{'draft_id':'','source_book':'CDB','submission_key':secrets.token_hex(32),'payload':entry});ident={'draft_id':str(d['id']),'revision':str(d['revision']),'submission_key':d['submission_key']};v=ordinary('review',ident);fixture['payment_target']=ordinary('post',{**ident,'payload':d['payload'],'review_token':v['token']})['id']
        before=int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])
        page.goto(base+'/journal_correction.php?journal_id='+str(fixture['payment_target']));ready()
        check('1000.00' in page.locator('#aw-original').inner_text(),'Original 1000 payment is read-only')
        browser_tag='Synthetic browser amount correction '+secrets.token_hex(4)
        page.locator('#aw-correction-reason').fill(browser_tag+' <script>window.bad=1</script>')
        page.locator('#aw-cash-amount').fill('900.00');page.locator('#aw-lines [data-field="debit_amount"]').fill('900.00');save()
        draft_id=re.search(r'draft_id=(\d+)',page.url).group(1)
        selected=page.locator('#aw-party').input_value();page.locator('#aw-party-search').fill('unknown search');page.keyboard.press('Escape');check(page.locator('#aw-party').input_value()==selected,'Visible selector cancellation preserves record')
        page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])');check(page.locator('#aw-post').is_enabled() and 'Generated exact reversal' in page.locator('#aw-review-content').inner_text(),'Ordinary reviewed comparison enables atomic posting')
        for width,height in [(1366,768),(1920,1080)]:
            page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Review fits '+str(width));page.screenshot(path=str(run/f'review-{width}.png'),full_page=True)
        loaded=ctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).json()['result'];token=page.evaluate("document.getElementById('aw-post').disabled")
        lost=[False]
        def lose_post(route):
            if not lost[0] and route.request.method=='POST' and route.request.post_data_json.get('action')=='post':lost[0]=True;route.fetch();route.abort('failed')
            else:route.continue_()
        page.route('**/journal_correction_actions.php',lose_post);page.locator('#aw-post').click();page.wait_for_function("document.getElementById('aw-error').textContent.length>0");check(int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before+2,'Lost successful response still commits exactly two journals')
        page.locator('#aw-post').click();page.wait_for_selector('#aw-posted:not([hidden])');page.unroute('**/journal_correction_actions.php',lose_post)
        check('already posted' in page.locator('#aw-posted-message').inner_text() and int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before+2,'Visible retry recovers original bundle without duplicate posting')
        page.wait_for_selector('[data-correction-result]');check(page.locator('.aw-columns').is_hidden() and '900.00' in page.locator('#aw-posted').inner_text() and 'Correction posted' in page.locator('#aw-status').inner_text() and 'Corrected on' in page.locator('#aw-correction-eligibility').inner_text(),'Posted UI shows immutable lines and accurate posted status')
        check(page.locator('#aw-posted a[href="financial_records.php?view=cdb&from=&to="]').count()==1 and page.locator('#aw-posted a[href="financial_records.php?from=&to="]').count()==1,'Posted links contain CDB and Journal History once')
        check(page.locator('#aw-correction-mode').is_disabled(),'Posted mode remains read-only')
        page.screenshot(path=str(run/'posted-1920.png'),full_page=True);page.reload();page.wait_for_selector('#aw-posted:not([hidden])');check('reversal journal #' in page.locator('#aw-posted').inner_text(),'Posted draft reopening shows the same complete bundle')
        # Matching retries recover with a new authenticated session and expired review token.
        fresh,fsession=context('admin@example.invalid');values={'draft_id':draft_id,'revision':str(loaded['revision']),'submission_key':loaded['submission_key'],'payload':loaded['payload'],'review_token':'0000000000.'+'0'*64}
        response=call('post',values,fresh,fsession['csrf']);check(response.ok and response.json()['result']['duplicate'],'New-session expired-token retry recovers posted correction')
        values['payload']['reason']='Changed key reuse';response=call('post',values,fresh,fsession['csrf']);check(response.status==409,'Changed-content durable key conflicts')
        result=ctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).json()['result'];replacement=result['posted_journal_id']
        page.goto(base+'/journal_corrections.php?journal_id='+str(fixture['payment_target']));page.wait_for_selector('.correction-comparison');check('POSTED' in page.locator('main').inner_text() and 'Recorded:' in page.locator('main').inner_text() and 'corrected net ledger effect' in page.locator('main').inner_text(),'Posted detail separates accounting/recorded dates and net effect')
        check(page.evaluate('typeof window.bad')=='undefined','Correction reason is escaped in detail rendering')
        for width,height in [(1366,768),(1920,1080)]:
            page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Detail fits '+str(width));page.screenshot(path=str(run/f'detail-{width}.png'),full_page=True)
        page.emulate_media(media='print');check(page.locator('.correction-navigation').first.is_hidden(),'Print media hides navigation');page.screenshot(path=str(run/'detail-print.png'),full_page=True);page.emulate_media(media='screen')
        page.goto(base+'/journal_corrections.php');check(page.locator('.correction-comparison').count()>0,'Posted correction list links to comparisons')
        page.goto(base+'/financial_records.php?view=cdb&from=&to=');page.wait_for_selector('.dt-search input');search=page.locator('.dt-search input');search.fill(browser_tag);page.wait_for_timeout(100)
        check(page.locator('#records-table tbody tr').count()==4 and 'Gross originating book activity' in page.locator('main').inner_text(),'Cash-book search retains full original and replacement entries')
        page.locator('#records-table .records-view').first.click();check(page.locator('#transaction-details').inner_text().find('accounting date')>=0,'Cash-book journal detail links dated correction outside filter scope');page.locator('#transaction-close').click()
        page.goto(base+'/financial_records.php?from=&to=');page.wait_for_selector('.dt-search input');page.locator('.dt-search input').fill(browser_tag);page.wait_for_timeout(100);check(page.locator('#records-table tbody tr').count()==6,'Journal History includes original/reversal/replacement lines in correction search')
        manager,msession=context('management@example.invalid');check(manager.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).status==403,'Management cannot open private posted draft')
        response=manager.request.get(base+'/journal_corrections.php?journal_id='+str(fixture['private_advance_journal']));check(response.ok and 'receipt_attachment.php' not in response.text() and 'secret-review' not in response.text() and 'Fully covered' in response.text(),'Management comparison redacts advance documents without losing coverage')
        other,osession=context('other@example.invalid');check(other.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).status==404,'Another Admin cannot read owned posted draft');check(other.request.get(base+'/journal_corrections.php?journal_id='+str(fixture['payment_target'])).ok,'Other active Admin can read public posted financial comparison')
        config(False);check(manager.request.get(base+'/journal_corrections.php?journal_id='+str(fixture['private_advance_journal'])).ok,'Posted chain remains readable with UI flag disabled');check(ctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).status==503,'Disabled flag blocks correction workspace');config()
        # Reverse only on the latest replacement; no false new cash movement/replacement.
        page.goto(base+'/journal_correction.php?journal_id='+str(replacement)+'&mode=reverse_only');ready();page.locator('#aw-correction-reason').fill('Duplicate synthetic replacement');save();page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])');check(page.locator('#aw-post').is_enabled() and page.locator('#aw-post').inner_text()=='Post reversal','Reverse-only review offers exact reversal');page.locator('#aw-post').click();page.wait_for_selector('#aw-posted:not([hidden])');check(page.locator('#aw-posted').inner_text().count('replacement journal #')==0 and page.locator('#aw-history-link').count()==1,'Reverse-only posted result has no replacement and one history link')
        check(int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before+3,'Browser posted exactly one bundle and one reversal-only journal');check(not errors,'Posting/detail/history browser paths have no JavaScript errors');browser.close()
    print('Stage 3 checkpoint 3 browser checks: '+str(checks));print('Private browser artifacts: '+str(run))
finally:
    if 'page' in globals() and not page.is_closed():
        try:print('Browser error panel: '+page.locator('#aw-error').inner_text());print('JavaScript errors: '+str(errors));page.screenshot(path=str(run/'last-screen.png'),full_page=True)
        except Exception:pass
    server.terminate();server.wait(timeout=10);log.close()
