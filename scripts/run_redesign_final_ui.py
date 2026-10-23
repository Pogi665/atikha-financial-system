"""Serial final-source affected browser captures, with raw per-suite evidence."""
from pathlib import Path
import json,secrets,subprocess,sys
ROOT=Path(__file__).resolve().parent.parent;run=ROOT/'.migration-private'/('redesign-final-ui-'+secrets.token_hex(5));run.mkdir();outcomes=[]
for script in ['run_redesign_checks.py','test_redesign_browser.py','test_redesign_dashboard.py','test_redesign_daily.py','test_redesign_workspaces.py','test_redesign_accessibility.py']:
 with (run/(script+'.stdout.log')).open('w',encoding='utf-8') as out,(run/(script+'.stderr.log')).open('w',encoding='utf-8') as err:r=subprocess.run([sys.executable,'-B','scripts/'+script],cwd=ROOT,stdout=out,stderr=err,timeout=1200)
 outcomes.append({'script':script,'exit':r.returncode});(run/'result.json').write_text(json.dumps({'passed':all(v['exit']==0 for v in outcomes),'suites':outcomes},indent=2));print(('PASS ' if r.returncode==0 else 'FAIL ')+script+' '+str(run),flush=True)
 if r.returncode:sys.exit(1)
p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());p['final_ui_run']=str(run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2))
