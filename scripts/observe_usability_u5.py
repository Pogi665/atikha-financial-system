"""Prepare/observe a human U5 walkthrough on a private synthetic app only.

The observer never completes a financial task or retries a mutation. Operator
commands change task labels, capture screenshots, switch synthetic role or close.
No production authentication/MFA, sessions, financial data or receipts are used.
"""
from pathlib import Path
from urllib.parse import urlparse
import argparse, hashlib, json, os, re, secrets, shutil, socket, subprocess, sys, time

root = Path(__file__).resolve().parent.parent
private = (root / '.migration-private').resolve()
sys.path.insert(0, str(private / 'journal-test-deps'))
from playwright.sync_api import sync_playwright
ap = argparse.ArgumentParser()
group = ap.add_mutually_exclusive_group(required=True)
group.add_argument('--fixture'); group.add_argument('--run')
ap.add_argument('--prepare-only', action='store_true')
ap.add_argument('--check-only', action='store_true')
ap.add_argument('--resume-path', help='Reopen an existing correction draft through a read-only page request')
a = ap.parse_args(); php = 'C:/xampp/php/php.exe'
if a.resume_path:
    assert a.run and re.fullmatch(r'/journal_correction\.php\?journal_id=[1-9]\d*&draft_id=[1-9]\d*', a.resume_path), 'Only an existing correction draft page can be resumed'

def ps(v): return "'" + str(v).replace('\\', '\\\\').replace("'", "\\'") + "'"
def digest(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def phpcode(code): return subprocess.check_output([php], input='<?php ' + code, text=True, encoding='utf-8', cwd=root)
def fingerprint(db):
    return json.loads(phpcode('require ' + ps(root / 'scripts/cli_common.php') + ';$p=cli_db(' + ps(db) + ');$p->exec("SET TRANSACTION READ ONLY");$p->beginTransaction();$out=[];foreach($p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $t)$out[$t]=hash("sha256",json_encode($p->query("SELECT * FROM `".$t."` ORDER BY 1")->fetchAll()));$p->rollBack();echo json_encode($out);'))
guard = json.loads(phpcode('echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);'))
assert guard[0] in ['', '0'] and all(n in guard[1].split(',') for n in ['curl_exec', 'mail', 'fsockopen', 'stream_socket_client'])

if a.fixture:
    path = Path(a.fixture).resolve(); assert path.is_relative_to(private)
    f = json.loads(path.read_text()); db = f['database']; evidence = Path(f['receipt_root']).resolve()
    assert re.fullmatch(r'atikha_test_stage1_u5human[a-z0-9]+', db) and evidence.is_relative_to(private)
    assert not (path.parent / 'human-prepared.json').exists(), 'Do not prepare a consumed walkthrough clone again'
    (path.parent / 'human-prepared.json').write_text('{}')
    run = private / ('u5-human-' + secrets.token_hex(5)); app = run / 'app'; app.mkdir(parents=True)
    sessions = run / 'sessions'; sessions.mkdir()
    s2 = json.loads(path.with_name('stage2-fixture.json').read_text()); assert s2['database'] == db
    for n in subprocess.check_output(['git', 'ls-files', '*.php'], cwd=root, text=True).splitlines():
        if '/' not in n and not n.startswith('_') and n not in ['config.php', 'db_connect.php']: shutil.copy2(root / n, app / n)
    for n in ['includes', 'assets', 'scripts', 'vendor']: shutil.copytree(root / n, app / n)
    (app / 'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR'," + ps(evidence) + ');', encoding='utf-8')
    dsn = 'mysql:host=' + os.getenv('ATIKHA_DB_HOST', '127.0.0.1') + ';dbname=' + db + ';charset=utf8mb4'
    (app / 'db_connect.php').write_text('<?php $pdo=new PDO(' + ','.join(ps(v) for v in [dsn, os.getenv('ATIKHA_DB_USER', 'root'), os.getenv('ATIKHA_DB_PASSWORD', '')]) + ',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);', encoding='utf-8')
    # Separate synthetic task actors, through the existing owner-scoped master writer.
    setup = "require " + ps(app / 'config.php') + ';require ' + ps(app / 'db_connect.php') + ';require ' + ps(app / 'includes/accounting_workspace.php') + ";$uid=(int)$pdo->query(\"SELECT UserID FROM Users WHERE Email='admin@example.invalid'\")->fetchColumn();$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];$parties=[];foreach(['U5 Synthetic Training Supplier','U5 Synthetic Donor','U5 Synthetic Employee'] as $name)$parties[]=workspace_master_save($pdo,$uid,['csrf_token'=>csrf_token(),'kind'=>'parties','id'=>'','name'=>$name,'party_type'=>'person','reference'=>'U5-HUMAN-SYNTHETIC','description'=>'Isolated human walkthrough only','is_active'=>'1']);$out=['parties'=>$parties,'today'=>journal_today(),'accounts'=>[]];foreach([" + ','.join(str(int(x)) for x in [f['bank'], f['training'], f['income'], s2['control']]) + "] as $id){$s=$pdo->prepare('SELECT CategoryID,Name,Account_Code FROM Categories WHERE CategoryID=?');$s->execute([$id]);$out['accounts'][$id]=$s->fetch();}echo json_encode($out);"
    facts = json.loads(phpcode(setup))
    proofs = run / 'synthetic-documents'; proofs.mkdir()
    for filename, title, amount in [('training-support.png', 'SYNTHETIC TRAINING COSTS', '8000.00'), ('cash-return-proof.png', 'SYNTHETIC UNUSED CASH RETURN', '2000.00')]:
        phpcode("$im=imagecreatetruecolor(900,300);$bg=imagecolorallocate($im,242,245,250);$fg=imagecolorallocate($im,20,30,50);imagefill($im,0,0,$bg);imagestring($im,5,30,35,'U5 TEST FIXTURE - NOT A REAL FINANCIAL DOCUMENT',$fg);imagestring($im,5,30,85," + ps(title) + ",$fg);imagestring($im,5,30,125," + ps('PHP ' + amount + ' | ' + facts['today']) + ",$fg);imagestring($im,5,30,190,'Synthetic facts only. No authenticity/payment assertion.',$fg);imagepng($im," + ps(proofs / filename) + ');imagedestroy($im);')
    state = {'fixture': str(path), 'database': db, 'receipt_root': str(evidence), 'facts': facts, 'base_fixture': f, 'stage2_fixture': s2, 'source_pins': {n: digest(root / n) for n in ['includes/journal_corrections.php', 'includes/stage2_common.php', 'includes/stage3_common.php', 'includes/accounting_entry_page.php', 'cash_advances.php', 'assets/js/accounting_workspace.js', 'assets/js/financial_records.js', 'assets/js/payment_presentation.js', 'assets/css/accounting_workspace.css', 'assets/css/financial_records.css']}}
    (run / 'preparation.json').write_text(json.dumps(state, indent=2), encoding='utf-8')
    # Task facts only; no discovery-determining control-by-control manual.
    tasks = [
        {'id': 'payment', 'goal': 'Record a PHP1000 training payment. Confirm the form prevents an incomplete entry, recover using its message, then finish the same entry.', 'party': facts['parties'][0]['name'], 'purpose': 'U5 HUMAN training payment', 'amount': '1000.00', 'date': facts['today'], 'project': 'Organization operations', 'evidence': 'No supporting document supplied; do not claim monetary support.', 'explanation': 'Explain draft, reviewed and recorded states; show the exact recorded transaction.'},
        {'id': 'receipt', 'goal': 'Prepare PHP5000 synthetic unrestricted funds received, save for later, leave and find that saved work, then record it.', 'party': facts['parties'][1]['name'], 'purpose': 'U5 HUMAN synthetic funds received', 'amount': '5000.00', 'date': facts['today'], 'project': 'Organization operations', 'explanation': 'Explain when the financial records actually changed.'},
        {'id': 'advance', 'goal': 'Release PHP10000 to the synthetic employee, liquidate PHP8000 of supported training costs, and return PHP2000 unused cash on that same advance.', 'party': facts['parties'][2]['name'], 'purpose': 'U5 HUMAN community training advance', 'date': facts['today'], 'due_date': facts['today'], 'project': 'Organization operations', 'documents': ['training-support.png: declared/accepted cost8000.00, allocated to the training debit', 'cash-return-proof.png: return2000.00 informational proof'], 'explanation': 'Explain why release is not an expense, and show the final advance balance/state.'},
        {'id': 'correction', 'goal': 'Find your PHP1000 training payment and correct it to PHP900 with a linked reversal and replacement.', 'reason': 'Synthetic walkthrough corrected training amount', 'explanation': 'Show original, reversal and replacement; explain which journals were previews before recording.'},
        {'id': 'management', 'goal': 'In the prepared Management role, inspect the recorded payment/correction and settled advance.', 'explanation': 'Explain what Management can inspect and which authoring/private-proof actions are unavailable.'},
    ]
    (run / 'tasks.json').write_text(json.dumps(tasks, indent=2), encoding='utf-8')
else:
    run = Path(a.run).resolve(); assert run.is_relative_to(private)
    state = json.loads((run / 'preparation.json').read_text()); db = state['database']; app = run / 'app'; sessions = run / 'sessions'
    assert re.fullmatch(r'atikha_test_stage1_u5human[a-z0-9]+', db)
    assert all(digest(root / n) == h for n, h in state['source_pins'].items()), 'Walkthrough source drift; preserve and refresh explicitly'
(private / 'u5-human-current.json').write_text(json.dumps({'run': str(run)}, indent=2))
print('U5_HUMAN_RUN: ' + str(run), flush=True)
if a.prepare_only: sys.exit(0)

sock = socket.socket(); sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]; sock.close(); base = f'http://127.0.0.1:{port}'
router = run / 'router.php'
router.write_text("""<?php
$p=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
$pages=['/dashboard.php','/cash_disbursement.php','/cash_receipt.php','/general_journal.php','/cash_advance_entry.php','/cash_advances.php','/accounting_drafts.php','/financial_records.php','/journal_transaction.php','/journal_corrections.php','/journal_correction.php','/receipt_attachment.php','/favicon.ico'];
$actions=['/accounting_actions.php','/cash_advance_actions.php','/journal_correction_actions.php'];
if(str_contains($p,'..')||(!str_starts_with($p,'/assets/')&&!in_array($p,array_merge($pages,$actions),true))||(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)&&!in_array($p,$actions,true))){http_response_code(403);exit('Outside isolated walkthrough');}return false;
""", encoding='utf-8')
log = (run / 'server.log').open('a', encoding='utf-8')
server = subprocess.Popen([php, '-d', 'session.save_path=' + str(sessions), '-S', f'127.0.0.1:{port}', '-t', str(app), str(router)], stdout=log, stderr=log)
events = (run / 'events.jsonl').open('a', encoding='utf-8'); task = 'ready'; role = 'Admin'; sequence = None; stop = False

def event(kind, details):
    events.write(json.dumps({'at_utc': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()), 'task': task, 'role': role, 'kind': kind, 'details': details}, ensure_ascii=False) + '\n'); events.flush()

try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.2): break
        except OSError: time.sleep(.1)
    with sync_playwright() as pw:
        browser = pw.chromium.launch(channel='msedge', headless=a.check_only, args=['--new-window'])
        ctx = browser.new_context(viewport={'width': 1366, 'height': 768}, service_workers='block')
        def route(request):
            parsed = urlparse(request.request.url)
            if parsed.scheme in ['http', 'https'] and parsed.netloc != f'127.0.0.1:{port}':
                event('blocked-outside-origin', {'origin': parsed.scheme + '://' + parsed.netloc}); request.abort()
            else: request.continue_()
        ctx.route('**/*', route)
        def session(email):
            ss = json.loads(subprocess.check_output([php, str(app / 'scripts/http_fixture.php'), '--database=' + db, '--sessions=' + str(sessions), '--email=' + email, '--test-login'], text=True))
            ctx.clear_cookies(); ctx.add_cookies([{'name': 'PHPSESSID', 'value': ss['session'], 'url': base}])
        session('admin@example.invalid')
        ctx.expose_binding('u5Observe', lambda source, value: event('ui', value))
        ctx.add_init_script("""(() => {
          const describe = e => ({event:e.type,tag:e.target.tagName,id:e.target.id||'',label:(e.target.closest('button,a,[role=option]')?.textContent||e.target.getAttribute('aria-label')||'').trim().slice(0,180)});
          document.addEventListener('click',e=>{if(e.target.closest('button,a,[role=option],summary'))window.u5Observe(describe(e))},true);
          document.addEventListener('change',e=>{if(e.target.matches('input,select,textarea')&&!['password','hidden'].includes(e.target.type)&&!/csrf|token|password|mfa|session/i.test(e.target.name||e.target.id||'')){const v=describe(e);v.value=e.target.type==='file'?'synthetic file chosen':e.target.type==='checkbox'?e.target.checked:String(e.target.value).slice(0,250);window.u5Observe(v)}},true);
        })()""")
        page = ctx.new_page(); page.on('pageerror', lambda e: event('js-error', {'message': str(e)}))
        def response_seen(response):
            req = response.request
            if req.method != 'POST': return
            try:
                body = response.json(); result = body.get('result') or {}
                action = (req.post_data_json or {}).get('action', 'multipart')
                event('mutation-response', {'path': urlparse(response.url).path, 'status': response.status, 'action': action, 'ok': body.get('ok'), 'identities': {k: result[k] for k in ['id', 'advance_id', 'correction_id', 'target_journal_id', 'reversal_journal_id', 'replacement_journal_id'] if k in result}, 'error': str(body.get('error', ''))[:400]})
            except Exception: event('mutation-response-unreadable', {'path': urlparse(response.url).path, 'status': response.status})
        page.on('response', response_seen)
        if a.check_only:
            before = fingerprint(db); labels = []
            for route_name in ['config.php', 'db_connect.php', 'scripts/http_fixture.php', 'uploads/synthetic.png', 'admin_users.php', 'forecast_ai.php', 'external_email.php', 'login.php']:
                assert ctx.request.get(base + '/' + route_name).status == 403; labels.append('Blocked private/out-of-scope route ' + route_name)
            assert ctx.request.get(base + '/accounting_actions.php?action=lists').json()['ok']; labels.append('Existing authorized Admin list contract available')
            try:
                page.goto('http://u5-outside.invalid/guard-probe', timeout=10000)
                raise AssertionError('Outside origin was not blocked')
            except Exception as e:
                assert 'net::ERR_FAILED' in str(e) or 'net::ERR_BLOCKED' in str(e), str(e)
                labels.append('Browser blocks outside origin before transport')
            session('management@example.invalid')
            assert ctx.request.get(base + '/accounting_drafts.php').status == 403; labels.append('Management private drafts remain denied')
            assert ctx.request.get(base + '/cash_advance_entry.php').status == 403; labels.append('Management advance authoring remains denied')
            assert fingerprint(db) == before; labels.append('Guard preparation/role setup changes no application table')
            (run / 'guard-checks.json').write_text(json.dumps({'passed': True, 'checks': len(labels), 'labels': labels, 'no_human_task_performed': True}, indent=2))
            print('U5_HUMAN_GUARD_CHECKED: ' + str(len(labels)), flush=True)
            browser.close(); sys.exit(0)
        page.goto(base + (a.resume_path or '/dashboard.php'), wait_until='networkidle')
        page.screenshot(path=str(run / 'ready.png'), full_page=True)
        event('ready', {'base': base, 'normal_login_mfa_tested': False})
        print('U5_HUMAN_READY: ' + base, flush=True)
        (run / 'runtime.json').write_text(json.dumps({'ready': True, 'base': base, 'server_pid': server.pid, 'task': task, 'role': role}, indent=2))
        start = time.monotonic(); next_capture = start; last = ''
        while not stop and time.monotonic() - start < 6 * 3600:
            page.wait_for_timeout(500)
            cmdfile = run / 'command.json'
            if cmdfile.exists():
                command = json.loads(cmdfile.read_text())
                if command.get('sequence') != sequence:
                    sequence = command.get('sequence'); action = command['action']
                    if action == 'task': task = command['task']; event('task-start', {'goal_only_instructions': command.get('notes', '')})
                    elif action == 'note': event('observer-note', command['note'])
                    elif action == 'capture': page.screenshot(path=str(run / ('observed-' + re.sub('[^a-zA-Z0-9_-]', '', command['label']) + '.png')), full_page=True)
                    elif action == 'management':
                        role = 'Management'; session('management@example.invalid'); page.goto(base + '/dashboard.php', wait_until='networkidle'); event('role-setup', {'normal_login_mfa_tested': False})
                    elif action == 'admin':
                        role = 'Admin'; session('admin@example.invalid'); page.goto(base + '/dashboard.php', wait_until='networkidle'); event('role-setup', {'normal_login_mfa_tested': False})
                    elif action == 'close': stop = True
                    else: raise ValueError('Unsupported observer action')
            if time.monotonic() >= next_capture:
                text = page.locator('body').inner_text(); signature = page.url + hashlib.sha256(text.encode()).hexdigest()
                if signature != last:
                    page.screenshot(path=str(run / 'latest.png'), full_page=True)
                    event('visible-state', {'path': urlparse(page.url).path, 'text': text[:22000]}); last = signature
                (run / 'runtime.json').write_text(json.dumps({'ready': not stop, 'base': base, 'server_pid': server.pid, 'task': task, 'role': role, 'url': page.url, 'last_capture_utc': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())}, indent=2))
                next_capture = time.monotonic() + 4
        browser.close()
finally:
    event('observer-stopped', {'human_completion_not_inferred': True}); events.close()
    server.terminate(); server.wait(timeout=10); log.close()
