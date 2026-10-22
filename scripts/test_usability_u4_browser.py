"""U4 connected gates on a fresh guarded synthetic full-schema clone only."""
from html.parser import HTMLParser
from itertools import product
from pathlib import Path
from urllib.parse import urlencode
import argparse,hashlib,json,os,re,secrets,shutil,socket,subprocess,sys,time
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--browser',default='msedge');parser.add_argument('--gate',choices=['a','b','c','d','all'],default='a');args=parser.parse_args()
fixture_path=Path(args.fixture).resolve();private=(root/'.migration-private').resolve()
if not fixture_path.is_relative_to(private):parser.error('Private fixture required')
fixture=json.loads(fixture_path.read_text());db=fixture['database'];evidence=Path(fixture['receipt_root']).resolve()
if not re.fullmatch(r'atikha_test_stage1_u4[a-z0-9]+',db) or not evidence.is_relative_to(private):parser.error('Disposable database/private evidence required')
php=r'C:\xampp\php\php.exe';run=private/('u4-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['config.php','db_connect.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
def connect(name=db):
    assert re.fullmatch(r'atikha_test_stage1_u4[a-z0-9]+',name)
    dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+name+';charset=utf8mb4'
    (app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(s1=True,s2=True,s3=True,ocr=True):
    flags=dict(STAGE1_WORKSPACE_ENABLED=s1,STAGE2_ADVANCES_ENABLED=s2,STAGE3_CORRECTIONS_ENABLED=s3,OCR_JOURNAL_ENABLED=ocr)
    (app/'config.php').write_text('<?php '+''.join('define('+ps(k)+','+str(v).lower()+');' for k,v in flags.items())+'define(\'GEMINI_API_KEY\',\'synthetic-no-network\');define(\'RECEIPT_UPLOAD_DIR\','+ps(evidence)+');',encoding='utf-8')
connect();config()
def sql(query,write=False,name=db):
    assert re.fullmatch(r'atikha_test_stage1_u4[a-z0-9]+',name)
    code='<?php require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(name)+');echo json_encode('+('$p->exec('+ps(query)+')' if write else '$p->query('+ps(query)+')->fetchAll()')+');'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
def state():
    code='<?php require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(db)+');$p->exec("SET TRANSACTION READ ONLY");$p->beginTransaction();$tables=$p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME")->fetchAll();$out=[];foreach($tables as $t){$n=$t["TABLE_NAME"];$out[$n]=hash("sha256",json_encode($p->query("SELECT * FROM `".$n."` ORDER BY 1")->fetchAll(),JSON_THROW_ON_ERROR));}$p->rollBack();echo json_encode($out);'
    return json.loads(subprocess.check_output([php],input=code,text=True))

sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
# Copied-app transport double plus disabled PHP transports: no provider call is possible.
g=app/'includes/gemini_client.php';g.write_text(g.read_text(encoding='utf-8').replace('function gemini_request(', 'function original_gemini_request(')+"\nfunction gemini_request(array $payload,?int $timeout=null,?int $connect=null):array{return ['ok'=>false,'raw'=>'synthetic failure','text'=>'','error'=>'Synthetic unavailable provider'];}\n",encoding='utf-8')
router=run/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads|scripts|includes|vendor)/~',$p)||preg_match('~^/(forecast_ai|gemini|anomaly_scan|audit_summary|external_email|send_email)~',$p)){http_response_code(403);exit('Blocked isolated boundary');}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-d','disable_functions=curl_exec,mail,fsockopen,stream_socket_client','-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks={k:0 for k in 'abcde'};gate='a';errors=[];success=False
def check(ok,label):
    if not ok:raise AssertionError(label)
    checks[gate]+=1;print('PASS '+gate.upper()+': '+label,flush=True)
try:
    with sync_playwright() as pw:
        browser=pw.chromium.launch(channel=args.browser,headless=True)
        def context(email):
            c=browser.new_context(viewport={'width':1366,'height':768});c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda r:r.abort());ss=login(email);c.add_cookies([{'name':'PHPSESSID','value':ss['session'],'url':base}]);return c,ss
        admin,session=context('admin@example.invalid');manager,ms=context('management@example.invalid');page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.on('dialog',lambda d:d.accept())
        def capture(label):
            for width,height in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':width,'height':height});page.evaluate('window.scrollTo(0,0)');check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),label+' fits '+str(width));page.screenshot(path=str(run/(label+'-'+str(width)+'.png')),full_page=True)
            page.set_viewport_size({'width':1366,'height':768})
        if args.gate in ['a','all']:
            before=state();page.goto(base+'/accounting_drafts.php',wait_until='networkidle')
            page.wait_for_function("document.querySelector('#draft-status').textContent.includes('loaded')")
            check(page.locator('label:has(select[name=source_book])').inner_text().startswith('Source book'),'Book filter is not presented as workflow filter')
            records=admin.request.get(base+'/accounting_actions.php?action=drafts').json()['result']
            for row in records:
                tr=page.locator('[data-draft-id="'+str(row['id'])+'"]');href=tr.locator('a').get_attribute('href');kind=row['workflow_kind']
                expected='journal_correction.php' if kind=='correction' else 'cash_advance_entry.php' if kind.startswith('advance_') else {'CRB':'cash_receipt.php','CDB':'cash_disbursement.php','GJ':'general_journal.php'}[row['source_book']]
                check(href==expected+'?draft_id='+str(row['id']),'Persisted '+kind+' dispatch retains draft #'+str(row['id']))
            check(manager.request.get(base+'/accounting_drafts.php').status==403,'Management cannot open private drafts')
            check(manager.request.get(base+'/accounting_actions.php?action=drafts').status==403,'Management cannot read private draft JSON')
            check(state()==before,'All draft list/page reads leave every application table unchanged')
            html=page.locator('#draft-list').inner_html()
            def fail_list(route):route.fulfill(status=503,json={'ok':False,'error':'Synthetic unavailable list'})
            page.route('**/accounting_actions.php?action=drafts*',fail_list)
            page.locator('button:has-text("Apply filters")').click();page.wait_for_function("document.querySelector('#draft-error').textContent.includes('Synthetic')")
            check(page.locator('#draft-list').inner_html()==html,'Failed refresh preserves the complete prior successful list')
            page.unroute('**/accounting_actions.php?action=drafts*',fail_list)
            def malformed_list(route):route.fulfill(json={'ok':True,'result':[{'id':1}]})
            page.route('**/accounting_actions.php?action=drafts*',malformed_list);page.locator('button:has-text("Apply filters")').click();page.wait_for_function("document.querySelector('#draft-error').textContent.includes('Unexpected')")
            check(page.locator('#draft-list').inner_html()==html,'Malformed draft projection cannot replace a verified list')
            page.unroute('**/accounting_actions.php?action=drafts*',malformed_list)
            # Create a private ordinary draft through the unchanged public contract.
            payload={'entry_date':time.strftime('%Y-%m-%d'),'reference':'U4-SYNTHETIC','description':'Synthetic saved outreach travel','party_id':str(fixture['party']),'default_project_id':'','cash_account_id':str(fixture['bank']),'cash_amount':'100.00','cash_project_id':'','transaction_kind':'ordinary','lines':[{'client_id':'f'*64,'account_id':str(fixture['training']),'fund_project_id':'','debit_amount':'100.00','credit_amount':''}],'documents':[]}
            saved=admin.request.post(base+'/accounting_actions.php',data={'action':'save','csrf_token':session['csrf'],'draft_id':'','submission_key':secrets.token_hex(32),'source_book':'CDB','payload':payload}).json()
            assert saved['ok'],saved
            did=saved['result']['id'];page.reload(wait_until='networkidle');page.wait_for_selector('[data-draft-id="'+str(did)+'"]')
            def lose_discard(route):
                if route.request.method=='POST' and route.request.post_data_json.get('action')=='discard':
                    response=route.fetch();assert response.json()['ok'];route.abort()
                else:route.continue_()
            page.route('**/accounting_actions.php',lose_discard)
            row=page.locator('[data-draft-id="'+str(did)+'"]');row.get_by_role('button',name='Discard',exact=True).click();page.wait_for_function("document.querySelector('#draft-error').textContent.includes('not confirmed')")
            check(row.locator('a').get_attribute('href') is None,'Actual committed lost discard response disables Continue')
            after=state();row.get_by_role('button',name='Check discard result',exact=True).click();page.wait_for_function("document.querySelector('#draft-status').textContent.includes('no longer an active')")
            check(state()==after and row.get_by_role('button').is_disabled(),'Result check sends no second mutation and retains safe inactive row')
            page.unroute('**/accounting_actions.php',lose_discard)
            capture('a-draft-result')
        if args.gate in ['b','all']:
            gate='b';before=state();page.goto(base+'/financial_records.php?from=&to=',wait_until='networkidle');page.wait_for_selector('#records-table tbody .records-view')
            check('Find a transaction' in page.locator('h1').inner_text() and 'General Ledger' not in page.locator('h1').inner_text(),'History is not labelled as unfinished running General Ledger')
            page.locator('#records-table tbody .records-view').first.click();page.wait_for_selector('#transaction-dialog[open]')
            exact=page.get_by_role('link',name=re.compile('View complete transaction #'));href=exact.get_attribute('href');journal=int(href.split('=')[-1]);exact.click();page.wait_for_selector('.correction-details')
            check('Transaction #'+str(journal) in page.locator('h1').inner_text(),'Modal opens the same exact journal ID')
            check(page.locator('tfoot').inner_text().startswith('Complete entry totals'),'Exact view retains full-entry totals')
            capture('b-exact-transaction')
            mp=manager.new_page();mp.goto(base+'/journal_transaction.php?journal_id='+str(journal),wait_until='networkidle')
            check(mp.locator('.aw-correction-entry').count()==0,'Management exact reader exposes no correction authoring action')
            mp.goto(base+'/financial_records.php?view=crb&from=&to=',wait_until='networkidle')
            check('Gross originating book activity' in mp.locator('.records-page').inner_text(),'Management receives truthful gross book explanation')
            correction=sql('SELECT id,target_journal_id,reversal_journal_id,replacement_journal_id FROM journal_corrections ORDER BY id LIMIT 1')[0]
            page.goto(base+'/journal_corrections.php?correction_id='+str(correction['id']),wait_until='networkidle')
            for field in ['target_journal_id','reversal_journal_id','replacement_journal_id']:
                if correction[field] is not None:check(page.locator('.correction-journal h3 a[href="journal_transaction.php?journal_id='+str(correction[field])+'"]').count()>=1,'Posted comparison links actual '+field)
            capture('b-correction-comparison')
            config(s1=False,s2=False,s3=False,ocr=False)
            check(manager.request.get(base+'/journal_corrections.php?correction_id='+str(correction['id'])).ok,'Posted comparison remains readable with authoring flags off')
            check(manager.request.get(base+'/journal_transaction.php?journal_id='+str(journal)).ok,'Exact posted reader remains available with UI flags off')
            config();check(state()==before,'Finding and inspection leave every application table unchanged')
        if args.gate in ['c','all']:
            gate='c';cases=json.loads((fixture_path.parent/'u4-cases.json').read_text());assert cases['database']==db
            for case in cases['ordinary']+cases['advance']:
                label=case.get('book',case.get('kind'))+'-'+case['mode'];did=case['draft_id']
                page.goto(base+'/journal_correction.php?draft_id='+str(did),wait_until='networkidle');page.wait_for_function("document.querySelector('#aw-save').disabled===false")
                check(page.locator('#aw-correction-mode').is_disabled(),'Saved mode remains immutable '+label)
                expected='cash_advances' if 'kind' in case else {'CRB':'crb','CDB':'cdb','GJ':'financial_records'}[case['book']]
                check(page.locator('a[aria-current=page]').get_attribute('data-nav-key')==expected,'Trusted original provenance drives navigation '+label)
                if case is cases['ordinary'][0]:
                    before_mode=state();page.locator('#aw-correction-mode').evaluate("el=>{el.value='reverse_replace';el.dispatchEvent(new Event('change',{bubbles:true}))}")
                    check(page.locator('#aw-correction-mode').input_value()=='reverse_only' and state()==before_mode,'Forced saved-mode change restores persisted mode without converting the draft')
                    reason=page.locator('#aw-correction-reason').input_value()+' updated synthetic wording';page.locator('#aw-correction-reason').fill(reason)
                    page.locator('.aw-header a[href="accounting_drafts.php"]').click();page.wait_for_selector('#aw-leave-dialog[open]');page.locator('#aw-leave-stay').click()
                    check(page.locator('#aw-correction-reason').input_value()==reason,'Correction Stay retains edited reason')
                    def fail_save(route):
                        if route.request.method=='POST' and route.request.post_data_json.get('action')=='save':route.fulfill(status=503,json={'ok':False,'error':'Synthetic failed correction save'})
                        else:route.continue_()
                    page.route('**/journal_correction_actions.php',fail_save);page.locator('.aw-header a[href="accounting_drafts.php"]').click();page.locator('#aw-leave-save').click();page.wait_for_function("document.querySelector('#aw-leave-error').textContent.includes('Synthetic')")
                    check(page.locator('#aw-leave-dialog').is_visible() and page.locator('#aw-correction-reason').input_value()==reason and state()==before_mode,'Failed correction Save-and-leave preserves input and every table')
                    page.locator('#aw-leave-stay').click();page.unroute('**/journal_correction_actions.php',fail_save)
                    page.locator('.aw-header a[href="accounting_drafts.php"]').click();page.locator('#aw-leave-save').click();page.wait_for_url('**/accounting_drafts.php')
                    page.goto(base+'/journal_correction.php?draft_id='+str(did),wait_until='networkidle');page.wait_for_function("document.querySelector('#aw-save').disabled===false")
                    check(page.locator('#aw-correction-reason').input_value()==reason and page.locator('#aw-correction-mode').is_disabled(),'Successful Save-and-leave preserves the same correction identity and mode')
                page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])')
                check(page.locator('#aw-error').inner_text()=='' and 'preview' in page.locator('#aw-review-content').inner_text(),'Full correction previews load '+label)
                links=page.locator('#aw-review-content a').evaluate_all('(nodes)=>nodes.map(n=>n.getAttribute("href"))')
                check(links==['journal_transaction.php?journal_id='+str(case['target'])],'Only the original has an exact journal link before posting '+label)
                expected_tables=2 if case['mode']=='reverse_only' else 3
                check(page.locator('#aw-review-content table tfoot').count()==expected_tables,'Every original/reversal/replacement preview has complete totals '+label)
                if case is cases['ordinary'][0]:capture('c-reversal-review')
                if case is cases['advance'][-1]:capture('c-return-correction-review')
                posts=[];refreshes=[];lost=[False];detail_failure=[case is cases['ordinary'][0]]
                def lose(route):
                    request=route.request
                    if request.method=='POST' and request.post_data_json.get('action')=='post':
                        posts.append(request.post_data_json);response=route.fetch();assert response.json().get('ok'),response.text()
                        if not lost[0]:
                            lost[0]=True
                            if label=='GJ-reverse_replace':
                                body=response.json();body['result'].pop('reversal_journal_id');route.fulfill(response=response,json=body)
                            else:route.abort()
                        else:route.fulfill(response=response)
                    elif request.method=='GET' and 'action=draft' in request.url and detail_failure[0]:route.fulfill(status=503,json={'ok':False,'error':'Synthetic detail-read failure'})
                    else:route.continue_()
                def session_read(route):refreshes.append(route.request.url);route.continue_()
                page.route('**/journal_correction_actions.php*',lose);page.route('**/accounting_actions.php?action=session',session_read)
                page.locator('#aw-post').click();page.wait_for_selector('#aw-unknown:not([hidden])')
                check(page.locator('.aw-columns').is_hidden() and page.locator('#aw-posted').is_hidden(),'Unknown result freezes editor and withholds success '+label)
                if case is cases['ordinary'][0]:
                    old_url=page.url;page.locator('.aw-header a[href="accounting_drafts.php"]').click();check(page.url==old_url and page.locator('#aw-unknown').is_visible(),'Uncertain correction blocks navigation that would lose its frozen request')
                committed=state();page.locator('#aw-check-result').click();page.wait_for_selector('#aw-posted:not([hidden])')
                page.wait_for_selector('#aw-correction-details-error:not([hidden])' if detail_failure[0] else '[data-correction-result]')
                check(len(posts)==2 and refreshes and {k:v for k,v in posts[0].items() if k!='csrf_token'}=={k:v for k,v in posts[1].items() if k!='csrf_token'},'CSRF refresh replays exact frozen request '+label)
                check(state()==committed,'Committed replay adds no journal/correction/advance operation/audit or any other table write '+label)
                check(page.locator('[data-correction-link=reversal_journal_id]').count()==1 and page.locator('[data-correction-link=target_journal_id]').get_attribute('href').endswith('='+str(case['target'])),'Recorded bundle exposes actual exact identities '+label)
                if detail_failure[0]:
                    check(page.locator('#aw-correction-details-error').is_visible() and page.locator('#aw-unknown').is_hidden(),'Failed secondary detail read retains recorded state')
                    detail_failure[0]=False;page.get_by_role('button',name='Retry correction details').click();page.wait_for_selector('#aw-correction-details-error[hidden]',state='attached');check(len(posts)==2 and state()==committed,'Details retry is read-only and does not repeat financial posting')
                page.unroute('**/journal_correction_actions.php*',lose);page.unroute('**/accounting_actions.php?action=session',session_read)
                check(page.get_by_role('link',name='Find another transaction to correct').get_attribute('href')=='financial_records.php' and page.locator('#aw-other-correction-mode').is_hidden(),'Recorded result returns to transaction selection rather than reusing corrected target '+label)
                if case is cases['advance'][1]:capture('c-release-correction-recorded')
                page.reload(wait_until='networkidle');page.wait_for_selector('[data-correction-result]')
                check(page.locator('[data-correction-link=target_journal_id]').get_attribute('href').endswith('='+str(case['target'])) and state()==committed,'Saved posted draft reload recovers the same bundle without writes '+label)
        if args.gate in ['d','all']:
            import struct,zlib
            gate='d'
            def png():
                def chunk(k,d):return struct.pack('>I',len(d))+k+d+struct.pack('>I',zlib.crc32(k+d)&0xffffffff)
                pixels=b''.join(b'\0'+os.urandom(64*4) for _ in range(64))
                return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',64,64,8,6,0,0,0))+chunk(b'IDAT',zlib.compress(pixels))+chunk(b'IEND',b'')
            journals=sql('SELECT COUNT(*) n FROM journal_entries')[0]['n']
            page.goto(base+'/cash_disbursement.php',wait_until='networkidle');page.wait_for_function("document.querySelector('#aw-save').disabled===false")
            page.locator('[data-line] [data-field=account_id]').first.select_option(str(fixture['training']),force=True)
            page.locator('#aw-document-group > summary').click()
            page.locator('#aw-cash-amount').fill('100.00');page.locator('#aw-upload').set_input_files({'name':'Synthetic U4 allocation support.png','mimeType':'image/png','buffer':png()})
            page.wait_for_selector('[data-document]');doc=page.locator('[data-document]').first;doc.locator('[data-doc-field=purpose]').select_option('amount')
            for name,value in [('declared_amount','120.00'),('accepted_amount','100.00')]:doc.locator('[data-doc-field='+name+']').fill(value)
            doc.locator('[data-doc-field=exclusion_reason]').fill('Synthetic excluded personal amount');doc.locator('[data-allocation]').first.fill('100.00');doc.locator('[data-doc-field=reviewed]').check()
            check(all(text in doc.locator('[data-support-summary]').inner_text() for text in ['Reviewed for this draft','120.00','100.00','20.00']),'Declared/support/allocated/excluded amounts remain separate')
            doc.locator('[data-doc-field=declared_amount]').fill('130.00');doc.locator('[data-doc-field=exclusion_reason]').click()
            check(not doc.locator('[data-doc-field=reviewed]').is_checked() and 'Needs manual review' in doc.locator('[data-support-summary]').inner_text(),'Changing evidence invalidates review rather than claiming verification')
            capture('d-support-guidance')
            page.goto(base+'/ocr_expense.php',wait_until='networkidle');page.locator('#receipt-image').set_input_files({'name':'Synthetic U4 receipt.png','mimeType':'image/png','buffer':png()});page.locator('#receipt-upload button').click()
            page.wait_for_function("document.querySelector('#receipt-retry')||document.querySelector('#ocr-progress a')")
            if page.locator('#ocr-progress a').count():page.locator('#ocr-progress a').click()
            page.wait_for_selector('#receipt-retry')
            rid=page.locator('#receipt-retry input[name=receipt_id]').input_value();key=page.locator('#receipt-retry input[name=request_key]').input_value()
            check(page.get_by_role('link',name='Review in General Journal').get_attribute('href')=='general_journal.php?receipt_id='+rid,'OCR failure retains exact General Journal-only manual handoff')
            config(s1=False);manual=admin.request.get(base+'/general_journal.php?receipt_id='+rid);check(manual.ok and 'General Journal' in manual.text(),'Scalar manual handoff remains available with Stage 1 off');config()
            check(manager.request.get(base+'/ocr_expense.php?receipt='+rid).status==403 and manager.request.get(base+'/receipt_attachment.php?receipt_id='+rid).status in [403,404],'Management cannot read OCR workspace or private image')
            requests=[];lost=[False]
            def lost_retry(route):
                if route.request.method=='POST':
                    requests.append(route.request.post_data)
                    if not lost[0]:lost[0]=True;response=route.fetch();assert response.ok;route.abort()
                    else:route.continue_()
                else:route.continue_()
            page.route('**/ocr_expense.php',lost_retry);page.locator('#receipt-retry button').click();page.wait_for_selector('#ocr-mutation-feedback:not([hidden])')
            check(page.locator('#receipt-retry button').is_disabled() and page.locator('#receipt-discard button').is_disabled(),'Lost retry response blocks blind second mutation')
            check('Reading the saved image' not in page.locator('#ocr-retry-progress').inner_text(),'Unverified result clears stale reading progress')
            committed=state();page.locator('#ocr-check-state').click();page.wait_for_selector('#ocr-replay-retry:not([hidden])')
            check(state()==committed and len(requests)==1,'Saved-state check is read-only and does not retry extraction')
            capture('d-ocr-unverified');page.locator('#ocr-replay-retry').click();page.wait_for_selector('#receipt-retry');page.wait_for_function("document.querySelector('#receipt-retry button').disabled===false")
            check(len(requests)==2 and all(key in body for body in requests) and state()==committed,'Same-key extraction replay creates no attempt/audit/other table write')
            page.unroute('**/ocr_expense.php',lost_retry)
            lost=[False];requests=[]
            def lost_discard(route):
                if route.request.method=='POST':requests.append(route.request.post_data);response=route.fetch();assert response.ok;route.abort()
                else:route.continue_()
            page.route('**/ocr_expense.php',lost_discard);page.locator('#receipt-discard button').click();page.wait_for_selector('#ocr-mutation-feedback:not([hidden])')
            discarded=state();page.locator('#ocr-check-state').click();page.wait_for_function("document.querySelector('#ocr-mutation-status').textContent.includes('no longer available')")
            check(state()==discarded and len(requests)==1 and page.locator('#ocr-replay-retry').is_hidden(),'Lost discard is checked without a second destructive action')
            check(page.locator('#receipt-discard button').is_disabled() and 'does not establish why' in page.locator('#ocr-mutation-status').inner_text(),'Unavailable receipt is not falsely labelled confirmed discard')
            page.unroute('**/ocr_expense.php',lost_discard)
            check(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n']==journals,'Evidence and OCR mutations never post a financial journal')
        check(not errors,'No uncaught JavaScript errors')
        browser.close();success=True
finally:
    server.terminate();server.wait(timeout=15);log.close()
    (run/'results.json').write_text(json.dumps({'passed':success,'gate':args.gate,'checks':checks,'errors':errors},indent=2),encoding='utf-8')
    print('U4_RUN: '+str(run),flush=True)
