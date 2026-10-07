"""Checkpoint 2 browser/HTTP checks on an already-created disposable CP2 fixture.
Copies code into a private app; never copies production config, sessions or receipts.
"""
import argparse,json,os,re,secrets,shutil,socket,struct,subprocess,sys,time,zlib
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
run=root/'.migration-private'/('stage3-cp2-browser-'+secrets.token_hex(5));app=run/'app';app.mkdir(parents=True);sessions=run/'sessions';sessions.mkdir()
for path in root.glob('*.php'):
    if path.name not in ['db_connect.php','config.php']:shutil.copy2(path,app/path.name)
for name in ['includes','assets','scripts','vendor']:shutil.copytree(root/name,app/name)
def ps(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
dsn='mysql:host='+os.environ.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+db+';charset=utf8mb4'
(app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,os.environ.get('ATIKHA_DB_USER','root'),os.environ.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8')
def config(stage3=True):
    (app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',"+str(stage3).lower()+");define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR',"+ps(str(evidence))+");",encoding='utf-8')
config()
def sql(query):
    code='<?php require '+ps(str(root/'scripts/cli_common.php'))+';$p=cli_db('+ps(db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());'
    return json.loads(subprocess.check_output([php],input=code,text=True))
def login(email):return json.loads(subprocess.check_output([php,str(app/'scripts/http_fixture.php'),'--database='+db,'--sessions='+str(sessions),'--email='+email,'--test-login'],text=True))
for target,key in [('liquidation_target','liquidation_draft'),('return_target','return_draft')]:
    rows=sql("SELECT id FROM journal_drafts WHERE workflow_kind='correction' AND state='Draft' AND correction_mode='reverse_replace' AND correction_target_journal_id="+str(int(fixture[target]))+' ORDER BY id DESC LIMIT 1')
    if rows:fixture[key]=rows[0]['id']
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
try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.2):break
        except OSError:time.sleep(.1)
    with sync_playwright() as p:
        browser=p.chromium.launch(channel=args.browser,headless=True)
        def context(email):
            ctx=browser.new_context(viewport={'width':1366,'height':768});ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());session=login(email);ctx.add_cookies([{'name':'PHPSESSID','value':session['session'],'url':base}]);return ctx,session
        ctx,session=context('admin@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        def ready():
            page.wait_for_selector('#aw-party-search',state='attached');page.wait_for_function("document.getElementById('aw-save').disabled===false")
        def save():
            page.locator('#aw-save').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Draft saved') || document.getElementById('aw-error').textContent.length>0");check(not page.locator('#aw-error').inner_text(),'Save: '+page.locator('#aw-error').inner_text())
        def review():
            if not page.locator('#aw-backdate-reason').input_value():page.locator('#aw-backdate-reason').fill('Synthetic historical fixture reviewed after midnight')
            page.locator('#aw-review').click();page.wait_for_function("!document.getElementById('aw-review-panel').hidden || document.getElementById('aw-error').textContent.length>0");check(not page.locator('#aw-error').inner_text(),'Review validation: '+page.locator('#aw-error').inner_text())
        def call(action,values={},actor=None,csrf=None,endpoint='journal_correction_actions.php'):
            return (actor or ctx).request.post(base+'/'+endpoint,data={'action':action,'csrf_token':csrf or session['csrf'],**values})
        prior=ctx.request.get(base+'/accounting_actions.php?action=drafts').json()['result']
        for row in prior:
            if row['workflow_kind']=='correction' and int(row['correction_target_journal_id'])==int(fixture['payment_target']):
                response=call('discard',{'draft_id':str(row['id']),'revision':str(row['revision'])});check(response.ok,'Discard earlier disposable browser draft')
        before=int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])
        page.goto(base+'/journal_correction.php?journal_id='+str(fixture['payment_target']));ready()
        check(page.locator('#aw-original tr').count()>=3,'Comparison shows immutable original lines')
        page.locator('#aw-correction-reason').fill('Synthetic amount or classification correction');save();draft_id=re.search(r'draft_id=(\d+)',page.url).group(1)
        check(page.locator('#aw-correction-mode').is_disabled(),'Saved correction mode remains immutable')
        committed=page.locator('#aw-party').input_value();page.locator('#aw-party-search').fill('no matching record zz');page.keyboard.press('Enter');page.keyboard.press('Escape')
        check(page.locator('#aw-party').input_value()==committed and 'unsaved' not in page.locator('#aw-draft-label').inner_text(),'Visible search cancellation keeps draft ID selection and revision unchanged')
        review();check(page.locator('#aw-post').is_enabled() and 'Generated exact reversal' in page.locator('#aw-review-content').inner_text(),'Ordinary preview shows exact GJ reversal and offers reviewed posting')
        for width,height in [(1366,768),(1920,1080)]:
            page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Comparison fits viewport '+str(width));page.screenshot(path=str(run/f'comparison-{width}.png'),full_page=True)
        page.locator('#aw-back').click();page.locator('#aw-save').click();page.wait_for_timeout(100)
        # Reload gets eligible source images after the first save.
        page.reload();ready();reuse=page.locator('#aw-reuse option:not([value=""])').first;image=reuse.get_attribute('value');page.locator('#aw-reuse').select_option(image);page.locator('#aw-attach-reuse').click();page.wait_for_selector('[data-document]')
        check('Prior review' in page.locator('[data-document]').inner_text() and not page.locator('[data-doc-field="reviewed"]').is_checked(),'Reused image displays prior review but never confirms it automatically')
        image_response=ctx.request.get(base+'/'+page.locator('[data-document] a').get_attribute('href'));check(image_response.ok,'Owner draft-scoped original-image URL returns protected bytes')
        page.locator('[data-doc-field="re_review_reason"]').fill('Informational reference in corrected entry');page.locator('[data-doc-field="reviewed"]').check();save();page.reload();ready();check(page.locator('[data-doc-field="reviewed"]').is_checked(),'Resume preserves fresh manual review and reason')
        review();page.locator('#aw-back').click()
        # An interrupted upload has a durable key; retry should recover one receipt.
        uploads_before=int(sql('SELECT COUNT(*) n FROM Receipts')[0]['n']);lost=[False]
        def lose_upload(route):
            if not lost[0] and route.request.method=='POST' and 'multipart/form-data' in route.request.headers.get('content-type',''):
                lost[0]=True;route.fetch();route.abort('failed')
            else:route.continue_()
        page.route('**/journal_correction_actions.php',lose_upload);page.locator('#aw-upload').set_input_files({'name':'Synthetic correction proof.png','mimeType':'image/png','buffer':png()});page.wait_for_selector('#aw-upload-retry:not([hidden])');page.locator('#aw-upload-retry').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Image stored')");page.unroute('**/journal_correction_actions.php',lose_upload)
        check(int(sql('SELECT COUNT(*) n FROM Receipts')[0]['n'])==uploads_before+1 and page.locator('[data-document]').count()==2,'Lost v4 upload response recovers one private image with context retained')
        page.locator('[data-document]').last.get_by_role('button',name='Remove from draft').click();page.wait_for_function("document.querySelectorAll('[data-document]').length===1")
        check(page.locator('[data-document]').count()==1,'Remove new upload preserves posted reused image')
        loaded=ctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).json()['result'];identity={'draft_id':draft_id,'revision':str(loaded['revision'])}
        for action in ['review','remove','discard']:
            response=call(action,{**identity,'receipt_id':image},endpoint='accounting_actions.php');check(response.status==409,'Ordinary '+action+' rejects v4 draft')
            response=call(action,{**identity,'receipt_id':image},endpoint='cash_advance_actions.php');check(response.status==409,'Advance '+action+' rejects v4 draft')
        response=call('post',identity);check(response.status==422,'Incomplete posting request is rejected with no financial mutation')
        mctx,msession=context('management@example.invalid')
        check(mctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).status==403,'Management cannot read private correction draft')
        check(call('discard',identity,mctx,msession['csrf']).status==403,'Management cannot discard private correction draft')
        check(mctx.request.get(base+'/receipt_attachment.php?receipt_id='+image+'&draft_id='+draft_id).status==404,'Management cannot fetch draft-scoped evidence bytes')
        octx,osession=context('other@example.invalid');check(octx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).status==404,'Other Admin cannot read private correction draft')
        # My Drafts resumes/discards through the dedicated route.
        page.goto(base+'/accounting_drafts.php');page.wait_for_function("document.getElementById('draft-status').textContent.includes('loaded')");row=page.locator('tr[data-draft-id="'+draft_id+'"]');check('Correction reverse replace' in row.inner_text() and row.locator('a').get_attribute('href')=='journal_correction.php?draft_id='+draft_id,'My Drafts labels and resumes correction context')
        row.locator('a').click();ready()
        if page.locator('#aw-replacement-book').input_value()=='GJ':
            with page.expect_navigation(wait_until='domcontentloaded'):page.locator('#aw-replacement-book').select_option('CRB')
            ready()
        with page.expect_navigation(wait_until='domcontentloaded'):page.locator('#aw-replacement-book').select_option('GJ')
        ready()
        check(page.locator('[data-line]').count()>=2 and page.locator('#aw-correction-reason').input_value().startswith('Synthetic'),'Explicit replacement book change preserves complete lines and correction reason')
        page.goto(base+'/journal_correction.php?journal_id='+str(fixture['release_target'])+'&mode=reverse_only');ready();page.locator('#aw-correction-reason').fill('Duplicate synthetic release');save();review()
        check(page.locator('.aw-columns aside').is_hidden() and page.locator('#aw-review-content').inner_text().count('Replacement ·')==0,'Reverse-only workspace hides replacement and evidence claims')
        page.screenshot(path=str(run/'reverse-only-1366.png'),full_page=True)
        # Review an existing supported replacement liquidation using the visible saved draft.
        if fixture.get('liquidation_draft'):
            page.goto(base+'/journal_correction.php?draft_id='+str(fixture['liquidation_draft']));ready();review();check('debit: PHP' in page.locator('.aw-coverage').inner_text() and 'credit:' not in page.locator('.aw-coverage').inner_text(),'Liquidation preview displays gross cost coverage only')
        if fixture.get('return_draft'):
            page.goto(base+'/journal_correction.php?draft_id='+str(fixture['return_draft']));ready();page.locator('#aw-backdate-reason').fill('Synthetic historical return reviewed after midnight');page.locator('#aw-confirm-proof').click();page.wait_for_function("document.getElementById('aw-status').textContent.includes('Proof confirmed')");review();check('Return proof confirmed' in page.locator('#aw-review-content').inner_text(),'Return replacement confirms current amount and reused proof')
            page.locator('#aw-back').click();page.locator('#aw-cash-amount').fill('17.00');save();check('not been confirmed' in page.locator('#aw-proof-status').inner_text(),'Changed return replacement amount clears confirmation')
        if fixture.get('stale_draft'):
            page.goto(base+'/journal_correction.php?draft_id='+str(fixture['stale_draft']));page.wait_for_function("document.getElementById('aw-correction-eligibility').textContent.includes('already been corrected')");check(page.locator('#aw-review').is_disabled() and page.locator('#aw-correction-eligibility a').get_attribute('href').startswith('journal_corrections.php'),'Stale correction shows reason, permitted posted link and disabled review')
        if fixture.get('posted_draft'):
            opage=octx.new_page();opage.goto(base+'/journal_correction.php?draft_id='+str(fixture['posted_draft']));opage.wait_for_selector('#aw-posted:not([hidden])');check(opage.locator('.aw-columns').is_hidden() and 'reversal journal #' in opage.locator('#aw-posted').inner_text(),'Posted correction reopening shows immutable posted bundle, not editable original')
        config(False);check(ctx.request.get(base+'/journal_correction_actions.php?action=draft&draft_id='+draft_id).status==503,'Disabled Stage 3 flag blocks correction workspace actions')
        check(mctx.request.get(base+'/receipt_attachment.php?receipt_id='+image+'&draft_id='+draft_id).status==404,'Draft privacy remains effective with Stage 3 flag disabled')
        config()
        check(int(sql('SELECT COUNT(*) n FROM journal_entries')[0]['n'])==before,'Every browser action leaves journal count unchanged')
        check(not errors,'Correction browser workflows have no JavaScript errors');browser.close()
    print('Stage 3 checkpoint 2 browser checks: '+str(checks));print('Private browser artifacts: '+str(run))
finally:
    if 'page' in globals() and not page.is_closed():
        try:print('Browser error panel: '+page.locator('#aw-error').inner_text());print('JavaScript errors: '+str(errors));page.screenshot(path=str(run/'last-screen.png'),full_page=True)
        except Exception:pass
    server.terminate();server.wait(timeout=10);log.close()
