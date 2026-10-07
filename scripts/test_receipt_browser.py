"""Phase 4 HTTP/Edge checks. Uses a NEW disposable DB, private files and Gemini
transport double. No live config/uploads/sessions or external calls are used."""
import argparse, hashlib, json, os, re, secrets, shutil, socket, struct, subprocess, sys, time, zlib
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
    if not args.database or not re.fullmatch(r'atikha_test_phase4_[a-z0-9]+',args.database):parser.error('New disposable receipt database required')
    bootstrap=subprocess.check_output([php,str(root/'scripts/test_receipt_journal.php'),'--database='+args.database],text=True);print(bootstrap,end='')
    evidence=Path(re.search(r'Private evidence: (.+)',bootstrap).group(1).strip())/'receipts'
if not re.fullmatch(r'atikha_test_phase4_[a-z0-9]+',args.database):parser.error('Disposable receipt database required')
run=root/'.migration-private'/('receipt-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(value):return "'"+value.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+args.database+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
(app/'config.php').write_text("<?php define('GEMINI_API_KEY','fixture-no-network');define('OCR_JOURNAL_ENABLED',true);define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");define('FIXTURE_CAPTURE',"+ps(str(run/'gemini-capture.json'))+");",encoding='utf-8')
g=app/'includes/gemini_client.php';s=g.read_text(encoding='utf-8').replace('function gemini_request(', 'function original_gemini_request(')
s+=r'''
function gemini_request(array $payload,?int $timeout=null,?int $connect=null):array{
 file_put_contents(FIXTURE_CAPTURE,json_encode(['payload'=>$payload,'timeout'=>$timeout,'connect'=>$connect]));
 $mode=$_POST['fixture_mode']??'good';
 if($mode==='failure'){return ['ok'=>false,'raw'=>'fixture_failure','text'=>'','error'=>'fixture timeout'];}
 if(in_array($mode,['busy','limit','badkey'],true)){$code=['busy'=>503,'limit'=>429,'badkey'=>403][$mode];return ['ok'=>false,'raw'=>json_encode(['error'=>['code'=>$code,'message'=>'PRIVATE_PROVIDER_DETAIL']]),'text'=>'','error'=>'Provider rejected request'];}
 if($mode==='badjson'){return ['ok'=>true,'raw'=>'fixture_badjson','text'=>'invalid JSON','error'=>''];}
 global $pdo;$id=(int)$pdo->query("SELECT CategoryID FROM Categories WHERE Account_Type='Expense' AND Is_Active=1 LIMIT 1")->fetchColumn();
 $d=['schema_version'=>'journal_receipt_v1','merchant'=>'Vendor <script>window.ocrBad=1</script>','document_type'=>'receipt','reference'=>'OCR-REF',
 'date_text'=>'October 1, 2026','transaction_date'=>'2026-10-01','currency'=>$mode==='foreign'?'USD':'PHP','total_amount'=>'123.45',
 'payment_text'=>null,'suggested_debit_account_id'=>$id,'classification_reason'=>'Fixture explanation','confidence'=>.95,'missing'=>[],'warnings'=>[]];
 if($mode==='partial'){$d['transaction_date']=null;$d['suggested_debit_account_id']=4294967295;$d['total_amount']='1e3';$d['confidence']=.1;}
 return ['ok'=>true,'raw'=>json_encode(['fixture'=>$d]),'text'=>json_encode($d),'error'=>''];
}
''';g.write_text(s,encoding='utf-8')
def sql(query,mutate=False):
    code='<?php require '+ps(str(root/'scripts/cli_common.php'))+';$pdo=cli_db('+ps(args.database)+');echo json_encode('+('$pdo->exec('+ps(query)+')' if mutate else '$pdo->query('+ps(query)+')->fetchAll()')+');'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def user(name,role='Admin'):
    email=name+'@browser.invalid'
    sql("INSERT INTO Users (FullName,Email,Role,Password,Is_Active) VALUES ('Browser "+name+"','"+email+"','"+role+"','not-a-login-hash',1)",True)
    sql("INSERT INTO user_identities (UserID,FullName,Email,Role) SELECT UserID,FullName,Email,Role FROM Users WHERE Email='"+email+"'",True)
    return email
def login(email):
    return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+args.database,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
def png(w=64,h=64,noise=False,pad=0):
    def chunk(kind,data):return struct.pack('>I',len(data))+kind+data+struct.pack('>I',zlib.crc32(kind+data)&0xffffffff)
    rows=b''.join(b'\0'+(os.urandom(w*4) if noise else bytes([secrets.randbelow(255),50,100,255])*w) for _ in range(h))
    data=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',w,h,8,6,0,0,0))+chunk(b'IDAT',zlib.compress(rows))+chunk(b'IEND',b'')
    return data+b'\0'*max(0,pad-len(data))
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
router=run/'router.php';router.write_text("<?php if(preg_match('~^/(uploads|scripts|includes|vendor)/~',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH))){http_response_code(403);exit;}return false;",encoding='utf-8')
log=(run/'server.log').open('w',encoding='utf-8');server=subprocess.Popen([php,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(app),str(router)],stdout=log,stderr=log)
checks=0
def check(ok,label):
    global checks
    if not ok:raise AssertionError(label)
    checks+=1;print('PASS: '+label,flush=True)
def counts():return [int(sql('SELECT COUNT(*) n FROM '+t)[0]['n']) for t in ['journal_entries','journal_entry_lines']]
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email=None):
            c=browser.new_context(viewport={'width':1440,'height':1000})
            c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort())
            if email:
                f=login(email);c.add_cookies([{'name':'PHPSESSID','value':f['session'],'url':base}])
            return c
        owner=context(user('owner'));other=context('other@receipt.invalid');manager=context('management@receipt.invalid');guest=context()
        page=owner.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.on('dialog',lambda d:d.accept())
        def payload(c,mode='good',data=None):
            # Read real rendered tokens without spawning dozens of asset-loading
            # tabs against PHP's single-threaded development server.
            html=c.request.get(base+'/ocr_expense.php').text()
            values={k:re.search(r'<input[^>]*name="'+k+r'"[^>]*value="([^"]*)"',html).group(1) for k in ['csrf_token','request_key']}
            values.update(action='upload',fixture_mode=mode,receipt_image={'name':'receipt.png','mimeType':'image/png','buffer':data or png()});return values
        values=payload(owner);before=counts();response=owner.request.post(base+'/ocr_extract.php',multipart=values)
        assert response.status==200,response.text()
        result=response.json();rid=result['data']['receipt_id']
        check(response.status==200 and result['ok'] and counts()==before,'Real multipart upload/extraction create zero financial records')
        row=sql('SELECT * FROM Receipts WHERE ReceiptID='+str(rid))[0];stored=evidence/Path(row['File_Path']).name
        check(stored.read_bytes()==values['receipt_image']['buffer'] and row['File_SHA256']==hashlib.sha256(stored.read_bytes()).hexdigest(),'Uploaded and stored original bytes/hash match exactly')
        check(owner.request.post(base+'/ocr_extract.php',multipart=values).json()['data']['receipt_id']==rid,'Same multipart request retries return original receipt')
        values_changed=dict(values);values_changed['receipt_image']={'name':'different.png','mimeType':'image/png','buffer':png()}
        check(owner.request.post(base+'/ocr_extract.php',multipart=values_changed).status==409,'Upload key with different bytes conflicts')
        check(other.request.get(base+'/receipt_attachment.php?receipt_id='+str(rid)).status==404 and manager.request.get(base+'/receipt_attachment.php?receipt_id='+str(rid)).status==404,'Unposted evidence private to uploader')
        preview=owner.request.get(base+'/receipt_attachment.php?receipt_id='+str(rid))
        check(preview.status==200 and preview.body()==stored.read_bytes() and preview.headers['x-content-type-options']=='nosniff','Uploader preview returns original bytes and safe headers')
        check(owner.request.get(base+'/'+row['File_Path']).status==403,'Isolated HTTP router denies direct receipt URLs')
        page.goto(base+'/ocr_expense.php?receipt='+str(rid));check('Vendor <script>' in page.locator('.ocr-facts').inner_text() and page.evaluate('window.ocrBad') is None,'OCR strings displayed as text, never executable HTML')
        check(page.locator('aside a[href="ocr_expense.php"]').count()==1,'Admin sidebar exposes Scan Receipt after deployment gate')
        page.locator('a',has_text='Review in General Journal').click()
        check(page.locator('[data-field="account_id"]').nth(1).input_value()=='' and page.locator('#post-journal').is_disabled(),'Credit account starts blank and posting disabled')
        check(page.locator('[data-amount="debit_amount"]').nth(0).input_value()=='123.45' and page.locator('[data-amount="credit_amount"]').nth(1).input_value()=='123.45','Two proposed lines use exact centavo amounts')
        bank=str(sql("SELECT CategoryID FROM Categories WHERE Name='Phase4 Bank'")[0]['CategoryID'])
        page.locator('[data-field="account_id"]').nth(1).select_option(bank);page.locator('#journal-description').fill('Reviewed office purchase')
        check(page.locator('#post-journal').is_disabled(),'PHP currency confirmation required even when amounts balance')
        page.locator('#receipt-currency-confirmed').check();check(page.locator('#post-journal').is_enabled(),'Complete reviewed entry enables Post Entry')
        page.screenshot(path=str(run/'receipt-journal-review.png'),full_page=True)
        page.set_viewport_size({'width':1024,'height':1000})
        check(page.evaluate('document.documentElement.scrollWidth<=1024'),'OCR-assisted journal has no page overflow at 1024px')
        page.set_viewport_size({'width':1440,'height':1000})
        page.locator('#post-journal').click();page.wait_for_url(base+'/general_journal.php')
        jid=int(sql('SELECT JournalEntryID FROM Receipts WHERE ReceiptID='+str(rid))[0]['JournalEntryID'])
        check(counts()==[before[0]+1,before[1]+2] and 'posted successfully' in page.locator('[role="status"]').first.inner_text(),'Explicit button posts one balanced journal and linked evidence')
        url=base+'/receipt_attachment.php?receipt_id='+str(rid)+'&journal_id='+str(jid)
        check(other.request.get(url).status==200 and manager.request.get(url).body()==stored.read_bytes(),'All active Admins and Management can view posted evidence')
        check(manager.request.get(url+'&download=1').headers['content-disposition'].startswith('attachment;'),'Posted evidence supports safe downloads')
        check(manager.request.get(base+'/receipt_attachment.php?receipt_id='+str(rid)+'&journal_id=4294967295').status==404,'Mismatched journal association denied')
        check(guest.request.get(url).status in [401,403] or '/login.php' in guest.request.get(url).url,'Anonymous evidence access denied')
        duplicate=payload(owner,data=stored.read_bytes());check(owner.request.post(base+'/ocr_extract.php',multipart=duplicate).status==409,'Posted identical image rejected before Gemini extraction')
        page.goto(base+'/financial_records.php?from=&to=');page.wait_for_selector('.records-view');page.locator('.dt-search input').fill('Reviewed office purchase');page.locator('.records-view').first.click()
        check(page.locator('.journal-receipt a',has_text='View receipt').count()==1,'Journal dialog exposes evidence without duplicating line rows')
        page.locator('#transaction-close').click()
        for mode in ['failure','badjson','partial','foreign']:
            c=context(user(mode));vals=payload(c,mode);r=c.request.post(base+'/ocr_extract.php',multipart=vals).json();id2=r['data']['receipt_id'];q=c.new_page();q.goto(base+'/general_journal.php?receipt_id='+str(id2))
            if mode in ['failure','badjson']:check(q.locator('.journal-alert').count()>0 and q.locator('#journal-form').count()==1,'Failed '+mode+' retains evidence for manual journal preparation')
            if mode=='partial':check(q.locator('#entry-date').input_value()=='' and q.locator('[data-field="account_id"]').nth(0).input_value()=='' and q.locator('#post-journal').is_disabled(),'Ambiguous date/invalid amount/nonexistent account remain blank')
            if mode=='foreign':check(q.locator('[data-amount="debit_amount"]').nth(0).input_value()=='' and 'Non-PHP' in q.locator('.journal-receipt-review').inner_text(),'Foreign receipt not automatically copied into PHP amounts')
            q.close()
        valid=context(user('large'));large=png(2200,600,True);r=valid.request.post(base+'/ocr_extract.php',multipart=payload(valid,data=large))
        check(r.status==200,'Large valid PNG accepted under size/pixel bounds')
        captured=json.loads((run/'gemini-capture.json').read_text());image=captured['payload']['contents'][0]['parts'][1]['inline_data']
        import base64
        check(image['mime_type']=='image/jpeg' and base64.b64decode(image['data']).startswith(b'\xff\xd8'),'Downscaled PNG sent as actual JPEG MIME')
        check(captured['timeout']==60 and captured['connect']==10,'OCR transport uses bounded request/connect timeouts')
        boundary=context(user('boundary'));eight=png(pad=8*1024*1024)
        check(boundary.request.post(base+'/ocr_extract.php',multipart=payload(boundary,data=eight)).status==200,'Exact 8 MiB valid image accepted')
        check(boundary.request.post(base+'/ocr_extract.php',multipart=payload(boundary,data=eight+b'x')).status==400,'8 MiB plus one byte rejected')
        check(boundary.request.post(base+'/ocr_extract.php',multipart=payload(boundary,data=b'not an image')).status==400,'Forged image MIME/nonimage bytes rejected')
        huge=b'\x89PNG\r\n\x1a\n'+struct.pack('>I',13)+b'IHDR'+struct.pack('>IIBBBBB',6000,6000,8,6,0,0,0)
        huge+=struct.pack('>I',zlib.crc32(huge[12:])&0xffffffff)+b'\0'*128
        check(boundary.request.post(base+'/ocr_extract.php',multipart=payload(boundary,data=huge)).status==400,'Oversized image dimensions rejected before decoding')
        csrf=payload(boundary);csrf['csrf_token']='wrong';before=counts();check(boundary.request.post(base+'/ocr_extract.php',multipart=csrf).status==400 and counts()==before,'Upload CSRF failure has no financial side effects')
        check(manager.request.post(base+'/ocr_extract.php',multipart=csrf).status==403,'Management upload denied')
        check(owner.request.get(base+'/ocr_extract.php').status==405,'Extraction endpoint rejects GET')
        rate=context(user('rate'));vals=payload(rate);r=rate.request.post(base+'/ocr_extract.php',multipart=vals).json();rateid=r['data']['receipt_id']
        for _ in range(4):
            v=payload(rate);v.pop('receipt_image');v.update(action='retry',receipt_id=str(rateid))
            check(rate.request.post(base+'/ocr_extract.php',multipart=v).status==200,'Explicit retry allowed within five-call window')
        v=payload(rate);v.pop('receipt_image');v.update(action='retry',receipt_id=str(rateid))
        check(rate.request.post(base+'/ocr_extract.php',multipart=v).status==429,'Sixth extraction within five minutes throttled')
        q=rate.new_page();q.goto(base+'/general_journal.php?receipt_id='+str(rateid));check(q.locator('#journal-form').count()==1,'Throttled retry preserves last completed proposal');q.close()
        # Uploaded evidence survives an extraction-start rate limit.
        blocked=rate.request.post(base+'/ocr_extract.php',multipart=payload(rate));saved=blocked.json()['data']['workspace_url'];q=rate.new_page();q.goto(base+'/'+saved);q.locator('a',has_text='Review in General Journal').click()
        q.wait_for_url(re.compile(r'.*/general_journal.php\?receipt_id=\d+'));q.wait_for_selector('#journal-form')
        check(q.locator('#journal-form').count()==1 and q.locator('#entry-date').input_value()=='','Rate-limited new upload has a recoverable manual proposal');q.close()
        inactive_id=int(sql("SELECT UserID FROM Users WHERE Email='other@receipt.invalid'")[0]['UserID']);sql('UPDATE Users SET Is_Active=0 WHERE UserID='+str(inactive_id),True)
        inactive_response=other.request.get(url)
        check(inactive_response.status!=200 or '/login.php' in inactive_response.url,'Deactivated Admin loses receipt access immediately')
        # Shared navigation changes must preserve real Board attachment uploads.
        page.goto(base+'/board_messages.php');page.fill('#subject','Phase4 Board fixture');page.fill('#message_body','Disposable draft only')
        original_pdf=b'%PDF-1.4\n%original fixture\n%%EOF\n';replacement_pdf=b'%PDF-1.4\n%replacement fixture\n%%EOF\n'
        page.set_input_files('#attachment',{'name':'original.pdf','mimeType':'application/pdf','buffer':original_pdf})
        page.wait_for_selector('#attachment-preview:not([hidden])')
        with page.expect_file_chooser() as chooser:page.click('#attachment-replace')
        chooser.value.set_files({'name':'replacement.pdf','mimeType':'application/pdf','buffer':replacement_pdf})
        page.wait_for_function("document.querySelector('.attachment-filename').textContent==='replacement.pdf'")
        check(page.locator('input[name=attachment]:enabled').count()==1,'Board replacement keeps one successful attachment control')
        page.get_by_role('button',name='Send to Management').click();page.wait_for_url('**/board_messages.php?sent=1')
        board=sql("SELECT * FROM Board_Communications WHERE Subject='Phase4 Board fixture' ORDER BY CommunicationID DESC LIMIT 1")[0]
        check((app/board['File_Path']).read_bytes()==replacement_pdf,'Board route stores replacement bytes in isolated application')
        page.goto(base+'/board_messages.php');page.fill('#subject','Phase4 Board removed');page.fill('#message_body','Disposable draft only')
        page.set_input_files('#attachment',{'name':'original.pdf','mimeType':'application/pdf','buffer':original_pdf});page.wait_for_selector('#attachment-preview:not([hidden])');page.click('#attachment-remove')
        page.get_by_role('button',name='Send to Management').click();page.wait_for_url('**/board_messages.php?sent=1')
        check(sql("SELECT File_Path FROM Board_Communications WHERE Subject='Phase4 Board removed'")[0]['File_Path'] is None,'Board attachment removal does not submit previous bytes')
        before=counts();page.goto(base+'/ocr_expense.php')
        page.set_viewport_size({'width':1366,'height':768})
        check(page.locator('#receipt-upload button').evaluate('(el)=>getComputedStyle(el).backgroundColor')=='rgb(4, 120, 87)','Upload action uses the accounting workspace accent color')
        page.screenshot(path=str(run/'scan-empty-1366.png'),full_page=True)
        page.set_input_files('#receipt-image',{'name':'ui-receipt.png','mimeType':'image/png','buffer':png()})
        page.wait_for_function("document.querySelector('#ocr-selected-image').naturalWidth>0")
        check(page.locator('#ocr-local-preview').is_visible() and 'ui-receipt.png' in page.locator('#ocr-selected-file').inner_text() and counts()==before,'Selected-image preview is local and posts nothing')
        page.screenshot(path=str(run/'scan-selected-1366.png'),full_page=True)
        page.locator('#receipt-upload button').click();page.wait_for_url(re.compile(r'.*/ocr_expense.php\?receipt=\d+'))
        ui_id=int(page.url.rsplit('=',1)[1])
        check(counts()==before and page.locator('a',has_text='Review in General Journal').count()==1,'Actual browser AJAX upload hands off to review without posting')
        check('123.45' in page.locator('.ocr-amount').inner_text() and page.locator('.ocr-tag-ready').inner_text()=='Ready for review','Processed details have a readable total and review status')
        check('Suggested expense account' in page.locator('.ocr-facts').inner_text() and 'Suggested Expense account ID' not in page.locator('.ocr-facts').inner_text() and page.evaluate('window.ocrBad===undefined'),'Account suggestions use escaped labels rather than raw IDs')
        page.set_viewport_size({'width':1920,'height':1080});page.screenshot(path=str(run/'scan-processed-1920.png'),full_page=True)
        ui_row=sql('SELECT File_Path FROM Receipts WHERE ReceiptID='+str(ui_id))[0]
        page.locator('#receipt-discard button').click();page.wait_for_url('**/ocr_expense.php?discarded=1')
        check(not (evidence/Path(ui_row['File_Path']).name).exists() and counts()==before,'Discard UI removes only unposted evidence and posts nothing')
        for mode,expected in [('busy','AI service is busy'),('limit','request limit'),('badkey','AI configuration'),('failure','too long')]:
            scan=context(user('supplement'+mode));q=scan.new_page();q.on('pageerror',lambda e:errors.append(str(e)))
            outcome=scan.request.post(base+'/ocr_extract.php',multipart=payload(scan,mode)).json();rid2=outcome['data']['receipt_id']
            q.goto(base+'/ocr_expense.php?receipt='+str(rid2));q.set_viewport_size({'width':1366,'height':768})
            check(outcome['data']['status']=='Failed' and expected in q.locator('.ocr-warning').inner_text(),'Specific '+mode+' guidance survives receipt extraction and rendering')
            check('PRIVATE_PROVIDER_DETAIL' not in q.content() and q.get_by_role('link',name='Review in General Journal').is_visible() and counts()==before,'Failure retains manual General Journal access without exposing provider details or posting')
            if mode=='busy':
                # Insert an older-style completed attempt; never rewrite immutable existing rows.
                sql("INSERT INTO receipt_ocr_attempts(receipt_id,requested_by_user_id,request_key,state,started_at,completed_at,source_hash,catalog_fingerprint,model,schema_version,raw_response,error_message) SELECT receipt_id,requested_by_user_id,'"+secrets.token_hex(32)+"','Failed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),source_hash,catalog_fingerprint,model,schema_version,raw_response,'Automatic extraction was unavailable. Retry or enter the details manually.' FROM receipt_ocr_attempts WHERE id="+str(outcome['data']['attempt_id']),True)
                q.reload();check('AI service is busy' in q.locator('.ocr-warning').inner_text(),'Historical generic failures display their saved 503 reason without rewriting attempts')
                q.screenshot(path=str(run/'scan-busy-1366.png'),full_page=True)
                q.set_viewport_size({'width':1920,'height':1080});q.screenshot(path=str(run/'scan-busy-1920.png'),full_page=True)
                check(q.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Busy desktop view has no page overflow')
                q.get_by_role('button',name='Retry Extraction').click();q.wait_for_url(re.compile(r'.*/ocr_expense.php\?receipt='+str(rid2)+r'$'))
                q.wait_for_selector('.ocr-tag-ready');check(sql('SELECT COUNT(*) n FROM Receipts WHERE ReceiptID='+str(rid2))[0]['n']==1 and counts()==before,'Manual retry reads the same saved image without duplicating it or posting')
            q.get_by_role('link',name='Review in General Journal').click();q.wait_for_selector('#journal-form')
            check(counts()==before,'General Journal handoff remains review only after '+mode)
            q.close();scan.close()
        check(not errors,'Browser reports no JavaScript errors')
        browser.close()
    print(f'Completed {checks} HTTP/browser checks. Screenshots/private app: {run}')
finally:
    server.terminate();server.wait(timeout=10);log.close()
