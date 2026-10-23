from test_pointer import test_pointer
"""Fresh connected synthetic reporting case, then additive023 once; no working rollout."""
from pathlib import Path
import hashlib,json,os,re,subprocess,sys
root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();pointer_path=test_pointer(private/'stages4-7-current.json')
created=subprocess.run([sys.executable,str(root/'scripts/run_stage5_checks.py'),'--base-only'],capture_output=True,text=True,encoding='utf-8',cwd=root,timeout=240)
assert created.returncode==0,'Fresh synthetic base rehearsal failed; preserve source evidence, no repair'
pointer=json.loads(pointer_path.read_text(encoding='utf-8'));fixture=Path(pointer['g2_ui_fixture']).resolve();assert fixture.is_relative_to(private)
f=json.loads(fixture.read_text(encoding='utf-8'));db=f['database'];assert re.fullmatch(r'atikha_test_stage1_s5[a-z0-9]+',db)
run=fixture.parent;assert not (run/'budget-apply-started.json').exists();(run/'budget-apply-started.json').write_text('{}',encoding='utf-8')
(run/'fresh-budget-base.stdout.log').write_text(created.stdout,encoding='utf-8');(run/'fresh-budget-base.stderr.log').write_text(created.stderr,encoding='utf-8')
env=os.environ.copy();env.update(pointer['environment'])
def ps(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
body='define("ATIKHA_ISOLATED_TEST",true);require '+ps(root/'scripts/cli_common.php')+';require_once '+ps(root/'includes/budget_revisions.php')+';$p=cli_db('+ps(db)+');'
body+='cli_require(report_configuration_schema($p)["complete"]&&budget_revision_schema($p)["state"]==="absent","Fresh complete022 and absent023 required");'
body+='$columns=[];foreach(["Categories"=>"CategoryID","projects"=>"id"] as $t=>$col){$s=$p->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$s->execute([$t,$col]);$columns[$t]=$s->fetchColumn();cli_require($columns[$t]==="int(10) unsigned","Unexpected FK source type; stop before DDL");}'
body+='$before=[];foreach($p->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t)$before[$t]=hash("sha256",$p->query("SHOW CREATE TABLE `".$t."`")->fetch(PDO::FETCH_NUM)[1]);$triggers=$p->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);'
body+='cli_sql_file($p,'+ps(root/'migrations/023_monthly_budget_revisions.sql')+');cli_require(budget_revision_schema($p)["complete"],"Additive budget schema is incomplete");foreach($before as $t=>$hash)cli_require(hash("sha256",$p->query("SHOW CREATE TABLE `".$t."`")->fetch(PDO::FETCH_NUM)[1])===$hash,"Existing table changed");$after=[];foreach($p->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC) as $t)$after[$t["Trigger"]]=$t;foreach($triggers as $t)cli_require(($after[$t["Trigger"]]??null)===$t,"Existing trigger changed");'
body+='cli_require((int)$p->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA<>DATABASE()")->fetchColumn()===0,"Cross-schema FK detected");echo json_encode(["passed"=>true,"old_tables_preserved"=>count($before),"old_triggers_preserved"=>count($triggers),"total_triggers"=>count($after),"foreign_key_source_types"=>$columns,"budget_schema"=>budget_revision_schema($p)]);'
r=subprocess.run(['C:/xampp/php/php.exe'],input='<?php '+body,capture_output=True,text=True,encoding='utf-8',env=env,timeout=120)
(run/'budget-apply.stdout.log').write_text(r.stdout,encoding='utf-8');(run/'budget-apply.stderr.log').write_text(r.stderr,encoding='utf-8')
if r.returncode or r.stderr:print(r.stdout+r.stderr);raise SystemExit('Budget apply rehearsal failed; no blind rerun or working change')
result=json.loads(r.stdout);assert result['passed'];f['migration_023_sha256']=hashlib.sha256((root/'migrations/023_monthly_budget_revisions.sql').read_bytes()).hexdigest();f['budget_apply_evidence']=result
out=run/'stage6-fixture.json';out.write_text(json.dumps(f,indent=2)+'\n',encoding='utf-8');pointer['g3_fixture']=str(out);pointer['g3_fixture_run']=str(run);pointer_path.write_text(json.dumps(pointer,indent=2)+'\n',encoding='utf-8')
print(json.dumps(result));print('New disposable synthetic database only; no working migration or financial data.')
