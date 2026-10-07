/* Presentation checks only. Server review/posting remains the accounting authority. */
((scope) => {
    'use strict';
    const cents = value => {
        if (value === '') return 0n;
        if (typeof value !== 'string' || !/^\d{1,13}(\.\d{1,2})?$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    function mode(p) {
        if (p.transaction_kind !== 'ordinary' || !Array.isArray(p.lines)) return 'advanced';
        if ((p.documents || []).some(d => (d.allocations || []).some(a => !p.lines.some(l => l.client_id === a.client_id)))) return 'advanced';
        if (p.lines.some(l => cents(l.credit_amount) === null || cents(l.credit_amount) !== 0n || cents(l.debit_amount) === null)) return 'advanced';
        if (p.lines.length !== 1) return 'split';
        const l = p.lines[0], cash = cents(p.cash_amount), debit = cents(l.debit_amount);
        if (cash === null || debit === null || (p.cash_amount === '') !== (l.debit_amount === '') || cash !== debit || p.cash_project_id !== l.fund_project_id) return 'split';
        // Unknown fields cannot be hidden by a representation we do not understand.
        if (Object.keys(l).some(k => !['client_id','account_id','fund_project_id','debit_amount','credit_amount'].includes(k))) return 'advanced';
        return 'quick';
    }
    scope.PaymentPresentation = Object.freeze({cents, mode});
})(typeof window === 'undefined' ? globalThis : window);
