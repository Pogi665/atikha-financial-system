(function () {
    'use strict';
    const form = document.getElementById('receipt-upload');
    const fileInput = document.getElementById('receipt-image');
    let previewUrl = '';
    fileInput?.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        const preview = document.getElementById('ocr-local-preview'), image = document.getElementById('ocr-selected-image'), description = document.getElementById('ocr-selected-file');
        const file = fileInput.files?.[0];
        preview.hidden = true; description.hidden = true; image.removeAttribute('src');
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
            description.textContent = 'Choose a JPG, PNG or WebP image up to 8 MiB.'; description.hidden = false; return;
        }
        previewUrl = URL.createObjectURL(file); image.src = previewUrl; preview.hidden = false;
        description.textContent = file.name + ' · ' + (file.size / 1024).toFixed(1) + ' KiB'; description.hidden = false;
    });
    window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
    if (form && window.fetch) {
        form.addEventListener('submit', async event => {
            if (!form.checkValidity()) return;
            event.preventDefault();
            const button = form.querySelector('button'), progress = document.getElementById('ocr-progress');
            if (button.disabled) return;
            const payload = new FormData(form);
            const file = payload.get('receipt_image');
            if (!file || !file.size || file.size > 8 * 1024 * 1024) { progress.textContent = 'Choose a receipt image up to 8 MiB.'; return; }
            button.disabled = true;
            progress.textContent = 'Storing evidence and reading the image. No journal is being posted.';
            try {
                const response = await fetch('ocr_extract.php', { method: 'POST', body: payload, credentials: 'same-origin' });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    progress.textContent = result.error || 'Receipt intake failed.';
                    if (result.data?.workspace_url) {
                        const recovery = document.createElement('a');
                        recovery.href = result.data.workspace_url; recovery.textContent = ' Open saved receipt for manual review.';
                        progress.append(recovery);
                    }
                    button.disabled = false; return;
                }
                window.location.assign(result.data.workspace_url);
            } catch (error) {
                progress.textContent = error.message + ' Retry with this same form to recover any saved upload.';
                button.disabled = false;
            }
        });
    }
    let mutation=null,mutationBusy=false;
    const feedback=document.getElementById('ocr-mutation-feedback'),mutationStatus=document.getElementById('ocr-mutation-status');
    const checkState=document.getElementById('ocr-check-state'),replay=document.getElementById('ocr-replay-retry'),openState=document.getElementById('ocr-open-state');
    function lockMutations(){document.querySelectorAll('#receipt-retry button,#receipt-discard button').forEach(button=>button.disabled=true);}
    function unknown(text){document.getElementById('ocr-retry-progress').textContent='Action not verified. Check the saved state above.';feedback.hidden=false;lockMutations();checkState.disabled=false;replay.hidden=true;openState.hidden=true;mutationStatus.textContent=text;checkState.focus();}
    function receiptDocument(doc,id){return doc.querySelector('#receipt-retry input[name="receipt_id"]')?.value===id&&doc.querySelector('.ocr-preview')?.getAttribute('src')==='receipt_attachment.php?receipt_id='+id;}
    async function sendMutation(request){
        if(mutationBusy)return;mutationBusy=true;lockMutations();checkState.disabled=true;replay.disabled=true;
        try{
            const response=await fetch(request.url,{method:'POST',body:request.payload,credentials:'same-origin',cache:'no-store'});
            const html=await response.text(),doc=new DOMParser().parseFromString(html,'text/html'),url=new URL(response.url);
            const local=url.origin===location.origin&&url.pathname.endsWith('/ocr_expense.php');
            const confirmed=response.ok&&local&&(request.action==='retry'?receiptDocument(doc,request.id):url.searchParams.get('discarded')==='1'&&Array.from(doc.querySelectorAll('[role="status"]')).some(el=>el.textContent.trim()==='Receipt discarded.'));
            if(!confirmed)throw new Error('The saved action response could not be verified.');
            mutation=null;location.assign(url.href);
        }catch(e){unknown('The '+request.action+' response was interrupted or could not be verified. Keep receipt #'+request.id+' and check its saved state before another action. '+e.message);}
        finally{mutationBusy=false;replay.disabled=false;}
    }
    for(const id of ['receipt-retry','receipt-discard'])document.getElementById(id)?.addEventListener('submit',event=>{
        const receiptForm=event.currentTarget;
        if(id==='receipt-discard'&&!window.confirm('Discard this unposted receipt and delete its image?')){event.preventDefault();return;}
        if(!window.fetch)return;
        event.preventDefault();if(mutation||mutationBusy||receiptForm.querySelector('button').disabled)return;
        // Freeze the existing form/request identity. A retry never creates a replacement key.
        const payload=new FormData(receiptForm);mutation={action:payload.get('action'),id:payload.get('receipt_id'),payload,url:new URL(receiptForm.getAttribute('action')||location.href,location.href).href};
        document.getElementById('ocr-retry-progress').textContent=mutation.action==='retry'?'Reading the saved image…':'Discarding the saved image…';
        sendMutation(mutation);
    });
    checkState?.addEventListener('click',async()=>{
        if(!mutation||mutationBusy)return;mutationBusy=true;checkState.disabled=true;replay.hidden=true;openState.hidden=true;
        try{
            const response=await fetch('ocr_expense.php?receipt='+encodeURIComponent(mutation.id),{credentials:'same-origin',cache:'no-store'});
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');
            if(response.status===404&&doc.querySelector('.ocr-error')?.textContent.includes('Receipt not found or unavailable')){
                document.getElementById('ocr-retry-progress').textContent='Saved state checked. This image is unavailable.';mutationStatus.textContent='Receipt #'+mutation.id+' is no longer available. This read does not establish why; no further retry or discard has been sent.';openState.href='ocr_expense.php';openState.textContent='Return to Scan Receipt';openState.hidden=false;return;
            }
            if(!response.ok||!receiptDocument(doc,mutation.id))throw new Error('The current receipt could not be read. Sign in normally if required, then open this same receipt.');
            const pending=doc.querySelector('.ocr-tag-neutral')?.textContent.trim()==='Reading image';
            openState.href='ocr_expense.php?receipt='+encodeURIComponent(mutation.id);openState.textContent='Open saved receipt';openState.hidden=false;
            mutationStatus.textContent=pending?'The saved image is still being read. Wait and check its state again. No retry or discard was sent.':'The saved receipt is available. No retry or discard was sent. '+(mutation.action==='retry'?'Open its current details, or check the original extraction result.':'Open its current details before deliberately deciding whether to discard it again.');
            document.getElementById('ocr-retry-progress').textContent=pending?'The saved image is still being read.':'Saved state checked. Use the options above.';
            replay.hidden=pending||mutation.action!=='retry';
        }catch(e){mutationStatus.textContent='Receipt action remains unverified. '+e.message;}
        finally{mutationBusy=false;checkState.disabled=false;}
    });
    replay?.addEventListener('click',()=>{if(mutation?.action==='retry'&&!mutationBusy)sendMutation(mutation);});
    openState?.addEventListener('click',()=>{mutation=null;});
    document.querySelector('.ocr-workspace')?.addEventListener('click',event=>{
        const a=event.target.closest('a[href]');if(mutation&&a&&a!==openState&&a.target!=='_blank'){event.preventDefault();mutationStatus.textContent='Check the saved receipt state before continuing to another task.';}
    });
    window.addEventListener('beforeunload',event=>{if(mutation){event.preventDefault();event.returnValue='';}});
}());
