(() => {
    'use strict';
    const config=JSON.parse(document.getElementById('workspace-data').textContent),book=config.book;
    const isCorrection=Boolean(config.is_correction),reverseOnly=isCorrection&&config.mode==='reverse_only';
    let correctionPayload=isCorrection?structuredClone(config.initial_payload):null,correctionTarget=config.target;
    const payment=Boolean(config.ordinary_payment)&&book==='CDB'&&!isCorrection&&!config.workflow_kind;
    const advanceKind=config.workflow_kind||'',isAdvance=Boolean(advanceKind),isLiquidation=advanceKind==='advance_liquidation';
    const $=id=>document.getElementById(id),root=$('entry-workspace');
    const nonce=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),x=>x.toString(16).padStart(2,'0')).join('');
    let lists=config.lists,draft=null,key=nonce(),token='',dirty=false,busy=false,documentDetails=[],pendingUpload=null;
    let busyDisabled=[],paymentMode='',paymentStep='enter',paymentRequest=null,paymentErrorsShown=false,leaveDestination='',paymentFocus='',paymentErrorSignature='';
    function disableBusyControls(){if(!busy||!isCorrection)return;root.querySelectorAll('button,input,select,textarea').forEach(el=>{if(!el.disabled){el.disabled=true;busyDisabled.push(el);}});}
    const freshLine=()=>({client_id:nonce(),account_id:'',fund_project_id:$('aw-default-project')?.value||'',debit_amount:'',credit_amount:''});
    let state={entry_date:$('aw-date').value,reference:'',description:'',party_id:'',default_project_id:'',cash_account_id:'',cash_amount:'',cash_project_id:'',transaction_kind:'ordinary',lines:[],documents:[]};
    state.lines=isAdvance?(isLiquidation?[freshLine()]:[]):book==='GJ'?[freshLine(),freshLine()]:[freshLine()];
    if(isAdvance){Object.assign(state,{control_account_id:'',due_date:'',approval_name:'',approval_date:'',approval_reference:'',line_notes:{}});if(config.advance){state.party_id=String(config.advance.party_id);state.default_project_id=state.cash_project_id=config.advance.originating_project_id===null?'':String(config.advance.originating_project_id);state.lines.forEach(l=>l.fund_project_id=state.default_project_id);}}
    if(isCorrection){if(correctionPayload.replacement)state=structuredClone(correctionPayload.replacement);else{state.lines=[];state.entry_date=correctionPayload.entry_date;}root.classList.toggle('aw-reverse-only',reverseOnly);}
    const cents=v=>{if(!/^\d{1,13}(\.\d{1,2})?$/.test(v||''))return v===''?0n:null;const [w,f='']=v.split('.');return BigInt(w)*100n+BigInt(f.padEnd(2,'0'));};
    const money=c=>'₱'+(c/100n).toLocaleString('en-PH')+'.'+String(c%100n).padStart(2,'0');
    async function api(action,args={},method='POST'){
        const url=new URL(action==='master'?'accounting_actions.php':(config.endpoint||'accounting_actions.php'),location.href),options={credentials:'same-origin',cache:'no-store'};
        if(method==='GET'){url.search=new URLSearchParams({action,...args});}else{options.method='POST';options.headers={'Content-Type':'application/json'};options.body=JSON.stringify({action,csrf_token:config.csrf_token,...args});}
        const response=await fetch(url,options);let data;try{data=await response.json();}catch{throw Object.assign(new Error('The server response was interrupted. Retry the same action.'),{uncertain:true});}
        if(typeof data?.ok!=='boolean')throw Object.assign(new Error('The recording response could not be verified. Check the same request.'),{uncertain:true});
        if(!response.ok||!data.ok)throw Object.assign(new Error(data.error||'Request failed.'),{status:response.status,uncertain:response.status>=500||response.status<400});
        if(action==='post'&&(!Number.isInteger(data.result?.id)||data.result.id<=0))throw Object.assign(new Error('The recorded transaction identity could not be verified. Check the same request.'),{uncertain:true});return data.result;
    }
    function message(v){$('aw-status').textContent=v;}
    function error(e){if(payment&&paymentErrorsShown)paymentShowErrors();const p=document.createElement('p');p.textContent=e.message||String(e);$('aw-error').append(p);$('aw-error').focus();}
    function mark(){if(payment){message('');paymentView('enter');}if(isAdvance&&$('aw-proof-status'))$('aw-proof-status').textContent='Save and confirm the current return context before review.';dirty=true;token='';$('aw-review-panel').hidden=true;$('aw-draft-label').textContent=(draft?'Draft #'+draft.id:'Unsaved draft')+' · unsaved changes';}
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
        const full=payment?document.createElement('span'):null;if(full){full.className='aw-record-full-label';label.append(full);}
        let active=-1,choices=[];
        const blocked=()=>busy||s.disabled||draft?.state==='Posted'||(isCorrection&&!correctionTarget.eligible);
        const selectedLabel=()=>s.selectedOptions[0]?.textContent||'';
        function close(){list.hidden=true;active=-1;search.value=selectedLabel();search.removeAttribute('aria-activedescendant');search.setAttribute('aria-expanded','false');toggle.setAttribute('aria-expanded','false');notice.textContent='';if(openRecordSelector===widget)openRecordSelector=null;}
        function sync(){search.disabled=toggle.disabled=blocked();search.value=selectedLabel();if(full){full.textContent=selectedLabel();full.hidden=selectedLabel().length<55;}if(blocked())close();}
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
        const widget={close,sync,destroy(){close();s.removeEventListener('change',sync);box.remove();full?.remove();s.hidden=false;label.htmlFor=oldFor;recordSelectors.delete(s);}};
        s.before(box);box.append(search,toggle,list,notice);label.htmlFor=search.id;s.hidden=true;recordSelectors.set(s,widget);s.addEventListener('change',sync);sync();return widget;
    }
    function destroyRecordSelectors(container){Array.from(recordSelectors.keys()).filter(s=>container.contains(s)).forEach(s=>recordSelectors.get(s).destroy());}
    function syncRecordSelectors(){recordSelectors.forEach(widget=>widget.sync());}
    document.addEventListener('mousedown',e=>{if(openRecordSelector&&!e.target.closest('.aw-record-selector'))openRecordSelector.close();});
    const accountOptions=cash=>{
        const rows=lists.accounts.filter(a=>(!isLiquidation||(!Number(a.Is_Cash_Account)&&((['Expense','Asset'].includes(a.Account_Type)&&a.Normal_Balance==='Debit')||(a.Account_Type==='Liability'&&a.Normal_Balance==='Credit'))))&&(cash===undefined||Boolean(Number(a.Is_Cash_Account))===cash)).map(a=>[a.CategoryID,payment?a.Name+' · '+(a.Account_Code||'#'+a.CategoryID):(a.Account_Code||'#'+a.CategoryID)+' · '+a.Name]);
        if(draft?.state==='Posted')(draft.posted_lines||[]).forEach(l=>{if(!rows.some(([id])=>String(id)===String(l.account_id)))rows.push([l.account_id,(l.account_code||'#'+l.account_id)+' · '+l.account_name]);});return rows;
    };
    const projectOptions=()=>{
        const rows=lists.projects.filter(p=>Number(p.is_active)).map(p=>[p.id,p.code+' · '+p.name]);
        if(isCorrection&&!isAdvance){correctionTarget.lines.forEach(l=>{if(l.fund_project_id!==null&&!rows.some(([id])=>String(id)===String(l.fund_project_id)))rows.push([l.fund_project_id,l.project_code_snapshot+' · '+l.project_name_snapshot+' (original project)']);});}
        if(config.advance?.original_project){const p=config.advance.original_project;rows.push([p.id,p.code+' \u00b7 '+p.name+' (original advance project)']);}
        if(draft?.state==='Posted')(draft.posted_lines||[]).forEach(l=>{if(l.fund_project_id!==null){const value=[l.fund_project_id,l.project_code_snapshot+' · '+l.project_name_snapshot],i=rows.findIndex(([id])=>String(id)===String(l.fund_project_id));if(i<0)rows.push(value);else rows[i]=value;}});return rows;
    };
    function replaceSelect(id,options,value,empty,searchable=false){const old=$(id),s=select(options,value,empty);recordSelectors.get(old)?.destroy();s.id=id;old.replaceWith(s);if(searchable)enhanceRecordSelector(s);return s;}
    function renderMasters(){
        const partyOptions=lists.parties.filter(p=>Number(p.is_active)&&(!isAdvance||p.party_type==='person')).map(p=>[p.id,p.name+' · '+p.code]);
        if(isCorrection&&!isAdvance&&correctionTarget.journal.party_snapshot){const p=JSON.parse(correctionTarget.journal.party_snapshot);if(!partyOptions.some(([id])=>String(id)===String(p.id)))partyOptions.push([p.id,p.name+' (original party)']);}
        if(draft?.state==='Posted'&&draft.posted_party){const p=draft.posted_party,i=partyOptions.findIndex(([id])=>String(id)===String(p.id)),value=[p.id,p.name+' · '+p.code];if(i<0)partyOptions.push(value);else partyOptions[i]=value;}
        if(config.advance?.original_party){const p=config.advance.original_party;const i=partyOptions.findIndex(([id])=>String(id)===String(p.id)),value=[p.id,p.name+' \u00b7 '+p.code+' (original employee)'];if(i<0)partyOptions.push(value);else partyOptions[i]=value;}
        replaceSelect('aw-party',partyOptions,state.party_id,isAdvance?'Select accountable employee':book==='GJ'?'No party':payment?'Select payee':'Select payer / payee',true);
        replaceSelect('aw-default-project',projectOptions(),state.default_project_id,'Organization operations',true);
        if(book!=='GJ'){replaceSelect('aw-cash-project',projectOptions(),state.cash_project_id,'Organization operations',true);replaceSelect('aw-cash-account',accountOptions(true),state.cash_account_id,'Select cash / bank account',true);}
    }
    function read(){
        if(isCorrection){correctionPayload.reason=$('aw-correction-reason').value;correctionPayload.backdate_reason=$('aw-backdate-reason').value;}
        Object.entries({'aw-date':'entry_date','aw-reference':'reference','aw-purpose':'description','aw-party':'party_id','aw-default-project':'default_project_id','aw-cash-account':'cash_account_id','aw-cash-amount':'cash_amount','aw-cash-project':'cash_project_id','aw-kind':'transaction_kind','aw-control-account':'control_account_id','aw-due-date':'due_date','aw-approval-name':'approval_name','aw-approval-date':'approval_date','aw-approval-reference':'approval_reference'}).forEach(([id,k])=>{if($(id))state[k]=$(id).value;});
        root.querySelectorAll('[data-line]').forEach(el=>{const l=state.lines.find(l=>l.client_id===el.dataset.line);el.querySelectorAll('[data-field]').forEach(f=>l[f.dataset.field]=f.value);});
        root.querySelectorAll('[data-line-note]').forEach(el=>state.line_notes[el.dataset.lineNote]=el.value);
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
        if(payment&&$('aw-cash-project'))$('aw-project-home').after($('aw-cash-project').closest('label'));
        destroyRecordSelectors($('aw-lines'));$('aw-lines').replaceChildren();const advanced=payment?paymentMode==='advanced':book==='GJ'||$('aw-advanced').checked;
        state.lines.forEach((l,index)=>{
            const box=document.createElement('div');box.className='aw-line';box.dataset.line=l.client_id;
            const top=document.createElement('div');top.className='aw-line-top';
            const a=select(accountOptions(book==='GJ'?undefined:false),l.account_id,'Select account');a.dataset.field='account_id';a.id='aw-line-'+l.client_id+'-account';
            const p=select(projectOptions(),l.fund_project_id,'Organization operations');p.dataset.field='fund_project_id';p.id='aw-line-'+l.client_id+'-project';
            const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.setAttribute('aria-label','Remove line '+(index+1));remove.disabled=state.lines.length<=(book==='GJ'&&!isLiquidation?2:1);
            remove.addEventListener('click',()=>{read();if(payment&&state.documents.some(d=>d.allocations.some(a=>a.client_id===l.client_id))){error(new Error('Reallocate or remove this line’s document support before removing the line.'));return;}state.lines=state.lines.filter(x=>x!==l);mark();renderLines();renderDocuments();totals();});
            top.append(field('Account '+(index+1),a),field('Project',p),remove);box.append(top);
            const amounts=document.createElement('div');amounts.className='aw-line-money';
            ['debit','credit'].forEach(side=>{const v=input(l[side+'_amount'],{field:side+'_amount'});if(payment)v.id='aw-line-'+l.client_id+'-'+side;const label=field(advanced?side[0].toUpperCase()+side.slice(1)+' (PHP)':'Amount (PHP)',v);
                if(!advanced&&side!==(book==='CRB'?'credit':'debit'))label.hidden=true;amounts.append(label);});
            box.append(amounts);if(isLiquidation){const note=document.createElement('textarea');note.value=state.line_notes[l.client_id]||'';note.maxLength=2000;note.dataset.lineNote=l.client_id;box.append(field('Allocation note (required for liability credits)',note));}$('aw-lines').append(box);enhanceRecordSelector(a);enhanceRecordSelector(p);
        });if(payment)paymentLayout();$('aw-add-line').disabled=state.lines.length>=(book==='GJ'&&!isLiquidation?100:99);disableBusyControls();
    }
    function totals(){
        read();let debit=0n,credit=0n,valid=true;
        state.lines.forEach(l=>{const d=cents(l.debit_amount),c=cents(l.credit_amount);if(d===null||c===null){valid=false;return;}debit+=d;credit+=c;});
        if(book!=='GJ'){const c=cents(state.cash_amount);if(c===null)valid=false;else if(book==='CRB')debit+=c;else credit+=c;}
        if(isAdvance&&!isLiquidation){if(book==='CDB')debit=credit;else credit=debit;}else if(isLiquidation&&debit>credit){credit=debit;}
        $('aw-debits').textContent=valid?money(debit):'Invalid amount';$('aw-credits').textContent=valid?money(credit):'Invalid amount';
        const diff=debit-credit;$('aw-difference').textContent=valid?(diff<0n?'-':'')+money(diff<0n?-diff:diff):'Check amounts';
    }
    function updatePartialDocumentWarning(box){
        const warning=box.querySelector('[data-partial-warning]');if(!warning)return;
        const declared=cents(box.querySelector('[data-doc-field="declared_amount"]').value),accepted=cents(box.querySelector('[data-doc-field="accepted_amount"]').value);
        warning.hidden=declared===null||accepted===null||accepted>=declared;
        if(!warning.hidden)warning.textContent=money(declared-accepted)+' is excluded from this claim. After posting, this image cannot support that unused amount in another liquidation.';
    }
    function renderDocuments(){
        $('aw-documents').replaceChildren();
        state.documents.forEach(d=>{
            const box=document.createElement('article');box.className='aw-document';box.dataset.document=d.receipt_id;
            const heading=document.createElement('h3');heading.textContent=documentDetails.find(x=>String(x.id)===d.receipt_id)?.name||'Image #'+d.receipt_id;box.append(heading);
            const link=document.createElement('a');link.href=documentDetails.find(x=>String(x.id)===d.receipt_id)?.url||'receipt_attachment.php?receipt_id='+encodeURIComponent(d.receipt_id)+(draft?.state==='Posted'?'&journal_id='+draft.posted_journal_id:'');link.target='_blank';link.rel='noopener';link.textContent='Open original image';box.append(link);
            const image=document.createElement('img');image.src=link.href;image.alt='Supporting document '+d.receipt_id;box.append(image);
            const detail=documentDetails.find(x=>String(x.id)===d.receipt_id);
            if(isCorrection&&detail?.reused){const prior=document.createElement('p');prior.className='aw-help';prior.textContent='Prior review: '+detail.prior_review.purpose+'; declared PHP '+(detail.prior_review.declared_amount||'—')+'; accepted PHP '+(detail.prior_review.accepted_amount||'—')+'. Fresh review required.';box.append(prior);const reason=document.createElement('textarea');reason.maxLength=2000;reason.value=d.re_review_reason||'';reason.dataset.docField='re_review_reason';box.append(field('Reason for changed purpose or amounts',reason));}
            const kind=select(isAdvance&&!isLiquidation?[['supporting','Informational supporting document']]:[['supporting','Informational supporting document'],['amount','Monetary evidence']],d.purpose,'Select purpose');kind.dataset.docField='purpose';box.append(field('Document purpose',kind));
            if(d.purpose==='amount'){
                const choices=isLiquidation?[['debit','Expenditure / asset debits']]:book==='CRB'?[['credit','Credit allocations']]:book==='CDB'?[['debit','Debit allocations']]:[['debit','Debit lines'],['credit','Credit lines']];
                const side=select(choices,d.support_side,'Choose supported side');side.dataset.docField='support_side';box.append(field('Supports',side));
                const grid=document.createElement('div');grid.className='aw-grid';
                ['declared_amount','accepted_amount'].forEach(k=>grid.append(field(k==='declared_amount'?'Confirmed total (PHP)':'Accepted amount (PHP)',input(d[k],{docField:k}))));box.append(grid);
                const reason=document.createElement('textarea');reason.value=d.exclusion_reason;reason.maxLength=2000;reason.dataset.docField='exclusion_reason';box.append(field('Reason for excluded amount',reason));
                if(isLiquidation){const warning=document.createElement('p');warning.className='aw-document-warning';warning.dataset.partialWarning='';warning.setAttribute('role','status');box.append(warning);updatePartialDocumentWarning(box);}
                state.lines.filter(l=>{const a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id);return a&&!Number(a.Is_Cash_Account)&&cents(l[d.support_side+'_amount'])>0n;}).forEach(l=>{
                    const a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id),v=input(d.allocations.find(x=>x.client_id===l.client_id)?.amount||'',{allocation:l.client_id});
                    box.append(field('Allocate to '+a.Name+' · line '+(state.lines.indexOf(l)+1),v));
                });
            }
            const reviewed=document.createElement('input');reviewed.type='checkbox';reviewed.checked=d.reviewed;reviewed.dataset.docField='reviewed';const reviewLabel=field(' I reviewed the original image and confirmed these details',reviewed);reviewLabel.className='aw-inline';reviewLabel.prepend(reviewed);box.append(reviewLabel);
            const remove=document.createElement('button');remove.type='button';remove.textContent='Remove from draft';remove.addEventListener('click',()=>run(async()=>{if(!isCorrection||correctionTarget.eligible)await save();adopt(await api('remove',{...identity(),receipt_id:d.receipt_id}));render();message('Document released from this draft.');}));box.append(remove);
            kind.addEventListener('change',()=>{read();d.reviewed=false;if(kind.value==='amount')d.support_side=book==='CRB'?'credit':'debit';mark();renderDocuments();});
            box.append(document.createElement('br'));$('aw-documents').append(box);
        });disableBusyControls();
    }
    function adopt(d){draft=d;key=d.submission_key;if(isCorrection){correctionPayload=structuredClone(d.payload);state=structuredClone(d.payload.replacement||{...state,entry_date:d.payload.entry_date,lines:[],documents:[]});if(d.target)correctionTarget=d.target;}else state=structuredClone(d.payload);if(d.document_details)documentDetails=d.document_details;dirty=false;token='';if($('aw-proof-status'))$('aw-proof-status').textContent=d.return_confirmation?'Proof confirmed for the saved amount and documents.':'Proof has not been confirmed for this saved context.';$('aw-draft-label').textContent='Draft #'+d.id+' · saved';
        const url=new URL(location.href);url.searchParams.set('draft_id',d.id);url.searchParams.delete('receipt_id');history.replaceState(null,'',url);
    }
    function identity(){return {draft_id:String(draft.id),revision:String(draft.revision),submission_key:key};}
    function payloadForSave(){const replacement=read();if(!isCorrection)return replacement;return {entry_date:replacement.entry_date,reason:$('aw-correction-reason').value,backdate_reason:$('aw-backdate-reason').value,replacement:reverseOnly?null:replacement};}
    async function save(){const payload=payloadForSave();if(!dirty&&draft){if(payment)message('Draft saved. No changes to save. This payment has not been recorded.');return;}
        adopt(await api('save',{draft_id:draft?String(draft.id):'',revision:draft?String(draft.revision):'',source_book:book,workflow_kind:advanceKind,advance_id:config.advance_id||'',submission_key:key,target_journal_id:String(config.target_journal_id),mode:config.mode,payload}));message(payment?'Draft saved. This payment has not been recorded.':'Draft saved. No financial entry has been posted.');if(payment&&paymentErrorsShown)paymentShowErrors();}
    function render(){if(isCorrection){$('aw-correction-reason').value=correctionPayload.reason;$('aw-backdate-reason').value=correctionPayload.backdate_reason;showCorrectionContext();}renderMasters();$('aw-date').value=state.entry_date;$('aw-reference').value=state.reference;$('aw-purpose').value=state.description;
        if(book!=='GJ'){$('aw-cash-amount').value=state.cash_amount;if(state.lines.some(l=>cents(l[book==='CRB'?'debit_amount':'credit_amount'])>0n))$('aw-advanced').checked=true;}
        else $('aw-kind').value=state.transaction_kind;
        if(isAdvance){['due-date','approval-name','approval-date','approval-reference'].forEach(id=>{if($('aw-'+id))$('aw-'+id).value=state[id.replaceAll('-','_')]||'';});
            if($('aw-control-account'))replaceSelect('aw-control-account',lists.all_accounts.filter(a=>lists.controls.includes(Number(a.CategoryID))).map(a=>[a.CategoryID,(a.Account_Code||'#'+a.CategoryID)+' \u00b7 '+a.Name]),state.control_account_id,'Select advance control account',true);
        }
        if(payment)paymentMode=PaymentPresentation.mode(state);renderLines();renderDocuments();totals();lockAdvanceContext();if(payment&&paymentErrorsShown)paymentShowErrors();}
    function lockAdvanceContext(){if(payment&&draft?.state==='Posted')paymentView('recorded');if(!isAdvance)return;if(!isLiquidation){$('aw-lines').hidden=true;$('aw-advanced')?.closest('label')?.setAttribute('hidden','');}if(config.advance){['aw-party','aw-default-project','aw-cash-project'].forEach(id=>{if($(id))$(id).disabled=true;});root.querySelectorAll('[data-new-master]').forEach(b=>b.hidden=true);$('aw-apply-project').hidden=true;}if(!isLiquidation&&$('aw-cash-project'))$('aw-cash-project').closest('label').hidden=true;syncRecordSelectors();}

    async function run(op){if(busy)return;busy=true;busyDisabled=[];const disabled=busyDisabled;root.querySelectorAll('button,input,select,textarea').forEach(el=>{if(!el.disabled){el.disabled=true;disabled.push(el);}});syncRecordSelectors();$('aw-error').textContent='';
        try{await op();}catch(e){error(e);}finally{busy=false;disabled.forEach(el=>{if(el.isConnected)el.disabled=false;});if(draft?.state==='Posted')root.querySelectorAll('#aw-entry input,#aw-entry select,#aw-entry textarea,#aw-entry button,.aw-columns aside input,.aw-columns aside select,.aw-columns aside textarea,.aw-columns aside button').forEach(el=>el.disabled=true);lockAdvanceContext();if(isCorrection){$('aw-post').disabled=!token||draft?.state==='Posted';if(draft?.state==='Posted'&&$('aw-replacement-book'))$('aw-replacement-book').disabled=true;if(draft)$('aw-correction-mode').disabled=true;if(!correctionTarget.eligible||draft?.state==='Discarded'){root.querySelectorAll('#aw-entry button,#aw-entry input,#aw-entry select,#aw-entry textarea,#aw-upload,#aw-attach,#aw-attach-reuse,#aw-confirm-proof,#aw-replacement-book').forEach(el=>el.disabled=true);}}syncRecordSelectors();restoreMasterFocus();if(payment){if(paymentRequest&&paymentStep==='unknown'){root.querySelectorAll('button,input,select,textarea').forEach(el=>el.disabled=true);$('aw-check-result').disabled=false;}if(paymentFocus){$(paymentFocus)?.focus();paymentFocus='';}}}
    }
    function table(lines){const t=document.createElement('table');t.className='aw-table';const h=document.createElement('tr');['Account','Project','Debit (PHP)','Credit (PHP)'].forEach(v=>{const th=document.createElement('th');th.textContent=v;h.append(th);});t.append(h);lines.forEach(l=>{const tr=document.createElement('tr');[l.account_name,l.project_name,l.debit_amount,l.credit_amount].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});t.append(tr);});return t;}
    root.addEventListener('input',e=>{if(!busy&&!e.target.hasAttribute('data-record-search')&&e.target.closest('#aw-entry,[data-document]')){mark();totals();if(payment&&paymentErrorsShown)paymentShowErrors();const box=e.target.closest('[data-document]');if(box)updatePartialDocumentWarning(box);}});
    root.addEventListener('change',e=>{if(!busy&&!e.target.hasAttribute('data-record-search')&&e.target.closest('#aw-entry,[data-document]')){
        if(e.target.dataset.docField&&e.target.dataset.docField!=='reviewed'){const box=e.target.closest('[data-document]');box.querySelector('[data-doc-field="reviewed"]').checked=false;}
        read();mark();if(e.target.dataset.docField==='support_side'){state.documents.find(d=>d.receipt_id===e.target.closest('[data-document]').dataset.document).allocations=[];renderDocuments();}
        totals();if(payment&&paymentErrorsShown)paymentShowErrors();const box=e.target.closest('[data-document]');if(box)updatePartialDocumentWarning(box);
    }});
    $('aw-add-line').addEventListener('click',()=>{read();state.lines.push(freshLine());if(payment)paymentMode='split';mark();renderLines();totals();});
    $('aw-advanced')?.addEventListener('change',()=>{read();if(!$('aw-advanced').checked&&state.lines.some(l=>cents(l[book==='CRB'?'debit_amount':'credit_amount'])>0n)){$('aw-advanced').checked=true;error(new Error('Remove opposite-side amounts before returning to simple allocations.'));}renderLines();});
    $('aw-apply-project').addEventListener('click',()=>{read();state.lines.forEach(l=>l.fund_project_id=state.default_project_id);state.cash_project_id=state.default_project_id;mark();render();});
    $('aw-save').addEventListener('click',()=>run(save));
    $('aw-entry').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
        await save();if(payment){paymentErrorsShown=true;if(paymentShowErrors()){paymentFocus='aw-error';return;}}const review=await api('review',identity());token=review.token;const panel=$('aw-review-content');panel.replaceChildren();
        if(isCorrection){const heading=document.createElement('h3');heading.textContent='Generated exact reversal · General Journal';panel.append(heading,table(review.reversal.lines));const note=document.createElement('p');note.textContent=review.notice;const effect=document.createElement('p');effect.textContent=review.net_explanation;panel.append(note,effect);if(review.advance_effect){const t=review.advance_effect,p=document.createElement('p');p.textContent='Advance #'+review.original_advance_id+' \u00b7 Before PHP '+t.before_display+' \u00b7 After PHP '+t.after_display+' \u00b7 Accounting date '+t.accounting_date+' \u00b7 Earliest affected date '+t.earliest_affected_date+(t.replacement_outstanding===null?'':' \u00b7 New replacement advance: PHP '+t.replacement_display+' outstanding; a new number is assigned at posting.');panel.append(p);}if(!reverseOnly){const h=document.createElement('h3');h.textContent='Replacement · '+book;panel.append(h);}}
        const summary=document.createElement('p');summary.textContent=review.input.entry_date+' · '+(review.party?.name||'No party')+' · '+review.input.description;panel.append(summary);
        const cover=document.createElement('div');cover.className='aw-coverage';cover.textContent=review.coverage.status+' — '+(isLiquidation?['debit']:book==='GJ'?['debit','credit']:book==='CRB'?['credit']:['debit']).map(side=>side+': PHP '+review.coverage[side].covered+' supported of PHP '+review.coverage[side].eligible).join('; ');panel.append(cover);
        if(payment)paymentSummary(review,panel);const wrap=document.createElement('div');wrap.className='aw-table-wrap';wrap.append(table(review.lines));if(payment){const details=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Complete debit and credit journal';details.append(summary,wrap);panel.append(details);paymentView('review');paymentFocus='aw-post';}else panel.append(wrap);$('aw-review-panel').hidden=false;$('aw-review-panel').scrollIntoView({block:'start'});if(isCorrection){$('aw-post').disabled=!review.posting_available;$('aw-post').textContent=review.posting_available?(reverseOnly?'Post reversal':'Post correction'):'Posting unavailable';if(review.posting_available)$('aw-post').focus();else $('aw-back').focus();}else $('aw-post').focus();
    });});
    $('aw-back').addEventListener('click',()=>{if(payment)paymentView('enter');token='';$('aw-review-panel').hidden=true;$('aw-purpose').focus();});
    function posted(id,duplicate=false,advanceId=config.advance_id){if(payment){paymentRequest=null;paymentView('recorded');message(duplicate?'Recorded result recovered. No duplicate payment was created.':'Payment recorded.');$('aw-exact-link').href='journal_transaction.php?journal_id='+encodeURIComponent(id);paymentFocus='aw-exact-link';}if(isAdvance){if($('aw-print-label'))$('aw-print-label').textContent='POSTED journal #'+id;if($('aw-advance-link'))$('aw-advance-link').href='cash_advances.php?advance_id='+advanceId;}draft.state='Posted';draft.posted_journal_id=id;dirty=false;token='';renderDocuments();$('aw-review-panel').hidden=true;$('aw-posted').hidden=false;$('aw-draft-label').textContent='Posted journal #'+id;$('aw-posted-message').textContent='Journal #'+id+(duplicate?' was already posted. The existing result was recovered.':' posted successfully.');
        if(book!=='GJ')$('aw-posted-link').href='financial_records.php?view='+book.toLowerCase()+'&from=&to=';$('aw-posted').scrollIntoView();}
    $('aw-post').addEventListener('click',()=>run(async()=>{if(payment){paymentRequest={...identity(),payload:payloadForSave(),review_token:token};await paymentRecord();return;}const result=await api('post',{...identity(),payload:payloadForSave(),review_token:token});posted(result.id,result.duplicate,result.advance_id);if(isCorrection)await showPostedCorrection();}));
    async function showPostedCorrection(loaded=null){
        const d=loaded||await api('draft',{draft_id:String(draft.id)},'GET');
        correctionTarget=d.target;message('Correction posted. The original journal remains unchanged.');
        const eligibility=$('aw-correction-eligibility');eligibility.textContent='Corrected on '+d.payload.entry_date+'. The original remains posted and immutable.';
        const detailLink=document.createElement('a');detailLink.href='journal_corrections.php?journal_id='+config.target_journal_id;detailLink.textContent='View posted correction';eligibility.append(' ',detailLink);
        $('aw-posted').querySelector('h2').textContent='Correction posted';root.querySelector('.aw-columns').hidden=true;
        if($('aw-reuse-card'))$('aw-reuse-card').hidden=true;
        const panel=$('aw-posted');panel.querySelectorAll('[data-correction-result]').forEach(el=>el.remove());
        const section=document.createElement('section');section.dataset.correctionResult='1';
        const a=document.createElement('a');a.href='journal_corrections.php?journal_id='+config.target_journal_id;a.textContent='View posted correction / print comparison';section.append(a);
        if(isAdvance){$('aw-advance-navigation').hidden=true;const ids=d.posted_result||{};for(const [key,label] of [['original_advance_id','View original advance'],['replacement_advance_id','View replacement advance']])if(ids[key]){const link=document.createElement('a');link.href='cash_advances.php?advance_id='+ids[key];link.textContent=label;link.dataset.advanceResult=key;section.append(document.createTextNode(' \u00b7 '),link);}}
        Object.entries(d.posted_bundle||{}).forEach(([kind,value])=>{const h=document.createElement('h3');h.textContent=kind+' journal #'+value.journal_id;section.append(h,table(value.lines.map(l=>({...l,project_name:l.project_name_snapshot||'Organization operations'}))));});
        panel.append(section);
        if($('aw-print-label'))$('aw-print-label').textContent='POSTED correction - accounting date '+d.payload.entry_date;
    }
    async function refreshDocuments(){if(isCorrection&&(!correctionTarget.eligible||reverseOnly))return;if(isCorrection&&draft&&$('aw-reuse')){const candidates=await api('reuse_candidates',{draft_id:String(draft.id)},'GET');replaceSelect('aw-reuse',candidates.map(d=>[d.id,d.name]),'','Select target image');}const docs=await api('documents',{},'GET');replaceSelect('aw-existing',docs.map(d=>[d.id,d.name||'Image #'+d.id]),'','Select an unposted image');}
    $('aw-attach-reuse')?.addEventListener('click',()=>run(async()=>{const id=$('aw-reuse').value;if(!id)throw new Error('Select a target image.');await save();adopt(await api('attach_reuse',{...identity(),receipt_id:id}));adopt(await api('draft',{draft_id:String(draft.id)},'GET'));render();await refreshDocuments();message('Original image reserved. Review it again for this replacement.');}));
    $('aw-attach').addEventListener('click',()=>run(async()=>{const id=$('aw-existing').value;if(!id)throw new Error('Select an image first.');await save();adopt(await api('attach',{...identity(),receipt_id:id}));render();await refreshDocuments();message('Image attached. Review it before posting.');}));
    async function uploadImage(){
        const file=$('aw-upload').files[0];if(!file&&!pendingUpload)return;
        if(!pendingUpload){await save();pendingUpload={file,identity:identity(),key:nonce()};}
        const form=new FormData();Object.entries({action:'upload',csrf_token:config.csrf_token,...pendingUpload.identity,upload_key:pendingUpload.key}).forEach(([k,v])=>form.append(k,v));form.append('image',pendingUpload.file);
        const res=await fetch(config.endpoint||'accounting_actions.php',{method:'POST',credentials:'same-origin',body:form});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'Upload failed.');
        adopt(data.result);pendingUpload=null;$('aw-upload-retry').hidden=true;const loaded=await api('draft',{draft_id:String(draft.id)},'GET');documentDetails=loaded.document_details;render();$('aw-upload').value='';message('Image stored privately. Manual review is required.');
    }
    $('aw-upload').addEventListener('change',()=>run(async()=>{pendingUpload=null;try{await uploadImage();}catch(e){$('aw-upload-retry').hidden=false;throw e;}}));
    $('aw-upload-retry').addEventListener('click',()=>run(uploadImage));
    $('aw-confirm-proof')?.addEventListener('click',()=>run(async()=>{await save();adopt(await api('confirm_return_proof',identity()));render();message('Proof confirmed for this advance and return amount. Review the entry next.');}));
    function showAdvanceContext(){if(!isAdvance)return;const panel=$('aw-advance-context');panel.replaceChildren();const p=document.createElement('p');p.textContent=config.advance?config.advance.number+' \u00b7 '+config.advance.employee+' \u00b7 Outstanding PHP '+config.advance.outstanding+' \u00b7 Due '+config.advance.due_date:'Release creates an employee advance, not an expense. Enter the required due date.';panel.append(p);}
    async function employeeAdvances(){if(isCorrection)return;if(advanceKind!=='advance_release'||!state.party_id)return;const selected=state.party_id;try{const register=await api('register',{party_id:selected},'GET');if(state.party_id!==selected)return;showAdvanceContext();const p=document.createElement('p');p.textContent='Existing advances: '+register.count+' \u00b7 Outstanding PHP '+register.totals.outstanding+' (additional releases are permitted).';$('aw-advance-context').append(p);}catch(e){message('Could not load existing employee advances. '+e.message);}}
    $('aw-party').closest('label').addEventListener('change',()=>{if(advanceKind==='advance_release'){read();employeeAdvances();}});
    let masterKind='',masterTrigger=null,masterCreated=false,masterFocusPending=false;
    function restoreMasterFocus(){if(!masterFocusPending||busy)return;masterFocusPending=false;if(masterCreated)$(masterKind==='projects'?(payment&&paymentMode==='quick'?'aw-cash-project-search':'aw-default-project-search'):'aw-party-search').focus();else masterTrigger?.focus();}
    $('aw-master-dialog').addEventListener('close',()=>{masterFocusPending=true;restoreMasterFocus();});
    root.querySelectorAll('[data-new-master]').forEach(b=>b.addEventListener('click',()=>{read();masterKind=b.dataset.newMaster;masterTrigger=b;masterCreated=false;$('aw-master-form').reset();$('aw-master-title').textContent='New '+(masterKind==='projects'?'project':'party');$('aw-master-code-label').hidden=masterKind!=='projects';$('aw-master-type-label').hidden=masterKind!=='parties';$('aw-master-error').textContent='';$('aw-master-dialog').showModal();}));
    $('aw-master-cancel').addEventListener('click',()=>$('aw-master-dialog').close());
    $('aw-master-form').addEventListener('submit',e=>{e.preventDefault();const fields=Object.fromEntries(new FormData(e.target));run(async()=>{try{const record=await api('master',{...fields,kind:masterKind,is_active:'1'});lists=await api('lists',{},'GET');
        if(masterKind==='projects'){state.default_project_id=String(record.id);if(payment&&paymentMode==='quick'){state.cash_project_id=String(record.id);state.lines[0].fund_project_id=String(record.id);}}else state.party_id=String(record.id);mark();renderMasters();if(payment)renderLines();masterCreated=true;$('aw-master-dialog').close();}catch(e){$('aw-master-error').textContent=e.message;}});});
    window.addEventListener('beforeunload',e=>{if(dirty||(payment&&paymentStep==='unknown')){e.preventDefault();e.returnValue='';}});
    function showCorrectionContext(){
        const box=$('aw-original');box.replaceChildren();const summary=document.createElement('p');summary.textContent=correctionTarget.journal.entry_date+' · '+(correctionTarget.journal.source_book||'Legacy General Journal')+' · '+correctionTarget.journal.description;box.append(summary,table(correctionTarget.lines.map(l=>({...l,project_name:l.project_name_snapshot||'Organization operations'}))));
        const status=$('aw-correction-eligibility');status.textContent=correctionTarget.ineligible_reason||'Eligible target. The original remains immutable.';
        if(correctionTarget.correction){const a=document.createElement('a');a.href='journal_corrections.php?journal_id='+config.target_journal_id;a.textContent='View posted correction';status.append(' ',a);}
        correctionTarget.blockers.forEach(b=>{const p=document.createElement('p');p.textContent='Blocking '+b.operation_kind+' journal #'+b.journal_id;status.append(p);});
        $('aw-date').min=correctionTarget.journal.entry_date;$('aw-date').max=config.today;
    }
    $('aw-correction-mode')?.addEventListener('change',()=>{
        if(dirty&&!confirm('Start another correction mode? Unsaved values will be replaced.')){$('aw-correction-mode').value=config.mode;return;}
        dirty=false;location.href='journal_correction.php?journal_id='+config.target_journal_id+'&mode='+$('aw-correction-mode').value;
    });
    $('aw-replacement-book')?.addEventListener('change',()=>run(async()=>{
        const next=$('aw-replacement-book').value,p=payloadForSave(),r=p.replacement;
        if(book!=='GJ'&&(r.cash_account_id||r.cash_amount)){r.lines.unshift({client_id:'cash_'+nonce().slice(0,40),account_id:r.cash_account_id,fund_project_id:r.cash_project_id,debit_amount:book==='CRB'?r.cash_amount:'',credit_amount:book==='CDB'?r.cash_amount:''});r.cash_account_id=r.cash_amount=r.cash_project_id='';}
        if(next!=='GJ'){const side=next==='CRB'?'debit_amount':'credit_amount',index=r.lines.findIndex(l=>lists.accounts.some(a=>String(a.CategoryID)===String(l.account_id)&&Number(a.Is_Cash_Account))&&cents(l[side])>0n);if(index>=0){const [cash]=r.lines.splice(index,1);r.cash_account_id=cash.account_id;r.cash_project_id=cash.fund_project_id;r.cash_amount=cash[side];}}
        const d=await api('save',{draft_id:draft?String(draft.id):'',revision:draft?String(draft.revision):'',submission_key:key,target_journal_id:String(config.target_journal_id),mode:config.mode,source_book:next,payload:p});
        message('Replacement book saved. Opening '+next+' workspace.');
        dirty=false;location.href='journal_correction.php?draft_id='+d.id;
    }));
    function paymentView(step){
        if(!payment)return;paymentStep=step;
        root.querySelector('.aw-columns').hidden=step!=='enter';
        $('aw-review-panel').hidden=step!=='review';$('aw-posted').hidden=step!=='recorded';$('aw-unknown').hidden=step!=='unknown';
        root.querySelector('h1').textContent={enter:'Record a payment',review:'Review payment',recorded:'Payment recorded',unknown:'Check recording result'}[step];
        $('aw-mode-notice').hidden=step!=='enter';
        Array.from($('aw-progress').children).forEach((el,i)=>{if(i==={enter:0,review:1,recorded:2,unknown:1}[step])el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});
        if(step==='review')message('Review complete. This payment has not been recorded.');
        if(step==='recorded')root.querySelectorAll('.aw-columns input,.aw-columns select,.aw-columns textarea,.aw-columns button').forEach(el=>el.disabled=true);
    }
    function paymentLayout(){
        if(!payment)return;const quick=paymentMode==='quick';root.dataset.paymentMode=paymentMode;
        $('aw-purpose').rows=quick?1:2;
        $('aw-advanced').checked=paymentMode==='advanced';$('aw-advanced').closest('label').hidden=true;
        root.querySelectorAll('[data-payment-mode]').forEach(b=>{b.setAttribute('aria-pressed',String(b.dataset.paymentMode===paymentMode));});
        $('aw-add-line').hidden=quick;root.querySelector('#aw-entry .aw-section-heading').hidden=quick;
        root.querySelector('#aw-entry > .aw-help').hidden=quick;root.querySelector('.aw-totals').hidden=quick;
        $('aw-mode-notice').textContent=quick?'One amount and one project for this payment.':'Detailed entry: amounts and project allocations are preserved independently.';
        root.querySelectorAll('[data-line]').forEach(el=>{
            el.querySelector('.aw-line-money').hidden=quick;el.querySelector('[data-field="fund_project_id"]').closest('label').hidden=quick;
            el.querySelector('button[aria-label^="Remove line"]').hidden=quick;
            const label=el.querySelector('[data-field="account_id"]').closest('label');label.firstChild.textContent=quick?'Record against account':'Account';
            const search=el.querySelector('[data-field="account_id"]').id+'-search';$(search).setAttribute('aria-describedby',$(search).getAttribute('aria-describedby')+' aw-account-help');
        });
        // Move the existing authoritative cash-project control; never replace its ID or saved value.
        const project=$('aw-cash-project').closest('label');
        project.firstChild.textContent=quick?'Project':'Cash line project';
        const addProject=root.querySelector('[data-new-master="projects"]');
        if(quick)project.append(addProject);else $('aw-default-project').closest('label').append(addProject);
        if(quick&&$('aw-lines').firstElementChild)$('aw-lines').firstElementChild.querySelector('.aw-line-top').append(project);
        else $('aw-project-home').after(project);
    }
    function paymentValidate(){
        const p=read(),errors={},add=(id,text)=>errors[id]=text;
        const visible=id=>$(id+'-search')?id+'-search':id;
        const eligible=(id,value)=>!value||!$(id)?.selectedOptions[0]||$(id).selectedOptions[0].disabled;
        const date=p.entry_date,parsed=new Date(date+'T00:00:00Z');
        if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||date<'1000-01-01'||!Number.isFinite(parsed.valueOf())||parsed.toISOString().slice(0,10)!==date||date>$('aw-date').max)add('aw-date','Choose a real accounting date on or before today.');
        if(eligible('aw-party',p.party_id))add(visible('aw-party'),'Choose an available payee.');
        if(!p.description.trim())add('aw-purpose','Explain the purpose of this payment.');
        if(eligible('aw-cash-account',p.cash_account_id))add(visible('aw-cash-account'),'Choose an eligible cash or bank account.');
        for(const id of ['aw-cash-project','aw-default-project'])if($(id).value&&$(id).selectedOptions[0]?.disabled)add(visible(id),'Choose an available project or Organization operations.');
        const cash=cents(p.cash_amount);if(cash===null||cash<=0n)add('aw-cash-amount','Enter a positive amount with at most two decimal places.');
        let debit=0n,credit=0n,valid=cash!==null;
        for(const l of p.lines){
            const account='aw-line-'+l.client_id+'-account',d=cents(l.debit_amount),c=cents(l.credit_amount);
            if(eligible(account,l.account_id))add(visible(account),'Choose an available cost, asset or liability account.');
            const project='aw-line-'+l.client_id+'-project';if(paymentMode!=='quick'&&l.fund_project_id&&$(project).selectedOptions[0]?.disabled)add(visible(project),'Choose an available project or Organization operations.');
            if(d===null||c===null||((d===0n)===(c===0n))){add(paymentMode==='quick'?'aw-cash-amount':'aw-line-'+l.client_id+'-'+(d===null||d===0n?'debit':'credit'),'Enter one positive debit or credit per allocation; keep the other side zero.');valid=false;}
            else {debit+=d;credit+=c;}
        }
        if(!p.lines.length)add('aw-add-line','Add at least one allocation.');
        if(valid&&debit!==credit+cash)add('aw-cash-amount','The complete entry is not balanced. Check the independent cash and allocation amounts.');
        for(const d of p.documents){
            const box=root.querySelector('[data-document="'+d.receipt_id+'"]');if(!box)continue;
            box.querySelectorAll('[data-doc-field],[data-allocation]').forEach((el,i)=>el.id='aw-doc-'+d.receipt_id+'-'+i);
            if(!d.reviewed)add(box.querySelector('[data-doc-field="reviewed"]').id,'Review this document after its latest changes.');
            if(d.purpose==='amount'){
                const accepted=cents(d.accepted_amount),declared=cents(d.declared_amount),sum=d.allocations.reduce((s,a)=>{const c=cents(a.amount);return s===null||c===null?null:s+c;},0n);
                if(accepted===null||declared===null||accepted<=0n||accepted>declared||sum!==accepted)add(box.querySelector('[data-doc-field="accepted_amount"]').id,'Accepted support must be positive, no greater than the declared amount, and equal its allocations.');
            }
        }
        return errors;
    }
    function paymentShowErrors(){
        if(!payment)return 0;const errors=paymentValidate();
        const signature=JSON.stringify(errors),entries=Object.entries(errors);
        // Do not replace a clicked summary link during the field's blur/change event.
        if(signature===paymentErrorSignature&&$('aw-error').querySelectorAll('a').length===entries.length&&entries.every(([id])=>$(id+'-error')&&$(id)?.getAttribute('aria-invalid')==='true'))return entries.length;
        paymentErrorSignature=signature;
        root.querySelectorAll('.aw-field-error').forEach(el=>el.remove());
        root.querySelectorAll('[aria-invalid]').forEach(el=>{el.removeAttribute('aria-invalid');el.setAttribute('aria-describedby',(el.getAttribute('aria-describedby')||'').split(' ').filter(v=>!v.endsWith('-error')).join(' '));});
        const summary=$('aw-error');summary.replaceChildren();
        if(entries.length){const heading=document.createElement('strong');heading.textContent='Check these payment details';summary.append(heading);const ul=document.createElement('ul');summary.append(ul);
            entries.forEach(([id,text])=>{const control=$(id);if(!control)return;const note=document.createElement('span');note.id=id+'-error';note.className='aw-field-error';note.textContent=text;
                control.closest('label')?.append(note);control.setAttribute('aria-invalid','true');control.setAttribute('aria-describedby',(control.getAttribute('aria-describedby')||'')+' '+note.id);
                const li=document.createElement('li'),a=document.createElement('a');a.href='#'+id;a.textContent=text;a.addEventListener('click',e=>{e.preventDefault();let el=control;while(el&&el!==root){if(el.tagName==='DETAILS')el.open=true;el=el.parentElement;}control.focus();});li.append(a);ul.append(li);
            });
        }return entries.length;
    }
    function paymentSummary(review,panel){
        const summary=document.createElement('dl');summary.className='aw-payment-summary';
        const add=(name,value)=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=name;dd.textContent=value;summary.append(dt,dd);};
        const cash=review.lines.filter(l=>Number(l.is_cash_account)).reduce((a,l)=>a+cents(l.credit_amount),0n);
        add('Cash paid',money(cash));
        const eligible=review.lines.filter(l=>!Number(l.is_cash_account)&&['Expense','Asset'].includes(l.account_type));
        const liabilities=review.lines.filter(l=>l.account_type==='Liability'&&cents(l.credit_amount)>0n);
        const gross=eligible.reduce((a,l)=>a+cents(l.debit_amount),0n),held=liabilities.reduce((a,l)=>a+cents(l.credit_amount),0n);
        if(held>0n&&gross-held===cash){add('Gross costs / asset purchases',money(gross));add('Withholding / other liability credits',money(held));}
        panel.prepend(summary);
    }
    async function paymentRecord(){
        const recovering=paymentStep==='unknown';
        try{const result=await api('post',paymentRequest);posted(result.id,result.duplicate);}
        catch(e){if(recovering||e.uncertain||!e.status){paymentView('unknown');message('Recording result is unknown. Keep this draft and check the same request.');paymentFocus='aw-check-result';}else{paymentRequest=null;paymentView('review');}throw e;}
    }
    if(payment){
        const form=$('aw-entry'),optional=document.createElement('details'),heading=document.createElement('summary');heading.textContent='Optional reference and default project context';optional.append(heading);optional.id='aw-optional';
        const grid=document.createElement('div');grid.className='aw-grid';optional.append(grid);grid.append($('aw-reference').closest('label'),$('aw-default-project').closest('label'));form.append(optional);
        const home=document.createElement('span');home.id='aw-project-home';$('aw-cash-project').closest('label').before(home);
        const help=document.createElement('p');help.id='aw-account-help';help.className='aw-help';help.textContent='Choose the account for this cost, asset purchase or liability settlement.';$('aw-lines').after(help);
        const aside=root.querySelector('.aw-columns > aside'),docs=document.createElement('details'),ds=document.createElement('summary');ds.textContent='Supporting documents (optional)';docs.id='aw-document-group';docs.className='aw-card';docs.append(ds,...aside.childNodes);aside.replaceWith(docs);
        $('aw-review').textContent='Review payment';root.querySelector('.aw-cash label:has(#aw-cash-amount)').firstChild.textContent='Amount paid (PHP)';
        const refresh=document.createElement('button');refresh.type='button';refresh.id='aw-refresh-review';refresh.textContent='Refresh review';refresh.addEventListener('click',()=>form.requestSubmit());$('aw-back').after(refresh);
        root.querySelectorAll('[data-payment-mode]').forEach(b=>b.addEventListener('click',()=>{
            read();const next=b.dataset.paymentMode,required=PaymentPresentation.mode(state);
            if(next==='quick'&&required!=='quick'||next==='split'&&required==='advanced'){error(new Error('These values require the detailed journal. No amounts or allocations have been changed.'));return;}
            paymentMode=next;renderLines();if(paymentErrorsShown)paymentShowErrors();
        }));
        // Deliberate Quick edits update the two represented values, never merely selecting a mode.
        root.addEventListener('input',e=>{if(paymentMode==='quick'&&e.target.id==='aw-cash-amount'){state.lines[0].debit_amount=e.target.value;root.querySelector('[data-field="debit_amount"]').value=e.target.value;}},true);
        root.addEventListener('change',e=>{if(paymentMode==='quick'&&e.target.id==='aw-cash-project'){state.lines[0].fund_project_id=e.target.value;const s=root.querySelector('[data-field="fund_project_id"]');s.value=e.target.value;recordSelectors.get(s)?.sync();}},true);
        $('aw-check-result').addEventListener('click',()=>run(async()=>{const session=await api('session',{},'GET');config.csrf_token=session.csrf_token;await paymentRecord();}));
        document.addEventListener('click',e=>{
            const a=e.target.closest('a[href]');if(!a||a.target==='_blank'||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||a.getAttribute('href').startsWith('#'))return;
            const dest=new URL(a.href,location.href);if(dest.origin!==location.origin)return;
            if(busy||paymentStep==='unknown'){e.preventDefault();message('Wait for the current action or check the recording result before leaving.');return;}
            const current=dest.pathname===location.pathname&&!dest.search&&paymentStep!=='recorded';if(current){e.preventDefault();return;}
            if(!dirty)return;e.preventDefault();leaveDestination=a.href;$('aw-leave-error').textContent='';$('aw-leave-dialog').showModal();
        });
        $('aw-leave-stay').addEventListener('click',()=>$('aw-leave-dialog').close());
        $('aw-leave-unsaved').addEventListener('click',()=>{dirty=false;location.href=leaveDestination;});
        $('aw-leave-save').addEventListener('click',()=>run(async()=>{try{await save();dirty=false;location.href=leaveDestination;}catch(e){$('aw-leave-error').textContent=e.message;throw e;}}));
    }

    render();showAdvanceContext();run(async()=>{
        if(config.draft_id){const d=await api('draft',{draft_id:config.draft_id},'GET');if(d.source_book!==book)throw new Error('This draft belongs to a different entry page. Open it from My drafts.');adopt(d);render();if(d.state==='Posted'){posted(d.posted_journal_id,true,d.advance_id);if(isCorrection)await showPostedCorrection(d);}if(d.state==='Discarded')throw new Error('This draft was discarded.');}
        await refreshDocuments();
        if(config.receipt_id&&!config.draft_id){await save();adopt(await api('attach',{...identity(),receipt_id:config.receipt_id}));render();message('Image attached to a new private draft. Enter and review the journal manually.');}
    });
})();
