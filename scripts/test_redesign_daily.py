"""R3 presentation and acknowledged-save checks in isolated synthetic app."""
from pathlib import Path
import json,sys
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());app=IsolatedBrowserApp(p,p['fixture'],'redesign-r3');checks=[]
def check(ok,label):
 assert ok,label
 checks.append(label);print('PASS '+label,flush=True)
def choose(page,id,value):
 control=page.locator(id);label=control.locator('option[value="'+value+'"]').text_content();combo=control.locator('..').locator('.aw-record-selector');combo.get_by_role('combobox').fill(label);combo.locator('[role="option"][data-value="'+value+'"]').click()
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True);ctx=app.context(browser,'g7.admin@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  for route in ['cash_disbursement.php','cash_receipt.php','general_journal.php','cash_advance_entry.php?workflow_kind=advance_release','journal_correction.php?journal_id=193']:
   page.goto(app.base+'/'+route,wait_until='networkidle');page.wait_for_function("document.querySelector('#aw-existing')&&!document.querySelector('#aw-existing').disabled")
   check(page.locator('#aw-save').inner_text()=='Save and close',route+' single save contract')
   check(page.get_by_role('button',name='Save draft',exact=True).count()==0,route+' no duplicate Save Draft')
   check(page.locator('#aw-entry #aw-upload').count()==1,route+' supporting documents in task flow')
   check(page.locator('#aw-progress li').all_inner_texts()==['1 Details','2 Review','3 Recorded'],route+' stages')
   if page.locator('#aw-more-options').count():
    check(not page.locator('#aw-more-options').get_attribute('open'),route+' advanced modes secondary')
   for w,h in [(1366,768),(1920,1080)]:
    page.set_viewport_size({'width':w,'height':h});check(page.evaluate('document.body.scrollWidth<=innerWidth'),route+' fits '+str(w));page.screenshot(path=str(app.run/(route.split('.')[0]+'-'+str(w)+'.png')),full_page=True)
  page.goto(app.base+'/cash_disbursement.php',wait_until='networkidle');page.wait_for_function("!document.querySelector('#aw-existing').disabled")
  page.locator('#aw-purpose').fill('Synthetic redesign acknowledged save');choose(page,'#aw-party',''+str(p['g7_case']['employee']) if 'employee' in p['g7_case'] else '1') if False else None
  # Drop the save response before it reaches the server; the populated form must remain.
  page.route('**/accounting_actions.php',lambda route:route.abort() if route.request.method=='POST' and route.request.post_data_json.get('action')=='save' else route.continue_())
  page.locator('#aw-save').click();page.wait_for_function("document.querySelector('#aw-error').textContent.length>0")
  check(page.url.endswith('/cash_disbursement.php') and page.locator('#aw-purpose').input_value()=='Synthetic redesign acknowledged save','Failed save retains form and route')
  page.unroute('**/accounting_actions.php');page.locator('#aw-save').click();page.wait_for_url('**/accounting_drafts.php');check('My drafts' in page.locator('h1').inner_text() or 'My Drafts' in page.locator('h1').inner_text(),'Acknowledged save leaves for private drafts')
  mg=app.context(browser,'g7.management@example.invalid');r=mg.request.get(app.base+'/cash_disbursement.php');check(r.status==403,'Management cannot author');mg.close()
  check(not errors,'No daily workflow JavaScript errors');browser.close()
 (app.run/'result.json').write_text(json.dumps({'passed':True,'checks':checks,'working_data_used':False},indent=2));p['r3_browser_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2));print('RESULT '+str(len(checks))+' '+str(app.run))
finally:app.close()
