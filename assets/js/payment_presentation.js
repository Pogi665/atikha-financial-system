/* Presentation checks only. Server review/posting remains the accounting authority. */
((scope) => {
    'use strict';
    const cents = value => {
        if (value === '') return 0n;
        if (typeof value !== 'string' || !/^\d{1,13}(\.\d{1,2})?$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    function mode(p, book = 'CDB') {
        if (!['CDB','CRB'].includes(book) || p.transaction_kind !== 'ordinary' || !Array.isArray(p.lines)) return 'advanced';
        const side = book === 'CRB' ? 'credit_amount' : 'debit_amount';
        const opposite = book === 'CRB' ? 'debit_amount' : 'credit_amount';
        if ((p.documents || []).some(d => (d.allocations || []).some(a => !p.lines.some(l => l.client_id === a.client_id)))) return 'advanced';
        if (p.lines.some(l => cents(l[opposite]) === null || cents(l[opposite]) !== 0n || cents(l[side]) === null)) return 'advanced';
        // Inspect every line before choosing any simplified representation.
        if (p.lines.some(l => Object.keys(l).some(k => !['client_id','account_id','fund_project_id','debit_amount','credit_amount'].includes(k)))) return 'advanced';
        if (p.lines.length !== 1) return 'split';
        const l = p.lines[0], cash = cents(p.cash_amount), amount = cents(l[side]);
        if (cash === null || amount === null || (p.cash_amount === '') !== (l[side] === '') || cash !== amount || p.cash_project_id !== l.fund_project_id) return 'split';
        return 'quick';
    }
    // Authoritative coverage uses ledger_decimal(), never the editable empty-as-zero convention.
    // At most 100 DECIMAL(15,2) journal lines contribute to an aggregate coverage side.
    function coverageCents(value) {
        if (typeof value !== 'string' || !/^(0|[1-9]\d{0,14})\.\d{2}$/.test(value)) return null;
        const amount = BigInt(value.replace('.', ''));
        return amount <= 99999999999999900n ? amount : null;
    }
    function coverageMoney(value) {
        const amount = coverageCents(value);
        if (amount === null) return null;
        return '\u20b1' + (amount / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
            + '.' + (amount % 100n).toString().padStart(2, '0');
    }
    function coverageText(review) {
        const c = review?.coverage, input = review?.input;
        const status = ['Not applicable','Fully covered','Partially covered','No monetary support'].includes(c?.status)
            ? c.status : 'Coverage status unavailable';
        const zero = side => coverageCents(c?.[side]?.eligible) === 0n && coverageCents(c?.[side]?.covered) === 0n;
        if (input?.source_book === 'GJ' && input.transaction_kind === 'transfer'
            && status === 'Not applicable' && zero('debit') && zero('credit')) {
            return 'Not applicable — monetary supporting-document coverage has no eligible noncash line in this internal cash transfer.';
        }
        const sides = input?.source_book === 'CDB' ? ['debit'] : input?.source_book === 'CRB' ? ['credit'] : ['debit','credit'];
        return status + ' — ' + sides.map(side => side + ': '
            + (coverageMoney(c?.[side]?.covered) ?? 'amount unavailable') + ' supported of '
            + (coverageMoney(c?.[side]?.eligible) ?? 'amount unavailable')).join('; ');
    }
    scope.PaymentPresentation = Object.freeze({cents, mode, coverageMoney, coverageText});
})(typeof window === 'undefined' ? globalThis : window);
