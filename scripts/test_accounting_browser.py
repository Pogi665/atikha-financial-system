"""Phase 3 HTTP/Edge tests on a guarded disposable DB and isolated app copy.
External requests are blocked; Gemini and Chart.js use explicit test doubles.
No production config, uploads, credentials, or sessions are copied.
"""
import argparse,json,os,re,secrets,shutil,socket,subprocess,sys,time
from pathlib import Path
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--database',required=True);parser.add_argument('--browser',default='msedge');args=parser.parse_args()
if not re.fullmatch(r'atikha_test_phase3_[a-z0-9]+',args.database):parser.error('Disposable Phase 3 DB required')
php=shutil.which('php') or r'C:\xampp\php\php.exe'
run=root/'.migration-private'/('accounting-browser-'+secrets.token_hex(6));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(s):return "'"+s.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+args.database+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
(app/'config.php').write_text("<?php define('GEMINI_API_KEY','fixture-no-network');",encoding='utf-8')
g=app/'includes/gemini_client.php';s=g.read_text(encoding='utf-8').replace('function gemini_structured_json(', 'function original_gemini_structured_json(')
s+='''
function gemini_structured_json(string $prompt,string $json,array $schema): array {
 $h=json_decode($json,true);return ['ok'=>true,'error'=>'','data'=>['chart_data'=>$h['baseline_projection'],
 'reallocation_suggestion'=>'Fixture advice <script>window.aiBad=1</script>. Review recorded budgets.',
 'funding_risk'=>'Fixture coverage observation; cash runway and donors unavailable.','risk_level'=>'UNKNOWN']];
}
''';g.write_text(s,encoding='utf-8')
def sql(query,mutate=False):
    code='<?php require '+ps(str(root/'scripts/cli_common.php'))+';$pdo=cli_db('+ps(args.database)+');echo json_encode('+('$pdo->exec('+ps(query)+')' if mutate else '$pdo->query('+ps(query)+')->fetchAll()')+');'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def fixture(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+args.database,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php if(preg_match('~^/(scripts|includes|vendor)/~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(404);exit;}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0
def check(condition,label):
    global checks
    if not condition:raise AssertionError(label)
    checks+=1;print('PASS:',label,flush=True)

def record_filter_checks(page,context):
    from urllib.parse import urlencode
    def api(params):return context.request.get(base+'/financial_records.php?'+urlencode(dict(params,format='json')))
    def apply():
        with page.expect_response(lambda r:'/financial_records.php?' in r.url and 'format=json' in r.url):page.locator('#records-apply').click()
        page.wait_for_function("document.querySelector('#records-request-status').textContent==='Filters applied.'")
    def state():return page.evaluate("({summary:document.querySelector('#records-applied-summary').textContent,info:document.querySelector('.dt-info').textContent,debit:document.querySelector('#records-total-debit').textContent,credit:document.querySelector('#records-total-credit').textContent})")
    def money(cents):return ('-' if cents<0 else '')+'₱'+f'{abs(cents)//100:,}.{abs(cents)%100:02d}'
    page.goto(base+'/financial_records.php?from=&to=');page.wait_for_selector('button.records-view')
    initial=state()
    check(page.locator('#date-scope').count()==0 and not page.locator('#from').get_attribute('readonly'),'Main view removes scope and leaves dates editable')
    check([' '.join(text.split()) for text in page.locator('.records-filter-fields label').all_text_contents()]==['From','To','Account type','Account'],'Four filter fields retain their requested order')
    heights=page.locator('#from,#to,#type,#records-account-search').evaluate_all('es=>es.map(e=>e.getBoundingClientRect().height)')
    check(heights==[42,42,42,42],'All filter controls have aligned 42px heights')
    check(page.locator('#records-apply').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(5, 150, 105)','Apply is green')
    check(page.locator('#records-reset').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(255, 255, 255)','Reset is secondary')
    all_rows=api({'from':'','to':''}).json()['rows'];capital=[r for r in all_rows if r['account_name']=='Fixture Capital']
    check('Fixture Capital' not in page.locator('#records-table tbody').inner_text(),'Capital fixture is outside the first page')
    page.locator('.dt-search input').fill('Fixture Capital')
    check(page.evaluate("new DataTable('#records-table').page.info().recordsDisplay")==len(capital)>0 and page.locator('#records-total-credit').inner_text()==money(sum(int(r['credit_cents']) for r in capital)),'Table search finds later-page lines and totals exactly that subset')
    page.locator('.dt-search input').fill('')
    for params,field in [({'from':'2024-02-30'},'from'),({'from':'2026-10-01','to':'2026-09-30'},'to'),({'from[]':'bad'},'from'),({'account_id':'4294967295'},'account_id'),({'sort':'7:asc'},'sort'),({'search[]':'bad'},'search')]:
        r=api(params);check(r.status==400 and field in r.json()['errors'] and not r.json()['ok'],'JSON rejects invalid '+field)
    check(api({'to':'2026-09-05'}).json()['filters']['from']=='','To-only request preserves unbounded From')
    day=api({'from':'2026-09-05','to':'2026-09-05'}).json();check(day['rows'] and all(r['entry_date']=='2026-09-05' for r in day['rows']),'HTTP same-day query includes boundary day only')
    initial_count=len(api({'from':'','to':''}).json()['rows'])
    page.locator('#from').fill('2026-09-01');page.locator('#to').fill('2026-09-30')
    check(page.locator('#records-draft-status').inner_text()=='Changes not applied.' and state()==initial,'Draft edits preserve applied summary, count and totals')
    page.locator('.dt-search input').fill('Synthetic');page.evaluate("new DataTable('#records-table').order([[4,'asc']]).page(1).draw(false)")
    apply()
    table=page.evaluate("({search:new DataTable('#records-table').search(),order:new DataTable('#records-table').order(),page:new DataTable('#records-table').page(),info:new DataTable('#records-table').page.info()})")
    expected=api({'from':'2026-09-01','to':'2026-09-30'}).json()['rows']
    check(table['search']=='Synthetic' and table['order']==[[4,'asc']] and table['page']==0,'Apply preserves search/sort and returns to page one')
    check(table['info']['recordsDisplay']==len(expected)>10 and page.locator('#records-total-debit').inner_text()==money(sum(int(r['debit_cents']) for r in expected)) and page.locator('#records-total-credit').inner_text()==money(sum(int(r['credit_cents']) for r in expected)),'Search-aware count and exact totals span more than one page')
    check('Table search: Synthetic' in state()['summary'] and '2026-09-01 through 2026-09-30' in state()['summary'],'Applied summary includes dates and table search')
    page.reload();page.wait_for_selector('button.records-view');check(page.locator('.dt-search input').input_value()=='Synthetic' and page.evaluate("new DataTable('#records-table').order()")==[[4,'asc']],'Refresh restores bookmarked search and sorting')
    displayed=state();page.locator('#from').fill('2026-10-01');page.locator('#records-apply').click()
    check('on or after' in page.locator('#to-error').inner_text() and page.locator('#from').input_value()=='2026-10-01' and state()==displayed,'Invalid client range preserves draft and prior results')
    page.locator('#from').fill('2026-09-01');check(page.locator('#records-draft-status').inner_text()=='','Returning draft to applied values removes dirty indicator')
    page.locator('#from').fill('');page.locator('#to').fill('');apply()
    check('All dates' in state()['summary'] and page.evaluate("new DataTable('#records-table').page.info().recordsDisplay")==initial_count,'Explicit blank Apply covers all dates')
    page.go_back();page.wait_for_function("document.querySelector('#records-applied-summary').textContent.includes('2026-09-01 through 2026-09-30')")
    page.go_forward();page.wait_for_function("document.querySelector('#records-applied-summary').textContent.includes('All dates')")
    check(page.locator('#from').input_value()=='' and page.locator('#to').input_value()=='','Back and Forward restore both results and controls')
    page.reload();page.wait_for_selector('button.records-view');check('All dates' in state()['summary'],'Blank-date bookmark survives refresh')
    wallet=sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Wallet'")[0]['CategoryID']
    mismatch=api({'from':'','to':'','account_id':wallet,'type':'Expense'});check(mismatch.status==400 and 'account_id' in mismatch.json()['errors'],'Server refuses incompatible account/type without broadening')
    old=sql(f'SELECT Name,Account_Code,Is_Active FROM Categories WHERE CategoryID={wallet}')[0]
    sql(f"UPDATE Categories SET Name='Fixture Wallet historical account with a deliberately long descriptive name',Account_Code='WAL-TEST',Is_Active=0 WHERE CategoryID={wallet}",True)
    try:
        page.reload();page.wait_for_selector('button.records-view');page.locator('#type').select_option('Asset')
        combo=page.locator('#records-account-search');combo.fill('WAL-TEST');page.keyboard.press('ArrowDown');page.keyboard.press('ArrowDown');page.keyboard.press('Enter')
        check(page.locator('#account_id').input_value()==str(wallet) and '(inactive)' in combo.input_value(),'Keyboard code search selects inactive historical account')
        apply();check('(inactive)' in state()['summary'] and page.evaluate("new DataTable('#records-table').page.info().recordsDisplay")>0,'Inactive account produces historical rows and accurate summary')
        full=state();page.locator('#type').select_option('Expense')
        check(page.locator('#account_id').input_value()=='' and 'Account cleared' in page.locator('#records-account-message').inner_text() and state()==full,'Incompatible type clears draft account and explains without changing results')
        names=page.locator('#account_id option').all_text_contents();check(all(' · Expense' in name for name in names[1:]),'Draft account choices follow selected type')
        page.locator('#type').select_option('Asset');combo.fill(str(wallet));page.keyboard.press('ArrowDown');page.keyboard.press('ArrowDown');page.keyboard.press('Enter');apply()
        page.locator('#type').select_option('');check(page.locator('#account_id').input_value()==str(wallet),'All types preserves compatible account')
        combo.fill('no such account');page.keyboard.press('Tab');page.locator('#records-apply').click();check('Select an account' in page.locator('#account_id-error').inner_text() and page.locator('#account_id').input_value()==str(wallet),'Unresolved account text cannot silently become All accounts')
        combo.focus();page.keyboard.press('Escape');check('(inactive)' in combo.input_value(),'Escape restores the selected account label')
    finally:
        old_code='NULL' if old['Account_Code'] is None else "'"+old['Account_Code'].replace("'","''")+"'"
        sql(f"UPDATE Categories SET Name='"+old['Name'].replace("'","''")+f"',Account_Code={old_code},Is_Active={int(old['Is_Active'])} WHERE CategoryID={wallet}",True)
    page.goto(base+'/financial_records.php?from=bad-date&to=2026-09-30');check('bad-date' in page.locator('#from-error').inner_text() and not page.locator('#records-results').is_visible(),'Malformed initial bookmark shows rejected value without fabricated results')
    page.get_by_role('button',name='Clear rejected From value').click();apply();check('On or before 2026-09-30' in state()['summary'],'Rejected native-date text can be explicitly cleared into an unbounded date')
    page.goto(base+'/financial_records.php?from=bad-date&to=2026-09-30')
    page.locator('#from').fill('2026-09-01');apply();check(page.locator('#records-results').is_visible(),'Invalid initial bookmark can recover in place')
    page.goto(base+'/financial_records.php?from=&to=&period=all');page.wait_for_selector('button.records-view');check('no longer used' in page.locator('#records-period-notice').inner_text() and 'period=' not in page.url,'Obsolete Period is explained and removed from canonical URL')
    legacy=api({'filter_category':'Fixture Revenue','filter_type':'Fund'});check(legacy.status==200 and legacy.json()['filters']['type']=='Income' and legacy.json()['dateMode']=='default','Legacy account links normalize without changing monthly default')
    baseline=state();pattern='**/financial_records.php?*format=json*'
    for status_code,body in [(503,{'ok':False,'error':'Synthetic request failure'}),(200,{'ok':True,'rows':[]})]:
        page.route(pattern,lambda route:route.fulfill(status=status_code,content_type='application/json',body=json.dumps(body)))
        page.locator('#from').fill('2026-09-01');page.locator('#to').fill('2026-09-30');page.locator('#records-apply').click()
        page.wait_for_function("document.querySelector('#records-request-status').textContent.includes('last successful results')")
        check(state()==baseline,'Failed or malformed response preserves successful display');page.unroute(pattern)
    page.route(pattern,lambda route:route.fulfill(status=400,content_type='application/json',body=json.dumps({'ok':False,'error':'Correct the filter errors below.','errors':{'account_id':'Synthetic account rejection'}})))
    page.locator('#records-apply').click();page.wait_for_function("document.querySelector('#account_id-error').textContent==='Synthetic account rejection'")
    check(state()==baseline,'Server validation errors preserve displayed results');page.unroute(pattern)
    page.route(pattern,lambda route:route.abort())
    page.locator('#records-apply').click();page.wait_for_function("document.querySelector('#records-request-status').textContent.includes('last successful results')")
    check(state()==baseline,'Network failure preserves successful display');page.unroute(pattern)
    page.locator('.dt-search input').fill('Synthetic');before_reset=state()
    page.route(pattern,lambda route:route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'error':'Synthetic reset failure'})))
    page.locator('#records-reset').click();page.wait_for_function("document.querySelector('#records-request-status').textContent.includes('last successful results')")
    fresh=api({}).json()['defaults'];check(state()==before_reset and page.locator('#from').input_value()==fresh['from'] and page.locator('#to').input_value()==fresh['to'] and page.locator('.dt-search input').input_value()=='Synthetic','Failed Reset preserves previous applied results/search while restoring draft defaults');page.unroute(pattern)
    # Hold the first response in JS, deliberately ignoring abort, to exercise the sequence guard.
    stale=api({'from':'2026-09-01','to':'2026-09-30'}).json()
    page.evaluate("""stale=>{window.savedRecordsFetch=window.fetch;let first=true;window.fetch=(url,options)=>{if(first){first=false;return new Promise(resolve=>{window.releaseRecordsResponse=()=>resolve(new Response(JSON.stringify(stale),{headers:{'Content-Type':'application/json'}}));});}return window.savedRecordsFetch(url,options);};}""",stale)
    page.locator('#from').fill('2026-09-01');page.locator('#to').fill('2026-09-30');page.locator('#records-apply').click();page.wait_for_function("typeof window.releaseRecordsResponse==='function'")
    page.locator('#from').fill('');page.locator('#to').fill('');apply();newer=state();page.evaluate('window.releaseRecordsResponse()')
    page.wait_for_function("document.querySelector('#records-request-status').textContent==='Filters applied.'")
    check(state()==newer and 'All dates' in state()['summary'],'Late older response cannot overwrite newer successful results')
    page.evaluate('window.fetch=window.savedRecordsFetch')
    page.evaluate("""()=>{window.savedRecordsFetch=window.fetch;window.fetch=(url,options)=>new Promise(resolve=>{window.releaseEditedRequest=()=>window.savedRecordsFetch(url,options).then(resolve);});}""")
    page.locator('#from').fill('2026-09-01');page.locator('#to').fill('2026-09-30');page.locator('#records-apply').click();page.wait_for_function("typeof window.releaseEditedRequest==='function'")
    page.locator('#from').fill('2026-09-05');page.evaluate('window.releaseEditedRequest()');page.wait_for_function("document.querySelector('#records-request-status').textContent==='Filters applied.'")
    check(page.locator('#from').input_value()=='2026-09-05' and '2026-09-01 through 2026-09-30' in state()['summary'] and page.locator('#records-draft-status').inner_text()=='Changes not applied.','Edits during request remain draft while submitted snapshot becomes applied')
    page.evaluate('window.fetch=window.savedRecordsFetch');page.locator('.dt-search input').fill('Synthetic');page.evaluate("new DataTable('#records-table').page(1).draw('page')")
    with page.expect_response(lambda r:'format=json' in r.url):page.locator('#records-reset').click()
    page.wait_for_function("document.querySelector('#records-request-status').textContent==='Filters applied.'")
    defaults=api({}).json()['defaults'];check(page.locator('#from').input_value()==defaults['from'] and page.locator('#to').input_value()==defaults['to'] and page.locator('#type').input_value()=='' and page.locator('#account_id').input_value()=='' and page.locator('.dt-search input').input_value()=='' and page.evaluate("new DataTable('#records-table').page()")==0 and 'from=' not in page.url,'Reset immediately applies fresh Manila defaults, clears search/accounts, and resets page')
    page.reload();page.wait_for_selector('button.records-view');check(page.locator('#to').input_value()==defaults['to'],'Reset URL recalculates dynamic defaults on refresh')
    page.locator('.dt-search input').fill('no matching journal');check(page.locator('#records-empty-help').is_visible() and page.locator('#records-total-debit').inner_text()=='₱0.00','No-match search explains empty results and uses exact zero totals')
    page.locator('#records-clear-search').click();check(not page.locator('#records-empty-help').is_visible(),'Empty-state action clears table search')
    for width,columns in [(1440,4),(1100,2),(600,1)]:
        page.set_viewport_size({'width':width,'height':1000});check(page.locator('.records-filter-fields').evaluate("e=>getComputedStyle(e).gridTemplateColumns.split(' ').length")==columns,'Filter grid wraps at viewport '+str(width))
    page.set_viewport_size({'width':1440,'height':1000});page.screenshot(path=str(run/'records-filters-admin.png'),full_page=True)
try:
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def ctx(email=None):
            c=browser.new_context(viewport={'width':1440,'height':1000})
            def external(route):
                if 'chart.js' in route.request.url:
                    route.fulfill(content_type='text/javascript',body='window.__charts=[];window.Chart=function(canvas,config){window.__charts.push(config);this.destroy=function(){};};Chart.getChart=function(){return null;};')
                else:route.abort()
            c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),external)
            if email:
                f=fixture(email);c.add_cookies([{'name':'PHPSESSID','value':f['session'],'url':base}])
            return c
        admin=ctx('admin@example.invalid');page=admin.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.on('dialog',lambda d:d.accept())
        page.goto(base+'/reports.php?year=2026&month=9');check(page.locator('.trial-table').count()==1,'Live Trial Balance renders grouped account table')
        check(page.locator('.trial-section').all_text_contents()==['Balance Sheet','Income Statement'],'Both requested Trial Balance groups present')
        check('Balanced' in page.locator('.trial-table tfoot').inner_text(),'Exact Trial Balance footer displays balance')
        before=sql('SELECT COUNT(*) AS n FROM trial_balance_snapshots')[0]['n']
        page.locator('.js-send-review').click();page.wait_for_url(re.compile(r'.*/reports.php\?snapshot_id=\d+'));page.wait_for_selector('.trial-table')
        sid=int(page.url.rsplit('=',1)[1]);check(int(sql('SELECT COUNT(*) AS n FROM trial_balance_snapshots')[0]['n'])==int(before)+1,'Admin UI submits frozen revision and navigates to its exact snapshot')
        page.emulate_media(media='print');check(page.locator('aside').evaluate("e=>getComputedStyle(e).display")== 'none','Print hides shared sidebar')
        page.screenshot(path=str(run/'trial-balance-print.png'),full_page=True);page.emulate_media(media='screen')
        response=page.goto(base+'/reports.php?month=99&year=99999');check(response.status==400 and page.locator('[role="alert"]').count()>0,'Invalid report period returns usable 400 page')
        response=page.goto(base+'/reports.php?snapshot_id=4294967295');check(response.status==404,'Unknown snapshot does not silently render live report')
        page.goto(base+'/financial_records.php?from=&to=');page.wait_for_selector('#records-table tbody tr button.records-view');check(page.locator('#records-table th').all_text_contents()[:6]==['Date','Reference','Description','Account','Debit','Credit'],'Journal History has requested line columns')
        check(page.locator('#records-page').inner_text().startswith('Page 1 of '),'Ledger pagination active')
        original=page.locator('#records-total-debit').inner_text();page.locator('.dt-search input').fill('Synthetic');check(page.locator('#records-total-debit').inner_text()==original,'Search summary includes all matching pages')
        page.locator('.dt-search input').fill('no such journal');check(page.locator('#records-total-debit').inner_text()=='₱0.00','Search summaries reset to exact zero')
        page.locator('.dt-search input').fill('');page.locator('button.records-view').first.click();check(page.locator('#transaction-dialog').is_visible() and page.locator('.journal-detail-lines tbody tr').count()==2,'Dialog shows complete two-line journal')
        check(page.evaluate('window.bad===undefined'),'Journal descriptions are escaped and inert')
        page.keyboard.press('Escape');check(not page.locator('#transaction-dialog').is_visible(),'Dialog keyboard close works')
        page.screenshot(path=str(run/'journal-history.png'),full_page=True)
        wallet=sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Wallet'")[0]['CategoryID'];page.goto(base+f'/financial_records.php?from=&to=&account_id={wallet}');page.wait_for_selector('button.records-view');check(page.locator('#records-total-difference').inner_text()!='₱0.00','Filtered account difference is visible without claiming journal imbalance')
        page.locator('button.records-view').first.click();check(page.locator('.journal-detail-lines tbody tr').count()==2,'Filtered line still opens its complete journal')
        for view,side in [('crb','debit_cents'),('cdb','credit_cents')]:
            page.goto(base+'/financial_records.php?from=&to=&view='+view);page.wait_for_selector('button.records-view');data=json.loads(page.locator('#records-data').text_content());check(all(int(r[side])>0 for r in data['rows']),view.upper()+' cash lines use the correct side')
            check(page.locator('#date-scope').count()==1 and page.locator('#records-reset').count()==0,view.upper()+' retains original filter controls')
        record_filter_checks(page,admin)
        sql('DELETE FROM forecast_cache',True)
        # A negative month alone need not make a twelve-month CATEGORY total negative.
        if not sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Refund'"):
            sql("INSERT INTO Categories (Name,Type,Account_Type,Normal_Balance,Is_Cash_Account,Is_Active) VALUES ('Fixture Refund','Expense','Expense','Debit',0,1)",True)
            refund=sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Refund'")[0]['CategoryID'];bank=sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Bank'")[0]['CategoryID']
            sql("INSERT INTO journal_entries (entry_date,description,status) VALUES ('2026-08-03','Synthetic refund category','posted')",True)
            jid=sql('SELECT MAX(id) AS id FROM journal_entries')[0]['id']
            sql(f"INSERT INTO journal_entry_lines (journal_entry_id,account_id,debit_amount,credit_amount) VALUES ({jid},{bank},5.00,0.00),({jid},{refund},0.00,5.00)",True)
        page.goto(base+'/dashboard.php');page.wait_for_function("document.querySelector('#forecast-loading').classList.contains('hidden')")
        check(page.locator('#expense-breakdown-list li').count()>0,'Recorded expense breakdown renders independently of AI')
        check('Category shares are unavailable' in page.locator('#expense-breakdown-status').inner_text(),'Signed expense breakdown preserves negative categories')
        check('₱' in page.locator('#expense-breakdown-list').inner_text() and '?' not in page.locator('#expense-breakdown-list').inner_text(),'Exact breakdown amounts retain the peso symbol')
        check(page.evaluate('window.aiBad===undefined'),'AI advisory renders as inert text')
        check(page.locator('p').filter(has_text=re.compile(r'^Total Assets$')).count()==1,'Admin KPI labels report Assets')
        page.screenshot(path=str(run/'admin-dashboard.png'),full_page=True)
        csrf=fixture('admin@example.invalid')['csrf']
        # Each fixture has its own session; use the current page token for HTTP actions.
        csrf=page.evaluate("Array.from(document.scripts).map(s=>s.textContent).join('\\n').match(/const csrfToken = \"([a-f0-9]+)\"/)[1]")
        response=admin.request.post(base+'/forecast_ai.php',form={'action':'refresh','csrf_token':csrf});check(response.status==200 and response.json()['data']['state']=='degraded','Throttled automatic/refresh calls return drawable baseline')
        response=admin.request.post(base+'/forecast_ai.php',form={'csrf_token[]':'invalid'});check(response.status==400,'Array CSRF rejected without PHP TypeError')
        response=admin.request.post(base+'/review_actions.php',form={'action':'send_for_review','entity_type':'report','csrf_token':csrf,'report_month':'9','report_year':'2026'});check(response.status==410,'Legacy report approval cannot mutate accounting reviews')
        management=ctx('management@example.invalid');mp=management.new_page();me=[];mp.on('pageerror',lambda e:me.append(str(e)));mp.on('dialog',lambda d:d.accept())
        mp.goto(base+'/reports.php?snapshot_id='+str(sid));mp.locator('#review-notes').fill('Browser reviewed exact revision');mp.locator('.js-mark-reviewed').click();mp.wait_for_function("!document.querySelector('.js-mark-reviewed')")
        check(sql('SELECT review_status FROM trial_balance_snapshots WHERE id='+str(sid))[0]['review_status']=='Reviewed','Management UI reviews the exact frozen revision')
        mp.goto(base+'/management_reviews.php');check(mp.locator('a[href^="reports.php?snapshot_id="]').count()>0,'Review queue links pending snapshot revisions')
        uid=sql("SELECT UserID FROM Users WHERE Email='admin@example.invalid'")[0]['UserID']
        sql(f"INSERT INTO Board_Communications (Subject,Message_Body,Sender_UserID,Review_Status) VALUES ('Phase 3 board fixture','Synthetic board message',{uid},'Requested')",True)
        boardid=sql('SELECT MAX(CommunicationID) AS id FROM Board_Communications')[0]['id']
        mp.goto(base+'/management_reviews.php');mp.locator(f'[data-entity-type="board"][data-entity-id="{boardid}"]').click();mp.wait_for_function(f"!document.querySelector('[data-entity-type=\"board\"][data-entity-id=\"{boardid}\"]')")
        check(sql(f'SELECT Review_Status FROM Board_Communications WHERE CommunicationID={boardid}')[0]['Review_Status']=='Reviewed','Existing board review workflow still saves and notifies')
        mp.goto(base+'/dashboard.php');mp.wait_for_function("document.querySelector('#md-forecast-status').textContent!=='Loading forecast…'")
        check(mp.locator('#md-cash-title').inner_text()=='Income and Expenses','Management chart uses recognized accounting labels')
        check('Unavailable' in mp.locator('#md-runway').inner_text(),'Management does not invent cash runway')
        mp.screenshot(path=str(run/'management-dashboard.png'),full_page=True)
        mp.goto(base+'/financial_records.php?from=&to=');mp.wait_for_selector('button.records-view')
        check(mp.locator('#records-apply').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(5, 150, 105)' and mp.locator('#records-reset').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(255, 255, 255)','Management uses green Apply and neutral Reset without changing its shell')
        check(mp.locator('.records-account-toggle').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(255, 255, 255)','Management account toggle keeps the neutral control styling')
        check(mp.locator('#records-results > section').nth(1).bounding_box()['y']-mp.locator('#records-results > section').first.bounding_box()['y']-mp.locator('#records-results > section').first.bounding_box()['height']>=20,'Results retain spacing between totals and table')
        mp.screenshot(path=str(run/'records-filters-management.png'),full_page=True)
        check(not errors and not me,'Both dashboard roles and journal/report pages have no uncaught browser errors')
        guest=ctx();gp=guest.new_page();gp.goto(base+'/reports.php');check('/login.php' in gp.url,'Anonymous report access requires authentication')
        expired=ctx('admin@example.invalid');ep=expired.new_page();ep.goto(base+'/financial_records.php?from=&to=');ep.wait_for_selector('button.records-view');old_summary=ep.locator('#records-applied-summary').inner_text();expired.clear_cookies()
        ep.locator('#records-apply').click();ep.wait_for_function("document.querySelector('#records-request-status').textContent.includes('session may have expired')")
        check(ep.locator('#records-applied-summary').inner_text()==old_summary and ep.locator('button.records-view').count()>0,'Expired-session redirect preserves last results and explains authentication failure');expired.close()
        snapshot_schema=sql('SELECT COUNT(*) AS n FROM trial_balance_snapshots')[0]['n'];sql('RENAME TABLE trial_balance_snapshots TO phase3_hidden_snapshots',True)
        try:
            page.goto(base+'/reports.php?year=2026&month=9');check(page.locator('.trial-table').count()==1 and page.locator('.js-send-review').count()==0,'Missing migration disables reviews while live Trial Balance stays available')
            mp.goto(base+'/management_reviews.php');check(mp.locator('h1').inner_text()=='Review Queue','Missing snapshot migration does not break board review page')
        finally:sql('RENAME TABLE phase3_hidden_snapshots TO trial_balance_snapshots',True)
        check(sql('SELECT COUNT(*) AS n FROM trial_balance_snapshots')[0]['n']==snapshot_schema,'Missing-schema test restores all snapshot data')
        browser.close()
    check('Fatal error' not in (run/'server.log').read_text(encoding='utf-8'),'HTTP fixture has no PHP fatal errors')
    print(f'PASS: {checks} browser/HTTP checks; screenshots and log retained in {run}',flush=True)
finally:server.terminate();server.wait(timeout=10);log.close()
