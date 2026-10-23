(() => {
  'use strict';
  const boot = document.getElementById('rs-data');
  if (!boot) return;
  const state = JSON.parse(boot.textContent), form = document.getElementById('rs-form');
  let draft = state.draft, busy = false, reviewed = false;
  const key = draft?.request_key || [...crypto.getRandomValues(new Uint8Array(32))].map(x => x.toString(16).padStart(2, '0')).join('');
  const error = document.getElementById('rs-error'), status = document.getElementById('rs-status');
  const fields = document.getElementById('rs-fields');
  function fail(message) { error.textContent = message; error.hidden = false; error.scrollIntoView({block: 'center'}); }
  function node(tag, text = '') { const e = document.createElement(tag); e.textContent = text; return e; }
  function input(row, name, label, type = 'text', value = '') {
    const wrap = node('label', label), el = node('input'); el.type = type; el.dataset.field = name; el.value = value; el.required = true;
    if (type === 'text') el.maxLength = name === 'reason' ? 2000 : 32;
    wrap.append(el); row.append(wrap); return el;
  }
  function select(row, name, label, options, value = '') {
    const wrap = node('label', label), el = node('select'); el.dataset.field = name; el.required = true;
    for (const [id, text] of options) { const o = node('option', text); o.value = id; el.append(o); }
    el.value = String(value); wrap.append(el); row.append(wrap); return el;
  }
  function lineLabel(l) { return `${l.account_name} · Debit PHP ${l.debit_amount} · Credit PHP ${l.credit_amount} · line ${l.id}`; }
  function source(row, name, label, selected) {
    const old = state.source_lines?.[selected];
    const journal = input(row, `${name}_journal`, `${label}: Journal number`, 'number', old?.journal_entry_id || ''); journal.min = '1'; journal.step = '1';
    const choices = select(row, name, `${label}: recorded line`, [['', 'Choose a recorded line']], '');
    if (old) { const o = node('option', lineLabel(old)); o.value = old.id; choices.append(o); choices.value = old.id; }
    else if (selected) { const o = node('option', `Saved line ${selected} — load its transaction to review`); o.value = selected; choices.append(o); choices.value = selected; }
    const load = node('button', 'Load transaction lines'); load.type = 'button'; load.className = 'fr-button'; row.append(load);
    let generation = 0;
    journal.addEventListener('input', () => { generation++; choices.replaceChildren(new Option('Choose a recorded line', '')); });
    load.addEventListener('click', async () => {
      const serial = ++generation, id = journal.value;
      if (!/^[1-9][0-9]{0,9}$/.test(id)) { fail('Enter the Journal number shown in Journal History.'); return; }
      load.disabled = true;
      try {
        const response = await fetch(`report_setup.php?format=lines&journal_id=${encodeURIComponent(id)}`, {credentials: 'same-origin', cache: 'no-store'}), data = await response.json();
        if (serial !== generation) return;
        if (!response.ok || !data.ok) throw new Error(data.error || 'Transaction lines could not be loaded.');
        const options = [new Option('Choose a recorded line', '')];
        for (const l of data.data) options.push(new Option(lineLabel(l), l.id));
        choices.replaceChildren(...options); choices.focus();
      } catch (e) { if (serial === generation) fail(e.message); } finally { load.disabled = state.published || reviewed; }
    });
  }
  function add(kind, v = {}) {
    const row = node('div'); row.className = 'fr-exception'; row.dataset.exception = kind;
    if (kind === 'designations') {
      input(row, 'journal_id', 'Journal number', 'number', v.journal_id || '').min = '1';
      select(row, 'kind', 'Reporting purpose', [['ordinary', 'Ordinary activity'], ['opening', 'Opening transfer'], ['closing', 'Closing transfer']], v.kind || 'ordinary');
    } else {
      const cash = kind === 'cash_allocations';
      source(row, cash ? 'cash_line_id' : 'from_line_id', cash ? 'Cash source' : 'First noncash source', v[cash ? 'cash_line_id' : 'from_line_id']);
      source(row, cash ? 'counterpart_line_id' : 'to_line_id', 'Counterpart source', v[cash ? 'counterpart_line_id' : 'to_line_id']);
      input(row, 'amount', 'Allocated amount (PHP)', 'text', v.amount || '').inputMode = 'decimal';
      if (cash) select(row, 'activity', 'Cash-flow activity', [['', 'Choose activity'], ['operating', 'Operating'], ['investing', 'Investing'], ['financing', 'Financing'], ['internal', 'Internal cash transfer']], v.activity || '');
    }
    input(row, 'reason', 'Reason / source explanation', 'text', v.reason || '').closest('label').className = 'fr-wide';
    const remove = node('button', 'Remove exception'); remove.type = 'button'; remove.className = 'fr-button'; remove.addEventListener('click', () => { row.remove(); dirty(); }); row.append(remove);
    document.getElementById(`rs-${kind}`).append(row);
  }
  function dirty() { if (!state.published) status.textContent = 'Draft — unsaved changes. Submit setup to make a new revision available to reports.'; }
  for (const kind of ['designations', 'cash_allocations', 'noncash_reclasses']) for (const row of state.payload[kind]) add(kind, row);
  if (state.published) return;
  form.addEventListener('input', dirty); form.addEventListener('change', dirty);
  document.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => { add(b.dataset.add); dirty(); }));
  function payload() {
    const v = {effective_from: document.getElementById('rs-effective').value + '-01', year_start_month: document.getElementById('rs-year').value,
      cash_change_reason: document.getElementById('rs-cash-reason').value, cash_accounts: [], mappings: [], designations: [], cash_allocations: [], noncash_reclasses: []};
    for (const row of document.querySelectorAll('#rs-mappings tr')) {
      const id = row.dataset.account, cash = row.querySelector('[data-field=cash]').checked, section = row.querySelector('[data-field=section]').value.trim(), flow = row.querySelector('[data-field=flow]').value;
      if (cash) v.cash_accounts.push(id); if (cash || section || flow) v.mappings.push({account_id: id, section, flow});
    }
    for (const row of document.querySelectorAll('[data-exception]')) {
      const kind = row.dataset.exception, item = {};
      for (const el of row.querySelectorAll('[data-field]')) if (!el.dataset.field.endsWith('_journal')) item[el.dataset.field] = el.value.trim();
      v[kind].push(item);
    }
    return v;
  }
  async function request(action, extra) {
    const response = await fetch('report_setup_actions.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action, csrf_token: state.csrf, ...extra})});
    let data; try { data = await response.json(); } catch { throw new Error('The response could not be confirmed. Keep this page open and check your saved drafts.'); }
    if (!response.ok || !data.ok) throw new Error(data.error || 'The setup response could not be confirmed. Check your saved drafts.'); return data.data;
  }
  async function save() {
    const v = payload();
    draft = await request('save', {draft_id: draft ? String(draft.id) : '', revision: draft ? String(draft.revision) : '', request_key: key, payload: v});
    history.replaceState(null, '', `report_setup.php?draft_id=${encodeURIComponent(draft.id)}`); status.textContent = 'Draft saved. This setup has not been submitted.'; return draft;
  }
  async function run(action) {
    if (busy) return; busy = true; error.hidden = true; form.querySelectorAll('button').forEach(b => b.disabled = true);
    try { await action(); } catch (e) { fail(e.message || 'The response could not be confirmed. Check your saved drafts before trying again.'); }
    finally { busy = false; form.querySelectorAll('button').forEach(b => b.disabled = false); fields.disabled = reviewed; }
  }
  document.getElementById('rs-save').addEventListener('click', () => { if (form.reportValidity()) run(async () => { await save(); location.assign('report_setup.php?saved=1'); }); });
  form.addEventListener('submit', e => { e.preventDefault(); if (form.reportValidity()) run(async () => { await save(); reviewed = true; fields.disabled = true; document.getElementById('rs-review').hidden = false; document.getElementById('rs-edit-actions').hidden = true; document.getElementById('rs-review-summary').textContent = `Effective ${draft.payload.effective_from}; ${draft.payload.cash_accounts.length} cash accounts; ${draft.payload.mappings.length} account mappings. Review the listed exceptions before submitting.`; document.getElementById('rs-review').scrollIntoView({block: 'center'}); }); });
  document.getElementById('rs-edit').addEventListener('click', () => { reviewed = false; fields.disabled = false; document.getElementById('rs-review').hidden = true; document.getElementById('rs-edit-actions').hidden = false; });
  document.getElementById('rs-submit').addEventListener('click', () => run(async () => { await request('submit', {draft_id: String(draft.id), revision: String(draft.revision), payload_hash: draft.payload_hash}); location.assign(`report_setup.php?draft_id=${encodeURIComponent(draft.id)}`); }));
  window.addEventListener('beforeunload', e => { if (!busy && status.textContent.includes('unsaved')) { e.preventDefault(); e.returnValue = ''; } });
})();
