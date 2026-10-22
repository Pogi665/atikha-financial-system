"""Final-byte U2 follow-up affected suites. Each financial suite gets a new guarded synthetic clone.
Requires an existing private complete-schema synthetic source and disabled PHP transports.
Never loads working configuration or bootstraps/migrates the working database.
"""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor,as_completed
import argparse,ast,hashlib,json,os,re,secrets,subprocess,sys
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve()
ap=argparse.ArgumentParser();ap.add_argument('--source-fixture',required=True);ap.add_argument('--only',default='all');a=ap.parse_args()
source=Path(a.source_fixture).resolve();assert source.is_relative_to(private)
metadata=json.loads(source.read_text());assert re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',metadata['database'])
assert Path(metadata['receipt_root']).resolve().is_relative_to(private)
env=os.environ.copy();env.pop('ATIKHA_TEST_INLINE_ROUTINES',None);env['PYTHONIOENCODING']='utf-8';env['ATIKHA_FOLLOWUP_BASELINE']='65de5c8c8b91a62083cc7244145db255b575ae88';php='C:/xampp/php/php.exe'
guard=json.loads(subprocess.check_output([php],input='<?php echo json_encode(["url"=>ini_get("allow_url_fopen"),"disabled"=>ini_get("disable_functions")]);',text=True,env=env))
assert guard['url'] in ['','0'] and all(n in guard['disabled'].split(',') for n in ['curl_exec','stream_socket_client','fsockopen','mail']), 'Disable server transports before testing.'
production=['assets/js/accounting_workspace.js','assets/js/payment_presentation.js']
run=private/('u2-followup-final-'+secrets.token_hex(5));run.mkdir();pins={n:hashlib.sha256((root/n).read_bytes()).hexdigest() for n in production};(run/'source-pins.json').write_text(json.dumps(pins,indent=2))
(private/'u2-followup-final-current.json').write_text(json.dumps({'run':str(run)}))
suites={
 'followup':('test_usability_u2_followup_browser.py','stage3-checkpoint5-fixture.json','u4fu'),
 'u2a':('test_usability_u2_browser.py','stage3-checkpoint5-fixture.json','u4reg'),
 'u2b':('test_usability_u2_b_browser.py','stage3-checkpoint5-fixture.json','u4reg'),
 'u4c':('test_usability_u4_browser.py','stage3-checkpoint5-fixture.json','u4fu'),
 'u4d':('test_usability_u4_browser.py','stage3-checkpoint5-fixture.json','u4fu'),
 'stage2':('test_stage2_browser.py','stage2-fixture.json','u4reg'),
 'cp4':('test_stage3_checkpoint4_browser.py','stage3-checkpoint4-fixture.json','u4reg'),
 'cp5':('test_stage3_checkpoint5_browser.py','stage3-checkpoint5-fixture.json','u4reg'),
 'receipt':('test_receipt_browser.py','stage3-checkpoint5-fixture.json','u4receipt')}

chosen=list(suites) if a.only=='all' else a.only.split(',');assert all(n in suites for n in chosen)
def command(cmd,folder,label,timeout=900):
    with (folder/(label+'.stdout.log')).open('w',encoding='utf-8') as out,(folder/(label+'.stderr.log')).open('w',encoding='utf-8') as err:
        r=subprocess.run(cmd,env=env,cwd=root,stdout=out,stderr=err,timeout=timeout)
    return {'command':cmd,'exit':r.returncode}
offline=run/'offline';offline.mkdir();offline_results=[]
for n in production:
    if n.endswith('.php'):offline_results.append(command([php,'-l',str(root/n)],offline,n.replace('/','_')))
    if n.endswith('.js'):offline_results.append(command(['node','--check',str(root/n)],offline,n.replace('/','_')))
for n in ['test_usability_u2_followup_adapter.js','test_usability_u4_adapter.js','test_usability_u2_adapter.js','test_usability_u3_advance_adapter.js']:
    offline_results.append(command(['node',str(root/'scripts'/n)],offline,n))
for n in ['test_usability_u2_followup_browser.py','test_usability_u2_followup_regressions.py','test_usability_u4_browser.py','test_receipt_browser.py','test_stage3_checkpoint2_browser.py','test_stage3_checkpoint3_browser.py','test_stage3_checkpoint4_browser.py','test_usability_u2_browser.py','test_usability_u2_b_browser.py']:
    ast.parse((root/'scripts'/n).read_text(encoding='utf-8'))
assert all(r['exit']==0 for r in offline_results), 'Offline checks failed; inspect private logs.'
(run/'offline-results.json').write_text(json.dumps(offline_results,indent=2))
def execute(name):
    script,fixture_name,prefix=suites[name];folder=run/name;folder.mkdir();db='atikha_test_stage1_'+prefix+secrets.token_hex(5)
    clone=command([sys.executable,str(root/'scripts/test_stage3_fixture.py'),'--fixture='+str(source.with_name(fixture_name)),'--database='+db],folder,'clone',180)
    if clone['exit']:return {'passed':False,'stage':'clone','command':clone}
    fixture=re.search(r'Cloned fixture: (.+)',(folder/'clone.stdout.log').read_text()).group(1).strip();commands=[]
    if name in ['u4c','u4d']:commands.append([php,str(root/'scripts/test_usability_u4_fixture.php'),'--fixture='+fixture])
    if name=='bcd':commands.append([php,str(root/'scripts/test_usability_u3_bcd.php'),'--fixture='+fixture,'--gate=b'])
    commands.append(([sys.executable] if script.endswith('.py') else [php])+[str(root/'scripts'/script),'--fixture='+fixture]+(['--gate='+name[-1]] if name in ['u4c','u4d'] else []))
    if name=='u3a':commands.append([sys.executable,str(root/'scripts/test_usability_u3_a_readback.py'),'--fixture='+fixture])
    evidence=[]
    for i,cmd in enumerate(commands):
        evidence.append(command(cmd,folder,str(i),1800))
        if evidence[-1]['exit']:break
    same=all(hashlib.sha256((root/n).read_bytes()).hexdigest()==h for n,h in pins.items())
    result={'passed':evidence[-1]['exit']==0 and same,'source_pins_match':same,'fixture':fixture,'commands':evidence};(folder/'result.json').write_text(json.dumps(result,indent=2));return result
results={}
with ThreadPoolExecutor(max_workers=3) as pool:
    jobs={pool.submit(execute,n):n for n in chosen}
    for job in as_completed(jobs):
        n=jobs[job]
        try:results[n]=job.result()
        except BaseException as e:results[n]={'passed':False,'exception':repr(e)}
        (run/'results.json').write_text(json.dumps(results,indent=2));print('U2_FOLLOWUP_FINAL '+n+' '+('PASS' if results[n]['passed'] else 'FAIL')+' '+str(run/n),flush=True)
print('U2_FOLLOWUP_FINAL_RUN: '+str(run),flush=True)
sys.exit(0 if all(r['passed'] for r in results.values()) else 1)
