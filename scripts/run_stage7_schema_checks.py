"""Separate consumed partial025 target. Restore positive pointers even on failure."""
from pathlib import Path
import json,os,subprocess,sys
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();pp=private/'stages4-7-current.json';before=json.loads(pp.read_text());saved={k:v for k,v in before.items() if k.startswith(('g4_','g5_'))};partial=None
try:
 r=subprocess.run([sys.executable,str(root/'scripts/create_stage7_fixture.py')],capture_output=True,text=True,encoding='utf-8',cwd=root,timeout=300);assert r.returncode==0,'Fresh partial target creation failed; no recovery'
 p=json.loads(pp.read_text());partial=Path(p['g5_fixture']);assert partial.resolve().is_relative_to(private);env=os.environ.copy();env.update(p['environment']);r=subprocess.run(['C:/xampp/php/php.exe',str(root/'scripts/test_stage7_schema.php'),'--fixture',str(partial)],capture_output=True,text=True,encoding='utf-8',cwd=root,env=env,timeout=180);(partial.parent/'stage7-schema.stdout.log').write_text(r.stdout,encoding='utf-8');(partial.parent/'stage7-schema.stderr.log').write_text(r.stderr,encoding='utf-8');print(r.stdout);print(r.stderr);assert r.returncode==0 and not r.stderr,'Schema negative failed; preserve consumed target'
finally:
 p=json.loads(pp.read_text());p.update(saved)
 if partial:p['g5_partial_fixture']=str(partial)
 pp.write_text(json.dumps(p,indent=2),encoding='utf-8')
