(function () {
    'use strict';
    const payload = document.getElementById('records-data');
    if (!payload) return;
    const data = JSON.parse(payload.textContent);
    const missing = (value, fallback = 'Not recorded') => String(value ?? '').trim() === '' ? fallback : String(value);
    const money = value => {
        const cents = BigInt(value), absolute = cents < 0n ? -cents : cents;
        return (cents < 0n ? '-' : '') + '₱' + (absolute / 100n).toLocaleString('en-PH') + '.' + String(absolute % 100n).padStart(2, '0');
    };
    const text = DataTable.render.text();
    const textRender = fallback => (value, type) => {
        const label = missing(value, fallback);
        return type === 'display' ? text.display(label) : label;
    };
    data.rows.forEach(row => {
        row.search_fields = [row.party, row.reference_number, row.category,
            missing(row.purpose, 'Not specified'), missing(row.project_code, 'Unallocated')].join(' ');
    });
    const table = new DataTable('#records-table', {
        data: data.rows, pageLength: 10, lengthChange: false, autoWidth: false,
        displayStart: Math.min((Math.max(1, data.page) - 1) * 10, Math.max(0, Math.floor((data.rows.length - 1) / 10) * 10)),
        search: {regex: false, smart: true},
        order: [[0, 'desc']], orderFixed: {post: [[7, 'desc']]},
        layout: {topStart: null, topEnd: 'search', bottomStart: 'info', bottomEnd: {paging: {numbers: false, firstLast: false}}},
        language: {
            search: 'Search records:', info: 'Showing _START_–_END_ of _TOTAL_ records.',
            infoEmpty: 'Showing 0–0 of 0 records.', infoFiltered: '',
            emptyTable: 'No records match the selected period or filters.',
            zeroRecords: 'No records match your search. Clear search or filters to continue.',
            paginate: {previous: 'Previous', next: 'Next'},
            aria: {paginate: {previous: 'Previous', next: 'Next'}}
        },
        columns: [
            {data: 'txn_date', type: 'string', render: textRender()},
            {data: 'txn_type', render: (value, type) => type === 'display' ? '<span class="records-badge ' + (value === 'Incoming' ? 'is-incoming' : 'is-expense') + '">' + text.display(value) + '</span>' : value},
            {data: 'reference_number', render: textRender()},
            {data: 'party', render: textRender()},
            {data: 'amount_cents', type: 'num', className: 'records-amount', render: (value, type) => type === 'display' || type === 'filter' ? money(value) : Number(value)},
            {data: null, orderable: false, searchable: false, render: () => '<button type="button" class="records-view">View</button>'},
            {data: 'search_fields', visible: false, orderable: false},
            {data: 'chronology', visible: false, searchable: false, type: 'num'}
        ]
    });
    function updateSummaries() {
        let incoming = 0n, expense = 0n, purpose = 0, allocation = 0, affected = 0;
        table.rows({search: 'applied'}).data().each(row => {
            if (row.txn_type === 'Incoming') incoming += BigInt(row.amount_cents);
            else expense += BigInt(row.amount_cents);
            purpose += Number(row.missing_purpose); allocation += Number(row.unallocated);
            affected += Number(row.missing_purpose || row.unallocated);
        });
        Object.entries({incoming, expense, net: incoming - expense}).forEach(([key, value]) => {
            const element = document.getElementById('records-total-' + key);
            if (element) element.textContent = money(value);
        });
        document.getElementById('records-completeness-counts').textContent = 'Data completeness: ' + affected + ' affected records; ' + purpose + ' missing purpose; ' + allocation + ' Unallocated.';
        const page = table.page.info();
        document.getElementById('records-page').textContent = 'Page ' + (page.recordsDisplay ? page.page + 1 : 0) + ' of ' + page.pages;
        document.getElementById('records-empty-help').hidden = page.recordsDisplay !== 0;
        document.querySelectorAll('.records-page .dt-paging button').forEach(button => {
            button.removeAttribute('role'); // Native button semantics, rather than the upstream link role.
            button.disabled = button.getAttribute('aria-disabled') === 'true';
        });
    }
    table.on('draw', updateSummaries); updateSummaries();
    const scope = document.getElementById('date-scope'), from = document.getElementById('from'), to = document.getElementById('to');
    function applyScope() {
        if (scope.value === 'all') { from.value = ''; to.value = ''; }
        if (scope.value === 'month') { from.value = data.monthStart; to.value = data.today; }
        from.readOnly = to.readOnly = scope.value !== 'custom';
    }
    scope.addEventListener('change', applyScope); applyScope();
    const dialog = document.getElementById('transaction-dialog');
    let opener;
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const items = Array.from(dialog.querySelectorAll('button:not([disabled]), a[href]')).filter(item => item.getClientRects().length);
        const first = items[0], last = items[items.length - 1];
        if (!first) { event.preventDefault(); return; }
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    document.getElementById('transaction-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { if (opener?.isConnected) opener.focus(); });
    document.getElementById('records-table').addEventListener('click', event => {
        const button = event.target.closest('.records-view');
        if (!button) return;
        const row = table.row(button.closest('tr')).data();
        if (!row) return;
        opener = button; dialog.dataset.transaction = row.identity;
        const details = document.getElementById('transaction-details'); details.replaceChildren();
        const values = [
            ['Type', row.txn_type], ['Reference number', missing(row.reference_number)], ['Date', row.txn_date], ['Amount', money(row.amount_cents)],
            [row.txn_type === 'Incoming' ? 'Received from' : 'Paid to', missing(row.party)], ['Category', missing(row.category)],
            ['Full purpose', missing(row.purpose, 'Not specified — requires attention.')], ['Internal project', missing(row.project_code, 'Unallocated')],
            ['Recorded by', missing(row.recorded_by)], ['Cumulative Net Recorded Cash Flow After Transaction', money(row.running_cents)]
        ];
        values.forEach(([label, value]) => {
            const dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = label; dd.textContent = value; details.append(dt, dd);
        });
        const documents = document.getElementById('transaction-documents'); documents.replaceChildren();
        if (!row.documents.length) { const p = document.createElement('p'); p.textContent = row.document_status; documents.append(p); }
        row.documents.forEach(file => {
            const link = document.createElement('a'); link.textContent = 'Download ' + file.name; link.href = file.url + '&download=1';
            const wrapper = document.createElement('div'); wrapper.append(link);
            if (file.previewable) {
                const img = document.createElement('img'); img.alt = file.name; img.src = file.url;
                img.addEventListener('error', () => { img.remove(); const p = document.createElement('p'); p.textContent = 'Supporting document preview unavailable.'; wrapper.append(p); });
                wrapper.append(img);
            }
            documents.append(wrapper);
        });
        dialog.showModal(); document.getElementById('transaction-close').focus();
    });
}());
