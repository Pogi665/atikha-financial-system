(() => {
  'use strict';
  const form = document.getElementById('gl-controls');
  if (!form) return;
  const byId = id => document.getElementById(id);
  let applied = JSON.parse(byId('gl-initial').textContent)?.criteria || null;
  let model = JSON.parse(byId('gl-initial').textContent);
  let sequence = 0, controller, pending = false, dirty = false;
  const text = (id, value) => { byId(id).textContent = value; };
  // Financial display uses integer centavos, including large server amounts.
  const cents = value => { const s = String(value); if (!/^-?\d+\.\d{2}$/.test(s)) throw new Error('Amount unavailable'); return BigInt(s.replace('.', '')); };
  const money = value => { const n = cents(value), a = n < 0n ? -n : n; return (n < 0n ? '-' : '') + '₱' + (a / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + (a % 100n).toString().padStart(2, '0'); };
  const balance = value => money(value.replace(/^-/, '')) + (cents(value) > 0n ? ' Debit' : cents(value) < 0n ? ' Credit' : '');
  const url = criteria => 'general_ledger.php?' + new URLSearchParams(criteria).toString();
  function state() {
    byId('gl-apply').disabled = pending;
    text('gl-status', pending ? 'Loading ledger…' : dirty ? 'Filters changed. Select Apply to update the ledger.' : 'Showing applied filters.');
    byId('gl-print').setAttribute('aria-disabled', String(dirty || pending || !model?.account));
    byId('gl-export').setAttribute('aria-disabled', String(dirty || pending || !model?.account));
    byId('gl-prev').setAttribute('aria-disabled', String(dirty || pending || !model?.account || Number(applied?.page || 1) <= 1));
    byId('gl-next').setAttribute('aria-disabled', String(dirty || pending || !model?.account || Number(applied?.page || 1) >= (model?.pages || 1)));
  }
  function render(data) {
    model = data; applied = data.criteria;
    const choices = [{CategoryID: '', Name: 'Choose an account', Is_Active: 1}, ...data.accounts];
    byId('gl-account').replaceChildren(...choices.map(account => { const option = document.createElement('option'); option.value = account.CategoryID; option.textContent = (account.Account_Code ? account.Account_Code + ' · ' : '') + account.Name + (Number(account.Is_Active) ? '' : ' (inactive)'); return option; }));
    for (const key of ['account_id', 'from', 'to', 'search', 'page_size']) form.elements[key].value = applied[key];
    byId('gl-sheet').hidden = !data.account; byId('gl-prompt').hidden = !!data.account;
    if (data.account) {
      text('gl-account-label', data.account.Name + (data.account.Account_Code ? ' · ' + data.account.Account_Code : '') + (Number(data.account.Is_Active) ? '' : ' (inactive)'));
      text('gl-period-label', `${AtikhaUI.date(applied.from)} to ${AtikhaUI.date(applied.to)} · Live posted account movements`);
      for (const [id, value] of [['gl-opening', balance(data.opening)], ['gl-debits', money(data.period_debits)], ['gl-credits', money(data.period_credits)], ['gl-ending', balance(data.ending)], ['gl-total-debits', money(data.period_debits)], ['gl-total-credits', money(data.period_credits)], ['gl-total-ending', balance(data.ending)]]) text(id, value);
      text('gl-counts', `Showing ${data.rows.length} of ${data.matching_count} matching movements; ${data.period_count} period movements. Balances include movements hidden by search.`);
      text('gl-page-notice', data.page_adjusted ? 'The requested page was beyond the results. Showing the last available page.' : '');
      const rows = [];
      for (const row of data.rows) {
        const tr = document.createElement('tr');
        const values = [`${AtikhaUI.date(row.entry_date)}\nJournal #${row.journal_id} · ${row.source_book}\n${row.reference || 'No reference'}`, `${row.description}\n${row.party_name || 'Not recorded'}\n${row.project_name_snapshot || 'Organization operations'}`, money(row.debit_amount), money(row.credit_amount), balance(row.running_balance)];
        const labels=['Date / Transaction','Description / Project','Debit','Credit','Running Balance'];
        values.forEach((value, index) => { const td = document.createElement('td'); td.textContent = value;td.dataset.label=labels[index]; td.style.whiteSpace = index < 2 ? 'pre-line' : 'nowrap'; if (index > 1) td.className = 'gl-amount'; tr.append(td); });
        const td = document.createElement('td'), a = document.createElement('a'); td.className = 'gl-no-print';td.dataset.label='Action'; a.href = row.transaction_url;a.className='gl-view'; a.textContent = 'View';a.setAttribute('aria-label','View transaction #'+row.journal_id); td.append(a); tr.append(td); rows.push(tr);
      }
      if (!rows.length) { const tr = document.createElement('tr'), td = document.createElement('td'); td.colSpan = 6; td.textContent = data.period_count ? 'No movements match this search. Opening, totals and ending still cover the full period.' : 'No movements in this period. The opening balance carries forward.'; tr.append(td); rows.push(tr); }
      byId('gl-rows').replaceChildren(...rows);
      byId('gl-print').href = url({...applied, search: '', page: '1'}) + '&format=print';
      document.querySelectorAll('[data-gl-format]').forEach(a=>a.href='financial_export.php?'+new URLSearchParams({...applied,kind:'ledger',format:a.dataset.glFormat}));
      byId('gl-prev').href = url({...applied, page: String(Math.max(1, Number(applied.page) - 1))});
      byId('gl-next').href = url({...applied, page: String(Math.min(data.pages, Number(applied.page) + 1))});
      text('gl-page-label', `Page ${applied.page} of ${data.pages}`);
      text('gl-generated', `Generated ${AtikhaUI.datetime(data.generated_at.replace(/ Asia\/Manila$/,''),'local')}. Live values may change when new transactions are recorded.`);
    }
  }
  async function load(criteria, push = true) {
    controller?.abort(); controller = new AbortController(); const ticket = ++sequence; pending = true; state();
    try {
      const response = await fetch(url(criteria) + '&format=json', {signal: controller.signal, credentials: 'same-origin', cache: 'no-store'});
      const body = await response.json();
      if (ticket !== sequence) return;
      if (!response.ok || !body.ok) throw new Error(body.error || 'The ledger could not be loaded. Please try again.');
      render(body.data); dirty = false; byId('gl-error').hidden = true;
      if (push) history.pushState(null, '', url(applied));
    } catch (error) {
      if (ticket !== sequence || error.name === 'AbortError') return;
      text('gl-error', error instanceof SyntaxError ? 'Your session or connection may have expired. Sign in again, then apply the filters.' : error instanceof TypeError ? 'The ledger could not be loaded. Check your connection and apply again.' : error.message);
      byId('gl-error').hidden = false; dirty = true;
    } finally { if (ticket === sequence) { pending = false; state(); } }
  }
  form.addEventListener('input', () => { if (pending) { ++sequence; controller?.abort(); pending = false; } dirty = true; state(); });
  form.addEventListener('submit', event => { event.preventDefault(); const values = Object.fromEntries(new FormData(form)); values.page = '1'; load(values); });
  for (const id of ['gl-prev', 'gl-next']) byId(id).addEventListener('click', event => { event.preventDefault(); if (byId(id).getAttribute('aria-disabled') !== 'true') load(Object.fromEntries(new URL(byId(id).href).searchParams)); });
  document.querySelectorAll('[data-gl-format]').forEach(a=>a.addEventListener('click',event=>{if(dirty||pending||!model?.account)event.preventDefault();}));
  byId('gl-print').addEventListener('click', event => { if (byId('gl-print').getAttribute('aria-disabled') === 'true') event.preventDefault(); });
  addEventListener('popstate', () => load(Object.fromEntries(new URL(location.href).searchParams), false));
  if (applied) history.replaceState(null, '', url(applied));
  state();
})();
