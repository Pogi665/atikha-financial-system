"""Rehearse stage browsers on separate fresh clones, preserving prior machine/human fixtures."""
from pathlib import Path
import argparse,json,os,re,secrets,subprocess,sys
ROOT=Path(__file__).resolve().parent.parent;private=ROOT/'.migration-private';pp=private/'redesign-regression-current.json';p=json.loads(pp.read_text());env=os.environ.copy();env.update(p['environment']);env['ATIKHA_TEST_POINTER']=str(pp);env['PYTHONIOENCODING']='utf-8';ap=argparse.ArgumentParser();ap.add_argument('--only',default='5,6,7,completion,4');a=ap.parse_args();run=private/('redesign-stage-browsers-'+secrets.token_hex(5));run.mkdir();results=[]
for name in a.only.split(','):
 key,script={'4':('g1_fixture','test_stage4_browser.py'),'5':('g2_ui_fixture','test_stage5_browser.py'),'6':('g3_fixture','test_stage6_browser.py'),'completion':('g4_fixture','test_stage5_completion_browser.py'),'7':('g5_fixture','test_stage7_browser.py')}[name]
 source=Path(p[key]);r=subprocess.run([sys.executable,'scripts/test_stage3_fixture.py','--fixture',str(source),'--database',('atikha_test_stage1_s4g1rd' if name=='4' else 'atikha_test_stage1_s5rdb')+secrets.token_hex(5)],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=180);assert r.returncode==0;target=Path(re.search('Cloned fixture: (.+)',r.stdout).group(1).strip());old=json.loads(source.read_text())['database'];new=json.loads(target.read_text())['database']
 for meta in source.parent.glob('*result.json'):
  data=meta.read_text(encoding='utf-8').replace(old,new);(target.parent/meta.name).write_text(data,encoding='utf-8')
 current=json.loads(pp.read_text());current[key]=str(target);pp.write_text(json.dumps(current,indent=2))
 cmd=[sys.executable,'scripts/'+script]+(['--fixture',str(target)] if name=='4' else [])
 with (run/(name+'.stdout.log')).open('w',encoding='utf-8') as out,(run/(name+'.stderr.log')).open('w',encoding='utf-8') as err:r=subprocess.run(cmd,cwd=ROOT,env=env,stdout=out,stderr=err,timeout=1800)
 results.append({'name':name,'command':cmd,'exit':r.returncode});(run/'result.json').write_text(json.dumps({'passed':all(x['exit']==0 for x in results),'commands':results},indent=2));print(('PASS ' if r.returncode==0 else 'FAIL ')+name+' '+str(run),flush=True)
 if r.returncode:sys.exit(1)
r=json.loads((private/'redesign-current.json').read_text());r['stage_browser_run']=str(run);(private/'redesign-current.json').write_text(json.dumps(r,indent=2))
