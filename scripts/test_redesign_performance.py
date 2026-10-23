"""Serial repeat measurements after concurrent regressions; synthetic engineering evidence only."""
from pathlib import Path
from urllib.parse import urlencode
import json,os,re,secrets,statistics,subprocess,sys,time
ROOT=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(ROOT/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
from stages4_7_browser import IsolatedBrowserApp
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());old=json.loads((ROOT/'.migration-private/stages4-7-current.json').read_text());env=os.environ.copy();env.update(p['environment']);source=Path(old['g6_final_g5_fixture']);r=subprocess.run([sys.executable,'scripts/test_stage3_fixture.py','--fixture',str(source),'--database','atikha_test_stage1_s5g6rdperf'+secrets.token_hex(5)],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=180);assert r.returncode==0;fixture=re.search('Cloned fixture: (.+)',r.stdout).group(1).strip();fixture=Path(fixture);(fixture.parent/'stage5-reports-result.json').write_bytes(source.with_name('stage5-reports-result.json').read_bytes());app=IsolatedBrowserApp(p,fixture,'redesign-performance');results=[]
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(channel='msedge',headless=True)
  for size in [500,5000]:
   prep=subprocess.run(['C:/xampp/php/php.exe','scripts/test_stages4_7_workload.php','--fixture',str(fixture),'--lines',str(size)],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=900);(app.run/('prepare-'+str(size)+'.stdout.log')).write_text(prep.stdout);(app.run/('prepare-'+str(size)+'.stderr.log')).write_text(prep.stderr);assert prep.returncode==0 and not prep.stderr,'Guarded workload preparation failed; no repair';case=json.loads(fixture.with_name('g6-workload-'+str(size)+'.json').read_text());assert case['passed'];criteria=case['criteria'];month=case['setup']['month']
   for role in ['Admin','Management']:
    ctx=app.context(browser,role.lower()+'@example.invalid');page=ctx.new_page();calls=[];page.on('request',lambda r:calls.append(r.url));cid=app.sql('SELECT target_journal_id FROM journal_corrections ORDER BY id LIMIT 1')[0]['target_journal_id']
    paths=[('dashboard','dashboard.php?month='+month,'.ui-kpis'),('ledger','general_ledger.php?'+urlencode(criteria),'#gl-counts'),('reports','report_bundles.php?component=income_statement&month='+month,'#rb-state'),('correction','journal_corrections.php?journal_id='+str(cid),'.correction-comparison'),('export','financial_export.php?'+urlencode({**criteria,'kind':'ledger'}),None)]+([('queue','management_reviews.php','table')] if role=='Management' else [])
    for name,route,ready in paths:
     http=[];visible=[]
     for n in range(6):
      t=time.perf_counter();response=ctx.request.get(app.base+'/'+route);http.append((time.perf_counter()-t)*1000);assert response.status==200,(name,response.status)
      if ready:
       t=time.perf_counter();response=page.goto(app.base+'/'+route);page.locator(ready).wait_for(state='visible');visible.append((time.perf_counter()-t)*1000);assert response.status==200
       if name=='ledger':assert json.loads(page.locator('#gl-initial').text_content())['period_count']==size,'Declared workload must match the actual selected account period'
     result={'size':size,'role':role,'route':route,'path':name,'http_ms':http,'visible_ms':visible,'http_median_ms':statistics.median(http[1:]),'visible_median_ms':statistics.median(visible[1:]) if visible else None};result['passed']=result['http_median_ms']<=2000 and (not visible or result['visible_median_ms']<=3000);results.append(result);(app.run/'result.json').write_text(json.dumps({'passed':all(r['passed'] for r in results),'timings':results,'synthetic_only':True,'production_sla':False},indent=2));print(('PASS ' if result['passed'] else 'FAIL ')+str(size)+' '+role+' '+name+' '+str(round(result['http_median_ms'],1)),flush=True)
    assert not any('forecast_ai.php' in u for u in calls);ctx.close()
  browser.close()
 assert all(r['passed'] for r in results),'Investigate timing regression'
 p['performance_run']=str(app.run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2));print('RESULT '+str(app.run))
finally:app.close()
