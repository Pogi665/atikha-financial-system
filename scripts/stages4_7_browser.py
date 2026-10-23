"""Owned isolated browser app/server harness. No real authentication/provider/working fixture."""
from pathlib import Path
import hashlib,json,os,re,secrets,shutil,socket,subprocess,time
ROOT=Path(__file__).resolve().parent.parent
PRIVATE=(ROOT/'.migration-private').resolve()
PHP='C:/xampp/php/php.exe'
def ps(value):return "'"+str(value).replace('\\','\\\\').replace("'","\\'")+"'"
class IsolatedBrowserApp:
 def __init__(self,pointer,fixture,prefix):
  self.fixture=Path(fixture).resolve();assert self.fixture.is_relative_to(PRIVATE);self.f=json.loads(self.fixture.read_text(encoding='utf-8'));self.db=self.f['database'];assert re.fullmatch(r'atikha_test_stage1_s5[a-z0-9]+',self.db);self.env=os.environ.copy();self.env.update(pointer['environment']);self.run=PRIVATE/(prefix+'-'+secrets.token_hex(5));self.app=self.run/'app';self.sessions=self.run/'sessions';self.app.mkdir(parents=True);self.sessions.mkdir();evidence=Path(self.f['receipt_root']).resolve();assert evidence.is_relative_to(PRIVATE)
  guard=self.php_json('echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);');assert guard[0] in ['','0'] and all(n in guard[1].split(',') for n in ['curl_exec','mail','fsockopen','stream_socket_client'])
  for src in ROOT.glob('*.php'):
   if src.name not in ['config.php','db_connect.php'] and not src.name.startswith('_'):shutil.copy2(src,self.app/src.name)
  for name in ['includes','assets','scripts','vendor']:shutil.copytree(ROOT/name,self.app/name)
  pins={str(src.relative_to(self.app)).replace('\\','/'):hashlib.sha256(src.read_bytes()).hexdigest() for src in self.app.rglob('*') if src.is_file() and src.suffix in ['.php','.js','.css'] and str(src.relative_to(self.app)).split('\\')[0] not in ['vendor','scripts'] and src.name not in ['config.php','db_connect.php']}
  (self.run/'source-pins.json').write_text(json.dumps(pins,indent=2),encoding='utf-8')
  (self.app/'config.php').write_text("<?php define('STAGE1_WORKSPACE_ENABLED',true);define('STAGE2_ADVANCES_ENABLED',true);define('STAGE3_CORRECTIONS_ENABLED',true);define('OCR_JOURNAL_ENABLED',false);define('GEMINI_API_KEY','');define('RECEIPT_UPLOAD_DIR',"+ps(evidence)+');',encoding='utf-8')
  dsn='mysql:host='+self.env.get('ATIKHA_DB_HOST','127.0.0.1')+';dbname='+self.db+';charset=utf8mb4';(self.app/'db_connect.php').write_text('<?php $pdo=new PDO('+','.join(ps(v) for v in [dsn,self.env.get('ATIKHA_DB_USER','root'),self.env.get('ATIKHA_DB_PASSWORD','')])+',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);',encoding='utf-8');sock=socket.socket();sock.bind(('127.0.0.1',0));self.port=sock.getsockname()[1];sock.close();self.base='http://127.0.0.1:'+str(self.port)
  router=self.run/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(preg_match('~^/(uploads/|scripts/|includes/|vendor/|extract_receipt|ocr_extract|gemini|anomaly_scan|audit_summary|external_email|send_email)~',$p)){http_response_code(403);exit;}return false;",encoding='utf-8');self.log=(self.run/'server.private.log').open('w',encoding='utf-8');self.server=subprocess.Popen([PHP,'-d','session.save_path='+str(self.sessions),'-S','127.0.0.1:'+str(self.port),'-t',str(self.app),str(router)],stdout=self.log,stderr=self.log,env=self.env)
  for _ in range(60):
   try:
    with socket.create_connection(('127.0.0.1',self.port),timeout=.2):break
   except OSError:time.sleep(.1)
 def php_json(self,body):
  r=subprocess.run([PHP],input='<?php '+body,capture_output=True,text=True,encoding='utf-8',env=self.env,cwd=ROOT,timeout=90);(self.run/'last-php.stdout.private.log').write_text(r.stdout,encoding='utf-8');(self.run/'last-php.stderr.private.log').write_text(r.stderr,encoding='utf-8');assert r.returncode==0 and not r.stderr,'Private fixture query failed; retained private diagnostics';return json.loads(r.stdout)
 def sql(self,query):return self.php_json('require '+ps(ROOT/'scripts/cli_common.php')+';$p=cli_db('+ps(self.db)+');echo json_encode($p->query('+ps(query)+')->fetchAll());')
 def fingerprint(self):return {r['TABLE_NAME']:hashlib.sha256(json.dumps(self.sql('SELECT * FROM `'+r['TABLE_NAME']+'` ORDER BY 1'),sort_keys=True).encode()).hexdigest() for r in self.sql('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE="BASE TABLE"')}
 def context(self,browser,email):
  ctx=browser.new_context(viewport={'width':1366,'height':768},service_workers='block');ctx.route(re.compile(r'^https?://(?!127\.0\.0\.1:)'),lambda route:route.abort());s=json.loads(subprocess.check_output([PHP,str(self.app/'scripts/http_fixture.php'),'--database='+self.db,'--sessions='+str(self.sessions),'--email='+email,'--test-login'],text=True,encoding='utf-8',env=self.env));ctx.add_cookies([{'name':'PHPSESSID','value':s['session'],'url':self.base}]);return ctx
 def close(self):
  self.server.terminate()
  try:self.server.wait(timeout=10)
  except subprocess.TimeoutExpired:self.server.kill();self.server.wait(timeout=10)
  self.log.close()
