"""Checkpoint 5 feature-gate and recovery checks on a complete-schema disposable fixture.
Copies code into a private app; never copies production config, sessions or receipts.
"""
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
run=root/'.migration-private'/('stage3-cp5-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
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
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email):
            c=browser.new_context(viewport={'width':1366,'height':768});c.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());ss=login(email);c.add_cookies([{'name':'PHPSESSID','value':ss['session'],'url':base}]);return c,ss
        ctx,session=context('admin@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        config(stage2=False)
        page.goto(base+'/cash_disbursement.php');today=page.locator('#aw-date').input_value()
        def ordinary(action,values):
            res=ctx.request.post(base+'/accounting_actions.php',data={'action':action,'csrf_token':session['csrf'],**values});check(res.ok,'Ordinary HTTP '+action+' '+res.text() if not res.ok else 'Ordinary HTTP '+action);return res.json()['result']
        for book in ['CRB','CDB','GJ','transfer']:
            amount='25.00'
            lines=([{'client_id':'income','account_id':str(fixture['income']),'fund_project_id':'','debit_amount':'','credit_amount':amount}] if book=='CRB' else
                   [{'client_id':'cost','account_id':str(fixture['training']),'fund_project_id':'','debit_amount':amount,'credit_amount':''}] if book=='CDB' else
                   [{'client_id':'debit','account_id':str(fixture['petty'] if book=='transfer' else fixture['training']),'fund_project_id':'','debit_amount':amount,'credit_amount':''},{'client_id':'credit','account_id':str(fixture['bank'] if book=='transfer' else fixture['tax']),'fund_project_id':'','debit_amount':'','credit_amount':amount}])
            pv={'entry_date':today,'reference':'','description':'Synthetic CP5 HTTP '+book,'party_id':str(fixture['party']),'default_project_id':'','cash_account_id':str(fixture['bank']) if book in ['CRB','CDB'] else '','cash_amount':amount if book in ['CRB','CDB'] else '','cash_project_id':'','transaction_kind':'transfer' if book=='transfer' else 'ordinary','documents':[],'lines':lines}
            d=ordinary('save',{'draft_id':'','source_book':book if book!='transfer' else 'GJ','submission_key':secrets.token_hex(32),'payload':pv});ident={'draft_id':str(d['id']),'revision':str(d['revision']),'submission_key':d['submission_key']}
            v=ordinary('review',ident);original_request={**ident,'payload':d['payload'],'review_token':v['token']};posted=ordinary('post',original_request)
            page.goto(base+'/journal_correction.php?journal_id='+str(posted['id']));page.wait_for_function("document.getElementById('aw-save') && !document.getElementById('aw-save').disabled")
            page.locator('#aw-correction-reason').fill('Synthetic CP5 visible '+book)
            page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved')")
            page.locator('#aw-review').click();page.wait_for_selector('#aw-review-panel:not([hidden])');check(page.locator('#aw-post').is_enabled(),'Ordinary '+book+' correction available while Stage 2 off')
            if book=='CDB':
                for width,height in [(1366,768),(1920,1080)]:
                    page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'CP5 review fits '+str(width));page.screenshot(path=str(run/f'ordinary-review-{width}.png'),full_page=True)
            page.locator('#aw-post').click();page.wait_for_function("document.querySelector('#aw-posted h2').textContent==='Correction posted'");check(page.locator('#aw-posted h2').inner_text()=='Correction posted','Visible '+book+' correction posts with Stage 2 off')
            if book=='CDB':page.screenshot(path=str(run/'ordinary-posted-1920.png'),full_page=True)
            fresh,fresh_session=context('admin@example.invalid');original_request['review_token']='1000000000.'+'0'*64
            before=state();res=fresh.request.post(base+'/accounting_actions.php',data={'action':'post','csrf_token':fresh_session['csrf'],**original_request});check(res.ok and res.json()['result']['duplicate'] and res.json()['result']['id']==posted['id'] and state()==before,'Original '+book+' HTTP request recovers original journal in new session with no writes')
            changed=json.loads(json.dumps(original_request));changed['payload']['description']='Changed original'
            res=fresh.request.post(base+'/accounting_actions.php',data={'action':'post','csrf_token':fresh_session['csrf'],**changed});check(res.status==409 and state()==before,'Changed original '+book+' retry conflicts without writes');fresh.close()
        # Resume and direct HTTP actions derive advance context from persisted records.
        advance=str(fixture['advance_journal']);draft=str(fixture['advance_draft'])
        before=state()
        for url in ['/journal_correction.php?journal_id='+advance,'/journal_correction.php?journal_id='+advance+'&mode=reverse_only','/journal_correction.php?draft_id='+draft,'/journal_correction_actions.php?action=target&journal_id='+advance,'/journal_correction_actions.php?action=draft&draft_id='+draft,'/journal_correction_actions.php?action=reuse_candidates&draft_id='+draft]:
            res=ctx.request.get(base+url);check(res.status==503,'Paused advance route rejects '+url.split('?')[0])
        for action in ['post','review','discard','remove','upload','confirm_return_proof']:
            # Include every required identity field so the availability gate is exercised.
            loaded=sql('SELECT revision,submission_key,payload FROM journal_drafts WHERE id='+draft)[0]
            res=ctx.request.post(base+'/journal_correction_actions.php',data={'action':action,'csrf_token':session['csrf'],'draft_id':draft,'revision':str(loaded['revision']),'submission_key':loaded['submission_key'],'payload':json.loads(loaded['payload']),'receipt_id':'1','upload_key':secrets.token_hex(32),'review_token':'1000000000.'+'0'*64})
            check(res.status==503,'Paused advance '+action+' HTTP rejects before mutation')
        check(state()==before,'All paused advance HTTP actions preserve journals/evidence/drafts/audits')
        page.goto(base+'/financial_records.php?from=&to=');data=json.loads(page.locator('#records-data').text_content())
        check(data['correctionsEnabled'] and data['journals'][advance]['correction_entry_available'] is False,'Records availability hides advance correction start while preserving ordinary global gate')
        eligible=[j for j in data['journals'].values() if j['correction_eligible'] and j['correction_entry_available']]
        check(bool(eligible),'Records retain eligible ordinary correction starts with Stage 2 off')
        comparison=str(sql('SELECT c.target_journal_id id FROM journal_corrections c JOIN cash_advance_operations o ON o.journal_id=c.target_journal_id ORDER BY c.id DESC LIMIT 1')[0]['id'])
        manager,ms=context('management@example.invalid')
        res=manager.request.get(base+'/journal_corrections.php?journal_id='+comparison,timeout=120000);check(res.ok and 'receipt_attachment.php' not in res.text(),'Management comparison remains readable without advance image links')
        for flags in [(True,True,False),(False,True,True),(False,False,False)]:
            config(*flags);before=state();res=ctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft)
            check(res.status==503 and state()==before,'Disabled Stage 1/3 flags preserve draft and block entry')
            res=manager.request.get(base+'/journal_corrections.php?journal_id='+comparison,timeout=120000);check(res.ok and 'receipt_attachment.php' not in res.text(),'Posted financial privacy remains with flags '+str(flags))
        config();page.goto(base+'/journal_correction.php?draft_id='+draft);page.wait_for_function("document.getElementById('aw-save') && !document.getElementById('aw-save').disabled");check(page.locator('#aw-correction-reason').count()==1,'Advance draft resumes after Stage 2 reenabled')
        page.goto(base+'/accounting_drafts.php');page.wait_for_selector('#draft-list tr');page.screenshot(path=str(run/'drafts-1920.png'),full_page=True)
        page.goto(base+'/journal_corrections.php?journal_id='+advance);page.emulate_media(media='print');page.screenshot(path=str(run/'comparison-print.png'),full_page=True);page.emulate_media(media='screen')
        check(not errors,'Checkpoint 5 browser paths have no uncaught errors');browser.close()
    print('Stage 3 checkpoint 5 browser checks:',checks);print('Private browser artifacts:',run)
finally:
    server.terminate();server.wait(timeout=10);log.close()
