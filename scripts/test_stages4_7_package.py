"""Offline positive/tamper tests for the consultation verifier; no app/database calls."""
from pathlib import Path
import argparse,hashlib,json,secrets,subprocess,sys,zipfile
from verify_stages4_7_package import verify
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve()
ap=argparse.ArgumentParser();ap.add_argument('archive');args=ap.parse_args()
archive=Path(args.archive).resolve();assert archive.is_relative_to(root)
run=private/('stages4-7-package-tests-'+secrets.token_hex(5));run.mkdir();checks=[]
with zipfile.ZipFile(archive) as z:original={n:z.read(n) for n in z.namelist()}
assert verify(archive)['passed'];checks.append('Valid archive passes manifest and forward/reverse reconstruction')
(run/'verifier.py').write_bytes(original['verifier.py'])
r=subprocess.run([sys.executable,str(run/'verifier.py'),str(archive)],cwd=run,capture_output=True,text=True,timeout=90)
assert r.returncode==0 and json.loads(r.stdout)['passed'];checks.append('Packaged standalone verifier runs outside repository working directory')
for case in ['content-tamper','patch-tamper-with-updated-manifest','baseline-blob-tamper-with-updated-manifest','unsafe-path']:
    payload=original.copy();manifest=json.loads(payload['manifest.json'])
    if case=='content-tamper':payload['source/general_ledger.php']+=b'\nCHANGED\n'
    elif case=='unsafe-path':payload['../escape.txt']=b'guard test, never extracted'
    else:
        name='patches/production.patch' if case.startswith('patch-') else next(n for n in payload if n.startswith('baseline/'))
        payload[name]=b'not a valid patch\n' if name.endswith('.patch') else payload[name]+b'\nCHANGED\n'
        manifest['members'][name]=hashlib.sha256(payload[name]).hexdigest()
        payload['manifest.json']=json.dumps(manifest).encode()
    target=run/(case+'.zip')
    with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as z:
        for name,data in payload.items():z.writestr(name,data)
    rejected=False
    try:verify(target)
    except AssertionError:rejected=True
    assert rejected,'Tampered archive accepted: '+case
    checks.append('Rejected '+case)
result={'passed':True,'checks':checks,'offline_only':True,'database_or_application_called':False,'archive_sha256':hashlib.sha256(archive.read_bytes()).hexdigest()}
(run/'result.json').write_text(json.dumps(result,indent=2))
print(json.dumps(result|{'run':str(run)},indent=2))
