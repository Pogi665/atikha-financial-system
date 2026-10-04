(function () {
    'use strict';
    const payload=document.getElementById('records-data');if(!payload)return;
    const data=JSON.parse(payload.textContent);
    const money=value=>{const c=BigInt(value),a=c<0n?-c:c;return(c<0n?'-':'')+'₱'+(a/100n).toLocaleString('en-PH')+'.'+String(a%100n).padStart(2,'0');};
    const text=DataTable.render.text();
    const label=value=>String(value??'').trim()||'Not recorded';
    const render=(value,type)=>type==='display'?text.display(label(value)):label(value);
    const account=r=>(r.account_code||'#'+r.account_id)+' · '+r.account_name+' · '+r.account_type;
    const table=new DataTable('#records-table',{
        data:data.rows,pageLength:10,lengthChange:false,autoWidth:false,
        displayStart:Math.min((data.page-1)*10,Math.max(0,Math.floor((data.rows.length-1)/10)*10)),
        search:{regex:false,smart:true},order:[[0,'desc']],orderFixed:{post:[[7,'desc'],[8,'asc']]},
        layout:{topStart:null,topEnd:'search',bottomStart:'info',bottomEnd:{paging:{numbers:false,firstLast:false}}},
        language:{search:'Search records:',info:'Showing _START_–_END_ of _TOTAL_ lines.',infoEmpty:'Showing 0–0 of 0 lines.',infoFiltered:'',emptyTable:'No posted lines match these filters.',zeroRecords:'No lines match your search.',paginate:{previous:'Previous',next:'Next'}},
        columns:[{data:'entry_date',render},{data:'reference',render},{data:'description',render},
            {data:null,render:(_,type,r)=>render(account(r),type)},
            ...['debit_cents','credit_cents'].map(key=>({data:key,type:'num',className:'records-amount',render:(v,type)=>type==='display'||type==='filter'?money(v):Number(v)})),
            {data:null,orderable:false,searchable:false,render:()=>'<button type="button" class="records-view">View</button>'},
            {data:'journal_id',visible:false,searchable:false,type:'num'},{data:'line_id',visible:false,searchable:false,type:'num'}]
    });
    function totals(){let d=0n,c=0n;table.rows({search:'applied'}).data().each(r=>{d+=BigInt(r.debit_cents);c+=BigInt(r.credit_cents);});
        Object.entries({debit:d,credit:c,difference:d-c}).forEach(([k,v])=>{document.getElementById('records-total-'+k).textContent=money(v);});
        const p=table.page.info();document.getElementById('records-page').textContent='Page '+(p.recordsDisplay?p.page+1:0)+' of '+p.pages;
        document.getElementById('records-empty-help').hidden=p.recordsDisplay!==0;
        document.querySelectorAll('.records-page .dt-paging button').forEach(b=>{b.removeAttribute('role');b.disabled=b.getAttribute('aria-disabled')==='true';});
    }
    table.on('draw',totals);totals();
    const scope=document.getElementById('date-scope'),from=document.getElementById('from'),to=document.getElementById('to');
    function dates(){if(scope.value==='all'){from.value=to.value='';}if(scope.value==='month'){from.value=data.monthStart;to.value=data.today;}from.readOnly=to.readOnly=scope.value!=='custom';}
    scope.addEventListener('change',dates);dates();
    const dialog=document.getElementById('transaction-dialog');let opener;
    document.getElementById('transaction-close').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>{if(opener?.isConnected)opener.focus();});
    dialog.addEventListener('keydown',e=>{if(e.key!=='Tab')return;const buttons=Array.from(dialog.querySelectorAll('button:not([disabled]),a[href]')).filter(b=>b.getClientRects().length);const first=buttons[0],last=buttons[buttons.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
    document.getElementById('records-table').addEventListener('click',event=>{
        const button=event.target.closest('.records-view');if(!button)return;const row=table.row(button.closest('tr')).data();if(!row)return;
        const j=data.journals[row.journal_id];opener=button;dialog.dataset.transaction=String(row.journal_id);
        const details=document.getElementById('transaction-details');details.replaceChildren();
        [['Journal ID',row.journal_id],['Date',row.entry_date],['Reference',label(row.reference)],['Description',row.description],['Posted by',label(row.posted_by)],['Total Debits',money(j.debit_cents)],['Total Credits',money(j.credit_cents)],['Difference',money(BigInt(j.debit_cents)-BigInt(j.credit_cents))]].forEach(([k,v])=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=k;dd.textContent=v;details.append(dt,dd);});
        const container=document.getElementById('transaction-documents');container.replaceChildren();const t=document.createElement('table');t.className='journal-detail-lines';
        const head=document.createElement('thead'),hr=document.createElement('tr');['Account','Fund / Project ID','Debit','Credit'].forEach(v=>{const th=document.createElement('th');th.textContent=v;hr.append(th);});head.append(hr);t.append(head);
        const body=document.createElement('tbody');j.lines.forEach(r=>{const tr=document.createElement('tr');[account(r),r.fund_project_id??'Not tagged',money(r.debit_amount.replace('.','')),money(r.credit_amount.replace('.',''))].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});body.append(tr);});t.append(body);container.append(t);
        (j.attachments || []).forEach(a=>{
            const article=document.createElement('article');article.className='journal-receipt';
            const heading=document.createElement('h3');heading.textContent=a.name;article.append(heading);
            const meta=document.createElement('p');meta.textContent='Uploaded by '+label(a.uploaded_by)+' ? '+a.size+' bytes ? SHA-256 '+a.sha256;article.append(meta);
            const link=document.createElement('a');link.href=a.url;link.target='_blank';link.rel='noopener';link.textContent='View receipt';article.append(link);
            const download=document.createElement('a');download.href=a.url+'&download=1';download.textContent='Download receipt';article.append(' ? ',download);
            container.append(article);
        });
        dialog.showModal();document.getElementById('transaction-close').focus();
    });
}());
