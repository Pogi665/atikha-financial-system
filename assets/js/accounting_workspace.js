(() => {
    'use strict';
    const config=JSON.parse(document.getElementById('workspace-data').textContent),book=config.book;
    const $=id=>document.getElementById(id),root=$('entry-workspace');
    const nonce=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),x=>x.toString(16).padStart(2,'0')).join('');
    let lists=config.lists,draft=null,key=nonce(),token='',dirty=false,busy=false,documentDetails=[],pendingUpload=null;
    const freshLine=()=>({client_id:nonce(),account_id:'',fund_project_id:$('aw-default-project')?.value||'',debit_amount:'',credit_amount:''});
    let state={entry_date:$('aw-date').value,reference:'',description:'',party_id:'',default_project_id:'',cash_account_id:'',cash_amount:'',cash_project_id:'',transaction_kind:'ordinary',lines:[],documents:[]};
    state.lines=book==='GJ'?[freshLine(),freshLine()]:[freshLine()];
    const cents=v=>{if(!/^\d{1,13}(\.\d{1,2})?$/.test(v||''))return v===''?0n:null;const [w,f='']=v.split('.');return BigInt(w)*100n+BigInt(f.padEnd(2,'0'));};
    const money=c=>'₱'+(c/100n).toLocaleString('en-PH')+'.'+String(c%100n).padStart(2,'0');
    async function api(action,args={},method='POST'){
        const url=new URL('accounting_actions.php',location.href),options={credentials:'same-origin',cache:'no-store'};
        if(method==='GET'){url.search=new URLSearchParams({action,...args});}else{options.method='POST';options.headers={'Content-Type':'application/json'};options.body=JSON.stringify({action,csrf_token:config.csrf_token,...args});}
        const response=await fetch(url,options);let data;try{data=await response.json();}catch{throw new Error('The server response was interrupted. Retry the same action.');}
        if(!response.ok||!data.ok)throw new Error(data.error||'Request failed.');return data.result;
    }
    function message(v){$('aw-status').textContent=v;}
    function error(e){$('aw-error').textContent=e.message||String(e);$('aw-error').focus();}
    function mark(){dirty=true;token='';$('aw-review-panel').hidden=true;$('aw-draft-label').textContent=(draft?'Draft #'+draft.id:'Unsaved draft')+' · unsaved changes';}
    function select(options,value,empty){const s=document.createElement('select');s.add(new Option(empty,''));options.forEach(([id,label])=>s.add(new Option(label,String(id))));if(value&&!options.some(([id])=>String(id)===String(value))){const unavailable=new Option(value+' (inactive or unavailable)',value);unavailable.disabled=true;s.add(unavailable);}s.value=value||'';return s;}
    const recordSelectors=new Map();let openRecordSelector=null,selectorSerial=0;
    function enhanceRecordSelector(s){
        const label=s.closest('label'),labelText=label.firstChild.textContent.trim(),oldFor=label.htmlFor;
        const id=s.id||'aw-record-'+(++selectorSerial);s.id=id;
        const box=document.createElement('div');box.className='aw-record-selector';
        const search=document.createElement('input');search.type='text';search.id=id+'-search';search.autocomplete='off';search.dataset.recordSearch='true';
        search.setAttribute('role','combobox');search.setAttribute('aria-autocomplete','list');search.setAttribute('aria-haspopup','listbox');search.setAttribute('aria-expanded','false');
        const toggle=document.createElement('button');toggle.type='button';toggle.className='aw-record-toggle';toggle.textContent='\u25be';toggle.setAttribute('aria-label','Show '+labelText+' choices');toggle.tabIndex=-1;
        const list=document.createElement('ul');list.id=id+'-choices';list.className='aw-record-choices';list.setAttribute('role','listbox');list.setAttribute('aria-label',labelText+' choices');list.hidden=true;
        search.setAttribute('aria-controls',list.id);toggle.setAttribute('aria-controls',list.id);toggle.setAttribute('aria-expanded','false');
        const notice=document.createElement('span');notice.id=id+'-notice';notice.className='aw-sr-only';notice.setAttribute('role','status');search.setAttribute('aria-describedby',notice.id);
        let active=-1,choices=[];
        const blocked=()=>busy||s.disabled||draft?.state==='Posted';
        const selectedLabel=()=>s.selectedOptions[0]?.textContent||'';
        function close(){list.hidden=true;active=-1;search.value=selectedLabel();search.removeAttribute('aria-activedescendant');search.setAttribute('aria-expanded','false');toggle.setAttribute('aria-expanded','false');notice.textContent='';if(openRecordSelector===widget)openRecordSelector=null;}
        function sync(){search.disabled=toggle.disabled=blocked();search.value=selectedLabel();if(blocked())close();}
        function activate(index){active=index;Array.from(list.querySelectorAll('[role="option"]')).forEach((el,i)=>el.classList.toggle('is-active',i===active));const el=list.querySelectorAll('[role="option"]')[active];if(el){search.setAttribute('aria-activedescendant',el.id);el.scrollIntoView({block:'nearest'});}}
        function choose(value){if(blocked())return;const changed=s.value!==value;s.value=value;close();if(changed)s.dispatchEvent(new Event('change',{bubbles:true}));if(search.isConnected)search.focus();}
        function open(query=''){
            if(blocked())return;if(openRecordSelector&&openRecordSelector!==widget)openRecordSelector.close();openRecordSelector=widget;
            const terms=query.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
            choices=Array.from(s.options).filter(o=>!o.disabled&&(o.value===''||terms.every(t=>o.textContent.toLocaleLowerCase().includes(t))));list.replaceChildren();active=-1;search.removeAttribute('aria-activedescendant');
            choices.forEach((o,index)=>{const li=document.createElement('li');li.id=id+'-choice-'+index;li.dataset.value=o.value;li.setAttribute('role','option');li.setAttribute('aria-selected',String(s.value===o.value));li.textContent=o.textContent;li.addEventListener('mousedown',e=>e.preventDefault());li.addEventListener('click',()=>choose(o.value));list.append(li);});
            const matches=choices.filter(o=>o.value!=='').length;
            if(!matches){const hint=document.createElement('li');hint.className='aw-record-empty';hint.textContent=terms.length?'No matching records.':'No records available.';list.append(hint);}
            notice.textContent=matches?matches+' matching choices. Choose a record to change the selection.':(terms.length?'No matching records.':'No records available.');
            list.hidden=false;search.setAttribute('aria-expanded','true');toggle.setAttribute('aria-expanded','true');
        }
        search.addEventListener('focus',()=>{open();search.select();});
        search.addEventListener('input',e=>{e.stopPropagation();open(search.value);});search.addEventListener('change',e=>e.stopPropagation());
        search.addEventListener('keydown',e=>{
            if(e.key==='ArrowDown'||e.key==='ArrowUp'||((e.key==='Home'||e.key==='End')&&!list.hidden)){
                e.preventDefault();if(list.hidden)open();if(choices.length)activate(e.key==='Home'?0:e.key==='End'?choices.length-1:e.key==='ArrowDown'?Math.min(active+1,choices.length-1):active<0?choices.length-1:Math.max(0,active-1));
            }else if(e.key==='Enter'){e.preventDefault();if(!list.hidden&&active>=0)choose(choices[active].value);else if(list.hidden)open();}
            else if(e.key==='Escape'){e.preventDefault();close();}else if(e.key==='Tab')close();
        });
        toggle.addEventListener('click',()=>{if(blocked())return;const wasOpen=!list.hidden;search.focus();if(wasOpen)close();else open();});
        box.addEventListener('focusout',e=>{if(!box.contains(e.relatedTarget))close();});
        const widget={close,sync,destroy(){close();s.removeEventListener('change',sync);box.remove();s.hidden=false;label.htmlFor=oldFor;recordSelectors.delete(s);}};
        s.before(box);box.append(search,toggle,list,notice);label.htmlFor=search.id;s.hidden=true;recordSelectors.set(s,widget);s.addEventListener('change',sync);sync();return widget;
    }
    function destroyRecordSelectors(container){Array.from(recordSelectors.keys()).filter(s=>container.contains(s)).forEach(s=>recordSelectors.get(s).destroy());}
    function syncRecordSelectors(){recordSelectors.forEach(widget=>widget.sync());}
    document.addEventListener('mousedown',e=>{if(openRecordSelector&&!e.target.closest('.aw-record-selector'))openRecordSelector.close();});
    const accountOptions=cash=>{
        const rows=lists.accounts.filter(a=>cash===undefined||Boolean(Number(a.Is_Cash_Account))===cash).map(a=>[a.CategoryID,(a.Account_Code||'#'+a.CategoryID)+' · '+a.Name]);
        if(draft?.state==='Posted')(draft.posted_lines||[]).forEach(l=>{if(!rows.some(([id])=>String(id)===String(l.account_id)))rows.push([l.account_id,(l.account_code||'#'+l.account_id)+' · '+l.account_name]);});return rows;
    };
    const projectOptions=()=>{
        const rows=lists.projects.filter(p=>Number(p.is_active)).map(p=>[p.id,p.code+' · '+p.name]);
        if(draft?.state==='Posted')(draft.posted_lines||[]).forEach(l=>{if(l.fund_project_id!==null){const value=[l.fund_project_id,l.project_code_snapshot+' · '+l.project_name_snapshot],i=rows.findIndex(([id])=>String(id)===String(l.fund_project_id));if(i<0)rows.push(value);else rows[i]=value;}});return rows;
    };
    function replaceSelect(id,options,value,empty,searchable=false){const old=$(id),s=select(options,value,empty);recordSelectors.get(old)?.destroy();s.id=id;old.replaceWith(s);if(searchable)enhanceRecordSelector(s);return s;}
    function renderMasters(){
        const partyOptions=lists.parties.filter(p=>Number(p.is_active)).map(p=>[p.id,p.name+' · '+p.code]);
        if(draft?.state==='Posted'&&draft.posted_party){const p=draft.posted_party,i=partyOptions.findIndex(([id])=>String(id)===String(p.id)),value=[p.id,p.name+' · '+p.code];if(i<0)partyOptions.push(value);else partyOptions[i]=value;}
        replaceSelect('aw-party',partyOptions,state.party_id,book==='GJ'?'No party':'Select payer / payee',true);
        replaceSelect('aw-default-project',projectOptions(),state.default_project_id,'Organization operations',true);
        if(book!=='GJ'){replaceSelect('aw-cash-project',projectOptions(),state.cash_project_id,'Organization operations',true);replaceSelect('aw-cash-account',accountOptions(true),state.cash_account_id,'Select cash / bank account',true);}
    }
    function read(){
        Object.entries({'aw-date':'entry_date','aw-reference':'reference','aw-purpose':'description','aw-party':'party_id','aw-default-project':'default_project_id','aw-cash-account':'cash_account_id','aw-cash-amount':'cash_amount','aw-cash-project':'cash_project_id','aw-kind':'transaction_kind'}).forEach(([id,k])=>{if($(id))state[k]=$(id).value;});
        root.querySelectorAll('[data-line]').forEach(el=>{const l=state.lines.find(l=>l.client_id===el.dataset.line);el.querySelectorAll('[data-field]').forEach(f=>l[f.dataset.field]=f.value);});
        root.querySelectorAll('[data-document]').forEach(el=>{
            const d=state.documents.find(d=>d.receipt_id===el.dataset.document);
            el.querySelectorAll('[data-doc-field]').forEach(f=>d[f.dataset.docField]=f.type==='checkbox'?f.checked:f.value);
            d.allocations=Array.from(el.querySelectorAll('[data-allocation]')).filter(f=>f.value!=='').map(f=>({client_id:f.dataset.allocation,amount:f.value}));
            if(d.purpose==='supporting'){d.support_side='';d.declared_amount=d.accepted_amount='';d.allocations=[];}
        });return structuredClone(state);
    }
    function field(labelText,input){const label=document.createElement('label');label.append(labelText,input);return label;}
    function input(value,attr){const i=document.createElement('input');i.value=value||'';i.inputMode='decimal';Object.assign(i.dataset,attr);return i;}
    function renderLines(){
        destroyRecordSelectors($('aw-lines'));$('aw-lines').replaceChildren();const advanced=book==='GJ'||$('aw-advanced').checked;
        state.lines.forEach((l,index)=>{
            const box=document.createElement('div');box.className='aw-line';box.dataset.line=l.client_id;
            const top=document.createElement('div');top.className='aw-line-top';
            const a=select(accountOptions(book==='GJ'?undefined:false),l.account_id,'Select account');a.dataset.field='account_id';a.id='aw-line-'+l.client_id+'-account';
            const p=select(projectOptions(),l.fund_project_id,'Organization operations');p.dataset.field='fund_project_id';p.id='aw-line-'+l.client_id+'-project';
            const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.setAttribute('aria-label','Remove line '+(index+1));remove.disabled=state.lines.length<=(book==='GJ'?2:1);
            remove.addEventListener('click',()=>{read();state.lines=state.lines.filter(x=>x!==l);mark();renderLines();renderDocuments();totals();});
            top.append(field('Account '+(index+1),a),field('Project',p),remove);box.append(top);
            const amounts=document.createElement('div');amounts.className='aw-line-money';
            ['debit','credit'].forEach(side=>{const v=input(l[side+'_amount'],{field:side+'_amount'});const label=field(advanced?side[0].toUpperCase()+side.slice(1)+' (PHP)':'Amount (PHP)',v);
                if(!advanced&&side!==(book==='CRB'?'credit':'debit'))label.hidden=true;amounts.append(label);});
            box.append(amounts);$('aw-lines').append(box);enhanceRecordSelector(a);enhanceRecordSelector(p);
        });$('aw-add-line').disabled=state.lines.length>=(book==='GJ'?100:99);
    }
    function totals(){
        read();let debit=0n,credit=0n,valid=true;
        state.lines.forEach(l=>{const d=cents(l.debit_amount),c=cents(l.credit_amount);if(d===null||c===null){valid=false;return;}debit+=d;credit+=c;});
        if(book!=='GJ'){const c=cents(state.cash_amount);if(c===null)valid=false;else if(book==='CRB')debit+=c;else credit+=c;}
        $('aw-debits').textContent=valid?money(debit):'Invalid amount';$('aw-credits').textContent=valid?money(credit):'Invalid amount';
        const diff=debit-credit;$('aw-difference').textContent=valid?(diff<0n?'-':'')+money(diff<0n?-diff:diff):'Check amounts';
    }
    function renderDocuments(){
        $('aw-documents').replaceChildren();
        state.documents.forEach(d=>{
            const box=document.createElement('article');box.className='aw-document';box.dataset.document=d.receipt_id;
            const heading=document.createElement('h3');heading.textContent=documentDetails.find(x=>String(x.id)===d.receipt_id)?.name||'Image #'+d.receipt_id;box.append(heading);
            const link=document.createElement('a');link.href='receipt_attachment.php?receipt_id='+encodeURIComponent(d.receipt_id)+(draft?.state==='Posted'?'&journal_id='+draft.posted_journal_id:'');link.target='_blank';link.rel='noopener';link.textContent='Open original image';box.append(link);
            const image=document.createElement('img');image.src=link.href;image.alt='Supporting document '+d.receipt_id;box.append(image);
            const kind=select([['supporting','Informational supporting document'],['amount','Monetary evidence']],d.purpose,'Select purpose');kind.dataset.docField='purpose';box.append(field('Document purpose',kind));
            if(d.purpose==='amount'){
                const choices=book==='CRB'?[['credit','Credit allocations']]:book==='CDB'?[['debit','Debit allocations']]:[['debit','Debit lines'],['credit','Credit lines']];
                const side=select(choices,d.support_side,'Choose supported side');side.dataset.docField='support_side';box.append(field('Supports',side));
                const grid=document.createElement('div');grid.className='aw-grid';
                ['declared_amount','accepted_amount'].forEach(k=>grid.append(field(k==='declared_amount'?'Confirmed total (PHP)':'Accepted amount (PHP)',input(d[k],{docField:k}))));box.append(grid);
                const reason=document.createElement('textarea');reason.value=d.exclusion_reason;reason.maxLength=2000;reason.dataset.docField='exclusion_reason';box.append(field('Reason for excluded amount',reason));
                state.lines.filter(l=>{const a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id);return a&&!Number(a.Is_Cash_Account)&&cents(l[d.support_side+'_amount'])>0n;}).forEach(l=>{
                    const a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id),v=input(d.allocations.find(x=>x.client_id===l.client_id)?.amount||'',{allocation:l.client_id});
                    box.append(field('Allocate to '+a.Name+' · line '+(state.lines.indexOf(l)+1),v));
                });
            }
            const reviewed=document.createElement('input');reviewed.type='checkbox';reviewed.checked=d.reviewed;reviewed.dataset.docField='reviewed';const reviewLabel=field(' I reviewed the original image and confirmed these details',reviewed);reviewLabel.className='aw-inline';reviewLabel.prepend(reviewed);box.append(reviewLabel);
            const remove=document.createElement('button');remove.type='button';remove.textContent='Remove from draft';remove.addEventListener('click',()=>run(async()=>{await save();adopt(await api('remove',{...identity(),receipt_id:d.receipt_id}));render();message('Document released from this draft.');}));box.append(remove);
            kind.addEventListener('change',()=>{read();d.reviewed=false;if(kind.value==='amount')d.support_side=book==='CRB'?'credit':'debit';mark();renderDocuments();});
            box.append(document.createElement('br'));$('aw-documents').append(box);
        });
    }
    function adopt(d){draft=d;key=d.submission_key;state=structuredClone(d.payload);if(d.document_details)documentDetails=d.document_details;dirty=false;token='';$('aw-draft-label').textContent='Draft #'+d.id+' · saved';
        const url=new URL(location.href);url.searchParams.set('draft_id',d.id);url.searchParams.delete('receipt_id');history.replaceState(null,'',url);
    }
    function identity(){return {draft_id:String(draft.id),revision:String(draft.revision),submission_key:key};}
    async function save(){const payload=read();if(!dirty&&draft)return;
        adopt(await api('save',{draft_id:draft?String(draft.id):'',revision:draft?String(draft.revision):'',source_book:book,submission_key:key,payload}));message('Draft saved. No financial entry has been posted.');}
    function render(){renderMasters();$('aw-date').value=state.entry_date;$('aw-reference').value=state.reference;$('aw-purpose').value=state.description;
        if(book!=='GJ'){$('aw-cash-amount').value=state.cash_amount;if(state.lines.some(l=>cents(l[book==='CRB'?'debit_amount':'credit_amount'])>0n))$('aw-advanced').checked=true;}
        else $('aw-kind').value=state.transaction_kind;renderLines();renderDocuments();totals();}
    async function run(op){if(busy)return;busy=true;const disabled=[];root.querySelectorAll('button,input,select,textarea').forEach(el=>{if(!el.disabled){el.disabled=true;disabled.push(el);}});syncRecordSelectors();$('aw-error').textContent='';
        try{await op();}catch(e){error(e);}finally{busy=false;disabled.forEach(el=>{if(el.isConnected)el.disabled=false;});if(draft?.state==='Posted')root.querySelectorAll('#aw-entry input,#aw-entry select,#aw-entry textarea,#aw-entry button,.aw-columns aside input,.aw-columns aside select,.aw-columns aside textarea,.aw-columns aside button').forEach(el=>el.disabled=true);syncRecordSelectors();restoreMasterFocus();}
    }
    function table(lines){const t=document.createElement('table');t.className='aw-table';const h=document.createElement('tr');['Account','Project','Debit (PHP)','Credit (PHP)'].forEach(v=>{const th=document.createElement('th');th.textContent=v;h.append(th);});t.append(h);lines.forEach(l=>{const tr=document.createElement('tr');[l.account_name,l.project_name,l.debit_amount,l.credit_amount].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});t.append(tr);});return t;}
    root.addEventListener('input',e=>{if(!busy&&!e.target.hasAttribute('data-record-search')&&e.target.closest('#aw-entry,[data-document]')){mark();totals();}});
    root.addEventListener('change',e=>{if(!busy&&!e.target.hasAttribute('data-record-search')&&e.target.closest('#aw-entry,[data-document]')){
        if(e.target.dataset.docField&&e.target.dataset.docField!=='reviewed'){const box=e.target.closest('[data-document]');box.querySelector('[data-doc-field="reviewed"]').checked=false;}
        read();mark();if(e.target.dataset.docField==='support_side'){state.documents.find(d=>d.receipt_id===e.target.closest('[data-document]').dataset.document).allocations=[];renderDocuments();}
        totals();
    }});
    $('aw-add-line').addEventListener('click',()=>{read();state.lines.push(freshLine());mark();renderLines();totals();});
    $('aw-advanced')?.addEventListener('change',()=>{read();if(!$('aw-advanced').checked&&state.lines.some(l=>cents(l[book==='CRB'?'debit_amount':'credit_amount'])>0n)){$('aw-advanced').checked=true;error(new Error('Remove opposite-side amounts before returning to simple allocations.'));}renderLines();});
    $('aw-apply-project').addEventListener('click',()=>{read();state.lines.forEach(l=>l.fund_project_id=state.default_project_id);state.cash_project_id=state.default_project_id;mark();render();});
    $('aw-save').addEventListener('click',()=>run(save));
    $('aw-entry').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
        await save();const review=await api('review',identity());token=review.token;const panel=$('aw-review-content');panel.replaceChildren();
        const summary=document.createElement('p');summary.textContent=review.input.entry_date+' · '+(review.party?.name||'No party')+' · '+review.input.description;panel.append(summary);
        const cover=document.createElement('div');cover.className='aw-coverage';cover.textContent=review.coverage.status+' — '+(book==='GJ'?['debit','credit']:book==='CRB'?['credit']:['debit']).map(side=>side+': PHP '+review.coverage[side].covered+' supported of PHP '+review.coverage[side].eligible).join('; ');panel.append(cover);
        const wrap=document.createElement('div');wrap.className='aw-table-wrap';wrap.append(table(review.lines));panel.append(wrap);$('aw-review-panel').hidden=false;$('aw-review-panel').scrollIntoView({block:'start'});$('aw-post').focus();
    });});
    $('aw-back').addEventListener('click',()=>{token='';$('aw-review-panel').hidden=true;$('aw-purpose').focus();});
    function posted(id,duplicate=false){draft.state='Posted';draft.posted_journal_id=id;dirty=false;token='';renderDocuments();$('aw-review-panel').hidden=true;$('aw-posted').hidden=false;$('aw-draft-label').textContent='Posted journal #'+id;$('aw-posted-message').textContent='Journal #'+id+(duplicate?' was already posted. The existing result was recovered.':' posted successfully.');
        if(book!=='GJ')$('aw-posted-link').href='financial_records.php?view='+book.toLowerCase()+'&from=&to=';$('aw-posted').scrollIntoView();}
    $('aw-post').addEventListener('click',()=>run(async()=>{const result=await api('post',{...identity(),payload:read(),review_token:token});posted(result.id,result.duplicate);}));
    async function refreshDocuments(){const docs=await api('documents',{},'GET');replaceSelect('aw-existing',docs.map(d=>[d.id,d.name||'Image #'+d.id]),'','Select an unposted image');}
    $('aw-attach').addEventListener('click',()=>run(async()=>{const id=$('aw-existing').value;if(!id)throw new Error('Select an image first.');await save();adopt(await api('attach',{...identity(),receipt_id:id}));render();await refreshDocuments();message('Image attached. Review it before posting.');}));
    async function uploadImage(){
        const file=$('aw-upload').files[0];if(!file&&!pendingUpload)return;
        if(!pendingUpload){await save();pendingUpload={file,identity:identity(),key:nonce()};}
        const form=new FormData();Object.entries({action:'upload',csrf_token:config.csrf_token,...pendingUpload.identity,upload_key:pendingUpload.key}).forEach(([k,v])=>form.append(k,v));form.append('image',pendingUpload.file);
        const res=await fetch('accounting_actions.php',{method:'POST',credentials:'same-origin',body:form});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'Upload failed.');
        adopt(data.result);pendingUpload=null;$('aw-upload-retry').hidden=true;const loaded=await api('draft',{draft_id:String(draft.id)},'GET');documentDetails=loaded.document_details;render();$('aw-upload').value='';message('Image stored privately. Manual review is required.');
    }
    $('aw-upload').addEventListener('change',()=>run(async()=>{pendingUpload=null;try{await uploadImage();}catch(e){$('aw-upload-retry').hidden=false;throw e;}}));
    $('aw-upload-retry').addEventListener('click',()=>run(uploadImage));
    let masterKind='',masterTrigger=null,masterCreated=false,masterFocusPending=false;
    function restoreMasterFocus(){if(!masterFocusPending||busy)return;masterFocusPending=false;if(masterCreated)$(masterKind==='projects'?'aw-default-project-search':'aw-party-search').focus();else masterTrigger?.focus();}
    $('aw-master-dialog').addEventListener('close',()=>{masterFocusPending=true;restoreMasterFocus();});
    root.querySelectorAll('[data-new-master]').forEach(b=>b.addEventListener('click',()=>{read();masterKind=b.dataset.newMaster;masterTrigger=b;masterCreated=false;$('aw-master-form').reset();$('aw-master-title').textContent='New '+(masterKind==='projects'?'project':'party');$('aw-master-code-label').hidden=masterKind!=='projects';$('aw-master-type-label').hidden=masterKind!=='parties';$('aw-master-error').textContent='';$('aw-master-dialog').showModal();}));
    $('aw-master-cancel').addEventListener('click',()=>$('aw-master-dialog').close());
    $('aw-master-form').addEventListener('submit',e=>{e.preventDefault();const fields=Object.fromEntries(new FormData(e.target));run(async()=>{try{const record=await api('master',{...fields,kind:masterKind,is_active:'1'});lists=await api('lists',{},'GET');
        if(masterKind==='projects')state.default_project_id=String(record.id);else state.party_id=String(record.id);mark();renderMasters();masterCreated=true;$('aw-master-dialog').close();}catch(e){$('aw-master-error').textContent=e.message;}});});
    window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
    render();run(async()=>{
        if(config.draft_id){const d=await api('draft',{draft_id:config.draft_id},'GET');if(d.source_book!==book)throw new Error('This draft belongs to a different entry page. Open it from My drafts.');adopt(d);render();if(d.state==='Posted')posted(d.posted_journal_id,true);if(d.state==='Discarded')throw new Error('This draft was discarded.');}
        await refreshDocuments();
        if(config.receipt_id&&!config.draft_id){await save();adopt(await api('attach',{...identity(),receipt_id:config.receipt_id}));render();message('Image attached to a new private draft. Enter and review the journal manually.');}
    });
})();
