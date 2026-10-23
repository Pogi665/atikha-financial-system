"""R4-R6 read-only synthetic visual and role checks; no working records/providers."""
from pathlib import Path
import json,sys,re,time,hashlib
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());app=IsolatedBrowserApp(p,p['fixture'],'redesign-workspaces');checks=[];screens=[]
def check(ok,label):
 assert ok,label
 checks.append(label);print('PASS '+label,flush=True)
before=app.fingerprint()
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  routes={'ledger':'general_ledger.php?account_id=27&from=2026-09-01&to=2026-09-30&search=REV-187','advances':'cash_advances.php?as_of=2026-09-30','advance-detail':'cash_advances.php?advance_id=35&as_of=2026-09-30','reports':'report_bundles.php?month=2026-09&component=income_statement','submitted':'report_bundles.php?bundle_id=1&component=income_statement','budgets':'budgets.php?month=2026-09','history':'financial_records.php?from=2026-09-01&to=2026-09-30','crb':'financial_records.php?view=crb&from=2026-09-01&to=2026-09-30','cdb':'financial_records.php?view=cdb&from=2026-09-01&to=2026-09-30','transaction':'journal_transaction.php?journal_id=187','correction':'journal_corrections.php?journal_id=187'}
  for role in ['admin','management']:
   ctx=app.context(browser,'g7.'+role+'@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
   selected={**routes,**({'setup':'accounting_setup.php','report-setup':'report_setup.php','report-setup-edit':'report_setup.php?new=1','accounts':'admin_accounts.php','users':'admin_users.php','audit':'audit_trail.php','drafts':'accounting_drafts.php','budget-draft':'budgets.php?month=2026-09&new=1'} if role=='admin' else {'queue':'management_reviews.php'})}
   for name,route in selected.items():
    t=time.perf_counter();response=page.goto(app.base+'/'+route,wait_until='networkidle');check(response.status==200 and page.locator('body').get_attribute('class').find('atikha-ui')>=0,role+' '+name+' render')
    check(not page.locator('body').inner_text().startswith('Fatal error'),role+' '+name+' no PHP render error')
    for w,h in [(1366,768),(1920,1080)]:
     page.set_viewport_size({'width':w,'height':h});check(page.evaluate('document.body.scrollWidth<=innerWidth'),role+' '+name+' fits '+str(w));file=role+'-'+name+'-'+str(w)+'.png';page.screenshot(path=str(app.run/file),full_page=True);screens.append({'file':file,'role':role,'route':route,'viewport':[w,h]})
    if name in ['transaction','history','crb','cdb']:
     controls=page.locator('.aw-header>a' if name=='transaction' else 'button.records-view')
     check(controls.count()>0,role+' '+name+' action labels present')
     for interaction in ['normal','hover','focus']:
      if interaction=='hover':controls.first.hover()
      if interaction=='focus':controls.first.focus()
      ratios=controls.evaluate_all(r'''els=>els.map(el=>{const s=getComputedStyle(el),rgb=v=>v.match(/[\d.]+/g).slice(0,3).map(Number),lum=v=>rgb(v).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4}).reduce((a,x,i)=>a+x*[.2126,.7152,.0722][i],0);let fg=lum(s.color),bg=lum(s.backgroundColor);return (Math.max(fg,bg)+.05)/(Math.min(fg,bg)+.05)})''')
      check(all(r>=4.5 for r in ratios),role+' '+name+' '+interaction+' action label contrast')
    if name=='ledger':
     check(page.locator('#gl-rows tr').count()==1 and '7 period movements' in page.locator('#gl-counts').inner_text(),role+' search matches one movement over complete period')
     check('6,250.00' in page.locator('#gl-ending').inner_text() and '10,000.00' in page.locator('#gl-opening').inner_text(),role+' full-period balances preserved')
    if name=='advances':check(page.locator('#ca-rows tr').first.locator('td').count()==9,role+' nine worksheet columns')
    if name=='reports':check(page.locator('.ui-report-tabs>a').count()==4 and page.locator('.ui-accounting-details').count()==1,role+' four primary reports and secondary accounting details')
    if name=='setup':
     page.get_by_role('tab',name='Parties',exact=True).click();check(page.locator('#setup-panel-parties').is_visible() and not page.locator('#setup-panel-projects').is_visible(),'Setup focused tabs');page.keyboard.press('ArrowRight');check(page.locator('#setup-panel-controls').is_visible(),'Setup arrow navigation')
    if name=='queue':check(page.locator('button').filter(has_text=re.compile('Approve|Mark.*Reviewed')).count()==0 and 'Board' not in page.locator('#ui-main').inner_text(),'Queue opens exact items without blind decisions/Board')
   check(not errors,role+' no workspace JavaScript errors');ctx.close()
  anon=browser.new_context();page=anon.new_page();page.goto(app.base+'/login.php');check(page.locator('#email').count()==1 and page.locator('#password').count()==1,'Authentication fields preserved');page.screenshot(path=str(app.run/'login-1366.png'),full_page=True);anon.close();browser.close()
 check(before==app.fingerprint(),'All workspace browsing leaves every synthetic table unchanged')
 (app.run/'result.json').write_text(json.dumps({'passed':True,'checks':checks,'screens':screens,'working_data_used':False},indent=2));p['workspaces_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2));print('RESULT '+str(len(checks))+' '+str(app.run))
finally:app.close()
