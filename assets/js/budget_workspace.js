(() => {
  'use strict';
  const boot = document.getElementById('bw-data'); if (!boot) return;
  const state = JSON.parse(boot.textContent), root = document.querySelector('.finance-reports');
  const error = document.getElementById('bw-error'), notice = document.getElementById('bw-notice');
  const randomKey = () => [...crypto.getRandomValues(new Uint8Array(32))].map(x => x.toString(16).padStart(2, '0')).join('');
  let busy = false, dirty = false, reviewed = false, draft = state.draft;
  const draftKey = draft?.request_key || randomKey();
  function fail(problem) { const message = typeof problem === 'string' ? problem : problem.message; error.textContent = message; error.hidden = false; error.scrollIntoView({block: 'center', behavior: 'instant'}); if (problem?.cell) { const project = problem.cell.project_id === null ? 'operations' : String(problem.cell.project_id); const input = root.querySelector(`input[data-account="${problem.cell.account_id}"][data-project="${project}"]`); if (input) { input.classList.add('budget-input-error'); error.textContent = `${input.getAttribute('aria-label')}: ${message}`; input.focus(); } } }
  function node(tag, text = '') { const el = document.createElement(tag); el.textContent = text; return el; }
  async function request(action, values) {
    let response; try { response = await fetch('budget_actions.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action, csrf_token: state.csrf, ...values})}); } catch { throw new Error('The response could not be confirmed. Keep your entries and check the saved draft or recorded decision before trying again.'); }
    let data; try { data = await response.json(); } catch { throw new Error('The response could not be confirmed. Keep your entries and check the saved revision before trying again.'); }
    if (!response.ok || !data.ok) { const problem = new Error(data.error || 'The budget response could not be confirmed. Check the saved revision.'); problem.cell = data.cell; throw problem; }
    return data.data;
  }
  async function run(action) {
    if (busy) return; busy = true; error.hidden = true;
    const buttons = [...root.querySelectorAll('button')], old = buttons.map(b => b.disabled); buttons.forEach(b => b.disabled = true);
    try { await action(); } catch (e) { fail(e.message ? e : 'The response could not be confirmed. Check the recorded result.'); }
    finally { buttons.forEach((b, i) => b.disabled = old[i]); const fields = document.getElementById('bw-fields'); if (fields) fields.disabled = reviewed; busy = false; }
  }
  function showResult(out) {
    dirty = false;
    if (out.warning) { notice.textContent = out.warning; notice.hidden = false; const a = node('a', 'Open saved revision'); a.href = `budgets.php?revision_id=${encodeURIComponent(out.id)}`; notice.append(document.createTextNode(' '), a); }
    else location.assign(`budgets.php?revision_id=${encodeURIComponent(out.id)}`);
  }
  if (state.editing) {
    const form = document.getElementById('bw-form'), fields = document.getElementById('bw-fields'), grid = document.getElementById('bw-grid'), label = document.getElementById('bw-state');
    const projects = new Map(state.projects.map(p => [String(p.id), p]));
    let columns = ['operations', ...new Set(state.payload.lines.filter(l => l.project_id !== null).map(l => String(l.project_id)))];
    function cents(raw) {
      if (raw.includes(',')) { if (!/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/.test(raw)) throw new Error('Enter a valid planned amount, with no more than two decimal places.'); raw = raw.replaceAll(',', ''); }
      if (!/^\d+(\.\d{1,2})?$/.test(raw)) throw new Error('Enter a valid planned amount, with no more than two decimal places.'); const parts = raw.split('.'); return BigInt(parts[0]) * 100n + BigInt((parts[1] || '').padEnd(2, '0'));
    }
    function money(value) { const digits = (value / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); return `PHP ${digits}.${(value % 100n).toString().padStart(2, '0')}`; }
    function values() { const data = new Map(); grid.querySelectorAll('input').forEach(i => data.set(`${i.dataset.account}:${i.dataset.project}`, i.value)); return data; }
    function updateTotal() {
      let total = 0n; try { for (const input of grid.querySelectorAll('input')) { const raw = input.value.trim(); if (raw) total += cents(raw); } document.getElementById('bw-total').textContent = money(total); }
      catch { document.getElementById('bw-total').textContent = 'Check the entered amounts'; }
    }
    function markDirty() { dirty = true; error.hidden = true; label.textContent = `Draft · ${AtikhaUI.month(state.month)} · Unsaved changes. The approved budget is unchanged.`; updateTotal(); }
    function render(data) {
      const head=node('tr');for(const title of ['Expense Account','Project / Organization Operations','Planned Amount (PHP)','Action']){const th=node('th',title);th.scope='col';head.append(th);}grid.tHead.replaceChildren(head);grid.tBodies[0].replaceChildren();
      for(const [key,value] of data){const split=key.indexOf(':');addAllocation(key.slice(0,split),key.slice(split+1),value);}
      if(!data.size)addAllocation();updateTotal();
    }
    function addAllocation(accountId='',projectId='operations',amount='') {
      const row=node('tr'),account=node('select'),project=node('select'),input=node('input'),remove=node('button','Remove');
      account.setAttribute('aria-label','Expense Account');account.add(new Option('Choose an expense account',''));
      state.accounts.forEach(a=>{const option=new Option(`${a.Account_Code?a.Account_Code+' · ':''}${a.Name}${Number(a.Is_Active)?'':' (inactive)'}`,String(a.CategoryID));option.disabled=!Number(a.Is_Active)&&String(a.CategoryID)!==String(accountId);account.add(option);});
      if(accountId&&![...account.options].some(o=>o.value===String(accountId)))account.add(new Option('Saved account (unavailable)',String(accountId)));account.value=String(accountId);
      project.setAttribute('aria-label','Project / Organization Operations');project.add(new Option('Organization operations','operations'));state.projects.forEach(p=>project.add(new Option((p.code?p.code+' · ':'')+p.name,String(p.id))));if(projectId!=='operations'&&![...project.options].some(o=>o.value===String(projectId)))project.add(new Option('Saved project (unavailable)',String(projectId)));project.value=String(projectId);
      input.type='text';input.inputMode='decimal';input.maxLength=32;input.value=amount;input.setAttribute('aria-label','Planned Amount (PHP)');
      const sync=()=>{input.dataset.account=account.value;input.dataset.project=project.value;};sync();account.addEventListener('change',()=>{sync();markDirty();});project.addEventListener('change',()=>{sync();markDirty();});input.addEventListener('input',()=>{input.classList.remove('budget-input-error');markDirty();});
      remove.type='button';remove.className='ui-button ui-button-danger';remove.setAttribute('aria-label','Remove allocation');remove.addEventListener('click',async()=>{if(input.value.trim()&&!await AtikhaUI.confirm({title:'Remove Allocation?',message:'Remove this planned amount from the draft? The approved budget will not change.',confirm:'Remove Allocation',destructive:true}))return;row.remove();markDirty();document.getElementById('bw-add-project').focus();});
      [account,project,input,remove].forEach((control,index)=>{const td=node('td');td.dataset.label=['Expense Account','Project / Organization Operations','Planned Amount (PHP)','Action'][index];td.append(control);row.append(td);});grid.tBodies[0].append(row);return row;
    }
    const initial = new Map(state.payload.lines.map(l => [`${l.account_id}:${l.project_id === null ? 'operations' : l.project_id}`, l.amount])); render(initial);
    document.getElementById('bw-note').addEventListener('input', markDirty);
    document.getElementById('bw-add-project').addEventListener('click', () => {
      const id=document.getElementById('bw-project').value||'operations';const row=addAllocation('',id);markDirty();row.querySelector('select').focus();
    });
    function payload() {
      const lines=[],seen=new Set();for(const i of grid.querySelectorAll('input'))if(i.value.trim()!==''){if(!i.dataset.account){i.closest('tr').querySelector('select').focus();throw new Error('Choose an expense account for each planned amount.');}const key=i.dataset.account+':'+i.dataset.project;if(seen.has(key)){i.focus();throw new Error('Use only one allocation per expense account and project.');}seen.add(key);cents(i.value.trim());lines.push({account_id:i.dataset.account,project_id:i.dataset.project==='operations'?null:i.dataset.project,amount:i.value.trim()});}
      return {month: state.month, note: document.getElementById('bw-note').value, lines};
    }
    async function save() {
      draft = await request('save', {draft_id: draft ? String(draft.id) : '', revision: draft ? String(draft.revision) : '', request_key: draftKey, copied_revision_id: state.copied_revision_id === null ? '' : String(state.copied_revision_id), payload: payload()});
      history.replaceState(null, '', `budgets.php?draft_id=${encodeURIComponent(draft.id)}`); dirty = false; label.textContent = `Draft saved · ${AtikhaUI.month(state.month)} · Not submitted for approval.`;
      const normalized = new Map(draft.payload.lines.map(l => [`${l.account_id}:${l.project_id === null ? 'operations' : l.project_id}`, l.amount])); render(normalized); return draft;
    }
    document.getElementById('bw-save').addEventListener('click', () => run(async () => { await save(); location.assign(`budgets.php?month=${encodeURIComponent(state.month)}&saved=1`); }));
    form.addEventListener('submit', e => { e.preventDefault(); run(async () => { await save(); reviewed = true; fields.disabled = true; document.getElementById('bw-edit-actions').hidden = true; document.getElementById('bw-review').hidden = false; const reviewGrid=grid.cloneNode(true);reviewGrid.removeAttribute('id');reviewGrid.classList.add('ui-operational-table');reviewGrid.querySelector('thead tr')?.lastElementChild?.remove();reviewGrid.querySelectorAll('tbody tr').forEach(row=>row.lastElementChild?.remove());reviewGrid.querySelectorAll('select').forEach(select=>select.replaceWith(node('span',select.selectedOptions[0]?.textContent||'Not selected')));reviewGrid.querySelectorAll('button').forEach(button=>button.remove());reviewGrid.querySelectorAll('input').forEach(input=>{const value=document.createElement('span');value.textContent=input.value===''?'No allocation':input.value;input.replaceWith(value);});const previous=document.getElementById('bw-review-grid');if(previous)previous.remove();const wrap=document.createElement('div');wrap.id='bw-review-grid';wrap.className='fr-table-wrap';wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label','Read-only proposed budget');wrap.append(reviewGrid);document.getElementById('bw-review-summary').after(wrap); document.getElementById('bw-review-summary').textContent = `${AtikhaUI.month(state.month)}: ${draft.payload.lines.length} allocations; total ${money(cents(draft.payload.total))}. Blank cells have no allocation.`; document.getElementById('bw-review').scrollIntoView({block: 'center'}); }); });
    document.getElementById('bw-edit').addEventListener('click', () => { reviewed = false; fields.disabled = false; document.getElementById('bw-edit-actions').hidden = false; document.getElementById('bw-review').hidden = true; });
    document.getElementById('bw-submit').addEventListener('click', () => run(async () => showResult(await request('submit', {draft_id: String(draft.id), revision: String(draft.revision), payload_hash: draft.payload_hash}))));
  }
  if (!state.editing && state.role === 'Management' && state.selected?.state === 'Submitted') {
    const keys = {Approved: randomKey(), Returned: randomKey()};
    for (const [id, decision] of [['bw-approve', 'Approved'], ['bw-return', 'Returned']]) {
      const button = document.getElementById(id); if (!button) continue;
      button.addEventListener('click', () => {
        const reason = document.getElementById('bw-reason').value;
        if (decision === 'Returned' && !reason.trim()) { fail('Enter the reason before returning this budget.'); document.getElementById('bw-reason').focus(); return; }
        run(async () => showResult(await request('review', {revision_id: String(state.selected.id), decision, reason, request_key: keys[decision], payload_hash: state.selected.payload_hash, expected_approval_epoch: String(state.selected.current_approval_epoch), expected_approved_id: state.selected.current_approved_id === null ? '' : String(state.selected.current_approved_id)})));
      });
    }
  }
  window.addEventListener('beforeunload', e => { if (dirty && !busy) { e.preventDefault(); e.returnValue = ''; } });
})();
