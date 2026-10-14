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
    scope.PaymentPresentation = Object.freeze({cents, mode});
})(typeof window === 'undefined' ? globalThis : window);
