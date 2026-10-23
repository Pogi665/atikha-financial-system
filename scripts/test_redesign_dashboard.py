"""R2 synthetic dashboard/archive/Email browser checks. No provider or working data."""
from pathlib import Path
import json,sys,re
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp,ps
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());app=IsolatedBrowserApp(p,p['fixture'],'redesign-r2');checks=[];screens=[]
def check(ok,label):assert ok,label;checks.append(label);print('PASS '+label,flush=True)
# Permit the existing Email page, but retain runtime provider/network disables and endpoint blocks.
(app.run/'router.php').write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|forecast_ai|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|send_email)~',$p)){http_response_code(403);exit;}return false;")
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  for role in ['admin','management']:
   ctx=app.context(browser,'g7.'+role+'@example.invalid');ctx.unroute_all();ctx.route('**/*',lambda route:route.continue_() if route.request.url.startswith(app.base) or re.match(r'https://cdn\.jsdelivr\.net/',route.request.url) else route.abort());page=ctx.new_page();requests=[];errors=[];page.on('request',lambda r:requests.append(r.url));page.on('pageerror',lambda e:errors.append(str(e)))
   for w,h in [(1366,768),(1920,1080)]:
    page.set_viewport_size({'width':w,'height':h});response=page.goto(app.base+'/dashboard.php?month=2026-09',wait_until='networkidle');check(response.status==200 and page.locator('#ui-main').count()==1,f'{role} {w} dashboard renders')
    check(page.locator('.ui-kpi').count()==6,f'{role} {w} six KPIs')
    check('₱6,250.00' in page.locator('.ui-kpi-cash').inner_text(),f'{role} {w} configured cash')
    check(page.locator('body').inner_text().find('Forecast')==-1 and not any('forecast_ai.php' in url for url in requests),f'{role} {w} no forecasting UI/request')
    check(page.get_by_role('alert').count()==0,f'{role} {w} no unhandled projection failure')
    check(page.evaluate('document.body.scrollWidth<=innerWidth'),f'{role} {w} dashboard fits')
    if role=='admin':check('₱7,000.00' in page.locator('.ui-kpis').inner_text() and '₱11,900.00' in page.locator('.ui-kpis').inner_text(),f'{w} gross cash-side values')
    else:check('-₱3,850.00' in page.locator('.ui-kpis').inner_text() or '₱-3,850.00' in page.locator('.ui-kpis').inner_text(),f'{w} signed net deficit')
    help=page.get_by_role('button',name='Cash Balance basis') if False else page.locator('.ui-kpi-cash summary');help.focus();page.keyboard.press('Enter');check('not a live bank-feed' in page.locator('.ui-kpi-cash details[open]').inner_text(),f'{role} {w} basis help keyboard accessible');page.keyboard.press('Enter')
    name=role+'-'+str(w)+'-dashboard.png';page.screenshot(path=str(app.run/name),full_page=True);screens.append(name)
   route='board_messages.php' if role=='admin' else 'board_inbox.php';page.goto(app.base+'/'+route,wait_until='networkidle');check(page.get_by_role('heading',name='Legacy Communications Archive',exact=True).count()==1,role+' Board archive reader')
   before=app.fingerprint();r=ctx.request.post(app.base+'/'+route,multipart={'subject':'must not persist','message_body':'retired','attachment':{'name':'synthetic.txt','mimeType':'text/plain','buffer':b'synthetic archive test'}});check(r.status==410,role+' retired POST rejected');check(before==app.fingerprint(),role+' retired POST leaves all records unchanged')
   page.goto(app.base+'/external_email.php',wait_until='networkidle');check(page.get_by_role('heading',name='Email',exact=True).count()==1,role+' Email heading');check(page.get_by_role('link',name='New Email',exact=True).count()==1,role+' New Email visible');check(page.get_by_role('link',name=re.compile('Delivery Issues')).count()==0,role+' zero issues secondary destination hidden');check(page.get_by_role('link',name='Legacy Communications Archive',exact=True).count()==1,role+' quiet archive access')
   check(not errors,role+' no browser JavaScript errors');ctx.close()
  browser.close()
 (app.run/'result.json').write_text(json.dumps({'passed':True,'checks':checks,'screens':screens,'provider_called':False,'working_data_used':False},indent=2));p['r2_browser_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2));print('RESULT '+str(len(checks))+' R2 checks '+str(app.run))
finally:app.close()
