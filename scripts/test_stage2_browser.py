"""Stage 2 HTTP and real browser checks, on a new DB and private copied app.
Production config, sessions and uploads are never copied. All external requests blocked.
"""
import argparse, hashlib, json, os, re, secrets, shutil, socket, struct, subprocess, sys, time, zlib
from urllib.parse import urlencode, urlparse, parse_qsl
from pathlib import Path
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--database');parser.add_argument('--fixture');parser.add_argument('--browser',default='msedge');args=parser.parse_args()
php=shutil.which('php') or r'C:\xampp\php\php.exe'
if args.fixture:
    if args.database:parser.error('Choose a new database or an existing disposable fixture, not both')
    path=Path(args.fixture).resolve()
    if not path.is_relative_to(root/'.migration-private'):parser.error('Private disposable fixture required')
    fixture=json.loads(path.read_text());args.database=fixture['database']
    if not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',args.database):parser.error('Disposable Stage 1 database required')
else:
    if not args.database or not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',args.database):parser.error('New disposable Stage 1 database required')
    bootstrap=subprocess.check_output([php,str(root/'scripts/test_stage2.php'),'--database='+args.database],text=True);print(bootstrap,end='')
    fixture=json.loads(Path(re.search(r'Stage 2 fixture: (.+)',bootstrap).group(1).strip()).read_text())

evidence=Path(fixture['receipt_root'])
run=root/'.migration-private'/('stage2-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+args.database+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(ocr=False,enabled=True,stage2=True):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',"+str(enabled).lower()+");define('STAGE2_ADVANCES_ENABLED',"+str(stage2).lower()+");define('OCR_JOURNAL_ENABLED',"+str(ocr).lower()+");define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
config()
def sql(query,mutate=False):
    code='<?php require '+ps(str(root/'scripts/cli_common.php'))+';$pdo=cli_db('+ps(args.database)+');echo json_encode('+('$pdo->exec('+ps(query)+')' if mutate else '$pdo->query('+ps(query)+')->fetchAll()')+');'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+args.database,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
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
def journal_count():return int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])
def choose_record(control,value):
    label=control.locator('option[value="'+value+'"]').text_content()
    combo=control.locator('..').locator('.aw-record-selector')
    combo.get_by_role('combobox').fill(label)
    combo.locator('[role="option"][data-value="'+value+'"]').click()

try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email=None):
            c=browser.new_context(viewport={'width':1366,'height':768});c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
            session=login(email) if email else None
            if session:c.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}])
            return c,session
        ctx,session=context('admin@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def ready():
            page.wait_for_selector('#aw-existing',state='attached');page.wait_for_function("!document.getElementById('aw-existing').disabled || !document.getElementById('aw-posted').hidden")
        def save():
            page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved')")
        def review():
            page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])')
        def post():
            page.locator('#aw-post').click();page.wait_for_selector('#aw-posted:not([hidden])');return re.search(r'advance_id=(\d+)',page.locator('#aw-advance-link').get_attribute('href')).group(1)
        def upload():
            page.locator('#aw-upload').set_input_files({'name':'Synthetic field activity proof.png','mimeType':'image/png','buffer':png()});page.wait_for_function("document.getElementById('aw-status').textContent.includes('Image stored')");page.wait_for_selector('[data-document]')
        def proof_review():page.locator('[data-doc-field="reviewed"]').check()
        def call(action,values={},actor=ctx,csrf=None,endpoint='cash_advance_actions.php'):
            return actor.request.post(base+'/'+endpoint,data={'action':action,'csrf_token':csrf or session['csrf'],**values})
        bank=str(fixture['bank']);employee=str(fixture['employee']);control=str(fixture['control']);training=str(fixture['training']);tax=str(fixture['tax']);project=str(fixture['project'])
        page.goto(base+'/cash_advance_entry.php');ready()
        check(page.locator('#aw-lines').is_hidden(),'Release hides editable allocation lines')
        check('release proof is optional' in page.locator('#aw-evidence-help').inner_text() and 'Missing or partial' not in page.locator('#aw-evidence-help').inner_text(),'Release explains its optional informational evidence policy')
        choose_record(page.locator('#aw-party'),employee);choose_record(page.locator('#aw-default-project'),project);choose_record(page.locator('#aw-control-account'),control);choose_record(page.locator('#aw-cash-account'),bank)
        page.locator('#aw-purpose').fill('Synthetic NGO field visit advance');page.locator('#aw-cash-amount').fill('10000.00');today=page.locator('#aw-date').input_value();page.locator('#aw-due-date').fill(today)
        page.locator('[data-new-master="parties"]').click();page.locator('#aw-master-form [name="name"]').fill('Synthetic additional NGO coordinator');page.locator('#aw-master-form button.aw-primary').click();page.wait_for_selector('#aw-master-dialog',state='hidden');check(page.locator('#aw-party-search').evaluate('(el)=>el===document.activeElement'),'Inline person creation restores focus to visible selector');choose_record(page.locator('#aw-party'),employee)
        before=journal_count();save();draft_id=re.search(r'draft_id=(\d+)',page.url).group(1);check(journal_count()==before,'Release draft saves with no financial posting')
        page.locator('#aw-party-search').fill('no such person zz');page.keyboard.press('Enter');page.keyboard.press('Escape');check(page.locator('#aw-party').input_value()==employee and 'unsaved' not in page.locator('#aw-draft-label').inner_text(),'Search cancellation preserves selected employee and saved revision')
        page.screenshot(path=str(run/'release-1366.png'),full_page=True);page.set_viewport_size({'width':1920,'height':1080});page.screenshot(path=str(run/'release-1920.png'),full_page=True);page.set_viewport_size({'width':1366,'height':768})
        review();check('Cash Advance' in page.locator('#aw-review-content').inner_text(),'Release review shows the generated control line');aid=post()
        check(page.locator('#aw-posted-link').get_attribute('href')=='financial_records.php?view=cdb&from=&to=' and page.locator('#aw-history-link').get_attribute('href')=='financial_records.php?from=&to=','Release exposes separate book/history destinations')
        check(page.locator('#aw-control-account-search').is_disabled(),'Posted release selector is read only')
        page.goto(base+'/cash_advance_entry.php?draft_id='+draft_id);ready();page.wait_for_selector('#aw-posted:not([hidden])');check('already posted' in page.locator('#aw-posted-message').inner_text(),'Reopening a posted advance draft recovers its navigation')
        page.goto(base+'/cash_advance_entry.php?workflow_kind=advance_liquidation&advance_id='+aid);ready();check(page.locator('#aw-party-search').is_disabled(),'Settlement fixes original employee identity')
        check(page.locator('#aw-liquidation-reuse-warning').is_visible() and 'cannot support another liquidation' in page.locator('#aw-liquidation-reuse-warning').inner_text(),'Liquidation explains unused-image restriction before review or posting')
        check('fully cover every expenditure' in page.locator('#aw-evidence-help').inner_text() and 'Missing or partial' not in page.locator('#aw-evidence-help').inner_text(),'Liquidation footer states full expenditure coverage instead of ordinary partial support')
        page.locator('#aw-purpose').fill('Supported NGO training expenses');choose_record(page.locator('[data-line] [data-field="account_id"]'),training);page.locator('[data-line] [data-field="debit_amount"]').fill('8000.00');save();liq_id=re.search(r'draft_id=(\d+)',page.url).group(1)
        page.locator('#aw-review').click();page.wait_for_function("document.getElementById('aw-error').textContent.length>0");check('Fully cover' in page.locator('#aw-error').inner_text(),'Missing liquidation evidence fails visibly and retains draft')
        uploads_before=int(sql('SELECT COUNT(*) n FROM Receipts')[0]['n']);lost=[False]
        def lose_upload(route):
            if not lost[0] and route.request.method=='POST' and 'multipart/form-data' in route.request.headers.get('content-type',''):
                lost[0]=True;route.fetch();route.abort('failed')
            else:route.continue_()
        page.route('**/cash_advance_actions.php',lose_upload)
        page.locator('#aw-upload').set_input_files({'name':'Synthetic liquidation proof.png','mimeType':'image/png','buffer':png()});page.wait_for_selector('#aw-upload-retry:not([hidden])');page.locator('#aw-upload-retry').click();page.wait_for_selector('[data-document]');page.unroute('**/cash_advance_actions.php',lose_upload)
        check(int(sql('SELECT COUNT(*) n FROM Receipts')[0]['n'])==uploads_before+1,'v3 lost upload response retries one reserved document without losing advance context')
        page.locator('[data-doc-field="purpose"]').select_option('amount');page.locator('[data-doc-field="declared_amount"]').fill('8000.00');page.locator('[data-doc-field="accepted_amount"]').fill('8000.00');page.locator('[data-allocation]').fill('8000.00');proof_review();save()
        check(page.locator('[data-partial-warning]').is_hidden(),'Fully accepted image has no excluded-amount warning')
        page.locator('[data-doc-field="declared_amount"]').fill('10000.00')
        check(page.locator('[data-partial-warning]').is_visible() and '\u20b12,000.00' in page.locator('[data-partial-warning]').inner_text(),'Partial acceptance shows exact unused amount without changing financial allocations')
        page.locator('[data-doc-field="declared_amount"]').fill('invalid')
        check(page.locator('[data-partial-warning]').is_hidden(),'Invalid document amount never displays a fabricated unused amount')
        page.locator('[data-doc-field="declared_amount"]').fill('10000.00');page.locator('[data-doc-field="exclusion_reason"]').fill('Synthetic personal costs excluded');proof_review();save()
        page.goto(base+'/accounting_drafts.php');page.wait_for_function("document.getElementById('draft-status').textContent.includes('loaded')");row=page.locator('tr[data-draft-id="'+liq_id+'"]');check(row.locator('a').get_attribute('href')=='cash_advance_entry.php?draft_id='+liq_id and 'Advance liquidation' in row.inner_text(),'My Drafts labels and resumes v3 liquidation')
        row.locator('a').click();ready();check(page.locator('[data-doc-field="accepted_amount"]').input_value()=='8000.00','Resume retains manual evidence and allocations')
        check('\u20b12,000.00' in page.locator('[data-partial-warning]').inner_text(),'Resumed liquidation retains partial-document explanation')
        review();check('debit: PHP 8000.00 supported of PHP 8000.00' in page.locator('.aw-coverage').inner_text(),'Liquidation review uses gross expenditure coverage only')
        page.screenshot(path=str(run/'liquidation-1366.png'),full_page=True);page.set_viewport_size({'width':1920,'height':1080});page.screenshot(path=str(run/'liquidation-1920.png'),full_page=True);page.set_viewport_size({'width':1366,'height':768});post()
        check(page.locator('#aw-posted-link').count()==0 and page.locator('#aw-history-link').count()==1,'Liquidation shows Journal History once')
        page.goto(base+'/cash_advance_entry.php?workflow_kind=advance_return&advance_id='+aid);ready();page.locator('#aw-purpose').fill('Return unused outreach cash');choose_record(page.locator('#aw-cash-account'),bank);page.locator('#aw-cash-amount').fill('2000.00');save();upload();proof_review();save();page.locator('#aw-confirm-proof').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Proof confirmed')")
        check('confirmation are required' in page.locator('#aw-evidence-help').inner_text() and 'Monetary evidence allocations are not used' in page.locator('#aw-evidence-help').inner_text(),'Return footer describes proof and confirmation requirements')
        page.locator('#aw-cash-amount').fill('1500.00');save();check('not been confirmed' in page.locator('#aw-proof-status').inner_text(),'Changing the return amount clears bound proof confirmation')
        page.locator('#aw-review').click();page.wait_for_function("document.getElementById('aw-error').textContent.length>0");check('Confirm the proof' in page.locator('#aw-error').inner_text(),'Changed return requires explicit confirmation again')
        page.locator('#aw-cash-amount').fill('2000.00');save();page.locator('#aw-confirm-proof').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Proof confirmed')");review()
        page.screenshot(path=str(run/'return-1366.png'),full_page=True);page.set_viewport_size({'width':1920,'height':1080});page.screenshot(path=str(run/'return-1920.png'),full_page=True);page.set_viewport_size({'width':1366,'height':768});post();check(page.locator('#aw-posted-link').get_attribute('href')=='financial_records.php?view=crb&from=&to=','Return routes to CRB')
        page.goto(base+'/cash_advances.php?advance_id='+aid);page.wait_for_selector('.ca-operation');check('PHP 0.00' in page.locator('#advance-register').inner_text() and page.locator('.ca-operation').count()==3,'Detail shows release/liquidation/return and settled balance')
        page.emulate_media(media='print');page.screenshot(path=str(run/'detail-print-1366.png'),full_page=True);check(page.locator('.app-sidebar').is_hidden(),'Print view hides application navigation');page.emulate_media(media='screen')
        page.goto(base+'/cash_advances.php');page.wait_for_function("document.getElementById('ca-status').textContent.includes('loaded')");check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Register fits 1366px laptop without page overflow');page.screenshot(path=str(run/'register-1366.png'),full_page=True);page.set_viewport_size({'width':1920,'height':1080});page.screenshot(path=str(run/'register-1920.png'),full_page=True)
        expected=ctx.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'register','as_of':today})).json()['result']['reconciliation']
        page.locator('[name="party_id"]').select_option(employee)
        with page.expect_response('**/cash_advance_actions.php?*') as filtered_response:page.locator('#ca-filters button').click()
        filtered=filtered_response.value.json()['result']['reconciliation'];page.wait_for_function("document.getElementById('ca-status').textContent.includes('loaded')");check(filtered==expected and all(r['ok'] for r in filtered),'Filtered register keeps full-account reconciliation')
        previous=page.locator('#ca-rows').inner_text();scope=page.locator('#ca-print-scope').inner_text();labels=page.locator('#ca-applied-filters').inner_text()
        page.route('**/cash_advance_actions.php?*',lambda route:route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'error':'fixture temporary failure'})));page.locator('[name="search"]').fill('unapplied');page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-error').textContent.includes('previously')");check(page.locator('#ca-rows').inner_text()==previous,'Failed register refresh preserves prior results');page.unroute('**/cash_advance_actions.php?*')
        check(page.locator('#ca-print-scope').inner_text()==scope and page.locator('#ca-applied-filters').inner_text()==labels and not page.locator('#ca-print').is_disabled(),'Failed refresh preserves printable applied scope and labels')
        # Response fixtures exercise the real renderer's multi-page print contract without seeding 40 advances.
        register=ctx.request.get(base+'/cash_advance_actions.php?'+urlencode({'action':'register','as_of':today})).json()['result']
        def register_fixture(route):
            query=dict(parse_qsl(urlparse(route.request.url).query))
            result={**register,'page':int(query.get('page','1')),'pages':2,'count':40}
            result['rows']=[dict(register['rows'][0]) for _ in range(25 if result['page']==1 else 15)]
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'result':result}))
        page.route('**/cash_advance_actions.php?*',register_fixture)
        page.locator('[name="project_id"]').select_option(project);page.locator('[name="control_account_id"]').select_option(control);page.locator('[name="status"]').select_option('partially_settled');page.locator('[name="overdue"]').select_option('1');page.locator('[name="search"]').fill('field <visit>');page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-totals-basis').textContent.includes('40')")
        labels=page.locator('#ca-applied-filters').inner_text()
        check(all(term in labels for term in ['Employee:','Originating project:','Control account: Account #'+control,'Settlement status: Partially settled','Overdue: Overdue','Search: field <visit>']),'Printed summary records all applied filters as safe text')
        check('rows 1-25 of 40' in page.locator('#ca-print-scope').inner_text() and 'all 40 matching advances (all pages)' in page.locator('#ca-totals-basis').inner_text(),'First page distinguishes printed rows from all-matching totals')
        page.locator('[name="search"]').fill('unsaved filter');check(page.locator('#ca-applied-filters').inner_text()==labels,'Unsaved filter edits do not change printed applied labels')
        page.locator('#ca-next').click();page.wait_for_function("document.getElementById('ca-print-scope').textContent.includes('rows 26-40')")
        check(page.locator('#ca-rows tr').count()==15 and page.locator('#ca-applied-filters').inner_text()==labels and 'Changes not applied' in page.locator('#ca-filter-status').inner_text(),'Pagination retains applied filters and explicitly labels the current page')
        for width,height in [(1366,768),(1920,1080)]:
            page.set_viewport_size({'width':width,'height':height});page.screenshot(path=str(run/f'register-supplement-{width}.png'),full_page=True)
        page.emulate_media(media='print');check(page.locator('#ca-filters').is_hidden() and page.locator('#ca-page').is_hidden() and page.locator('#ca-print-scope').is_visible() and page.locator('#ca-applied-filters').is_visible() and page.locator('#ca-totals-basis').is_visible(),'Printed register retains filters, row scope and totals basis while hiding controls')
        page.screenshot(path=str(run/'register-supplement-print-1920.png'),full_page=True);page.emulate_media(media='screen');page.unroute('**/cash_advance_actions.php?*',register_fixture)
        # Ignore cancellation deliberately to confirm an older response cannot replace any displayed scope.
        page.evaluate("""() => {window.registerFetch=window.fetch;window.registerPending=[];window.fetch=(url,options)=>String(url).includes('action=register')?new Promise(resolve=>window.registerPending.push(resolve)):window.registerFetch(url,options);} """)
        page.locator('[name="search"]').fill('older');page.locator('#ca-filters button').click();page.locator('[name="search"]').fill('newer');page.locator('#ca-filters button').click();page.wait_for_function('window.registerPending.length===2')
        newest={**register,'rows':[],'count':0,'page':1,'pages':1}
        page.evaluate('(result)=>window.registerPending[1](new Response(JSON.stringify({ok:true,result}),{status:200}))',newest);page.wait_for_function("document.getElementById('ca-applied-filters').textContent.includes('Search: newer')")
        page.evaluate('(result)=>window.registerPending[0](new Response(JSON.stringify({ok:true,result}),{status:200}))',register)
        # Next event-loop task runs after the deliberately late response's fetch/json continuations.
        page.evaluate('()=>new Promise(resolve=>setTimeout(resolve,0))')
        check('Search: newer' in page.locator('#ca-applied-filters').inner_text() and 'rows 0-0 of 0' in page.locator('#ca-print-scope').inner_text() and 'all 0 matching' in page.locator('#ca-totals-basis').inner_text(),'Stale response cannot overwrite newer empty results, print labels or totals basis')
        page.evaluate('()=>{window.fetch=window.registerFetch;delete window.registerPending;delete window.registerFetch;}')
        # Legacy public mutation endpoints reject every v3 evidence action before any write.
        saved=ctx.request.get(base+'/cash_advance_actions.php?action=draft&draft_id='+liq_id).json()['result'];identity={'draft_id':liq_id,'revision':str(saved['revision'])};receipt=saved['payload']['documents'][0]['receipt_id']
        for action in ['attach','remove','discard']:
            response=call(action,{**identity,'receipt_id':receipt},endpoint='accounting_actions.php');check(response.status==409,'Ordinary '+action+' rejects v3 context')
        # Management HTML, JSON, print and bytes, also with the Stage 2 UI flag disabled.
        mctx,msession=context('management@example.invalid');mpage=mctx.new_page();mpage.goto(base+'/cash_advances.php?advance_id='+aid);check(mpage.locator('a[href*="receipt_attachment"]').count()==0,'Management print/detail contains no private image links')
        for flag in [True,False]:
            config(stage2=flag)
            for view in ['', 'crb','cdb']:
                response=mctx.request.get(base+'/financial_records.php?'+urlencode({'format':'json','from':'','to':'','view':view}));body=response.text();check('receipt_attachment.php?receipt_id='+receipt not in body,'Management history/book privacy with Stage 2 '+str(flag)+' view '+(view or 'history'))
            posted_id=str(saved['posted_journal_id']);response=mctx.request.get(base+'/receipt_attachment.php?receipt_id='+receipt+'&journal_id='+posted_id);check(response.status==404,'Management image bytes denied with Stage 2 '+str(flag))
        config(enabled=False,stage2=False)
        response=mctx.request.get(base+'/financial_records.php?format=json&from=&to=');check('receipt_attachment.php?receipt_id='+receipt not in response.text(),'Advance privacy also survives the Stage 1 UI flag being disabled')
        config()
        check(mctx.request.get(base+'/cash_advance_actions.php?action=draft&draft_id='+liq_id).status==403,'Management cannot read private advance drafts')
        check(call('discard',identity,actor=mctx,csrf=msession['csrf']).status==403,'Management cannot mutate advances')
        anon,_=context();check(anon.request.get(base+'/cash_advance_actions.php?action=register').status==401,'Anonymous advance API is denied')
        # Durable successful replay recovers after new session and an expired token.
        newctx,newsession=context('admin@example.invalid');request={'draft_id':liq_id,'revision':str(saved['revision']),'submission_key':saved['submission_key'],'payload':saved['payload'],'review_token':'1000000000.'+'0'*64}
        response=call('post',request,actor=newctx,csrf=newsession['csrf']);check(response.ok and response.json()['result']['duplicate'],'HTTP retry across sessions and expired token recovers posted liquidation')
        check(not errors,'Advance browser workflows have no JavaScript errors')
        browser.close()
    print('Stage 2 browser checks: '+str(checks));print('Private browser artifacts: '+str(run))
finally:
    server.terminate();server.wait(timeout=10);log.close()
