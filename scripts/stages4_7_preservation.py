"""Read-only working-state fingerprints for the approved Stages4-7 envelope.

Never creates financial fixtures from working data. Raw fingerprints remain private.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess

root = Path(__file__).resolve().parent.parent
private = (root / '.migration-private').resolve()
ap = argparse.ArgumentParser()
ap.add_argument('--run', required=True)
ap.add_argument('--label', required=True)
ap.add_argument('--compare')
args = ap.parse_args()
run = Path(args.run).resolve()
assert run.parent == private and re.fullmatch(r'stages4-7-[a-f0-9]{12}', run.name)
assert re.fullmatch(r'[a-z0-9-]+', args.label)
pointer = json.loads((private / 'stages4-7-current.json').read_text(encoding='utf-8'))
assert Path(pointer['run']).resolve() == run
target = run / ('working-' + args.label + '.json')
assert not target.exists(), 'Use a fresh observation label; do not overwrite evidence.'
env = os.environ.copy()
env.update(pointer['environment'])
body = r'''<?php
require getcwd().'/scripts/cli_common.php';
require getcwd().'/scripts/database_phase2b_probe.php';
$p=cli_db('atikha_finance');
$p->exec('SET TRANSACTION READ ONLY');
$p->beginTransaction();
$identity=$p->query('SELECT @@port port,@@datadir datadir,@@version version,@@innodb_force_recovery recovery')->fetch();
if((int)$identity['port']!==3306||(int)$identity['recovery']!==0)throw new RuntimeException('Unexpected working identity.');
$s=p2b_snapshot($p);
$p->rollBack();
echo json_encode(['identity'=>$identity,'snapshot'=>$s],JSON_THROW_ON_ERROR);
'''
r = subprocess.run(['C:/xampp/php/php.exe'], input=body, capture_output=True,
                   text=True, encoding='utf-8', env=env, timeout=90)
if r.returncode or r.stderr:
    (run / ('preservation-' + args.label + '.stderr.private.log')).write_text(r.stderr, encoding='utf-8')
    raise RuntimeError('Read-only preservation failed; inspect private log. No repair attempted.')
observed = json.loads(r.stdout)
assert Path(observed['identity']['datadir']).resolve() == Path('C:/xampp/mysql/data').resolve()
pins = {}
for p in sorted((root / 'uploads').rglob('*')):
    if p.is_file():
        pins[str(p.relative_to(root)).replace('\\', '/')] = hashlib.sha256(p.read_bytes()).hexdigest()
for name in ['config.php', 'db_connect.php', 'C:/xampp/php/php.ini',
             'C:/xampp/mysql/bin/my.ini', 'C:/xampp/mysql/bin/mysqld.exe',
             'C:/xampp/apache/conf/httpd.conf', 'C:/xampp/apache/bin/httpd.exe']:
    p = Path(name) if ':' in name else root / name
    assert p.is_file(), 'Required configuration/binary pin missing.'
    pins[name] = hashlib.sha256(p.read_bytes()).hexdigest()
observed['files'] = pins
target.write_text(json.dumps(observed, indent=2), encoding='utf-8')
if args.compare:
    prior = (run / args.compare).resolve()
    assert prior.parent == run and prior.is_file()
    assert observed == json.loads(prior.read_text(encoding='utf-8')), 'Unexplained working preservation drift; stop.'
summary = {
    'passed': True, 'read_only': True, 'compared': bool(args.compare),
    'application_tables': len(observed['snapshot']['tables']),
    'triggers': len(observed['snapshot']['objects']['triggers']),
    'audit_rows': observed['snapshot']['tables']['audit_logs']['rows'],
    'upload_files': sum(name.startswith('uploads/') for name in pins),
    'configuration_and_permissions_pinned': True,
    'snapshot_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
}
(run / ('preservation-' + args.label + '.json')).write_text(json.dumps(summary, indent=2), encoding='utf-8')
print(json.dumps(summary))
