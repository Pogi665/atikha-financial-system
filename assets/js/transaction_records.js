(() => {
    'use strict';
    const initial=JSON.parse(document.getElementById('tx-data').textContent),form=document.getElementById('tx-filters'),dialog=document.getElementById('tx-dialog');
    const $=id=>document.getElementById(id),node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
    let model=initial.model,controller=null,generation=0,opener=null;
    const link=(text,url,cls='ui-button')=>{const a=node('a',text,cls);a.href=url;return a;};
    const money=v=>{const c=BigInt(String(v).replace('.',''));return '₱'+(c/100n).toLocaleString('en-PH')+'.'+String(c%100n).padStart(2,'0');};
    function render(){
        if(!model)return;
        const body=$('tx-rows');body.replaceChildren();
        for(const row of model.rows){
            const tr=node('tr');const cells=[row.date_label,row.type+' #'+row.journal_id,row.reference,row.party,row.purpose,row.project,row.amount_text];
            cells.forEach((value,i)=>{const td=node('td',value);td.dataset.label=['Date','Type','Reference','Party','Purpose','Project','Amount / Summary'][i];tr.append(td);});
            const td=node('td'),button=node('button','View','ui-button');button.type='button';button.setAttribute('aria-label','View '+row.type+' #'+row.journal_id);button.dataset.journal=row.journal_id;button.addEventListener('click',()=>preview(row,button));td.append(button);tr.append(td);body.append(tr);
        }
        if(!model.rows.length){const tr=node('tr'),td=node('td','No transactions match these filters.');td.colSpan=8;tr.append(td);body.append(tr);}
        $('tx-count').textContent=model.matching_count+' transactions found';$('tx-page').textContent='Page '+model.page+' of '+model.pages;
        $('tx-previous').disabled=model.page<=1;$('tx-next').disabled=model.page>=model.pages;
        const c=model.criteria,parts=[(c.from?new Date(c.from+'T12:00:00Z').toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric',timeZone:'UTC'}):'All dates')+' to '+(c.to?new Date(c.to+'T12:00:00Z').toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric',timeZone:'UTC'}):'today')];
        if(c.book)parts.push({CDB:'Payments',CRB:'Money Received',GJ:'General Journal'}[c.book]);
        if(c.account_id)parts.push('Account: '+(model.accounts.find(a=>String(a.CategoryID)===c.account_id)?.Name||c.account_id));
        if(c.type)parts.push(c.type);if(c.project_id)parts.push('Project: '+(c.project_id==='0'?'Organization operations':model.projects.find(p=>String(p.id)===c.project_id)?.name));
        if(c.search)parts.push('Search: '+c.search);$('tx-applied').replaceChildren(...parts.map(t=>node('span',t,'tx-chip')));
        const params=new URLSearchParams(c);params.delete('page');params.delete('context');params.set('kind',model.context==='records'?'history':model.context);
        const nav=$('tx-exports');nav.replaceChildren();
        for(const [label,representation,format] of [['Transaction list — PDF','transactions','pdf'],['Transaction list — Excel','transactions','xlsx'],['Transaction list — CSV','transactions','csv'],['Detailed journal lines — CSV','transaction_lines','csv'],['Print transaction list','transactions','print']]){
            const p=new URLSearchParams(params);p.set('representation',representation);p.set('format',format);const a=link(label,'financial_export.php?'+p);if(format==='print'){a.target='_blank';a.rel='noopener';}nav.append(a);
        }
        const url=new URL(location.href);url.search=new URLSearchParams({...c,page:model.page});history.replaceState(null,'',url);
    }
    function fields(items){const dl=node('dl',undefined,'tx-summary');for(const [name,value] of items){const div=node('div');div.append(node('dt',name),node('dd',value||'Not recorded'));dl.append(div);}return dl;}
    function details(title){const d=node('details'),s=node('summary',title);s.setAttribute('aria-expanded','false');d.addEventListener('toggle',()=>s.setAttribute('aria-expanded',String(d.open)));d.append(s);return d;}
    function preview(row,button){
        opener=button;const j=model.journals[row.journal_id];if(!j)return;
        $('tx-dialog-title').textContent=row.type+' #'+row.journal_id;const box=$('tx-dialog-content');box.replaceChildren();
        box.append(node('p',row.amount_text,'tx-main-amount'),fields([['Party',row.party],['Purpose',row.purpose],['Date',row.date_label],['Reference',row.reference],['Project',row.project],['Book',({CDB:'Cash Disbursements Book',CRB:'Cash Receipts Book',GJ:'General Journal'})[row.book]||row.book],['Recorded by',row.recorded_by]]),link('Open full transaction',row.transaction_url,'ui-button ui-button-primary'));
        const accounting=details('Accounting details'),wrap=node('div',undefined,'tx-table-region'),table=node('table',undefined,'tx-table'),head=node('thead'),hr=node('tr');
        ['Account','Project','Debit (PHP)','Credit (PHP)'].forEach(t=>hr.append(node('th',t)));head.append(hr);table.append(head);const body=node('tbody');
        for(const l of j.lines){const tr=node('tr');[l.account_name,l.project_name_snapshot||(l.fund_project_id==null?'Organization operations':'Project label not recorded'),money(l.debit_amount),money(l.credit_amount)].forEach(t=>tr.append(node('td',t)));body.append(tr);}table.append(body);wrap.append(table);accounting.append(wrap);box.append(accounting);
        const docs=details('Supporting documents'),attachments=j.attachments||[];docs.append(node('p',attachments.length+' documents available in this view.'));for(const a of attachments)docs.append(link(a.name,a.url));box.append(docs);
        if(initial.role==='Admin'&&j.correction_entry_available&&j.correction_eligible){const more=details('More actions');more.append(link('Correct this transaction','journal_correction.php?journal_id='+row.journal_id),link('Reverse an entry recorded by mistake','journal_correction.php?journal_id='+row.journal_id+'&mode=reverse_only'));box.append(more);}
        dialog.showModal();$('tx-dialog-close').focus();
    }
    $('tx-dialog-close').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{(opener?.isConnected?opener:$('tx-count')).focus();});
    async function load(criteria){
        controller?.abort();controller=new AbortController();const serial=++generation;
        $('tx-error').textContent='';$('tx-count').setAttribute('aria-busy','true');
        try{const params=new URLSearchParams({...criteria,format:'transactions'}),r=await fetch('financial_records.php?'+params,{credentials:'same-origin',cache:'no-store',signal:controller.signal});const next=await r.json();if(serial!==generation)return;if(!r.ok||!next.ok||!Array.isArray(next.rows)||!Number.isSafeInteger(next.matching_count))throw new Error(next.error||'The results could not be confirmed.');model=next;render();$('tx-draft-notice').textContent='';}
        catch(e){if(e.name==='AbortError')return;$('tx-error').textContent=e.message+' Your last successful results are retained.';$('tx-error').focus();}
        finally{if(serial===generation)$('tx-count').removeAttribute('aria-busy');}
    }
    form.addEventListener('submit',e=>{e.preventDefault();load({...Object.fromEntries(new FormData(form)),page:1});});
    form.addEventListener('input',()=>$('tx-draft-notice').textContent='Filters changed — Apply to update results.');
    $('tx-previous').addEventListener('click',()=>load({...model.criteria,page:model.page-1}));$('tx-next').addEventListener('click',()=>load({...model.criteria,page:model.page+1}));
    document.querySelectorAll('[data-tx-sort]').forEach(b=>b.addEventListener('click',()=>{const col=b.dataset.txSort,next=model?.criteria.sort===col+':asc'?'desc':'asc';form.elements.sort.value=col+':'+next;load({...model.criteria,sort:form.elements.sort.value,page:1});}));
    for(const d of document.querySelectorAll('#tx-records details')){const s=d.querySelector(':scope > summary');if(s){s.setAttribute('aria-expanded',String(d.open));d.addEventListener('toggle',()=>s.setAttribute('aria-expanded',String(d.open)));}}
    render();
})();
