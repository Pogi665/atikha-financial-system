"""Run guarded Stage7 checks, retain exact stdout/stderr and production pins."""
from pathlib import Path
import hashlib,json,os,subprocess,sys
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();pp=private/'stages4-7-current.json';p=json.loads(pp.read_text());fixture=Path(p['g5_fixture']).resolve();assert fixture.is_relative_to(private);run=fixture.parent;env=os.environ.copy();env.update(p['environment'])
for name in ['test_stage7_evidence.php','test_stage7_exports.php','test_stage7_negatives.php']:
 if not (root/'scripts'/name).exists():continue
 r=subprocess.run(['C:/xampp/php/php.exe',str(root/'scripts'/name),'--fixture',str(fixture)],capture_output=True,text=True,encoding='utf-8',cwd=root,env=env,timeout=180);(run/(name+'.stdout.log')).write_text(r.stdout,encoding='utf-8');(run/(name+'.stderr.log')).write_text(r.stderr,encoding='utf-8');print(r.stdout);print(r.stderr)
 if r.returncode or r.stderr:raise SystemExit('Stage7 test failed; preserve target, do not rerun consumed scenario')
p['g5_backend_run']=str(run);pp.write_text(json.dumps(p,indent=2),encoding='utf-8')
