"""Private deterministic public implementation fingerprint; excludes local secrets/data."""
import hashlib,json,secrets,subprocess
from pathlib import Path
root=Path(__file__).resolve().parent.parent
paths=[p for p in root.glob('*.php') if p.name not in ['config.php','db_connect.php']]
for folder in ['includes','assets','migrations','scripts']:
    paths += [p for p in (root/folder).rglob('*') if p.is_file() and '__pycache__' not in p.parts and p.suffix in ['.php','.js','.css','.py','.sql','.json']]
paths += [root/n for n in ['composer.json','composer.lock','package.json','package-lock.json'] if (root/n).exists()]
manifest={p.relative_to(root).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(set(paths))}
encoded=json.dumps(manifest,sort_keys=True,separators=(',',':')).encode();fingerprint=hashlib.sha256(encoded).hexdigest()
out=root/'.migration-private'/('cp5-source-'+secrets.token_hex(5)+'.json')
out.write_text(json.dumps({'head':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),'fingerprint':fingerprint,'files':manifest},sort_keys=True),encoding='utf-8')
print('Source fingerprint:',fingerprint);print('Source manifest:',out)
