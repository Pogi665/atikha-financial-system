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
