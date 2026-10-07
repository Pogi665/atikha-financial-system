"""Checkpoint 4 browser/HTTP checks on an already-created disposable CP3 fixture.
Copies code into a private app; never copies production config, sessions or receipts.
"""
import argparse,json,os,re,secrets,shutil,socket,struct,subprocess,sys,time,zlib
from pathlib import Path
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--browser',default='msedge');parser.add_argument('--followup',action='store_true');args=parser.parse_args()
fixture_path=Path(args.fixture).resolve()
if not fixture_path.is_relative_to(root/'.migration-private'):parser.error('Private disposable fixture required')
fixture=json.loads(fixture_path.read_text());db=fixture['database']
if not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',db):parser.error('Disposable database required')
php=r'C:\xampp\php\php.exe';evidence=Path(fixture['receipt_root'])
run=root/'.migration-private'/('stage3-cp4-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(stage3=True,all_off=False):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',"+str(not all_off).lower()+");define('STAGE2_ADVANCES_ENABLED',"+str(not all_off).lower()+");define('STAGE3_CORRECTIONS_ENABLED',"+str(stage3).lower()+");define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
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
        def review():
            page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])');check(page.locator('#aw-error').inner_text()=='','Review has no error')
        def call(action,values={},actor=None,csrf=None):
            return (actor or ctx).request.post(base+'/journal_correction_actions.php',data={'action':action,'csrf_token':csrf or session['csrf'],**values})
        def capture(label):
            for width,height in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),label+' fits '+str(width));page.screenshot(path=str(run/(label+'-'+str(width)+'.png')),full_page=True)
        if args.followup:
            before=int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])
            page.goto(base+'/journal_correction.php?draft_id='+str(fixture['release_draft']));page.wait_for_selector('[data-advance-result="replacement_advance_id"]')
            check(page.locator('#aw-advance-navigation').is_hidden() and '?' not in page.locator('#aw-posted').inner_text(),'Final posted result hides original placeholder and uses clean separators')
            check(page.locator('[data-advance-result="original_advance_id"]').count()==1 and page.locator('[data-advance-result="replacement_advance_id"]').count()==1,'Final posted release keeps distinct identity links')
            capture('final-release-posted')
            rows=sql("SELECT a.release_journal_id FROM cash_advances a WHERE NOT EXISTS(SELECT 1 FROM journal_corrections c WHERE c.target_journal_id=a.release_journal_id) AND NOT EXISTS(SELECT 1 FROM cash_advance_operations o WHERE o.advance_id=a.id AND o.operation_kind<>'release' AND NOT EXISTS(SELECT 1 FROM cash_advance_operation_reversals r WHERE r.original_operation_id=o.id)) ORDER BY a.id DESC LIMIT 1")
            page.goto(base+'/journal_correction.php?journal_id='+str(rows[0]['release_journal_id']));ready()
            committed=page.locator('#aw-cash-account').input_value();search=page.locator('#aw-cash-account-search');search.fill('no matching record');search.press('Escape')
            check(page.locator('#aw-cash-account').input_value()==committed,'Final advance selector keyboard cancellation preserves account ID')
            page.locator('#aw-correction-reason').fill('Synthetic final layout review');page.locator('#aw-due-date').fill('9998-12-31');review()
            check('Earliest affected date' in page.locator('#aw-review-content').inner_text() and '? Before PHP' not in page.locator('#aw-review-content').inner_text(),'Final review labels dated effects with clean separators')
            capture('final-release-review')
            check(int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before and not errors,'Final layout checks create no financial posting or browser errors')
            print('Checkpoint 4 final presentation checks: '+str(checks));print('Private browser artifacts: '+str(run));browser.close();raise SystemExit(0)
        before=int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])
        release_draft=str(fixture['release_draft']);page.goto(base+'/journal_correction.php?draft_id='+release_draft);ready();review()
        check(page.locator('#aw-post').is_enabled() and 'New replacement advance' in page.locator('#aw-review-content').inner_text(),'Release review enables posting and explains new identity')
        capture('release-review')
        # Lost response on the actual dedicated writer, followed by the visible retry.
        lost=[False]
        def lose(route):
            if not lost[0] and route.request.method=='POST' and route.request.post_data_json.get('action')=='post':lost[0]=True;route.fetch();route.abort('failed')
            else:route.continue_()
        page.route('**/journal_correction_actions.php',lose);page.locator('#aw-post').click();page.wait_for_function("document.getElementById('aw-error').textContent.length>0")
        check(int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before+2,'Lost release correction response commits one atomic bundle')
        page.locator('#aw-post').click();page.wait_for_selector('[data-advance-result="replacement_advance_id"]');page.unroute('**/journal_correction_actions.php',lose)
        new_id=int(re.search(r'advance_id=(\d+)',page.locator('[data-advance-result="replacement_advance_id"]').get_attribute('href')).group(1))
        old_id=int(re.search(r'advance_id=(\d+)',page.locator('[data-advance-result="original_advance_id"]').get_attribute('href')).group(1))
        check(new_id!=old_id and 'already posted' in page.locator('#aw-posted-message').inner_text(),'Visible release retry recovers distinct old/new identity links')
        check(page.locator('#aw-posted a[href="financial_records.php?view=cdb&from=&to="]').count()==1 and page.locator('#aw-history-link').count()==1,'Release result retains CDB and Journal History navigation')
        capture('release-posted');page.reload();page.wait_for_selector('[data-advance-result="replacement_advance_id"]')
        check(page.locator('[data-advance-result="replacement_advance_id"]').get_attribute('href').endswith('='+str(new_id)) and page.locator('.aw-columns').is_hidden(),'Posted draft reopening keeps replacement ID and read-only bundle')
        fresh,fs=context('admin@example.invalid');q=fixture['release_request'];q.pop('csrf_token',None);q['review_token']='0000000000.'+'0'*64
        recovered=call('post',q,fresh,fs['csrf']);check(recovered.ok and recovered.json()['result']['replacement_advance_id']==new_id and recovered.json()['result']['duplicate'],'Cross-session expired review recovers release correction')
        q['payload']['replacement']['due_date']='9998-12-31';check(call('post',q,fresh,fs['csrf']).status==409,'Changed advance fields conflict on successful retry')
        page.goto(base+'/cash_advances.php?advance_id='+str(old_id));check('replaced' in page.locator('main').inner_text() and page.locator('#ca-extension').count()==0,'Old release detail is Replaced without settlement/extension actions')
        check(page.locator('a',has_text='View replacement advance').get_attribute('href').find('advance_id='+str(new_id))>=0,'Old detail navigates to replacement')
        page.goto(base+'/cash_advances.php?advance_id='+str(new_id));check('9000.00' in page.locator('main').inner_text() and page.locator('#ca-extension').count()==1,'New advance detail has correct outstanding and normal actions')
        page.goto(base+'/journal_correction.php?journal_id='+str(fixture['blocked_release']));page.wait_for_function("document.getElementById('aw-correction-eligibility').textContent.includes('Blocking')")
        check(page.locator('#aw-review').is_disabled(),'Release with effective settlements shows blockers and disables review')
        # Both settlement replacement types use saved reviewed images and visible controls.
        page.goto(base+'/journal_correction.php?draft_id='+str(fixture['liquidation_draft']));ready();review()
        check('debit: PHP 7000.00 supported of PHP 7000.00' in page.locator('.aw-coverage').inner_text() and 'credit:' not in page.locator('.aw-coverage').inner_text(),'Liquidation review covers gross cost once')
        capture('liquidation-review');page.locator('#aw-post').click();page.wait_for_selector('[data-advance-result="original_advance_id"]')
        check(page.locator('[data-advance-result="replacement_advance_id"]').count()==0 and page.locator('[data-advance-result="original_advance_id"]').get_attribute('href').endswith('='+str(fixture['settlement_advance'])),'Liquidation posting preserves original advance navigation')
        check(page.locator('#aw-posted a[href="financial_records.php?from=&to="]').count()==1,'GJ liquidation result has one Journal History link')
        page.goto(base+'/journal_correction.php?draft_id='+str(fixture['return_draft']));ready();page.locator('#aw-confirm-proof').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Proof confirmed')")
        page.locator('#aw-cash-amount').fill('1400.00');page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved')")
        check('not been confirmed' in page.locator('#aw-proof-status').inner_text(),'Editing the return amount invalidates confirmation visibly')
        page.locator('#aw-cash-amount').fill('1500.00');page.locator('#aw-confirm-proof').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Proof confirmed')");review()
        check('Return proof confirmed' in page.locator('#aw-review-content').inner_text(),'Return review confirms current proof and amount')
        capture('return-review');page.locator('#aw-post').click();page.wait_for_selector('[data-advance-result="original_advance_id"]')
        check(page.locator('#aw-posted a[href="financial_records.php?view=crb&from=&to="]').count()==1,'Return replacement result links CRB')
        page.goto(base+'/cash_advances.php?advance_id='+str(fixture['settlement_advance']));check('1500.00' in page.locator('main').inner_text() and 'Reversal of liquidation' in page.locator('main').inner_text() and 'Reversal of return' in page.locator('main').inner_text(),'Detail shows effective balance and original/reversal/replacement timeline')
        capture('settlement-detail')
        page.emulate_media(media='print');check(page.locator('#ca-extension').is_hidden(),'Print media hides editable actions');page.screenshot(path=str(run/'settlement-detail-print.png'),full_page=True);page.emulate_media(media='screen')
        page.goto(base+'/cash_advances.php');page.wait_for_function("document.getElementById('ca-status').textContent.includes('loaded')")
        for status in ['replaced','cancelled']:
            page.locator('[name="status"]').select_option(status);page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-applied-filters').textContent.includes('Settlement status: '+document.querySelector('[name=status] option:checked').textContent)")
            check(page.locator('#ca-rows').inner_text().find(status)>=0 and 'Reconciled' in page.locator('#ca-reconciliation').inner_text(),'Register '+status+' filter retains full reconciliation')
        capture('retired-register');applied=page.locator('#ca-applied-filters').inner_text();rows=page.locator('#ca-rows').inner_text()
        page.locator('[name="status"]').select_option('replaced');check(page.locator('#ca-applied-filters').inner_text()==applied,'Unapplied filter edits retain displayed print scope')
        def fail_register(route):route.fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"Synthetic refresh failure"}')
        page.route('**/cash_advance_actions.php?*',fail_register);page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-error').textContent.length>0")
        check(page.locator('#ca-rows').inner_text()==rows and page.locator('#ca-applied-filters').inner_text()==applied,'Failed refresh preserves rows and applied print filters');page.unroute('**/cash_advance_actions.php?*',fail_register)
        page.emulate_media(media='print');check(page.locator('#ca-filters').is_hidden() and 'Current page only' in page.locator('#ca-print-scope').inner_text(),'Printed register labels page and all-matching totals');page.screenshot(path=str(run/'retired-register-print.png'),full_page=True);page.emulate_media(media='screen')
        # Viewer-aware coverage and evidence redaction in existing interfaces, including UI flag off.
        journals=sql('SELECT c.replacement_journal_id journal_id FROM journal_corrections c JOIN journal_drafts d ON d.id=c.draft_id WHERE d.id='+str(fixture['liquidation_draft']))
        jid=int(journals[0]['journal_id']);proof=sql('SELECT receipt_id FROM posted_evidence_associations WHERE journal_id='+str(jid))[0]['receipt_id']
        manager,ms=context('management@example.invalid');other,other_session=context('other@example.invalid')
        check(manager.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+release_draft).status==403 and other.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+release_draft).status==404,'Management and other Admin cannot read owned advance correction draft')
        private_journals={str(row['journal_id']) for row in sql('SELECT journal_id FROM cash_advance_operations UNION SELECT reversal_journal_id journal_id FROM cash_advance_operation_reversals')}
        for view in ['', '&view=crb','&view=cdb']:
            result=manager.request.get(base+'/financial_records.php?format=json&from=&to='+view)
            data=result.json() if not view else json.loads(re.search(r'<script id="records-data" type="application/json">(.*?)</script>',result.text(),re.S).group(1))
            journals=[j for key,j in data['journals'].items() if str(key) in private_journals]
            check(result.ok and bool(journals) and all(j['attachments']==[] and 'receipt_attachment.php' not in json.dumps(j) and 'return_confirmation' not in json.dumps(j) for j in journals),'Management existing history/book response redacts only private advance evidence '+view)
        response=manager.request.get(base+'/journal_corrections.php?journal_id='+str(jid),timeout=120000);check(response.ok and 'Fully covered' in response.text() and 'receipt_attachment.php' not in response.text(),'Management correction comparison retains supported status without images')
        check(manager.request.get(base+'/receipt_attachment.php?receipt_id='+str(proof)+'&journal_id='+str(jid)).status==404,'Management cannot download reused advance image')
        config(False,all_off=True);check(manager.request.get(base+'/journal_corrections.php?journal_id='+str(jid),timeout=120000).ok and manager.request.get(base+'/receipt_attachment.php?receipt_id='+str(proof)+'&journal_id='+str(jid)).status==404,'Disabled accounting UI flags do not weaken comparison/download privacy');config()
        check(int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before+6,'Visible release/liquidation/return flows post exactly three two-journal bundles')
        check(not errors,'Advance correction browser flows have no JavaScript errors');browser.close()
    print('Stage 3 checkpoint 4 browser checks: '+str(checks));print('Private browser artifacts: '+str(run))
finally:
    if 'page' in globals() and not page.is_closed():
        try:print('Browser error panel: '+page.locator('#aw-error').inner_text());print('JavaScript errors: '+str(errors));page.screenshot(path=str(run/'last-screen.png'),full_page=True)
        except Exception:pass
    server.terminate();server.wait(timeout=10);log.close()
