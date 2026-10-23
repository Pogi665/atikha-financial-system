"""Final syntax/diff inventory without executing application writers or loading configuration."""
from pathlib import Path
import ast,hashlib,json,secrets,subprocess
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();pp=private/'stages4-7-current.json';p=json.loads(pp.read_text());run=private/('stages4-7-g6-syntax-'+secrets.token_hex(5));run.mkdir()
names=subprocess.check_output(['git','diff','--name-only','-z'],cwd=root).decode().split('\0')+subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z'],cwd=root).decode().split('\0');names=sorted({n for n in names if n and (root/n).is_file() and '__pycache__' not in Path(n).parts and Path(n).suffix not in ['.pyc','.pyo']});results=[]
for n in names:
 suffix=Path(n).suffix
 if suffix=='.py':ast.parse((root/n).read_text(encoding='utf-8-sig'));results.append({'file':n,'check':'Python AST','passed':True});continue
 cmd=['C:/xampp/php/php.exe','-l',n] if suffix=='.php' else ['node','--check',n] if suffix=='.js' else None
 if cmd:
  r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,encoding='utf-8');results.append({'file':n,'check':cmd[0],'passed':r.returncode==0});(run/(n.replace('/','_')+'.log')).write_text(r.stdout+r.stderr,encoding='utf-8');assert r.returncode==0,'Syntax failure; inspect private log'
r=subprocess.run(['git','diff','--check'],cwd=root,capture_output=True,text=True,encoding='utf-8');(run/'diff-check.log').write_text(r.stdout+r.stderr,encoding='utf-8');assert r.returncode==0
pins={n:hashlib.sha256((root/n).read_bytes()).hexdigest() for n in names if (not n.startswith(('scripts/','docs/')) and Path(n).suffix in ['.php','.js','.css','.sql','.json'])};tests={n:hashlib.sha256((root/n).read_bytes()).hexdigest() for n in names if n.startswith('scripts/')};out={'passed':True,'checks':results,'production_sha256':pins,'test_sha256':tests,'git_diff_check':True,'baseline':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip()};(run/'result.json').write_text(json.dumps(out,indent=2),encoding='utf-8');p['g6_syntax_run']=str(run);pp.write_text(json.dumps(p,indent=2),encoding='utf-8');print(json.dumps({'passed':True,'syntax_checks':len(results),'production_pins':len(pins),'test_pins':len(tests),'diff_check':True}))
