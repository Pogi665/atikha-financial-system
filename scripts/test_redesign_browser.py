"""Owned synthetic browser gates for Refined Client Experience. No working login/provider."""
from pathlib import Path
import argparse,json,sys,re,hashlib
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp
ap=argparse.ArgumentParser();ap.add_argument('--gate',choices=['shell'],default='shell');args=ap.parse_args()
pointer=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());app=IsolatedBrowserApp(pointer,pointer['fixture'],'redesign-'+args.gate);checks=[];captures=[]
def check(ok,label):assert ok,label;checks.append(label);print('PASS '+label,flush=True)
before=app.fingerprint()
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  for role in ['admin','management']:
   ctx=app.context(browser,'g7.'+role+'@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
   url='new_transaction.php' if role=='admin' else 'general_ledger.php?account_id=27&from=2026-09-01&to=2026-09-30'
   for w,h in [(1366,768),(1920,1080)]:
    page.set_viewport_size({'width':w,'height':h});response=page.goto(app.base+'/'+url,wait_until='networkidle');check(response.status==200,f'{role} {w} route')
    nav=page.get_by_role('navigation',name='Main navigation')
    check(nav.get_by_role('link',name='Reports',exact=True).count()==1,f'{role} {w} one Reports')
    check(nav.get_by_role('link',name='Email',exact=True).count()==1,f'{role} {w} Email')
    check(nav.locator('a[href*="board_"]').count()==0,f'{role} {w} no Board navigation')
    check(page.evaluate('document.body.scrollWidth<=innerWidth'),f'{role} {w} page fits')
    check(page.locator('body').evaluate('(e)=>getComputedStyle(e).fontSize')=='16px',f'{role} {w} body scale')
    check(page.locator('#ui-sidebar').evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(237, 244, 238)',f'{role} {w} approved sage sidebar')
    if role=='admin':
     check(nav.get_by_role('link',name='New Transaction',exact=True).count()==1,f'{w} hub navigation')
     button=nav.get_by_role('button',name='Setup',exact=True);button.click();check(button.get_attribute('aria-expanded')=='true' and nav.get_by_role('link',name='Reporting Setup',exact=True).is_visible(),f'{w} Setup disclosure')
     nav.get_by_role('button',name='Administration',exact=True).click();check(button.get_attribute('aria-expanded')=='false',f'{w} one group expanded')
     check(page.locator('.ui-task').count()==4,f'{w} gated hub choices without unavailable Scan')
     for route,label,description in [
      ('cash_disbursement.php','Record a Payment','Record cash paid to a supplier, partner, or other payee.'),
      ('cash_receipt.php','Record Money Received','Record cash received from a donor, partner, or other source.')]:
      task=page.locator('.ui-task[href="'+route+'"]')
      check(task.locator('strong').inner_text()==label and task.locator('p').inner_text()==description,f'{w} neutral cash-movement copy and unchanged task route '+label)
      check(task.locator('p').is_visible(),f'{w} task description visible '+label)
    else:check(nav.locator('[data-ui-nav-group]').count()==0,f'{w} Management has no author/setup groups')
    name=f'{role}-{w}-{args.gate}.png';page.screenshot(path=str(app.run/name),full_page=True);captures.append(name)
   check(not errors,role+' no JavaScript errors')
   if role=='management':check(page.goto(app.base+'/new_transaction.php').status==403,'Direct Management hub denied')
   ctx.close()
  browser.close()
 check(before==app.fingerprint(),'Read-only shell leaves all synthetic tables unchanged')
 result={'passed':True,'gate':args.gate,'checks':checks,'screenshots':captures,'provider_called':False,'working_data_used':False}
 (app.run/'result.json').write_text(json.dumps(result,indent=2));pointer[args.gate+'_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(pointer,indent=2));print('RESULT '+str(len(checks))+' checks '+str(app.run))
finally:app.close()
