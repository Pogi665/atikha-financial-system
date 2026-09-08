"""Exercise production routes in an isolated application copy and disposable database.
SMTP/Gemini configuration is deliberately not copied. No external services are called.
"""
import argparse
from datetime import date
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
source = source.replace("$host = '127.0.0.1';", "$host = '" + os.environ.get('ATIKHA_DB_HOST', '127.0.0.1') + "';")
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
    fund = {'action':'create','csrf_token':fcsrf,'purpose':'HTTP funding purpose','source_donor':'HTTP Fixture','category':'Donation','project_code':'FIXTURE','amount':'1234.56','date_received':f'{date.today().year}-09-01'}
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
    save={'action':'save','csrf_token':ocsrf,'receipt_id':rid,'purpose':'Receipt confirmed purpose','project_code':'SHARED','payee':'Receipt Fixture','category':'Equipment','amount':'-5','date_incurred':f'{date.today().year}-09-01'}
    check('valid values' in request(admin,'ocr_expense.php',save)[1],'Receipt save rejects invalid amount')
    save['amount']='3830.40';request(admin,'ocr_expense.php',save)
    check(fixture('--inspect')['receipts'][-1]['ExpenseID'] is not None,'Admin confirms receipt as expense')
    receipt_expense = fixture('--inspect')['expenses'][-1]
    check(receipt_expense['Purpose']=='Receipt confirmed purpose' and receipt_expense['Project_Code']=='SHARED', 'OCR saves user-authored purpose and allocation')
    invalid_receipt = fixture('--email=admin@example.invalid','--receipt')['receipt_id']
    missing = dict(save, receipt_id=invalid_receipt, purpose=' ')
    check('Purpose is required' in request(admin,'ocr_expense.php',missing)[1], 'OCR rejects missing purpose')
    retry = dict(save, receipt_id=invalid_receipt, purpose='Keep my purpose', project_code='KEEP', amount='-1')
    retry_body = request(admin,'ocr_expense.php',retry)[1]
    check('Keep my purpose' in retry_body and 'value="KEEP"' in retry_body, 'OCR failed save retains authored fields')
    missing_fund = dict(fund, purpose=' ')
    check('Purpose is required' in request(admin,'funds.php',missing_fund)[1], 'Fund edits require purpose')
    expense = {'action':'create','csrf_token':ocsrf,'payee':'Manual Fixture','category':'Equipment','purpose':'Manual purpose','project_code':'SHARED','amount':'1.23','date_incurred':f'{date.today().year}-09-02'}
    check('saved successfully' in request(admin,'expenses.php',expense)[1], 'Manual expense saves metadata')
    eid = fixture('--inspect')['expenses'][-1]['ExpenseID']
    expense.update(action='update',expense_id=eid,purpose='Edited purpose',project_code='')
    check('Expense updated' in request(admin,'expenses.php',expense)[1], 'Manual expense edit saves metadata')
    stored = fixture('--inspect')['expenses'][-1]
    check(stored['Purpose']=='Edited purpose' and stored['Project_Code'] is None, 'Expense edit preserves legitimate Unallocated NULL')
    check('Purpose is required' in request(admin,'expenses.php',dict(expense,purpose=''))[1], 'Expense edit rejects blank purpose')
    check('valid values' in request(admin,'funds.php',dict(fund,project_code='x'*51))[1], 'Overlength allocation rejected')
    check('valid values' in request(admin,'funds.php',dict(fund,amount='0.001'))[1], 'Fund rejects amount rounding to zero')
    check('valid values' in request(admin,'expenses.php',dict(expense,amount='0.001'))[1], 'Expense rejects amount rounding to zero')
    check('valid values' in request(admin,'ocr_expense.php',dict(save,receipt_id=invalid_receipt,amount='0.001'))[1], 'OCR rejects amount rounding to zero')
    state = fixture('--inspect')
    check(all(float(row['Amount']) > 0 for row in state['funds'] + state['expenses']), 'Source amounts remain positive')
    audited = [json.loads(row['new_values']) for row in state['audits'] if row['new_values']]
    check(any(row.get('purpose')=='HTTP funding purpose' and row.get('project_code')=='UPDATED' for row in audited), 'Fund audit includes both fields')
    check(any(row.get('purpose')=='Edited purpose' and row.get('project_code') is None for row in audited), 'Expense audit includes changed metadata')
    check(any(row.get('purpose')=='Receipt confirmed purpose' and row.get('project_code')=='SHARED' for row in audited), 'OCR audit includes user-authored metadata')
    historical = fixture('--report-fixture')
    yr = historical['year']
    report_url = f'reports.php?month=02&year={yr}'
    for viewer, role in [(admin,'Admin'),(management,'Management')]:
        report = request(viewer,report_url)[1]
        (run / (role.lower()+'-report.html')).write_text(report,encoding='utf-8')
        check(f'February {yr}' in report and 'Historical &lt;donor&gt;' in report and 'HTTP Fixture' not in report.split('Detailed Transaction Report',1)[1], role+' historical month selected')
        check('Opening Organization Balance: '+chr(8369)+'75.05' in report and 'Closing Organization Balance: -'+chr(8369)+'14.93' in report, role+' opening and closing balances rendered')
        check('3 records need attention' in report and '2 missing Purpose' in report and '2 Unallocated' in report, role+' completeness counts visible')
        check('Purpose &lt;b&gt; &amp; verified' in report and 'SHARED' in report and 'Not specified' in report, role+' database metadata escaped and legacy shown')
        check('Panel fund' in report and 'Panel expense' in report and 'Organization Balance After Transaction' in report, role+' category consistency and balance label')
        check(report.index('Incoming-'+str(historical['first'])) < report.index('Incoming-'+str(historical['fund'])) < report.index('Expense-'+str(historical['expense'])), role+' deterministic report order')
        (run / (role.lower()+'-report.html')).write_text(report,encoding='utf-8')
    records = request(admin,f'financial_records.php?from={yr}-02-01&to={yr}-02-28')[1]
    check('3 records need attention' in records and 'Purpose &lt;b&gt;' in records and 'Organization Balance After Transaction' in records, 'Financial Records matches detailed report')
    empty = request(admin,f'reports.php?month=04&year={yr}')[1]
    check('No records match' in empty and empty.count('-'+chr(8369)+'14.93')==2 and 'records need attention' not in empty, 'Empty month carries balance without false completeness notice')
    legacy_fund = dict(fund, fund_id=historical['fund'], category='Panel fund',date_received=f'{yr}-02-01',amount='0.02',purpose='')
    legacy_expense = dict(expense,expense_id=historical['expense'],category='Panel expense',date_incurred=f'{yr}-02-28',amount='0.01',purpose='')
    check('Purpose is required' in request(admin,'funds.php',legacy_fund)[1] and 'Purpose is required' in request(admin,'expenses.php',legacy_expense)[1], 'Both legacy edit routes reject missing purpose')
    check('updated' in request(admin,'funds.php',dict(legacy_fund,purpose='Confirmed legacy purpose',project_code=''))[1], 'Legacy fund can be corrected and remain Unallocated')
    fixture('--report-error=on')
    try:
        error_report = request(admin,report_url)[1]
        check('Unable to generate this report' in error_report and 'Closing Organization Balance:' not in error_report and 'Net Income' not in error_report, 'Report database error suppresses totals')
        error_records = request(admin,'financial_records.php')[1]
        check('Unable to load financial records' in error_records and 'No records match' not in error_records, 'Financial Records distinguishes error from empty')
    finally:
        fixture('--report-error=off')
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
