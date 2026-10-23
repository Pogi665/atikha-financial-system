"""Fresh final encoder evidence/exports/browser rehearsal; never reuses consumed writer scenarios."""
from pathlib import Path
import json,os,secrets,subprocess,sys
ROOT=Path(__file__).resolve().parent.parent;pp=ROOT/'.migration-private/redesign-regression-current.json';p=json.loads(pp.read_text());env=os.environ.copy();env.update(p['environment']);env['ATIKHA_TEST_POINTER']=str(pp);env['PYTHONDONTWRITEBYTECODE']='1';env['PYTHONIOENCODING']='utf-8';run=ROOT/'.migration-private'/('redesign-export-retests-'+secrets.token_hex(5));run.mkdir();results=[]
for i,script in enumerate(['create_stage7_fixture.py','run_stage7_checks.py','test_stage7_browser.py','test_redesign_workspaces.py']):
 with (run/(str(i)+'.stdout.log')).open('w',encoding='utf-8') as out,(run/(str(i)+'.stderr.log')).open('w',encoding='utf-8') as err:r=subprocess.run([sys.executable,'-B','scripts/'+script],cwd=ROOT,env=env,stdout=out,stderr=err,timeout=1200)
 results.append({'script':script,'exit':r.returncode});(run/'result.json').write_text(json.dumps({'passed':all(x['exit']==0 for x in results),'commands':results},indent=2));print(('PASS ' if r.returncode==0 else 'FAIL ')+script+' '+str(run),flush=True)
 if r.returncode:sys.exit(1)
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());p['export_retests']=str(run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2))
