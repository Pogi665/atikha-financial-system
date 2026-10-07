"""Stage 1 HTTP and real browser checks, on a new DB and private copied app.
Production config, sessions and uploads are never copied. All external requests blocked.
"""
import argparse, hashlib, json, os, re, secrets, shutil, socket, struct, subprocess, sys, time, zlib
from urllib.parse import urlencode
from pathlib import Path
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--database');parser.add_argument('--fixture');parser.add_argument('--browser',default='msedge');args=parser.parse_args()
php=shutil.which('php') or r'C:\xampp\php\php.exe'
if args.fixture:
    if args.database:parser.error('Choose database or fixture, not both')
    path=Path(args.fixture).resolve()
    if not path.is_relative_to(root/'.migration-private'):parser.error('Private fixture required')
    fixture=json.loads(path.read_text());args.database=fixture['database'];evidence=Path(fixture['receipt_root']).resolve()
    if not evidence.is_relative_to(root/'.migration-private'):parser.error('Private evidence required')
else:
    if not args.database or not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',args.database):parser.error('New disposable Stage 1 database required')
    bootstrap=subprocess.check_output([php,str(root/'scripts/test_stage1.php'),'--database='+args.database],text=True);print(bootstrap,end='')
    evidence=Path(re.search(r'Private evidence: (.+)',bootstrap).group(1).strip())/'receipts'
if not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',args.database):parser.error('Disposable Stage 1 database required')
run=root/'.migration-private'/('stage1-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+args.database+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(ocr=False,enabled=True):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',"+str(enabled).lower()+");define('OCR_JOURNAL_ENABLED',"+str(ocr).lower()+");define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
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
        page.goto(base+'/cash_disbursement.php');page.wait_for_selector('#aw-existing:enabled',state='attached')
        check(page.locator('#aw-lines [data-line]').count()==1,'Payment starts with one simple allocation')
        check(page.locator('aside a[href="accounting_setup.php"]').count()==1,'Enabled setup navigation appears')
        check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'1366px laptop form fits without page overflow')
        page.screenshot(path=str(run/'payment-1366.png'),full_page=True)
        bank=str(sql("SELECT CategoryID FROM Categories WHERE Name='Stage1 Bank'")[0]['CategoryID']);training=str(sql("SELECT CategoryID FROM Categories WHERE Name='Stage1 Training Expense'")[0]['CategoryID']);transport=str(sql("SELECT CategoryID FROM Categories WHERE Name='Stage1 Transportation'")[0]['CategoryID'])
        party=str(sql('SELECT id FROM parties LIMIT 1')[0]['id']);project=str(sql('SELECT id FROM projects LIMIT 1')[0]['id'])
        original_party=sql('SELECT name FROM parties WHERE id='+party)[0]['name'];original_project=sql('SELECT name FROM projects WHERE id='+project)[0]['name']
        browser_marker='Browser NGO workshop '+secrets.token_hex(6)
        page.locator('[data-payment-mode=split]').click();page.locator('#aw-optional summary').click();choose_record(page.locator('#aw-party'),party);choose_record(page.locator('#aw-default-project'),project);page.locator('#aw-purpose').fill(browser_marker+' <script>window.bad=1</script>')
        choose_record(page.locator('#aw-cash-account'),bank);page.locator('#aw-cash-amount').fill('8000.00')
        lines=page.locator('[data-line]');choose_record(lines.nth(0).locator('[data-field="account_id"]'),training);lines.nth(0).locator('[data-field="debit_amount"]').fill('5000.00');page.locator('#aw-add-line').click()
        choose_record(lines.nth(1).locator('[data-field="account_id"]'),transport);lines.nth(1).locator('[data-field="debit_amount"]').fill('3000.00');page.locator('#aw-apply-project').click()
        check(lines.nth(0).locator('[data-field="fund_project_id"]').input_value()==project and lines.nth(1).locator('[data-field="fund_project_id"]').input_value()==project,'Explicit apply-all assigns project to all lines')
        check(page.locator('#aw-difference').inner_text()=='₱0.00','Exact browser totals balance split payment')
        before=journal_count();page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved')")
        check(journal_count()==before,'Save draft has no journal effect');draft_id=re.search(r'draft_id=(\d+)',page.url).group(1)
        saved_label=page.locator('#aw-draft-label').inner_text()
        page.locator('#aw-party-search').fill('zz-no-such-party')
        check(page.locator('.aw-record-empty').filter(has_text='No matching').is_visible() and page.locator('#aw-party').input_value()==party,'No-match search preserves the committed party ID')
        page.keyboard.press('Enter');check(page.locator('#aw-draft-label').inner_text()==saved_label,'Search Enter does not submit or dirty the saved draft')
        page.keyboard.press('Escape');check(page.locator('#aw-party').input_value()==party and page.locator('#aw-party-search').input_value()==page.locator('#aw-party option:checked').inner_text(),'Escape restores the committed label')
        page.locator('#aw-cash-account-search').fill('stage1 bank');page.keyboard.press('ArrowDown');page.keyboard.press('ArrowDown');page.keyboard.press('Enter')
        check(page.locator('#aw-cash-account').input_value()==bank and page.locator('#aw-draft-label').inner_text()==saved_label,'Keyboard selection of the same record preserves saved state')
        page.locator('#aw-default-project-search').fill('nonmatching query');page.keyboard.press('Tab')
        check(page.locator('#aw-default-project').input_value()==project,'Tab cancels an uncommitted project query')
        page.locator('#aw-cash-account-search').fill('bank stage1');page.screenshot(path=str(run/'selector-1366.png'),full_page=True);page.keyboard.press('Escape')
        # A fresh authenticated session can recover the persistent draft.
        ctx2,s2=context('admin@example.invalid');pg=ctx2.new_page();pg.on('pageerror',lambda e:errors.append(str(e)));pg.goto(base+'/cash_disbursement.php?draft_id='+draft_id);pg.wait_for_function("id=>document.getElementById('aw-draft-label').textContent.startsWith('Draft #'+id+' · saved')",arg=draft_id)
        check(pg.locator('#aw-purpose').input_value().startswith(browser_marker) and pg.locator('[data-line]').count()==2,'Draft survives a new login session')
        # Commit an upload at the server, then lose its response. Retry must reuse its durable key.
        uploads_before=int(sql('SELECT COUNT(*) n FROM Receipts')[0]['n']);lost=[False]
        def lose_upload(route):
            if not lost[0] and route.request.method=='POST' and 'multipart/form-data' in route.request.headers.get('content-type',''):
                lost[0]=True;route.fetch();route.abort('failed')
            else:route.continue_()
        pg.route('**/accounting_actions.php',lose_upload)
        pg.locator('#aw-document-group summary').click();image=png();pg.locator('#aw-upload').set_input_files({'name':'Workshop <script>.png','mimeType':'image/png','buffer':image});pg.wait_for_selector('#aw-upload-retry:not([hidden])');pg.locator('#aw-upload-retry').click();pg.wait_for_selector('[data-document]');pg.unroute('**/accounting_actions.php',lose_upload)
        check(int(sql('SELECT COUNT(*) n FROM Receipts')[0]['n'])==uploads_before+1,'Lost upload response retry recovers one reserved image')
        check(journal_count()==before and sql("SELECT model FROM receipt_ocr_attempts ORDER BY id DESC LIMIT 1")[0]['model']=='manual','Upload stages evidence with OCR disabled and no posting')
        doc=pg.locator('[data-document]');doc_id=doc.get_attribute('data-document');doc.locator('[data-doc-field="purpose"]').select_option('amount')
        doc.locator('[data-doc-field="declared_amount"]').fill('6000.00');doc.locator('[data-doc-field="accepted_amount"]').fill('6000.00')
        alloc=doc.locator('[data-allocation]');check(alloc.count()==2,'Evidence editor exposes both split allocation lines');alloc.nth(0).fill('5000.00');alloc.nth(1).fill('1000.00');doc.locator('[data-doc-field="reviewed"]').check()
        pg.locator('#aw-review').click();pg.wait_for_selector('#aw-review-panel:not([hidden])')
        check('Partially covered' in pg.locator('.aw-coverage').inner_text() and '8000.00' in pg.locator('.aw-coverage').inner_text(),'Browser review clearly shows partial evidence against gross allocations')
        check(not pg.locator('#aw-entry').is_visible(),'Focused Review hides editing controls');pg.locator('#aw-back').click();pg.locator('#aw-party-search').fill('no such choice')
        check('saved' in pg.locator('#aw-draft-label').inner_text(),'Search typing after Back preserves the saved entry')
        pg.keyboard.press('Escape')
        choose_record(pg.locator('#aw-cash-project'),'');check(pg.locator('#aw-review-panel').is_hidden() and 'unsaved' in pg.locator('#aw-draft-label').inner_text(),'Committing a different project invalidates review and marks the draft dirty')
        choose_record(pg.locator('#aw-cash-project'),project);pg.locator('#aw-review').click();pg.wait_for_selector('#aw-review-panel:not([hidden])')
        pg.set_viewport_size({'width':1920,'height':1080});pg.screenshot(path=str(run/'payment-review-1920.png'),full_page=True)
        pg.locator('#aw-back').click();pg.locator('#aw-optional summary').click();pg.locator('#aw-default-project-search').fill('synthetic community');pg.screenshot(path=str(run/'selector-1920.png'),full_page=True);pg.keyboard.press('Escape')
        pg.locator('#aw-review').click();pg.wait_for_selector('#aw-review-panel:not([hidden])');check(pg.evaluate('window.bad===undefined'),'Purpose and image names are inert text')
        # Old receipt endpoints must reject reservations even when enabled.
        config(ocr=True);legacy=ctx2.new_page();legacy.goto(base+'/ocr_expense.php');legacy_data=legacy.locator('script[type="application/json"]').all_text_contents()
        # Sign request keys using the existing rendered field, never fake authorization.
        legacy_key=legacy.locator('[name="request_key"]').first.input_value() if legacy.locator('[name="request_key"]').count() else ''
        if not legacy_key:
            legacy_key=legacy.evaluate("typeof receiptData!=='undefined'?receiptData.request_key:''")
        if not legacy_key:
            script_text=legacy.content();match=re.search(r'"request_key"\s*:\s*"([a-f0-9]{64})"',script_text);legacy_key=match.group(1) if match else ''
        check(bool(legacy_key),'Existing OCR request key obtained from authorized page')
        res=ctx2.request.post(base+'/ocr_expense.php',form={'action':'discard','receipt_id':doc_id,'csrf_token':s2['csrf'],'request_key':legacy_key},max_redirects=0)
        check(res.status==409 and (evidence/Path(sql('SELECT File_Path FROM Receipts WHERE ReceiptID='+doc_id)[0]['File_Path']).name).is_file(),'Old discard cannot delete a reserved image')
        res=ctx2.request.post(base+'/ocr_extract.php',form={'action':'retry','receipt_id':doc_id,'csrf_token':s2['csrf'],'request_key':legacy_key})
        check(res.status==409,'Old OCR reprocessing cannot bypass a draft reservation')
        config(ocr=False)
        # Actual UI posting and lost-response recovery in another session.
        pg.locator('#aw-post').click();pg.wait_for_selector('#aw-posted:not([hidden])');check(journal_count()==before+1,'Browser posts payment exactly once')
        check(pg.locator('#aw-posted-link').get_attribute('href')=='financial_records.php?view=cdb&from=&to=' and pg.locator('#aw-history-link').get_attribute('href')=='financial_records.php?from=&to=','Payment result links separately to CDB and Journal History')
        pg.reload();pg.wait_for_selector('#aw-posted:not([hidden])');check(pg.locator('#aw-purpose').is_disabled(),'Reloaded posted draft is read-only')
        check(pg.locator('#aw-party-search').is_disabled() and pg.locator('.aw-record-toggle').first.is_disabled(),'Reloaded posted searchable controls remain disabled')
        check(ctx2.request.get(base+'/'+pg.locator('[data-document] a').first.get_attribute('href')).status==200,'Posted draft still opens its original evidence with journal authorization')
        later_party='Changed supplier '+secrets.token_hex(6);later_project='Changed project '+secrets.token_hex(6)
        sql("UPDATE parties SET name='"+later_party+"',is_active=0 WHERE id="+party,True);sql("UPDATE projects SET name='"+later_project+"',is_active=0 WHERE id="+project,True)
        pg.reload();pg.wait_for_selector('#aw-posted:not([hidden])');check(original_party in pg.locator('#aw-party option:checked').inner_text() and original_project in pg.locator('[data-line] [data-field="fund_project_id"]').first.locator('option:checked').inner_text() and later_party not in pg.locator('#aw-party option:checked').inner_text() and later_project not in pg.locator('[data-line] [data-field="fund_project_id"]').first.locator('option:checked').inner_text(),'Posted draft keeps snapshot labels after master rename and disabling')
        sql('UPDATE parties SET is_active=1 WHERE id='+party,True);sql('UPDATE projects SET is_active=1 WHERE id='+project,True)
        d=ctx2.request.get(base+'/accounting_actions.php?action=draft&draft_id='+draft_id).json()['result']
        retry={'action':'post','csrf_token':session['csrf'],'draft_id':draft_id,'revision':str(d['revision']),'submission_key':d['submission_key'],'payload':d['payload'],'review_token':'1000000000.'+'0'*64}
        res=ctx.request.post(base+'/accounting_actions.php',data=json.dumps(retry),headers={'Content-Type':'application/json'})
        check(res.status==200 and res.json()['result']['duplicate'] and journal_count()==before+1,'HTTP matching retry across sessions and expired review recovers result')
        retry['payload']['description']='Changed content';res=ctx.request.post(base+'/accounting_actions.php',data=json.dumps(retry),headers={'Content-Type':'application/json'});check(res.status==409,'HTTP changed content with durable key conflicts')
        # Search selects journal IDs, keeping both sides and exact totals.
        page.goto(base+'/financial_records.php?view=cdb&from=&to=');page.wait_for_selector('.dt-search input');page.locator('.dt-search input').fill(browser_marker);page.wait_for_timeout(300)
        check(page.locator('#records-table tbody tr').count()==3 and page.locator('#records-total-debit').inner_text()=='₱8,000.00' and page.locator('#records-total-credit').inner_text()=='₱8,000.00','Cash-book search retains complete entry and balanced totals')
        page.locator('.dt-search input').fill('Stage1 Transportation '+browser_marker);page.wait_for_timeout(200);check(page.locator('#records-table tbody tr').count()==3,'Search terms on different lines retain the whole journal')
        page.locator('.records-view').last.click();page.wait_for_selector('#transaction-dialog[open]');check('Reviewed monetary evidence' in page.locator('#transaction-documents').inner_text(),'History displays posted manual evidence review');check('Partially covered' in page.locator('#transaction-details').inner_text(),'Posted cash-book details retain partial coverage status');page.keyboard.press('Escape')
        page.screenshot(path=str(run/'cash-book-1366.png'),full_page=True)
        # Inline masters and server validation recovery.
        page.goto(base+'/cash_receipt.php');page.wait_for_selector('#aw-existing:enabled');page.locator('[data-new-master="parties"]').click();page.locator('#aw-master-form [name="name"]').fill('Browser new NGO donor');page.locator('#aw-master-form [name="party_type"]').select_option('organization');page.locator('#aw-master-form button.aw-primary').click();page.wait_for_selector('#aw-master-dialog:not([open])',state='attached')
        check('Browser new NGO donor' in page.locator('#aw-party option:checked').inner_text(),'Inline party creation selects saved record')
        page.wait_for_function("document.activeElement.id==='aw-party-search'");check(page.evaluate('document.activeElement.id')=='aw-party-search','Inline creation restores focus to the searchable party')
        page.locator('#aw-purpose').fill('Keep values after validation failure');page.locator('#aw-review').click();page.wait_for_function("document.getElementById('aw-error').textContent.length>0")
        check(page.locator('#aw-purpose').input_value()=='Keep values after validation failure' and page.locator('#aw-save').is_enabled(),'Validation failure retains values and enables recovery')
        page.locator('#aw-purpose').focus();page.keyboard.press('Tab');check(page.evaluate('document.activeElement.id')=='aw-cash-account-search','Keyboard moves through labelled searchable fields')
        project_code='BROWSER-'+secrets.token_hex(6).upper()
        page.locator('[data-new-master="projects"]').click();page.locator('#aw-master-form [name="name"]').fill('Browser community project');page.locator('#aw-master-form [name="code"]').fill(project_code);page.locator('#aw-master-form button.aw-primary').click();page.wait_for_function("document.activeElement.id==='aw-default-project-search'")
        check(project_code in page.locator('#aw-default-project-search').input_value() and page.locator('#aw-purpose').input_value()=='Keep values after validation failure','Inline project creation keeps form values and restores searchable focus')
        for source in ['CRB','GJ']:
            existing=str(sql("SELECT id FROM journal_drafts WHERE state='Posted' AND source_book='"+source+"' ORDER BY id LIMIT 1")[0]['id'])
            page.goto(base+('/cash_receipt.php' if source=='CRB' else '/general_journal.php')+'?draft_id='+existing);page.wait_for_selector('#aw-posted:not([hidden])')
            check(page.locator('#aw-history-link').get_attribute('href')=='financial_records.php?from=&to=' and page.locator('#aw-posted-link').count()==(1 if source=='CRB' else 0),'Reopened '+source+' result has correct, nonredundant navigation')
            if source=='CRB':check(page.locator('#aw-posted-link').get_attribute('href')=='financial_records.php?view=crb&from=&to=','Receipt result links to CRB across all dates')
        # Fixtures below mutate ONLY the disposable database, never working data.
        owner=int(sql("SELECT UserID FROM Users WHERE Email='admin@example.invalid'")[0]['UserID']);other=int(sql("SELECT UserID FROM Users WHERE Email='other@example.invalid'")[0]['UserID'])
        template=json.loads(json.dumps(d['payload']));template['documents']=[]
        def fixture(label,saved,entry='2020-01-01',book='CDB',actor=None,unavailable=False):
            payload=json.loads(json.dumps(template));payload['entry_date']=entry;payload['description']='Saved-date fixture '+label
            if unavailable:payload['party_id']='999999'
            encoded=json.dumps(payload).replace('\\','\\\\').replace("'","''");key=secrets.token_hex(32)
            sql("INSERT INTO journal_drafts(owner_id,source_book,payload,creation_hash,submission_key,created_at,updated_at) VALUES("+str(actor or owner)+",'"+book+"','"+encoded+"','"+'0'*64+"','"+key+"','"+saved+"','"+saved+"')",True)
            return int(sql("SELECT id FROM journal_drafts WHERE submission_key='"+key+"'")[0]['id'])
        before_day=fixture('before','2026-10-05 15:59:59');start=fixture('start','2026-10-05 16:00:00');end=fixture('end','2026-10-06 15:59:59',book='GJ')
        after_day=fixture('after','2026-10-06 16:00:00');empty_date=fixture('empty','2026-10-05 17:00:00',entry='');foreign=fixture('foreign','2026-10-05 18:00:00',actor=other)
        query={'action':'drafts','from':'2026-10-06','to':'2026-10-06'}
        r=ctx.request.get(base+'/accounting_actions.php?'+urlencode(query));check(r.status==200,'Saved-date API accepts a valid Manila range');saved_rows=r.json()['result'];ids={int(row['id']) for row in saved_rows}
        check({start,end,empty_date}<=ids and not {before_day,after_day,foreign}&ids,'Saved-date query includes both day edges and empty accounting dates, excluding next day and other owner')
        start_row=next(row for row in saved_rows if int(row['id'])==start);check(start_row['updated_at']=='2026-10-05 16:00:00' and start_row['updated_at_display']=='2026-10-06 00:00:00','UTC API value is preserved with an additive Manila display value')
        r=ctx.request.get(base+'/accounting_actions.php?'+urlencode(query|{'source_book':'CDB'}));check(r.status==200 and all(row['source_book']=='CDB' for row in r.json()['result']),'Saved-date filtering combines with entry type')
        for filters in [{'from':'2026-10-07','to':'2026-10-06'},{'from':'2026-02-30'},{'from':'0999-12-31'},{'source_book':'bad'}]:
            r=ctx.request.get(base+'/accounting_actions.php?'+urlencode({'action':'drafts'}|filters));check(r.status==400 and bool(r.json().get('error')),'Invalid draft filter returns descriptive 400: '+str(filters))
        r=ctx.request.get(base+'/accounting_actions.php?action=drafts&from[]=2026-10-06');check(r.status==400,'Array-valued draft date is rejected with 400')
        missing=fixture('unavailable','2026-10-05 19:00:00',unavailable=True)
        page.goto(base+'/cash_disbursement.php?draft_id='+str(missing));page.wait_for_selector('#aw-party-search:enabled');page.wait_for_function("document.getElementById('aw-party').value==='999999'")
        page.locator('#aw-party-search').fill('999999');check(page.locator('#aw-party').input_value()=='999999' and page.locator('#aw-party-choices [data-value="999999"]').count()==0,'Unavailable restored ID remains visible but cannot be selected as an eligible option');page.keyboard.press('Escape')
        page.goto(base+'/accounting_drafts.php');page.wait_for_function("document.getElementById('draft-status').textContent.includes('loaded')")
        page.locator('[name="from"]').fill('2026-10-06');page.locator('[name="to"]').fill('2026-10-06');check(page.locator('#draft-filter-status').inner_text()=='Changes not applied.','Changed saved-date controls clearly remain unapplied')
        page.locator('#draft-filters button').click();page.wait_for_function("document.getElementById('draft-filter-status').textContent==='' && document.getElementById('draft-list').getAttribute('aria-busy')==='false'")
        check(page.locator('[data-draft-id="'+str(start)+'"]').inner_text().endswith('Discard') and '2026-10-06 00:00:00' in page.locator('[data-draft-id="'+str(start)+'"]').inner_text(),'Draft list shows accounting date separately from Manila last-saved date')
        page.screenshot(path=str(run/'drafts-1366.png'),full_page=True);page.set_viewport_size({'width':1920,'height':1080});page.screenshot(path=str(run/'drafts-1920.png'),full_page=True)
        previous=page.locator('#draft-list').inner_text()
        page.route('**/accounting_actions.php?action=drafts*',lambda route:route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'error':'Injected draft lookup failure'})))
        page.locator('[name="source_book"]').select_option('CDB');page.locator('#draft-filters button').click();page.wait_for_function("document.getElementById('draft-error').textContent.includes('Injected')")
        check(page.locator('#draft-list').inner_text()==previous and page.locator('#draft-filter-status').inner_text()=='Changes not applied.','Failed refresh preserves the last successful list and unapplied filters');page.unroute('**/accounting_actions.php?action=drafts*')
        # Ignore abort in this mock deliberately: sequence checks must reject a late response too.
        mock_cdb=start_row.copy();mock_gj=next(row for row in saved_rows if int(row['id'])==end)
        page.evaluate("""() => {window.__realDraftFetch=window.fetch;window.__draftPending=[];window.fetch=(url,options)=>String(url).includes('action=drafts')?new Promise(resolve=>window.__draftPending.push(resolve)):window.__realDraftFetch(url,options);} """)
        page.locator('[name="source_book"]').select_option('CDB');page.locator('#draft-filters button').click();page.locator('[name="source_book"]').select_option('GJ');page.locator('#draft-filters button').click();page.wait_for_function('window.__draftPending.length===2')
        page.evaluate("row=>window.__draftPending[1](new Response(JSON.stringify({ok:true,result:[row]}),{status:200,headers:{'Content-Type':'application/json'}}))",mock_gj);page.wait_for_function("document.querySelector('#draft-list').textContent.includes('Saved-date fixture end')")
        page.evaluate("row=>window.__draftPending[0](new Response(JSON.stringify({ok:true,result:[row]}),{status:200,headers:{'Content-Type':'application/json'}}))",mock_cdb);page.wait_for_timeout(100)
        check('Saved-date fixture end' in page.locator('#draft-list').inner_text() and 'Saved-date fixture start' not in page.locator('#draft-list').inner_text(),'Late response cannot overwrite the newer draft result');page.evaluate('window.fetch=window.__realDraftFetch')
        page.locator('#draft-filters button').click();page.wait_for_function("document.getElementById('draft-list').getAttribute('aria-busy')==='false'")
        page.locator('[name="from"]').fill('2026-10-07');previous=page.locator('#draft-list').inner_text();page.locator('#draft-filters button').click();page.wait_for_function("document.getElementById('draft-error').textContent.includes('on or before')")
        check(page.locator('#draft-list').inner_text()==previous,'Invalid saved-date range leaves prior successful results intact')
        page.locator('[name="from"]').fill('0999-12-31');page.evaluate("document.getElementById('draft-filters').dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}))")
        check(page.locator('#draft-list').inner_text()==previous and 'Correct the last saved dates' in page.locator('#draft-error').inner_text(),'Browser-invalid saved date cannot be broadened into an empty filter')
        page.locator('[name="from"]').fill('2026-10-06');page.locator('[name="source_book"]').select_option('CDB');page.locator('#draft-filters button').click();page.wait_for_selector('[data-draft-id="'+str(start)+'"]')
        page.locator('[data-draft-id="'+str(start)+'"] a').click();page.wait_for_selector('#aw-purpose');page.wait_for_function("document.getElementById('aw-purpose').value==='Saved-date fixture start'")
        check(page.locator('#aw-draft-label').inner_text().endswith('saved'),'Filtered draft resumes through its existing entry page')
        page.goto(base+'/accounting_drafts.php');page.wait_for_selector('[data-draft-id="'+str(start)+'"]');count_before_discard=journal_count();page.once('dialog',lambda dialog:dialog.accept());page.locator('[data-draft-id="'+str(start)+'"] button').click();page.wait_for_selector('[data-draft-id="'+str(start)+'"]',state='detached')
        check(ctx.request.get(base+'/accounting_actions.php?action=draft&draft_id='+str(start)).json()['result']['state']=='Discarded' and journal_count()==count_before_discard,'Draft-list discard refreshes correctly without changing journal records')
        for email in ['other@example.invalid','management@example.invalid']:
            c,s=context(email);res=c.request.get(base+'/accounting_actions.php?action=draft&draft_id='+draft_id);check(res.status==(404 if email.startswith('other') else 403),'Owner and Management API restrictions: '+email);c.close()
        anon,_=context();check(anon.request.get(base+'/accounting_actions.php?action=lists').status==401,'Anonymous API access rejected');anon.close()
        page.goto(base+'/accounting_setup.php');page.wait_for_selector('#setup-projects tr');check('Stage1 Cash Advance Employees' in page.locator('#setup-controls-list').inner_text(),'Setup shows designated account');page.screenshot(path=str(run/'setup-1366.png'),full_page=True)
        config(enabled=False);check(ctx.request.get(base+'/cash_receipt.php').status==503,'Disabled feature fails closed');page.goto(base+'/general_journal.php');check(page.locator('#journal-form').count()==1,'Flag off preserves legacy General Journal');config()
        check(not errors,'No uncaught browser errors: '+str(errors));browser.close()
    print('Stage 1 browser checks:',checks);print('Browser artifacts:',run)
finally:server.terminate();server.wait(timeout=10);log.close()
