"""Clone a guarded disposable integrated fixture for an independent browser suite.
Never loads production config; database and private files must both be disposable.
"""
import argparse, hashlib, json, os, re, secrets, shutil, subprocess
from pathlib import Path
root=Path(__file__).resolve().parent.parent
parser=argparse.ArgumentParser();parser.add_argument('--fixture',required=True);parser.add_argument('--database',required=True);args=parser.parse_args()
private=(root/'.migration-private').resolve();source=Path(args.fixture).resolve()
if not source.is_relative_to(private):parser.error('Private fixture required')
fixture=json.loads(source.read_text());db=fixture['database'];target=args.database
if not all(re.fullmatch(r'atikha_test_(?:stage1|phase4)_[a-z0-9]+',n) for n in [db,target]) or db==target:parser.error('Distinct guarded disposable databases required')
evidence=Path(fixture['receipt_root']).resolve()
if not evidence.is_relative_to(private):parser.error('Private evidence required')
php=r'C:\xampp\php\php.exe'
def quote(v):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
def runphp(body):return subprocess.check_output([php],input='<?php define("ATIKHA_ISOLATED_TEST",true);require '+quote(str(root/'scripts/cli_common.php'))+';'+body,text=True)
run=root/'.migration-private'/('cp5-clone-'+secrets.token_hex(6));run.mkdir();dump=run/'fixture.sql'
env=os.environ.copy();env['MYSQL_PWD']=env.get('ATIKHA_DB_PASSWORD','')
host=env.get('ATIKHA_DB_HOST','127.0.0.1');user=env.get('ATIKHA_DB_USER','root')
subprocess.run([r'C:\xampp\mysql\bin\mysqldump.exe','--host='+host,'--user='+user,'--single-transaction','--result-file='+str(dump),db],env=env,check=True)
runphp('$p=cli_db('+quote(db)+');require_once '+quote(str(root/'includes/stage2_common.php'))+';require_once '+quote(str(root/'includes/stage3_common.php'))+';cli_require(stage3_schema($p)&&stage2_schema($p),"Integrated source required");$s=new PDO("mysql:host=".(getenv("ATIKHA_DB_HOST")?:"127.0.0.1"),getenv("ATIKHA_DB_USER")?:"root",getenv("ATIKHA_DB_PASSWORD")?:"");$s->exec("CREATE DATABASE '+target+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");')
with dump.open('rb') as data:subprocess.run([r'C:\xampp\mysql\bin\mysql.exe','--host='+host,'--user='+user,target],stdin=data,env=env,check=True)
receipts=run/'receipts';shutil.copytree(evidence,receipts)
for original in source.parent.glob('*fixture.json'):
    data=json.loads(original.read_text())
    if data.get('database')!=db:continue
    data['database']=target;data['receipt_root']=str(receipts);(run/original.name).write_text(json.dumps(data),encoding='utf-8')
result=run/source.name
if not result.exists():parser.error('Fixture copy missing')
summary=runphp('$p=cli_db('+quote(target)+');require_once '+quote(str(root/'includes/stage2_common.php'))+';require_once '+quote(str(root/'includes/stage3_common.php'))+';cli_require(stage3_schema($p)&&stage2_schema($p),"Complete cloned schema required");echo json_encode(["database"=>$p->query("SELECT DATABASE()")->fetchColumn(),"journals"=>(int)$p->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn(),"schema_complete"=>true]);')
manifest={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in receipts.iterdir() if p.is_file()}
(run/'clone-manifest.json').write_text(json.dumps({'source':db,'target':target,'dump_sha256':hashlib.sha256(dump.read_bytes()).hexdigest(),'files':manifest,'verification':json.loads(summary)},sort_keys=True),encoding='utf-8')
print('Cloned fixture:',result)
