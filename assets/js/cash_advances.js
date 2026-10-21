(() => {
    'use strict';
    const $=id=>document.getElementById(id),config=JSON.parse($('advance-data').textContent),form=$('ca-filters');
    let sequence=0,controller=null,applied=null,current=1,pages=1;
    const node=(tag,text)=>{const n=document.createElement(tag);n.textContent=text;return n;};
    // Formatting only: keep exact decimal strings; never round through floating point.
    function money(value){
        if(typeof value!=='string'||!/^(-?)(\d+)\.(\d{2})$/.test(value))throw new Error('The advance amount response could not be verified.');
        const [whole,fraction]=value.split('.');
        return 'PHP '+whole.replace(/\B(?=(\d{3})+(?!\d))/g,',')+'.'+fraction;
    }
    const printState=new Map();
    function printDetails(open){
        if(open){root.querySelectorAll('details[data-ca-print]').forEach(d=>{if(!printState.has(d))printState.set(d,d.open);d.open=true;});}
        else {printState.forEach((wasOpen,d)=>{if(d.isConnected)d.open=wasOpen;});printState.clear();}
    }
    const root=$('advance-register'),printMedia=matchMedia('print');
    window.addEventListener('beforeprint',()=>printDetails(true));
    window.addEventListener('afterprint',()=>printDetails(false));
    printMedia.addEventListener('change',e=>printDetails(e.matches));
    $('ca-print').addEventListener('click',()=>window.print());
    if(form)$('ca-print').disabled=true;
    $('ca-health-link').addEventListener('click',e=>{
        e.preventDefault();const details=$('ca-reconciliation-details');if(details.hidden)return;details.open=true;
        details.querySelector('summary').focus();details.scrollIntoView({block:'start'});
    });
    function filterSummary(snapshot){
        const labels={party_id:'Employee',project_id:'Originating project',control_account_id:'Control account',status:'Settlement status',overdue:'Overdue'};
        const parts=Object.entries(labels).map(([key,label])=>{
            const option=Array.from(form.elements.namedItem(key).options).find(o=>o.value===snapshot[key]);
            return label+': '+(option?.textContent||snapshot[key]||'All');
        });
        parts.push('Search: '+(snapshot.search||'None'));
        return 'Applied filters - '+parts.join(' · ');
    }
    function reconciliationView(rows,asof){
        if(!Array.isArray(rows))throw new Error('The reconciliation response could not be verified.');
        const fragment=document.createDocumentFragment();let bad=0;
        rows.forEach(r=>{
            if(typeof r.ok!=='boolean'||!Array.isArray(r.errors))throw new Error('The reconciliation response could not be verified.');
            const discrepancy=!r.ok||r.errors.length>0;
            const article=node('article','');article.className=discrepancy?'ca-control ca-discrepancy':'ca-control ca-ok';
            article.append(node('h3',r.account_name));
            article.append(node('p','Register '+money(r.register_balance)+' · Ledger '+money(r.ledger_balance)+' · Difference '+money(r.difference)+' · '+(r.ok?'Reconciled':'Integrity discrepancy')));
            r.errors.forEach(error=>article.append(node('p',error)));
            if(discrepancy)bad++;
            fragment.append(article);
        });
        if(!rows.length)fragment.append(node('p',config.role==='Admin'?'No designated control accounts. Accounting Setup can designate unused eligible accounts.':'No designated control accounts.'));
        const text=bad
            ?'Integrity discrepancy in '+bad+' control account'+(bad===1?'':'s')+' · As of '+asof+'. This independent check includes accounts outside the register filters. Posting may be blocked; inspect the details before proceeding.'
            :rows.length?'All '+rows.length+' control account'+(rows.length===1?'':'s')+' reconciled · As of '+asof+'. This check is independent of register filters.'
            :'No designated control accounts · As of '+asof+'.';
        return {fragment,bad,text};
    }
    function unverifiedReconciliation(state){
        const health=$('ca-health'),details=$('ca-reconciliation-details');
        health.dataset.state=state;
        health.classList.remove('ca-health-warning');
        health.classList.toggle('ca-health-loading',state==='loading');
        health.classList.toggle('ca-health-unavailable',state==='unavailable');
        $('ca-health-message').textContent=state==='loading'
            ?'Loading the independent control-account check…'
            :'Reconciliation unavailable — not verified. No successful control-account check is available.';
        $('ca-health-link').hidden=true;details.hidden=true;details.open=false;
        $('ca-reconciliation').replaceChildren();
        $('ca-reconciliation-basis').textContent='No successful control-account check is available.';
    }
    function showReconciliation(view,asof){
        $('ca-reconciliation').replaceChildren(view.fragment);
        $('ca-health-message').textContent=view.text;
        $('ca-health').classList.toggle('ca-health-warning',view.bad>0);
        $('ca-health').classList.remove('ca-health-loading','ca-health-unavailable');
        $('ca-health').dataset.state=view.bad>0?'discrepancy':'healthy';
        $('ca-health-link').hidden=false;$('ca-reconciliation-details').hidden=false;
        $('ca-reconciliation-basis').textContent='As of '+asof+' · All posted advances and control-account lines are included, independently of employee, project, status, overdue and search filters.';
    }
    function renderRow(r,asof){
        if(!Number.isSafeInteger(r.id)||r.id<=0||typeof r.status!=='string')throw new Error('The advance row response could not be verified.');
        const tr=document.createElement('tr'),first=document.createElement('td'),a=node('a',r.number);
        tr.dataset.advanceId=String(r.id);a.href='cash_advances.php?'+new URLSearchParams({advance_id:String(r.id),as_of:asof});
        first.append(a,node('p',r.employee));tr.append(first);
        const purpose=document.createElement('td');purpose.append(node('p',r.purpose),node('p',r.project),node('small','Released '+r.release_date));tr.append(purpose);
        const amount=document.createElement('td');amount.className='ca-row-outstanding';amount.append(node('strong',money(r.outstanding)));tr.append(amount);
        const status=document.createElement('td'),badge=node('span',r.status.replaceAll('_',' '));badge.className='ca-badge';
        status.append(node('p','Due '+r.due_date),badge,node('p',r.overdue_days>0?r.overdue_days+' days overdue ('+r.aging_bucket+')':'Not overdue at this date'));tr.append(status);
        const breakdown=document.createElement('td'),details=document.createElement('details');details.dataset.caPrint='';
        details.append(node('summary','Released / settled'));
        ['released','liquidated','returned'].forEach(key=>details.append(node('p',key[0].toUpperCase()+key.slice(1)+': '+money(r[key]))));
        breakdown.append(details);tr.append(breakdown);return tr;
    }
    function registerView(d){
        if(!d||!Array.isArray(d.rows)||!Number.isSafeInteger(d.count)||d.count<0||!Number.isSafeInteger(d.page)||d.page<1||!Number.isSafeInteger(d.pages)||d.pages<d.page||typeof d.as_of!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(d.as_of))throw new Error('The advance register response could not be verified.');
        const rows=document.createDocumentFragment(),totals=document.createDocumentFragment();
        d.rows.forEach(r=>rows.append(renderRow(r,d.as_of)));
        if(!d.rows.length){
            const tr=document.createElement('tr'),td=node('td',config.has_posted_advances?'No posted advances match these filters.':'No posted advances yet.');
            td.colSpan=5;td.append(node('p',config.has_posted_advances?'Try another date or adjust the applied filters.':config.role==='Admin'?'Use Release an advance to record a release after reviewing it.':'Posted advances will appear here when recorded.'));
            tr.append(td);rows.append(tr);
        }
        ['outstanding','released','liquidated','returned'].forEach(key=>{
            const span=node('span',key[0].toUpperCase()+key.slice(1)+' ');if(key==='outstanding')span.className='ca-outstanding';
            span.append(node('strong',money(d.totals[key])));totals.append(span);
        });
        return {rows,totals,reconciliation:reconciliationView(d.reconciliation,d.as_of)};
    }
    function mark(){if(form)$('ca-filter-status').textContent=!applied||Object.entries(Object.fromEntries(new FormData(form))).some(([k,v])=>applied[k]!==v)?'Changes not applied.':'';}
    async function load(page=1,useApplied=false){
        if(!form.reportValidity())return;
        const request=++sequence,snapshot=useApplied&&applied?{...applied}:Object.fromEntries(new FormData(form));
        controller?.abort();controller=new AbortController();$('ca-status').textContent='Loading advances…';$('ca-error').textContent='';$('ca-rows').setAttribute('aria-busy','true');
        if(!applied)unverifiedReconciliation('loading');
        try{
            const res=await fetch('cash_advance_actions.php?'+new URLSearchParams({action:'register',...snapshot,page:String(page)}),{credentials:'same-origin',cache:'no-store',signal:controller.signal});
            const data=await res.json();if(request!==sequence)return;if(!res.ok||!data.ok)throw new Error(data.error||'Could not load advances.');
            // Build the entire successful view before replacing the applied snapshot.
            const d=data.result,view=registerView(d);
            $('ca-rows').replaceChildren(view.rows);$('ca-totals').replaceChildren(view.totals);showReconciliation(view.reconciliation,d.as_of);
            $('ca-basis').textContent='POSTED advances · As of '+d.as_of;$('ca-notice').textContent=d.restatement_notice;
            current=d.page;pages=d.pages;$('ca-page').textContent='Page '+current+' of '+pages+' · '+d.count+' matching advances';
            $('ca-prev').disabled=current<=1;$('ca-next').disabled=current>=pages;applied=snapshot;
            $('ca-applied-filters').textContent=filterSummary(snapshot);
            const first=d.rows.length?(d.page-1)*25+1:0,last=d.rows.length?first+d.rows.length-1:0;
            $('ca-print-scope').textContent='Current page only - Page '+d.page+' of '+d.pages+'; rows '+first+'-'+last+' of '+d.count+' matching advances. Printing includes this page, not the full register.';
            $('ca-totals-basis').textContent='Totals for all '+d.count+' matching advances (all pages)';
            $('ca-print').disabled=false;mark();$('ca-status').textContent='Advance register loaded.';
        }catch(e){
            if(request!==sequence||e.name==='AbortError')return;
            $('ca-error').textContent=(applied?'The previously loaded register, balances and reconciliation remain visible for the last successful date and filters. ':'The register and independent reconciliation have not loaded. ')+(e instanceof SyntaxError?'The server response could not be read. Try loading again.':e.message);
            if(!applied)unverifiedReconciliation('unavailable');
            $('ca-status').textContent='';mark();
        }finally{if(request===sequence)$('ca-rows').setAttribute('aria-busy','false');}
    }
    if(form){
        form.addEventListener('input',mark);form.addEventListener('change',mark);form.addEventListener('submit',e=>{e.preventDefault();load();});
        $('ca-prev').addEventListener('click',()=>load(current-1,true));$('ca-next').addEventListener('click',()=>load(current+1,true));load();
    }else{
        showReconciliation(reconciliationView(config.detail.reconciliation,config.detail.as_of),config.detail.as_of);
    }
    $('ca-extension')?.addEventListener('submit',async e=>{
        e.preventDefault();const b=e.target.querySelector('button');b.disabled=true;$('ca-error').textContent='';
        try{
            const res=await fetch('cash_advance_actions.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'extend_due',csrf_token:config.csrf_token,advance_id:String(config.detail.id),revision:String(config.detail.revision),...Object.fromEntries(new FormData(e.target))})});
            const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'Could not extend deadline.');location.reload();
        }catch(err){$('ca-error').textContent=err instanceof SyntaxError?'The deadline response could not be verified. Reload this advance before trying another extension.':err.message==='Failed to fetch'?'The connection was interrupted. Reload this advance to check its deadline before trying another extension.':err.message;$('ca-error').focus();b.disabled=false;}
    });
})();
