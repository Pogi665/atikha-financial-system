"""Isolated Management dashboard integration checks. Creates a NEW disposable DB.
No live application config is copied; Gemini is stubbed only in the isolated copy.
Database/artifacts are retained for inspection, never reused or automatically dropped.
"""
from pathlib import Path
import json
import re
import secrets
import shutil
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

root = Path(__file__).resolve().parent.parent
php = shutil.which('php') or r'C:\xampp\php\php.exe'
database = 'atikha_test_dashboard_' + secrets.token_hex(6)
run = root / '.migration-private' / database
app = run / 'app'
app.mkdir(parents=True)
sessions = run / 'sessions'
sessions.mkdir()
for name in ('includes', 'assets'):
    shutil.copytree(root / name, app / name)
for path in root.glob('*.php'):
    if path.name != 'config.php':
        shutil.copy2(path, app / path.name)
connection = app / 'db_connect.php'
connection.write_text(connection.read_text(encoding='utf-8').replace("$db   = 'atikha_finance';", "$db   = '" + database + "';"), encoding='utf-8')
fixture = run / 'fixture.php'
fixture.write_text('''<?php
require ''' + json.dumps(str(root / 'scripts/cli_common.php').replace('\\', '/')) + ''';
require ''' + json.dumps(str(root / 'includes/admin_bootstrap.php').replace('\\', '/')) + ''';
$db=$argv[1]; cli_require((bool)preg_match('/^atikha_test_dashboard_[a-f0-9]+$/',$db),'Disposable only');
$server=new PDO('mysql:host=127.0.0.1;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$action=$argv[2];
if ($action==='init') { $server->exec("CREATE DATABASE `$db`"); }
$pdo=cli_db($db);
if ($action==='init') {
 cli_sql_file($pdo, ''' + json.dumps(str(root / 'database.sql').replace('\\', '/')) + ''');
 foreach(glob(''' + json.dumps(str(root / 'migrations/*.sql').replace('\\', '/')) + ''') as $path) {
  if (!str_starts_with(basename($path),'011_')) cli_sql_file($pdo,$path);
 }
 foreach(['Incoming_Funds','Expenses'] as $table) {
  if (!$pdo->query("SHOW COLUMNS FROM $table LIKE 'Reference_Number'")->fetch()) $pdo->exec("ALTER TABLE $table ADD COLUMN Reference_Number VARCHAR(100) NULL");
 }
 cli_sql_file($pdo, ''' + json.dumps(str(root / 'migrations/011_transaction_reporting.sql').replace('\\', '/')) + ''');
 bootstrap_admin($pdo,'Fixture Admin','admin@example.invalid',bin2hex(random_bytes(20)));
 $pdo->exec("INSERT INTO Users(FullName,Role,Email,Password) VALUES ('Fixture Management','Management','management@example.invalid','unused')");
 user_identity_create($pdo,(int)$pdo->lastInsertId());
 $pdo->exec('DELETE FROM Budgets');
 echo json_encode(['database'=>$db]);
} elseif ($action==='session') {
 session_save_path($argv[3]); session_id(bin2hex(random_bytes(16))); session_start();
 $_SESSION=['UserID'=>(int)$argv[4],'Role'=>$argv[4]==='1'?'Admin':'Management','csrf_token'=>'fixture-csrf'];
 echo session_id(); session_write_close();
} elseif ($action==='seed') {
 $budget=$pdo->prepare('INSERT INTO Budgets(Category,Year,Month,Amount) VALUES (?,YEAR(CURDATE()),MONTH(CURDATE()),?)');
 $expense=$pdo->prepare('INSERT INTO Expenses(Payee,Category,Amount,Date_Incurred,RecordedBy_UserID) VALUES (?,?,?,CURDATE(),1)');
 foreach ([['Zero spend',0,0],['Unallocated <&>',0,25],['Over',100,125],['Threshold 75',100,75],['Threshold 90',100,90],['Exact 100',100,100],['Below',100,74.99],['Near 90',100,89.99],['Negative allocation',-10,0],['Negative spend',100,-5],['Long '.str_repeat('category ',8),100,0]] as [$name,$amount,$spent]) {
  $budget->execute([$name,$amount]);
  if ($spent!=0) $expense->execute(['Fixture',$name,$spent]);
 }
 $pdo->exec("INSERT INTO Incoming_Funds(Source_Donor,Category,Amount,Date_Received,RecordedBy_UserID) VALUES ('Fixture donor','Donation',10000,CURDATE(),1)");
 foreach([1,2,3] as $ago) $pdo->exec("INSERT INTO Expenses(Payee,Category,Amount,Date_Incurred,RecordedBy_UserID) VALUES ('Fixture history','History',300,DATE_SUB(CURDATE(),INTERVAL $ago MONTH),1)");
 echo json_encode(['funds'=>$pdo->query('SELECT SUM(Amount) FROM Incoming_Funds')->fetchColumn(),'expenses'=>$pdo->query('SELECT SUM(Amount) FROM Expenses')->fetchColumn()]);
} elseif ($action==='budgets-off') { $pdo->exec('RENAME TABLE Budgets TO Budgets_unavailable'); }
elseif ($action==='budgets-on') { $pdo->exec('RENAME TABLE Budgets_unavailable TO Budgets'); }
elseif ($action==='expenses-date-off') { $pdo->exec('ALTER TABLE Expenses CHANGE Date_Incurred Date_unavailable DATE NOT NULL'); }
elseif ($action==='expenses-date-on') { $pdo->exec('ALTER TABLE Expenses CHANGE Date_unavailable Date_Incurred DATE NOT NULL'); }
elseif ($action==='cache-count') { echo $pdo->query('SELECT COUNT(*) FROM forecast_cache')->fetchColumn(); }
''', encoding='utf-8')

def fixture_call(action, *args):
    return subprocess.check_output([php, str(fixture), database, action, *args], text=True).strip()

checks = 0
def check(condition, label):
    global checks
    assert condition, label
    checks += 1
    print('PASS:', label)

fixture_call('init')
management = fixture_call('session', str(sessions), '2')
admin = fixture_call('session', str(sessions), '1')
sock = socket.socket()
sock.bind(('127.0.0.1', 0))
port = sock.getsockname()[1]
sock.close()
log = (run / 'server.log').open('w', encoding='utf-8')
server = subprocess.Popen([php, '-d', 'session.save_path=' + str(sessions), '-S', '127.0.0.1:' + str(port), '-t', str(app)], stdout=log, stderr=log)
def request(path, session=management, fields=None):
    request_data = urllib.parse.urlencode(fields).encode() if fields is not None else None
    req = urllib.request.Request('http://127.0.0.1:' + str(port) + '/' + path, request_data, {'Cookie': 'PHPSESSID=' + session})
    try:
        response = urllib.request.urlopen(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8')
    check('Fatal error' not in body and '<b>Warning' not in body, path + ' no PHP runtime errors')
    return response.status, body

try:
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.2): break
        except OSError: time.sleep(.1)
    code, empty = request('dashboard.php')
    check(code == 200 and 'No budget exceptions' in empty and 'No recorded budgets or expenses' in empty, 'Empty budget state')
    code, body = request('forecast_ai.php', fields={'action':'load','csrf_token':'fixture-csrf'})
    check(json.loads(body)['data']['state'] == 'insufficient', 'Insufficient forecast history')
    totals = json.loads(fixture_call('seed'))
    code, body = request('dashboard.php')
    (run / 'management.html').write_text(body, encoding='utf-8')
    check(all('₱' + format(value, ',.2f') in body for value in [float(totals['funds']), float(totals['expenses']), float(totals['funds'])-float(totals['expenses'])]), 'All-time totals reconcile with seeded transactions')
    check('No budget allocated · ₱25.00 spent' in body and 'Unallocated &lt;&amp;&gt;' in body, 'Zero allocation and escaping')
    zero_row = re.search(r'<tr[^>]*>\s*<th scope="row">Unallocated &lt;&amp;&gt;</th>(.*?)</tr>', body, re.S).group(1)
    check('N/A' in zero_row and 'md-bar' not in zero_row, 'Zero-budget spending has no percentage bar')
    check('No allocation or spending' in body and 'Negative recorded budget' in body and 'Negative recorded spending' in body, 'Zero and signed cases preserved')
    check('125.00%' in body and 'Over budget by ₱25.00' in body and 'Fully utilized' in body, 'Overrun and exact 100 percent')
    check('75% to below 90%' in body and '90% or above' in body, 'Approaching threshold labels')
    check(body.count('data-md-extra-row') == 5 and 'data-count="11"' in body, 'All eleven categories rendered with six-row enhancement')
    check(body.index('id="md-attention-title"') < body.index('id="md-cash-title"') < body.index('id="md-budget-title"') < body.index('id="md-forecast-title"'), 'Required section order')
    data = json.loads(re.search(r'id="management-dashboard-data">(.*?)</script>', body, re.S).group(1))
    check(len(data['cashFlow']) == 12 and sum(p['outflow'] for p in data['cashFlow']) == 900, 'Cash flow preserves closed months')
    check(round(sum(data['breakdown']['amounts']), 2) == round(float(totals['expenses']) + 5, 2), 'Existing top-eight aggregation omits nonpositive Other tail (unchanged)')
    category_list = re.search(r'id="md-category-list">(.*?)</ul>', body, re.S).group(1)
    check(all('₱' + format(amount, ',.2f') in category_list and format(amount / sum(data['breakdown']['amounts']) * 100, ',.1f') + '%' in category_list for amount in data['breakdown']['amounts']), 'Server category list amounts and shares match chart payload')
    fixture_call('budgets-off')
    _, unavailable = request('dashboard.php')
    check('Exceptions could not be checked' in unavailable and 'No budget exceptions' not in unavailable, 'Unavailable budgets never shown as no exceptions')
    fixture_call('budgets-on')
    _, admin_body = request('dashboard.php', admin)
    check('Financial Overview' in admin_body and 'management-dashboard.css' not in admin_body and 'management-dashboard.js' not in admin_body, 'Admin excludes Management assets')
    (run / 'admin.html').write_text(admin_body, encoding='utf-8')
    sparkline_series = json.loads(re.search(r'const series = (.*?);', admin_body).group(1))
    check(len(sparkline_series) == 6 and sum(point['expenses'] for point in sparkline_series) == 900,
          'Admin six-month expense trends exclude the current month')
    check(all(point['income'] == 0 for point in sparkline_series) and sparkline_series[-1]['balance'] == -900,
          'Admin closed-month balance excludes current incoming funds')
    cards = admin_body.split('<div class="grid grid-cols-1 md:grid-cols-3 gap-6">', 1)[1].split('<section', 1)[0]
    check(cards.count('<canvas') == 3 and '<svg' not in cards and cards.count('relative h-16 w-full') == 3,
          'Admin replaces only the three card icons with 64px chart containers')
    check(cards.count('<li>') == 18 and cards.count('aria-describedby="kpi-') == 3,
          'All eighteen monthly values have server-rendered accessible alternatives')
    check(all('₱' + format(value, ',.2f') in cards for value in [float(totals['funds']), float(totals['expenses']), float(totals['funds'])-float(totals['expenses'])]),
          'Admin large totals retain all-time recorded amounts')
    fixture_call('expenses-date-off')
    try:
        _, trend_failure = request('dashboard.php', admin)
        failed_cards = trend_failure.split('<div class="grid grid-cols-1 md:grid-cols-3 gap-6">', 1)[1].split('<section', 1)[0]
        check('const series = [];' in trend_failure and failed_cards.count('relative h-16 w-full mt-4 hidden') == 3,
              'Trend failure hides canvases and does not serialize fabricated zeros')
        check(all(re.search(r'id="kpi-' + key + r'-status"[^>]*class="[^"]*mt-4">Trend unavailable', failed_cards)
                  for key in ['income', 'expenses', 'balance']), 'Trend failure displays three unavailable states')
        check(all('₱' + format(value, ',.2f') in failed_cards for value in [float(totals['funds']), float(totals['expenses']), float(totals['funds'])-float(totals['expenses'])]),
              'Trend query failure preserves valid all-time totals')
    finally:
        fixture_call('expenses-date-on')
    code, response = request('forecast_ai.php', fields={'action':'load','csrf_token':'fixture-csrf'})
    check(json.loads(response)['data']['state'] == 'degraded', 'Missing key returns baseline')
    code, _ = request('forecast_ai.php', fields={'action':'refresh','csrf_token':'wrong'})
    check(code == 400, 'CSRF rejection')
    code, _ = request('forecast_ai.php', session='invalid-session', fields={'action':'load','csrf_token':'fixture-csrf'})
    check(code == 401, 'Unauthenticated endpoint rejection')
    code, _ = request('forecast_ai.php')
    check(code == 405, 'POST-only endpoint')
    # Test actual fresh/cache/throttle paths without any network/model calls.
    (app / 'includes/gemini_client.php').write_text('''<?php
function gemini_is_configured(): bool { return true; }
function gemini_forecast_projection(array $history): array { return ['ok'=>true,'data'=>[
'chart_data'=>$history['baseline_projection'],'reallocation_suggestion'=>'First sentence. Second sentence. Full final sentence.',
'funding_risk'=>'Fixture funding observation.','risk_level'=>'LOW']]; }
''', encoding='utf-8')
    code, response = request('forecast_ai.php', fields={'action':'refresh','csrf_token':'fixture-csrf'})
    check(code == 200 and json.loads(response)['data']['state'] == 'fresh', 'Management fresh refresh with stubbed AI')
    check(fixture_call('cache-count') == '1', 'Fresh result stored only in disposable database')
    code, response = request('forecast_ai.php', fields={'action':'load','csrf_token':'fixture-csrf'})
    check(json.loads(response)['data']['state'] == 'cached', 'Cache state metadata')
    code, _ = request('forecast_ai.php', fields={'action':'refresh','csrf_token':'fixture-csrf'})
    check(code == 429, 'Management refresh throttle')
    code, _ = request('forecast_ai.php', admin, {'action':'refresh','csrf_token':'fixture-csrf'})
    check(code == 429, 'Admin remains authorized and reaches throttle')
    print(f'PASS: {checks} assertions; disposable database {database}; artifacts {run}')
finally:
    server.terminate()
    server.wait(timeout=10)
    log.close()
