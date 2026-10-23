from pathlib import Path
import hashlib,json,re,shutil,sys,secrets
from urllib.parse import urlencode
root=Path(__file__).resolve().parent.parent;private=root/'.migration-private'
sys.path.insert(0,str(private/'journal-test-deps'))
from playwright.sync_api import sync_playwright
pointer=json.loads((private/'stages4-7-current.json').read_text());mp=Path(pointer['g7_metadata']).resolve()
assert mp.is_relative_to(private)
meta=json.loads(mp.read_text());app=Path(meta['app']).resolve();assert app.is_relative_to(private)
run=private/('stages4-7-g7-ledger-buttons-'+secrets.token_hex(5));run.mkdir()
names=['general_ledger.php','assets/css/general_ledger.css']
old={n:hashlib.sha256((app/n).read_bytes()).hexdigest() for n in names};new={n:hashlib.sha256((root/n).read_bytes()).hexdigest() for n in names}
assert all(old[n]==meta['source_pins'][n] for n in names)
installed=old==new
if installed:old=meta['source_refinements'][-1]['before']
criteria={'account_id':str(meta['case']['bank']),'from':'2026-09-01','to':'2026-09-30','search':'REV-187','page':'1','page_size':'25'}
checks=[];passed=False
def check(ok,label):
    assert ok,label
    checks.append(label)
def model(ctx,url):
    r=ctx.request.get(url+'&format=json');assert r.ok
    d=r.json()['data'];d.pop('generated_at',None);return d
with sync_playwright() as pw:
    browser=pw.chromium.launch(channel='msedge',headless=True)
    try:
        contexts={};baseline={}
        for role in ['admin','management']:
            ctx=browser.new_context(viewport={'width':1366,'height':768},service_workers='block')
            ctx.route(re.compile(r'^https?://(?!(?:127\.0\.0\.1|localhost):)'),lambda r:r.abort())
            page=ctx.new_page();page.goto(meta['urls'][role]);contexts[role]=ctx
            base=meta['base'].replace('127.0.0.1','localhost') if role=='management' else meta['base']
            url=base+'/general_ledger.php?'+urlencode(criteria);baseline[role]=(url,model(ctx,url))
        if not installed:
            for n in names:
                saved=run/'before'/n;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(app/n,saved);shutil.copy2(root/n,app/n)
            meta['source_refinements'].append({'reason':'G7 user requests ledger Reports/Previous/Next buttons','before':old,'after':new,'evidence_run':str(run),'financial_data_changed':False});meta['source_pins'].update(new);mp.write_text(json.dumps(meta,indent=2))
        for role,ctx in contexts.items():
            url,data=baseline[role];check(model(ctx,url)==data,role+' complete ledger model unchanged')
            page=ctx.new_page();page.goto(url)
            check(page.locator('.gl-nav-button').get_attribute('href')=='reports.php',role+' Reports destination unchanged')
            links=page.locator('.gl-nav-button,.gl-page')
            check(links.count()==3,role+' three requested navigation buttons')
            check(links.evaluate_all("aa=>aa.every(a=>{let s=getComputedStyle(a);return ['flex','inline-flex'].includes(s.display)&&parseFloat(s.minHeight)>=42&&s.borderTopStyle==='solid'&&s.textDecorationLine==='none'})"),role+' button shape and target height')
            for control in ['gl-prev','gl-next']:
                a=page.locator('#'+control);check(a.get_attribute('aria-disabled')=='true',role+' '+control+' disabled on one page')
                before=page.url;a.focus();page.keyboard.press('Enter');page.wait_for_timeout(200)
                check(page.url==before,role+' '+control+' disabled keyboard activation does not navigate')
            page.locator('.gl-nav-button').focus()
            check(page.locator(':focus').evaluate("e=>getComputedStyle(e).outlineStyle==='solid'"),role+' visible keyboard focus')
            for width,height in [(1366,768),(1920,1080)]:
                page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),role+' no document overflow '+str(width))
                page.screenshot(path=str(run/(role+'-'+str(width)+'.png')),full_page=True)
            page.emulate_media(media='print')
            check(page.locator('.gl-nav-button').is_hidden() and page.locator('.gl-pagination').is_hidden(),role+' navigation hidden in print')
            check(page.locator('#gl-ending').is_visible(),role+' balance retained in print')
            page.emulate_media(media='screen')
            page.locator('.gl-nav-button').click();page.wait_for_url('**/reports.php')
            check(page.locator('body').inner_text().find('Reports')>=0,role+' Reports navigation opens authorized hub')
        passed=True
    finally:
        browser.close()
        result={'passed':passed,'checks':checks,'source_before':old,'source_after':new,'classification':'G7 scoped ledger navigation presentation check; human retest pending','working_database_used':False,'financial_write':False}
        (run/'result.json').write_text(json.dumps(result,indent=2));print(json.dumps({'passed':passed,'checks':len(checks),'run':str(run)}))
