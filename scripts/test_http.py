"""Exercise production routes in an isolated application copy and disposable database.
SMTP/Gemini configuration is deliberately not copied. No external services are called.
"""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
args = parser.parse_args()
if not re.fullmatch(r'atikha_test_[a-z0-9_]+', args.database):
    parser.error('Only an explicit disposable database is allowed')
root = Path(__file__).resolve().parent.parent
php = shutil.which('php') or r'C:\xampp\php\php.exe'
run = root / '.migration-private' / ('http-' + secrets.token_hex(6))
app = run / 'app'
app.mkdir(parents=True)
sessions = run / 'sessions'
sessions.mkdir()
for path in root.glob('*.php'):
    if path.name != 'config.php': shutil.copy2(path, app / path.name)
for name in ['includes', 'assets', 'vendor', 'scripts']:
    shutil.copytree(root / name, app / name)
for name in ['receipts', 'board']:
    (app / 'uploads' / name).mkdir(parents=True)
connection = app / 'db_connect.php'
source = connection.read_text(encoding='utf-8')
assert "$db   = 'atikha_finance';" in source
connection.write_text(source.replace("$db   = 'atikha_finance';", "$db   = '" + args.database + "';"), encoding='utf-8')
router = run / 'router.php'
router.write_text("<?php if (preg_match('~^/(scripts|includes|vendor)/~', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) { http_response_code(404); exit; } return false;", encoding='utf-8')
sock = socket.socket(); sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]; sock.close()
base = f'http://127.0.0.1:{port}'
log = (run / 'server.log').open('w', encoding='utf-8')
server = subprocess.Popen([php, '-d', f'session.save_path={sessions}', '-S', f'127.0.0.1:{port}', '-t', str(app), str(router)], stdout=log, stderr=log)
checks = 0
def check(condition, label):
    global checks
    if not condition: raise AssertionError(label)
    checks += 1
    print('PASS:', label)
def fixture(*extra):
    return json.loads(subprocess.check_output([php, str(app / 'scripts/http_fixture.php'), '--database='+args.database, '--sessions='+str(sessions), *extra], text=True))
def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar)), jar
def request(opener, path, data=None):
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        response = opener.open(base + '/' + path, payload, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8', errors='replace')
    check('Fatal error' not in body and 'Warning:' not in body, path + ' has no PHP runtime errors')
    return response.status, body
def login(email):
    f = fixture('--email='+email)
    opener, jar = client()
    jar.set_cookie(http.cookiejar.Cookie(0, 'PHPSESSID', f['session'], None, False, '127.0.0.1', False, False, '/', True, False, None, True, None, None, {}))
    status, body = request(opener, 'mfa_verify.php', {'mfa_code':f['code'], 'csrf_token':f['csrf']})
    check(status == 200 and 'Dashboard' in body, 'Real MFA verification completes for '+email)
    return opener
def token(opener, page):
    status, body = request(opener, page)
    match = re.search(r'(?:name="csrf_token" value|data-csrf)="([^"]+)"', body)
    check(status == 200 and bool(match), page + ' provides CSRF token')
    return match.group(1)
try:
    for attempt in range(50):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.2): break
        except OSError: time.sleep(.1)
    admin = login('admin@example.invalid')
    csrf = token(admin, 'admin_users.php')
    email = 'management-'+secrets.token_hex(4)+'@example.invalid'
    newuser = {'action':'create_user', 'csrf_token':csrf, 'full_name':'Fixture Management', 'email':email, 'role':'Management', 'password':secrets.token_urlsafe(24)}
    status, body = request(admin, 'admin_users.php', newuser)
    check('User account created successfully' in body, 'Admin creates Management through existing feature')
    newuser.update(email='invalid-'+email, role='Staff')
    status, body = request(admin, 'admin_users.php', newuser)
    check('Select a valid role' in body, 'Account creation rejects retired operational role')
    state = fixture('--inspect')
    check(state['identity_count'] == len(state['users']), 'User creation registers historical identity')
    management = login(email)
    for page in ['dashboard.php','financial_records.php','reports.php','management_reviews.php','board_inbox.php']:
        check(request(management,page)[0] == 200, 'Management retains '+page)
    for page in ['funds.php','expenses.php','ocr_expense.php','board_messages.php','admin_users.php','audit_trail.php']:
        check(request(management,page)[0] == 403, 'Management denied '+page)
    for page in ['funds.php','expenses.php','ocr_expense.php','board_messages.php','admin_users.php','audit_trail.php']:
        check(request(admin,page)[0] == 200, 'Admin retains '+page)
    fcsrf = token(admin,'funds.php')
    fund = {'action':'create','csrf_token':fcsrf,'source_donor':'HTTP Fixture','category':'Donation','project_code':'FIXTURE','amount':'1234.56','date_received':'2026-09-01'}
    check('saved successfully' in request(admin,'funds.php',fund)[1], 'Admin records fund')
    fid = fixture('--inspect')['funds'][-1]['FundID']
    fund.update(action='update',fund_id=fid,project_code='UPDATED')
    check('Incoming fund updated' in request(admin,'funds.php',fund)[1], 'Admin edits fund')
    mcsrf = token(management,'management_reviews.php')
    check(request(management,'funds.php',dict(fund,csrf_token=mcsrf))[0] == 403, 'Management direct financial POST denied')
    r = request(admin,'review_actions.php',{'csrf_token':fcsrf,'action':'send_for_review','entity_type':'fund','entity_id':fid})
    check(r[0] == 200 and json.loads(r[1])['ok'], 'Admin submits fund for review')
    r = request(management,'review_actions.php',{'csrf_token':mcsrf,'action':'mark_reviewed','entity_type':'fund','entity_id':fid,'review_notes':'Fixture review note'})
    check(r[0] == 200 and json.loads(r[1])['ok'], 'Management marks reviewed and adds notes')
    check(fixture('--inspect')['funds'][-1]['Review_Notes']=='Fixture review note','Review note persisted')
    r = request(management,'review_actions.php',{'csrf_token':mcsrf,'action':'send_for_review','entity_type':'fund','entity_id':fid})
    check(r[0] == 403, 'Management cannot submit financial records')
    r = request(management,'forecast_ai.php',{'csrf_token':mcsrf,'action':'refresh'})
    check(r[0] == 200 and json.loads(r[1])['ok'], 'Management forecast refresh permitted (insufficient-history path, no API call)')
    ocsrf = token(admin,'ocr_expense.php')
    check(request(admin,'ocr_extract.php',{'csrf_token':ocsrf})[0] == 400,'OCR missing-file validation')
    check(request(admin,'ocr_extract.php',{'csrf_token':'invalid'})[0] == 400,'OCR CSRF validation')
    check(request(management,'ocr_extract.php',{'csrf_token':mcsrf})[0] == 403,'Management OCR denied')
    anonymous,_ = client()
    check(request(anonymous,'ocr_extract.php',{'csrf_token':'invalid'})[0] == 401,'Anonymous OCR denied')
    rid = fixture('--email=admin@example.invalid','--receipt')['receipt_id']
    save={'action':'save','csrf_token':ocsrf,'receipt_id':rid,'payee':'Receipt Fixture','category':'Equipment','amount':'-5','date_incurred':'2026-09-01'}
    check('valid values' in request(admin,'ocr_expense.php',save)[1],'Receipt save rejects invalid amount')
    save['amount']='3830.40';request(admin,'ocr_expense.php',save)
    check(fixture('--inspect')['receipts'][-1]['ExpenseID'] is not None,'Admin confirms receipt as expense')
    bcsrf=token(admin,'board_messages.php')
    check('message was sent' in request(admin,'board_messages.php',{'csrf_token':bcsrf,'subject':'Fixture board message','message_body':'Isolated review test'})[1],'Admin sends board message')
    check('Fixture board message' in request(management,'board_inbox.php?filter=all')[1],'Management inbox shows submission')
    check('Incoming fund deleted' in request(admin,'funds.php',{'csrf_token':fcsrf,'action':'delete','fund_id':fid})[1],'Admin retains financial deletion')
    check(request(anonymous,'scripts/bootstrap_admin.php')[0] == 404,'CLI scripts unavailable over HTTP')
    print(f'PASS: {checks} HTTP assertions; isolated copy: {run}')
finally:
    server.terminate()
    try: server.wait(timeout=5)
    except subprocess.TimeoutExpired: server.kill(); server.wait()
    log.close()
