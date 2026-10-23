"""Fresh isolated reporting rehearsal; keep each attempt and exact outputs private."""
from pathlib import Path
import argparse,hashlib,json,os,subprocess,sys
parser=argparse.ArgumentParser();parser.add_argument('--base-only',action='store_true');args=parser.parse_args()
root=Path(__file__).resolve().parent.parent
pointer_path=root/'.migration-private/stages4-7-current.json'
r=subprocess.run([sys.executable,str(root/'scripts/create_reporting_fixture.py')],capture_output=True,text=True,encoding='utf-8',cwd=root)
if r.returncode:print(r.stdout+r.stderr);raise SystemExit(r.returncode)
pointer=json.loads(pointer_path.read_text(encoding='utf-8'));fixture=Path(pointer['g2_fixture']);run=fixture.parent
(run/'creation.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'creation.stderr.log').write_text(r.stderr,encoding='utf-8')
env=os.environ.copy();env.update(pointer['environment'])
for name in ['test_stage5_cashflows.php','test_stage5_reports.php']+([] if args.base_only else ['test_stage5_report_edges.php','test_stage5_configuration.php']):
    r=subprocess.run(['C:/xampp/php/php.exe',str(root/'scripts'/name),'--fixture',str(fixture)],capture_output=True,text=True,encoding='utf-8',env=env,cwd=root,timeout=180)
    (run/(name+'.stdout.log')).write_text(r.stdout,encoding='utf-8');(run/(name+'.stderr.log')).write_text(r.stderr,encoding='utf-8')
    expected_stderr='Audit log insert failed: Synthetic injected audit failure\n'*2 if name=='test_stage5_configuration.php' else ''
    if r.returncode or r.stderr.replace('\r\n','\n')!=expected_stderr:print(name+' FAILED\n'+r.stdout[-2500:]+'\n'+r.stderr[-1500:]);raise SystemExit(1)
    print(name+': '+r.stdout.strip().splitlines()[-1])
pins={str(p.relative_to(root)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for folder in ['includes','scripts','migrations'] for p in (root/folder).glob('*') if p.is_file() and (p.name.startswith(('report','financial_report','test_stage5','022_')) or p.name in ['reporting_common.php','stages4_7_fixture_helpers.php'])}
(run/'stage5-check-source-pins.json').write_text(json.dumps(pins,indent=2),encoding='utf-8')
if args.base_only:pointer['g2_ui_fixture']=str(fixture)
else:pointer['g2_backend_run']=str(run)
pointer_path.write_text(json.dumps(pointer,indent=2),encoding='utf-8');print('New empty disposable schema only; no working configuration or financial data changed.')
