(() => {
    'use strict';
    const csrf=JSON.parse(document.getElementById('draft-data').textContent).csrf_token;
    const form=document.getElementById('draft-filters'),error=document.getElementById('draft-error'),body=document.getElementById('draft-list');
    const status=document.getElementById('draft-status'),filterStatus=document.getElementById('draft-filter-status');
    let sequence=0,pending=null,applied=null;
    const mutations=new Map();
    const positive=v=>/^\d+$/.test(String(v))&&Number.isSafeInteger(Number(v))&&Number(v)>0;
    const task=row=>row.workflow_kind==='correction'
        ? (row.correction_mode==='reverse_only'?'Reverse an entry recorded by mistake':'Correct a recorded transaction')+' · original journal #'+row.correction_target_journal_id
        : ({advance_release:'Release an advance',advance_liquidation:'Liquidate an advance',advance_return:'Return unused cash'}[row.workflow_kind]
            ||{CRB:'Record money received',CDB:'Record a payment',GJ:'Other journal entry'}[row.source_book]);
    function validate(records){
        if(!Array.isArray(records)||records.some(row=>!row||!positive(row.id)||!positive(row.revision)||!['CRB','CDB','GJ'].includes(row.source_book)
            ||!['ordinary','advance_release','advance_liquidation','advance_return','correction',undefined,null,''].includes(row.workflow_kind)
            ||(row.workflow_kind==='correction'&&(!['reverse_only','reverse_replace'].includes(row.correction_mode)||!positive(row.correction_target_journal_id)))
            ||typeof row.updated_at_display!=='string')||new Set(records.map(row=>String(row.id))).size!==records.length)throw new Error('Unexpected draft-list response.');
        return records;
    }
    async function readList(snapshot={},signal){
        const r=await fetch('accounting_actions.php?'+new URLSearchParams({action:'drafts',...snapshot}),{credentials:'same-origin',cache:'no-store',signal});
        const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Could not load drafts.');return validate(d.result);
    }
    const filters=()=>Object.fromEntries(new FormData(form));
    function mark(){filterStatus.textContent=!applied||Object.entries(filters()).some(([k,v])=>applied[k]!==v)||Array.from(form.querySelectorAll('input')).some(el=>!el.validity.valid)?'Changes not applied.':'';}
    function rows(records){
        const fragment=document.createDocumentFragment();
        records.forEach(row=>{
            const tr=document.createElement('tr');tr.dataset.draftId=String(row.id);
            const correction=row.workflow_kind==='correction',advance=row.workflow_kind?.startsWith('advance_');
            ['Draft #'+row.id,task(row)+' · '+row.source_book+' / '+(row.entry_date||'No accounting date'),row.description||'No purpose or reason yet',row.updated_at_display].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});
            const actions=document.createElement('td'),a=document.createElement('a');
            actions.className='ui-draft-actions';a.className='ui-button ui-draft-continue';
            a.href=(correction?'journal_correction.php':advance?'cash_advance_entry.php':({CRB:'cash_receipt.php',CDB:'cash_disbursement.php',GJ:'general_journal.php'}[row.source_book]))+'?draft_id='+row.id;a.textContent='Continue';actions.append(a,' ');
            const b=document.createElement('button');b.textContent='Discard';b.type='button';b.className='ui-button ui-draft-discard';
            const originalHref=a.getAttribute('href');
            function sync(){
                const state=mutations.get(String(row.id));
                b.disabled=state==='pending'||state==='checking'||state==='gone';
                b.textContent=state==='unknown'?'Check discard result':state==='pending'?'Discarding…':state==='checking'?'Checking…':state==='gone'?'No longer active':'Discard';
                if(state){a.removeAttribute('href');a.setAttribute('aria-disabled','true');}else{a.href=originalHref;a.removeAttribute('aria-disabled');}
            }
            sync();
            b.addEventListener('click',async()=>{
                if(mutations.get(String(row.id))==='unknown'){
                    mutations.set(String(row.id),'checking');sync();error.textContent='';
                    try{
                        // An unfiltered owner-scoped read avoids mistaking an out-of-filter draft for a discard.
                        const records=await readList(),current=records.find(item=>String(item.id)===String(row.id));
                        if(!current){mutations.set(String(row.id),'gone');status.textContent='Draft #'+row.id+' is no longer an active draft. No further discard was sent.';}
                        else if(String(current.revision)!==String(row.revision)){mutations.set(String(row.id),'unknown');throw new Error('This draft changed. Apply filters to load its current revision before taking another action.');}
                        else{mutations.delete(String(row.id));status.textContent='Draft #'+row.id+' is still saved. Continue or deliberately choose Discard again.';}
                    }catch(e){mutations.set(String(row.id),'unknown');error.textContent='Discard result remains unverified. '+e.message;}
                    finally{sync();}return;
                }
                if(!await AtikhaUI.confirm({title:'Discard Draft?',message:'Discard draft #'+row.id+' ('+task(row)+') and its reserved images? No recorded financial records will change.',confirm:'Discard Draft',destructive:true}))return;
                mutations.set(String(row.id),'pending');sync();error.textContent='';let discarded=false;
                try{
                    const r=await fetch(correction?'journal_correction_actions.php':advance?'cash_advance_actions.php':'accounting_actions.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'discard',csrf_token:csrf,draft_id:String(row.id),revision:String(row.revision)})});
                    const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Could not discard this draft.');discarded=true;mutations.set(String(row.id),'gone');sync();await load();
                }catch(e){if(!discarded)mutations.set(String(row.id),'unknown');sync();error.textContent='Discard was not confirmed. Check its result before another action. '+e.message;}
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
            const records=await readList(snapshot,controller.signal);if(request!==sequence)return;
            // A verified changed revision is safe to resume; an unresolved same-revision mutation remains guarded.
            records.forEach(row=>{const old=body.querySelector('[data-draft-id="'+row.id+'"]');if(old&&old.dataset.revision!==String(row.revision)&&mutations.get(String(row.id))==='unknown')mutations.delete(String(row.id));});
            const next=rows(records);Array.from(next.children).forEach(tr=>{const row=records.find(item=>String(item.id)===tr.dataset.draftId);if(row)tr.dataset.revision=String(row.revision);});body.replaceChildren(next);applied=snapshot;status.textContent=records.length+' saved draft'+(records.length===1?'':'s')+' loaded.';mark();
        }catch(e){if(request!==sequence||e.name==='AbortError')return;status.textContent='';error.textContent=(applied?'The previously loaded list is still shown. ':'')+'Could not apply these filters. '+e.message;mark();}
        finally{if(request===sequence){body.setAttribute('aria-busy','false');pending=null;}}
    }
    form.addEventListener('input',mark);form.addEventListener('change',mark);
    form.addEventListener('submit',e=>{e.preventDefault();load();});load();
})();
