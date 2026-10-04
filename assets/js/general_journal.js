(function () {
    'use strict';
    const form = document.getElementById('journal-form');
    if (!form || typeof BigInt !== 'function') return;
    const tbody = document.getElementById('journal-lines');
    const template = document.getElementById('journal-line-template');
    const submit = document.getElementById('post-journal');
    const add = document.getElementById('add-journal-line');
    const count = document.getElementById('journal-line-count');
    const message = document.getElementById('journal-validation');
    let submitting = false;
    function cents(raw) {
        const value = raw.trim();
        if (value === '') return 0n;
        if (value.length > 32 || !/^\d+(?:\.\d{1,2})?$/.test(value)) return null;
        const parts = value.split('.');
        const whole = parts[0].replace(/^0+/, '') || '0';
        if (whole.length > 13) return null;
        return BigInt(whole) * 100n + BigInt((parts[1] || '').padEnd(2, '0'));
    }
    function validId(raw, optional) {
        const value = raw.trim();
        return optional && value === '' || /^\d{1,10}$/.test(value) && BigInt(value) > 0n && BigInt(value) <= 4294967295n;
    }
    function money(value) {
        const absolute = value < 0n ? -value : value;
        return (value < 0n ? '-' : '') + '₱' + (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + (absolute % 100n).toString().padStart(2, '0');
    }
    function syncRow(row, index, minimum) {
        row.querySelectorAll('[data-field]').forEach(field => {
            field.name = 'lines[' + index + '][' + field.dataset.field + ']';
        });
        const debit = row.querySelector('[data-amount="debit_amount"]');
        const credit = row.querySelector('[data-amount="credit_amount"]');
        const d = debit.value.trim(), c = credit.value.trim();
        // Preserve conflicting restored values so users can correct either field.
        debit.disabled = c !== '' && d === '';
        credit.disabled = d !== '' && c === '';
        row.querySelector('[data-field="debit_amount"]').value = debit.value;
        row.querySelector('[data-field="credit_amount"]').value = credit.value;
        const dc = cents(d), cc = cents(c);
        const amountValid = dc !== null && cc !== null && ((dc > 0n && cc === 0n) || (cc > 0n && dc === 0n));
        const account = row.querySelector('[data-field="account_id"]');
        const accountValid = validId(account.value, false) && !account.selectedOptions[0]?.dataset.unavailable;
        const fund = row.querySelector('[data-field="fund_project_id"]');
        const fundValid = validId(fund.value, true);
        account.setAttribute('aria-invalid', String(!accountValid));
        fund.setAttribute('aria-invalid', String(!fundValid));
        debit.setAttribute('aria-invalid', String(!amountValid));
        credit.setAttribute('aria-invalid', String(!amountValid));
        row.querySelector('[data-remove-line]').disabled = minimum || submitting;
        return { valid: accountValid && fundValid && amountValid, debit: dc || 0n, credit: cc || 0n };
    }
    function recalculate() {
        const rows = Array.from(tbody.querySelectorAll('[data-journal-line]'));
        let debits = 0n, credits = 0n, valid = rows.length >= 2 && rows.length <= 100;
        rows.forEach((row, i) => {
            const result = syncRow(row, i, rows.length <= 2);
            valid = valid && result.valid;
            debits += result.debit; credits += result.credit;
        });
        count.value = String(rows.length);
        add.disabled = rows.length >= 100 || submitting;
        document.getElementById('total-debits').textContent = money(debits);
        document.getElementById('total-credits').textContent = money(credits);
        document.getElementById('journal-difference').textContent = money(debits - credits);
        const date = document.getElementById('entry-date');
        const description = document.getElementById('journal-description');
        const reference = document.getElementById('journal-reference');
        valid = valid && /^\d{4}-\d{2}-\d{2}$/.test(date.value) && date.validity.valid
            && description.value.trim() !== '' && Array.from(description.value.trim()).length <= 2000
            && Array.from(reference.value.trim()).length <= 100 && debits > 0n && debits === credits;
        submit.disabled = !valid || submitting;
        message.textContent = submitting ? 'Posting entry…' : valid ? 'Entry is balanced and ready to post.' : 'Complete the header and every line. Debits and Credits must be equal and greater than zero.';
        return valid;
    }
    form.addEventListener('input', recalculate);
    form.addEventListener('change', recalculate);
    add.addEventListener('click', () => {
        if (tbody.children.length >= 100 || submitting) return;
        tbody.appendChild(template.content.cloneNode(true));
        recalculate();
        tbody.lastElementChild.querySelector('select').focus();
    });
    tbody.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-line]');
        if (!button || tbody.children.length <= 2 || submitting) return;
        button.closest('[data-journal-line]').remove(); recalculate();
    });
    form.addEventListener('submit', event => {
        if (submitting || !recalculate() || !form.checkValidity()) { event.preventDefault(); return; }
        submitting = true; recalculate();
    });
    window.addEventListener('pageshow', () => { submitting = false; recalculate(); });
    recalculate();
})();
