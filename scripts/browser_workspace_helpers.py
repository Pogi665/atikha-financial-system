"""Exercise the visible draft-saving control and resume the same owner draft.

No API mutation substitute: focused Save and close goes through My drafts;
legacy Save draft retains its existing stay-on-page behavior.
"""
from urllib.parse import urlparse, parse_qs


def save_and_resume(page):
    button = page.locator('#aw-save')
    if button.inner_text() != 'Save and close':
        button.click()
        page.wait_for_function("document.querySelector('#aw-status').textContent.includes('Draft saved')")
        return parse_qs(urlparse(page.url).query)['draft_id'][0]
    prior = parse_qs(urlparse(page.url).query).get('draft_id', [None])[0]
    purpose = page.locator('#aw-purpose').input_value()
    button.click()
    page.wait_for_url('**/accounting_drafts.php')
    # My drafts is the real owner-scoped UI, newest saved row first. Read its
    # identity rather than racing a response body after the page has navigated.
    row = page.locator('[data-draft-id="' + prior + '"]') if prior else page.locator('[data-draft-id]').filter(has_text=purpose).first
    row.wait_for()
    identity = row.get_attribute('data-draft-id')
    assert identity and identity.isdigit(), 'Saved draft identity missing'
    row.get_by_role('link', name='Continue', exact=True).click()
    page.wait_for_function("([id,purpose]) => document.querySelector('#aw-draft-label')?.textContent.includes('Draft #'+id) && document.querySelector('#aw-purpose')?.value === purpose && document.querySelector('#aw-existing') && !document.querySelector('#aw-existing').disabled", arg=[identity,purpose])
    assert parse_qs(urlparse(page.url).query)['draft_id'][0] == identity, 'Resumed a different draft'
    assert page.locator('#aw-purpose').input_value() == purpose, 'Saved purpose changed on resume'
    return identity


def select_entry_mode(page,mode):
    button=page.locator('[data-payment-mode="'+mode+'"]')
    if not button.is_visible():page.locator('#aw-more-options>summary').click()
    button.click()

def navigate_sidebar(page,href):
    link=page.locator('.app-sidebar a[href="'+href+'"]')
    if not link.is_visible():
        group=link.locator('xpath=ancestor::*[contains(@class,"ui-nav-group")][1]')
        group.locator('[data-ui-nav-group]').first.click()
    link.click()


def open_advance_filters(page):
    panel=page.locator('#ca-more-filters')
    if panel.count() and panel.get_attribute('open') is None:panel.locator('summary').click()


def open_period_controls(page):
    panel=page.locator('[aria-label="Month posting controls"]')
    panel.wait_for()
    if panel.get_attribute('open') is None:panel.locator('summary').click()


def open_advance_scope(page):
    panel=page.locator('.ca-scope-details')
    if panel.count() and panel.get_attribute('open') is None:panel.locator('summary').click()
