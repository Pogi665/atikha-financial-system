"""Separate consumed guard-negative clone; preserve current positive fixture pointer."""
from pathlib import Path
import json,os,subprocess,sys
root=Path(__file__).resolve().parent.parent;pp=root/'.migration-private/stages4-7-current.json';saved=json.loads(pp.read_text(encoding='utf-8'));active={k:saved[k] for k in ['g4_fixture','g4_fixture_run']};r=subprocess.run([sys.executable,str(root/'scripts/create_period_fixture.py')],capture_output=True,text=True,encoding='utf-8',cwd=root,timeout=360)
if r.returncode:print(r.stdout+r.stderr);raise SystemExit(r.returncode)
p=json.loads(pp.read_text(encoding='utf-8'));f=Path(p['g4_fixture']);run=f.parent;env=os.environ.copy();env.update(p['environment']);(run/'negative-period-create.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'negative-period-create.stderr.log').write_text(r.stderr,encoding='utf-8');r=subprocess.run(['C:/xampp/php/php.exe',str(root/'scripts/test_stage5_period_schema.php'),'--fixture',str(f)],capture_output=True,text=True,encoding='utf-8',env=env,cwd=root,timeout=180);(run/'period-schema.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'period-schema.stderr.log').write_text(r.stderr,encoding='utf-8');p=json.loads(pp.read_text(encoding='utf-8'));p.update(active);p['g4_partial_fixture']=str(f);pp.write_text(json.dumps(p,indent=2),encoding='utf-8')
if r.returncode or r.stderr:print(r.stdout[-3000:]+r.stderr[-1500:]);raise SystemExit(1)
print(r.stdout.strip().splitlines()[-1]);print('Consumed new partial disposable schema only; active positive fixture preserved.')
