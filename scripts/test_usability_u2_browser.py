"""U2-A visible payment and exact-reader checks on a complete-schema disposable fixture.
Copies code into a private app; never copies production config, sessions or receipts.
"""
from urllib.parse import urlencode
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
php=r'C:\xampp\php\php.exe';evidence=Path(fixture['receipt_root'])
run=root/'.migration-private'/('u2a-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(stage1=True,stage2=True,stage3=True):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',"+str(stage1).lower()+");define('STAGE2_ADVANCES_ENABLED',"+str(stage2).lower()+");define('STAGE3_CORRECTIONS_ENABLED',"+str(stage3).lower()+");define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
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
def state():
    tables=['journal_entries','journal_entry_lines','journal_drafts','Receipts','posted_evidence_associations','evidence_allocations','journal_corrections','journal_correction_lines','cash_advances','cash_advance_operations','cash_advance_operation_reversals','audit_logs','accounting_write_state','draft_evidence_reservations','correction_evidence_reservations']
    return hashlib.sha256(json.dumps({n:sql('SELECT * FROM '+n+' ORDER BY 1') for n in tables},sort_keys=True).encode()).hexdigest()
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as playwright:
        browser=playwright.chromium.launch(channel=args.browser,headless=True)
        def context(email='admin@example.invalid'):
            c=browser.new_context(viewport={'width':1366,'height':768});c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());ss=login(email);c.add_cookies([{'name':'PHPSESSID','value':ss['session'],'url':base}]);return c,ss
        ctx,session=context();page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def ready():page.wait_for_function("document.getElementById('aw-existing') && (!document.getElementById('aw-existing').disabled || !document.getElementById('aw-posted').hidden)")
        def open_payment(draft=None):
            page.goto(base+'/cash_disbursement.php'+('?draft_id='+str(draft) if draft else ''));ready()
        def api(action,values={},method='POST'):
            res=ctx.request.get(base+'/accounting_actions.php?'+urlencode({'action':action,**values})) if method=='GET' else ctx.request.post(base+'/accounting_actions.php',data={'action':action,'csrf_token':session['csrf'],**values})
            check(res.ok,'Ordinary '+action+' succeeds: '+(res.text()[:120] if not res.ok else ''));return res.json()['result']
        def payload(cash='1000',amount='1000',credit='',project=''):
            return {'entry_date':today,'reference':'','description':'Synthetic U2 payment','party_id':str(fixture['party']),'default_project_id':'','cash_account_id':str(fixture['bank']),'cash_amount':cash,'cash_project_id':'','transaction_kind':'ordinary','documents':[], 'lines':[{'client_id':secrets.token_hex(12),'account_id':str(fixture['training']),'fund_project_id':project,'debit_amount':amount,'credit_amount':credit}]}
        def saved(pv):return api('save',{'source_book':'CDB','draft_id':'','submission_key':secrets.token_hex(32),'payload':pv})
        def save_visible():
            page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved')")
            return re.search(r'draft_id=(\d+)',page.url).group(1)
        def capture(name):
            for w,h in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':w,'height':h});page.evaluate('window.scrollTo(0,0)');check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),name+' fits '+str(w));page.screenshot(path=str(run/(name+'-'+str(w)+'.png')),full_page=True)
            page.set_viewport_size({'width':1366,'height':768})
        def fill():
            choose(page.locator('#aw-party'),str(fixture['party']));choose(page.locator('#aw-cash-account'),str(fixture['bank']));choose(page.locator('[data-field="account_id"]').first,str(fixture['training']));page.locator('#aw-cash-amount').fill('1000');page.locator('#aw-purpose').fill('Synthetic U2 supplies')
        def review():
            page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])')
        open_payment();today=page.locator('#aw-date').input_value()
        check(page.locator('#entry-workspace').get_attribute('data-payment-mode')=='quick','Blank payment starts faithfully in Quick')
        capture('payment-empty');page.locator('#aw-review').click();page.wait_for_selector('#aw-error a');check('draft_id=' in page.url,'Review saves incomplete draft without posting')
        capture('payment-invalid');links=page.locator('#aw-error a').count();page.locator('#aw-purpose').fill('Synthetic blank validation');check(page.locator('#aw-error a').count()==links-1,'Correcting purpose preserves unrelated errors')
        page.locator('#aw-error a').first.click();check(page.evaluate("document.activeElement.id==='aw-party-search'"),'Error summary focuses visible payee combobox')
        page.locator('#aw-party-search').fill('unmatched synthetic query');check(page.locator('#aw-party').input_value()=='' and page.locator('#aw-party-search').get_attribute('aria-invalid')=='true','Search query is not selected payee or error recovery');page.keyboard.press('Escape')
        fill();check(page.locator('[data-field="debit_amount"]').first.input_value()=='1000','One deliberate amount edit updates represented debit')
        capture('payment-filled');check(page.locator('#aw-review').bounding_box()['y']+page.locator('#aw-review').bounding_box()['height']<=768,'Simple Review action fits laptop first viewport')
        page.locator('.app-sidebar a[href="cash_disbursement.php"]').click();check(page.locator('#aw-cash-amount').input_value()=='1000','Current task leaves edits intact')
        page.locator('a[href="accounting_drafts.php"]').first.click();check(page.locator('#aw-leave-dialog').is_visible(),'Dirty navigation asks about unsaved edits');page.locator('#aw-leave-stay').click()
        d=save_visible();before=api('draft',{'draft_id':d},'GET')['payload'];page.locator('[data-payment-mode="advanced"]').click();page.locator('[data-payment-mode="quick"]').click();page.locator('#aw-save').click();ready();after=api('draft',{'draft_id':d},'GET')['payload'];check(before==after,'Mode round trip preserves financial values and stable IDs')
        page.locator('#aw-party-search').focus();page.locator('#aw-party-search').fill('cancel query');page.keyboard.press('Escape');check('saved' in page.locator('#aw-draft-label').inner_text(),'Selector cancellation does not dirty saved draft')
        review();check(not page.locator('#aw-entry').is_visible() and 'not been recorded' in page.locator('#aw-status').inner_text(),'Review is focused and truthfully unrecorded');capture('payment-review')
        check(page.locator('#aw-post').bounding_box()['y']+page.locator('#aw-post').bounding_box()['height']<=768,'Simple Confirm action fits laptop first viewport')
        page.locator('#aw-back').click();check(page.locator('#aw-cash-amount').input_value()=='1000','Back to editing preserves amounts');review()
        page.locator('#aw-post').click();page.wait_for_selector('#aw-posted:not([hidden])');check(page.locator('h1').inner_text()=='Payment recorded' and not page.locator('#aw-entry').is_visible(),'Recorded state replaces editor and stale title');check(page.locator('.aw-columns input,.aw-columns select,.aw-columns textarea,.aw-columns button').evaluate_all('(nodes)=>nodes.every(el=>el.disabled)'),'Recorded controls, including optional documents, remain read only');capture('payment-recorded')
        journal=re.search(r'journal_id=(\d+)',page.locator('#aw-exact-link').get_attribute('href')).group(1);before_state=state();res=ctx.request.get(base+'/journal_transaction.php?journal_id='+journal);check(res.ok and '1000.00' in res.text() and state()==before_state,'Exact read returns complete entry and performs no data writes')
        page.locator('#aw-exact-link').click();check(page.locator('h1').inner_text()=='Transaction #'+journal,'Recorded link opens exact journal');capture('payment-exact')
        open_payment(d);check(page.locator('#aw-exact-link').get_attribute('href').endswith('journal_id='+journal),'Reopened posted draft links to original exact identity')
        # Quick master creation is deliberate; actual line tags and visible focus stay connected.
        open_payment();fill();page.locator('[data-new-master="projects"]').click();page.locator('#aw-master-form [name="name"]').fill('Synthetic U2 training project');page.locator('#aw-master-form [name="code"]').fill('U2-'+secrets.token_hex(4));page.locator('#aw-master-form button.aw-primary').click();page.wait_for_selector('#aw-master-dialog:not([open])',state='attached');ready()
        check(page.evaluate("document.activeElement.id==='aw-cash-project-search'") and page.locator('#aw-cash-project').input_value()==page.locator('[data-field="fund_project_id"]').input_value()!='','Quick project creation assigns actual common tags and returns visible focus')
        page.locator('[data-new-master="parties"]').click();long_name='Synthetic U2 supplier with a long descriptive organization name for wrapped record labels';page.locator('#aw-master-form [name="name"]').fill(long_name);page.locator('#aw-master-form button.aw-primary').click();page.wait_for_selector('#aw-master-dialog:not([open])',state='attached');ready();check(long_name in page.locator('#aw-party').locator('option:checked').inner_text(),'Inline payee creation retains complete long label');capture('payment-long-label')
        # Lowering costs never trims attached evidence to make review succeed.
        page.locator('#aw-document-group summary').click();page.locator('#aw-upload').set_input_files({'name':'Synthetic U2 proof.png','mimeType':'image/png','buffer':png()});page.wait_for_selector('[data-document]');ready();doc=page.locator('[data-document]');doc.locator('[data-doc-field="purpose"]').select_option('amount');doc.locator('[data-doc-field="declared_amount"]').fill('800');doc.locator('[data-doc-field="accepted_amount"]').fill('800');doc.locator('[data-allocation]').fill('800');doc.locator('[data-doc-field="reviewed"]').check();supported_id=save_visible();supported=api('draft',{'draft_id':supported_id},'GET')['payload'];page.locator('[data-payment-mode="advanced"]').click();page.locator('[data-payment-mode="quick"]').click();check(api('draft',{'draft_id':supported_id},'GET')['payload']==supported,'Evidence review and stable allocation identity survive presentation changes')
        page.locator('#aw-cash-amount').fill('600');page.locator('#aw-review').click();page.wait_for_function("document.getElementById('aw-error').textContent.length>0 && !document.getElementById('aw-save').disabled");check(not page.locator('#aw-review-panel').is_visible() and doc.locator('[data-doc-field="accepted_amount"]').input_value()=='800' and doc.locator('[data-allocation]').input_value()=='800','Excess support rejects review without trimming amounts or allocations')
        # An asset purchase and a liability settlement retain their distinct account meanings.
        for kind in ['Asset','Liability']:
            account=sql("SELECT CategoryID FROM Categories WHERE Account_Type='"+kind+"' AND Is_Cash_Account=0 AND Is_Active=1 AND CategoryID NOT IN (SELECT account_id FROM advance_control_designations) LIMIT 1")[0]['CategoryID'];pv=payload();pv['lines'][0]['account_id']=str(account);loaded=saved(pv);open_payment(loaded['id']);review();check(str(account)==api('draft',{'draft_id':str(loaded['id'])},'GET')['payload']['lines'][0]['account_id'],'Quick '+kind+' counterpart remains its selected account')
        # Loaded invalid/complex drafts cannot be silently repaired or collapsed.
        for cash,amount,credit,expected in [('1000','900','','split'),('1000','','','split'),('1000','1000','-1','advanced'),('1000','1000','bad','advanced')]:
            loaded=saved(payload(cash,amount,credit));open_payment(loaded['id']);check(page.locator('#entry-workspace').get_attribute('data-payment-mode')==expected,'Loaded '+amount+'/'+credit+' preserves required detailed mode');save_visible();stored=api('draft',{'draft_id':str(loaded['id'])},'GET');check(stored['payload']==loaded['payload'],'Loaded malformed/mismatched payload not automatically rewritten')
        split=payload('1000','600');split['lines'].append({**split['lines'][0],'client_id':'split-second','debit_amount':'400'});loaded=saved(split);open_payment(loaded['id']);review();check(page.locator('#aw-review-content table tr').count()==4,'Split review retains complete cash and both allocations');capture('payment-split')
        pv=payload('4000','4500');pv['lines'].append({'client_id':'tax','account_id':str(fixture['tax']),'fund_project_id':'','debit_amount':'','credit_amount':'500'});loaded=saved(pv);open_payment(loaded['id']);review();check(all(v in page.locator('.aw-payment-summary').inner_text() for v in ['4,500','500','4,000']),'Withholding summary shows authoritative gross, liability and cash');check('4500.00' in page.locator('.aw-coverage').inner_text(),'Evidence denominator remains gross expenditure');capture('payment-withholding')
        # Lose the response after a real disposable commit, then reauthenticate and replay identical identity.
        open_payment();fill();review();post_requests=[];lost=[False]
        def interrupt(route):
            body=route.request.post_data_json if route.request.method=='POST' else {}
            if body.get('action')=='post':
                post_requests.append(body)
                if not lost[0]:lost[0]=True;route.fetch();route.abort('failed');return
            route.continue_()
        page.route('**/accounting_actions.php',interrupt);page.locator('#aw-post').click();page.wait_for_selector('#aw-unknown:not([hidden])');check(page.locator('#aw-save').is_disabled(),'Unknown result prevents editing/new request');capture('payment-unknown')
        fresh=login('admin@example.invalid');ctx.add_cookies([{'name':'PHPSESSID','value':fresh['session'],'url':base}]);session=fresh
        page.locator('#aw-check-result').click();page.wait_for_selector('#aw-posted:not([hidden])');check(len(post_requests)==2 and all(post_requests[0][k]==post_requests[1][k] for k in ['draft_id','revision','submission_key','payload','review_token']),'Lost-response recovery preserves frozen identity through reauthentication');check('No duplicate' in page.locator('#aw-status').inner_text(),'Recovered result is explicitly explained');capture('payment-recovered');page.unroute('**/accounting_actions.php',interrupt)
        # Saved drafts coexist; failed Save-and-leave preserves edited contents and destination.
        open_payment();fill();save_visible();page.locator('#aw-purpose').fill('Latest unsaved synthetic edits');page.locator('a[href="accounting_drafts.php"]').first.click()
        def fail_save(route):
            if route.request.method=='POST' and route.request.post_data_json.get('action')=='save':route.fulfill(status=503,json={'ok':False,'error':'Synthetic save unavailable'})
            else:route.continue_()
        page.route('**/accounting_actions.php',fail_save);page.locator('#aw-leave-save').click();page.wait_for_function("document.getElementById('aw-leave-error').textContent.length>0");check(page.locator('#aw-leave-dialog').is_visible() and page.locator('#aw-purpose').input_value()=='Latest unsaved synthetic edits','Failed Save-and-leave retains edits and intent');page.unroute('**/accounting_actions.php',fail_save);page.locator('#aw-leave-save').click();page.wait_for_url('**/accounting_drafts.php');check(ctx.request.get(base+'/accounting_actions.php?action=draft&draft_id='+d).json()['result']['state']=='Posted','Starting later drafts leaves earlier posted draft intact')
        # Exact reader privacy and access are independent of entry UI gates.
        manager,ms=context('management@example.invalid');sensitive=str(fixture['advance_journal']);before_state=state()
        for flags in [(True,True,True),(False,False,False)]:
            config(*flags);res=manager.request.get(base+'/journal_transaction.php?journal_id='+sensitive);check(res.ok and 'receipt_attachment.php' not in res.text() and 'File_SHA256' not in res.text(),'Management exact history redacts private evidence with flags '+str(flags));check(state()==before_state,'Exact Management reads leave financial/audit/draft state unchanged')
        config();
        for value,status in [('0',400),('-1',400),('4294967296',400),('abc',400),('999999999',404)]:check(ctx.request.get(base+'/journal_transaction.php?journal_id='+value).status==status,'Exact reader rejects '+value)
        check(ctx.request.get(base+'/journal_transaction.php?journal_id[]=1').status==400,'Exact reader rejects array IDs');check(ctx.request.post(base+'/journal_transaction.php?journal_id='+journal).status==405,'Exact reader is GET only')
        anonymous=browser.new_context();check(anonymous.request.get(base+'/journal_transaction.php?journal_id='+journal,max_redirects=0).status==302,'Unauthenticated exact read redirects to login');anonymous.close()
        page.goto(base+'/cash_receipt.php');check(page.locator('#entry-workspace').get_attribute('data-payment-mode') is None,'Receipt presentation remains unchanged');page.goto(base+'/journal_correction.php?journal_id='+fixture['ordinary_journal'].__str__());check(page.locator('#aw-correction-reason').count()==1,'Correction workspace retains its dedicated presentation')
        check(not errors,'Payment and retained shared routes have no uncaught JavaScript errors')
        (run/'results.json').write_text(json.dumps({'checks':checks,'browser':browser.version,'database':db,'screenshots':sorted(p.name for p in run.glob('*.png')),'page_errors':errors},indent=2))
        files={str(p.relative_to(root)):hashlib.sha256(p.read_bytes()).hexdigest() for folder in ['includes','assets','scripts'] for p in (root/folder).rglob('*') if p.is_file()};files.update({p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in root.glob('*.php') if p.name!='config.php'});(run/'source-manifest.json').write_text(json.dumps(files,sort_keys=True,indent=2))
        browser.close()
    print('U2-A browser/HTTP checks:',checks);print('Private browser artifacts:',run)
finally:
    server.terminate();server.wait(timeout=10);log.close()
