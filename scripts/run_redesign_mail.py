"""Fresh full-schema synthetic clone for fake Email and MFA checks."""
from pathlib import Path
import json,os,re,secrets,subprocess,sys
ROOT=Path(__file__).resolve().parent.parent;p=json.loads((ROOT/'.migration-private/redesign-current.json').read_text());env=os.environ.copy();env.update(p['environment']);env['PYTHONIOENCODING']='utf-8'
r=subprocess.run([sys.executable,'scripts/test_stage3_fixture.py','--fixture',p['fixture'],'--database','atikha_test_stage1_s5rdmail'+secrets.token_hex(5)],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=180);assert r.returncode==0;fixture=Path(re.search('Cloned fixture: (.+)',r.stdout).group(1).strip());f=json.loads(fixture.read_text());run=fixture.parent;results=[]
for i,cmd in enumerate([['C:/xampp/php/php.exe','scripts/test_external_mail.php','--database='+f['database']],[sys.executable,'scripts/test_external_mail_http.py','--database',f['database']],['C:/xampp/php/php.exe','scripts/test_mfa_flow.php','--database='+f['database']]]):
 with (run/('mail-'+str(i)+'.stdout.log')).open('w',encoding='utf-8') as out,(run/('mail-'+str(i)+'.stderr.log')).open('w',encoding='utf-8') as err:r=subprocess.run(cmd,cwd=ROOT,env=env,stdout=out,stderr=err,timeout=300)
 results.append({'command':cmd,'exit':r.returncode});(run/'redesign-mail-result.json').write_text(json.dumps({'passed':all(x['exit']==0 for x in results),'commands':results,'provider_called':False},indent=2));print(('PASS ' if r.returncode==0 else 'FAIL ')+str(i)+' '+str(run),flush=True)
 if r.returncode:sys.exit(1)
p['mail_run']=str(run);(ROOT/'.migration-private/redesign-current.json').write_text(json.dumps(p,indent=2))
