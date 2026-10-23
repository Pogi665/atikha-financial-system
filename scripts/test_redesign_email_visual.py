"""Read-only Email state and archive screenshots using the existing fake-transport fixture."""
from pathlib import Path
import json,re,sys
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());app=IsolatedBrowserApp(p,Path(p['mail_run'])/'stage7-fixture.json','redesign-email-visual');checks=[];screens=[]
(app.run/'router.php').write_text("<?php if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;} $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|send_email)~',$p)){http_response_code(403);exit;}return false;")
def check(ok,label):
 assert ok,label
 checks.append(label);print('PASS '+label,flush=True)
before=app.fingerprint()
try:
 rows=app.sql('SELECT Send_Status,MIN(CommunicationID) id FROM External_Communications GROUP BY Send_Status');states={r['Send_Status']:r['id'] for r in rows}
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  for role in ['Admin','Management']:
   ctx=app.context(browser,role.lower()+'@example.invalid');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
   paths={'compose':'external_email.php?view=compose','sent':'external_email.php?folder=sent','issues':'external_email.php?folder=issues','archive':'board_messages.php' if role=='Admin' else 'board_inbox.php'}
   paths.update({state.lower():'external_email.php?folder='+('sent' if state=='Sent' else 'issues')+'&id='+str(id) for state,id in states.items()})
   for state,route in paths.items():
    response=page.goto(app.base+'/'+route,wait_until='networkidle');check(response.status==200,role+' '+state+' authorized read')
    body=page.locator('#ui-main').inner_text()
    if state=='unknown':check('Unconfirmed' in body and 'check' in body.lower(),'Unconfirmed delivery retains check-before-resend guidance')
    if state=='sending':check('Sending' in body,'Sending remains distinct')
    if state=='failed':check('Failed' in body,'Failed remains distinct')
    if state=='compose':check(page.locator('[data-send-button]').is_disabled() and page.locator('[data-send-button]').evaluate('(el)=>getComputedStyle(el).backgroundColor')=='rgb(229, 234, 230)','Unavailable SMTP keeps Send visibly disabled');check(page.get_by_role('button',name='Send Email',exact=False).count()==1,'Explicit Send Email remains separate from financial review')
    for w,h in [(1366,768),(1920,1080)]:
     page.set_viewport_size({'width':w,'height':h});check(page.evaluate('document.body.scrollWidth<=innerWidth'),role+' '+state+' fits '+str(w));
     if state=='compose':check(page.locator('[data-send-button]').bounding_box()['y']+page.locator('[data-send-button]').bounding_box()['height']<=h,role+' compose Send action stays in desktop viewport')
     name=role.lower()+'-'+state+'-'+str(w)+'.png';page.screenshot(path=str(app.run/name),full_page=True);screens.append({'file':name,'role':role,'route':route,'viewport':[w,h]})
   check(not errors,role+' no email JavaScript errors');ctx.close()
  browser.close()
 check(before==app.fingerprint(),'Email/archive visual reads preserve every synthetic table')
 (app.run/'result.json').write_text(json.dumps({'passed':True,'checks':checks,'screens':screens,'provider_called':False},indent=2));p['email_visual_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2));print('RESULT '+str(app.run))
finally:app.close()
