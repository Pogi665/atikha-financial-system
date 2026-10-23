"""R7 fresh disposable regressions, separate pointer and retained original evidence."""
from pathlib import Path
import argparse,json,os,secrets,subprocess,sys
ROOT=Path(__file__).resolve().parent.parent;private=ROOT/'.migration-private';ap=argparse.ArgumentParser();ap.add_argument('--suite',choices=['prepare','ordinary','components'],required=True);ap.add_argument('--only',default='all');a=ap.parse_args();r=json.loads((private/'redesign-current.json').read_text());pp=private/'redesign-regression-current.json'
if a.suite=='prepare':
 assert not pp.exists(),'Preserve prepared regression run'
 p=json.loads((private/'stages4-7-current.json').read_text());p['environment']=r['environment'];pp.write_text(json.dumps(p,indent=2));print('Separate regression pointer prepared; original unchanged');sys.exit()
p=json.loads(pp.read_text());env=os.environ.copy();env.update(r['environment']);env['ATIKHA_TEST_POINTER']=str(pp);env['PYTHONIOENCODING']='utf-8';run=private/('redesign-regressions-'+a.suite+'-'+secrets.token_hex(5));run.mkdir();outcomes=[]
commands=([[sys.executable,'scripts/test_usability_u5_regressions.py','--source-fixture',p['synthetic_source'],'--workers','2','--only',a.only]] if a.suite=='ordinary' else [[sys.executable,'scripts/'+name]+extra for name,extra in [('run_stage5_checks.py',[]),('run_stage5_checks.py',['--base-only']),('run_stage6_checks.py',[]),('run_stage5_completion_checks.py',[]),('create_stage7_fixture.py',[]),('run_stage7_checks.py',[])]])
for i,cmd in enumerate(commands):
 with (run/(str(i)+'.stdout.log')).open('w',encoding='utf-8') as out,(run/(str(i)+'.stderr.log')).open('w',encoding='utf-8') as err:result=subprocess.run(cmd,cwd=ROOT,env=env,stdout=out,stderr=err,timeout=3600)
 outcomes.append({'command':cmd,'exit_code':result.returncode});(run/'result.json').write_text(json.dumps({'passed':all(x['exit_code']==0 for x in outcomes),'commands':outcomes},indent=2));print(('PASS ' if result.returncode==0 else 'FAIL ')+str(cmd)+' '+str(run),flush=True)
 if result.returncode:sys.exit(1)
r=json.loads((private/'redesign-current.json').read_text());r[a.suite+'_regressions']=str(run);(private/'redesign-current.json').write_text(json.dumps(r,indent=2))
