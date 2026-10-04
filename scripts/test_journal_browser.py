"""Phase 2 HTTP and headless Edge checks on a synthetic DB and copied app.
Requires test_journal.php bootstrap and Playwright. Never copies production config/uploads.
"""
import argparse
from datetime import datetime, timezone, timedelta
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import sys
import time

root = Path(__file__).resolve().parent.parent
deps = root / '.migration-private/journal-test-deps'
if deps.is_dir(): sys.path.insert(0, str(deps))
from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
parser.add_argument('--browser', default='msedge')
args = parser.parse_args()
if not re.fullmatch(r'atikha_test_journal_[a-z0-9]+', args.database): parser.error('Disposable journal database required')
php = shutil.which('php') or r'C:\xampp\php\php.exe'
run = root / '.migration-private' / ('journal-browser-' + secrets.token_hex(6))
app = run / 'app'; app.mkdir(parents=True)
sessions = run / 'sessions'; sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['config.php', 'db_connect.php']: shutil.copy2(path, app / path.name)
for name in ['includes', 'assets', 'scripts', 'vendor']: shutil.copytree(root / name, app / name)

def php_string(value): return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"
dsn = 'mysql:host=' + os.environ.get('ATIKHA_DB_HOST', '127.0.0.1') + ';dbname=' + args.database + ';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO(' + ','.join(php_string(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')]) + ',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def sql(query, mutate=False):
    # CLI guards select only this validated disposable DB. Queries are fixed test code.
    code = '<?php require ' + php_string(str(root/'scripts/cli_common.php')) + '; $pdo=cli_db(' + php_string(args.database) + ');'
    code += 'echo json_encode(' + ('$pdo->exec('+php_string(query)+')' if mutate else '$pdo->query('+php_string(query)+')->fetchAll()') + ');'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def counts(): return sql('SELECT (SELECT COUNT(*) FROM journal_entries) headers,(SELECT COUNT(*) FROM journal_entry_lines) `lines`,(SELECT COUNT(*) FROM audit_logs) audits')[0]
def fixture(email):
    return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+args.database,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
base=f'http://127.0.0.1:{port}'
router=run/'router.php'
router.write_text("<?php if(preg_match('~^/(scripts|includes|vendor)/~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(404);exit;}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8')
server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0
def check(condition,label):
    global checks
    if not condition: raise AssertionError(label)
    checks+=1; print('PASS:',label,flush=True)
asset=int(sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Cash <script>'")[0]['CategoryID'])
income=int(sql("SELECT CategoryID FROM Categories WHERE Name='Fixture Revenue'")[0]['CategoryID'])
try:
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2): break
        except OSError: time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        browser_contexts=[]
        def context(email=None):
            ctx=browser.new_context(viewport={'width':1440,'height':1000})
            # No external SMTP, Gemini or CDN calls permitted by the fixture.
            ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
            if email:
                session=fixture(email);ctx.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}])
            browser_contexts.append(ctx);return ctx
        ctx=context('admin@example.invalid');page=ctx.new_page();errors=[]
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(base+'/general_journal.php')
        rows=page.locator('[data-journal-line]');post=page.locator('#post-journal')
        check(rows.count()==2 and post.is_disabled(),'Initial two rows and disabled posting')
        check(page.locator('#entry-date').input_value()==datetime.now(timezone(timedelta(hours=8))).strftime('%Y-%m-%d'),'Manila date default in rendered form')
        check(page.locator('[data-remove-line]:disabled').count()==2,'Cannot remove below two rows')
        check(page.locator('aside a[href="general_journal.php"]').count()==1 and page.locator('aside a[href="funds.php"],aside a[href="expenses.php"],aside a[href="ocr_expense.php"]').count()==0,'Sidebar replaces retired workflows with journal')
        page.locator('#journal-description').fill('Browser journal <script>window.bad=1</script>')
        rows.nth(0).locator('select').select_option(str(asset));rows.nth(1).locator('select').select_option(str(income))
        def amount(row,side,value): rows.nth(row).locator('[data-amount="'+side+'_amount"]').fill(value)
        amount(0,'debit','0.10')
        check(rows.nth(0).locator('[data-amount="credit_amount"]').is_disabled(),'Debit typing strictly disables Credit')
        amount(0,'debit','')
        check(rows.nth(0).locator('[data-amount="credit_amount"]').is_enabled(),'Clearing Debit unlocks Credit')
        amount(0,'debit','0.10');amount(1,'credit','0.30')
        check(post.is_disabled() and page.locator('#journal-difference').inner_text()=='-₱0.20','Unbalanced totals disable posting')
        page.locator('#add-journal-line').click();rows.nth(2).locator('select').select_option(str(asset));amount(2,'debit','0.20')
        check(post.is_enabled() and page.locator('#total-debits').inner_text()=='₱0.30' and page.locator('#journal-difference').inner_text()=='₱0.00','0.10 + 0.20 balances exactly against 0.30')
        amount(2,'debit','0.201');check(post.is_disabled(),'Third decimal place blocks posting')
        amount(2,'debit','0.20');rows.nth(2).locator('[data-field="fund_project_id"]').fill('PROJECT')
        check(post.is_disabled(),'Non-numeric Fund/Project blocks posting')
        rows.nth(2).locator('[data-field="fund_project_id"]').fill('4294967295');check(post.is_enabled(),'Maximum numeric Fund/Project is accepted')
        rows.nth(2).locator('select').select_option('');check(post.is_disabled(),'Every row requires an account')
        rows.nth(2).locator('select').select_option(str(asset))
        page.locator('#journal-description').fill('   ');check(post.is_disabled(),'Whitespace-only description blocks posting')
        page.locator('#journal-description').fill('Browser journal <script>window.bad=1</script>')
        page.locator('#add-journal-line').click();rows.nth(3).locator('[data-remove-line]').click()
        check(rows.count()==3 and page.locator('#journal-line-count').input_value()=='3','Dynamic removal synchronizes row count')
        check(rows.nth(2).locator('[data-field="debit_amount"]').get_attribute('name')=='lines[2][debit_amount]','Hidden amounts named and rows reindexed')
        before=counts()
        with page.expect_response(lambda response:response.url.endswith('/general_journal.php') and response.request.method=='POST') as response:
            post.click()
        page.wait_for_url(base+'/general_journal.php');page.wait_for_selector('.journal-success')
        after=counts()
        check(response.value.status==303 and after=={'headers':before['headers']+1,'lines':before['lines']+3,'audits':before['audits']+1},'Browser submits disabled-side amounts and atomically posts via 303 redirect')
        check(rows.count()==2 and page.locator('#journal-description').input_value()=='' and post.is_disabled(),'Successful post resets form to two blank rows')
        check(sql('SELECT SUM(debit_amount) d,SUM(credit_amount) c FROM journal_entry_lines WHERE journal_entry_id=(SELECT MAX(id) FROM journal_entries)')[0]=={'d':'0.30','c':'0.30'},'Stored browser journal balances in database')
        # Exercise the full dynamic row limit and actual PHP input parsing at 100 lines.
        page.locator('#journal-description').fill('Maximum browser journal')
        page.evaluate('''([asset,income]) => {
            const add=document.getElementById('add-journal-line');
            while(document.querySelectorAll('[data-journal-line]').length<100) add.click();
            document.querySelectorAll('[data-journal-line]').forEach((row,i)=>{
                row.querySelector('select').value=String(i%2?income:asset);
                row.querySelector('[data-amount="debit_amount"]').value=i%2?'':'9999999999999.99';
                row.querySelector('[data-amount="credit_amount"]').value=i%2?'9999999999999.99':'';
            });
            document.getElementById('journal-form').dispatchEvent(new Event('input',{bubbles:true}));
        }''',[asset,income])
        check(rows.count()==100 and page.locator('#add-journal-line').is_disabled() and post.is_enabled(),'100 valid lines enforce frontend limit')
        check(page.locator('#total-debits').inner_text()=='₱499,999,999,999,999.50' and page.locator('#journal-difference').inner_text()=='₱0.00','Browser BigInt totals remain exact beyond Number safe-integer range')
        with page.expect_navigation():post.click()
        check(page.locator('.journal-success').count()==1 and int(sql('SELECT COUNT(*) n FROM journal_entry_lines WHERE journal_entry_id=(SELECT MAX(id) FROM journal_entries)')[0]['n'])==100,'100-line real HTTP submission fits PHP limits without truncation')
        def payload():
            page.goto(base+'/general_journal.php')
            return {'csrf_token':page.locator('[name="csrf_token"]').input_value(),'submission_key':page.locator('[name="submission_key"]').input_value(),
                'entry_date':'2026-10-04','reference':'HTTP fixture','description':'Retained <script>window.bad=1</script>', 'line_count':'2','form_complete':'1',
                'lines[0][account_id]':str(asset),'lines[0][fund_project_id]':'','lines[0][debit_amount]':'1.00','lines[0][credit_amount]':'',
                'lines[1][account_id]':str(income),'lines[1][fund_project_id]':'','lines[1][debit_amount]':'','lines[1][credit_amount]':'1.00'}
        data=payload();data['lines[1][credit_amount]']='1.01';before=counts()
        failure=ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0)
        check(failure.status==422 and counts()==before,'HTTP bypass cannot post unbalanced entry')
        text=failure.text()
        check('Retained &lt;script&gt;' in text and 'value="1.01"' in text,'Validation error retains and escapes input')
        page.set_content(text);page.add_script_tag(path=str(root/'assets/js/general_journal.js'))
        check(post.is_disabled() and page.evaluate('window.bad') is None,'Retained malicious text remains inert')
        data=payload();data['csrf_token']='bad';check(ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0).status==400,'HTTP CSRF enforced')
        data=payload();data['submission_key']='0'*64;check(ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0).status==400,'HTTP submission signature enforced')
        data=payload();data['lines[0][account_id]']='4294967295';res=ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0)
        check(res.status==422 and 'Unavailable account' in res.text(),'Missing account fails and remains visibly invalid')
        data=payload();del data['form_complete'];check(ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0).status==422,'HTTP incomplete form rejected')
        data=payload();res=ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0);check(res.status==303,'HTTP balanced posting succeeds')
        before=counts();res=ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0)
        check(res.status==303 and counts()==before,'HTTP retry remains idempotent')
        data['description']='Changed';check(ctx.request.post(base+'/general_journal.php',form=data,max_redirects=0).status==409,'HTTP changed replay returns conflict')
        for route in ['funds.php','expenses.php','ocr_expense.php']:
            get=ctx.request.get(base+'/'+route,max_redirects=0)
            check(get.status==302 and get.headers['location']=='general_journal.php',route+' GET redirects to journal')
            check(ctx.request.post(base+'/'+route,form={'action':'create'},max_redirects=0).status==410,route+' POST retired before writes')
        check(ctx.request.post(base+'/ocr_extract.php',form={},max_redirects=0).status==410,'OCR API retired before upload or external calls')
        data=payload()
        for entity in ['fund','expense']:
            check(ctx.request.post(base+'/review_actions.php',form={'csrf_token':data['csrf_token'],'action':'send_for_review','entity_type':entity,'entity_id':'1'},max_redirects=0).status==410,entity+' review writes retired')
        for email in [None,'management@example.invalid','inactive@example.invalid']:
            other=context(email)
            for route in ['general_journal.php','admin_accounts.php']:
                expected=403 if email=='management@example.invalid' else 302
                check(other.request.get(base+'/'+route,max_redirects=0).status==expected,str(email)+' cannot access '+route)
        page.goto(base+'/admin_accounts.php?new=1');page.wait_for_selector('#account-form')
        check(page.locator('#account-type option').count()==6,'Account form exposes all five types')
        page.locator('#account-name').fill('Browser Asset');page.locator('#account-type').select_option('Asset')
        check(page.locator('#normal-balance').input_value()=='Debit' and not page.locator('#cash-account option[value="1"]').is_disabled(),'Asset defaults to Debit and permits cash')
        page.locator('#account-type').select_option('Liability')
        check(page.locator('#normal-balance').input_value()=='Credit' and page.locator('#cash-account option[value="1"]').is_disabled(),'Liability defaults to Credit and excludes cash')
        page.locator('#account-type').select_option('Asset');page.locator('#cash-account').select_option('1');page.locator('#account-code').fill('1010')
        with page.expect_navigation(): page.locator('#account-form button[type="submit"]').click()
        check('Account created.' in page.locator('main').inner_text(),'Browser creates Asset account through audited service')
        account=sql("SELECT * FROM Categories WHERE Name='Browser Asset'")[0]
        check(account['Type'] is None and account['Normal_Balance']=='Debit' and int(account['Is_Cash_Account'])==1,'Created Asset has correct accounting fields')
        page.goto(base+'/admin_accounts.php?edit='+str(asset));page.wait_for_selector('#account-form')
        check(page.locator('#normal-balance').is_disabled() and page.locator('#cash-account').is_disabled() and page.locator('#account-code').get_attribute('readonly') is not None,'Posted account controls lock in UI')
        token=page.locator('[name="csrf_token"]').first.input_value()
        bad=ctx.request.post(base+'/admin_accounts.php',form={'csrf_token':token,'action':'update','account_id':str(asset),'account_code':'FORGED','normal_balance':'Credit','is_cash_account':'0','description':'Retain description'},max_redirects=0)
        check('Accounting fields are locked' in bad.text() and 'value="1000" readonly' in bad.text(),'Rejected locked-field changes restore authoritative values in error form')
        page.locator('#description').fill('Browser metadata edit')
        with page.expect_navigation(): page.locator('#account-form button[type="submit"]').click()
        check(sql('SELECT Description FROM Categories WHERE CategoryID='+str(asset))[0]['Description']=='Browser metadata edit','Locked values survive form submission while metadata updates')
        check(page.locator('.account-transactions-link').count()==0,'Retired legacy account drill-down removed')
        page.goto(base+'/admin_accounts.php?type=Asset&q=1010')
        check(page.locator('.account-row').count()==1 and 'Browser Asset' in page.locator('.account-row').inner_text(),'Account code search and type filter work')
        for type_ in ['Asset','Liability','Equity','Income','Expense']:
            page.goto(base+'/admin_accounts.php?type='+type_)
            check(page.locator('#filter-type').input_value()==type_,type_+' filter supported')
        page.goto(base+'/general_journal.php');page.screenshot(path=str(run/'journal.png'),full_page=True)
        check(page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'),'Journal has no page overflow at desktop width')
        page.set_viewport_size({'width':1024,'height':768})
        check(page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'),'Journal table scroll stays inside page at 1024px')
        page.goto(base+'/admin_accounts.php?new=1');page.screenshot(path=str(run/'accounts.png'),full_page=True)
        sql('ALTER TABLE journal_entries CHANGE submission_key submission_key_unavailable CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL',True)
        try: check(ctx.request.get(base+'/general_journal.php').status==503,'Missing posting migration fails closed')
        finally: sql('ALTER TABLE journal_entries CHANGE submission_key_unavailable submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL',True)
        check(not errors,'No browser JavaScript errors')
        for ctx_ in browser_contexts:ctx_.close()
        browser.close()
    log.flush();logs=(run/'server.log').read_text(encoding='utf-8')
    check('PHP Fatal error' not in logs and 'PHP Warning' not in logs,'No PHP runtime warnings or fatal errors in HTTP tests')
    print('Journal HTTP/browser checks passed:',checks,flush=True)
    print('Isolated fixture:',run,flush=True)
finally:
    server.terminate();server.wait(timeout=10);log.close()
