"""Database-free template/JS/print checks. Not authorization or financial testing.
Renders only the actual view portion with explicit synthetic data; all browser
requests are intercepted. Full PHP/auth/database integration is a separate suite.
"""
from pathlib import Path
import argparse,copy,json,re,secrets,shutil,subprocess,sys
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--browser',default='msedge');args=parser.parse_args()
run=root/'.migration-private'/('u3a-presentation-'+secrets.token_hex(5));run.mkdir();php=r'C:\xampp\php\php.exe'
(run/'assets/js').mkdir(parents=True);shutil.copy2(root/'assets/js/cash_advances.js',run/'assets/js/cash_advances.js')
today='2026-10-20';recon=[{'account_id':22,'account_name':'Synthetic employee advance control','register_balance':'10000.00','ledger_balance':'10000.00','difference':'0.00','ok':True,'errors':[]}]
record={'id':1,'number':'CA-0000000001','employee':'Synthetic employee with a long label for field and training activities','purpose':'Synthetic outreach purpose <training & community> with a deliberately long readable description.','project':'Synthetic community project with a long display name','release_date':'2026-10-17','due_date':today,'status':'outstanding','outstanding':'10000.00','released':'10000.00','liquidated':'0.00','returned':'0.00','overdue_days':0,'aging_bucket':'Current','party_id':2,'originating_project_id':2,'control_account_id':22}
result={'as_of':today,'rows':[record],'totals':{k:record[k] for k in ['released','liquidated','returned','outstanding']},'reconciliation':recon,'count':1,'page':1,'pages':1,'restatement_notice':'Historical balances include all currently recorded postings through the selected accounting date.'}
detail={**record,'as_of':today,'reference':'','original_released':'10000.00','lifecycle_date':None,'original_advance_id':None,'replacement_advance_id':None,'can_settle':True,'external_approval':None,'initial_due_date':today,'due_changes':[],'reconciliation':recon,'operations':[{'kind':'release','date':'2026-10-17','journal_id':10,'is_reversal':False,'correction_id':None,'amount':'10000.00','coverage':{'status':'Optional proof not supplied'},'recorded_at_display':'2026-10-17 10:00:00','reference':'','lines':[{'account_name':'Synthetic advance control','project_name_snapshot':record['project'],'debit_amount':'10000.00','credit_amount':'0.00'},{'account_name':'Synthetic bank','project_name_snapshot':record['project'],'debit_amount':'0.00','credit_amount':'10000.00'}]}]}
source=(root/'cash_advances.php').read_text(encoding='utf-8');view=source.split('?>',1)[1];formatters=source[source.index('$esc='):source.index('layout_begin(')]
template=run/'view.php';template.write_text("<?php function csrf_token(){return 'synthetic-token';}function stage3_enabled($pdo){return true;}function journal_today(){return '2026-10-20';}function layout_end($s){echo $s;}$pdo=null;$v=json_decode(stream_get_contents(STDIN),true);extract($v);"+formatters+'?>'+view,encoding='utf-8')
def html(role='Admin',data=None,empty=False):
    data=copy.deepcopy(data)
    if role=='Management' and data is not None:data['can_settle']=False;data.pop('external_approval',None)
    variables={'role':role,'detail':data,'asof':data['as_of'] if data else today,'id':data['id'] if data else None,'options':[] if empty else [record],'controlNames':{'22':recon[0]['account_name']}}
    response=subprocess.run([php,str(template)],input=json.dumps(variables),capture_output=True,text=True,encoding='utf-8',check=True);assert not response.stderr,response.stderr
    return '<!doctype html><html><head><base href="http://127.0.0.1:9/"><link rel="stylesheet" href="assets/css/accounting_workspace.css"><style>body{margin:0;background:#f1f5f9}main{margin-left:256px;padding:30px}*{box-sizing:border-box}p{margin:8px 0}h1,h2,h3{margin:0}</style></head><body><main>'+response.stdout+'</main></body></html>'
checks=0;errors=[];response_data=copy.deepcopy(result);response_mode='success';pending=[]
def check(ok,label):
    global checks
    if not ok:raise AssertionError(label)
    checks+=1;print('PASS: '+label,flush=True)
with sync_playwright() as p:
    browser=p.chromium.launch(channel=args.browser,headless=True);c=browser.new_context();page=c.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
    def route(request):
        path=request.request.url.split('127.0.0.1:9/',1)[-1].split('?',1)[0]
        if path=='assets/css/accounting_workspace.css':request.fulfill(content_type='text/css',body=(root/path).read_text(encoding='utf-8'))
        elif path=='assets/js/cash_advances.js':request.fulfill(content_type='application/javascript',body=(root/path).read_text(encoding='utf-8'))
        elif path=='cash_advance_actions.php':
            if response_mode=='pending':pending.append(request)
            elif response_mode=='failure':request.fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"Synthetic unavailable check"}')
            else:request.fulfill(content_type='application/json',body=json.dumps({'ok':True,'result':response_data}))
        else:request.fulfill(status=403,body='Blocked presentation test request')
    c.route('**/*',route)
    def render(role='Admin',data=None,empty=False,wait=True):
        page.set_content(html(role,data,empty),wait_until='networkidle' if wait else 'domcontentloaded')
        if data is None and wait:page.wait_for_function("document.getElementById('ca-status').textContent.includes('loaded')")
    def captures(label):
        for w,h in [(1366,768),(1920,1080)]:
            page.set_viewport_size({'width':w,'height':h});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),label+' fits '+str(w));page.screenshot(path=str(run/(label+'-'+str(w)+'.png')),full_page=True)
    render();check('PHP 10,000.00' in page.locator('#ca-totals').inner_text(),'Exact amounts format with separators');check('<training & community>' in page.locator('#ca-rows').inner_text() and page.locator('#ca-rows script').count()==0,'Purpose text is escaped');check(page.locator('#ca-reconciliation-details').get_attribute('open') is None,'Full reconciliation starts secondary');captures('register')
    page.locator('#ca-health-link').click();check(page.locator('#ca-reconciliation-details').get_attribute('open') is not None and page.locator('#ca-reconciliation-details summary').evaluate('e=>e===document.activeElement'),'Health shortcut opens and focuses full reconciliation')
    response_data=copy.deepcopy(result);response_data['reconciliation'][0]['ok']=False;response_data['reconciliation'][0]['difference']='1.00';response_data['reconciliation'][0]['errors']=['Synthetic excluded-account discrepancy'];render();check('Integrity discrepancy' in page.locator('#ca-health-message').inner_text(),'Discrepancy stays prominent outside disclosure');captures('discrepancy')
    warning_snapshot=page.locator('#ca-health').get_attribute('class'),page.locator('#ca-health-message').inner_text(),page.locator('#ca-reconciliation').text_content()
    response_mode='failure';page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-error').textContent.length>0")
    check(warning_snapshot==(page.locator('#ca-health').get_attribute('class'),page.locator('#ca-health-message').inner_text(),page.locator('#ca-reconciliation').text_content()) and page.locator('#ca-health-link').is_visible(),'Failed discrepancy refresh retains verified warning/details and link')
    response_mode='pending';render(wait=False);page.wait_for_function("document.getElementById('ca-rows').getAttribute('aria-busy')==='true'")
    check(page.locator('#ca-health').get_attribute('data-state')=='loading' and page.locator('#ca-health').evaluate("e=>getComputedStyle(e).backgroundColor")== 'rgb(248, 250, 252)','Initial loading has a distinct neutral health state')
    check(page.locator('#ca-health-link').is_hidden() and page.locator('#ca-reconciliation-details').is_hidden(),'Loading offers no unverified full-reconciliation details')
    pending.pop().fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"Synthetic unavailable check"}');page.wait_for_function("document.getElementById('ca-health').dataset.state==='unavailable'")
    check(page.locator('#ca-health').evaluate("e=>getComputedStyle(e).backgroundColor")== 'rgb(241, 245, 249)' and 'not verified' in page.locator('#ca-health-message').inner_text() and not page.locator('#ca-health').evaluate("e=>e.classList.contains('ca-health-warning')"),'Initial failure is unavailable, distinct from healthy and discrepancy')
    check(page.locator('#ca-print').is_disabled() and page.locator('#ca-health-link').is_hidden() and page.locator('#ca-reconciliation-details').is_hidden() and 'No successful' in page.locator('#ca-reconciliation-basis').text_content(),'Initial failure disables printing and hides empty reconciliation details')
    page.locator('#ca-health-link').evaluate('e=>e.click()');check(page.locator('#ca-reconciliation-details').get_attribute('open') is None,'Unavailable shortcut cannot open an empty disclosure');captures('first-load-unavailable')
    response_mode='success';response_data=copy.deepcopy(result);response_data['reconciliation']=[{'ok':False,'errors':[]}];render(wait=False);page.wait_for_function("document.getElementById('ca-health').dataset.state==='unavailable'")
    check(page.locator('#ca-health-link').is_hidden() and page.locator('#ca-reconciliation-details').is_hidden(),'Unverifiable initial response also leaves reconciliation unavailable')
    response_data=copy.deepcopy(result);page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-health').dataset.state==='healthy'")
    check(page.locator('#ca-health-link').is_visible() and page.locator('#ca-reconciliation-details').is_visible() and not page.locator('#ca-print').is_disabled(),'Verified retry restores healthy styling, details and printing')
    healthy_snapshot=page.locator('#ca-health').get_attribute('class'),page.locator('#ca-health-message').inner_text(),page.locator('#ca-reconciliation').text_content()
    response_mode='failure';page.locator('#ca-filters button').click();page.wait_for_function("document.getElementById('ca-error').textContent.length>0")
    check(healthy_snapshot==(page.locator('#ca-health').get_attribute('class'),page.locator('#ca-health-message').inner_text(),page.locator('#ca-reconciliation').text_content()) and page.locator('#ca-health-link').is_visible(),'Later failure retains the successful healthy reconciliation snapshot');response_mode='success'
    response_data=copy.deepcopy(result);render();page.emulate_media(media='print');page.wait_for_function("[...document.querySelectorAll('details[data-ca-print]')].every(d=>d.open)");check(page.locator('#ca-filters').is_hidden() and page.locator('#ca-print-scope').is_visible(),'Print retains applied scope and hides filters');captures('register-print');page.emulate_media(media='screen');page.wait_for_function("!document.getElementById('ca-reconciliation-details').open");check(page.locator('#ca-reconciliation-details').get_attribute('open') is None,'Print restores prior disclosures')
    response_data={**result,'rows':[],'count':0,'reconciliation':[],'totals':{k:'0.00' for k in result['totals']}};render(empty=True);check('No posted advances yet' in page.locator('#ca-rows').inner_text(),'Empty-register guidance differs from filtering');captures('empty')
    render();check('No posted advances match' in page.locator('#ca-rows').inner_text(),'No-match guidance retains filter context')
    response_data=copy.deepcopy(result);render(data=detail);check(page.get_by_role('link',name='Record liquidation',exact=True).count()==1,'Current eligible detail exposes linked liquidation');check(page.locator('#ca-extension-panel').get_attribute('open') is None,'Extension starts secondary');captures('detail')
    page.emulate_media(media='print');page.wait_for_function("[...document.querySelectorAll('details[data-ca-print]')].every(d=>d.open)");check(page.locator('.ca-journal table').is_visible() and page.locator('#ca-extension').is_hidden(),'Detail print exposes full journal and hides extension');captures('detail-print');page.emulate_media(media='screen')
    historical=copy.deepcopy(detail);historical['as_of']='2026-10-18';historical['can_settle']=False;render(data=historical);check('Historical view' in page.locator('.ca-date-basis').inner_text() and page.get_by_role('link',name="View today's advance").count()==1,'Historical date remains explicit');check(page.get_by_role('link',name='Record liquidation',exact=True).count()==0,'Historical template excludes settlement actions');captures('historical')
    render(role='Management',data=detail);check(page.locator('a[href="cash_advance_entry.php?workflow_kind=advance_release"]').count()==0 and page.locator('#ca-extension').count()==0,'Management template excludes mutation controls');captures('management-detail')
    check(not errors,'Presentation checks have no uncaught JavaScript errors');(run/'results.json').write_text(json.dumps({'checks':checks,'database_access':False,'authorization_test':False,'browser':browser.version,'page_errors':errors,'screenshots':sorted(p.name for p in run.glob('*.png'))},indent=2));browser.close()
print('Presentation checks: '+str(checks));print('Private artifacts: '+str(run))
