"""Focused R7 keyboard/semantic/reflow/print checks. ARIA snapshots are not NVDA certification."""
from pathlib import Path
import json,sys
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());app=IsolatedBrowserApp(p,p['fixture'],'redesign-accessibility');checks=[];screens=[]
def check(ok,label):
 assert ok,label
 checks.append(label);print('PASS '+label,flush=True)
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True);ctx=app.context(browser,'g7.admin@example.invalid');page=ctx.new_page();page.set_viewport_size({'width':1366,'height':768})
  page.goto(app.base+'/cash_disbursement.php',wait_until='networkidle');page.wait_for_function("!document.querySelector('#aw-existing').disabled")
  check(page.locator('#aw-review').bounding_box()['y']+page.locator('#aw-review').bounding_box()['height']<=768,'Sticky task footer keeps Review in the laptop viewport')
  page.locator('#aw-review').click();page.wait_for_selector('#aw-error a');check(page.locator('#aw-error a').count()>0 and page.locator('[aria-invalid=true]').count()>0,'Validation summary has linked invalid fields')
  page.locator('#aw-error a').first.click();check(page.locator(':focus').get_attribute('id') is not None,'Summary moves keyboard focus to invalid control')
  page.locator('[data-new-master=parties]').click();check(page.get_by_role('dialog',name='New party',exact=False).is_visible(),'Native dialog has an accessible title');page.keyboard.press('Escape');check(page.locator(':focus').get_attribute('data-new-master')=='parties','Escape closes dialog and restores opener focus')
  page.goto(app.base+'/admin_accounts.php?new=1',wait_until='networkidle');check(page.get_by_role('dialog').first.is_visible(),'Account editor opens as a labelled dialog');page.keyboard.press('Escape');check(page.locator('#add-account-button').evaluate('(e)=>e===document.activeElement'),'Account editor Escape restores Add Account focus')
  page.goto(app.base+'/admin_accounts.php',wait_until='networkidle');page.locator('.account-action-menu-button').first.click();check(page.locator('.account-action-menu:not(.hidden) .account-edit-button').is_visible(),'One row Actions menu contains Edit');page.keyboard.press('Escape')
  page.goto(app.base+'/accounting_setup.php',wait_until='networkidle');page.get_by_role('tab',name='Projects',exact=True).focus();page.keyboard.press('End');check(page.get_by_role('tab',name='Advance Accounts').get_attribute('aria-selected')=='true','Setup tab End key selects final panel')
  for name,route in [('ledger','general_ledger.php?account_id=27&from=2026-09-01&to=2026-09-30'),('reports','report_bundles.php?month=2026-09&component=income_statement'),('advances','cash_advances.php?as_of=2026-09-30'),('payment','cash_disbursement.php'),('setup','accounting_setup.php')]:
   page.goto(app.base+'/'+route,wait_until='networkidle');page.set_viewport_size({'width':683,'height':768});check(page.evaluate('document.body.scrollWidth<=innerWidth'),name+' 200-percent-equivalent viewport reflow');page.screenshot(path=str(app.run/(name+'-reflow-683.png')),full_page=True);page.set_viewport_size({'width':1366,'height':768})
   (app.run/(name+'-aria.txt')).write_text(page.locator('body').aria_snapshot(),encoding='utf-8')
   check(page.get_by_role('main').count()==1,name+' one main landmark')
  page.goto(app.base+'/general_ledger.php?account_id=27&from=2026-09-01&to=2026-09-30',wait_until='networkidle');region=page.locator('.gl-table-wrap');region.focus();page.keyboard.press('ArrowRight');page.wait_for_timeout(250);check(region.evaluate('(e)=>e.scrollLeft')>0,'Financial table scrolls with keyboard')
  page.emulate_media(reduced_motion='reduce');check(page.locator('body').evaluate('(e)=>getComputedStyle(e).animationName')=='none','Reduced motion removes decorative animation')
  for name,route in [('ledger','general_ledger.php?account_id=27&from=2026-09-01&to=2026-09-30&format=print'),('reports','report_bundles.php?bundle_id=1&component=all&format=print'),('advance','cash_advances.php?advance_id=35&as_of=2026-09-30'),('correction','journal_corrections.php?journal_id=187'),('budget','budgets.php?month=2026-09&format=print')]:
   page.goto(app.base+'/'+route,wait_until='networkidle');page.emulate_media(media='print');check(page.locator('#ui-sidebar').is_hidden(),name+' print hides navigation');page.pdf(path=str(app.run/(name+'-print.pdf')),landscape=True,print_background=True,prefer_css_page_size=True);page.screenshot(path=str(app.run/(name+'-print.png')),full_page=True);page.emulate_media(media='screen')
  mg=app.context(browser,'g7.management@example.invalid');mp=mg.new_page();mp.goto(app.base+'/management_reviews.php',wait_until='networkidle');(app.run/'management-queue-aria.txt').write_text(mp.locator('body').aria_snapshot(),encoding='utf-8');check(mp.get_by_role('table').count()==1,'Management queue exposes one decision list')
  for route in ['admin_accounts.php','admin_users.php','audit_trail.php','accounting_setup.php','report_setup.php','new_transaction.php']:
   check(mg.request.get(app.base+'/'+route).status==403,'Management direct author/setup URL denied '+route)
  browser.close()
 (app.run/'result.json').write_text(json.dumps({'passed':True,'checks':checks,'real_screen_reader_tested':False,'zoom_method':'683 CSS-pixel reflow equivalent; physical browser zoom unperformed','working_data_used':False},indent=2));p['accessibility_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2));print('RESULT '+str(len(checks))+' '+str(app.run))
finally:app.close()
