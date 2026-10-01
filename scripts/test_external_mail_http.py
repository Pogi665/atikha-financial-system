"""Exercise real email routes in a private copy, with a disposable DB and fake SMTP."""
import argparse
import base64
import email
from email import policy
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
parser.add_argument('--keep-running', action='store_true')
args = parser.parse_args()
if not re.fullmatch(r'atikha_test_[a-z0-9_]+', args.database):
    parser.error('An explicit disposable database is required')
root = Path(__file__).resolve().parent.parent
php = r'C:\xampp\php\php.exe'
run = root / '.migration-private' / ('external-email-http-' + secrets.token_hex(6))
app = run / 'app'
app.mkdir(parents=True)
sessions = run / 'sessions'
sessions.mkdir()
for name in ['external_email.php', 'external_attachment.php', 'board_messages.php', 'board_inbox.php', 'board_attachment.php', 'review_actions.php', 'management_reviews.php']:
    shutil.copy2(root / name, app / name)
for name in ['includes', 'assets']:
    shutil.copytree(root / name, app / name)
(app / 'vendor').mkdir()
(app / 'vendor/autoload.php').write_text('<?php require ' + repr(str(root / 'vendor/autoload.php').replace('\\', '/')) + ';', encoding='utf8')
shutil.copytree(root / 'uploads/external', app / 'uploads/external')
# Local SMTP configuration is never copied.
capture = run / 'message.eml'
configuration = "<?php\ndefine('SMTP_HOST','fixture.example.invalid');\ndefine('SMTP_PORT',587);\ndefine('SMTP_USERNAME','system@example.invalid');\ndefine('SMTP_PASSWORD','fixture-only');\ndefine('SMTP_ENCRYPTION','tls');\ndefine('SMTP_FROM_NAME','Fixture system');\n"
configuration += 'define("EXTERNAL_FIXTURE_CAPTURE", ' + json.dumps(str(capture).replace('\\', '/')) + ');\n'
(app / 'config.php').write_text(configuration, encoding='utf8')
shutil.copy2(root / 'scripts/external_mail_fixture.php', app / 'includes/fixture_transport.php')
helper = app / 'includes/external_mail.php'
source = helper.read_text(encoding='utf8').replace("($sender ?? 'send_external_email')", "($sender ?? 'fixture_external_send')")
helper.write_text(source + "\nrequire_once __DIR__ . '/fixture_transport.php';\n", encoding='utf8')
db = "<?php $pdo=new PDO('mysql:host=127.0.0.1;dbname=" + args.database + ";charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);"
(app / 'db_connect.php').write_text(db, encoding='utf8')
login = """<?php
session_start(); require __DIR__.'/db_connect.php';
$role=($_GET['role']??'Admin')==='Management'?'Management':'Admin';
$s=$pdo->prepare("SELECT * FROM Users WHERE Role=? AND FullName LIKE 'Fixture %' AND Is_Active=1 ORDER BY UserID DESC LIMIT 1");
$s->execute([$role]); $u=$s->fetch();
$_SESSION=['UserID'=>(int)$u['UserID'],'Role'=>$role,'FullName'=>$u['FullName'],'csrf_token'=>'fixture-csrf','fixture_smtp_mode'=>($_GET['mode']??'sent')];
header('Location: external_email.php');
"""
(app / '__fixture_login.php').write_text(login, encoding='utf8')
router = run / 'router.php'
router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); if(preg_match('~^/(uploads|includes|vendor|scripts)/~',$p)){http_response_code(403);exit;}return false;", encoding='utf8')
sock = socket.socket()
sock.bind(('127.0.0.1', 0))
port = sock.getsockname()[1]
sock.close()
base = f'http://127.0.0.1:{port}'
log = (run / 'server.log').open('w', encoding='utf8')
command = [php, '-d', f'session.save_path={sessions}', '-d', 'upload_max_filesize=1M', '-d', 'post_max_size=2M', '-d', 'display_errors=0', '-S', f'127.0.0.1:{port}', '-t', str(app), str(router)]
server = subprocess.Popen(command, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
checks = 0

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1
    print('PASS:', label, flush=True)

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(opener, route, fields=None, attachment=None):
    headers = {}
    payload = None
    if fields is not None:
        if attachment is None:
            payload = urllib.parse.urlencode(fields).encode()
        else:
            boundary = 'email-' + secrets.token_hex(12)
            chunks = []
            for name, value in fields.items():
                chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode())
            filename, content = attachment
            chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="attachment"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n').encode() + content + b'\r\n')
            chunks.append(f'--{boundary}--\r\n'.encode())
            payload = b''.join(chunks)
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        req = urllib.request.Request(base + '/' + route, payload, headers)
    else:
        req = urllib.request.Request(base + '/' + route)
    try:
        response = opener.open(req, timeout=20)
    except urllib.error.HTTPError as exc:
        response = exc
    body = response.read()
    check(b'Fatal error' not in body and b'Warning:' not in body, route + ' has no exposed runtime errors')
    return response.status, body, response.geturl(), response.headers

def compose(opener):
    status, body, _, _ = request(opener, 'external_email.php?view=compose')
    text = body.decode()
    match = re.search(r'name="submission_key" value="([a-f0-9]{64})"', text)
    check(status == 200 and match is not None, 'Compose View provides signed submission key')
    return {'csrf_token': 'fixture-csrf', 'submission_key': match.group(1), 'to_email': 'recipient@example.invalid', 'subject': 'HTTP fixture subject', 'message_body': 'First line\nSecond line <b>text</b>'}

try:
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.1):
                break
        except OSError:
            time.sleep(.1)
    anon = client()
    check('login.php' in request(anon, 'external_email.php')[2], 'Anonymous email access redirects to login')
    admin, management = client(), client()
    for opener, role in [(admin, 'Admin'), (management, 'Management')]:
        check(request(opener, '__fixture_login.php?role=' + role)[0] == 200, role + ' accesses shared email history')
        form = compose(opener)
        status, body, url, _ = request(opener, 'external_email.php?view=compose', form)
        check(status == 200 and 'id=' in url and b'data-sent-detail' in body and b'data-compose-form' not in body, role + ' send opens Detail View without composer')
        check(b'SMTP server accepted' in body and b'&lt;b&gt;text&lt;/b&gt;' in body, 'Sent detail shows acceptance and safely escaped body')
        original_url = url
        _, _, repeat_url, _ = request(opener, 'external_email.php?view=compose', form)
        check(repeat_url == original_url, 'Duplicate HTTP submission returns same record')
    form = compose(admin)
    form['csrf_token'] = 'invalid'
    check(b'session expired' in request(admin, 'external_email.php?view=compose', form)[1], 'Invalid CSRF rejected')
    form = compose(admin)
    check(b'one valid recipient' in request(admin, 'external_email.php?view=compose', dict(form, to_email='bad address'))[1], 'Invalid recipient rejected with preserved compose mode')
    check(b'Only PDF' in request(admin, 'external_email.php?view=compose', compose(admin), ('payload.php', b'<?php echo 1;'))[1], 'Unsupported file rejected before send')
    check(b'upload_max_filesize limit of 1M' in request(admin, 'external_email.php?view=compose', compose(admin), ('large.pdf', b'%PDF-1.4\n' + b'a' * (1024 * 1024 + 100)))[1], 'PHP upload_max_filesize produces explicit environment warning')
    oversized = request(admin, 'external_email.php?view=compose', compose(admin), ('oversized.pdf', b'%PDF-1.4\n' + b'a' * (2 * 1024 * 1024 + 100)))[1]
    check(b'exceeds post_max_size (2M)' in oversized and b'session expired' not in oversized, 'Oversized POST handled before CSRF')
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aK1cAAAAASUVORK5CYII=')
    form = compose(admin)
    _, body, url, _ = request(admin, 'external_email.php?view=compose', form, ('fixture.png', png))
    check(b'data-sent-detail' in body and b'fixture.png' in body, 'Multipart upload sent and logged')
    message = email.message_from_bytes(capture.read_bytes(), policy=policy.default)
    attachments = list(message.iter_attachments())
    check(len(attachments) == 1 and attachments[0].get_filename() == 'fixture.png' and attachments[0].get_payload(decode=True) == png, 'PHPMailer MIME attachment matches uploaded bytes')
    html_body = message.get_body(preferencelist=('html',)).get_content()
    plain_body = message.get_body(preferencelist=('plain',)).get_content()
    check('<br' in html_body and '&lt;b&gt;' in html_body and '<b>text</b>' in plain_body, 'HTML and AltBody verified in transmitted MIME')
    record_id = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)['id'][0]
    status, downloaded, _, headers = request(management, 'external_attachment.php?id=' + record_id)
    check(status == 200 and downloaded == png and 'attachment;' in headers['Content-Disposition'], 'Other authorized role downloads original attachment')
    check(request(anon, 'external_attachment.php?id=' + record_id)[0] != 200, 'Anonymous attachment download denied')
    for mode, expected in [('reject', b'Failed'), ('unknown', b'Unconfirmed')]:
        request(management, '__fixture_login.php?role=Management&mode=' + mode)
        _, body, _, _ = request(management, 'external_email.php?view=compose', compose(management))
        check(expected in body and b'check' in body.lower(), 'HTTP send mode ' + mode + ' goes to issues')
    request(management, '__fixture_login.php?role=Management')
    check(request(admin, 'board_messages.php')[0] == 200, 'Existing Admin board page preserved')
    check(request(management, 'board_inbox.php')[0] == 200, 'Existing Management board page preserved')
    check(request(management, 'management_reviews.php')[0] == 200, 'Existing Management review queue preserved')
    # Identify stored attachment paths without exposing file content or SMTP settings.
    inspection = run / 'inspect.php'
    inspection.write_text('<?php require ' + repr(str(app / 'db_connect.php').replace('\\', '/')) + "; echo json_encode($pdo->query('SELECT File_Path FROM External_Communications WHERE File_Path IS NOT NULL ORDER BY CommunicationID DESC LIMIT 1')->fetchColumn());", encoding='utf8')
    saved_path = json.loads(subprocess.check_output([php, str(inspection)], text=True))
    check(request(admin, saved_path)[0] == 403, 'Direct attachment URL blocked in fixture router')
    (app / '.migration-private').mkdir()
    maintenance = app / '.migration-private/external-email-maintenance.flag'
    maintenance.write_text('fixture')
    check(request(admin, 'external_email.php')[0] == 503, 'Maintenance blocks email module')
    maintenance.unlink()
    # Pagination is exercised with fixture-only accepted records; no SMTP calls.
    paging = run / 'paging.php'
    paging.write_text('<?php require ' + repr(str(app / 'db_connect.php').replace('\\', '/')) + """;
    $u=$pdo->query("SELECT UserID,Email FROM Users WHERE FullName LIKE 'Fixture %' AND Role='Admin' ORDER BY UserID DESC LIMIT 1")->fetch();
    $s=$pdo->prepare("INSERT INTO External_Communications (Sender_UserID,To_Email,From_Email,Reply_To_Email,Subject,Message_Body,SMTP_Message_ID,Submission_Key,Send_Status,Sent_At) VALUES (?,?,?,?,?,?,?,?, 'Sent',CURRENT_TIMESTAMP)");
    for($i=0;$i<55;$i++){$key=bin2hex(random_bytes(32));$s->execute([$u['UserID'],'recipient@example.invalid','system@example.invalid',$u['Email'],'Pagination fixture '.$i,'Fixture body','<'.$key.'@example.invalid>',$key]);}
    """, encoding='utf8')
    subprocess.check_call([php, str(paging)])
    first = request(admin, 'external_email.php?folder=sent')[1]
    second = request(admin, 'external_email.php?folder=sent&page=2')[1]
    check(b'>Next</a>' in first and b'>Previous</a>' in second, 'Sent folder pagination moves between pages')
    check(request(admin, 'external_email.php?id=999999')[0] == 200 and b'could not be found' in request(admin, 'external_email.php?id=999999')[1], 'Missing selected record has a clear state')
    # Restart with larger PHP limits to distinguish the application 8 MB limit.
    server.terminate()
    server.wait(timeout=10)
    command = [php, '-d', f'session.save_path={sessions}', '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M', '-d', 'display_errors=0', '-S', f'127.0.0.1:{port}', '-t', str(app), str(router)]
    server = subprocess.Popen(command, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.1):
                break
        except OSError:
            time.sleep(.1)
    check(b'application attachment limit is 8 MB' in request(admin, 'external_email.php?view=compose', compose(admin), ('app-limit.pdf', b'%PDF-1.4\n' + b'a' * (9 * 1024 * 1024)))[1], 'Application attachment limit enforced independently of PHP')
    check(b'uploaded file is empty' in request(admin, 'external_email.php?view=compose', compose(admin), ('empty.pdf', b''))[1], 'Empty upload rejected')
    runtime = {'database': args.database, 'app': str(app), 'base': base, 'pid': server.pid, 'capture': str(capture), 'port': port}
    (root / '.migration-private/external-email-http-run.json').write_text(json.dumps(runtime, indent=2), encoding='utf8')
    print(f'PASS: {checks} external email HTTP checks; no external mail sent.', flush=True)
    print('Browser fixture: ' + base + '/__fixture_login.php?role=Admin', flush=True)
    if args.keep_running:
        print('Fixture server retained for browser verification.', flush=True)
        server.wait()
finally:
    if server.poll() is None:
        server.terminate()
        server.wait(timeout=10)
    log.close()
