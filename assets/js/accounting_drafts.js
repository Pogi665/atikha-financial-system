(() => {
    'use strict';
    const csrf=JSON.parse(document.getElementById('draft-data').textContent).csrf_token;
    const form=document.getElementById('draft-filters'),error=document.getElementById('draft-error'),body=document.getElementById('draft-list');
    const status=document.getElementById('draft-status'),filterStatus=document.getElementById('draft-filter-status');
    let sequence=0,pending=null,applied=null;
    const filters=()=>Object.fromEntries(new FormData(form));
    function mark(){filterStatus.textContent=!applied||Object.entries(filters()).some(([k,v])=>applied[k]!==v)||Array.from(form.querySelectorAll('input')).some(el=>!el.validity.valid)?'Changes not applied.':'';}
    function rows(records){
        const fragment=document.createDocumentFragment();
        records.forEach(row=>{
            const tr=document.createElement('tr');tr.dataset.draftId=String(row.id);
            [row.id,row.source_book+' / '+(row.entry_date||'No accounting date'),row.description||'No purpose yet',row.updated_at_display].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});
            const actions=document.createElement('td'),a=document.createElement('a');
            a.href=({CRB:'cash_receipt.php',CDB:'cash_disbursement.php',GJ:'general_journal.php'}[row.source_book])+'?draft_id='+row.id;a.textContent='Continue';actions.append(a,' ');
            const b=document.createElement('button');b.textContent='Discard';b.type='button';
            b.addEventListener('click',async()=>{
                if(!confirm('Discard this draft and its reserved images? No posted financial records will change.'))return;
                b.disabled=true;error.textContent='';let discarded=false;
                try{
                    const r=await fetch('accounting_actions.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'discard',csrf_token:csrf,draft_id:String(row.id),revision:String(row.revision)})});
                    const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Could not discard this draft.');discarded=true;b.textContent='Discarded';a.removeAttribute('href');await load();
                }catch(e){error.textContent=e.message;if(!discarded&&b.isConnected)b.disabled=false;}
            });actions.append(b);tr.append(actions);fragment.append(tr);
        });
        if(!records.length){const tr=document.createElement('tr'),td=document.createElement('td');td.colSpan=5;td.textContent='No saved drafts match these filters.';tr.append(td);fragment.append(tr);}
        return fragment;
    }
    async function load(){
        if(!form.reportValidity()){error.textContent='Correct the last saved dates before applying filters. The displayed list has not changed.';mark();return;}
        const request=++sequence,snapshot=filters();pending?.abort();const controller=new AbortController();pending=controller;
        error.textContent='';status.textContent='Loading saved drafts...';body.setAttribute('aria-busy','true');
        try{
            const r=await fetch('accounting_actions.php?'+new URLSearchParams({action:'drafts',...snapshot}),{credentials:'same-origin',cache:'no-store',signal:controller.signal});
            const d=await r.json();if(request!==sequence)return;if(!r.ok||!d.ok)throw new Error(d.error||'Could not load drafts.');if(!Array.isArray(d.result))throw new Error('Unexpected draft-list response.');
            const next=rows(d.result);body.replaceChildren(next);applied=snapshot;status.textContent=d.result.length+' saved draft'+(d.result.length===1?'':'s')+' loaded.';mark();
        }catch(e){if(request!==sequence||e.name==='AbortError')return;status.textContent='';error.textContent=(applied?'The previously loaded list is still shown. ':'')+'Could not apply these filters. '+e.message;mark();}
        finally{if(request===sequence){body.setAttribute('aria-busy','false');pending=null;}}
    }
    form.addEventListener('input',mark);form.addEventListener('change',mark);
    form.addEventListener('submit',e=>{e.preventDefault();load();});load();
})();
