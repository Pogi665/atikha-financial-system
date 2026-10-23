"""Fresh budget rehearsal with retained per-attempt logs, never working financial data."""
from pathlib import Path
import hashlib,json,os,subprocess,sys
root=Path(__file__).resolve().parent.parent;pointer_path=root/'.migration-private/stages4-7-current.json'
r=subprocess.run([sys.executable,str(root/'scripts/create_budget_fixture.py')],capture_output=True,text=True,encoding='utf-8',cwd=root,timeout=300)
if r.returncode:print(r.stdout+r.stderr);raise SystemExit(r.returncode)
p=json.loads(pointer_path.read_text(encoding='utf-8'));f=Path(p['g3_fixture']);run=f.parent;env=os.environ.copy();env.update(p['environment']);(run/'partial-create.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'partial-create.stderr.log').write_text(r.stderr,encoding='utf-8')
r=subprocess.run(['C:/xampp/php/php.exe',str(root/'scripts/test_stage6_schema.php'),'--fixture',str(f)],capture_output=True,text=True,encoding='utf-8',env=env,cwd=root,timeout=120)
(run/'stage6-schema.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'stage6-schema.stderr.log').write_text(r.stderr,encoding='utf-8')
if r.returncode or r.stderr:print(r.stdout[-4000:]+r.stderr[-2000:]);raise SystemExit(1)
p['g3_partial_fixture']=str(f);pointer_path.write_text(json.dumps(p,indent=2),encoding='utf-8');print(r.stdout.strip().splitlines()[-1])
r=subprocess.run([sys.executable,str(root/'scripts/create_budget_fixture.py')],capture_output=True,text=True,encoding='utf-8',cwd=root,timeout=300)
if r.returncode:print(r.stdout+r.stderr);raise SystemExit(r.returncode)
p=json.loads(pointer_path.read_text(encoding='utf-8'));f=Path(p['g3_fixture']);run=f.parent;env=os.environ.copy();env.update(p['environment']);(run/'budget-create.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'budget-create.stderr.log').write_text(r.stderr,encoding='utf-8')
for name in ['budgets','read_models','integrity']:
 r=subprocess.run(['C:/xampp/php/php.exe',str(root/('scripts/test_stage6_'+name+'.php')),'--fixture',str(f)],capture_output=True,text=True,encoding='utf-8',env=env,cwd=root,timeout=180)
 (run/('stage6-'+name+'.stdout.log')).write_text(r.stdout,encoding='utf-8');(run/('stage6-'+name+'.stderr.log')).write_text(r.stderr,encoding='utf-8')
 if r.returncode or r.stderr:print(r.stdout[-4000:]+r.stderr[-2000:]);raise SystemExit(1)
 print(r.stdout.strip().splitlines()[-1])
names=['includes/budget_revisions.php','includes/budget_actuals.php','includes/financial_report_query.php','includes/dashboard_reporting.php','includes/budget_query.php','includes/forecast_query.php','migrations/023_monthly_budget_revisions.sql','scripts/test_stage6_budgets.php','scripts/test_stage6_read_models.php','scripts/test_stage6_integrity.php'];pins={name:hashlib.sha256((root/name).read_bytes()).hexdigest() for name in names};(run/'stage6-backend-source-pins.json').write_text(json.dumps(pins,indent=2),encoding='utf-8');p['g3_backend_run']=str(run);pointer_path.write_text(json.dumps(p,indent=2),encoding='utf-8');print('Synthetic fixture only; no working deployment.')
