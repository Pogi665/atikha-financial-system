from browser_workspace_helpers import navigate_sidebar
"""Connected U5 daily workflow, only on a fresh private full-schema synthetic clone.

Automation is not an observed human walkthrough or working login/MFA acceptance.
No schema construction, working credentials, provider calls or migrations.
"""
from pathlib import Path
from datetime import date, timedelta
from decimal import Decimal
from urllib.parse import urlparse, parse_qs
import argparse, hashlib, json, os, re, secrets, shutil, socket, struct, subprocess, sys, time, zlib

root = Path(__file__).resolve().parent.parent
private = (root / '.migration-private').resolve()
sys.path.insert(0, str(private / 'journal-test-deps'))
from playwright.sync_api import sync_playwright

ap = argparse.ArgumentParser()
ap.add_argument('--fixture', required=True)
a = ap.parse_args()
path = Path(a.fixture).resolve()
assert path.is_relative_to(private)
f = json.loads(path.read_text())
db = f['database']
evidence = Path(f['receipt_root']).resolve()
assert re.fullmatch(r'atikha_test_stage1_u5[a-z0-9]+', db) and evidence.is_relative_to(private)
assert not (path.parent / 'u5-started.json').exists(), 'Fresh U5 clone required'
(path.parent / 'u5-started.json').write_text('{}')
s2 = json.loads(path.with_name('stage2-fixture.json').read_text())
assert s2['database'] == db
php = 'C:/xampp/php/php.exe'
run = private / ('u5-browser-' + secrets.token_hex(5))
app = run / 'app'
app.mkdir(parents=True)
sessions = run / 'sessions'
sessions.mkdir()

def ps(value):
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"

transport = json.loads(subprocess.check_output([php], input='<?php echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);', text=True))
assert transport[0] in ['', '0'] and all(x in transport[1].split(',') for x in ['curl_exec', 'mail', 'fsockopen', 'stream_socket_client'])
for name in subprocess.check_output(['git', 'ls-files', '*.php'], cwd=root, text=True).splitlines():
    if '/' not in name and not name.startswith('_') and name not in ['config.php', 'db_connect.php']:
        shutil.copy2(root / name, app / name)
for name in ['includes', 'assets', 'scripts', 'vendor']:
    shutil.copytree(root / name, app / name)
(app / 'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);define('RECEIPT_UPLOAD_DIR'," + ps(evidence) + ');', encoding='utf-8')
dsn = 'mysql:host=' + os.getenv('ATIKHA_DB_HOST', '127.0.0.1') + ';dbname=' + db + ';charset=utf8mb4'
(app / 'db_connect.php').write_text('<?php $pdo=new PDO(' + ','.join(ps(v) for v in [dsn, os.getenv('ATIKHA_DB_USER', 'root'), os.getenv('ATIKHA_DB_PASSWORD', '')]) + ',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);', encoding='utf-8')

def sql(query):
    code = '<?php require ' + ps(root / 'scripts/cli_common.php') + ';$p=cli_db(' + ps(db) + ');echo json_encode($p->query(' + ps(query) + ')->fetchAll());'
    return json.loads(subprocess.check_output([php], input=code, text=True, encoding='utf-8'))

def login(email):
    return json.loads(subprocess.check_output([php, str(app / 'scripts/http_fixture.php'), '--database=' + db, '--sessions=' + str(sessions), '--email=' + email, '--test-login'], text=True))

def balances():
    return {int(row['account_id']): Decimal(row['net']) for row in sql("SELECT l.account_id,SUM(l.debit_amount-l.credit_amount) net FROM journal_entry_lines l JOIN journal_entries j ON j.id=l.journal_entry_id WHERE j.status='posted' GROUP BY l.account_id")}

def fingerprint():
    return {r['TABLE_NAME']: hashlib.sha256(json.dumps(sql('SELECT * FROM `' + r['TABLE_NAME'] + '` ORDER BY 1'), sort_keys=True).encode()).hexdigest() for r in sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")}

def png():
    def chunk(k, data):
        return struct.pack('>I', len(data)) + k + data + struct.pack('>I', zlib.crc32(k + data) & 0xffffffff)
    rows = b''.join(b'\0' + bytes([secrets.randbelow(255), 50, 100, 255]) * 64 for _ in range(64))
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 64, 64, 8, 6, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(rows)) + chunk(b'IEND', b'')

sock = socket.socket(); sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]; sock.close()
base = f'http://127.0.0.1:{port}'
router = run / 'router.php'
router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|forecast_ai|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|external_email|send_email)~',$p)){http_response_code(403);exit;}return false;", encoding='utf-8')
log = (run / 'server.log').open('w', encoding='utf-8')
server = subprocess.Popen([php, '-d', 'session.save_path=' + str(sessions), '-S', f'127.0.0.1:{port}', '-t', str(app), str(router)], stdout=log, stderr=log)
checks = []; errors = []; outcomes = {}; traces = []; passed = False

def check(ok, label):
    if not ok:
        raise AssertionError(label)
    checks.append(label); print('PASS: ' + label, flush=True)

try:
    for _ in range(60):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.2): break
        except OSError: time.sleep(.1)
    with sync_playwright() as pw:
        browser = pw.chromium.launch(channel='msedge', headless=True)
        def context(email):
            ctx = browser.new_context(viewport={'width': 1366, 'height': 768}, service_workers='block')
            ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'), lambda r: r.abort())
            session = login(email)
            ctx.add_cookies([{'name': 'PHPSESSID', 'value': session['session'], 'url': base}])
            return ctx, session
        admin, session = context('admin@example.invalid')
        manager, _ = context('management@example.invalid')
        page = admin.new_page(); page.on('pageerror', lambda e: errors.append(str(e)))
        def ready():
            page.wait_for_function("document.getElementById('aw-existing') && (!document.getElementById('aw-existing').disabled || !document.getElementById('aw-posted').hidden)")
        def choose(selector, value):
            control = page.locator(selector)
            label = control.locator('option[value="' + str(value) + '"]').text_content()
            combo = control.locator('..').locator('.aw-record-selector')
            combo.get_by_role('combobox').fill(label)
            combo.locator('[role="option"][data-value="' + str(value) + '"]').click()
        def capture(name):
            for w, h in [(1366, 768), (1920, 1080)]:
                page.set_viewport_size({'width': w, 'height': h})
                page.evaluate('window.scrollTo(0,0)')
                check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'), name + ' fits ' + str(w))
                page.screenshot(path=str(run / (name + '-' + str(w) + '.png')), full_page=True)
            page.set_viewport_size({'width': 1366, 'height': 768})
        def review(endpoint):
            with page.expect_response(lambda r: r.url.endswith('/' + endpoint) and r.request.method == 'POST' and r.request.post_data_json.get('action') == 'review', timeout=120000) as response:
                page.locator('#aw-review').click()
            data = response.value.json()
            assert data.get('ok'), data.get('error', 'Review failed')
            page.wait_for_selector('#aw-review-panel:not([hidden])'); ready()
            return data['result']
        def record(endpoint):
            with page.expect_response(lambda r: r.url.endswith('/' + endpoint) and r.request.method == 'POST' and r.request.post_data_json.get('action') == 'post', timeout=120000) as response:
                page.locator('#aw-post').focus(); page.keyboard.press('Enter')
            data = response.value.json()
            assert data.get('ok'), data.get('error', 'Post failed')
            page.wait_for_selector('#aw-posted:not([hidden])'); ready()
            return data['result']
        def support(amount=None):
            group = page.locator('#aw-document-group')
            if group.count() and not group.get_attribute('open'): group.locator('summary').click()
            page.locator('#aw-upload').set_input_files({'name': 'Synthetic U5 proof.png', 'mimeType': 'image/png', 'buffer': png()})
            page.wait_for_function("document.querySelector('#aw-status').textContent.includes('Image stored')")
            ready(); doc = page.locator('[data-document]').last
            if amount:
                doc.locator('[data-doc-field=purpose]').select_option('amount')
                doc.locator('[data-doc-field=declared_amount]').fill(amount)
                doc.locator('[data-doc-field=accepted_amount]').fill(amount)
                doc.locator('[data-allocation]').fill(amount)
            doc.locator('[data-doc-field=reviewed]').check()
            return doc
        def advance(kind, aid=None):
            page.goto(base + '/cash_advance_entry.php?workflow_kind=' + kind + ('&advance_id=' + str(aid) if aid else ''))
            ready()
        def fill_release(amount):
            choose('#aw-party', s2['employee']); choose('#aw-control-account', s2['control']); choose('#aw-cash-account', f['bank'])
            page.locator('#aw-purpose').fill('Synthetic U5 community training advance')
            page.locator('#aw-cash-amount').fill(amount)
            page.locator('#aw-due-date').fill(page.locator('#aw-date').input_value())
        def detail(aid):
            data = admin.request.get(base + '/cash_advance_actions.php?action=advance&advance_id=' + str(aid), timeout=120000).json()
            assert data['ok']; return data['result']
        def balanced(jid):
            rows = sql('SELECT SUM(debit_amount) debit,SUM(credit_amount) credit,COUNT(*) n FROM journal_entry_lines WHERE journal_entry_id=' + str(int(jid)))
            check(Decimal(rows[0]['debit']) == Decimal(rows[0]['credit']) > 0 and int(rows[0]['n']) >= 2, 'Exact journal #' + str(jid) + ' is balanced')

        opening = balances()
        # A separate save/resume/error variant, outside the matched simple-task traces.
        page.goto(base + '/cash_receipt.php'); ready()
        choose('#aw-party', f['party']); page.locator('#aw-purpose').fill('Synthetic U5 save-close retest')
        choose('#aw-cash-account', f['bank']); page.locator('#aw-cash-amount').fill('5,000')
        choose('[data-field="account_id"]', f['income'])
        check(page.get_by_role('button', name='Save draft', exact=True).count() == 0 and page.get_by_role('button', name='Save and close', exact=True).count() == 1, 'Focused receipt has one explicit draft-saving action')
        capture('receipt-single-save')
        page.locator('#aw-save').focus(); page.keyboard.press('Enter')
        page.wait_for_url('**/accounting_drafts.php')
        row = page.locator('[data-draft-id]').first
        saved_id = int(row.get_attribute('data-draft-id'))
        check('Synthetic U5 save-close retest' in row.inner_text(), 'Save and close returns the new receipt to owner My drafts')
        page.locator('[data-draft-id="' + str(saved_id) + '"]').get_by_role('link', name='Continue', exact=True).click(); ready()
        saved_url = page.url
        payload = json.loads(sql('SELECT payload FROM journal_drafts WHERE id=' + str(saved_id))[0]['payload'])
        check(payload['cash_amount'] == payload['lines'][0]['credit_amount'] == '5000', 'Grouped ordinary amount saves exact canonical cash and counterpart decimals')
        check(page.locator('#entry-workspace').get_attribute('data-payment-mode') == 'quick' and page.locator('#aw-cash-amount').input_value() == '5000', 'Saved receipt resumes in Quick form with exact amount')
        capture('saved-receipt-resumed')
        r = review('accounting_actions.php')
        check(len(r['lines']) == 2 and sum(Decimal(l['debit_amount']) for l in r['lines']) == sum(Decimal(l['credit_amount']) for l in r['lines']) == Decimal('5000.00'), 'Grouped receipt reaches authoritative balanced Review without advanced-mode fallback')
        page.locator('#aw-back').click(); page.locator('#aw-purpose').fill('Synthetic U5 saved response interruption')
        dropped = []
        def drop_save(route):
            if route.request.method == 'POST' and route.request.post_data_json.get('action') == 'save':
                dropped.append(1); route.fetch(); route.abort()
            else: route.continue_()
        page.route('**/accounting_actions.php', drop_save)
        page.locator('#aw-save').click()
        page.wait_for_function("document.querySelector('#aw-error').textContent.includes('interrupted')"); ready()
        check(page.url == saved_url and len(dropped) == 1 and page.locator('#aw-purpose').input_value() == 'Synthetic U5 saved response interruption', 'Lost save response stays on populated form without navigation or automatic save replay')
        page.unroute('**/accounting_actions.php', drop_save)
        page.goto(saved_url); ready()
        check(page.locator('#aw-purpose').input_value() == 'Synthetic U5 saved response interruption', 'Read-only reopen recovers the same confirmed saved draft after lost response')
        check(sql('SELECT state,posted_journal_id FROM journal_drafts WHERE id=' + str(saved_id))[0] == {'state':'Draft','posted_journal_id':None}, 'Save/resume variant creates no financial record')
        # Reproduce the raw grouped draft accepted before this presentation fix.
        old = admin.request.get(base + '/accounting_actions.php?action=draft&draft_id=' + str(saved_id)).json()['result']
        legacy = old['payload']; legacy['cash_amount'] = legacy['lines'][0]['credit_amount'] = '5,000'
        cfg = json.loads(page.locator('#workspace-data').text_content())
        seeded = admin.request.post(base + '/accounting_actions.php', data={'action':'save','csrf_token':cfg['csrf_token'],'draft_id':str(saved_id),'revision':str(old['revision']),'source_book':'CRB','submission_key':old['submission_key'],'payload':legacy}).json()
        check(seeded['ok'], 'Existing save contract reproduces a pre-fix grouped synthetic draft')
        page.goto(saved_url); ready()
        check(page.locator('#entry-workspace').get_attribute('data-payment-mode') == 'quick' and 'save required' in page.locator('#aw-draft-label').inner_text(), 'Legacy grouped draft resumes Quick and explicitly requires saving its normalized formatting')
        review('accounting_actions.php')
        check(sql('SELECT state,posted_journal_id FROM journal_drafts WHERE id=' + str(saved_id))[0] == {'state':'Draft','posted_journal_id':None}, 'Legacy normalization and Review remain unposted')
        for task, route, account, amount, book in [('payment', 'cash_disbursement.php', f['training'], '1000', 'CDB'), ('receipt', 'cash_receipt.php', f['income'], '5000', 'CRB')]:
            page.goto(base + '/dashboard.php', wait_until='networkidle')
            navigate_sidebar(page,route); ready()
            choose('#aw-party', f['party']); page.locator('#aw-purpose').fill('Synthetic U5 ' + task)
            choose('#aw-cash-account', f['bank']); page.locator('#aw-cash-amount').fill(amount)
            choose('[data-field="account_id"]', account)
            check(page.locator('#aw-default-project').input_value() == '', task + ' retains explicit organization-wide default')
            r = review('accounting_actions.php')
            check(r['coverage']['status'] == 'No monetary support', task + ' permits truthful optional no-support state')
            result = record('accounting_actions.php'); jid = int(result['id']); balanced(jid); outcomes[task] = result
            check('journal_id=' + str(jid) in page.locator('#aw-exact-link').get_attribute('href'), task + ' records exact returned identity')
            check(sql('SELECT source_book FROM journal_entries WHERE id=' + str(jid))[0]['source_book'] == book, task + ' retains source book')
            traces.append({'task': task, 'classification': 'newly reproduced automation', 'navigation_controls': 1, 'page_changes': 1, 'fields': 5, 'progression': 2, 'required_evidence': 0, 'optional': 0})
            capture(task + '-recorded')

        page.goto(base + '/cash_advances.php'); page.get_by_role('link', name='Release cash advance', exact=True).click(); ready()
        check(page.locator('#aw-control-account').input_value() == '', 'Release control account requires explicit selection')
        fill_release('10000'); r = review('cash_advance_actions.php')
        check('not an expense' in page.locator('#aw-review-content').inner_text(), 'Release clearly creates an advance asset')
        result = record('cash_advance_actions.php'); outcomes['release'] = result; aid = int(result['advance_id']); balanced(result['id'])
        check(detail(aid)['outstanding'] == '10000.00', 'Released advance has exact 10000 outstanding')
        check(page.locator('#aw-advance-context').is_hidden(), 'Recorded release hides pre-operation context')
        page.locator('#aw-advance-link').click(); page.get_by_role('link', name='Record liquidation', exact=True).click(); ready()
        page.locator('#aw-purpose').fill('Synthetic U5 supported training costs')
        lid = page.locator('[data-line]').first.get_attribute('data-line')
        choose('#aw-line-' + lid + '-account', f['training']); page.locator('[data-field=debit_amount]').first.fill('8000')
        page.locator('#aw-review').click(); page.wait_for_function("document.querySelector('#aw-error').textContent.includes('Fully cover')")
        check(page.locator('#aw-review-panel').is_hidden(), 'Liquidation cannot review unsupported gross costs')
        support('8000'); r = review('cash_advance_actions.php')
        check('PHP 2,000.00' in page.locator('#aw-review-content').inner_text(), 'Liquidation review shows server remaining 2000')
        capture('liquidation-review'); result = record('cash_advance_actions.php'); outcomes['liquidation'] = result; balanced(result['id'])
        check(detail(aid)['outstanding'] == '2000.00', 'Same advance retains exact 2000 after liquidation')
        check(page.locator('#aw-advance-context').is_hidden(), 'Recorded liquidation hides stale pre-liquidation balance')
        page.locator('#aw-advance-link').click(); page.get_by_role('link', name='Return unused cash', exact=True).click(); ready()
        page.locator('#aw-purpose').fill('Synthetic U5 unused cash returned'); choose('#aw-cash-account', f['bank']); page.locator('#aw-cash-amount').fill('2000')
        support(); page.locator('#aw-confirm-proof').click(); page.wait_for_selector('#aw-proof-status[data-confirmed=true]')
        check(page.locator('#aw-review-panel').is_hidden(), 'Proof confirmation remains separate from Review')
        review('cash_advance_actions.php'); capture('return-review'); result = record('cash_advance_actions.php'); outcomes['return'] = result; balanced(result['id'])
        d = detail(aid)
        check(d['outstanding'] == '0.00' and d['status'] == 'settled', '10000 release / 8000 liquidation / 2000 return settles the same advance')
        check(page.locator('#aw-advance-context').is_hidden(), 'Recorded cash return hides stale pre-return balance')

        payment = int(outcomes['payment']['id'])
        original = sql('SELECT * FROM journal_entries WHERE id=' + str(payment))
        original_lines = sql('SELECT * FROM journal_entry_lines WHERE journal_entry_id=' + str(payment) + ' ORDER BY id')
        page.goto(base + '/financial_records.php'); search = page.locator('.dt-search input'); search.fill('Synthetic U5 payment')
        page.wait_for_function("document.querySelector('#records-table tbody').textContent.includes('Synthetic U5 payment')")
        check(page.locator('#records-table tbody .records-journal-identity').all_text_contents() == ['Journal #' + str(payment)] * 2, 'Both payment lines visibly share the exact posted journal identity')
        page.locator('#records-table .records-view').first.click()
        page.get_by_role('link', name='View complete transaction #' + str(payment), exact=True).click()
        page.get_by_role('link', name='Correct a recorded transaction', exact=True).click(); ready()
        page.locator('#aw-correction-reason').fill('Synthetic U5 correction of training amount')
        page.locator('#aw-cash-amount').fill('900'); page.locator('#aw-lines [data-field=debit_amount]').first.fill('900')
        review('journal_correction_actions.php')
        check('Preview' in page.locator('#aw-review-content').inner_text() or 'preview' in page.locator('#aw-review-content').inner_text(), 'Generated correction journals remain previews before recording')
        capture('correction-review'); result = record('journal_correction_actions.php'); outcomes['correction'] = result
        for field in ['reversal_journal_id', 'replacement_journal_id']: balanced(result[field])
        check(sql('SELECT * FROM journal_entries WHERE id=' + str(payment)) == original and sql('SELECT * FROM journal_entry_lines WHERE journal_entry_id=' + str(payment) + ' ORDER BY id') == original_lines, 'Correction preserves the original posted journal and lines exactly')
        closing = balances()
        expected = {int(f['bank']): Decimal('-3900'), int(f['training']): Decimal('8900'), int(f['income']): Decimal('-5000'), int(s2['control']): Decimal('0')}
        for account, delta in expected.items(): check(closing.get(account, Decimal(0)) - opening.get(account, Decimal(0)) == delta, 'Integrated authoritative account delta #' + str(account) + ' = ' + str(delta))
        check(int(sql('SELECT COUNT(*) n FROM journal_entries WHERE id IN (' + ','.join(str(outcomes[x]['id']) for x in ['payment', 'receipt', 'release', 'liquidation', 'return']) + ") AND status='posted'")[0]['n']) == 5, 'Original recorded entries remain in gross history after correction')

        # A separate 4500 gross / 500 liability / 4000 reduction example.
        advance('advance_release'); fill_release('4000'); review('cash_advance_actions.php'); gross_release = record('cash_advance_actions.php'); gaid = int(gross_release['advance_id'])
        advance('advance_liquidation', gaid); page.locator('#aw-purpose').fill('Synthetic U5 gross costs and manually assessed liability')
        lid = page.locator('[data-line]').first.get_attribute('data-line'); choose('#aw-line-' + lid + '-account', f['training']); page.locator('[data-field=debit_amount]').first.fill('4500')
        page.locator('#aw-add-line').click(); tid = page.locator('[data-line]').last.get_attribute('data-line'); choose('#aw-line-' + tid + '-account', f['tax']); page.locator('#aw-line-' + tid + '-credit').fill('500'); page.locator('#aw-line-' + tid + '-note').fill('Synthetic manually assessed liability, no client tax-policy claim')
        doc = support('4000'); page.locator('#aw-review').click(); page.wait_for_function("document.querySelector('#aw-error').textContent.includes('Fully cover')")
        check(page.locator('#aw-review-panel').is_hidden(), '4000 net support cannot cover 4500 gross costs')
        for name in ['declared_amount', 'accepted_amount']: doc.locator('[data-doc-field=' + name + ']').fill('4500')
        doc.locator('[data-allocation]').fill('4500'); doc.locator('[data-doc-field=reviewed]').check()
        check(doc.locator('[data-allocation]').count() == 1, 'Neither liability nor generated control credit is an evidence target')
        review('cash_advance_actions.php'); capture('gross-net-review'); result = record('cash_advance_actions.php'); outcomes['gross_liquidation'] = result; balanced(result['id'])
        check(detail(gaid)['outstanding'] == '0.00', '4500 costs less 500 liability reduces advance by exactly 4000')

        before_reads = fingerprint()
        cmpurl = base + '/journal_corrections.php?journal_id=' + str(payment)
        ar = admin.request.get(cmpurl, timeout=120000); mr = manager.request.get(cmpurl, timeout=120000)
        check(ar.status == mr.status == 200, 'Admin and Management can inspect exact posted correction comparison')
        mp = manager.new_page(); mp.on('pageerror', lambda e: errors.append(str(e))); mp.goto(cmpurl, timeout=120000)
        check(mp.locator('.correction-journal table').count() == 3, 'Management sees complete original/reversal/replacement journals')
        mp.emulate_media(media='print'); check(mp.locator('.correction-journal table').count() == 3, 'Management print preserves full journal content')
        md = manager.request.get(base + '/cash_advance_actions.php?action=advance&advance_id=' + str(aid), timeout=120000).json()['result']
        check('external_approval' not in md and all('attachments' not in o and 'private_review' not in o for o in md['operations']), 'Management advance JSON excludes private evidence/review properties')
        for op in d['operations']:
            for attachment in op.get('attachments', []):
                denied = manager.request.get(base + '/' + attachment['url'])
                check(denied.status == 404 and not denied.headers.get('content-type', '').startswith('image/'), 'Management direct private advance proof download denied without disclosing existence')
        for route in ['cash_disbursement.php', 'cash_advance_entry.php', 'journal_correction.php?journal_id=' + str(payment), 'accounting_drafts.php']:
            check(manager.request.get(base + '/' + route).status == 403, 'Management authoring/private route denied: ' + route.split('?')[0])
        page.goto(base + '/cash_advances.php?advance_id=' + str(aid)); capture('settled-advance')
        check(page.locator('.ca-settlement-status').inner_text() == 'Settlement status: Settled' and page.locator('nav[aria-label="Settle this advance"]').count() == 0 and page.locator('.ca-status-line .ca-badge').count() == 0 and 'Due ' in page.locator('.ca-status-line').inner_text(), 'Settled status is explicitly labelled and has no settlement actions')
        historical = (date.fromisoformat(page.locator('input[name=as_of]').first.input_value()) - timedelta(days=1)).isoformat()
        h = admin.request.get(base + '/cash_advance_actions.php?action=register&as_of=' + historical, timeout=120000).json()
        check(h['ok'] and all(int(row['id']) not in [aid, gaid] for row in h['result']['rows']), 'Earlier as-of register does not invent today\'s new advances')
        register = admin.request.get(base + '/cash_advance_actions.php?action=register', timeout=120000).json()['result']
        check(all(row['ok'] for row in register['reconciliation']), 'Full control-account reconciliation passes after the connected workflow')
        check(fingerprint() == before_reads, 'Read/print/privacy inspection changes no application table')
        verify = '<?php require ' + ps(app / 'config.php') + ';require ' + ps(app / 'db_connect.php') + ';require ' + ps(app / 'includes/journal_corrections.php') + ';$problems=correction_integrity($pdo);$n=0;foreach($pdo->query("SELECT r.* FROM Receipts r WHERE r.JournalEntryID IN (' + ','.join(str(outcomes[k]['id']) for k in ['liquidation', 'return', 'gross_liquidation']) + ')") as $r){receipt_file_verify($r);$n++;}echo json_encode(["problems"=>$problems,"images"=>$n]);'
        verified = json.loads(subprocess.check_output([php], input=verify, text=True, encoding='utf-8'))
        check(verified['problems'] == [], 'Final connected correction, association, balance, reservation and control integrity has no findings')
        check(verified['images'] == 3, 'All three newly posted synthetic proof files match recorded bytes and metadata')
        check(not errors, 'No uncaught JavaScript errors')
        admin.close(); manager.close(); browser.close(); passed = True
finally:
    server.terminate(); server.wait(timeout=10); log.close()
    result = {'passed': passed, 'checks': len(checks), 'labels': checks, 'errors': errors, 'outcomes': outcomes, 'action_traces': traces, 'scope': 'new isolated synthetic clone; automation, not human observation'}
    (run / 'results.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
    print('U5_BROWSER_RUN: ' + str(run), flush=True)
