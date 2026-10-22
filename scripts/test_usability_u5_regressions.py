"""Affected final-source suites; new guarded synthetic clone per suite, no migrations.

Run only after serial performance measurement is finished. All financial actions,
negative-schema cases and uploads remain inside disposable test namespaces.
"""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed
import argparse, ast, hashlib, json, os, re, secrets, subprocess, sys

root = Path(__file__).resolve().parent.parent
private = (root / '.migration-private').resolve()
ap = argparse.ArgumentParser()
ap.add_argument('--source-fixture', required=True)
ap.add_argument('--only', default='all')
ap.add_argument('--workers', type=int, choices=[1, 2], default=2)
a = ap.parse_args()
source = Path(a.source_fixture).resolve()
assert source.is_relative_to(private)
f = json.loads(source.read_text())
assert re.fullmatch(r'atikha_test_stage1_[a-z0-9]+', f['database'])
assert Path(f['receipt_root']).resolve().is_relative_to(private)
env = os.environ.copy(); env['PYTHONIOENCODING'] = 'utf-8'
env.pop('ATIKHA_TEST_INLINE_ROUTINES', None)
php = 'C:/xampp/php/php.exe'
guard = json.loads(subprocess.check_output([php], input='<?php echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);', text=True, env=env))
assert guard[0] in ['', '0'] and all(x in guard[1].split(',') for x in ['curl_exec', 'mail', 'fsockopen', 'stream_socket_client'])
production = ['includes/journal_corrections.php', 'includes/stage2_common.php', 'includes/stage3_common.php', 'includes/accounting_entry_page.php', 'cash_advances.php', 'assets/js/accounting_workspace.js', 'assets/js/financial_records.js', 'assets/js/payment_presentation.js', 'assets/css/accounting_workspace.css', 'assets/css/financial_records.css']
pins = {n: hashlib.sha256((root / n).read_bytes()).hexdigest() for n in production}
run = private / ('u5-final-' + secrets.token_hex(5)); run.mkdir()
(run / 'source-pins.json').write_text(json.dumps(pins, indent=2))
(private / 'u5-final-current.json').write_text(json.dumps({'run': str(run)}))
for n in production:
    (run / ('baseline-' + n.replace('/', '_'))).write_bytes(subprocess.check_output(['git', 'show', 'f50d79e3d2bf38072946fbfdf543b6699118ad84:' + n], cwd=root))

def command(cmd, folder, label, timeout=1800):
    with (folder / (label + '.stdout.log')).open('w', encoding='utf-8') as out, (folder / (label + '.stderr.log')).open('w', encoding='utf-8') as err:
        p = subprocess.run(cmd, cwd=root, env=env, stdout=out, stderr=err, timeout=timeout)
    return {'command': cmd, 'exit': p.returncode}

offline = run / 'offline'; offline.mkdir(); results = []
for n in [n for n in production if not n.endswith('.css')] + ['scripts/test_usability_perf_fixture.php', 'scripts/test_usability_perf_reader.php', 'scripts/test_usability_perf_catalog.php']:
    results.append(command(['node', '--check', n] if n.endswith('.js') else [php, '-l', n], offline, n.replace('/', '_')))
for n in ['test_usability_u5_adapter.js', 'test_usability_u2_followup_adapter.js', 'test_usability_u2_adapter.js', 'test_usability_u3_advance_adapter.js', 'test_usability_u4_adapter.js']:
    results.append(command(['node', 'scripts/' + n], offline, n))
for n in ['browser_workspace_helpers.py', 'test_usability_perf_browser.py', 'test_usability_u5_browser.py', 'test_usability_u5_regressions.py']:
    ast.parse((root / 'scripts' / n).read_text(encoding='utf-8'))
assert all(r['exit'] == 0 for r in results), 'Offline check failed'
(run / 'offline-results.json').write_text(json.dumps(results, indent=2))

suites = {
    'stage1': ('test_stage1_browser.py', 'stage3-checkpoint5-fixture.json', 'u5stage1'),
    'integrated': ('test_usability_u5_browser.py', 'stage3-checkpoint5-fixture.json', 'u5integrated'),
    'reader': ('test_usability_perf_reader.php', 'stage3-checkpoint5-fixture.json', 'perfcheck'),
    'exact': ('test_usability_u2_reader.php', 'stage3-checkpoint5-fixture.json', 'u5exact'),
    'u2a': ('test_usability_u2_browser.py', 'stage3-checkpoint5-fixture.json', 'u5u2a'),
    'u2b': ('test_usability_u2_b_browser.py', 'stage3-checkpoint5-fixture.json', 'u5u2b'),
    'stage2': ('test_stage2_browser.py', 'stage2-fixture.json', 'u5stage2'),
    'cp2': ('test_stage3_checkpoint2_browser.py', 'stage3-checkpoint2-fixture.json', 'u5cp2'),
    'cp3': ('test_stage3_checkpoint3_browser.py', 'stage3-checkpoint3-fixture.json', 'u5cp3'),
    'cp4': ('test_stage3_checkpoint4_browser.py', 'stage3-checkpoint4-fixture.json', 'u5cp4'),
    'cp5': ('test_stage3_checkpoint5_browser.py', 'stage3-checkpoint5-fixture.json', 'u5cp5'),
    'u3a': ('test_usability_u3_a_browser.py', 'stage3-checkpoint5-fixture.json', 'u3a'),
    'bcd': ('test_usability_u3_bcd_browser.py', 'stage3-checkpoint5-fixture.json', 'u3bcd'),
    'bcd_backend': ('test_usability_u3_bcd_edges.php', 'stage3-checkpoint5-fixture.json', 'u3bcd'),
    'u4b': ('test_usability_u4_browser.py', 'stage3-checkpoint5-fixture.json', 'u4perf'),
    'u4c': ('test_usability_u4_browser.py', 'stage3-checkpoint5-fixture.json', 'u4perf'),
    'u4d': ('test_usability_u4_browser.py', 'stage3-checkpoint5-fixture.json', 'u4perf'),
}
chosen = list(suites) if a.only == 'all' else a.only.split(',')
assert all(n in suites for n in chosen)

def execute(name):
    script, filename, prefix = suites[name]
    folder = run / name; folder.mkdir()
    target = 'atikha_test_stage1_' + prefix + secrets.token_hex(5)
    c = command([sys.executable, 'scripts/test_stage3_fixture.py', '--fixture=' + str(source.with_name(filename)), '--database=' + target], folder, 'clone', 180)
    assert c['exit'] == 0, 'Clone failed: ' + name
    fixture = re.search(r'Cloned fixture: (.+)', (folder / 'clone.stdout.log').read_text()).group(1).strip()
    commands = []
    if name in ['u4b', 'u4c', 'u4d']:
        commands.append([php, 'scripts/test_usability_u4_fixture.php', '--fixture=' + fixture])
    if name == 'bcd':
        # Browser B expects the B-prepared register before C/D consume their own fixtures.
        commands.append([php, 'scripts/test_usability_u3_bcd.php', '--fixture=' + fixture, '--gate=b'])
    cmd = ([sys.executable] if script.endswith('.py') else [php]) + ['scripts/' + script, '--fixture=' + fixture]
    if name in ['u4b', 'u4c', 'u4d']: cmd += ['--gate=' + name[-1]]
    if name in ['bcd', 'bcd_backend']: cmd += ['--gate=all']
    if name == 'reader':
        for key, filename in [('reader', 'journal_corrections.php'), ('stage2', 'stage2_common.php'), ('stage3', 'stage3_common.php')]:
            cmd += ['--baseline-' + key + '=' + str(run / ('baseline-includes_' + filename))]
    commands.append(cmd)
    if name == 'u3a': commands.append([sys.executable, 'scripts/test_usability_u3_a_readback.py', '--fixture=' + fixture])
    entries = []
    for i, cmd in enumerate(commands):
        entries.append(command(cmd, folder, str(i)))
        if entries[-1]['exit']: break
    same = all(hashlib.sha256((root / n).read_bytes()).hexdigest() == h for n, h in pins.items())
    result = {'passed': entries[-1]['exit'] == 0 and same, 'source_pins_match': same, 'fixture': fixture, 'commands': entries}
    (folder / 'result.json').write_text(json.dumps(result, indent=2)); return result

outcomes = {}
with ThreadPoolExecutor(max_workers=a.workers) as pool:
    jobs = {pool.submit(execute, n): n for n in chosen}
    for job in as_completed(jobs):
        name = jobs[job]
        try: outcomes[name] = job.result()
        except BaseException as e: outcomes[name] = {'passed': False, 'exception': repr(e)}
        (run / 'results.json').write_text(json.dumps(outcomes, indent=2))
        print('U5_FINAL ' + name + ' ' + ('PASS' if outcomes[name]['passed'] else 'FAIL') + ' ' + str(run / name), flush=True)
print('U5_FINAL_RUN: ' + str(run), flush=True)
sys.exit(0 if all(r['passed'] for r in outcomes.values()) else 1)
