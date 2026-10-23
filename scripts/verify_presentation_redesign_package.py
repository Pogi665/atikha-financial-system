"""Verify a review ZIP or extracted directory; optionally reconstruct its bounded patch.

Does not connect to an application/database or change the checkout.
"""
from pathlib import Path
import argparse, hashlib, json, os, shutil, subprocess, tempfile, zipfile

def digest(data):
    return hashlib.sha256(data).hexdigest()

def verify(path, reconstruct=False):
    archive = zipfile.ZipFile(path) if path.is_file() else None
    names = set(archive.namelist()) if archive else {p.relative_to(path).as_posix() for p in path.rglob('*') if p.is_file()}
    read = archive.read if archive else lambda name: (path / name).read_bytes()
    manifest = json.loads(read('MANIFEST.json'))
    expected = manifest['files']
    assert names == set(expected) | {'MANIFEST.json'}, 'Unexpected or missing members'
    assert all(not Path(n).is_absolute() and '..' not in Path(n).parts and '\\' not in n for n in names), 'Unsafe member name'
    for name, record in expected.items():
        data = read(name)
        assert len(data) == record['bytes'] and digest(data) == record['sha256'], name
    if reconstruct:
        scope = json.loads(read('evidence/patch-scope.json'))
        with tempfile.TemporaryDirectory(prefix='atikha-review-') as tmp:
            root = Path(tmp); tree = root / 'tree'; tree.mkdir(); patch = root / 'change.patch'
            patch.write_bytes(read('evidence/baseline-to-final.patch'))
            for name, rec in scope.items():
                if rec['baseline_sha256'] is not None:
                    f = tree / name; f.parent.mkdir(parents=True, exist_ok=True); f.write_bytes(read('baseline/' + name))
            env = os.environ.copy(); env['GIT_CEILING_DIRECTORIES'] = str(root)
            for reverse in [False, True]:
                cmd = ['git', '-c', 'core.autocrlf=false', 'apply', '--whitespace=nowarn'] + (['--reverse'] if reverse else []) + [str(patch)]
                done = subprocess.run(cmd, cwd=tree, env=env, capture_output=True, text=True)
                assert done.returncode == 0, done.stderr
                for name, rec in scope.items():
                    expected_hash = rec['baseline_sha256' if reverse else 'final_sha256']; f = tree / name
                    assert (not f.exists()) if expected_hash is None else f.is_file() and digest(f.read_bytes()) == expected_hash, ('reverse' if reverse else 'forward', name)
    if archive:
        archive.close()
    return len(expected)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('package', type=Path); parser.add_argument('--reconstruct', action='store_true'); args = parser.parse_args()
    count = verify(args.package.resolve(), args.reconstruct)
    print('PASS: ' + str(count) + '/' + str(count) + ' manifest-tracked members' + ('; forward/reverse exact-byte patch reconstruction' if args.reconstruct else ''))
