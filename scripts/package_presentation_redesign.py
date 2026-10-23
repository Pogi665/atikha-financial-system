"""Assemble the sanitized, exact-source redesign review handoff. No DB or provider use.

Reads an explicit private evidence registry; never packages configuration, sessions,
database fixtures/dumps, private logs or uploaded evidence. Does not stage or commit.
"""
from pathlib import Path
import ast, datetime, hashlib, html, json, os, re, secrets, shutil, subprocess, sys, zipfile
from verify_presentation_redesign_package import verify

ROOT = Path(__file__).resolve().parent.parent
PRIVATE = ROOT / '.migration-private'
BASELINE = 'db15b542a45c3c6e88ee9ccab78e8adc9c700f4c'

def sha(data): return hashlib.sha256(data).hexdigest()
def readj(path): return json.loads(Path(path).read_text(encoding='utf-8-sig'))
def git(*args): return subprocess.check_output(['git', *args], cwd=ROOT)
def safe_text(text):
    text = text.replace(str(ROOT), '<workspace>').replace(str(ROOT).replace('\\', '/'), '<workspace>')
    # Fixture-only paths are provenance, never a credential-bearing environment object.
    assert not re.search(r'"(?:ATIKHA_DB_PASSWORD|SMTP_PASSWORD|GEMINI_API_KEY|session|csrf_token)"\s*:', text, re.I), 'Sensitive field in evidence selection'
    return text

def main():
    assert git('rev-parse', 'HEAD').decode().strip() == BASELINE
    assert git('rev-parse', 'origin/main').decode().strip() == BASELINE
    assert git('branch', '--show-current').decode().strip() == 'main'
    registry = readj(PRIVATE / 'redesign-final-evidence.json')
    run = PRIVATE / ('redesign-package-' + secrets.token_hex(5)); run.mkdir()
    package = run / 'package'; package.mkdir()
    def put(name, data):
        dest = package / name; dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(data if isinstance(data, bytes) else data.encode('utf-8'))
    def putj(name, data): put(name, json.dumps(data, indent=2, ensure_ascii=False) + '\n')
    def sanitized(name, source): put(name, safe_text(Path(source).read_text(encoding='utf-8-sig')))

    r0path = PRIVATE / 'redesign-475f29a2afb1'
    original = readj(r0path / 'baseline-source.json'); preserved = readj(r0path / 'preserved-local.json')
    tracked = git('ls-files', '-z').decode().split('\0'); tracked = [f for f in tracked if f]
    changed = git('diff', '--name-only', '-z').decode().split('\0')
    untracked = git('ls-files', '--others', '--exclude-standard', '-z').decode().split('\0')
    def included_new(f):
        return f.startswith(('includes/', 'assets/')) or f == 'new_transaction.php' or f == 'design-qa.md' or (f.startswith('scripts/') and f.endswith(('.py', '.php')) and '__pycache__' not in f) or (f.startswith('docs/presentation-redesign-') and f.endswith('.md'))
    changed = sorted(set(f for f in changed if f and '__pycache__' not in f) | {f for f in untracked if f and included_new(f)})
    assert all((ROOT / f).is_file() for f in changed), 'Unexpected deletion'
    source = sorted({f for f in tracked if f.endswith(('.php', '.js', '.css', '.py', '.sql')) and not f.startswith(('vendor/', '.')) and f not in ['config.php', 'db_connect.php']} | {f for f in changed if f.endswith(('.php', '.js', '.css', '.py', '.sql'))})
    inventory = {}; scope = {}; changed_manifest = []
    for f in source:
        data = (ROOT / f).read_bytes(); put('final/' + f, data); inventory[f] = {'sha256': sha(data), 'bytes': len(data), 'kind': 'test/tool' if f.startswith('scripts/') else 'migration (unchanged)' if f.startswith('migrations/') else 'production'}
    scratch = run / 'patch'; (scratch / 'a').mkdir(parents=True); (scratch / 'b').mkdir()
    for f in changed:
        final = (ROOT / f).read_bytes(); baseline = git('show', BASELINE + ':' + f) if f in tracked else None
        if baseline == final: continue
        put('final/' + f, final)
        for folder, data in [('a', baseline), ('b', final)]:
            if data is not None:
                dest = scratch / folder / f; dest.parent.mkdir(parents=True, exist_ok=True); dest.write_bytes(data)
        if baseline is not None: put('baseline/' + f, baseline)
        scope[f] = {'baseline_sha256': sha(baseline) if baseline is not None else None, 'final_sha256': sha(final)}
        changed_manifest.append({'file': f, **scope[f], 'preexisting_at_R0': f in preserved, 'R0_sha256': preserved.get(f, original.get(f)), 'kind': inventory.get(f, {}).get('kind', 'documentation')})
    result = subprocess.run(['git', '-c', 'core.autocrlf=false', 'diff', '--no-index', '--binary', '--src-prefix=', '--dst-prefix=', 'a', 'b'], cwd=scratch, capture_output=True)
    assert result.returncode == 1, result.stderr
    put('evidence/baseline-to-final.patch', result.stdout); putj('evidence/patch-scope.json', scope)
    putj('evidence/changed-files.json', changed_manifest); putj('evidence/final-source-inventory.json', inventory)
    unchanged = {f: h for f, h in original.items() if (ROOT / f).is_file() and sha((ROOT / f).read_bytes()) == h}
    putj('evidence/unchanged-baseline-files.json', unchanged)
    preserve_result = []
    for f, oldhash in preserved.items():
        path = ROOT / f; current = sha(path.read_bytes()) if path.is_file() else None
        if current != oldhash:
            oldcopy = r0path / 'preserved' / f
            assert f in scope and oldcopy.is_file(), ('Unexplained preexisting change', f)
            oldbytes = oldcopy.read_bytes(); assert sha(oldbytes) == oldhash
            if path.suffix == '.md':
                # Current notes may prepend an update but must retain the user's prior material.
                assert oldbytes.decode('utf-8-sig').replace('\r\n','\n').strip() in path.read_text(encoding='utf-8-sig').replace('\r\n','\n'), f
            put('preserved-at-R0/' + f, oldbytes)
        preserve_result.append({'file': f, 'R0_sha256': oldhash, 'final_sha256': current, 'unchanged': current == oldhash, 'preserved_copy': 'preserved-at-R0/' + f if current != oldhash else None})
    putj('evidence/preexisting-local-preservation.json', preserve_result)
    sanitized('evidence/R0.json', r0path / 'r0.json')
    sanitized('design/written-design-rules.md', r0path / 'design-reference.md')
    sanitized('design/control-warnings.md', r0path / 'control-warnings.md')
    put('design/tokens.json', (r0path / 'design-tokens.json').read_bytes())
    design_hashes = readj(r0path / 'r0.json')['design_sha256']
    for name, expected in design_hashes.items():
        data = (Path('C:/Users/ACER/Downloads') / name).read_bytes(); assert sha(data) == expected; put('design/' + name, data)
    putj('design/precedence.json', {'approved_sha256': design_hashes, 'order': ['Current user implementation authorization and final amendments', 'Explicit actual-human U5 decisions', 'Approved written design/control rules', 'Approved visual boards; incidental tiny labels are not authority'], 'new_visual_direction': False})

    # Selected evidence is explicit. Never recurse over a private test application.
    tests = []; captures = []; pin_differences = {}
    for label, entry in registry['runs'].items():
        directory = Path(entry['path']); assert directory.resolve().is_relative_to(PRIVATE.resolve())
        result_name = entry.get('result', 'result.json'); data = readj(directory / result_name)
        assert data.get('passed') is True, label
        sanitized('evidence/tests/' + label + '/result.json', directory / result_name)
        for log in entry.get('logs', []):
            sanitized('evidence/tests/' + label + '/' + log, directory / log)
        checks = data.get('checks', data.get('commands', data.get('suites', [])))
        tests.append({'name': label, 'passed': True, 'check_or_command_count': len(checks) if isinstance(checks, list) else checks, 'evidence': 'evidence/tests/' + label + '/result.json', 'boundary': entry['boundary'], 'phase': entry.get('phase', 'Initial R8; predates bounded text correction')})
        pins = directory / 'source-pins.json'
        if pins.exists():
            pin_data = readj(pins); sanitized('evidence/tests/' + label + '/source-pins.json', pins)
            if all(isinstance(v, str) for v in pin_data.values()):
                differences = [f for f, h in pin_data.items() if (ROOT / f).is_file() and sha((ROOT / f).read_bytes()) != h]
                pin_differences[label] = differences
        if entry.get('screens'):
            metadata = {s['file']: s for s in data.get('screens', []) if isinstance(s, dict) and 'file' in s}
            for f in sorted(directory.glob('*.png')):
                name = 'screens/after/' + label + '/' + f.name; put(name, f.read_bytes())
                meta = metadata.get(f.name, {}); width = re.search(r'(683|1366|1920)', f.name)
                captures.append({'image': name, 'label': f.stem, 'role': meta.get('role', 'Management' if 'management' in f.name else 'Public' if any(w in f.name for w in ['login','mfa','password']) else 'Admin'), 'viewport': meta.get('viewport', [int(width.group()) if width else None, 1080 if width and width.group()=='1920' else 768]), 'route': meta.get('route'), 'state': f.stem, 'source_pins': 'evidence/tests/' + label + '/source-pins.json' if pins.exists() else None, 'source_difference_files': pin_differences.get(label, []), 'synthetic': True, 'phase': entry.get('phase', 'Initial R8; predates bounded text correction')})
        if entry.get('prints'):
            for pattern in ['*.pdf', '*.render.json']:
                for f in directory.glob(pattern): put('prints/' + f.name, f.read_bytes())
    ordinary = readj(PRIVATE / 'u5-final-1bde3cd105/results.json')
    corrected = readj(PRIVATE / 'u5-final-575f51765b/results.json')
    combined = {**ordinary, **corrected}
    assert len(combined) == 17 and all(v['passed'] and v['source_pins_match'] for v in combined.values())
    put('evidence/tests/ordinary/original-results.json', safe_text(json.dumps(ordinary, indent=2)))
    put('evidence/tests/ordinary/affected-retest-results.json', safe_text(json.dumps(corrected, indent=2)))
    put('evidence/tests/ordinary/accepted-union.json', safe_text(json.dumps(combined, indent=2)))
    for label, directory in [('ordinary', PRIVATE / 'u5-final-1bde3cd105'), ('ordinary-retest', PRIVATE / 'u5-final-575f51765b')]:
        put('evidence/tests/' + label + '/source-pins.json', (directory / 'source-pins.json').read_bytes())
        for f in directory.rglob('*.stdout.log'):
            # Test output only, never the fixtures/current pointer or private transport/session logs.
            sanitized('evidence/tests/' + label + '/logs/' + f.parent.name + '-' + f.name, f)
    tests.append({'name': 'ordinary regression union', 'passed': True, 'suites': sorted(combined), 'phase': 'Initial R8; predates bounded text correction', 'boundary': '17 fresh-clone suites. Original stage2/cp3 UI selector failures retained; only these affected suites rerun. Later CSS/CSV changes covered by focused final runs, not mislabelled as all-suite exact-final-byte reruns.'})
    putj('evidence/test-summary.json', tests); putj('evidence/capture-source-differences.json', pin_differences)
    for label in ['redesign-r0', 'redesign-r7', 'redesign-r8']:
        sanitized('evidence/preservation-' + label + '.json', PRIVATE / 'stages4-7-a165dc4e7b25' / ('preservation-' + label + '.json'))
    putj('evidence/known-unperformed-checks.json', {'actual_screen_reader': 'Unperformed; ARIA snapshots and keyboard checks are not NVDA or accessibility certification.', 'physical_browser_zoom': 'Unperformed; 683-CSS-pixel reflow simulates 200 percent of a 1366 viewport.', 'browser_matrix': 'Headless installed Edge on Windows; other browsers, physical mobile devices and printers unperformed.', 'actual_human': 'Old G7 paused/incomplete. Fresh protocol prepared only; run after external review.', 'working_rollout': 'Unperformed; no working migration, client configuration or financial write.', 'external_providers': 'No real SMTP/Gemini/provider call or activation. Fake transport and synthetic MFA helpers only.', 'performance': 'Controlled local synthetic engineering medians; not a production SLA.', 'evidence_timing': 'Component suites have dated source pins. Final affected UI/export checks cover last scoped changes. No claim every suite reran after every CSS edit.'})
    # Before images remain a frozen dated consultation record, not a final-source capture.
    beforezip = ROOT / 'docs/system-visual-review-package.zip'; assert sha(beforezip.read_bytes()) == '5f38e0dcfa5c357ba262dc103ed7ef0d5546eedf0f97929c7d356b8b95b5e3b5'
    with zipfile.ZipFile(beforezip) as before:
        put('screens/before/index.json', before.read('index.json'))
        for name in before.namelist():
            if name.startswith('screenshots/') and name.endswith('-full.png'): put('screens/before/' + name, before.read(name))
        meta = json.loads(before.read('index.json'))
    for record in meta['screens']:
        captures.append({'image': 'screens/before/' + record['full_image'], 'label': record['title'], 'role': record['role'], 'viewport': record['viewport'], 'route': record['route'], 'state': 'Before redesign: ' + record['title'], 'source_pins': 'Frozen pre-redesign consultation ZIP SHA256 5f38e0dc...', 'synthetic': True, 'before': True})
    putj('screens/index.json', captures)
    for f in ['README.md', 'docs/project-status.md', 'docs/client-context.md', 'docs/project-decisions.md', 'docs/presentation-redesign-plan.md', 'docs/presentation-redesign-delivery.md', 'docs/presentation-redesign-human-protocol.md', 'docs/presentation-redesign-rollout.md', 'design-qa.md']:
        put('documents/' + f.replace('docs/', ''), (ROOT / f).read_bytes())
    for f in sorted((PRIVATE / 'redesign-qa').glob('*.png')): put('visual-qa/' + f.name, f.read_bytes())
    gallery = []
    for c in captures:
        title = c.get('phase', 'Before redesign') + ' / ' + c['role'] + ' / ' + c['state'] + ' / ' + str(c['viewport'])
        gallery.append('<details><summary>' + html.escape(title) + '</summary><p>' + html.escape(str(c.get('route') or 'See capture/test source')) + '</p><a href="' + c['image'] + '">Open full size</a><img loading="lazy" src="' + c['image'] + '" alt="' + html.escape(title, quote=True) + '"></details>')
    put('index.html', '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Atikha redesign review</title><style>body{font:16px/1.5 system-ui;max-width:1300px;margin:32px auto;padding:0 20px;color:#172f27}details{margin:16px 0;border:1px solid #d8e2da;padding:12px}summary{cursor:pointer;font-weight:600}img{display:block;max-width:100%;margin-top:12px}a{color:#164b3b}</style><h1>Atikha Finance - Refined Client Experience</h1><p>External exact-source/browser review. Synthetic screens only. Read README-START-HERE.md first. Before images are dated consultation evidence; after captures identify their source pins and any later scoped difference in screens/index.json.</p><p><a href="documents/presentation-redesign-delivery.md">Delivery</a> | <a href="evidence/changed-files.json">Actual changed files</a> | <a href="evidence/test-summary.json">Tests</a> | <a href="evidence/known-unperformed-checks.json">Limits</a></p>' + ''.join(gallery) + '</html>')
    put('README-START-HERE.md', '''# Atikha Finance - Refined Client Experience

This package is for external exact-source/browser review, not deployment or client acceptance.
Baseline: main / db15b542a45c3c6e88ee9ccab78e8adc9c700f4c. No commit or push.

1. Read documents/presentation-redesign-delivery.md and design/precedence.json.
2. Open index.html for before/after full-page screens. PNGs may be opened directly.
3. Inspect final/ and evidence/changed-files.json. baseline/ contains Git-baseline bytes only for the patch scope; preexisting user documentation is separately preserved.
4. Inspect evidence/test-summary.json, source pins, performance results, preservation summaries and limitations. Earlier failed attempts are retained in the ordinary original results and explained in delivery; do not count them as passing final runs.
5. Read documents/design-qa.md, fresh human protocol and unperformed working rollout procedure.

Verify without the application/database:

    python verify_package.py this-package.zip --reconstruct

Or run against the extracted directory. Requires Python 3 and Git for --reconstruct. The verifier changes only a temporary directory. No configuration, database dump, session, private receipt or provider secret is included. The source copy excludes operational db_connect.php/config.php and third-party PHP vendor dependencies; this is a review package, not a runnable installation.

R0-R8 source implementation/review delivery is complete. External review, actual screen-reader/physical zoom checks, fresh human walkthrough, working rollout and Atikha acceptance remain separate. Old Stage4-7 G7 remains paused/incomplete.
''')
    put('REVIEW-PROMPT.txt', 'Review the approved Refined Client Experience implementation against its governing plan and written design rules. Verify exact manifest/patch reconstruction, financial/security boundaries, source-pinned tests, role-specific full-page screens, print scopes, Board retirement/archive authorization, Email uncertain-send states, and documented unperformed checks. Do not treat source completion as human or Atikha acceptance. Identify blockers or approve source/browser delivery; do not authorize or perform rollout/commit/push.\n')
    put('verify_package.py', (ROOT / 'scripts/verify_presentation_redesign_package.py').read_bytes())
    putj('evidence/package-build.json', {'baseline': BASELINE, 'branch': 'main', 'generated_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'source_files': len(inventory), 'patch_files': len(scope), 'screens': len(captures), 'no_database_calls': True, 'no_commit_push': True, 'no_rollout': True, 'registry_sha256': sha((PRIVATE / 'redesign-final-evidence.json').read_bytes())})
    manifest = {p.relative_to(package).as_posix(): {'bytes': p.stat().st_size, 'sha256': sha(p.read_bytes())} for p in sorted(package.rglob('*')) if p.is_file()}
    putj('MANIFEST.json', {'algorithm': 'SHA256', 'files': manifest})
    assert verify(package, True) == len(manifest)
    output = ROOT / 'docs/presentation-redesign-review-package.zip'
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
        for p in sorted(package.rglob('*')):
            if p.is_file(): z.write(p, p.relative_to(package).as_posix())
    assert verify(output, True) == len(manifest)
    checksum = sha(output.read_bytes()); output.with_suffix('.zip.sha256').write_text(checksum + '  ' + output.name + '\n')
    result = {'passed': True, 'package': output.name, 'sha256': checksum, 'bytes': output.stat().st_size, 'manifest_members': len(manifest), 'source_files': len(inventory), 'patch_files': len(scope), 'screens': len(captures), 'forward_reverse_reconstruction': True}
    (run / 'result.json').write_text(json.dumps(result, indent=2)); (PRIVATE / 'redesign-package-current.json').write_text(json.dumps({'run': str(run), 'result': result}, indent=2)); print(json.dumps(result))

if __name__ == '__main__': main()
