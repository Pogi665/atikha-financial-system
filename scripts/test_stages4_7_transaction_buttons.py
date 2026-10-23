"""G7 bounded presentation retest on the retained synthetic app; no financial writes."""
from pathlib import Path
import hashlib,json,os,re,secrets,shutil,subprocess,sys
from urllib.parse import urlparse,parse_qs,urlencode
from stages4_7_browser import ROOT,PRIVATE,ps,IsolatedBrowserApp
sys.path.insert(0,str(PRIVATE/'journal-test-deps'))
from playwright.sync_api import sync_playwright
pointer=json.loads((PRIVATE/'stages4-7-current.json').read_text())
meta_path=Path(pointer['g7_metadata']).resolve()
assert meta_path.is_relative_to(PRIVATE) and pointer['g7_ready']
meta=json.loads(meta_path.read_text());app=Path(meta['app']).resolve()
assert app.is_relative_to(PRIVATE) and meta['base'].startswith('http://127.0.0.1:')
fixture=json.loads(Path(meta['fixture']).read_text());db=fixture['database']
assert re.fullmatch(r'atikha_test_stage1_s5[a-z0-9]+',db)
env=os.environ.copy();env.update(pointer['environment'])
run=PRIVATE/('stages4-7-g7-buttons-'+secrets.token_hex(5));run.mkdir()
names=['journal_transaction.php','assets/css/accounting_workspace.css']
old={n:hashlib.sha256((app/n).read_bytes()).hexdigest() for n in names}
assert all(old[n]==meta['source_pins'][n] for n in names),'Preserve unexplained runtime drift'
new={n:hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in names}
installed=old==new
baseline_app=None
if installed:
    previous=next(r for r in reversed(meta['source_refinements']) if r['after']==new)
    assert previous['after']==new
    saved_run=Path(previous['evidence_run']).resolve();assert saved_run.is_relative_to(PRIVATE)
    baseline_app=IsolatedBrowserApp(pointer,meta['fixture'],'stages4-7-g7-buttons-baseline')
    for name in names:
        assert hashlib.sha256((saved_run/'before'/name).read_bytes()).hexdigest()==previous['before'][name]
        shutil.copy2(saved_run/'before'/name,baseline_app.app/name)
    old=previous['before']
def fingerprint():
    body="require "+ps(ROOT/'scripts/cli_common.php')+";$p=cli_db("+ps(db)+");$r=[];foreach($p->query(\"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME\") as $t){$n=$t['TABLE_NAME'];$r[$n]=$p->query('SELECT * FROM `'.$n.'` ORDER BY 1')->fetchAll();}echo json_encode($r);"
    r=subprocess.run(['C:/xampp/php/php.exe'],input='<?php '+body,cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=90)
    assert r.returncode==0 and not r.stderr,'Read-only synthetic fingerprint failed'
    return hashlib.sha256(r.stdout.encode()).hexdigest()
before=fingerprint();checks=[];errors=[];passed=False;deployed=False
def check(value,label):
    assert value,label
    checks.append(label)
def projection(page):
    return page.locator('.correction-details').evaluate("e=>({links:[...e.querySelectorAll('a')].map(a=>[a.textContent.trim(),a.getAttribute('href')]),tables:[...e.querySelectorAll('table')].map(t=>t.innerText),coverage:[...e.querySelectorAll('.aw-coverage')].map(t=>t.innerText)})")
criteria={'account_id':str(meta['case']['bank']),'from':'2026-09-01','to':'2026-09-30','search':'','page':'1','page_size':'25'}
try:
    with sync_playwright() as pw:
        browser=pw.chromium.launch(channel='msedge',headless=True)
        contexts={};pages={};baseline={};targets={}
        for role in ['admin','management']:
            ctx=browser.new_context(viewport={'width':1366,'height':768},service_workers='block')
            ctx.route(re.compile(r'^https?://(?!(?:127\.0\.0\.1|localhost):)'),lambda r:r.abort())
            page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
            page.goto(meta['urls'][role]);contexts[role]=ctx;pages[role]=page
            base=meta['base'].replace('127.0.0.1','localhost') if role=='management' else meta['base']
            original=baseline_app.context(browser,'g7.'+role+'@example.invalid').new_page() if baseline_app else page
            original_base=baseline_app.base if baseline_app else base
            original.goto(original_base+'/general_ledger.php?'+urlencode(criteria))
            links=original.get_by_role('link',name='View transaction',exact=True)
            check(links.count()>=2,role+' ledger has transaction destinations')
            targets[role]=[links.nth(i).get_attribute('href') for i in [0,1]]
            for i,url in enumerate(targets[role]):
                original.goto(original_base+'/'+url);baseline[(role,i)]=projection(original)
        if not installed:
            for name in names:
                saved=run/'before'/name;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(app/name,saved)
                shutil.copy2(ROOT/name,app/name)
            deployed=True
            change={'reason':'G7 user requests transaction green links as buttons','before':old,'after':new,'evidence_run':str(run),'financial_data_changed':False}
            meta.setdefault('source_refinements',[]).append(change)
            meta['source_pins'].update(new);meta_path.write_text(json.dumps(meta,indent=2))
        for role,page in pages.items():
            base=meta['base'].replace('127.0.0.1','localhost') if role=='management' else meta['base']
            for i,url in enumerate(targets[role]):
                page.goto(base+'/'+url)
                check(projection(page)==baseline[(role,i)],role+str(i)+' exact link targets, tables and coverage unchanged')
                controls=page.locator('.exact-transaction a')
                check(controls.count()>=4,role+str(i)+' scoped navigation controls present')
                check(controls.evaluate_all("aa=>aa.every(a=>{let s=getComputedStyle(a);return ['inline-flex','flex'].includes(s.display)&&parseFloat(s.minHeight)>=42&&s.borderTopStyle==='solid'&&s.textDecorationLine==='none'})"),role+str(i)+' links have button shape and readable targets')
                check(controls.evaluate_all("aa=>aa.every(a=>!a.hasAttribute('role')&&!a.hasAttribute('onclick'))"),role+str(i)+' real navigation semantics retained')
                if role=='management':
                    check(page.locator('a[href^="journal_correction.php"],a[href*="item_review"],a[href*="receipt_file"]').count()==0,'Management'+str(i)+' financial authoring and private item/file controls absent')
                for width,height in [(1366,768),(1920,1080)]:
                    page.set_viewport_size({'width':width,'height':height})
                    check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),role+str(i)+' no page overflow at '+str(width))
                    page.screenshot(path=str(run/(role+'-'+str(i)+'-'+str(width)+'.png')),full_page=True)
                page.get_by_role('link',name='Back to General Ledger',exact=True).focus()
                check(page.locator(':focus').evaluate("e=>getComputedStyle(e).outlineStyle==='solid'&&parseFloat(getComputedStyle(e).outlineWidth)>=3"),role+str(i)+' keyboard focus visible')
                page.emulate_media(media='print')
                check(page.get_by_role('link',name='Back to General Ledger',exact=True,include_hidden=True).is_hidden(),role+str(i)+' contextual return control hidden in print')
                check(page.locator('.correction-navigation').evaluate("e=>getComputedStyle(e).display==='none'"),role+str(i)+' print toolbar hidden')
                check(page.locator('table').is_visible(),role+str(i)+' print financial table remains visible')
                if i==0:
                    check(page.locator('.transaction-actions').is_visible(),role+' correction identities retained in print')
                    page.pdf(path=str(run/(role+'-transaction.pdf')),format='A4',landscape=True,print_background=True)
                page.emulate_media(media='screen')
                page.get_by_role('link',name='Back to General Ledger',exact=True).click()
                check({k:v[0] for k,v in parse_qs(urlparse(page.url).query,keep_blank_values=True).items()}==criteria,role+str(i)+' return preserves complete ledger criteria')
        browser.close()
    check(not errors,'No browser script errors')
    check(fingerprint()==before,'All synthetic application tables unchanged')
    passed=True
finally:
    if baseline_app:baseline_app.close()
    result={'passed':passed,'checks':checks,'browser_errors':errors,'source_before':old,'source_after':new,'owned_walkthrough_files_updated':deployed,'working_database_used':False,'provider_called':False,'human_retest_pending':True}
    (run/'result.json').write_text(json.dumps(result,indent=2))
    print(json.dumps({'passed':passed,'checks':len(checks),'run':str(run)}))
