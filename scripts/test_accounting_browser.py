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
        check(not errors and not me,'Both dashboard roles and journal/report pages have no uncaught browser errors')
        guest=ctx();gp=guest.new_page();gp.goto(base+'/reports.php');check('/login.php' in gp.url,'Anonymous report access requires authentication')
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
