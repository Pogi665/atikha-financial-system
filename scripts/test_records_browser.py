"""Real Chromium/Edge checks against a disposable database and isolated application copy.

Requires Playwright in the Python environment (no application dependency).
Run test_ledger.php on a fresh atikha_test_* database first. Never copies live uploads/config.
"""
import argparse
import base64
from email.parser import BytesParser
from email.policy import default
import hashlib
import http.client
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import time
import threading

parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
parser.add_argument('--browser', default='msedge')
parser.add_argument('--fixture', help='Reuse a retained disposable fixture manifest for the same database')
args = parser.parse_args()
if not re.fullmatch(r'atikha_test_[a-z0-9_]+', args.database):
    parser.error('Only a disposable atikha_test_* database is allowed')
try:
    from playwright.sync_api import sync_playwright
except ImportError:
    raise SystemExit('UNVERIFIED: Playwright unavailable. Follow docs/records-and-board-previews.md.')
root = Path(__file__).resolve().parent.parent
run = root / '.migration-private' / ('records-browser-' + secrets.token_hex(6))
app = run / 'app'
app.mkdir(parents=True)
sessions = run / 'sessions'
sessions.mkdir()
php = shutil.which('php') or r'C:\xampp\php\php.exe'
for path in root.glob('*.php'):
    if path.name not in ['config.php', 'db_connect.php']:
        shutil.copy2(path, app / path.name)
for name in ['includes', 'assets', 'vendor', 'scripts']:
    shutil.copytree(root / name, app / name)
for name in ['receipts', 'board', 'external']:
    (app / 'uploads' / name).mkdir(parents=True)
def php_string(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"
dsn = 'mysql:host=' + os.environ.get('ATIKHA_DB_HOST', '127.0.0.1') + ';dbname=' + args.database + ';charset=utf8mb4'
(app / 'db_connect.php').write_text('<?php $pdo=new PDO(' + ','.join(php_string(v) for v in [dsn, os.environ.get('ATIKHA_DB_USER','root'), os.environ.get('ATIKHA_DB_PASSWORD','')]) + ',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);', encoding='utf-8')
def fixture(*options):
    return json.loads(subprocess.check_output([php,str(app / 'scripts/http_fixture.php'),'--database='+args.database,'--sessions='+str(sessions),*options],text=True))
if args.fixture:
    seed_path=Path(args.fixture).resolve()
    if not seed_path.is_relative_to((root/'.migration-private').resolve()): parser.error('Fixture must be retained under .migration-private')
    manifest=json.loads(seed_path.read_text(encoding='utf-8'))
    if manifest['database']!=args.database: parser.error('Fixture database mismatch')
    seed=manifest['seed']
else:
    seed = fixture('--records-fixture')
(run/'fixture.json').write_text(json.dumps({'database':args.database,'seed':seed}),encoding='utf-8')
print('Fixture manifest:',run/'fixture.json',flush=True)
png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aYJ8AAAAASUVORK5CYII=')
(app / 'uploads/receipts/browser.png').write_bytes(png)
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
captures=[]
class CaptureProxy(BaseHTTPRequestHandler):
    def do_GET(self): self.forward()
    def do_POST(self): self.forward()
    def do_HEAD(self): self.forward()
    def forward(self):
        body=self.rfile.read(int(self.headers.get('Content-Length','0')))
        if self.command=='POST' and self.path.split('?')[0]=='/board_messages.php':
            captures.append({'content-type':self.headers.get('Content-Type',''),'body':body})
        headers={k:v for k,v in self.headers.items() if k.lower() not in ['host','connection']}
        connection=http.client.HTTPConnection('127.0.0.1',port,timeout=30)
        connection.request(self.command,self.path,body=body,headers=headers)
        response=connection.getresponse();content=response.read()
        self.send_response(response.status)
        for key,value in response.getheaders():
            if key.lower() not in ['connection','transfer-encoding','content-length']: self.send_header(key,value)
        self.send_header('Content-Length',str(len(content)));self.end_headers()
        if self.command!='HEAD': self.wfile.write(content)
        connection.close()
    def log_message(self,*args): pass
proxy=ThreadingHTTPServer(('127.0.0.1',0),CaptureProxy)
threading.Thread(target=proxy.serve_forever,daemon=True).start()
base=f'http://127.0.0.1:{proxy.server_port}'
router=run / 'router.php'
router.write_text("<?php if(preg_match('~^/(scripts|includes|vendor)/~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(404);exit;}return false;",encoding='utf-8')
log=(run / 'server.log').open('w',encoding='utf-8')
server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0
def check(condition,label):
    global checks
    if not condition: raise AssertionError(label)
    checks+=1;print('PASS:',label,flush=True)
def money(cents):
    value=int(cents);a=abs(value)
    return ('-' if value<0 else '')+'₱'+format(a//100,',')+'.'+str(a%100).zfill(2)
def multipart(capture):
    message=BytesParser(policy=default).parsebytes(('Content-Type: '+capture['content-type']+'\r\nMIME-Version: 1.0\r\n\r\n').encode()+capture['body'])
    return [(part.get_filename(),part.get_payload(decode=True)) for part in message.iter_parts() if part.get_param('name',header='content-disposition')=='attachment']
try:
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        contexts={}
        def viewer(key,email):
            login=fixture('--email='+email,'--test-login')
            context=browser.new_context(viewport={'width':1440,'height':1000})
            context.add_cookies([{'name':'PHPSESSID','value':login['session'],'url':base}])
            contexts[key]=context
            return context.new_page()
        page=viewer('owner','admin@example.invalid')
        page.add_init_script('''window.__objectUrls = new Set();
            const create=URL.createObjectURL.bind(URL), revoke=URL.revokeObjectURL.bind(URL);
            URL.createObjectURL=value=>{const url=create(value);window.__objectUrls.add(url);return url;};
            URL.revokeObjectURL=url=>{window.__objectUrls.delete(url);return revoke(url);};
            const read=File.prototype.arrayBuffer;
            File.prototype.arrayBuffer=function(){return this.name==='unreadable.pdf'?Promise.reject(new Error('Fixture read failure')):read.call(this);};''')
        errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
        records=base+'/financial_records.php?category=Browser%20shared'
        page.goto(records);page.wait_for_selector('#records-table tbody .records-view')
        check(page.locator('#records-table tbody tr').count()==10,'Exactly ten rows on first page of 61 matches')
        check(page.locator('.dt-info').inner_text()=='Showing 1–10 of 61 records.','Information spans entire matching dataset')
        dates=page.evaluate('new DataTable("#records-table").rows({order:"applied"}).data().toArray().map(r=>r.txn_date)')
        check(dates==sorted(dates,reverse=True),'Default dates sort newest first')
        check(page.get_by_role('button',name='Previous',exact=True).is_disabled(),'Previous disabled on first page')
        check(page.locator('.dt-length').count()==0,'No length selector')
        check(page.locator('#records-total-incoming').inner_text()==money(seed['incoming_cents']) and page.locator('#records-total-expense').inner_text()==money(seed['expense_cents']),'Exact summaries span all 61 records')
        check('3 affected records; 2 missing purpose; 2 Unallocated' in page.locator('#records-completeness-counts').inner_text(),'Completeness union counted across pages')
        for _ in range(6):page.get_by_role('button',name='Next',exact=True).click()
        check(page.locator('#records-table tbody tr').count()==1 and page.locator('.dt-info').inner_text()=='Showing 61–61 of 61 records.','Partial final page beyond fifty')
        check(page.get_by_role('button',name='Next',exact=True).is_disabled(),'Next disabled on final page')
        page.locator('.records-view').click();page.wait_for_selector('#transaction-dialog[open]');page.click('#transaction-close')
        check(page.locator('#records-page').inner_text()=='Page 7 of 7','Closing modal retains current page')
        search=page.get_by_role('searchbox')
        search.fill('Hiddenneedle');page.wait_for_function("document.querySelector('.dt-info').textContent.includes('of 1 records')")
        check(page.locator('#records-page').inner_text()=='Page 1 of 1','Search returns to page one and finds hidden later purpose')
        check(page.locator('#records-total-incoming').inner_text()==money(20),'Summary follows hidden-field search')
        page.locator('.records-view').click();page.wait_for_selector('#transaction-dialog[open]')
        check('Hiddenneedle <script> harmless' in page.locator('#transaction-details').inner_text(),'Full purpose rendered as safe text in exact transaction dialog')
        identity=page.locator('#transaction-dialog').get_attribute('data-transaction')
        rows=page.locator('#records-data').text_content();original=next(r for r in json.loads(rows)['rows'] if r['identity']==identity)
        check(money(original['running_cents']) in page.locator('#transaction-details').inner_text(),'Modal retains original pre-search running figure')
        page.keyboard.press('Shift+Tab')
        check(page.evaluate("document.activeElement.closest('dialog') !== null"),'Keyboard focus contained in modal')
        page.keyboard.press('Escape')
        check(page.locator('#transaction-dialog[open]').count()==0 and page.locator('.records-view').evaluate('(el)=>el===document.activeElement'),'Escape closes and restores focus')
        check(search.input_value()=='Hiddenneedle','Modal leaves search intact')
        for term,count in [('one-only',1),('ten-match',10),('eleven-match',11),('Projectneedle',1),('FIND-LATE-REF',1),('0.20',1),('no-such-needle',0)]:
            search.fill(term);page.wait_for_function('(n)=>new DataTable("#records-table").page.info().recordsDisplay===n',arg=count)
            check(page.locator('#records-page').inner_text()==('Page 0 of 0' if count==0 else 'Page 1 of '+str((count+9)//10)),f'Correct page indicator for {count} search matches: {term}')
        check(page.locator('.dt-info').inner_text()=='Showing 0–0 of 0 records.' and page.get_by_role('button',name='Next',exact=True).is_disabled(),'Zero search results have disabled navigation')
        page.goto(base+'/financial_records.php?category=NoHistory');page.wait_for_selector('.dt-empty')
        check(page.locator('#records-page').inner_text()=='Page 0 of 0','Empty dataset initializes through DataTables')
        for context,txn,label in [('crb','Incoming','Received from'),('cdb','Expense','Paid to')]:
            page.goto(records+'&view='+context);page.wait_for_selector('.records-view')
            payload=json.loads(page.locator('#records-data').text_content())['rows']
            check(all(r['txn_type']==txn for r in payload) and page.locator('select[name=type]').count()==0 and label in page.locator('#records-table thead').inner_text(),context+' fixed type and contextual columns')
            nav=page.locator('aside a[href="financial_records.php?view='+context+'"]')
            check('bg-slate-700' in nav.get_attribute('class'),context+' sidebar highlighted')
            page.select_option('#date-scope','month')
            check(page.locator('#from').input_value()==time.strftime('%Y-%m-01'),'Month preset fills canonical date')
            page.get_by_role('button',name='Apply Filters').click();page.wait_for_selector('.dt-empty')
            check('All dates' not in page.locator('.records-filter-summary').inner_text(),'Applied date preset is clearly labeled')
            page.locator('#records-filters a').click();page.wait_for_selector('.records-view')
            check('view='+context in page.url and page.locator('#from').input_value()=='','Clear keeps book context and restores all dates')
        page.goto(base+'/financial_records.php?filter_category=Browser%20shared&filter_type=Fund');page.wait_for_selector('.records-view')
        check(len(json.loads(page.locator('#records-data').text_content())['rows'])==31 and page.locator('#category').input_value()=='Browser shared','Inactive account deep link retains exact category and type')
        page.goto(records);page.wait_for_selector('.records-view')
        page.locator('#records-table thead th').nth(4).click()
        # The order state updates before DataTables finishes its deferred sort/draw.
        page.wait_for_function('''()=>{const t=new DataTable('#records-table');
            if(t.order()[0][0]!==4)return false;
            const a=t.rows({order:'applied'}).data().toArray().map(r=>Number(r.amount_cents));
            return a.every((v,i)=>!i||(t.order()[0][1]==='desc'?a[i-1]>=v:a[i-1]<=v));}''')
        sorted_amounts=page.evaluate('new DataTable("#records-table").rows({order:"applied"}).data().toArray().map(r=>Number(r.amount_cents))')
        direction=page.evaluate('new DataTable("#records-table").order()[0][1]')
        check(sorted_amounts==sorted(sorted_amounts,reverse=direction=='desc'),'Amounts sort numerically')
        page.screenshot(path=str(run/'admin-records.png'),full_page=True)
        page.goto(base+'/financial_records.php');page.wait_for_selector('.records-view')
        all_rows=json.loads(page.locator('#records-data').text_content())['rows']
        common=min({r['record_id'] for r in all_rows if r['txn_type']=='Incoming'} & {r['record_id'] for r in all_rows if r['txn_type']=='Expense'})
        for txn in ['Incoming','Expense']:
            chosen=next(r for r in all_rows if r['record_id']==common and r['txn_type']==txn)
            page.get_by_role('searchbox').fill(chosen['party']);page.wait_for_function('new DataTable("#records-table").page.info().recordsDisplay===1')
            page.locator('.records-view').click();page.wait_for_selector('#transaction-dialog[open]')
            check(page.locator('#transaction-dialog').get_attribute('data-transaction')==txn+':'+str(common),'Overlapping numeric ID opens correct '+txn+' modal')
            page.keyboard.press('Escape')
        page.set_viewport_size({'width':420,'height':760});page.locator('.records-view').click();page.wait_for_selector('#transaction-dialog[open]')
        bounds=page.locator('#transaction-dialog').bounding_box()
        check(bounds['width']<=420 and bounds['height']<=760,'Details dialog fits small viewport while shell remains unchanged')
        page.screenshot(path=str(run/'small-dialog.png'));page.keyboard.press('Escape');page.set_viewport_size({'width':1440,'height':1000})
        management=viewer('management','management@example.invalid');management.goto(records);management.wait_for_selector('.records-view')
        check('executive-theme' in management.locator('body').get_attribute('class'),'Management theme preserved')
        management.screenshot(path=str(run/'management-records.png'),full_page=True)
        other=viewer('other','other@example.invalid')
        def receipt_url(n,expense=None):
            return base+'/transaction_attachment.php?type=Expense&id='+str(expense or seed['ids'][n]['id'])+'&receipt_id='+str(seed['receipts'][str(n)])
        check(contexts['owner'].request.get(receipt_url(31)).body()==png,'Owner Admin receives real associated receipt bytes')
        check(contexts['other'].request.get(receipt_url(32)).body()==png,'Other Admin can access only their own associated receipt')
        check(contexts['management'].request.get(receipt_url(31)).status==403,'Management receipt request denied')
        check(contexts['other'].request.get(receipt_url(31)).status==404,'Different Admin receipt request denied')
        check(contexts['owner'].request.get(receipt_url(31,seed['ids'][32]['id'])).status==404,'Wrong expense association denied')
        check(contexts['owner'].request.get(receipt_url(33)).status==404 and contexts['owner'].request.get(receipt_url(34)).status==404,'Missing and traversal receipt paths denied')
        inactive=viewer('inactive','inactive@example.invalid');inactive.goto(receipt_url(31))
        check('login.php' in inactive.url,'Inactive session denied')
        check(contexts['owner'].request.get(base+'/transaction_attachment.php?type=Expense&id[]=1&receipt_id=1').status==400,'Malformed array identifier rejected')
        # Real browser multipart serialization; parse file parts and compare bytes.
        def compose():
            page.goto(base+'/board_messages.php');page.fill('#subject','Browser fixture');page.fill('#message_body','Isolated draft')
        original_pdf=b'%PDF-1.4\n%original browser fixture\n%%EOF\n'
        replacement_pdf=b'%PDF-1.4\n%replacement browser fixture\n%%EOF\n'
        original_file={'name':'original.pdf','mimeType':'application/pdf','buffer':original_pdf}
        replacement_file={'name':'replacement.pdf','mimeType':'application/pdf','buffer':replacement_pdf}
        def choose(file):
            page.set_input_files('#attachment',file);page.wait_for_selector('#attachment-preview:not([hidden])')
            check(not captures,'Selecting a file sends no upload or message')
        def replacement(file):
            with page.expect_file_chooser() as chooser:page.click('#attachment-replace')
            chooser.value.set_files(file)
        def submit_bytes(expected,label):
            check(page.locator('input[name=attachment]:enabled').count()==1,'Only one successful named attachment control: '+label)
            page.get_by_role('button',name='Send to Management').click()
            page.wait_for_url('**/board_messages.php?sent=1')
            parts=multipart(captures.pop())
            check(len(parts)==1 and (parts[0][1] or b'')==(expected or b''),'Actual multipart bytes: '+label)
            state=fixture('--inspect');stored=state['messages'][-1]['File_Path']
            check((app/stored).read_bytes()==expected if expected is not None else stored is None,'Real board route stores intended attachment: '+label)
        compose();choose(original_file);replacement(replacement_file)
        page.wait_for_function("document.querySelector('.attachment-filename').textContent==='replacement.pdf'")
        check(page.evaluate('window.__objectUrls.size')==1,'Replacement releases old preview URL')
        submit_bytes(replacement_pdf,'valid replacement')
        compose();choose(original_file);replacement([])
        check('original.pdf' in page.locator('#attachment-preview').inner_text(),'Empty/canceled picker preserves original preview')
        submit_bytes(original_pdf,'canceled selection')
        compose();choose(original_file);replacement({'name':'bad.txt','mimeType':'text/plain','buffer':b'bad'})
        page.wait_for_selector('#attachment-error:not([hidden])');submit_bytes(original_pdf,'invalid replacement')
        compose();choose(original_file);page.click('#attachment-remove')
        check(page.evaluate('window.__objectUrls.size')==0,'Remove releases preview URL')
        submit_bytes(None,'removed attachment')
        compose();choose(original_file);page.click('#attachment-remove');choose(original_file);submit_bytes(original_pdf,'same file reselected')
        compose();choose({'name':'empty-mime.png','mimeType':'','buffer':png})
        check(page.locator('#attachment-preview img').count()==1,'Empty browser MIME accepted with image decode')
        page.click('#attachment-preview button');page.wait_for_selector('#attachment-enlarge[open]');page.keyboard.press('Escape')
        check(page.locator('#attachment-enlarge[open]').count()==0,'Image enlargement Escape works')
        page.click('#attachment-remove');choose({'name':'generic.docx','mimeType':'application/octet-stream','buffer':b'PK generic fixture'})
        check('Content preview unavailable' in page.locator('#attachment-preview').inner_text(),'Generic DOCX MIME is inconclusive with honest card')
        page.click('#attachment-remove')
        for file in [{'name':'empty.pdf','mimeType':'application/pdf','buffer':b''},{'name':'oversize.pdf','mimeType':'application/pdf','buffer':b'x'*(8*1024*1024+1)},{'name':'bad.pdf','mimeType':'text/html','buffer':b'<html>'},{'name':'corrupt.png','mimeType':'image/png','buffer':b'not an image'},{'name':'unreadable.pdf','mimeType':'application/pdf','buffer':original_pdf}]:
            page.set_input_files('#attachment',file);page.wait_for_selector('#attachment-error:not([hidden])')
            check(page.locator('#attachment').evaluate('(el)=>el.files.length')==0,'Rejected file clears initial selection: '+file['name'])
        exact={'name':'exact.pdf','mimeType':'application/pdf','buffer':b'%PDF-1.4\n'+b' '*(8*1024*1024-9)}
        choose(exact);check('8.00 MiB' in page.locator('#attachment-preview').inner_text(),'Exact 8 MiB allowed client-side')
        page.screenshot(path=str(run/'board-preview.png'),full_page=True)
        submit_bytes(exact['buffer'],'exact 8 MiB boundary')
        last=fixture('--inspect')['messages'][-1]
        sent_url=base+'/board_attachment.php?id='+str(last['CommunicationID'])
        check(contexts['owner'].request.get(sent_url).body()==exact['buffer'],'Existing sender download returns stored bytes')
        check(contexts['management'].request.get(sent_url).body()==exact['buffer'],'Existing Management download remains authorized')
        check(contexts['other'].request.get(sent_url).status==403,'Existing unrelated sender download remains denied')
        compose();token=page.locator('input[name=csrf_token]').input_value()
        count=len(fixture('--inspect')['messages'])
        for attachment,expected in [({'name':'oversize.pdf','mimeType':'application/pdf','buffer':exact['buffer']+b'x'},'too large'),({'name':'empty.pdf','mimeType':'application/pdf','buffer':b''},'empty'),({'name':'pretend.pdf','mimeType':'application/pdf','buffer':b'<html>not a PDF</html>'},'Only PDF')]:
            response=contexts['owner'].request.post(base+'/board_messages.php',multipart={'csrf_token':token,'subject':'Server draft <safe>','message_body':'Retained & body','attachment':attachment})
            html=response.text();captures.clear()
            check(expected in html and 'Server draft &lt;safe&gt;' in html and 'Retained &amp; body' in html,'Server validation and escaped draft: '+attachment['name'])
            check(len(fixture('--inspect')['messages'])==count,'Rejected upload creates no message: '+attachment['name'])
        response=contexts['owner'].request.post(base+'/board_messages.php',multipart={'csrf_token':'wrong','subject':'CSRF fixture','message_body':'not sent','attachment':original_file})
        captures.clear();check('session expired' in response.text() and len(fixture('--inspect')['messages'])==count,'CSRF still rejects attachment submissions')
        state=fixture('--inspect')
        check(last['Review_Status']=='Requested' and any(row['module']=='Board_Communications' for row in state['audits']),'Existing review status and audit behavior retained')
        check(not errors,'No browser JavaScript errors')
        check(contexts['management'].request.get(base+'/board_messages.php').status==403,'Management board composition denied')
        browser.close()
    print(f'PASS: {checks} browser assertions; screenshots and isolated fixture: {run}',flush=True)
finally:
    proxy.shutdown();proxy.server_close()
    server.terminate()
    try:server.wait(timeout=5)
    except subprocess.TimeoutExpired:server.kill();server.wait()
    log.close()
