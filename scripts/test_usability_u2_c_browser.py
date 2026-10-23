"""U2-C actual navigation/role/gate/read integration on guarded disposable copies.
No working config, sessions, receipts or provider calls. Deliberate fixture writes
are separate from the fingerprinted read-only navigation and correction page reads.
"""
from html.parser import HTMLParser
from itertools import product
from pathlib import Path
from urllib.parse import urlencode
import argparse,hashlib,json,os,re,secrets,shutil,socket,subprocess,sys,time
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--browser',default='msedge');args=parser.parse_args()
fixture_path=Path(args.fixture).resolve();private=(root/'.migration-private').resolve()
if not fixture_path.is_relative_to(private):parser.error('Private fixture required')
fixture=json.loads(fixture_path.read_text());db=fixture['database'];evidence=Path(fixture['receipt_root']).resolve()
if not re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',db) or not evidence.is_relative_to(private):parser.error('Disposable database/private evidence required')
php=r'C:\xampp\php\php.exe';run=private/('u2c-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['config.php','db_connect.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
def connect(name=db):
    assert re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',name)
    dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+name+';charset=utf8mb4'
    (app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(s1=True,s2=True,s3=True,ocr=True):
    flags=dict(STAGE1_WORKSPACE_ENABLED=s1,STAGE2_ADVANCES_ENABLED=s2,STAGE3_CORRECTIONS_ENABLED=s3,OCR_JOURNAL_ENABLED=ocr)
    (app/'config.php').write_text('<?php '+''.join('define('+ps(k)+','+str(v).lower()+');' for k,v in flags.items())+'define(\'RECEIPT_UPLOAD_DIR\','+ps(evidence)+');',encoding='utf-8')
connect();config()
def sql(query,write=False,name=db):
    assert re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',name)
    code='<?php require '+ps(root/'scripts/cli_common.php')+';$p=cli_db('+ps(name)+');echo json_encode('+('$p->exec('+ps(query)+')' if write else '$p->query('+ps(query)+')->fetchAll()')+');'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
def state():
    tables=[r['TABLE_NAME'] for r in sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")]
    return hashlib.sha256(json.dumps({n:sql('SELECT * FROM `'+n+'` ORDER BY 1') for n in tables},sort_keys=True).encode()).hexdigest()
# Test-only shell exercises the real nav and gate helpers, with normal DB-backed login.
(app/'_nav_probe.php').write_text("<?php session_start();require __DIR__.'/config.php';require __DIR__.'/db_connect.php';require __DIR__.'/includes/require_role.php';require_login();require __DIR__.'/includes/layout.php';layout_begin('Synthetic navigation gate check','financial_records');echo '<p>Synthetic isolated gate check</p>';layout_end();",encoding='utf-8')
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads|scripts|includes|vendor)/~',$path)||preg_match('~^/(forecast_ai|ocr_extract|gemini|anomaly_scan|audit_summary|external_email_actions|send_email)~',$path)){http_response_code(403);exit('Blocked isolated external/action boundary');}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0;errors=[];partials=[]
def check(ok,label):
    global checks
    if not ok:raise AssertionError(label)
    checks+=1;print('PASS: '+label,flush=True)
class Nav(HTMLParser):
    def __init__(self):super().__init__();self.on=False;self.heading=None;self.link=None;self.group='';self.groups=[];self.links=[]
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='nav' and a.get('aria-label')=='Main navigation':self.on=True
        elif self.on and tag=='h2':self.heading=''
        elif self.on and tag=='a':self.link={'key':a['data-nav-key'],'href':a['href'],'current':a.get('aria-current'),'group':self.group,'label':''}
    def handle_data(self,text):
        if self.on and self.heading is not None:self.heading+=text
        if self.on and self.link is not None:self.link['label']+=text
    def handle_endtag(self,tag):
        if self.on and tag=='h2':self.group=self.heading.strip();self.groups.append(self.group);self.heading=None
        elif self.on and tag=='a':self.link['label']=' '.join(self.link['label'].split());self.links.append(self.link);self.link=None
        elif tag=='nav':self.on=False
def nav(html):n=Nav();n.feed(html);return n
def data(html):return json.loads(re.search(r'<script[^>]*id="records-data"[^>]*>(.*?)</script>',html,re.S).group(1))
def expected(role,s1=True,s2=True,ocr=True):
    keys=['dashboard'];groups=[]
    if role=='Admin':
        groups.append('Transactions');keys+=['payment','receipt'] if s1 else [];keys+=['general_journal'];keys+=['ocr_expense'] if ocr else [];keys+=['accounting_drafts'] if s1 else []
    if s1 and s2:groups.append('Cash advances');keys+=['cash_advances']
    groups+=['Books & reports','Communications'];keys+=['financial_records','crb','cdb','general_ledger','budgets','financial_statements','report_bundles','reports','external_email']
    keys+=['board_messages'] if role=='Admin' else ['management_reviews','board_inbox']
    if role=='Admin':groups.append('Administration');keys+=['accounting_setup'] if s1 else [];keys+=['report_setup','admin_accounts','admin_users','audit_trail']
    return keys,groups
urls={'dashboard':'dashboard.php','payment':'cash_disbursement.php','receipt':'cash_receipt.php','general_journal':'general_journal.php','ocr_expense':'ocr_expense.php','accounting_drafts':'accounting_drafts.php','cash_advances':'cash_advances.php','financial_records':'financial_records.php','crb':'financial_records.php?view=crb','cdb':'financial_records.php?view=cdb','reports':'reports.php','external_email':'external_email.php','board_messages':'board_messages.php','management_reviews':'management_reviews.php','board_inbox':'board_inbox.php','accounting_setup':'accounting_setup.php','admin_accounts':'admin_accounts.php','admin_users':'admin_users.php','audit_trail':'audit_trail.php'}
urls.update({key:key+'.php' for key in ['general_ledger','budgets','financial_statements','report_bundles','report_setup']})
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email):
            c=browser.new_context(viewport={'width':1366,'height':768});c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda r:r.abort());ss=login(email);c.add_cookies([{'name':'PHPSESSID','value':ss['session'],'url':base}]);return c,ss
        admin,session=context('admin@example.invalid');manager,ms=context('management@example.invalid');page=admin.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        def current(key):
            links=page.locator('.app-sidebar a[aria-current="page"]');check(links.count()==1 and links.get_attribute('data-nav-key')==key,'Actual page current location '+key)
        def capture(label):
            for width,height in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),label+' fits '+str(width));page.screenshot(path=str(run/(label+'-'+str(width)+'.png')),full_page=True)
            page.set_viewport_size({'width':1366,'height':768})
        # Fingerprinted read-only navigation, including all 32 complete-schema role/flag cases.
        before=state()
        for s1,s2,s3,ocr in product([False,True],repeat=4):
            config(s1,s2,s3,ocr)
            for role,ctx in [('Admin',admin),('Management',manager)]:
                response=ctx.request.get(base+'/_nav_probe.php');check(response.ok,'Navigation probe permitted '+role)
                n=nav(response.text());keys,groups=expected(role,s1,s2,ocr)
                check([l['key'] for l in n.links]==keys,role+' exact task visibility '+str((s1,s2,s3,ocr)))
                check(n.groups==groups and len(set(keys))==len(keys),role+' ordered nonempty groups without duplicate links')
                check(all(l['href']==urls[l['key']] for l in n.links),role+' preserves exact existing URLs/date defaults')
                check([l['key'] for l in n.links if l['current']]=='financial_records'.split(),role+' unique accessible read location')
        config()
        for role,ctx in [('Admin',admin),('Management',manager)]:
            for key in expected(role)[0]:
                response=ctx.request.get(base+'/'+urls[key]);check(response.status==503 if key in ['budgets','financial_statements','report_bundles','report_setup'] else response.ok,role+' destination opens or explicitly requires its unapplied additive schema '+key)
                check([l['key'] for l in nav(response.text()).links if l['current']]==[key],role+' real route has correct current key '+key)
        for path in ['cash_receipt.php','cash_disbursement.php','general_journal.php','accounting_drafts.php','accounting_setup.php','admin_accounts.php','admin_users.php','audit_trail.php','ocr_expense.php','board_messages.php','journal_correction.php?journal_id='+str(fixture['ordinary_journal'])]:
            check(manager.request.get(base+'/'+path,max_redirects=0).status==403,'Management direct authoring/admin access denied '+path.split('?')[0])
        check(state()==before,'All-role navigation/flag/default-route pass leaves every application table unchanged')
        page.goto(base+'/cash_receipt.php');page.wait_for_selector('#aw-party-search');current('receipt');capture('admin-receipt-task')
        check(page.locator('.app-sidebar nav > a').count()==page.locator('.app-sidebar nav a').count(),'Links remain direct nav children')
        first=page.locator('.app-sidebar nav > a').first;first.focus();page.keyboard.press('Tab');check(page.evaluate("getComputedStyle(document.activeElement).outlineWidth==='2px'"),'Keyboard navigation has visible focus outline')
        for _ in range(page.locator('.app-sidebar nav > a').count()-2):page.keyboard.press('Tab')
        check(page.evaluate("document.activeElement.dataset.navKey==='audit_trail' && document.querySelector('.app-sidebar nav').scrollTop>0"),'Keyboard reaches last task within scrollable sidebar');capture('admin-sidebar-keyboard-bottom')
        page.goto(base+'/accounting_drafts.php');current('accounting_drafts');capture('admin-drafts-current')
        page.goto(base+'/financial_records.php?view=crb');current('crb');capture('admin-receipts-book')
        page.goto(base+'/general_journal.php');current('general_journal')
        page.goto(base+'/cash_advance_entry.php?workflow_kind=advance_release');current('cash_advances')
        page.goto(base+'/journal_transaction.php?journal_id='+str(fixture['ordinary_journal']));current('financial_records')
        page.goto(base+'/journal_corrections.php?journal_id='+str(fixture['ordinary_journal']));current('financial_records')
        mpage=manager.new_page();mpage.on('pageerror',lambda e:errors.append(str(e)));mpage.goto(base+'/financial_records.php?view=cdb');mpage.wait_for_selector('.app-sidebar');page,mpage=mpage,page;current('cdb');capture('management-payments-book');page.emulate_media(media='print');check(page.locator('.app-sidebar').is_hidden(),'Print hides navigation');capture('management-book-print');page.emulate_media(media='screen');page,mpage=mpage,page
        # The grouped sidebar must still use the existing protected ordinary navigation.
        page.goto(base+'/cash_receipt.php');page.wait_for_selector('#aw-purpose');page.locator('#aw-purpose').fill('Synthetic U2-C unsaved sidebar edits');page.locator('[data-nav-key="receipt"]').click();check(page.locator('#aw-purpose').input_value()=='Synthetic U2-C unsaved sidebar edits' and not page.locator('#aw-leave-dialog').is_visible(),'Current receipt sidebar task preserves unsaved edits')
        page.locator('[data-nav-key="financial_records"]').click();check(page.locator('#aw-leave-dialog').is_visible(),'Grouped sidebar asks before leaving dirty receipt');capture('receipt-dirty-sidebar-leave');page.locator('#aw-leave-stay').click();check(page.locator('#aw-purpose').input_value()=='Synthetic U2-C unsaved sidebar edits','Stay from sidebar keeps latest edits')
        def save_failure(route):
            if route.request.method=='POST' and route.request.post_data_json.get('action')=='save':route.fulfill(status=503,json={'ok':False,'error':'Synthetic sidebar save unavailable'})
            else:route.continue_()
        page.locator('[data-nav-key="financial_records"]').click();page.route('**/accounting_actions.php',save_failure);page.locator('#aw-leave-save').click();page.wait_for_function("document.getElementById('aw-leave-error').textContent.length>0");check(page.locator('#aw-leave-dialog').is_visible() and page.locator('#aw-purpose').input_value()=='Synthetic U2-C unsaved sidebar edits','Failed sidebar Save-and-leave retains edit/destination');page.unroute('**/accounting_actions.php',save_failure);page.locator('#aw-leave-save').click();page.wait_for_url('**/financial_records.php');check(sql("SELECT COUNT(*) n FROM journal_drafts WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.description'))='Synthetic U2-C unsaved sidebar edits'")[0]['n']==1,'Sidebar Save-and-leave waits for one acknowledged private draft')
        config(False,False,False,True);page.goto(base+'/general_journal.php');check(page.locator('#journal-form').count()==1 and page.locator('[data-nav-key="ocr_expense"]').count()==1,'Stage 1 off retains scalar GJ and independent OCR');current('general_journal');capture('admin-flags-off-scalar');check(admin.request.get(base+'/cash_receipt.php').status==503,'Stage 1 off rejects receipt entry')
        config(True,False,True,False);check(admin.request.get(base+'/journal_correction.php?journal_id='+str(fixture['ordinary_journal'])).ok,'Ordinary correction remains available with Stage 2 off');check(admin.request.get(base+'/journal_correction.php?journal_id='+str(fixture['advance_journal'])).status==503,'Advance correction pauses with Stage 2 off');config()
        # Corrections are reads here; targets come from persisted journals/operations.
        targets={}
        for book in ['CRB','CDB','GJ']:
            rows=sql("SELECT j.id FROM journal_entries j WHERE j.source_book='"+book+"' AND j.status='posted' AND COALESCE(j.transaction_kind,'')<>'correction_reversal' AND NOT EXISTS(SELECT 1 FROM cash_advance_operations o WHERE o.journal_id=j.id) AND NOT EXISTS(SELECT 1 FROM journal_corrections c WHERE c.target_journal_id=j.id) ORDER BY j.id DESC LIMIT 1")
            assert rows,'Missing ordinary '+book;targets[book]=rows[0]['id']
        for kind in ['release','liquidation','return']:
            rows=sql("SELECT o.journal_id id FROM cash_advance_operations o WHERE o.operation_kind='"+kind+"' AND NOT EXISTS(SELECT 1 FROM journal_corrections c WHERE c.target_journal_id=o.journal_id)"+(" AND NOT EXISTS(SELECT 1 FROM cash_advance_operations s WHERE s.advance_id=o.advance_id AND s.operation_kind<>'release' AND NOT EXISTS(SELECT 1 FROM cash_advance_operation_reversals r WHERE r.original_operation_id=s.id))" if kind=='release' else '')+" ORDER BY o.id DESC LIMIT 1")
            assert rows,'Missing advance '+kind;targets[kind]=rows[0]['id']
        before=state()
        for kind,target in targets.items():
            key={'CRB':'crb','CDB':'cdb','GJ':'financial_records'}.get(kind,'cash_advances')
            for mode in ['reverse_only','reverse_replace']:
                page.goto(base+'/journal_correction.php?'+urlencode({'journal_id':target,'mode':mode}));page.wait_for_selector('#aw-correction-reason');current(key)
                info=json.loads(page.locator('#workspace-data').text_content());check(info['target']['eligible'] and bool(info['target']['operation'])==(kind in ['release','liquidation','return']),'Correction uses eligible persisted target '+kind+' '+mode)
                if mode=='reverse_only':check(info['book']=='GJ' and 'workflow_kind' not in info,'Reverse-only current state works without advance display config')
                if kind in ['CRB','release'] and mode=='reverse_only':capture(kind.lower()+'-reverse-only-current')
            if kind in ['CRB','CDB','GJ']:
                page.goto(base+'/journal_correction.php?'+urlencode({'journal_id':target,'book':'CDB' if kind=='GJ' else 'GJ'}));page.wait_for_selector('#aw-correction-reason');current(key)
        check(state()==before,'Correction page/mode/replacement-book navigation creates no financial/draft/evidence writes')
        # Saved correction resumption is a separate, deliberate disposable draft/audit case.
        for kind in ['CRB','CDB','GJ','release','liquidation','return']:
            for mode in ['reverse_only','reverse_replace']:
                page.goto(base+'/journal_correction.php?'+urlencode({'journal_id':targets[kind],'mode':mode}));page.wait_for_selector('#workspace-data',state='attached');info=json.loads(page.locator('#workspace-data').text_content());pv=info['initial_payload'];pv['reason']='Synthetic U2-C navigation resumption'
                response=admin.request.post(base+'/journal_correction_actions.php',data={'action':'save','csrf_token':session['csrf'],'draft_id':'','target_journal_id':str(targets[kind]),'mode':mode,'source_book':info['book'],'submission_key':secrets.token_hex(32),'payload':pv});check(response.ok,'Synthetic correction draft saved '+kind+' '+mode)
                saved=response.json()['result'];before=state();page.goto(base+'/journal_correction.php?draft_id='+str(saved['id']));page.wait_for_selector('#aw-correction-reason');current({'CRB':'crb','CDB':'cdb','GJ':'financial_records'}.get(kind,'cash_advances'));check(state()==before,'Reopened correction keeps provenance with no page-read writes')
        legacy=sql("SELECT j.id FROM journal_entries j WHERE j.status='posted' AND j.source_book IS NULL LIMIT 1")
        check(bool(legacy),'Legacy original provenance fixture available');page.goto(base+'/journal_correction.php?journal_id='+str(legacy[0]['id'])+'&mode=reverse_only');page.wait_for_selector('#aw-correction-reason');current('financial_records')
        # Separate deliberate fixture setup for current cash-book/history integration.
        marker='U2C'+secrets.token_hex(6).upper();seed=json.loads(subprocess.check_output([php,str(root/'scripts/test_usability_u2_c_records_fixture.php'),'--fixture='+str(fixture_path),'--marker='+marker],text=True));(run/'records-fixture.json').write_text(json.dumps(seed))
        before=state()
        for book,account in [('CRB',fixture['income']),('CDB',fixture['training'])]:
            r=admin.request.get(base+'/financial_records.php?'+urlencode({'view':book.lower(),'from':'','to':'','account_id':account}));d=data(r.text());rows=[row for row in d['rows'] if row['description'].startswith(marker)]
            check(r.ok and d['completeBook'] and {int(row['journal_id']) for row in rows}==set(seed['journals'][book]) and len(rows)==27,'Account-filtered '+book+' retains every complete originating journal')
            check(sum(int(row['debit_cents']) for row in rows)==sum(int(row['credit_cents']) for row in rows)==13000,book+' full-entry amounts balance across all matching rows')
            page.goto(base+'/financial_records.php?'+urlencode({'view':book.lower(),'from':'','to':''}));page.wait_for_selector('.dt-search input');page.locator('.dt-search input').fill(marker);page.wait_for_function("document.getElementById('records-total-debit').textContent==='₱130.00'")
            check(page.locator('#records-total-credit').inner_text()=='₱130.00' and page.locator('#records-page').inner_text()=='Page 1 of 3',book+' search totals include all pages and complete entries')
            page.get_by_role('button',name='Next',exact=True).click();check(page.locator('#records-total-debit').inner_text()=='₱130.00' and page.locator('#records-page').inner_text()=='Page 2 of 3',book+' pagination keeps all-matching totals');capture(book.lower()+'-whole-entry-search')
        response=admin.request.get(base+'/financial_records.php?'+urlencode({'from':'','to':'','account_id':fixture['training'],'search':marker,'format':'json'}));rows=response.json()['rows'];check(response.ok and rows and all(int(row['account_id'])==int(fixture['training']) for row in rows),'Journal History retains account-line filtering instead of expanding journals')
        chain=sql('SELECT target_journal_id,reversal_journal_id,replacement_journal_id FROM journal_corrections WHERE replacement_journal_id IS NOT NULL LIMIT 1')[0];all_rows=admin.request.get(base+'/financial_records.php?from=&to=&format=json').json()['rows'];ids={int(row['journal_id']) for row in all_rows};check(all(int(chain[key]) in ids for key in chain),'History retains corrected original, generated reversal and replacement')
        page.goto(base+'/financial_records.php?'+urlencode({'from':'','to':'','account_id':fixture['training'],'search':marker}));page.wait_for_selector('.dt-search input')
        def shown():return page.evaluate("[document.querySelector('#records-table tbody').innerText,document.getElementById('records-applied-summary').innerText,...['debit','credit','difference'].map(k=>document.getElementById('records-total-'+k).innerText)]")
        prior=shown();route_pattern=re.compile(r'.*/financial_records\.php\?.*format=json.*');page.route(route_pattern,lambda route:route.fulfill(status=503,json={'ok':False,'error':'Synthetic U2-C read failure'}));page.locator('#type').select_option('Income');page.locator('#records-filters').evaluate('(f)=>f.requestSubmit()');page.wait_for_function("document.getElementById('records-request-status').textContent.includes('last successful')");check(shown()==prior and 'not applied' in page.locator('#records-draft-status').inner_text(),'Failed history refresh preserves applied scope, rows and totals');page.unroute(route_pattern)
        old=admin.request.get(base+'/financial_records.php?from=&to=&format=json').json();page.evaluate("""(old)=>{window.__u2cFetch=window.fetch;window.__u2cLate=null;window.fetch=(url,options)=>{const u=new URL(url,location.href);if(u.pathname.endsWith('/financial_records.php')&&u.searchParams.get('format')==='json'&&u.searchParams.get('type')==='Income')return new Promise(resolve=>{window.__u2cLate=()=>resolve(new Response(JSON.stringify(old),{status:200,headers:{'Content-Type':'application/json'}}));});return window.__u2cFetch(url,options);};}""",old)
        page.locator('#records-filters').evaluate('(f)=>f.requestSubmit()');page.wait_for_function('window.__u2cLate!==null');page.locator('#type').select_option('Expense');page.locator('#records-filters').evaluate('(f)=>f.requestSubmit()');page.wait_for_function("document.getElementById('records-request-status').textContent==='Filters applied.'");latest=shown();page.evaluate('window.__u2cLate()');page.wait_for_timeout(150);check(shown()==latest and 'Expense' in page.locator('#records-applied-summary').inner_text(),'Late history response cannot overwrite newer applied results');page.evaluate('window.fetch=window.__u2cFetch');capture('history-applied-filters')
        check(state()==before,'Records search/filter/pagination/failure checks do not mutate financial, draft or evidence tables')
        # Private evidence remains protected with installed schema and all entry flags off.
        for flags in [(True,True,True),(True,False,True),(False,False,False)]:
            config(*flags,ocr=False);r=manager.request.get(base+'/journal_transaction.php?journal_id='+str(fixture['advance_journal']));check(r.ok and 'receipt_attachment.php' not in r.text(),'Management exact advance privacy survives flags '+str(flags))
        config()
        # Independent partial-schema copies, never the operational or working database.
        for label,table,hidden in [('stage1','accounting_write_state',['payment','receipt','accounting_drafts','accounting_setup','cash_advances']),('stage2','cash_advance_due_changes',['cash_advances']),('ocr','receipt_ocr_attempts',['ocr_expense'])]:
            name='atikha_test_stage1_u2cpartial'+secrets.token_hex(5);out=subprocess.check_output([sys.executable,str(root/'scripts/test_stage3_fixture.py'),'--fixture='+str(fixture_path),'--database='+name],text=True);path=Path(re.search(r'Cloned fixture: (.+)',out).group(1).strip());assert path.is_relative_to(private)
            sql('RENAME TABLE `'+table+'` TO `'+table+'_u2c_unavailable`',True,name);connect(name);r=admin.request.get(base+'/_nav_probe.php');check(r.ok and not any(l['key'] in hidden for l in nav(r.text()).links),'Missing '+label+' prerequisite hides dependent tasks');partials.append({'database':name,'fixture':str(path.relative_to(root)),'missing':table});connect()
        # Anonymous and inactive direct access; fixtures remain independently disposable.
        anon=browser.new_context();check(anon.request.get(base+'/cash_receipt.php',max_redirects=0).status==302,'Anonymous authoring redirects to login');anon.close()
        def forged_context(uid):
            code='<?php session_save_path('+ps(sessions)+');session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=["UserID"=>'+str(uid)+',"Role"=>"Unsupported","FullName"=>"Synthetic unsupported cache"];echo json_encode(["session"=>session_id()]);session_write_close();'
            token=json.loads(subprocess.check_output([php],input=code,text=True));c=browser.new_context();c.add_cookies([{'name':'PHPSESSID','value':token['session'],'url':base}]);return c
        unknown=forged_context(4294967294);check(unknown.request.get(base+'/general_journal.php',max_redirects=0).status==302,'Unsupported invented identity cannot enter through a cached session');unknown.close()
        manager_id=sql("SELECT UserID FROM Users WHERE Email='management@example.invalid'")[0]['UserID'];forged=forged_context(manager_id);check(forged.request.get(base+'/cash_receipt.php',max_redirects=0).status==403,'Unsupported cached role cannot override trusted Management identity');forged.close()
        inactive,ss=context('other@example.invalid');sql("UPDATE Users SET Is_Active=0 WHERE Email='other@example.invalid'",True);check(inactive.request.get(base+'/general_journal.php',max_redirects=0).status==302,'Inactive account cannot use a navigation destination');inactive.close()
        check(not errors,'Navigation browser pages have no uncaught JavaScript errors: '+str(errors))
        result={'checks':checks,'browser':browser.version,'database':db,'screenshots':sorted(p.name for p in run.glob('*.png')),'page_errors':errors,'partials':partials};(run/'results.json').write_text(json.dumps(result,indent=2));browser.close()
finally:
    server.terminate();server.wait(timeout=10);log.close()
print('U2-C browser/HTTP checks:',checks);print('Private browser artifacts:',run)
