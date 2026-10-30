(() => {
  'use strict';
  const boot = document.getElementById('rb-data'); if (!boot) return;
  const data = JSON.parse(boot.textContent), error = document.getElementById('rb-error'), notice = document.getElementById('rb-notice');
  if(data.frozen){history.scrollRestoration='manual';const top=()=>{window.scrollTo(0,0);document.getElementById('rb-state')?.setAttribute('tabindex','-1');};window.addEventListener('pageshow',top);requestAnimationFrame(top);}
  const keys = new Map(); let busy = false;
  function key(action) { if (!keys.has(action)) keys.set(action, [...crypto.getRandomValues(new Uint8Array(32))].map(n => n.toString(16).padStart(2, '0')).join('')); return keys.get(action); }
  function fail(message) { error.textContent = message; error.hidden = false; error.scrollIntoView({block: 'center', behavior: 'instant'}); }
  async function act(action, fields) {
    if (busy) return; busy = true; error.hidden = true;
    const buttons = [...document.querySelector('.finance-reports').querySelectorAll('button')], disabled = buttons.map(b => b.disabled); buttons.forEach(b => b.disabled = true);
    try {
      let response, result;
      try { response = await fetch('report_bundle_actions.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action, csrf_token: data.csrf, request_key: key(action + (fields.decision || '')), ...fields})}); result = await response.json(); }
      catch { throw new Error('The result could not be confirmed. Check the saved revision or month history before trying again. This page will not automatically replay the action.'); }
      if (!response.ok || !result.ok) throw new Error(result.error || 'Check the saved revision or month state before trying again.');
      const destination = result.data.id ? `report_bundles.php?bundle_id=${encodeURIComponent(result.data.id)}` : `report_bundles.php?month=${encodeURIComponent(data.month)}`;
      const next=destination+'&component='+encodeURIComponent(data.component||'worksheet');
      if (result.data.warning) { notice.textContent = result.data.warning; notice.hidden = false; const a = document.createElement('a'); a.href = next; a.textContent = 'Open the saved result'; notice.append(document.createTextNode(' '), a); }
      else location.assign(next);
    } catch (e) { fail(e.message); } finally { buttons.forEach((b, i) => b.disabled = disabled[i]); busy = false; }
  }
  document.getElementById('rb-submit')?.addEventListener('click', () => act('submit', {month: data.month, source_fingerprint: data.source_fingerprint, expected_epoch: data.period.epoch, acknowledge_no_budget: !!document.getElementById('rb-no-budget')?.checked}));
  for (const [id, decision] of [['rb-reviewed', 'Reviewed'], ['rb-return', 'Returned']]) document.getElementById(id)?.addEventListener('click', () => {
    const reason = document.getElementById('rb-review-reason').value;
    if (decision === 'Returned' && !reason.trim()) { fail('Enter the reason before returning these reports.'); document.getElementById('rb-review-reason').focus(); return; }
    act('review', {bundle_id: String(data.frozen.id), payload_hash: data.frozen.payload_hash, decision, reason});
  });
  document.getElementById('rb-close')?.addEventListener('click', () => {
    const bundle = document.getElementById('rb-close-bundle').value, reason = document.getElementById('rb-close-reason').value;
    if (!bundle || !reason.trim()) { fail('Choose a reviewed complete revision and enter the reason for closing this month.'); return; }
    act('close', {month: data.month, bundle_id: bundle, expected_epoch: data.period.epoch, reason});
  });
  document.getElementById('rb-reopen')?.addEventListener('click', () => {
    const reason = document.getElementById('rb-reopen-reason').value;
    if (!reason.trim()) { fail('Enter the reason for reopening every listed month.'); document.getElementById('rb-reopen-reason').focus(); return; }
    act('reopen', {month: data.month, expected_set: data.reopen_set, reason});
  });
})();
