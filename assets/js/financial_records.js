(function () {
    'use strict';
    const payload=document.getElementById('records-data');if(!payload)return;
    let data=JSON.parse(payload.textContent);
    const main=document.querySelector('.records-page').dataset.context==='records';
    let controller=null;
    const money=value=>{const c=BigInt(value),a=c<0n?-c:c;return(c<0n?'-':'')+'₱'+(a/100n).toLocaleString('en-PH')+'.'+String(a%100n).padStart(2,'0');};
    const text=DataTable.render.text();
    const label=value=>String(value??'').trim()||'Not recorded';
    const render=(value,type)=>type==='display'?text.display(label(value)):label(value);
    const account=r=>(r.account_code||'#'+r.account_id)+' · '+r.account_name+' · '+r.account_type;
    const table=new DataTable('#records-table',{
        data:data.rows,pageLength:10,lengthChange:false,autoWidth:false,
        displayStart:Math.min(((data.state?.page||data.page||1)-1)*10,Math.max(0,Math.floor((data.rows.length-1)/10)*10)),
        search:{regex:false,smart:true,search:main?data.state.search:''},order:main&&data.state.order.length?data.state.order:[[0,'desc']],orderFixed:{post:[[7,'desc'],[8,'asc']]},
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
        controller?.draw();
    }
    table.on('draw',totals);totals();
    const scope=document.getElementById('date-scope'),from=document.getElementById('from'),to=document.getElementById('to');
    function dates(){if(scope.value==='all'){from.value=to.value='';}if(scope.value==='month'){from.value=data.monthStart;to.value=data.today;}from.readOnly=to.readOnly=scope.value!=='custom';}
    if(scope){scope.addEventListener('change',dates);dates();}
    if(main){controller=setupFilters();controller.draw();}

    function setupFilters(){
        const form=document.getElementById('records-filters'),type=document.getElementById('type'),select=document.getElementById('account_id');
        const draftStatus=document.getElementById('records-draft-status'),status=document.getElementById('records-request-status');
        const results=document.getElementById('records-results'),summary=document.getElementById('records-applied-summary');
        let accounts=data.accounts,applied=data.ok?{...data.filters}:null,dateMode=data.dateMode;
        let revision=0,sequence=0,pending=null,committing=false,unresolved=false,active=-1,choices=[];
        const rawDates={};
        ['from','to'].forEach(k=>{if(document.getElementById(k).value!==data.draft[k])rawDates[k]=data.draft[k];});
        const clearRejected={};
        ['from','to'].forEach(k=>{
            const button=document.createElement('button');button.type='button';button.className='records-clear-rejected-date';button.textContent='Clear rejected '+(k==='from'?'From':'To')+' value';button.hidden=rawDates[k]===undefined;
            document.getElementById(k+'-error').after(button);clearRejected[k]=button;
            button.addEventListener('click',()=>{delete rawDates[k];document.getElementById(k).value='';button.hidden=true;revision++;errorFields({});mark();});
        });
        const accountLabel=a=>(a.Account_Code||'#'+a.CategoryID)+' · '+a.Name+' · '+a.Account_Type+(!Number(a.Is_Active)?' (inactive)':'');
        const find=id=>accounts.find(a=>String(a.CategoryID)===String(id));
        const selectedLabel=()=>select.value===''?'All accounts':find(select.value)?accountLabel(find(select.value)):select.value+' (unavailable)';
        const combo=document.createElement('div');combo.className='records-account-combo';
        const input=document.createElement('input');input.type='text';input.id='records-account-search';input.autocomplete='off';
        input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-expanded','false');
        input.setAttribute('aria-controls','records-account-options');input.setAttribute('aria-describedby','account_id-error records-account-message');
        const toggle=document.createElement('button');toggle.type='button';toggle.className='records-account-toggle';toggle.textContent='▾';
        toggle.setAttribute('aria-label','Show account choices');toggle.setAttribute('aria-controls','records-account-options');toggle.setAttribute('aria-expanded','false');
        const list=document.createElement('ul');list.id='records-account-options';list.setAttribute('role','listbox');list.setAttribute('aria-labelledby','account-label');list.hidden=true;
        select.before(combo);combo.append(input,toggle,list);select.hidden=true;document.getElementById('account-label').htmlFor=input.id;
        input.value=selectedLabel();
        function draft(){return {from:rawDates.from??from.value,to:rawDates.to??to.value,type:type.value,account_id:select.value};}
        function dirty(){return unresolved||!applied||Object.entries(draft()).some(([k,v])=>v!==applied[k])||from.validity.badInput||to.validity.badInput;}
        function mark(){draftStatus.textContent=dirty()?'Changes not applied.':'';}
        function close(){list.hidden=true;active=-1;input.removeAttribute('aria-activedescendant');input.setAttribute('aria-expanded','false');toggle.setAttribute('aria-expanded','false');}
        function activate(index){
            active=index;Array.from(list.querySelectorAll('[role="option"]')).forEach((el,i)=>el.classList.toggle('is-active',i===active));
            const el=list.querySelectorAll('[role="option"]')[active];if(el){input.setAttribute('aria-activedescendant',el.id);el.scrollIntoView({block:'nearest'});}
        }
        function open(){
            const query=unresolved?input.value.toLocaleLowerCase().trim():'';
            choices=[null,...accounts.filter(a=>(type.value===''||a.Account_Type===type.value)&&(!query||[a.Name,a.Account_Code||'',String(a.CategoryID),'#'+a.CategoryID,accountLabel(a)].some(v=>v.toLocaleLowerCase().includes(query))))];
            list.replaceChildren();
            choices.forEach((a,i)=>{const option=document.createElement('li');option.id='records-account-option-'+i;option.setAttribute('role','option');
                option.setAttribute('aria-selected',String(!unresolved&&select.value===(a?String(a.CategoryID):'')));option.textContent=a?accountLabel(a):'All accounts';
                option.addEventListener('mousedown',e=>e.preventDefault());option.addEventListener('click',()=>choose(a?String(a.CategoryID):''));list.append(option);});
            if(choices.length===1&&query){const hint=document.createElement('li');hint.className='records-account-no-match';hint.textContent='No matching accounts.';list.append(hint);}
            list.hidden=false;input.setAttribute('aria-expanded','true');toggle.setAttribute('aria-expanded','true');active=-1;
        }
        function rebuild(){
            const id=select.value;select.replaceChildren(new Option('All accounts',''));
            accounts.filter(a=>type.value===''||a.Account_Type===type.value).forEach(a=>select.add(new Option(accountLabel(a),String(a.CategoryID))));
            if(id!==''&&!Array.from(select.options).some(o=>o.value===id)){select.add(new Option(find(id)?accountLabel(find(id)):id+' (unavailable)',id));}
            select.value=id;
        }
        function errorFields(errors){
            ['from','to','type','account_id'].forEach(k=>{document.getElementById(k+'-error').textContent=errors[k]||'';document.getElementById(k).setAttribute('aria-invalid',String(Boolean(errors[k])));});
            input.setAttribute('aria-invalid',String(Boolean(errors.account_id)));
            document.getElementById('records-filter-errors').textContent=Object.entries(errors).filter(([k])=>!['from','to','type','account_id'].includes(k)).map(([,v])=>v).join(' ');
        }
        function choose(id){select.value=id;unresolved=false;input.value=selectedLabel();close();revision++;errorFields({});mark();input.focus();}
        input.addEventListener('focus',open);
        input.addEventListener('input',()=>{unresolved=true;revision++;errorFields({});open();mark();});
        input.addEventListener('keydown',event=>{
            if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){
                event.preventDefault();if(list.hidden)open();
                activate(event.key==='Home'?0:event.key==='End'?choices.length-1:event.key==='ArrowDown'?Math.min(active+1,choices.length-1):active<0?choices.length-1:Math.max(0,active-1));
            }else if(event.key==='Enter'&&!list.hidden){event.preventDefault();if(active>=0)choose(choices[active]?String(choices[active].CategoryID):'');}
            else if(event.key==='Escape'){event.preventDefault();unresolved=false;input.value=selectedLabel();close();revision++;mark();}
            else if(event.key==='Tab'){close();}
        });
        toggle.addEventListener('click',()=>{const wasOpen=!list.hidden;input.focus();if(wasOpen)close();else open();});
        combo.addEventListener('focusout',event=>{if(!combo.contains(event.relatedTarget))close();});
        document.addEventListener('click',event=>{if(!combo.contains(event.target))close();});
        type.addEventListener('change',()=>{
            const account=find(select.value);let message='';
            if(select.value!==''&&(!account||(type.value!==''&&account.Account_Type!==type.value))){select.value='';message='Account cleared because it does not match the selected type.';}
            unresolved=false;rebuild();input.value=selectedLabel();close();revision++;errorFields({});document.getElementById('records-account-message').textContent=message;mark();
        });
        [from,to].forEach(field=>field.addEventListener('input',()=>{delete rawDates[field.id];clearRejected[field.id].hidden=true;revision++;errorFields({});mark();}));
        function setDraft(values){
            ['from','to'].forEach(k=>{delete rawDates[k];document.getElementById(k).value=values[k];if(document.getElementById(k).value!==values[k])rawDates[k]=values[k];clearRejected[k].hidden=rawDates[k]===undefined;});
            if(!Array.from(type.options).some(o=>o.value===values.type))type.add(new Option(values.type+' (invalid)',values.type));
            type.value=values.type;select.value='';rebuild();
            if(!Array.from(select.options).some(o=>o.value===values.account_id))select.add(new Option(values.account_id+' (unavailable)',values.account_id));
            select.value=values.account_id;unresolved=false;input.value=selectedLabel();close();document.getElementById('records-account-message').textContent='';mark();
        }
        function tableState(){return {search:table.search(),order:table.order().map(pair=>[Number(pair[0]),pair[1]]),page:table.page()+1};}
        function urlFor(filters,mode,state){
            const url=new URL('financial_records.php',location.href);
            if(mode==='explicit'){url.searchParams.set('from',filters.from);url.searchParams.set('to',filters.to);}
            if(filters.type)url.searchParams.set('type',filters.type);if(filters.account_id)url.searchParams.set('account_id',filters.account_id);
            if(state.search)url.searchParams.set('search',state.search);
            const sort=state.order.map(pair=>pair.join(':')).join(',');if(sort&&sort!=='0:desc')url.searchParams.set('sort',sort);
            if(state.page>1)url.searchParams.set('page',String(state.page));return url;
        }
        function writeUrl(method='replaceState'){if(applied&&!committing)history[method](null,'',urlFor(applied,dateMode,tableState()));}
        function draw(){
            if(!applied){summary.textContent='No filters have been applied successfully.';mark();return;}
            const dates=applied.from&&applied.to?applied.from+' through '+applied.to:applied.from?'On or after '+applied.from:applied.to?'On or before '+applied.to:'All dates';
            const a=find(applied.account_id);
            summary.textContent='Applied: '+dates+' · Account type: '+(applied.type||'All types')+' · Account: '+(a?accountLabel(a):'All accounts')+(table.search()?' · Table search: '+table.search():'');
            mark();writeUrl();
        }
        function clientErrors(){
            const errors={};const values=draft();
            [from,to].forEach(field=>{if(field.validity.badInput||!field.validity.valid||rawDates[field.id]!==undefined){errors[field.id]='Enter a complete, valid '+(field===from?'From':'To')+' date.';}});
            if(!errors.from&&!errors.to&&values.from&&values.to&&values.from>values.to)errors.to='To must be on or after From.';
            if(unresolved)errors.account_id='Select an account from the choices or choose All accounts.';
            return errors;
        }
        function validResponse(result){
            if(!result||result.ok!==true||!result.filters||!Array.isArray(result.rows)||!result.journals||!Array.isArray(result.accounts)||!result.state||!Array.isArray(result.state.order))return false;
            if(!['default','explicit'].includes(result.dateMode)||!['from','to','type','account_id'].every(k=>typeof result.filters[k]==='string'))return false;
            const types=['Asset','Liability','Equity','Income','Expense'];
            if(typeof result.state.search!=='string'||!Number.isInteger(result.state.page)||result.state.page<1||!result.state.order.length||!result.state.order.every(p=>Array.isArray(p)&&Number.isInteger(p[0])&&p[0]>=0&&p[0]<=5&&['asc','desc'].includes(p[1])))return false;
            if(!result.defaults||typeof result.defaults.from!=='string'||typeof result.defaults.to!=='string'||typeof result.notice!=='string'||!result.accounts.every(a=>a&&/^\d+$/.test(String(a.CategoryID))&&typeof a.Name==='string'&&types.includes(a.Account_Type)))return false;
            return result.rows.every(r=>{
                const journal=result.journals[r?.journal_id];
                return r&&typeof r.entry_date==='string'&&typeof r.account_name==='string'&&types.includes(r.account_type)&&typeof r.debit_cents==='string'&&/^\d+$/.test(r.debit_cents)&&typeof r.credit_cents==='string'&&/^\d+$/.test(r.credit_cents)&&journal&&/^\d+$/.test(journal.debit_cents)&&/^\d+$/.test(journal.credit_cents)&&Array.isArray(journal.lines)&&journal.lines.every(l=>l&&typeof l.account_name==='string'&&typeof l.debit_amount==='string'&&typeof l.credit_amount==='string');
            });
        }
        async function load(url,kind,submittedRevision){
            const id=++sequence;if(pending)pending.abort();pending=new AbortController();
            errorFields({});status.textContent='Applying filters…';results.setAttribute('aria-busy','true');
            url.searchParams.set('format','json');
            try{
                const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:pending.signal});
                if(id!==sequence)return;
                if(response.redirected||!(response.headers.get('content-type')||'').includes('application/json'))throw new Error(response.redirected?'Your session may have expired. Sign in again to apply filters.':'Unable to load journal history. Please try again.');
                const result=await response.json();if(id!==sequence)return;
                if(!response.ok||!result.ok){
                    if(result.errors&&typeof result.errors==='object'){if(kind==='history'&&revision===submittedRevision&&result.draft)setDraft(result.draft);errorFields(result.errors);}
                    throw new Error(result.error||'Filters were not applied. Correct the highlighted fields.');
                }
                if(!validResponse(result))throw new Error('Unable to load journal history. Please try again.');
                const state=kind==='history'?result.state:{...tableState(),page:1};if(kind==='reset')state.search='';
                committing=true;data=result;accounts=result.accounts;applied={...result.filters};dateMode=result.dateMode;
                if(revision===submittedRevision)setDraft(result.draft);else{rebuild();if(!unresolved)input.value=selectedLabel();}
                document.getElementById('records-load-error').textContent='';document.getElementById('records-period-notice').textContent=result.notice;
                if(dialog.open)dialog.close();results.hidden=false;
                table.clear().rows.add(result.rows);table.search(state.search).order(state.order).draw();
                table.page(Math.max(0,Math.min(state.page-1,table.page.info().pages-1))).draw('page');
                committing=false;writeUrl(kind==='history'?'replaceState':'pushState');draw();status.textContent='Filters applied.';
            }catch(error){
                if(id!==sequence||error.name==='AbortError')return;
                status.textContent=error.message+' '+(applied?'The last successful results are still shown.':'No results have been loaded.');
                if(kind==='history')writeUrl();mark();
            }finally{if(id===sequence){committing=false;pending=null;results.removeAttribute('aria-busy');}}
        }
        function cancel(){sequence++;pending?.abort();pending=null;results.removeAttribute('aria-busy');}
        form.addEventListener('submit',event=>{
            event.preventDefault();cancel();const errors=clientErrors();errorFields(errors);
            if(Object.keys(errors).length){status.textContent='Filters were not applied. Correct the highlighted fields.';mark();const key=Object.keys(errors)[0];(key==='account_id'?input:document.getElementById(key)).focus();return;}
            load(urlFor(draft(),'explicit',{...tableState(),page:1}),'apply',revision);
        });
        function reset(){
            cancel();const parts=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
            const part=k=>parts.find(p=>p.type===k).value;const today=part('year')+'-'+part('month')+'-'+part('day');
            revision++;setDraft({from:today.slice(0,7)+'-01',to:today,type:'',account_id:''});
            const url=urlFor(draft(),'default',{...tableState(),page:1,search:''});url.searchParams.set('reset','1');load(url,'reset',revision);
        }
        document.getElementById('records-reset').addEventListener('click',reset);document.querySelector('[data-records-reset]').addEventListener('click',reset);
        document.getElementById('records-clear-search').addEventListener('click',()=>table.search('').page(0).draw());
        window.addEventListener('popstate',()=>{revision++;load(new URL(location.href),'history',revision);});
        rebuild();errorFields(data.errors);mark();
        if(applied){committing=true;table.page(Math.max(0,Math.min(data.state.page-1,table.page.info().pages-1))).draw('page');committing=false;writeUrl();}
        return {draw};
    }
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
