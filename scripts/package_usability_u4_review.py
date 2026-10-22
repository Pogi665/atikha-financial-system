"""Package exact U4 source only after every final suite and working-state comparison passed."""
from pathlib import Path
import argparse,datetime,difflib,hashlib,json,re,shutil,subprocess,zipfile
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();baseline='b6e9ca8f26e66820f4deeb4c4d2361e7e3cd0302'
ap=argparse.ArgumentParser();ap.add_argument('--verification-run',required=True);a=ap.parse_args();run=Path(a.verification_run).resolve();assert run.is_relative_to(private)
results=json.loads((run/'results.json').read_text());expected={'u4','u2a','u2b','u2c','reader','bcd','backend','u3a','stage2','cp2','cp3','cp4','cp5','receipt'}
assert set(results)==expected and all(r['passed'] for r in results.values()),'Every required final suite must pass.'
production=json.loads((run/'source-pins.json').read_text());sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
assert all(sha(root/n)==h for n,h in production.items()),'Production source changed after final verification.'
working=json.loads((run/'working-final.json').read_text());assert working['passed'],'Working-state comparison must pass.'
visual=json.loads((run/'visual-review.json').read_text());assert visual['passed'],'Visual review must be recorded.'
def git(*args):return subprocess.check_output(['git',*args],cwd=root)
assert git('rev-parse','HEAD').decode().strip()==baseline,'Checkpoint baseline changed.'
changed=git('diff','--name-only').decode().splitlines();tests=[n for n in changed if n.startswith('scripts/') and n.endswith(('.py','.php','.js'))]
tests+=['scripts/test_usability_u4_browser.py','scripts/test_usability_u4_fixture.php','scripts/test_usability_u4_adapter.js','scripts/test_usability_u4_regressions.py','scripts/package_usability_u4_review.py']
def patch(names):
    existing=[];new=[]
    for n in names:
        check=subprocess.run(['git','cat-file','-e',baseline+':'+n],cwd=root,capture_output=True)
        (existing if check.returncode==0 else new).append(n)
    output=git('diff','--full-index','--no-ext-diff',baseline,'--',*existing).decode('utf-8') if existing else ''
    for n in new:
        text=(root/n).read_text(encoding='utf-8');blob=git('hash-object','--path='+n,n).decode().strip()
        output+='diff --git a/'+n+' b/'+n+'\nnew file mode 100644\nindex '+'0'*40+'..'+blob+'\n'+''.join(difflib.unified_diff([],text.splitlines(keepends=True),fromfile='/dev/null',tofile='b/'+n))
    return output
docs=root/'docs';(docs/'usability-u4-production.patch').write_text(patch(list(production)),encoding='utf-8',newline='\n');(docs/'usability-u4-tests.patch').write_text(patch(tests),encoding='utf-8',newline='\n')
# Prove both patches reconstruct the final normalized source from the declared baseline.
check=run/('patch-check-'+datetime.datetime.now().strftime('%H%M%S%f'));check.mkdir()
for n in list(production)+tests:
    old=subprocess.run(['git','show',baseline+':'+n],cwd=root,capture_output=True)
    if old.returncode==0:p=check/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(old.stdout)
for name in ['usability-u4-production.patch','usability-u4-tests.patch']:
    subprocess.run(['git','apply','--directory='+str(check.relative_to(root)).replace('\\','/'),str(docs/name)],cwd=root,check=True)
for n in list(production)+tests:assert (check/n).read_bytes().replace(b'\r\n',b'\n')==(root/n).read_bytes().replace(b'\r\n',b'\n'),n
summary={'baseline':baseline,'final_suite_results':{n:{'passed':r['passed'],'affected_rerun':r.get('affected_rerun',False),'commands':[{'runner':Path(c['command'][1]).name,'exit':c['exit']} for c in r['commands']]} for n,r in results.items()},'working_comparison':working,'visual_review':visual,'patch_reconstruction_passed':True}
u4out=(run/'u4/1.stdout.log').read_text(encoding='utf-8');image_run=Path(re.search(r'U4_RUN: (.+)',u4out).group(1).strip());assert image_run.resolve().is_relative_to(private)
summary['u4_gates']=json.loads((image_run/'results.json').read_text())
(docs/'usability-u4-verification-results.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
log=[]
for n in sorted(results):
    log.append('SUITE '+n+' PASS\n')
    for p in sorted(Path(results[n].get('artifact_directory',run/n)).glob('*.stdout.log')):
        if p.name.startswith('clone'):continue
        text=p.read_text(encoding='utf-8');text=re.sub(r'C:[^\r\n]*?\.migration-private[^\r\n]*','<private isolated artifacts>',text,flags=re.I);log.append(p.name+'\n'+text+'\n')
for p in sorted((run/'offline').glob('*.stdout.log')):log.append('OFFLINE '+p.name+'\n'+p.read_text(encoding='utf-8')+'\n')
(docs/'usability-u4-verification-log.txt').write_text(''.join(log),encoding='utf-8')
dependencies=['assets/js/payment_presentation.js','assets/js/advance_presentation.js','scripts/test_usability_u2_adapter.js','scripts/test_usability_u3_advance_adapter.js','scripts/cli_common.php','scripts/http_fixture.php','scripts/test_stage3_fixture.py']
for r in results.values():
    dependencies += ['scripts/'+Path(c['command'][1]).name for c in r['commands']]
documents=['README.md','docs/project-status.md','docs/project-decisions.md','docs/usability-completion-plan.md','docs/usability-u3-bcd-approval.md','docs/usability-u4-plan.md','docs/usability-u4-delivery.md','docs/usability-u4-reviewer-notes.md','docs/usability-u4-production.patch','docs/usability-u4-tests.patch','docs/usability-u4-verification-results.json','docs/usability-u4-verification-log.txt']
files=sorted(set(list(production)+tests+dependencies+documents));images=sorted(image_run.glob('*.png'));assert images
for p in images:assert visual['screenshots'].get(p.name)==sha(p),'Screenshot not covered by final visual-review record.'
manifest={'baseline':baseline,'status':'ready for external exact-source review; not committed/pushed','production':{},'packaged_files':{n:sha(root/n) for n in files},'screenshots':{p.name:sha(p) for p in images},'dependencies':'Reconstruct runtime from the declared public baseline; pure adapter dependencies are included. Private DB/session/evidence fixtures are excluded.'}
contracts=['includes/accounting_query.php','includes/accounting_workspace.php','includes/cash_advance.php','includes/cash_advance_lifecycle.php','includes/correction_drafts.php','includes/correction_posting.php','includes/receipt_ocr.php','accounting_actions.php','cash_advance_actions.php','journal_correction_actions.php','ocr_extract.php']
manifest['unchanged_contracts']={}
for n in contracts:
    old=git('rev-parse',baseline+':'+n).decode().strip();current=git('hash-object','--path='+n,n).decode().strip();assert old==current,n
    manifest['unchanged_contracts'][n]=current
for n in production:
    old=subprocess.run(['git','rev-parse',baseline+':'+n],cwd=root,capture_output=True)
    manifest['production'][n]={'sha256':sha(root/n),'git_blob':git('hash-object','--path='+n,n).decode().strip(),'baseline_git_blob':old.stdout.decode().strip() if old.returncode==0 else None}
mf=docs/'usability-u4-source-manifest.json';mf.write_text(json.dumps(manifest,indent=2),encoding='utf-8');files.append(str(mf.relative_to(root)))
archive=docs/'usability-u4-review-package.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
    for n in files:z.write(root/n,n)
    for p in images:z.write(p,'screenshots/'+p.name)
with zipfile.ZipFile(archive) as z:
    for n,h in manifest['packaged_files'].items():assert hashlib.sha256(z.read(n)).hexdigest()==h,n
    for n,h in manifest['screenshots'].items():assert hashlib.sha256(z.read('screenshots/'+n)).hexdigest()==h,n
(docs/'usability-u4-review-package.sha256').write_text(sha(archive)+'  '+archive.name+'\n',encoding='utf-8')
print('U4 review package verified:',archive,'files',len(files),'screenshots',len(images),'sha256',sha(archive))
