"""Build an INTERIM consultation archive; cannot declare G7/G8 complete or deploy anything."""
from pathlib import Path
import csv,datetime,difflib,hashlib,json,re,subprocess,sys,zipfile
from verify_stages4_7_package import verify,canonical,blob
ROOT=Path(__file__).resolve().parent.parent;PRIVATE=ROOT/'.migration-private'
BASE='60206a7047d99975a29245e236ae786d16e31bc7'
def sha(b):return hashlib.sha256(b).hexdigest()
def git(*a):return subprocess.check_output(['git',*a],cwd=ROOT)
def clean(s):
    s=s.replace(str(ROOT),'<repository>').replace(str(ROOT).replace('\\','/'),'<repository>')
    s=re.sub(r'C:[^\r\n"<>]*?\.migration-private[^\r\n"<>]*','<private isolated artifact>',s,flags=re.I)
    s=re.sub(r'atikha_test_[a-z0-9_]+','<guarded synthetic database>',s)
    return s
def safe_json(data):
    if isinstance(data,dict):return {k:safe_json(v) for k,v in data.items() if k.lower() not in ['environment','password','session','sessions','cookies','csrf','csrf_token','access_token','api_key','urls']}
    if isinstance(data,list):return [safe_json(v) for v in data]
    return clean(data) if isinstance(data,str) else data

assert git('rev-parse','HEAD').decode().strip()==BASE and git('rev-parse','origin/main').decode().strip()==BASE
pointer=json.loads((PRIVATE/'stages4-7-current.json').read_text())
human=json.loads((ROOT/'docs/stages4-7-human-observations.json').read_text())
assert human['paused_at_user_request'] and human['human_walkthrough_complete'] is False
syntax=json.loads((Path(pointer['g6_syntax_run'])/'result.json').read_text())
assert syntax['passed'] and syntax['git_diff_check']
production=syntax['production_sha256'];tests={n:h for n,h in syntax['test_sha256'].items() if Path(n).suffix in ['.php','.js','.py','.ps1','.sql','.json']}
for n,h in (production|tests).items():assert sha((ROOT/n).read_bytes())==h,'Source changed after syntax inventory: '+n
refinements=json.loads((ROOT/'docs/stages4-7-g7-refinements.json').read_text())
expected=set(refinements['source_sha256'])|set(refinements['ledger_navigation']['source_sha256'])
prior=pointer['g6_source_sha256']
actual={n for n,h in production.items() if prior.get(n)!=h}
assert actual==expected,'Unexpected production drift beyond recorded G7 changes'
preservation=PRIVATE/'stages4-7-a165dc4e7b25/preservation-g7-paused-consultation.json'
preserved=json.loads(preservation.read_text());assert preserved['passed'] and preserved['compared']
assert sha((ROOT/'docs/stages4-7-plan-review-package.zip').read_bytes())=='15481ad0175ec09af4837c4a73c92a4d0d0e6e84af311d590f790583494c9ffb'
assert sha((ROOT/'docs/usability-u-perf-01-u5-review-package.zip').read_bytes())=='8e66278fa16095fd9be07cafcde5cac7a5ae788c980cd4f2cdf7c603b9eece99'
files={}
def add(n,b):
    assert n not in files,'Duplicate destination: '+n
    files[n]=b
def add_local(p,n=None):add(n or str(p.relative_to(ROOT)).replace('\\','/'),p.read_bytes())
tracked=git('ls-files','-z').decode().split('\0')
# Source-only application closure; no local connection/configuration or data files.
runtime=set(production)
for n in tracked:
    p=Path(n)
    if not n or not (ROOT/n).is_file():continue
    if ((len(p.parts)==1 and p.suffix=='.php') or n.startswith(('includes/','assets/'))) and p.suffix in ['.php','.js','.css','.json'] and p.name not in ['config.php','db_connect.php']:runtime.add(n)
for p in (ROOT/'vendor').rglob('*'):
    if p.is_file() and (p.suffix in ['.php','.json'] or p.name in ['LICENSE','LICENSE.md','LICENSE.txt','COPYING','NOTICE']) and not any(x in ['test','tests','examples','language'] for x in p.parts):runtime.add(str(p.relative_to(ROOT)).replace('\\','/'))
runtime.add('assets/vendor/README.md')
for n in ['composer.json','composer.lock','package.json','package-lock.json','tailwind.config.js']:
    if (ROOT/n).is_file():runtime.add(n)
test_files=set(tests)
for n in tracked:
    if n.startswith('scripts/test_') and Path(n).suffix in ['.py','.php','.js','.ps1','.sql','.json']:test_files.add(n)
for n in ['scripts/cli_common.php','scripts/http_fixture.php','scripts/database_phase2b_probe.php','scripts/browser_workspace_helpers.py']:
    if (ROOT/n).is_file():test_files.add(n)
for n in sorted(runtime|test_files):add_local(ROOT/n,'source/'+n)
docs=set()
for pattern in ['stage4-*','stage5-*','stage6-*','stage7-*','stages4-7-*']:
    docs.update(p for p in (ROOT/'docs').glob(pattern) if p.is_file() and p.suffix in ['.md','.json','.csv','.txt'])
docs.update(ROOT/n for n in ['README.md','docs/project-status.md','docs/project-decisions.md','docs/client-context.md','docs/usability-completion-plan.md'])
for p in sorted(docs):add_local(p)
add_local(ROOT/'docs/stages4-7-consultation.md','README-REVIEW.md')
add_local(ROOT/'scripts/verify_stages4_7_package.py','verifier.py')
changes={}
def patch(names):
    result=''
    for n in sorted(names):
        raw=(ROOT/n).read_bytes();final=canonical(raw)
        old=subprocess.run(['git','show',BASE+':'+n],cwd=ROOT,capture_output=True)
        prior_bytes=old.stdout if old.returncode==0 else None
        changes[n]={'kind':'production' if n in production else 'test/tool','raw_sha256':sha(raw),'canonical_sha256':sha(final),'final_git_blob':blob(final),'baseline_git_blob':blob(prior_bytes) if prior_bytes is not None else None}
        if prior_bytes is not None:
            add('baseline/'+n,prior_bytes)
            result+=git('diff','--full-index','--no-ext-diff',BASE,'--',n).decode('utf-8')
        else:
            text=final.decode('utf-8')
            result+='diff --git a/'+n+' b/'+n+'\nnew file mode 100644\nindex '+'0'*40+'..'+blob(final)+'\n'
            diff=list(difflib.unified_diff([],text.splitlines(keepends=True),fromfile='/dev/null',tofile='b/'+n))
            result+=''.join(line if line.endswith('\n') else line+'\n\\ No newline at end of file\n' for line in diff)
    return result.encode()
add('patches/production.patch',patch(production));add('patches/tests.patch',patch(tests))
add('evidence/current-syntax-source.json',json.dumps(safe_json(syntax),indent=2).encode())
add('evidence/working-preservation.json',json.dumps(preserved,indent=2).encode())
# Whitelist structured engineering outputs; never recursively ship private directories.
for key in ['g6_performance_500_run','g6_performance_5000_run']:
    p=Path(pointer[key]);r=json.loads((p/'result.json').read_text())
    add('evidence/'+key+'.json',json.dumps(safe_json(r),indent=2).encode())
    for f in p.glob('*.png'):
        if 'worksheet' in f.name or 'budget' in f.name:add('screenshots/g6/'+key+'/'+f.name,f.read_bytes())
    for f in p.glob('*.pdf'):add('print/g6/'+key+'/'+f.name,f.read_bytes())
for dirname in ['stages4-7-g7-buttons-2bcfd6e9ab','stages4-7-g7-ledger-buttons-62df3c5eda','stages4-browser-c51d3a04a8']:
    p=PRIVATE/dirname;r=json.loads((p/'result.json').read_text());assert r['passed']
    add('evidence/'+dirname+'.json',json.dumps(safe_json(r),indent=2).encode())
    for f in p.glob('*.png'):
        if not '-page-' in f.name:add('screenshots/g7/'+dirname+'/'+f.name,f.read_bytes())
    for f in p.glob('*.pdf'):add('print/g7/'+dirname+'/'+f.name,f.read_bytes())
checks={'baseline':BASE,'classification':'interim consultation; G7 paused; not final G8','source_identity_local_and_cached_remote':True,'live_remote_refresh_this_preparation':False,'syntax_checks':len(syntax['checks']),'production_files':len(production),'test_tool_files':len(tests),'G6_production_drift_exactly_four_recorded_G7_presentation_files':sorted(actual),'working_preservation':preserved,'approved_archives_unchanged':True,'G7_paused':True,'human_walkthrough_complete':False,'working_rollout':False,'provider_calls':False,'commit_or_push':False,'historical_evidence_boundary':'G6 runs retain their own pre-G7 hashes. Affected G7 checks supplement, not relabel, those runs.'}
add('evidence/consultation-checks.json',json.dumps(checks,indent=2).encode())
# Prevent accidental inclusion of authentication artifacts or private configuration values.
for n,b in files.items():
    assert not any(x in Path(n).parts for x in ['.migration-private','__pycache__','uploads','sessions'])
    if n.startswith('evidence/'):
        assert b'PHPSESSID=' not in b and b'"environment"' not in b and b'"password"' not in b,'Sensitive engineering payload'
manifest={'classification':checks['classification'],'baseline':BASE,'prepared_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'human_walkthrough_complete':False,'working_rollout':False,'members':{n:sha(b) for n,b in sorted(files.items())},'changed_source':changes,'dependencies':'Source-only runtime and test helpers included; working config, databases, sessions and uploaded originals excluded. This is not an installation bundle.'}
archive=ROOT/'docs/stages4-7-consultation-package.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
    for n,b in sorted(files.items()):z.writestr(n,b)
    z.writestr('manifest.json',json.dumps(manifest,indent=2).encode())
out=verify(archive)
(ROOT/'docs/stages4-7-consultation-package.zip.sha256').write_text(sha(archive.read_bytes())+'  '+archive.name+'\n')
(PRIVATE/'stages4-7-consultation-package-result.json').write_text(json.dumps(out|checks|{'archive_sha256':sha(archive.read_bytes()),'archive_bytes':archive.stat().st_size},indent=2))
print(json.dumps(out|{'archive':str(archive),'sha256':sha(archive.read_bytes()),'bytes':archive.stat().st_size},indent=2))
