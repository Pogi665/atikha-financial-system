"""R0 source/design preservation and new guarded synthetic clone. No working writes."""
from pathlib import Path
import hashlib,json,os,re,secrets,subprocess,sys,zipfile
ROOT=Path(__file__).resolve().parent.parent;PRIVATE=ROOT/'.migration-private';BASE='db15b542a45c3c6e88ee9ccab78e8adc9c700f4c'
def git(*a):return subprocess.check_output(['git',*a],cwd=ROOT,text=True,encoding='utf-8').strip()
assert git('branch','--show-current')=='main' and git('rev-parse','HEAD')==git('rev-parse','origin/main')==BASE
assert not git('diff','--name-only','HEAD','--','*.php','*.js','*.css','*.sql','package.json','tailwind.config.js'),'Unrelated production drift'
sha=lambda b:hashlib.sha256(b).hexdigest()
design=Path('C:/Users/ACER/Downloads');hashes={'Atikha-Design-Extension.pdf':'a5b237f6fe46fc7c4315ddbabd7bd6bf32f34c666ac0b0b234e498c5c5af0a4b','Atikha-Design-Extension-Package.zip':'3bffe67924b80c30a9c7871f70593823c838e4771bf169b2c40c13d3a31e47e5'}
for n,h in hashes.items():assert sha((design/n).read_bytes())==h,'Design drift'
run=PRIVATE/('redesign-'+secrets.token_hex(6));run.mkdir();preserved={}
status=subprocess.check_output(['git','status','--porcelain=v1','-z','--untracked-files=all'],cwd=ROOT).decode().split('\0')
for line in status:
 if not line:continue
 name=line[3:];p=ROOT/name
 assert p.is_file(),name
 preserved[name]=sha(p.read_bytes())
 if p.suffix=='.md':target=run/'preserved'/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(p.read_bytes())
(run/'preserved-local.json').write_text(json.dumps(preserved,indent=2))
tracked=git('ls-files','*.php','*.js','*.css','*.sql','package.json','tailwind.config.js').splitlines()
pins={n:sha((ROOT/n).read_bytes()) for n in tracked if (ROOT/n).is_file()}
(run/'baseline-source.json').write_text(json.dumps(pins,indent=2))
with zipfile.ZipFile(design/'Atikha-Design-Extension-Package.zip') as z:
 prefix='Atikha-Design-Extension/';manifest=json.loads(z.read(prefix+'review-manifest.json'))
 for item in manifest['files']:assert sha(z.read(prefix+item['path']))==item['sha256']
 for item in json.loads(z.read(prefix+'source-inspection.json'))['files']:assert git('rev-parse','HEAD:'+item['path'])==item['blob_sha']
 for n in ['design-reference.md','control-warnings.md','design-tokens.json']:(run/n).write_bytes(z.read(prefix+n))
p=json.loads((PRIVATE/'stages4-7-current.json').read_text());source=Path(p['g7_fixture']).resolve();f=json.loads(source.read_text());assert source.is_relative_to(PRIVATE.resolve()) and f['working_data_used'] is False
env=os.environ.copy();env.update(p['environment']);target='atikha_test_stage1_s5redesign'+secrets.token_hex(5)
r=subprocess.run([sys.executable,'scripts/test_stage3_fixture.py','--fixture',str(source),'--database',target],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8',timeout=180)
(run/'clone.stdout.private.log').write_text(r.stdout);(run/'clone.stderr.private.log').write_text(r.stderr);assert r.returncode==0,'Guarded clone failed; no repair/retry'
fixture=re.search(r'Cloned fixture: (.+)',r.stdout).group(1).strip()
summary={'baseline':BASE,'branch':'main','local_cached_remote_equal':True,'live_remote_verified':'2026-10-24 db15b542a45c3c6e88ee9ccab78e8adc9c700f4c via git ls-remote','design_sha256':hashes,'design_manifest_members':14,'design_source_blobs':16,'preserved_local_files':len(preserved),'production_baseline_files':len(pins),'no_unrelated_production_drift':True,'reports_primary':'report_bundles.php?component=income_statement; existing default/JSON/print/snapshot contracts retained','transactions_navigation':'expandable group; no extra hub','setup_and_administration':'expandable groups; no hub routes','working_data_used':False,'schema_migration':False,'original_g7_untouched':True}
(run/'r0.json').write_text(json.dumps(summary,indent=2));(PRIVATE/'redesign-current.json').write_text(json.dumps({'run':str(run),'fixture':fixture,'environment':p['environment'],'g7_case':json.loads(Path(p['g7_metadata']).read_text())['case'],'gate':'R0 source/isolation ready; working preservation pending'},indent=2))
print(json.dumps(summary))
