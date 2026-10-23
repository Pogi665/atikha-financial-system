from pathlib import Path
import json,sys,re
from urllib.parse import urlencode,parse_qs,urlparse
root=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(root/'.migration-private/journal-test-deps'))
from playwright.sync_api import sync_playwright
pointer=json.loads((root/'.migration-private/stages4-7-current.json').read_text())
meta=json.loads(Path(pointer['g7_metadata']).read_text())
criteria={'account_id':str(meta['case']['bank']),'from':'2026-09-01','to':'2026-09-30','search':'','page':'1','page_size':'25'}
checks=[];observed={}
def check(ok,label):
    assert ok,label
    checks.append(label)
with sync_playwright() as pw:
    browser=pw.chromium.launch(channel='msedge',headless=True)
    for role in ['admin','management']:
        ctx=browser.new_context(service_workers='block')
        ctx.route(re.compile(r'^https?://(?!(?:127\.0\.0\.1|localhost):)'),lambda r:r.abort())
        page=ctx.new_page();page.goto(meta['urls'][role])
        base=meta['base'].replace('127.0.0.1','localhost') if role=='management' else meta['base']
        page.goto(base+'/general_ledger.php?'+urlencode(criteria))
        ending=page.locator('#gl-ending').inner_text()
        page.get_by_role('link',name='View transaction',exact=True).first.click()
        page.locator('.exact-transaction').wait_for(state='visible')
        check(page.locator('.exact-transaction').count()==1,role+' opened exact transaction')
        page.get_by_role('link',name='Back to General Ledger',exact=True).click()
        check(page.locator('[name=from]').input_value()==criteria['from'] and page.locator('[name=to]').input_value()==criteria['to'],role+' return keeps September form dates')
        page.locator('[name=search]').fill('REV-187')
        with page.expect_response(lambda r:'general_ledger.php?' in r.url and 'format=json' in r.url):page.locator('#gl-apply').click()
        page.wait_for_function("!document.getElementById('gl-apply').disabled")
        query=parse_qs(urlparse(page.url).query)
        check(query['from']==[criteria['from']] and query['to']==[criteria['to']],role+' search keeps September URL dates')
        check('2026-09-01 to 2026-09-30' in page.locator('#gl-period-label').inner_text(),role+' search keeps September applied heading')
        check(page.locator('#gl-ending').inner_text()==ending,role+' search preserves ending balance')
        rows=page.locator('#gl-rows tr')
        check(rows.count()==1 and 'REV-187' in rows.inner_text(),role+' search displays one actual matching reversal')
        observed[role]={'period':page.locator('#gl-period-label').inner_text(),'ending':ending,'counts':page.locator('#gl-counts').inner_text()}
        ctx.close()
    browser.close()
result={'passed':True,'checks':checks,'observed':observed,'classification':'automated read-only return/form/search diagnostic; not human completion','application_changes':False,'working_database_used':False}
(root/'.migration-private/g7-return-search-diagnostic.json').write_text(json.dumps(result,indent=2))
print(json.dumps(result))
