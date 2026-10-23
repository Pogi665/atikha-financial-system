from test_pointer import test_pointer
"""Fresh schema-only copy of a proven synthetic source, then new fake identities.

No working financial data, old migration replay, trigger removal or server config.
"""
from pathlib import Path
import argparse
import hashlib
import json
import os
import re
import secrets
import subprocess

root=Path(__file__).resolve().parent.parent
private=(root/'.migration-private').resolve()
ap=argparse.ArgumentParser()
ap.add_argument('--source-fixture')
args=ap.parse_args()
pointer_path=test_pointer(private/'stages4-7-current.json')
pointer=json.loads(pointer_path.read_text(encoding='utf-8'))
source_path=Path(args.source_fixture or pointer['synthetic_source']).resolve()
assert source_path.is_relative_to(private)
source=json.loads(source_path.read_text(encoding='utf-8'))
source_db=source['database']
assert re.fullmatch(r'atikha_test_stage1_[a-z0-9]+',source_db)
assert Path(source['receipt_root']).resolve().is_relative_to(private)
db='atikha_test_stage1_s5'+secrets.token_hex(6)
run=private/('stage5-fixture-'+secrets.token_hex(6))
run.mkdir()
receipts=run/'receipts';receipts.mkdir();(receipts/'.htaccess').write_text('Require all denied\n')
env=os.environ.copy();env.update(pointer['environment'])
env['MYSQL_PWD']=env.get('ATIKHA_DB_PASSWORD','')
php='C:/xampp/php/php.exe'
def quote(value):return "'"+str(value).replace('\\','\\\\').replace("'","\\'")+"'"
def php_json(body):
    r=subprocess.run([php],input='<?php define("ATIKHA_ISOLATED_TEST",true);require '+quote(root/'scripts/cli_common.php')+';'+body,capture_output=True,text=True,encoding='utf-8',env=env,timeout=120)
    (run/'last-php.stderr.private.log').write_text(r.stderr,encoding='utf-8')
    assert r.returncode==0 and not r.stderr,'Fixture preflight/apply failed; preserve private logs, no retry/repair.'
    return json.loads(r.stdout)
guard=php_json('echo json_encode([ini_get("allow_url_fopen"),ini_get("disable_functions")]);')
assert guard[0] in ['', '0'] and all(name in guard[1].split(',') for name in ['curl_exec','mail','fsockopen','stream_socket_client'])
verified=php_json('require_once '+quote(root/'includes/stage2_common.php')+';require_once '+quote(root/'includes/stage3_common.php')+';$p=cli_db('+quote(source_db)+');echo json_encode(["schema"=>stage2_schema($p)&&stage3_schema($p)]);')
assert verified['schema']
client=['--no-defaults','--protocol=TCP','--host='+env.get('ATIKHA_DB_HOST','127.0.0.1'),'--port=3306','--user='+env.get('ATIKHA_DB_USER','root')]
dump=run/'synthetic-schema-only.sql'
r=subprocess.run(['C:/xampp/mysql/bin/mysqldump.exe',*client,'--no-data','--routines','--triggers','--result-file='+str(dump),source_db],capture_output=True,env=env,timeout=120)
(run/'schema-dump.stderr.private.log').write_bytes(r.stderr)
assert r.returncode==0,'Schema-only source dump failed'
sql=dump.read_text(encoding='utf-8')
assert 'atikha_finance' not in sql and not re.search(r'^\s*(?:USE\s|CREATE DATABASE\s|SET\s+(?:GLOBAL|@@GLOBAL))',sql,re.I|re.M),'Unexpected cross-target/global SQL; stop'
created=php_json('$p=new PDO("mysql:host=".(getenv("ATIKHA_DB_HOST")?:"127.0.0.1").";charset=utf8mb4",getenv("ATIKHA_DB_USER")?:"root",getenv("ATIKHA_DB_PASSWORD")?:"",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("CREATE DATABASE '+db+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");echo json_encode(["created"=>true]);')
assert created['created']
with dump.open('rb') as data:r=subprocess.run(['C:/xampp/mysql/bin/mysql.exe',*client,db],stdin=data,capture_output=True,env=env,timeout=120)
(run/'schema-load.stderr.private.log').write_bytes(r.stderr)
assert r.returncode==0,'Fresh schema load failed; no blind retry'
body='$p=cli_db('+quote(db)+');require_once '+quote(root/'includes/stage2_common.php')+';require_once '+quote(root/'includes/stage3_common.php')+';cli_require(stage2_schema($p)&&stage3_schema($p),"Fresh schema incomplete");cli_require((int)$p->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn()===0,"Schema copy must not contain financial data");'
body+='foreach(["admin"=>"Admin","other"=>"Admin","management"=>"Management"] as $name=>$role){$s=$p->prepare("INSERT INTO Users(FullName,Email,Role,Password,Is_Active) VALUES(?,?,?,?,1)");$s->execute(["S5 Synthetic ".$name,$name."@example.invalid",$role,"not-a-login-hash"]);}'
body+='$p->exec("INSERT INTO user_identities(UserID,FullName,Email,Role) SELECT UserID,FullName,Email,Role FROM Users");$p->exec("INSERT INTO accounting_write_state(id,change_version) VALUES(1,0)");'
body+='$before=[];foreach($p->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t){$row=$p->query("SHOW CREATE TABLE `".$t."`")->fetch(PDO::FETCH_NUM);$before[$t]=hash("sha256",$row[1]);}$oldTriggers=$p->query("SHOW TRIGGERS")->fetchAll();'
body+='cli_require($p->query("SELECT DATABASE()")->fetchColumn()==='+quote(db)+',"Disposable apply target changed");cli_sql_file($p,'+quote(root/'migrations/022_report_configuration.sql')+');require_once '+quote(root/'includes/report_configuration.php')+';cli_require(report_configuration_schema($p)["complete"],"New reporting schema incomplete");'
body+='foreach($before as $t=>$h){$row=$p->query("SHOW CREATE TABLE `".$t."`")->fetch(PDO::FETCH_NUM);cli_require(hash("sha256",$row[1])===$h,"Old schema changed");}$newTriggers=$p->query("SHOW TRIGGERS")->fetchAll();$byName=[];foreach($newTriggers as $t)$byName[$t["Trigger"]]=$t;foreach($oldTriggers as $t)cli_require(($byName[$t["Trigger"]]??null)===$t,"Old trigger changed");'
body+='echo json_encode(["passed"=>true,"financial_rows"=>(int)$p->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn(),"old_tables_preserved"=>count($before),"old_triggers_preserved"=>count($oldTriggers),"new_trigger_count"=>count($newTriggers),"migration"=>"022_report_configuration.sql","configuration_state"=>report_configuration_schema($p)]);'
applied=php_json(body)
assert applied['passed'] and applied['financial_rows']==0
fixture={'database':db,'receipt_root':str(receipts),'synthetic_schema_source':source_db,'schema_only_sha256':hashlib.sha256(dump.read_bytes()).hexdigest(),'migration_022_sha256':hashlib.sha256((root/'migrations/022_report_configuration.sql').read_bytes()).hexdigest(),'apply_evidence':applied,'working_data_used':False,'old_migrations_replayed':False,'external_calls_blocked':True}
path=run/'stage5-fixture.json';path.write_text(json.dumps(fixture,indent=2),encoding='utf-8')
pointer['g2_fixture']=str(path);pointer['g2_fixture_run']=str(run);pointer_path.write_text(json.dumps(pointer,indent=2))
print(json.dumps({'passed':True,'new_schema_only_synthetic_fixture':True,'migration_022':applied,'working_data_or_config_changed':False}))
