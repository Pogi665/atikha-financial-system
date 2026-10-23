"""Serial mixed-month G6 measurements on an owned synthetic copy, never working data."""
from pathlib import Path
from urllib.parse import urlencode,parse_qs,urlparse
import argparse,csv,hashlib,io,json,re,statistics,subprocess,sys,time
from stages4_7_browser import ROOT,PRIVATE,IsolatedBrowserApp
sys.path.insert(0,str(PRIVATE/'journal-test-deps'))
from playwright.sync_api import sync_playwright
ap=argparse.ArgumentParser();ap.add_argument('--size',type=int,choices=[500,5000],required=True);args=ap.parse_args()
pp=PRIVATE/'stages4-7-current.json';pointer=json.loads(pp.read_text());assert pointer.get('g5_passed')
fixture=Path(pointer['g6_fixture']).resolve();case=json.loads(fixture.with_name('g6-workload-'+str(args.size)+'.json').read_text());assert case['passed'] and case['size']==args.size
app=IsolatedBrowserApp(pointer,fixture,'stages4-7-g6-performance-'+str(args.size));before=app.fingerprint();checks=[];timings=[];operations=[];errors=[];advisory=[];passed=False
names=subprocess.check_output(['git','diff','--name-only','-z'],cwd=ROOT).decode().split('\0')+subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z'],cwd=ROOT).decode().split('\0')
pins={n:hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in sorted(set(names)) if n and not n.startswith(('scripts/','docs/')) and Path(n).suffix in ['.php','.js','.css','.sql','.json']}
month=case['setup']['month'];criteria=case['criteria'];account=criteria['account_id'];address=app.base+'/report_bundles.php?month='+month
def check(ok,label):
 assert ok,label
 checks.append(label);print('PASS: '+label,flush=True)
def record(name,role,http,visible):
 item={'path':name,'role':role,'selected_account_lines':args.size,'http_samples_ms':http,'visible_samples_ms':visible,'http_median_ms':statistics.median(http[1:]),'http_slowest_ms':max(http),'visible_median_ms':statistics.median(visible[1:]),'visible_slowest_ms':max(visible)}
 timings.append(item);check(item['http_median_ms']<=2000 and item['visible_median_ms']<=3000,role+' '+name+' repeat HTTP/visible targets')
def validate(name,body):
 assert body['ok'],name
 d=body['data']
 if name.startswith('ledger'):assert d['period_count']==args.size
 elif name=='reports':assert d['core_complete'] and d['cash_flows']['closing_cash']==case['cash']
 elif name=='budget':assert d['actuals']['actual_total']==case['recognized_expense'] and d['actuals']['approved_total']=='24000.00'
 elif name=='bundle-live':assert d['live']['complete'] and d['live']['fingerprint']==case['source_fingerprint']
 elif name=='bundle-frozen':assert d['frozen']['stale'] and d['frozen']['payload']['components']['budget_actuals']['approved_total']!='24000.00'
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True);contexts={r:app.context(browser,email) for r,email in [('Admin','admin@example.invalid'),('Management','management@example.invalid')]}
  paths=[('ledger-first','general_ledger.php?'+urlencode(criteria),'#gl-counts'),('ledger-search-page2','general_ledger.php?'+urlencode(criteria|{'search':'G6','page':'2'}),'#gl-counts'),('reports','financial_statements.php?month='+month,'#fr-state'),('budget','budgets.php?month='+month,'#bw-state'),('bundle-live','report_bundles.php?month='+month,'#rb-state'),('bundle-frozen','report_bundles.php?bundle_id=1','#rb-state'),('dashboard','dashboard.php','.budget-status-panel'),('queue','management_reviews.php','main')]
  for role,ctx in contexts.items():
   page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));forecast_starts={};forecast_samples=[]
   def started(request):
    if request.url.endswith('/forecast_ai.php'):forecast_starts[request]=time.perf_counter()
   def finished(request):
    if request in forecast_starts:forecast_samples.append((time.perf_counter()-forecast_starts.pop(request))*1000)
   page.on('request',started);page.on('requestfinished',finished)
   for name,path,ready in paths:
    if name=='queue' and role=='Admin':continue
    http=[];visible=[]
    for iteration in range(6):
     start=time.perf_counter();response=ctx.request.get(app.base+'/'+path+('' if name in ['dashboard','queue'] else '&format=json'));http.append((time.perf_counter()-start)*1000);assert response.status==200,(role,name,response.status)
     if name not in ['dashboard','queue']:validate(name,response.json())
     start=time.perf_counter();response=page.goto(app.base+'/'+path);page.locator(ready).first.wait_for(state='visible');visible.append((time.perf_counter()-start)*1000);assert response.status==200
     # This wait is outside both timing intervals. Serialize complete background work before the next sample.
     page.wait_for_load_state('networkidle')
    record(name,role,http,visible)
    if name in ['reports','budget','bundle-frozen']:
     for width,height in [(1366,768),(1920,1080)]:
      page.set_viewport_size({'width':width,'height':height});check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),role+' '+name+' document fits '+str(width));page.screenshot(path=str(app.run/(role+'-'+name+'-'+str(width)+'.png')),full_page=True)
     page.set_viewport_size({'width':1366,'height':768})
   check(len(forecast_samples)==6,role+' six separate advisory response samples');advisory.append({'role':role,'samples_ms':forecast_samples,'repeat_median_ms':statistics.median(forecast_samples[1:]),'slowest_ms':max(forecast_samples),'scope':'Asynchronous forecast completion; separate from financial-panel visibility and ordinary HTML targets'});page.close()
  check(not errors,'No mixed-workload JavaScript errors')
  after=app.fingerprint();check(all(after[t]==h for t,h in before.items() if t!='forecast_cache'),'Navigation preserves every financial, evidence, master, audit and approval table')
  if args.size==5000:
   page=contexts['Admin'].new_page();page.goto(address);start=time.perf_counter();page.locator('#rb-submit').click();page.wait_for_url('**bundle_id=*');bundle=int(parse_qs(urlparse(page.url).query)['bundle_id'][0]);operations.append({'operation':'complete_submit','elapsed_ms':(time.perf_counter()-start)*1000});check('Requested' in page.locator('#rb-state').inner_text(),'Large mixed month submitted as frozen Requested revision');page.close()
   stored=app.sql('SELECT OCTET_LENGTH(payload) bytes,JSON_UNQUOTE(JSON_EXTRACT(payload,"$.storage_format")) format FROM complete_report_bundles WHERE id='+str(bundle))[0];check(stored['format']=='report_envelope_deflate_v1' and stored['bytes']<1048576,'Large immutable envelope retains complete manifest within existing packet limit')
   page=contexts['Management'].new_page();page.goto(app.base+'/report_bundles.php?bundle_id='+str(bundle));body=contexts['Management'].request.get(page.url+'&format=json').json()['data'];check(len(body['frozen']['payload']['manifest']['source_lines'])>5000 and not body['frozen']['stale'],'Management loads every exact frozen source line and current source comparison');start=time.perf_counter();page.locator('#rb-reviewed').click();page.wait_for_function("document.querySelector('#rb-state').textContent.includes('Reviewed')");operations.append({'operation':'management_review','elapsed_ms':(time.perf_counter()-start)*1000});page.locator('#rb-close-reason').fill('Synthetic G6 measured complete-month closure');page.locator('#rb-close-bundle').select_option(str(bundle));start=time.perf_counter();page.locator('#rb-close').click();page.locator('#rb-reopen').wait_for(state='visible');operations.append({'operation':'month_close','elapsed_ms':(time.perf_counter()-start)*1000});check(page.locator('#rb-period-state').inner_text()=='Closed','Large complete reviewed month closes through actual UI')
   for role,ctx in contexts.items():
    for label,query in [('ledger',{'kind':'ledger',**criteria}),('frozen',{'kind':'frozen','bundle_id':str(bundle),'component':'worksheet'})]:
     start=time.perf_counter();response=ctx.request.get(app.base+'/financial_export.php?'+urlencode(query));elapsed=(time.perf_counter()-start)*1000;assert response.status==200;body=response.body();rows=list(csv.reader(io.StringIO(body.decode('utf-8-sig'))));numeric=sum(1 for r in rows if label=='ledger' and len(r)>3 and re.fullmatch(r'\d{4}-\d{2}-\d{2}',r[0].lstrip("'")))
     if label=='ledger':check(numeric==5000,role+' full-result CSV includes all5000 account movements')
     operations.append({'operation':label+'_full_export','role':role,'elapsed_ms':elapsed,'bytes':len(body),'csv_rows':len(rows),'data_rows':numeric if label=='ledger' else None,'sha256':hashlib.sha256(body).hexdigest()})
   page.goto(app.base+'/report_bundles.php?bundle_id='+str(bundle)+'&component=worksheet&format=print');page.emulate_media(media='print');page.pdf(path=str(app.run/'large-frozen-worksheet.pdf'),landscape=True,prefer_css_page_size=True,print_background=True);page.close()
  browser.close();check(all(hashlib.sha256((ROOT/n).read_bytes()).hexdigest()==h for n,h in pins.items()),'Final production pins unchanged during serial measurements');passed=True
finally:
 app.close();result={'passed':passed,'checks':checks,'timings':timings,'advisory_timings':advisory,'operations':operations,'actual_counts_before':case['actual_counts'],'oracles':{'expense':case['recognized_expense'],'cash':case['cash'],'approved_budget':case['approved_budget']},'javascript_errors':errors,'source_pins':pins,'server_mode':'single PHP built-in; normal MariaDB3306; headless Edge; serial samples drain asynchronous work outside timing intervals; first+5 repeats','provider_called':False,'working_data_used':False,'human_acceptance':False};(app.run/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');pointer=json.loads(pp.read_text());pointer['g6_performance_'+str(args.size)+'_run']=str(app.run);pp.write_text(json.dumps(pointer,indent=2),encoding='utf-8');print('G6 mixed '+str(args.size)+' '+('PASS' if passed else 'FAIL')+' '+str(app.run),flush=True)
