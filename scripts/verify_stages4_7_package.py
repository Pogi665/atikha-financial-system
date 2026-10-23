"""Standalone source-archive manifest and reversible-patch verifier; no application/DB access."""
from pathlib import Path,PurePosixPath
import argparse,hashlib,json,shutil,subprocess,tempfile,zipfile

def sha(data):return hashlib.sha256(data).hexdigest()
def canonical(data):return data.replace(b'\r\n',b'\n')
def blob(data):return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()

def verify(path):
    with zipfile.ZipFile(path) as z:
        names=z.namelist()
        assert len(names)==len(set(names)), 'Duplicate archive members'
        for n in names:
            p=PurePosixPath(n)
            assert not p.is_absolute() and '..' not in p.parts and '\\' not in n and ':' not in n,'Unsafe member path'
            assert not any(part in ['.git','.migration-private','__pycache__','uploads','sessions'] for part in p.parts),'Private/runtime member'
            assert p.name not in ['config.php','db_connect.php','roles.private.json'],'Private configuration member'
        m=json.loads(z.read('manifest.json'))
        assert set(names)==set(m['members'])|{'manifest.json'},'Member inventory differs'
        for n,h in m['members'].items():assert sha(z.read(n))==h,'Member hash mismatch: '+n
        assert m['classification']=='interim consultation; G7 paused; not final G8'
        assert m['human_walkthrough_complete'] is False and m['working_rollout'] is False
        assert shutil.which('git'),'Git is required only for local patch application'
        with tempfile.TemporaryDirectory(prefix='atikha-review-verify-') as tmp:
            tree=Path(tmp)
            for name,entry in m['changed_source'].items():
                if entry['baseline_git_blob']:
                    data=z.read('baseline/'+name)
                    assert blob(data)==entry['baseline_git_blob'],'Baseline blob mismatch: '+name
                    p=tree/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
            patches=[]
            for part in ['production','tests']:
                p=tree/(part+'.patch');p.write_bytes(z.read('patches/'+part+'.patch'));patches.append(p)
                if p.stat().st_size:
                    r=subprocess.run(['git','-c','core.autocrlf=false','-c','core.eol=lf','apply','--check',str(p)],cwd=tree,capture_output=True)
                    assert r.returncode==0,part+' forward patch check failed'
                    r=subprocess.run(['git','-c','core.autocrlf=false','-c','core.eol=lf','apply',str(p)],cwd=tree,capture_output=True)
                    assert r.returncode==0,part+' forward patch failed'
            for name,entry in m['changed_source'].items():
                data=(tree/name).read_bytes()
                assert sha(canonical(data))==entry['canonical_sha256'],'Forward reconstruction mismatch: '+name
                source=z.read('source/'+name)
                assert sha(source)==entry['raw_sha256'] and canonical(source)==canonical(data),'Exact source mismatch: '+name
                assert blob(canonical(data))==entry['final_git_blob'],'Final blob mismatch: '+name
            for p in reversed(patches):
                if p.stat().st_size:
                    r=subprocess.run(['git','-c','core.autocrlf=false','-c','core.eol=lf','apply','--reverse','--check',str(p)],cwd=tree,capture_output=True)
                    assert r.returncode==0,'Reverse patch check failed'
                    r=subprocess.run(['git','-c','core.autocrlf=false','-c','core.eol=lf','apply','--reverse',str(p)],cwd=tree,capture_output=True)
                    assert r.returncode==0,'Reverse patch failed'
            for name,entry in m['changed_source'].items():
                p=tree/name
                if entry['baseline_git_blob']:
                    assert p.read_bytes()==z.read('baseline/'+name),'Reverse reconstruction mismatch: '+name
                else:assert not p.exists(),'New file survived reverse: '+name
        return {'passed':True,'manifest_members':len(m['members']),'changed_source_files':len(m['changed_source']),'forward_reverse_patches':True,'classification':m['classification'],'human_walkthrough_complete':False}

if __name__=='__main__':
    ap=argparse.ArgumentParser();ap.add_argument('archive');a=ap.parse_args()
    print(json.dumps(verify(Path(a.archive)),indent=2))
