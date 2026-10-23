"""Approved G6 guarded regression orchestration. No working migration/config/provider/commit."""
from pathlib import Path
import argparse,hashlib,json,os,re,secrets,subprocess,sys
ROOT=Path(__file__).resolve().parent.parent;PRIVATE=(ROOT/'.migration-private').resolve();PP=PRIVATE/'stages4-7-current.json'
ap=argparse.ArgumentParser();ap.add_argument('--phase',choices=['regressions','receipt','ledger','components','readback','forecast'],required=True);args=ap.parse_args();p=json.loads(PP.read_text(encoding='utf-8'));assert p.get('g5_passed') is True
env=os.environ.copy();env.update(p['environment']);env['PYTHONIOENCODING']='utf-8';source=Path(p['synthetic_source']).resolve();assert source.is_relative_to(PRIVATE);f=json.loads(source.read_text());assert re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',f['database']) and Path(f['receipt_root']).resolve().is_relative_to(PRIVATE)
changed=subprocess.check_output(['git','diff','--name-only','-z'],cwd=ROOT).decode().split('\0');new=subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z'],cwd=ROOT).decode().split('\0');files=sorted({n for n in changed+new if n and not n.startswith(('docs/','scripts/')) and (Path(n).suffix in ['.php','.js','.css','.sql'] or n=='assets/vendor/unicode-search.json')});pins={n:hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in files}
run=PRIVATE/('stages4-7-g6-'+args.phase+'-'+secrets.token_hex(5));run.mkdir();(run/'source-pins.json').write_text(json.dumps(pins,indent=2),encoding='utf-8');p['g6_'+args.phase+'_run']=str(run);PP.write_text(json.dumps(p,indent=2),encoding='utf-8')
commands=[]
if args.phase=='regressions':
 for name in ['stage2-fixture.json','stage3-checkpoint2-fixture.json','stage3-checkpoint3-fixture.json','stage3-checkpoint4-fixture.json','stage3-checkpoint5-fixture.json']:assert source.with_name(name).is_file(),name
 commands=[[sys.executable,'scripts/test_usability_u5_regressions.py','--source-fixture',str(source),'--workers','2']]
elif args.phase=='receipt':commands=[[sys.executable,'scripts/test_usability_u4_regressions.py','--source-fixture',str(source),'--only','receipt,u2c']]
elif args.phase=='readback':commands=[[sys.executable,'scripts/test_stages4_7_g6_readback.py']]
elif args.phase=='forecast':commands=[[sys.executable,'scripts/test_stages4_7_forecast_session.py'],[sys.executable,'scripts/run_stage6_checks.py'],[sys.executable,'scripts/test_stage6_browser.py']]
elif args.phase=='ledger':
 clone=subprocess.run([sys.executable,'scripts/test_stage3_fixture.py','--fixture',str(source),'--database','atikha_test_stage1_s4g1g6'+secrets.token_hex(5)],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=180);(run/'clone.stdout.log').write_text(clone.stdout,encoding='utf-8');(run/'clone.stderr.log').write_text(clone.stderr,encoding='utf-8');assert clone.returncode==0
 fresh=re.search(r'Cloned fixture: (.+)',clone.stdout).group(1).strip();commands.append(['C:/xampp/php/php.exe','scripts/test_stage4_ledger.php','--fixture',fresh])
 commands.append([sys.executable,'scripts/test_stage4_browser.py','--fixture',p['g1_fixture']])
 for size in [500,5000]:
  fixture=Path(p['g1_perf_fixture_'+str(size)]).resolve();assert fixture.is_relative_to(PRIVATE);commands.append([sys.executable,'scripts/test_stage4_browser.py','--fixture',str(fixture),'--timing',str(size)])
  commands.append(['C:/xampp/php/php.exe','scripts/test_stage4_queries.php','--fixture',str(fixture)])
elif args.phase=='components':
 commands.append(['C:/xampp/php/php.exe','scripts/test_report_bundle_storage.php'])
 for script,extra in [('run_stage5_checks.py',[]),('run_stage5_checks.py',['--base-only']),('test_stage5_browser.py',[]),('run_stage6_checks.py',[]),('test_stage6_browser.py',[]),('run_stage5_completion_checks.py',[]),('test_stage5_completion_browser.py',[]),('create_stage7_fixture.py',[]),('run_stage7_checks.py',[]),('test_stage7_browser.py',[]),('test_stages4_7_g6_readback.py',[])]:commands.append([sys.executable,'scripts/'+script]+extra)
results=[]
for index,cmd in enumerate(commands):
 with (run/(str(index)+'.stdout.log')).open('w',encoding='utf-8') as out,(run/(str(index)+'.stderr.log')).open('w',encoding='utf-8') as err:r=subprocess.run(cmd,cwd=ROOT,env=env,stdout=out,stderr=err,timeout=3600)
 results.append({'command':cmd,'exit_code':r.returncode});same=all(hashlib.sha256((ROOT/n).read_bytes()).hexdigest()==h for n,h in pins.items());result={'passed':r.returncode==0 and same,'source_pins_match':same,'commands':results,'provider_called':False,'working_data_used':False};(run/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print('G6 '+args.phase+' '+('PASS' if result['passed'] else 'FAIL')+' '+str(run),flush=True)
 if not result['passed']:raise SystemExit(1)
