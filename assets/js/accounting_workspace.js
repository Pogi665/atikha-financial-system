(() => {
    'use strict';
    const config=JSON.parse(document.getElementById('workspace-data').textContent),book=config.book;
    const isCorrection=Boolean(config.is_correction),reverseOnly=isCorrection&&config.mode==='reverse_only';
    let correctionPayload=isCorrection?structuredClone(config.initial_payload):null,correctionTarget=config.target;
    // Explicit opt-in excludes dedicated advance/correction and legacy scalar OCR routes.
    const focused=Boolean(config.ordinary_workspace)&&!isCorrection&&!config.workflow_kind;
    const cashFocused=focused&&book!=='GJ';
    const counterpartSide=book==='CRB'?'credit':'debit',oppositeSide=book==='CRB'?'debit':'credit';
    const advanceKind=config.workflow_kind||'',isAdvance=Boolean(advanceKind),isLiquidation=advanceKind==='advance_liquidation';
    const advanceFocused=typeof AdvancePresentation!=='undefined'&&AdvancePresentation.enabled(config);
    const correctionFocused=typeof CorrectionPresentation!=='undefined'&&CorrectionPresentation.enabled(config);
    const viewFocused=focused||advanceFocused||correctionFocused;
    const entryNoun=correctionFocused?(reverseOnly?'reversal':'correction'):advanceFocused?AdvancePresentation.names[advanceKind]:book==='CDB'?'payment':book==='CRB'?'receipt':'journal entry';
    const $=id=>document.getElementById(id),root=$('entry-workspace');
    const nonce=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),x=>x.toString(16).padStart(2,'0')).join('');
    let lists=config.lists,draft=null,key=nonce(),token='',dirty=false,busy=false,documentDetails=[],pendingUpload=null;
    let liquidationDetailed=false,confirmedProofKey=null,correctionRecorded=false,lastReview=null;
    let busyDisabled=[],entryMode='',entryStep='enter',entryRequest=null,entryErrorsShown=false,leaveDestination='',entryFocus='',entryErrorSignature='';
    function disableBusyControls(){if(!busy||(!isCorrection&&!advanceFocused))return;root.querySelectorAll('button,input,select,textarea').forEach(el=>{if(!el.disabled){el.disabled=true;busyDisabled.push(el);}});}
    const freshLine=()=>({client_id:nonce(),account_id:'',fund_project_id:$('aw-default-project')?.value||'',debit_amount:'',credit_amount:''});
    let state={entry_date:$('aw-date').value,reference:'',description:'',party_id:'',default_project_id:'',cash_account_id:'',cash_amount:'',cash_project_id:'',transaction_kind:'ordinary',lines:[],documents:[]};
    state.lines=isAdvance?(isLiquidation?[freshLine()]:[]):book==='GJ'?[freshLine(),freshLine()]:[freshLine()];
    if(isAdvance){Object.assign(state,{control_account_id:'',due_date:'',approval_name:'',approval_date:'',approval_reference:'',line_notes:{}});if(config.advance){state.party_id=String(config.advance.party_id);state.default_project_id=state.cash_project_id=config.advance.originating_project_id===null?'':String(config.advance.originating_project_id);state.lines.forEach(l=>l.fund_project_id=state.default_project_id);}}
    if(isCorrection){if(correctionPayload.replacement)state=structuredClone(correctionPayload.replacement);else{state.lines=[];state.entry_date=correctionPayload.entry_date;}root.classList.toggle('aw-reverse-only',reverseOnly);}
    const cents=v=>{if(!/^\d{1,13}(\.\d{1,2})?$/.test(v||''))return v===''?0n:null;const [w,f='']=v.split('.');return BigInt(w)*100n+BigInt(f.padEnd(2,'0'));};
    const displayDate=value=>new Date(value+'T12:00:00Z').toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric',timeZone:'UTC'});
    const money=c=>'₱'+(c/100n).toLocaleString('en-PH')+'.'+String(c%100n).padStart(2,'0');
    async function api(action,args={},method='POST'){
        const url=new URL(['master','session'].includes(action)?'accounting_actions.php':(config.endpoint||'accounting_actions.php'),location.href),options={credentials:'same-origin',cache:'no-store'};
        if(method==='GET'){url.search=new URLSearchParams({action,...args});}else{options.method='POST';options.headers={'Content-Type':'application/json'};options.body=JSON.stringify({action,csrf_token:config.csrf_token,...args});}
        let response;try{response=await fetch(url,options);}catch(e){if(viewFocused)throw Object.assign(new Error('The connection was interrupted. Check the recording result using the same request.'),{uncertain:true});throw e;}let data;try{data=await response.json();}catch{throw Object.assign(new Error('The server response was interrupted. Retry the same action.'),{uncertain:true});}
        if(typeof data?.ok!=='boolean')throw Object.assign(new Error('The recording response could not be verified. Check the same request.'),{uncertain:true});
        if(!response.ok||!data.ok)throw Object.assign(new Error(data.error||'Request failed.'),{status:response.status,uncertain:response.status>=500||response.status<400});
        if(action==='post'&&(!Number.isSafeInteger(data.result?.id)||data.result.id<=0||(advanceFocused&&!AdvancePresentation.positiveId(data.result?.advance_id))))throw Object.assign(new Error('The recorded transaction or advance identity could not be verified. Check the same request.'),{uncertain:true});if(action==='post'&&correctionFocused)CorrectionPresentation.result(data.result,config);return data.result;
    }
    function message(v){$('aw-status').textContent=v;$('aw-status').classList.toggle('aw-draft-saved-status',v.startsWith('Draft saved.'));}
    function error(e){if(advanceFocused&&entryStep==='review'&&!token)entryView('enter');if(viewFocused&&entryErrorsShown)entryShowErrors();const p=document.createElement('p');p.textContent=e.message||String(e);$('aw-error').append(p);$('aw-error').focus();}
    function mark(){if(viewFocused){message('');entryView('enter');}if(advanceFocused&&advanceKind==='advance_return')returnProofStatus(read());else if(isAdvance&&$('aw-proof-status'))$('aw-proof-status').textContent='Save and confirm the current return context before review.';dirty=true;token='';$('aw-review-panel').hidden=true;$('aw-draft-label').textContent=(draft?'Draft #'+draft.id:'Unsaved draft')+' · unsaved changes';}
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
        const full=viewFocused?document.createElement('span'):null;if(full){full.className='aw-record-full-label';label.append(full);}
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
        const rows=lists.accounts.filter(a=>(!isLiquidation||(!Number(a.Is_Cash_Account)&&!lists.controls.includes(Number(a.CategoryID))&&((['Expense','Asset'].includes(a.Account_Type)&&a.Normal_Balance==='Debit')||(a.Account_Type==='Liability'&&a.Normal_Balance==='Credit'))))&&(cash===undefined||Boolean(Number(a.Is_Cash_Account))===cash)).map(a=>[a.CategoryID,viewFocused?a.Name+' · '+(a.Account_Code||'#'+a.CategoryID):(a.Account_Code||'#'+a.CategoryID)+' · '+a.Name]);
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
        replaceSelect('aw-party',partyOptions,state.party_id,isAdvance?'Select accountable employee':book==='GJ'?'No party':focused?(book==='CRB'?'Select payer':'Select payee'):'Select payer / payee',true);
        replaceSelect('aw-default-project',projectOptions(),state.default_project_id,'Organization operations',true);
        if(book!=='GJ'){replaceSelect('aw-cash-project',projectOptions(),state.cash_project_id,'Organization operations',true);replaceSelect('aw-cash-account',accountOptions(true),state.cash_account_id,'Select cash / bank account',true);}
    }
    function read(){
        if(isCorrection){correctionPayload.reason=$('aw-correction-reason').value;correctionPayload.backdate_reason=$('aw-backdate-reason').value;}
        Object.entries({'aw-date':'entry_date','aw-reference':'reference','aw-purpose':'description','aw-party':'party_id','aw-default-project':'default_project_id','aw-cash-account':'cash_account_id','aw-cash-amount':'cash_amount','aw-cash-project':'cash_project_id','aw-kind':'transaction_kind','aw-control-account':'control_account_id','aw-due-date':'due_date','aw-approval-name':'approval_name','aw-approval-date':'approval_date','aw-approval-reference':'approval_reference'}).forEach(([id,k])=>{if($(id))state[k]=$(id).value;});
        root.querySelectorAll('[data-line]').forEach(el=>{const l=state.lines.find(l=>l.client_id===el.dataset.line);el.querySelectorAll('[data-field]').forEach(f=>l[f.dataset.field]=f.value);});
        if(cashFocused){state.cash_amount=PaymentPresentation.editableAmount(state.cash_amount);state.lines.forEach(l=>{l.debit_amount=PaymentPresentation.editableAmount(l.debit_amount);l.credit_amount=PaymentPresentation.editableAmount(l.credit_amount);});}
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
        if(cashFocused&&$('aw-cash-project'))$('aw-project-home').after($('aw-cash-project').closest('label'));
        destroyRecordSelectors($('aw-lines'));$('aw-lines').replaceChildren();const advanced=correctionFocused||advanceFocused||(focused?entryMode==='advanced':book==='GJ'||$('aw-advanced').checked);
        state.lines.forEach((l,index)=>{
            const box=document.createElement('div');box.className='aw-line';box.dataset.line=l.client_id;
            const top=document.createElement('div');top.className='aw-line-top';
            const a=select(accountOptions(book==='GJ'?undefined:false),l.account_id,'Select account');a.dataset.field='account_id';a.id='aw-line-'+l.client_id+'-account';
            const p=select(projectOptions(),l.fund_project_id,'Organization operations');p.dataset.field='fund_project_id';p.id='aw-line-'+l.client_id+'-project';
            const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.setAttribute('aria-label','Remove line '+(index+1));remove.disabled=state.lines.length<=(isAdvance&&!isLiquidation?0:book==='GJ'&&!isLiquidation?2:1);
            remove.addEventListener('click',()=>{read();if(viewFocused&&state.documents.some(d=>d.allocations.some(a=>a.client_id===l.client_id))){error(new Error('Reallocate or remove this line’s document support before removing the line.'));return;}state.lines=state.lines.filter(x=>x!==l);mark();renderLines();renderDocuments();totals();});
            if(advanceFocused&&isLiquidation){const type=lists.accounts.find(a=>String(a.CategoryID)===l.account_id),h=document.createElement('h3');h.textContent=type?.Account_Type==='Liability'?'Liability / withholding credit':'Cost / asset purchase';box.append(h);}
            top.append(field('Account '+(index+1),a),field('Project',p),remove);box.append(top);
            const amounts=document.createElement('div');amounts.className='aw-line-money';
            ['debit','credit'].forEach(side=>{const v=input(l[side+'_amount'],{field:side+'_amount'});if(viewFocused)v.id='aw-line-'+l.client_id+'-'+side;const label=field(advanced?side[0].toUpperCase()+side.slice(1)+' (PHP)':'Amount (PHP)',v);
                if(!advanced&&side!==(book==='CRB'?'credit':'debit'))label.hidden=true;amounts.append(label);});
            box.append(amounts);if(isLiquidation){const note=document.createElement('textarea');note.value=state.line_notes[l.client_id]||'';note.maxLength=2000;note.dataset.lineNote=l.client_id;note.id='aw-line-'+l.client_id+'-note';box.append(field('Allocation note (required for liability credits)',note));}$('aw-lines').append(box);enhanceRecordSelector(a);enhanceRecordSelector(p);
        });if(advanceFocused&&isLiquidation)liquidationLayout();if(cashFocused)entryLayout();else if(focused)root.dataset.paymentMode='advanced';$('aw-add-line').disabled=state.lines.length>=(book==='GJ'&&!isLiquidation?100:99);disableBusyControls();
    }
    function totals(){
        read();let debit=0n,credit=0n,valid=true;
        state.lines.forEach(l=>{const d=cents(l.debit_amount),c=cents(l.credit_amount);if(d===null||c===null){valid=false;return;}debit+=d;credit+=c;});
        if(book!=='GJ'){const c=cents(state.cash_amount);if(c===null)valid=false;else if(book==='CRB')debit+=c;else credit+=c;}
        if(isAdvance&&!isLiquidation){if(book==='CDB')debit=credit;else credit=debit;}else if(isLiquidation&&debit>credit){credit=debit;}
        if(advanceFocused&&isLiquidation){const d=state.lines.reduce((s,l)=>cents(l.debit_amount)===null?null:s===null?null:s+cents(l.debit_amount),0n),c=state.lines.reduce((s,l)=>cents(l.credit_amount)===null?null:s===null?null:s+cents(l.credit_amount),0n);$('aw-liquidation-estimate').textContent=d===null||c===null?'Provisional amounts: check invalid debit/credit values.':'Provisional gross costs '+money(d)+' / Liability credits '+money(c)+' / Net advance reduction '+(d<c?'-':'')+money(d<c?c-d:d-c)+'. Server Review validates these amounts and evidence.';}
        $('aw-debits').textContent=valid?money(debit):'Invalid amount';$('aw-credits').textContent=valid?money(credit):'Invalid amount';
        const diff=debit-credit;$('aw-difference').textContent=valid?(diff<0n?'-':'')+money(diff<0n?-diff:diff):'Check amounts';if(focused&&$('aw-balance-status')){$('aw-balance-status').dataset.balanced=String(valid&&diff===0n&&debit>0n);$('aw-balance-status').textContent=valid&&diff===0n&&debit>0n?'Balanced':valid?(debit===0n&&credit===0n?'Enter debit and credit amounts.':'Difference remaining: '+money(diff<0n?-diff:diff)):'Check invalid amounts';}
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
            const support=document.createElement('p');support.className='aw-help';support.dataset.supportSummary='';support.setAttribute('role','status');box.append(support);
            const link=document.createElement('a');link.href=documentDetails.find(x=>String(x.id)===d.receipt_id)?.url||'receipt_attachment.php?receipt_id='+encodeURIComponent(d.receipt_id)+(draft?.state==='Posted'?'&journal_id='+draft.posted_journal_id:'');link.target='_blank';link.rel='noopener';link.textContent='Open original image';box.append(link);
            const image=document.createElement('img');image.src=link.href;image.alt='Supporting document '+d.receipt_id;box.append(image);
            const detail=documentDetails.find(x=>String(x.id)===d.receipt_id);
            if(config.item_review_enabled&&draft?.state==='Draft'){const reviewLink=document.createElement('a');reviewLink.href='evidence_item_review.php?receipt_id='+encodeURIComponent(d.receipt_id)+'&draft_id='+draft.id;reviewLink.target='_blank';reviewLink.rel='noopener';reviewLink.textContent='Review item details';const p=document.createElement('p');p.append(reviewLink);box.append(p);}
            if(isCorrection&&detail?.reused){const prior=document.createElement('p');prior.className='aw-help';prior.textContent='Prior review: '+detail.prior_review.purpose+'; declared PHP '+(detail.prior_review.declared_amount||'—')+'; accepted PHP '+(detail.prior_review.accepted_amount||'—')+'. Fresh review required.';box.append(prior);const reason=document.createElement('textarea');reason.maxLength=2000;reason.value=d.re_review_reason||'';reason.dataset.docField='re_review_reason';box.append(field('Reason for changed purpose or amounts',reason));}
            const kind=select(isAdvance&&!isLiquidation?[['supporting','Informational supporting document']]:[['supporting','Informational supporting document'],['amount','Monetary evidence']],d.purpose,'Select purpose');kind.dataset.docField='purpose';box.append(field('Document purpose',kind));
            if(d.purpose==='amount'){
                const choices=isLiquidation?[['debit','Expenditure / asset debits']]:book==='CRB'?[['credit','Credit allocations']]:book==='CDB'?[['debit','Debit allocations']]:[['debit','Debit lines'],['credit','Credit lines']];
                const side=select(choices,d.support_side,'Choose supported side');side.dataset.docField='support_side';box.append(field('Supports',side));
                const grid=document.createElement('div');grid.className='aw-grid';
                ['declared_amount','accepted_amount'].forEach(k=>grid.append(field(k==='declared_amount'?'Declared document amount (PHP)':'Support amount for this entry (PHP)',input(d[k],{docField:k}))));box.append(grid);
                const reason=document.createElement('textarea');reason.value=d.exclusion_reason;reason.maxLength=2000;reason.dataset.docField='exclusion_reason';box.append(field('Reason for excluded amount',reason));
                if(isLiquidation){const warning=document.createElement('p');warning.className='aw-document-warning';warning.dataset.partialWarning='';warning.setAttribute('role','status');box.append(warning);updatePartialDocumentWarning(box);}
                state.lines.filter(l=>{const a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id);return a&&!Number(a.Is_Cash_Account)&&(!isLiquidation||(['Expense','Asset'].includes(a.Account_Type)&&a.Normal_Balance==='Debit'&&!lists.controls.includes(Number(a.CategoryID))))&&cents(l[d.support_side+'_amount'])>0n;}).forEach(l=>{
                    const a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id),v=input(d.allocations.find(x=>x.client_id===l.client_id)?.amount||'',{allocation:l.client_id});
                    box.append(field('Allocate to '+a.Name+' · line '+(state.lines.indexOf(l)+1),v));
                });
            }
            const reviewed=document.createElement('input');reviewed.type='checkbox';reviewed.checked=d.reviewed;reviewed.dataset.docField='reviewed';const reviewLabel=field(' I reviewed the original image and confirmed these details',reviewed);reviewLabel.className='aw-inline';reviewLabel.prepend(reviewed);box.append(reviewLabel);
            const remove=document.createElement('button');remove.type='button';remove.textContent='Remove from draft';remove.addEventListener('click',()=>run(async()=>{if(!isCorrection||correctionTarget.eligible)await save();adopt(await api('remove',{...identity(),receipt_id:d.receipt_id}));render();message('Document released from this draft.');}));box.append(remove);
            kind.addEventListener('change',()=>{read();d.reviewed=false;if(kind.value==='amount')d.support_side=book==='CRB'?'credit':'debit';mark();renderDocuments();});
            box.append(document.createElement('br'));$('aw-documents').append(box);updateDocumentSummary(box);
        });disableBusyControls();
    }
    function updateDocumentSummary(box){
        const d=state.documents.find(item=>item.receipt_id===box.dataset.document),el=box.querySelector('[data-support-summary]');if(!d||!el)return;
        const review=d.reviewed?'Reviewed for this draft. ':'Needs manual review. ';
        if(d.purpose!=='amount'){el.textContent=review+'Informational document; no monetary coverage. This is not a payment or authenticity verification.';return;}
        const declared=cents(d.declared_amount),accepted=cents(d.accepted_amount),allocations=d.allocations.map(a=>cents(a.amount));
        if(declared===null||accepted===null||allocations.some(a=>a===null)||accepted>declared){el.textContent=review+'Check the declared, supported and allocated amounts. Server Review validates coverage.';return;}
        const allocated=allocations.reduce((sum,a)=>sum+a,0n);
        el.textContent=review+'Declared '+money(declared)+' · Support for this entry '+money(accepted)+' · Allocated to lines '+money(allocated)+' · Excluded '+money(declared-accepted)+'. Server Review validates coverage.'+(isLiquidation?' Generated control and liability credits do not provide expenditure support.':'');
    }
    function adopt(d){draft=d;key=d.submission_key;if(isCorrection){correctionPayload=structuredClone(d.payload);state=structuredClone(d.payload.replacement||{...state,entry_date:d.payload.entry_date,lines:[],documents:[]});if(d.target)correctionTarget=d.target;}else state=structuredClone(d.payload);if(d.document_details)documentDetails=d.document_details;dirty=false;token='';if(advanceFocused&&advanceKind==='advance_return'){confirmedProofKey=d.return_confirmation?AdvancePresentation.proofKey(state):null;returnProofStatus(state);}else if($('aw-proof-status'))$('aw-proof-status').textContent=d.return_confirmation?'Proof confirmed for the saved amount and documents.':'Proof has not been confirmed for this saved context.';$('aw-draft-label').textContent='Draft #'+d.id+' · saved';
        const url=new URL(location.href);url.searchParams.set('draft_id',d.id);url.searchParams.delete('receipt_id');history.replaceState(null,'',url);
    }
    function returnProofStatus(p){
        if(!advanceFocused||advanceKind!=='advance_return')return;
        const confirmed=confirmedProofKey!==null&&confirmedProofKey===AdvancePresentation.proofKey(p),el=$('aw-proof-status');el.dataset.confirmed=String(confirmed);
        const account=lists.all_accounts.find(a=>String(a.CategoryID)===p.cash_account_id),amount=cents(p.cash_amount);
        el.textContent=confirmed?'Proof confirmed for '+config.advance.number+' / '+(amount===null?'Check amount':money(amount))+' / '+(account?.Name||'Cash account unavailable')+' / '+p.entry_date+' / '+p.documents.length+' reviewed document(s).':'Proof not confirmed for this context. Review the images, then confirm this advance, amount, receiving account and accounting date before Review.';
    }
    function identity(){return {draft_id:String(draft.id),revision:String(draft.revision),submission_key:key};}
    function payloadForSave(){const replacement=read();if(!isCorrection)return replacement;return {entry_date:replacement.entry_date,reason:$('aw-correction-reason').value,backdate_reason:$('aw-backdate-reason').value,replacement:reverseOnly?null:replacement};}
    async function save(){const payload=payloadForSave();if(!dirty&&draft){if(viewFocused)message('Draft saved. No changes to save. Draft #'+draft.id+'. This '+entryNoun+' has not been recorded. Keep editing here, or choose Save and close to leave.');return;}
        adopt(await api('save',{draft_id:draft?String(draft.id):'',revision:draft?String(draft.revision):'',source_book:book,workflow_kind:advanceKind,advance_id:config.advance_id||'',submission_key:key,target_journal_id:String(config.target_journal_id),mode:config.mode,payload}));message(viewFocused?'Draft saved. Draft #'+draft.id+'. This '+entryNoun+' has not been recorded. Keep editing here, or choose Save and close to leave.':'Draft saved. No financial entry has been posted.');if(viewFocused&&entryErrorsShown)entryShowErrors();}
    function render(){
        if(cashFocused&&draft?.state!=='Posted'){
            let changed=false;const normalize=(object,key)=>{const value=PaymentPresentation.editableAmount(object[key]);if(value!==object[key]){object[key]=value;changed=true;}};
            normalize(state,'cash_amount');state.lines.forEach(l=>{normalize(l,'debit_amount');normalize(l,'credit_amount');});
            if(changed){dirty=true;token='';$('aw-draft-label').textContent=(draft?'Draft #'+draft.id:'Unsaved draft')+' · amount formatting updated; save required';message('Thousands separators were removed from editable amounts. Save or Review to save this draft.');}
        }
        if(isCorrection){$('aw-correction-reason').value=correctionPayload.reason;$('aw-backdate-reason').value=correctionPayload.backdate_reason;showCorrectionContext();}renderMasters();$('aw-date').value=state.entry_date;$('aw-reference').value=state.reference;$('aw-purpose').value=state.description;
        if(book!=='GJ'){$('aw-cash-amount').value=state.cash_amount;if(state.lines.some(l=>cents(l[book==='CRB'?'debit_amount':'credit_amount'])>0n))$('aw-advanced').checked=true;}
        else{$('aw-kind').value=state.transaction_kind;if(focused&&$('aw-kind-help'))$('aw-kind-help').textContent=state.transaction_kind==='transfer'?'Move money between eligible cash/bank accounts.':'Adjustments and other accounting entries.';}
        if(isAdvance){['due-date','approval-name','approval-date','approval-reference'].forEach(id=>{if($('aw-'+id))$('aw-'+id).value=state[id.replaceAll('-','_')]||'';});
            if($('aw-control-account'))replaceSelect('aw-control-account',lists.all_accounts.filter(a=>lists.controls.includes(Number(a.CategoryID))).map(a=>[a.CategoryID,a.Name+' \u00b7 '+(a.Account_Code||'#'+a.CategoryID)]),state.control_account_id,'Select advance control account',true);
        }
        if(focused){const required=PaymentPresentation.mode(state,book);entryMode=required==='advanced'||config.entry_presentation==='advanced'?'advanced':required==='split'||config.entry_presentation==='split'?'split':'quick';}renderLines();renderDocuments();totals();lockAdvanceContext();if(viewFocused&&entryErrorsShown)entryShowErrors();}
    function lockAdvanceContext(){if(viewFocused&&draft?.state==='Posted'&&(!correctionFocused||correctionRecorded))entryView('recorded');if(!isAdvance)return;if(!isLiquidation){$('aw-lines').hidden=!(advanceFocused&&state.lines.length);if(advanceFocused){const heading=root.querySelector('#aw-entry .aw-section-heading');heading.hidden=!state.lines.length;heading.querySelector('h2').textContent='Unsupported saved journal lines';} $('aw-advanced')?.closest('label')?.setAttribute('hidden','');}if(config.advance){['aw-party','aw-default-project','aw-cash-project'].forEach(id=>{if($(id))$(id).disabled=true;});root.querySelectorAll('[data-new-master]').forEach(b=>b.hidden=true);$('aw-apply-project').hidden=true;}if(!isLiquidation&&$('aw-cash-project'))$('aw-cash-project').closest('label').hidden=true;syncRecordSelectors();}

    async function run(op){if(busy)return;busy=true;busyDisabled=[];const disabled=busyDisabled;root.querySelectorAll('button,input,select,textarea').forEach(el=>{if(!el.disabled){el.disabled=true;disabled.push(el);}});syncRecordSelectors();$('aw-error').textContent='';
        try{await op();}catch(e){error(e);}finally{busy=false;disabled.forEach(el=>{if(el.isConnected)el.disabled=false;});if(draft?.state==='Posted')root.querySelectorAll('#aw-entry input,#aw-entry select,#aw-entry textarea,#aw-entry button,.aw-columns aside input,.aw-columns aside select,.aw-columns aside textarea,.aw-columns aside button').forEach(el=>el.disabled=true);lockAdvanceContext();if(isCorrection){$('aw-post').disabled=!token||draft?.state==='Posted';if(draft?.state==='Posted'&&$('aw-replacement-book'))$('aw-replacement-book').disabled=true;if(draft)$('aw-correction-mode').disabled=true;if(!correctionTarget.eligible||draft?.state==='Discarded'){root.querySelectorAll('#aw-entry button,#aw-entry input,#aw-entry select,#aw-entry textarea,#aw-upload,#aw-attach,#aw-attach-reuse,#aw-confirm-proof,#aw-replacement-book').forEach(el=>el.disabled=true);}}syncRecordSelectors();restoreMasterFocus();if(focused)$('aw-post').disabled=!token||draft?.state==='Posted';if(advanceFocused)$('aw-post').disabled=!token||draft?.state==='Posted';if(viewFocused){if(entryRequest&&entryStep==='unknown'){root.querySelectorAll('button,input,select,textarea').forEach(el=>el.disabled=true);$('aw-check-result').disabled=false;}if(correctionFocused&&correctionRecorded)root.querySelectorAll('#aw-posted button,.aw-header button').forEach(el=>el.disabled=false);if(entryFocus){$(entryFocus)?.focus();entryFocus='';}}}
    }
    function table(lines){const t=document.createElement('table');t.className='aw-table';const h=document.createElement('tr');['Account','Project','Debit (PHP)','Credit (PHP)'].forEach(v=>{const th=document.createElement('th');th.textContent=v;h.append(th);});t.append(h);lines.forEach(l=>{const tr=document.createElement('tr');[l.account_name,l.project_name,l.debit_amount,l.credit_amount].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});t.append(tr);});if(correctionFocused){let debit=0n,credit=0n;lines.forEach(l=>{const d=cents(l.debit_amount),c=cents(l.credit_amount);if(d===null||c===null)throw new Error('Journal preview contains an invalid amount.');debit+=d;credit+=c;});const foot=document.createElement('tfoot'),tr=document.createElement('tr');['Complete journal totals','',money(debit),money(credit)].forEach(v=>{const td=document.createElement('td');td.textContent=v;tr.append(td);});foot.append(tr);t.append(foot);}return t;}
    // Native selects emit input before change. Document-purpose changes first rebuild
    // their dependent controls; validate the finished editor in the change handler.
    root.addEventListener('input',e=>{if(!busy&&e.target.tagName!=='SELECT'&&!e.target.hasAttribute('data-record-search')&&e.target.closest('#aw-entry,[data-document]')){mark();totals();if(viewFocused&&entryErrorsShown)entryShowErrors();const box=e.target.closest('[data-document]');if(box){updatePartialDocumentWarning(box);updateDocumentSummary(box);}}});
    root.addEventListener('change',e=>{if(!busy&&!e.target.hasAttribute('data-record-search')&&e.target.closest('#aw-entry,[data-document]')){
        if(e.target.dataset.docField&&e.target.dataset.docField!=='reviewed'){const box=e.target.closest('[data-document]');box.querySelector('[data-doc-field="reviewed"]').checked=false;}
        read();mark();if(e.target.dataset.docField==='support_side'){state.documents.find(d=>d.receipt_id===e.target.closest('[data-document]').dataset.document).allocations=[];renderDocuments();}
        if(advanceFocused&&isLiquidation&&e.target.dataset.field==='account_id'){renderLines();renderDocuments();}
        totals();if(viewFocused&&entryErrorsShown)entryShowErrors();const box=e.target.closest('[data-document]');if(box){updatePartialDocumentWarning(box);updateDocumentSummary(box);}
    }});
    $('aw-add-line').addEventListener('click',()=>{read();state.lines.push(freshLine());if(cashFocused)entryMode='split';mark();renderLines();totals();});
    $('aw-advanced')?.addEventListener('change',()=>{read();if(!$('aw-advanced').checked&&state.lines.some(l=>cents(l[book==='CRB'?'debit_amount':'credit_amount'])>0n)){$('aw-advanced').checked=true;error(new Error('Remove opposite-side amounts before returning to simple allocations.'));}renderLines();});
    $('aw-apply-project').addEventListener('click',()=>{read();state.lines.forEach(l=>l.fund_project_id=state.default_project_id);state.cash_project_id=state.default_project_id;mark();render();});
    $('aw-save').addEventListener('click',()=>run(async()=>{
        await save();
        if(!viewFocused)return;
        if(!draft||!Number.isSafeInteger(Number(draft.id))||Number(draft.id)<=0||draft.state!=='Draft'||dirty)throw new Error('The saved draft could not be confirmed. Stay here and check My drafts before retrying.');
        location.href='accounting_drafts.php';
    }));
    $('aw-entry').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
        await save();if(viewFocused){entryErrorsShown=true;if(entryShowErrors()){entryFocus='aw-error';return;}}token='';const review=await api('review',identity());if(advanceFocused)AdvancePresentation.effect(review,advanceKind);token=review.token;if(focused){lastReview=review;$('aw-refresh-review').hidden=true;}const panel=$('aw-review-content');panel.replaceChildren();
        if(correctionFocused)correctionReviewOriginal(review,panel);if(isCorrection){const heading=document.createElement('h3');heading.textContent='Reversal preview · General Journal · not recorded';panel.append(heading,table(review.reversal.lines));const note=document.createElement('p');note.textContent=review.notice;const effect=document.createElement('p');effect.textContent=review.net_explanation;panel.append(note,effect);if(review.advance_effect){const t=review.advance_effect,p=document.createElement('p');p.textContent='Advance #'+review.original_advance_id+' \u00b7 Before PHP '+t.before_display+' \u00b7 After PHP '+t.after_display+' \u00b7 Accounting date '+t.accounting_date+' \u00b7 Earliest affected date '+t.earliest_affected_date+(t.replacement_outstanding===null?'':' \u00b7 New replacement advance: PHP '+t.replacement_display+' outstanding; a new number is assigned at posting.');panel.append(p);}if(!reverseOnly){const h=document.createElement('h3');h.textContent='Replacement preview · '+book+' · not recorded';panel.append(h);}}
        const summary=document.createElement('p');summary.textContent=review.input.entry_date+' · '+(review.party?.name||'No party')+' · '+review.input.description;if(!focused)panel.append(summary);
        const cover=document.createElement('div');cover.className='aw-coverage';cover.textContent=focused?PaymentPresentation.coverageText(review):advanceFocused?review.coverage.status:review.coverage.status+' — '+(isLiquidation?['debit']:book==='GJ'?['debit','credit']:book==='CRB'?['credit']:['debit']).map(side=>side+': PHP '+review.coverage[side].covered+' supported of PHP '+review.coverage[side].eligible).join('; ');if(!focused)panel.append(cover);
        if(advanceFocused)advanceSummary(review,panel);else if(focused)entrySummary(review,panel);const wrap=document.createElement('div');wrap.className='aw-table-wrap';wrap.append(table(review.lines));if(focused){const details=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Accounting details';details.open=book==='GJ'||entryMode==='advanced'||review.coverage.status==='Partially covered';const explanation=document.createElement('p');explanation.className='aw-help';explanation.textContent='View debit and credit posting';details.append(explanation);details.append(summary,wrap);panel.append(details);entryView('review');entryFocus='aw-post';}else{if(!reverseOnly)panel.append(wrap);if(advanceFocused||correctionFocused){entryView('review');entryFocus='aw-post';}}$('aw-review-panel').hidden=false;$('aw-review-panel').scrollIntoView({block:'start'});if(isCorrection){$('aw-post').disabled=!review.posting_available;$('aw-post').textContent=review.posting_available?(reverseOnly?'Confirm and record reversal':'Confirm and record correction'):'Posting unavailable';if(review.posting_available)$('aw-post').focus();else $('aw-back').focus();}else $('aw-post').focus();
    });});
    $('aw-back').addEventListener('click',()=>{if(viewFocused)entryView('enter');token='';$('aw-review-panel').hidden=true;$(correctionFocused?'aw-correction-reason':'aw-purpose').focus();});
    function posted(id,duplicate=false,advanceId=config.advance_id){if(correctionFocused)correctionRecorded=true;if(viewFocused){entryRequest=null;entryView('recorded');message(duplicate?'Recorded result recovered. No duplicate '+entryNoun+' was created.':focused?'':entryNoun[0].toUpperCase()+entryNoun.slice(1)+' recorded.');$('aw-exact-link').href='journal_transaction.php?journal_id='+encodeURIComponent(id);entryFocus='aw-exact-link';}if(advanceFocused)draft.advance_id=advanceId;if(isAdvance){if($('aw-print-label'))$('aw-print-label').textContent='POSTED journal #'+id;if($('aw-advance-link'))$('aw-advance-link').href='cash_advances.php?advance_id='+advanceId;}draft.state='Posted';draft.posted_journal_id=id;dirty=false;token='';renderDocuments();$('aw-review-panel').hidden=true;$('aw-posted').hidden=false;$('aw-draft-label').textContent='Posted journal #'+id;$('aw-posted-message').textContent='Journal #'+id+(duplicate?' was already posted. The existing result was recovered.':' posted successfully.');
        if(focused){ordinaryResult(id);$('aw-posted-message').textContent='';}if(book!=='GJ')$('aw-posted-link').href='financial_records.php?view='+book.toLowerCase()+'&from=&to=';$('aw-posted').scrollIntoView();}
    $('aw-post').addEventListener('click',()=>run(async()=>{if(viewFocused){entryRequest=correctionFocused?CorrectionPresentation.freeze({...identity(),payload:payloadForSave(),review_token:token}):structuredClone({...identity(),payload:payloadForSave(),review_token:token});await entryRecord();return;}const result=await api('post',{...identity(),payload:payloadForSave(),review_token:token});posted(result.id,result.duplicate,result.advance_id);if(isCorrection)await showPostedCorrection();}));
    async function showPostedCorrection(loaded=null){
        const d=loaded||await api('draft',{draft_id:String(draft.id)},'GET');
        if(correctionFocused){const result=CorrectionPresentation.resumed(d,config);correctionResultLinks(result);$('aw-correction-details-error').hidden=true;}
        correctionTarget=d.target;message('Correction posted. The original journal remains unchanged.');
        const eligibility=$('aw-correction-eligibility');eligibility.textContent='Corrected on '+d.payload.entry_date+'. The original remains posted and immutable.';
        const detailLink=document.createElement('a');detailLink.href='journal_corrections.php?journal_id='+config.target_journal_id;detailLink.textContent='View posted correction';eligibility.append(' ',detailLink);
        $('aw-posted').querySelector('h2').textContent='Correction posted';root.querySelector('.aw-columns').hidden=true;
        if($('aw-reuse-card'))$('aw-reuse-card').hidden=true;
        const panel=$('aw-posted');panel.querySelectorAll('[data-correction-result]').forEach(el=>el.remove());
        const section=document.createElement('section');section.dataset.correctionResult='1';
        const a=document.createElement('a');a.href='journal_corrections.php?journal_id='+config.target_journal_id;a.textContent='View posted correction / print comparison';section.append(a);
        if(isAdvance&&!correctionFocused){$('aw-advance-navigation').hidden=true;const ids=d.posted_result||{};for(const [key,label] of [['original_advance_id','View original advance'],['replacement_advance_id','View replacement advance']])if(ids[key]){const link=document.createElement('a');link.href='cash_advances.php?advance_id='+ids[key];link.textContent=label;link.dataset.advanceResult=key;section.append(document.createTextNode(' \u00b7 '),link);}}
        Object.entries(d.posted_bundle||{}).forEach(([kind,value])=>{const h=document.createElement('h3');const exact=document.createElement('a');exact.href='journal_transaction.php?journal_id='+value.journal_id;exact.textContent=kind+' journal #'+value.journal_id;h.append(exact);section.append(h,table(value.lines.map(l=>({...l,project_name:l.project_name_snapshot||'Organization operations'}))));});
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
    let advisorySelection=null,advisoryGeneration=0;
    async function employeeAdvances(){
        if(isCorrection||advanceKind!=='advance_release')return;
        const selected=state.party_id;
        if(advanceFocused){
            if(selected===advisorySelection)return;advisorySelection=selected;const request=++advisoryGeneration,panel=$('aw-advance-advisory');
            panel.textContent=selected?'Loading existing posted advances for the selected employee...':'';
            if(!selected)return;
            try{
                const register=await api('register',{party_id:selected},'GET');
                if(request!==advisoryGeneration||selected!==state.party_id)return;
                if(!Number.isSafeInteger(register.count)||register.count<0)throw new Error('Unverified advisory');
                panel.textContent='Existing posted advances: '+register.count+' matching (all pages). Outstanding '+AdvancePresentation.money(register.totals.outstanding)+' as of '+register.as_of+'. Additional releases are permitted.';
            }catch(e){if(request===advisoryGeneration&&selected===state.party_id)panel.textContent='Existing-advance advisory unavailable. Your entry and recording status are unchanged.';}
            return;
        }
        if(!selected)return;
        try{const register=await api('register',{party_id:selected},'GET');if(state.party_id!==selected)return;showAdvanceContext();const p=document.createElement('p');p.textContent='Existing advances: '+register.count+'; Outstanding PHP '+register.totals.outstanding+' (additional releases are permitted).';$('aw-advance-context').append(p);}catch(e){message('Could not load existing employee advances. '+e.message);}
    }
    $('aw-party').closest('label').addEventListener('change',()=>{if(advanceKind==='advance_release'){read();employeeAdvances();}});
    let masterKind='',masterTrigger=null,masterCreated=false,masterFocusPending=false;
    function restoreMasterFocus(){if(!masterFocusPending||busy)return;masterFocusPending=false;if(masterCreated)$(masterKind==='projects'?(focused&&entryMode==='quick'?'aw-cash-project-search':'aw-default-project-search'):'aw-party-search').focus();else masterTrigger?.focus();}
    $('aw-master-dialog').addEventListener('close',()=>{masterFocusPending=true;restoreMasterFocus();});
    root.querySelectorAll('[data-new-master]').forEach(b=>b.addEventListener('click',()=>{read();masterKind=b.dataset.newMaster;masterTrigger=b;masterCreated=false;$('aw-master-form').reset();$('aw-master-title').textContent='New '+(masterKind==='projects'?'project':'party');$('aw-master-code-label').hidden=masterKind!=='projects';$('aw-master-type-label').hidden=masterKind!=='parties';$('aw-master-error').textContent='';$('aw-master-dialog').showModal();}));
    $('aw-master-cancel').addEventListener('click',()=>$('aw-master-dialog').close());
    $('aw-master-form').addEventListener('submit',e=>{e.preventDefault();const fields=Object.fromEntries(new FormData(e.target));run(async()=>{try{const record=await api('master',{...fields,kind:masterKind,is_active:'1'});lists=await api('lists',{},'GET');
        if(masterKind==='projects'){state.default_project_id=String(record.id);if(focused&&entryMode==='quick'){state.cash_project_id=String(record.id);state.lines[0].fund_project_id=String(record.id);}}else state.party_id=String(record.id);mark();renderMasters();if(focused)renderLines();if(advanceFocused&&masterKind==='parties')employeeAdvances();masterCreated=true;$('aw-master-dialog').close();}catch(e){$('aw-master-error').textContent=e.message;}});});
    window.addEventListener('beforeunload',e=>{if(dirty||(viewFocused&&entryStep==='unknown')){e.preventDefault();e.returnValue='';}});
    function showCorrectionContext(){
        const box=$('aw-original');box.replaceChildren();const summary=document.createElement('p');summary.textContent=correctionTarget.journal.entry_date+' · '+(correctionTarget.journal.source_book||'Legacy General Journal')+' · '+correctionTarget.journal.description;box.append(summary,table(correctionTarget.lines.map(l=>({...l,project_name:l.project_name_snapshot||'Organization operations'}))));
        const status=$('aw-correction-eligibility');status.textContent=correctionTarget.ineligible_reason||'Eligible target. The original remains immutable.';
        if(correctionTarget.correction){const a=document.createElement('a');a.href='journal_corrections.php?journal_id='+config.target_journal_id;a.textContent='View posted correction';status.append(' ',a);}
        correctionTarget.blockers.forEach(b=>{const p=document.createElement('p');p.textContent='Blocking '+b.operation_kind+' journal #'+b.journal_id;const link=document.createElement('a');link.href='journal_transaction.php?journal_id='+b.journal_id;link.textContent='View blocking transaction';p.append(' ',link);status.append(p);});
        $('aw-date').min=correctionTarget.journal.entry_date;$('aw-date').max=config.today;
    }
    $('aw-correction-mode')?.addEventListener('change',()=>{
        const mode=$('aw-correction-mode').value;$('aw-correction-mode').value=config.mode;
        if(draft||busy||entryStep==='unknown'){message('A saved correction keeps its mode. Preserve this draft and start a separate task for another mode.');return;}
        const dest='journal_correction.php?journal_id='+config.target_journal_id+'&mode='+mode;
        if(correctionFocused&&dirty){leaveDestination=dest;$('aw-leave-error').textContent='';$('aw-leave-dialog').showModal();return;}
        dirty=false;location.href=dest;
    });
    $('aw-replacement-book')?.addEventListener('change',()=>run(async()=>{
        const next=$('aw-replacement-book').value,p=payloadForSave(),r=p.replacement;
        if(book!=='GJ'&&(r.cash_account_id||r.cash_amount)){r.lines.unshift({client_id:'cash_'+nonce().slice(0,40),account_id:r.cash_account_id,fund_project_id:r.cash_project_id,debit_amount:book==='CRB'?r.cash_amount:'',credit_amount:book==='CDB'?r.cash_amount:''});r.cash_account_id=r.cash_amount=r.cash_project_id='';}
        if(next!=='GJ'){const side=next==='CRB'?'debit_amount':'credit_amount',index=r.lines.findIndex(l=>lists.accounts.some(a=>String(a.CategoryID)===String(l.account_id)&&Number(a.Is_Cash_Account))&&cents(l[side])>0n);if(index>=0){const [cash]=r.lines.splice(index,1);r.cash_account_id=cash.account_id;r.cash_project_id=cash.fund_project_id;r.cash_amount=cash[side];}}
        const d=await api('save',{draft_id:draft?String(draft.id):'',revision:draft?String(draft.revision):'',submission_key:key,target_journal_id:String(config.target_journal_id),mode:config.mode,source_book:next,payload:p});
        message('Replacement book saved. Opening '+next+' workspace.');
        dirty=false;location.href='journal_correction.php?draft_id='+d.id;
    }));
    function entryView(step){
        if(correctionFocused){correctionView(step);return;}if(advanceFocused){advanceView(step);return;}if(!focused)return;entryStep=step;
        root.querySelector('.aw-columns').hidden=step!=='enter';
        $('aw-review-panel').hidden=step!=='review';$('aw-posted').hidden=step!=='recorded';$('aw-unknown').hidden=step!=='unknown';
        const capital=entryNoun[0].toUpperCase()+entryNoun.slice(1);
        root.querySelector('h1').textContent={enter:({CDB:'Record a Payment',CRB:'Record Money Received',GJ:'General Journal Entry'}[book]),review:'Review '+entryNoun,recorded:capital+' recorded',unknown:'Check recording result'}[step];
        root.querySelector('.aw-header > div > p:last-child').textContent={enter:book==='GJ'?'Record an adjustment, transfer, or other journal entry.':'Enter the '+entryNoun+' details, review, then confirm to record.',review:book==='GJ'?'Check every account, project, debit and credit before recording.':book==='CRB'?'Check where the money is received and how this receipt will be recorded.':'Check where the money comes from and how this payment will be recorded.',recorded:'The '+entryNoun+' has been added to the accounting records.',unknown:'The '+entryNoun+' may already be recorded. Check the same request before continuing.'}[step];
        $('aw-mode-notice').hidden=step!=='enter';
        Array.from($('aw-progress').children).forEach((el,i)=>{if(i==={enter:0,review:1,recorded:2,unknown:1}[step])el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});
        if(step==='review')message('Ready to record');
        if(step==='recorded')root.querySelectorAll('.aw-columns input,.aw-columns select,.aw-columns textarea,.aw-columns button').forEach(el=>el.disabled=true);
    }
    function correctionView(step){
        entryStep=step;root.dataset.correctionStep=step;
        root.querySelector('.aw-columns').hidden=step!=='enter';
        if($('aw-reuse-card'))$('aw-reuse-card').hidden=step!=='enter';
        $('aw-correction-context').querySelector('.aw-grid').hidden=step!=='enter';
        if($('aw-advance-context'))$('aw-advance-context').hidden=step!=='enter';
        if($('aw-other-correction-mode'))$('aw-other-correction-mode').hidden=step!=='enter';
        $('aw-review-panel').hidden=step!=='review';$('aw-posted').hidden=step!=='recorded';$('aw-unknown').hidden=step!=='unknown';
        $('aw-mode-notice').hidden=step!=='enter';
        root.querySelector('h1').textContent={enter:reverseOnly?'Reverse an entry recorded by mistake':'Correct a recorded transaction',review:'Review '+entryNoun+' previews',recorded:'Correction recorded',unknown:'Recording result unknown'}[step];
        root.querySelector('.aw-header > div > p:last-child').textContent={enter:'Prepare a linked '+entryNoun+'. The original remains in history.',review:'Not recorded yet. Inspect the original and generated journal previews before confirming.',recorded:'Recorded together. Open the exact journals or correction comparison below.',unknown:'This correction may already be recorded. Check the same frozen request before continuing.'}[step];
        Array.from($('aw-progress').children).forEach((el,i)=>{if(i==={enter:0,review:1,recorded:2,unknown:1}[step])el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});
        if(step==='review')message('Review complete. These generated journals are previews, not posted transactions.');
        if(step==='recorded')root.querySelectorAll('.aw-columns input,.aw-columns select,.aw-columns textarea,.aw-columns button').forEach(el=>el.disabled=true);
    }
    function correctionValidate(){
        const errors={};if(!$('aw-correction-reason').value.trim())errors['aw-correction-reason']='Explain why this recorded transaction needs correction.';
        if(!$('aw-date').value||!$('aw-date').validity.valid)errors['aw-date']='Choose a valid accounting date within the permitted correction dates.';
        return errors;
    }
    function correctionReviewOriginal(review,panel){
        if(review.mode!==config.mode||!Array.isArray(review.original)||!Array.isArray(review.reversal?.lines)||!review.original.length||!review.reversal.lines.length)throw new Error('Correction previews could not be verified. Review the same saved draft again.');
        const h=document.createElement('h3'),a=document.createElement('a');a.href='journal_transaction.php?journal_id='+config.target_journal_id;a.textContent='Original Transaction #'+config.target_journal_id+' / Posted';h.append(a);
        const details=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Original Accounting Details';details.append(summary,table(review.original.map(l=>({...l,project_name:l.project_name_snapshot||'Organization operations'}))));panel.append(h,details);
        const reason=document.createElement('p');reason.textContent='Correction reason: '+review.reason;panel.append(reason);
    }
    function correctionResultLinks(result){
        CorrectionPresentation.result(result,config);$('aw-correction-context').querySelector('h2').textContent='Original Transaction #'+config.target_journal_id+' / Corrected';$('aw-correction-eligibility').textContent='Correction recorded. The original remains posted and unchanged.';$('aw-correction-result-links').replaceChildren();
        const links=$('aw-correction-result-links');
        for(const [key,label] of [['target_journal_id','View original'],['reversal_journal_id','View reversal'],['replacement_journal_id','View replacement']]){
            if(result[key]===null)continue;const a=document.createElement('a');a.href='journal_transaction.php?journal_id='+result[key];a.textContent=label+' #'+result[key];a.dataset.correctionLink=key;links.append(a,' · ');
        }
        const compare=document.createElement('a');compare.href='journal_corrections.php?correction_id='+result.correction_id;compare.textContent='Compare correction #'+result.correction_id;links.append(compare);
        for(const [key,label] of [['original_advance_id','View original advance'],['replacement_advance_id','View replacement advance']])if(CorrectionPresentation.positiveId(result[key])){
            const a=document.createElement('a');a.href='cash_advances.php?advance_id='+result[key];a.textContent=label+' #'+result[key];a.dataset.advanceResult=key;links.append(' · ',a);
        }
        if($('aw-advance-navigation'))$('aw-advance-navigation').hidden=true;
    }
    function correctionDetailsUnavailable(e){
        const el=$('aw-correction-details-error');el.hidden=false;el.querySelector('p').textContent='Correction recorded; full details are temporarily unavailable. Retry this read, not the financial posting. '+e.message;
    }
    function advanceView(step){
        entryStep=step;root.dataset.advanceStep=step;
        root.querySelector('.aw-columns').hidden=step!=='enter';
        $('aw-review-panel').hidden=step!=='review';$('aw-posted').hidden=step!=='recorded';$('aw-unknown').hidden=step!=='unknown';
        const title={advance_release:'Release an advance',advance_liquidation:'Record liquidation',advance_return:'Return unused cash'}[advanceKind];
        root.querySelector('h1').textContent={enter:title,review:'Review '+entryNoun,recorded:entryNoun[0].toUpperCase()+entryNoun.slice(1)+' recorded',unknown:'Check recording result'}[step];
        root.querySelector('.aw-header > div > p:last-child').textContent={enter:'Enter details, review the complete journal, then confirm to record.',review:'Not recorded yet. Check the complete entry.',recorded:'Recorded. Open the linked advance and exact transaction below.',unknown:'This entry may already be recorded. Check the same request before continuing.'}[step];
        $('aw-mode-notice').hidden=step!=='enter';$('aw-advance-advisory').hidden=step!=='enter';
        // This context was loaded before the operation. Never present that old
        // balance as the current balance after a confirmed posting.
        $('aw-advance-context').hidden=step==='recorded'||step==='unknown';
        Array.from($('aw-progress').children).forEach((el,i)=>{if(i==={enter:0,review:1,recorded:2,unknown:1}[step])el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});
        if(step==='review')message('Review complete. This '+entryNoun+' has not been recorded.');
        if(step==='recorded')root.querySelectorAll('.aw-columns input,.aw-columns select,.aw-columns textarea,.aw-columns button').forEach(el=>el.disabled=true);
    }
    function advanceValidate(){
        const p=read(),errors={},add=(id,text)=>errors[id]=text;
        const visible=id=>$(id+'-search')?id+'-search':id;
        const unavailable=id=>!$(id)?.value||$(id).selectedOptions[0]?.disabled;
        const date=v=>{const d=new Date(v+'T00:00:00Z');return /^\d{4}-\d{2}-\d{2}$/.test(v)&&v>='1000-01-01'&&v<='9998-12-31'&&Number.isFinite(d.valueOf())&&d.toISOString().slice(0,10)===v;};
        if(!date(p.entry_date)||p.entry_date>$('aw-date').max)add('aw-date','Choose a real accounting date on or before today.');
        if(!p.description.trim())add('aw-purpose','Explain the purpose of this '+entryNoun+'.');
        if(advanceKind==='advance_release'){
            if(unavailable('aw-party'))add(visible('aw-party'),'Choose an available accountable employee.');
            if(unavailable('aw-control-account'))add(visible('aw-control-account'),'Explicitly choose a designated advance control account.');
            if(!date(p.due_date)||p.due_date<p.entry_date)add('aw-due-date','Choose a real liquidation deadline on or after the release date.');
            if(p.default_project_id&&$('aw-default-project').selectedOptions[0]?.disabled)add(visible('aw-default-project'),'Choose an available originating project or Organization operations.');
            if(p.approval_name||p.approval_date||p.approval_reference){
                if(!p.approval_name.trim())add('aw-approval-name','Enter the name for the staff-recorded external approval.');
                if(!date(p.approval_date)||p.approval_date>p.entry_date)add('aw-approval-date','Enter a real external approval date no later than the release date.');
            }
        }
        if(isLiquidation){
            if(!p.lines.length)add('aw-add-line','Add at least one supported cost or asset purchase.');
            for(const l of p.lines){const base='aw-line-'+l.client_id,account=lists.accounts.find(a=>String(a.CategoryID)===l.account_id),d=cents(l.debit_amount),c=cents(l.credit_amount);
                if(unavailable(base+'-account'))add(visible(base+'-account'),'Choose an eligible cost, asset or liability account.');
                if(d===null||c===null||((d===0n)===(c===0n)))add(base+'-'+(d===null?'debit':c===null?'credit':account?.Account_Type==='Liability'?'credit':'debit'),'Enter one positive amount with at most two decimal places.');
                if(account?.Account_Type==='Liability'&&!String(p.line_notes[l.client_id]||'').trim())add(base+'-note','Explain this liability or withholding credit.');
                if(l.fund_project_id&&$(base+'-project').selectedOptions[0]?.disabled)add(visible(base+'-project'),'Choose an available project or the original advance project.');
            }
        }
        if(!isLiquidation){
            if(unavailable('aw-cash-account'))add(visible('aw-cash-account'),'Choose an eligible cash or bank account.');
            if(cents(p.cash_amount)===null||cents(p.cash_amount)<=0n)add('aw-cash-amount','Enter a positive cash amount with at most two decimal places.');
            if(p.lines.length)add('aw-add-line','Release and return journals are generated. Inspect and explicitly remove unsupported saved lines.');
        }
        if(advanceKind==='advance_return'){if(!p.documents.length)add('aw-upload','Attach and manually review proof of the returned cash.');if(confirmedProofKey===null||confirmedProofKey!==AdvancePresentation.proofKey(p))add('aw-confirm-proof','Confirm the current return proof before accounting Review.');}
        for(const d of p.documents){
            const box=root.querySelector('[data-document="'+d.receipt_id+'"]');if(!box)continue;
            box.querySelectorAll('[data-doc-field],[data-allocation]').forEach((el,i)=>el.id='aw-doc-'+d.receipt_id+'-'+i);
            if(!d.reviewed)add(box.querySelector('[data-doc-field="reviewed"]').id,'Review this document after its latest changes.');
            if(isLiquidation&&d.purpose==='amount'){const declared=cents(d.declared_amount),accepted=cents(d.accepted_amount),sum=d.allocations.reduce((s,a)=>s===null||cents(a.amount)===null?null:s+cents(a.amount),0n);if(declared===null||accepted===null||accepted<=0n||accepted>declared||sum!==accepted)add(box.querySelector('[data-doc-field=accepted_amount]').id,'Accepted support must be positive, no greater than the confirmed total, and equal its allocations.');if(accepted!==null&&declared!==null&&accepted<declared&&!d.exclusion_reason.trim())add(box.querySelector('[data-doc-field=exclusion_reason]').id,'Explain the excluded amount.');}
            if(!isLiquidation&&d.purpose!=='supporting')add(box.querySelector('[data-doc-field="purpose"]').id,'Release and return proof is informational, without monetary allocations.');
        }
        return errors;
    }
    function advanceSummary(review,panel){
        const effect=AdvancePresentation.effect(review,advanceKind),summary=document.createElement('dl');summary.className='aw-payment-summary';
        const add=(label,value)=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=value;summary.append(dt,dd);};
        const ctx=review.input.advance_context;
        add('Accountable employee',review.party?.name||'Unavailable');add('Purpose',review.input.description);add('Accounting date',effect.accounting_date);
        if(advanceKind==='advance_release'){
            add('Amount released',AdvancePresentation.money(ctx.reduction));add('Liquidation deadline',ctx.due_date);
            add('Proposed new advance outstanding',AdvancePresentation.money(effect.new_advance_outstanding));
        }else{
            add('Linked advance',config.advance?.number||'Advance #'+config.advance_id);
            add('Current posted outstanding',AdvancePresentation.money(effect.before_outstanding));
            add('Projected current outstanding after this entry',AdvancePresentation.money(effect.after_outstanding));
            if(isLiquidation){add('Gross costs / asset purchases',AdvancePresentation.money(ctx.gross_costs));add('Liability / withholding credits',AdvancePresentation.money(ctx.liability_credits));add('Gross reviewed monetary support',AdvancePresentation.money(review.coverage.debit.covered));}
            add('Advance reduction',AdvancePresentation.money(ctx.reduction));
        }
        add('Current-balance basis',effect.balance_as_of+' (not a historical balance at the entry date)');
        for(const line of review.lines){add(line.client_id==='control'?'Advance asset / control account':line.client_id==='cash'?(advanceKind==='advance_return'?'Receive into':'Pay from'):'Allocation account',line.account_name);add('Actual project',line.project_name);}
        if(advanceKind==='advance_return')add('Return proof',review.coverage.status+' for this advance, amount, receiving account, accounting date and reviewed documents');
        if(ctx.approval)add('External approval recorded by staff',ctx.approval.name+' · '+ctx.approval.date+' · '+ctx.approval.reference+' (not system approval)');
        panel.prepend(summary);const note=document.createElement('p');note.className='aw-help';note.textContent=advanceKind==='advance_release'?'This release creates an advance asset, not an expense. A CA number is assigned only after recording.':'The server has validated the full chronological entry. Review does not record it.';panel.append(note);
    }
    function advanceLayout(){
        const form=$('aw-entry');
        const optional=document.createElement('details'),heading=document.createElement('summary');heading.textContent='Optional reference';optional.append(heading,$('aw-reference').closest('label'));form.append(optional);
        const project=$('aw-default-project').closest('label');project.firstChild.textContent='Originating project';
        $('aw-apply-project').hidden=true;
        if(!isLiquidation)root.querySelector('label:has(#aw-cash-amount)').firstChild.textContent=advanceKind==='advance_return'?'Cash returned (PHP)':'Amount released (PHP)';
        $('aw-review').textContent='Review '+entryNoun;$('aw-post').textContent='Confirm and record '+entryNoun;
        const refresh=document.createElement('button');refresh.type='button';refresh.id='aw-refresh-review';refresh.textContent='Refresh review';refresh.addEventListener('click',()=>form.requestSubmit());$('aw-back').after(refresh);
        if(isLiquidation){const heading=root.querySelector('#aw-entry .aw-section-heading h2');heading.textContent='Costs and asset purchases';const detail=document.createElement('button');detail.type='button';detail.id='aw-liquidation-detail';detail.textContent='Show explicit debit / credit fields';detail.addEventListener('click',()=>{read();liquidationDetailed=!liquidationDetailed;renderLines();if(entryErrorsShown)entryShowErrors();});$('aw-add-line').after(detail);const estimate=document.createElement('p');estimate.id='aw-liquidation-estimate';estimate.className='aw-help';estimate.setAttribute('aria-live','polite');root.querySelector('.aw-totals').before(estimate);}
        if(advanceKind==='advance_return'){$('aw-confirm-proof').className='aw-primary';$('aw-confirm-proof').before($('aw-proof-status'));}
        const aside=root.querySelector('.aw-columns > aside');
        if(advanceKind==='advance_release'){const details=document.createElement('details'),ds=document.createElement('summary');ds.textContent='Supporting release proof (optional)';details.className='aw-card';details.id='aw-document-group';details.append(ds,...aside.childNodes);aside.replaceWith(details);}
        if(advanceKind==='advance_release'&&!lists.controls.length){const help=document.createElement('p');help.className='aw-document-warning';help.textContent='No designated advance control account is available. Ask an authorized administrator to check Accounting Setup before recording.';const link=document.createElement('a');link.href='accounting_setup.php';link.textContent='Open Accounting Setup';help.append(' ',link);$('aw-control-account').closest('label').after(help);}
        advanceView('enter');
    }
    function liquidationLayout(){
        const detailed=liquidationDetailed||AdvancePresentation.liquidationDetailed(state,lists.accounts);
        $('aw-liquidation-detail').setAttribute('aria-pressed',String(detailed));
        $('aw-liquidation-detail').textContent=detailed?'Use focused cost / liability amounts':'Show explicit debit / credit fields';
        const liabilities=document.createElement('section'),h=document.createElement('h3');h.textContent='Liability / withholding credits';liabilities.id='aw-liabilities';liabilities.append(h);
        const help=document.createElement('p');help.className='aw-help';help.textContent='Credits reduce the net advance consumption. Explain each liability; they do not reduce the gross evidence required.';liabilities.append(help);
        root.querySelectorAll('[data-line]').forEach(box=>{const l=state.lines.find(l=>l.client_id===box.dataset.line),a=lists.accounts.find(a=>String(a.CategoryID)===l.account_id),credit=a?.Account_Type==='Liability';
            if(!detailed)box.querySelector('[data-field="'+(credit?'debit':'credit')+'_amount"]').closest('label').hidden=true;
            if(credit)liabilities.append(box);
        });
        if(liabilities.querySelector('[data-line]'))$('aw-lines').append(liabilities);
        const unknown=document.createElement('pre');unknown.className='aw-help';unknown.id='aw-unsupported-values';
        const metadata=state.lines.filter(l=>Object.keys(l).some(k=>!['client_id','account_id','fund_project_id','debit_amount','credit_amount'].includes(k)));
        if(metadata.length){unknown.textContent='Unsupported saved line values (preserved): '+JSON.stringify(metadata,null,2);$('aw-lines').append(unknown);}
    }
    function entryLayout(){
        if(!cashFocused)return;const quick=entryMode==='quick';root.dataset.paymentMode=entryMode;
        const modeControls=root.querySelector('.aw-payment-modes');if(modeControls)modeControls.hidden=quick;
        if(!quick&&$('aw-more-options'))$('aw-more-options').open=true;
        $('aw-purpose').rows=quick?1:2;
        $('aw-advanced').checked=entryMode==='advanced';$('aw-advanced').closest('label').hidden=true;
        root.querySelectorAll('[data-payment-mode]').forEach(b=>{b.setAttribute('aria-pressed',String(b.dataset.paymentMode===entryMode));});
        $('aw-add-line').hidden=quick;root.querySelector('#aw-entry .aw-section-heading').hidden=quick;
        root.querySelector('#aw-entry > .aw-help').hidden=quick;root.querySelector('.aw-totals').hidden=quick;
        $('aw-mode-notice').textContent=quick?'One amount and one project for this '+entryNoun+'.':'Detailed entry: amounts and project allocations are preserved independently.';
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
        if(addProject){if(quick)project.append(addProject);else $('aw-default-project').closest('label').append(addProject);}
        if(quick&&$('aw-lines').firstElementChild)$('aw-lines').firstElementChild.querySelector('.aw-line-top').append(project);
        else $('aw-project-home').after(project);
    }
    function entryValidate(){
        const p=read(),errors={},add=(id,text)=>errors[id]=text;
        const visible=id=>$(id+'-search')?id+'-search':id;
        const eligible=(id,value)=>!value||!$(id)?.selectedOptions[0]||$(id).selectedOptions[0].disabled;
        const date=p.entry_date,parsed=new Date(date+'T00:00:00Z');
        if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||date<'1000-01-01'||!Number.isFinite(parsed.valueOf())||parsed.toISOString().slice(0,10)!==date||($('aw-date').max&&date>$('aw-date').max))add('aw-date',book==='GJ'?'Choose a real accounting date.':'Choose a real accounting date on or before today.');
        if((book!=='GJ'||p.party_id)&&eligible('aw-party',p.party_id))add(visible('aw-party'),'Choose an available '+(book==='CRB'?'payer':book==='CDB'?'payee':'party')+'.');
        if(!p.description.trim())add('aw-purpose','Explain the purpose of this '+entryNoun+'.');
        if(cashFocused&&eligible('aw-cash-account',p.cash_account_id))add(visible('aw-cash-account'),'Choose an eligible cash or bank account.');
        for(const id of ['aw-cash-project','aw-default-project'])if($(id)?.value&&$(id).selectedOptions[0]?.disabled)add(visible(id),'Choose an available project or Organization operations.');
        const cash=cashFocused?cents(p.cash_amount):0n;
        if(cashFocused&&(cash===null||cash<=0n))add('aw-cash-amount','Enter a positive amount with at most two decimal places.');
        let debit=0n,credit=0n,valid=cash!==null;
        for(const l of p.lines){
            const account='aw-line-'+l.client_id+'-account',d=cents(l.debit_amount),c=cents(l.credit_amount);
            if(eligible(account,l.account_id))add(visible(account),book==='CDB'?'Choose an available cost, asset or liability account.':'Choose an available account for this line.');
            const project='aw-line-'+l.client_id+'-project';if(entryMode!=='quick'&&l.fund_project_id&&$(project).selectedOptions[0]?.disabled)add(visible(project),'Choose an available project or Organization operations.');
            if(d===null||c===null||((d===0n)===(c===0n))){
                const side=d===null?'debit':c===null?'credit':d===0n&&c===0n&&cashFocused&&entryMode==='split'?counterpartSide:d===0n?'debit':'credit';
                add(entryMode==='quick'?'aw-cash-amount':'aw-line-'+l.client_id+'-'+side,entryMode==='quick'?'Enter a positive '+entryNoun+' amount with at most two decimal places.':'Enter one positive debit or credit per allocation; keep the other side zero.');valid=false;
            }
            else {debit+=d;credit+=c;}
        }
        if(p.lines.length<(book==='GJ'?2:1))add('aw-add-line',book==='GJ'?'Add at least two journal lines.':'Add at least one allocation.');
        if(valid&&debit+(book==='CRB'?cash:0n)!==credit+(book==='CDB'?cash:0n))add(cashFocused?'aw-cash-amount':'aw-line-'+p.lines[0]?.client_id+'-debit','The complete entry is not balanced. Check the independent '+(cashFocused?'cash and allocation':'debit and credit')+' amounts.');
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
    function entryShowErrors(){
        if(!viewFocused)return 0;const errors=correctionFocused?correctionValidate():advanceFocused?advanceValidate():entryValidate();
        const signature=JSON.stringify(errors),entries=Object.entries(errors);
        // Do not replace a clicked summary link during the field's blur/change event.
        if(signature===entryErrorSignature&&$('aw-error').querySelectorAll('a').length===entries.length&&entries.every(([id])=>$(id+'-error')&&$(id)?.getAttribute('aria-invalid')==='true'))return entries.length;
        entryErrorSignature=signature;
        root.querySelectorAll('.aw-field-error').forEach(el=>el.remove());
        root.querySelectorAll('[aria-invalid]').forEach(el=>{el.removeAttribute('aria-invalid');el.setAttribute('aria-describedby',(el.getAttribute('aria-describedby')||'').split(' ').filter(v=>!v.endsWith('-error')).join(' '));});
        const summary=$('aw-error');summary.replaceChildren();
        if(entries.length){const heading=document.createElement('strong');heading.textContent='Check these '+entryNoun+' details';summary.append(heading);const ul=document.createElement('ul');summary.append(ul);
            entries.forEach(([id,text])=>{const control=$(id);if(!control)return;const note=document.createElement('span');note.id=id+'-error';note.className='aw-field-error';note.textContent=text;
                (control.closest('label')||control.parentElement).append(note);control.setAttribute('aria-invalid','true');control.setAttribute('aria-describedby',(control.getAttribute('aria-describedby')||'')+' '+note.id);
                for(let parent=control.parentElement;parent&&parent!==root;parent=parent.parentElement)if(parent.tagName==='DETAILS')parent.open=true;
                const li=document.createElement('li'),a=document.createElement('a');a.href='#'+id;a.textContent=text;a.addEventListener('click',e=>{e.preventDefault();let el=control;while(el&&el!==root){if(el.tagName==='DETAILS')el.open=true;el=el.parentElement;}control.focus();});li.append(a);ul.append(li);
            });
        }return entries.length;
    }
    function entrySummary(review,panel){
        const summary=document.createElement('dl');summary.className='aw-review-summary';
        const add=(name,value,amount=false)=>{const div=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');if(amount)div.className='aw-summary-amount';dt.textContent=name;dd.textContent=value;div.append(dt,dd);summary.append(div);};
        add(book==='CDB'?'Payee':book==='CRB'?'Source / Payer':'Party',review.party?.name||'Not recorded');add('Date',displayDate(review.input.entry_date));add('Purpose',review.input.description);add('Reference',review.input.reference||'Not provided');
        const projects=[...new Set(review.lines.map(l=>l.project_name||'Organization operations'))];add('Project',projects.join(', '));
        if(book==='GJ'){add('Entry type',review.input.transaction_kind==='transfer'?'Internal Transfer':'General Journal');add('Total Debit',money(review.lines.reduce((n,l)=>n+cents(l.debit_amount),0n)));add('Total Credit',money(review.lines.reduce((n,l)=>n+cents(l.credit_amount),0n)));}
        else{
            const cashLines=review.lines.filter(l=>Number(l.is_cash_account));cashLines.forEach(l=>add(book==='CDB'?'Pay from':'Receive into',l.account_name));const primaryCash=review.lines.find(l=>l.client_id==='cash');add(cashLines.length===1?'Amount':'Selected cash / bank amount',money(cents(primaryCash[book==='CRB'?'debit_amount':'credit_amount'])),true);
            const allocations=review.lines.filter(l=>!Number(l.is_cash_account));
            if(allocations.length===1){add('Recorded against',allocations[0].account_name);if(projects.join(', ')!==(allocations[0].project_name||'Organization operations'))add('Allocation project',allocations[0].project_name||'Organization operations');}
            else{const section=document.createElement('section');section.className='aw-review-allocations';const h=document.createElement('h3');h.textContent='Allocations';const wrap=document.createElement('div');wrap.className='aw-table-wrap';wrap.append(table(allocations));section.append(h,wrap);panel.append(section);}
        }
        panel.prepend(summary);
        const docs=document.createElement('section');docs.className='aw-ordinary-docs';const h=document.createElement('h3');h.textContent='Supporting documents';const p=document.createElement('p'),count=review.input.documents.length;p.textContent=count?count+' document'+(count===1?'':'s')+' attached and reviewed.':'No documents attached. Optional for this transaction.';docs.append(h,p);
        if(count){const detail=document.createElement('details'),s=document.createElement('summary'),text=document.createElement('p');s.textContent='Document review details';text.textContent=PaymentPresentation.coverageText(review);detail.open=review.coverage.status==='Partially covered';detail.append(s,text);docs.append(detail);}panel.append(docs);
    }
    function ordinaryResult(id){
        const panel=$('aw-posted');if(!panel.querySelector('.aw-success-icon')){const icon=document.createElementNS('http://www.w3.org/2000/svg','svg'),path=document.createElementNS('http://www.w3.org/2000/svg','path');icon.setAttribute('viewBox','0 0 24 24');icon.setAttribute('aria-hidden','true');icon.classList.add('aw-success-icon');path.setAttribute('d','M20 6 9 17l-5-5');icon.append(path);panel.querySelector('h2').prepend(icon);}panel.querySelectorAll('.aw-result-summary').forEach(e=>e.remove());
        const dl=document.createElement('dl');dl.className='aw-review-summary aw-result-summary';dl.dataset.ordinaryResult='';
        const add=(name,value)=>{const div=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=name;dd.textContent=value;div.append(dt,dd);dl.append(div);};
        add('Journal / Transaction','#'+id);add('Date',displayDate(state.entry_date));add(book==='CDB'?'Payee':book==='CRB'?'Source / Payer':'Party',draft?.posted_party?.name||lastReview?.party?.name||'Not recorded');
        if(book!=='GJ')add('Amount',money(cents(state.cash_amount)));add('Status','Recorded');
        const projects=lastReview?[...new Set(lastReview.lines.map(l=>l.project_name||'Organization operations'))]:[];if(projects.length)add('Project',projects.join(', '));
        panel.querySelector('h2').after(dl);$('aw-exact-link').textContent='View recorded transaction';
        let next=panel.querySelector('.aw-next-actions');if(!next){next=document.createElement('nav');next.className='aw-next-actions';next.setAttribute('aria-label','Next actions');const title=document.createElement('h3');title.textContent='Next actions';title.dataset.ordinaryResult='';panel.append(title,next);for(const a of [...panel.querySelectorAll(':scope > a')])if(a.id!=='aw-exact-link')next.append(a);[...panel.childNodes].filter(n=>n.nodeType===Node.TEXT_NODE&&n.textContent.includes('·')).forEach(n=>n.remove());}
        next.querySelectorAll('a').forEach(a=>a.classList.add('ui-button'));$('aw-history-link').textContent='Find another transaction';const another=next.querySelector('a:not([id])');if(another)another.textContent=book==='CDB'?'Record another payment':book==='CRB'?'Record more money received':'Record another journal entry';
    }
    async function entryRecord(){
        const recovering=entryStep==='unknown';
        try{const result=await api('post',entryRequest);posted(result.id,result.duplicate,result.advance_id);if(correctionFocused){correctionResultLinks(result);try{await showPostedCorrection();}catch(e){correctionDetailsUnavailable(e);}}}
        catch(e){if(recovering||e.uncertain||!e.status){entryView('unknown');message('Recording result is unknown. Keep this draft and check the same request.');entryFocus='aw-check-result';}else{entryRequest=null;entryView('review');if(focused&&/review|preview|expired|changed|token|fresh/i.test(e.message)){token='';$('aw-refresh-review').hidden=false;}}throw e;}
    }
    if(focused){
        const form=$('aw-entry'),optional=document.createElement('details'),heading=document.createElement('summary');heading.textContent='Additional details';optional.append(heading);optional.id='aw-optional';
        const grid=document.createElement('div');grid.className='aw-grid';optional.append(grid);if(cashFocused)grid.append($('aw-reference').closest('label'));else grid.append($('aw-party').closest('label'));grid.append($('aw-default-project').closest('label'));form.append(optional);const projectHint=document.createElement('p');projectHint.className='aw-help';projectHint.textContent='Default Project applies to new lines unless changed individually.';grid.append(projectHint);if(book==='GJ'){form.querySelector('.aw-grid').append($('aw-kind').closest('label'));const kindHelp=document.createElement('p');kindHelp.className='aw-help';kindHelp.id='aw-kind-help';$('aw-kind').closest('label').append(kindHelp);const explain=()=>kindHelp.textContent=$('aw-kind').value==='transfer'?'Move money between eligible cash/bank accounts.':'Adjustments and other accounting entries.';$('aw-kind').addEventListener('change',explain);explain();}
        if(cashFocused){const home=document.createElement('span');home.id='aw-project-home';$('aw-cash-project').closest('label').before(home);}
        const help=document.createElement('p');help.id='aw-account-help';help.className='aw-help';help.textContent=book==='CRB'?'Choose the account for this receipt, such as income, a liability, equity or an asset recovery.':book==='GJ'?'Enter every debit and credit explicitly. Transfers use eligible cash accounts; server review checks the complete entry.':'Choose the account for this cost, asset purchase or liability settlement.';$('aw-lines').after(help);
        const aside=root.querySelector('.aw-columns > aside'),docs=document.createElement('details'),ds=document.createElement('summary');ds.textContent='Supporting documents (optional)';docs.id='aw-document-group';docs.className='aw-card';docs.append(ds,...aside.childNodes);aside.replaceWith(docs);docs.querySelector('h2').remove();docs.querySelector('.aw-help').textContent='Attach receipts or supporting documents when available.';if($('aw-evidence-help'))$('aw-evidence-help').textContent='Review attached documents before recording. Monetary allocations remain available when needed.';
        $('aw-review').textContent='Review '+entryNoun;if(cashFocused)root.querySelector('.aw-cash label:has(#aw-cash-amount)').firstChild.textContent=book==='CRB'?'Amount received (PHP)':'Amount paid (PHP)';else $('aw-mode-notice').textContent='Detailed journal: enter every debit, credit and actual line project.';
        const refresh=document.createElement('button');refresh.type='button';refresh.id='aw-refresh-review';refresh.textContent='Refresh review';refresh.addEventListener('click',()=>form.requestSubmit());$('aw-back').after(refresh);
        $('aw-refresh-review').hidden=true;
        root.querySelectorAll('[data-payment-mode]').forEach(b=>b.addEventListener('click',()=>{
            read();const next=b.dataset.paymentMode,required=PaymentPresentation.mode(state,book);
            if(next==='quick'&&required!=='quick'||next==='split'&&required==='advanced'){error(new Error('These values require the detailed journal. No amounts or allocations have been changed.'));return;}
            entryMode=next;config.entry_presentation=next;const modeUrl=new URL(location.href);modeUrl.searchParams.set('presentation',next==='advanced'?'detailed':'multiple');history.replaceState(null,'',modeUrl);renderLines();if(entryErrorsShown)entryShowErrors();
        }));
        // Deliberate Quick edits update the two represented values, never merely selecting a mode.
        root.addEventListener('input',e=>{if(entryMode==='quick'&&e.target.id==='aw-cash-amount'){state.lines[0][counterpartSide+'_amount']=e.target.value;root.querySelector('[data-field="'+counterpartSide+'_amount"]').value=e.target.value;}},true);
        root.addEventListener('change',e=>{if(entryMode==='quick'&&e.target.id==='aw-cash-project'){state.lines[0].fund_project_id=e.target.value;const s=root.querySelector('[data-field="fund_project_id"]');s.value=e.target.value;recordSelectors.get(s)?.sync();}},true);
    }
    function bindEntryNavigation(){
        $('aw-check-result').addEventListener('click',()=>run(async()=>{const session=await api('session',{},'GET');config.csrf_token=session.csrf_token;await entryRecord();}));
        document.addEventListener('click',e=>{
            const a=e.target.closest('a[href]');if(!a||a.target==='_blank'||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||a.getAttribute('href').startsWith('#'))return;
            const dest=new URL(a.href,location.href);if(dest.origin!==location.origin)return;
            if(busy||entryStep==='unknown'){e.preventDefault();message('Wait for the current action or check the recording result before leaving.');return;}
            const current=entryStep!=='recorded'&&(correctionFocused?CorrectionPresentation.sameTask(a.href,location.href,{...config,draft_id:draft?.id}):advanceFocused?AdvancePresentation.sameTask(a.href,location.href,{workflow_kind:advanceKind,advance_id:config.advance_id,draft_id:draft?.id}):dest.pathname===location.pathname&&!dest.search);if(current){e.preventDefault();return;}
            if(!dirty)return;e.preventDefault();leaveDestination=a.href;$('aw-leave-error').textContent='';$('aw-leave-dialog').showModal();
        });
        $('aw-leave-stay').addEventListener('click',()=>$('aw-leave-dialog').close());
        $('aw-leave-unsaved').addEventListener('click',()=>{dirty=false;location.href=leaveDestination;});
        $('aw-leave-save').addEventListener('click',()=>run(async()=>{try{await save();dirty=false;location.href=leaveDestination;}catch(e){$('aw-leave-error').textContent=e.message;throw e;}}));
    }
    if(viewFocused)bindEntryNavigation();

    if(correctionFocused){
        const links=document.createElement('nav');links.id='aw-correction-result-links';links.className='correction-navigation';links.setAttribute('aria-label','Recorded correction journals');$('aw-posted-message').after(links);
        const unavailable=document.createElement('section');unavailable.id='aw-correction-details-error';unavailable.hidden=true;const p=document.createElement('p'),retry=document.createElement('button');p.setAttribute('role','status');retry.type='button';retry.textContent='Retry correction details';retry.addEventListener('click',()=>run(async()=>{try{await showPostedCorrection();}catch(e){correctionDetailsUnavailable(e);}}));unavailable.append(p,retry);links.after(unavailable);
        $('aw-unknown').querySelector('h2').textContent='Recording result unknown';
        const newMode=document.createElement('a');newMode.id='aw-other-correction-mode';newMode.href='journal_correction.php?journal_id='+config.target_journal_id+'&mode='+(reverseOnly?'reverse_replace':'reverse_only');newMode.textContent='Start a separate '+(reverseOnly?'reverse-and-replace':'reverse-only')+' task';$('aw-correction-context').append(newMode);
        correctionView('enter');
    }
    if(advanceFocused)advanceLayout();
    // Refined presentation only: keep all controls, serialization and save/post handlers.
    if(viewFocused){
        const form=$('aw-entry'),actions=form.querySelector('.aw-actions');
        const modes=form.querySelector('.aw-payment-modes');
        if(modes){modes.hidden=!config.entry_presentation;form.prepend(modes);}
        for(const optional of [...form.children].filter(el=>el.tagName==='DETAILS'))actions.before(optional);
        const docs=$('aw-document-group')||root.querySelector('.aw-columns > aside');
        if(docs){docs.classList.add('ui-supporting-documents');actions.before(docs);}
        root.querySelectorAll('#aw-progress li').forEach((el,i)=>{el.textContent=['1 Details','2 Review','3 Recorded'][i];});
        root.querySelector('.aw-header').querySelectorAll('a').forEach(a=>a.classList.add('ui-button'));
    }
    if(focused){$('aw-context-back')?.classList.add('ui-button');root.querySelector('.aw-context-back')?.classList.add('ui-button');if(book==='GJ')root.querySelector('.aw-header > div > p:last-child').textContent='Record an adjustment, transfer, or other journal entry.';if(book==='GJ'){$('aw-debits').parentElement.firstChild.textContent='Total Debit ';$('aw-credits').parentElement.firstChild.textContent='Total Credit ';}const balance=document.createElement('span');balance.className='aw-balance-status';balance.id='aw-balance-status';balance.setAttribute('role','status');root.querySelector('.aw-totals').append(balance);}
    render();showAdvanceContext();run(async()=>{
        if(config.draft_id){const d=await api('draft',{draft_id:config.draft_id},'GET');if(d.source_book!==book)throw new Error('This draft belongs to a different entry page. Open it from My drafts.');adopt(d);render();if(d.state==='Posted'){if(correctionFocused){const result=CorrectionPresentation.resumed(d,config);posted(result.id,true,result.advance_id);correctionResultLinks(result);await showPostedCorrection(d);}else{posted(d.posted_journal_id,true,d.advance_id);if(isCorrection)await showPostedCorrection(d);}}if(d.state==='Discarded')throw new Error('This draft was discarded.');}
        await refreshDocuments();if(advanceFocused)employeeAdvances();
        if(config.receipt_id&&!config.draft_id){await save();adopt(await api('attach',{...identity(),receipt_id:config.receipt_id}));render();message('Image attached to a new private draft. Enter and review the journal manually.');}
    });
})();
