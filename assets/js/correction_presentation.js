/* Version 4 presentation only. Writers, canonical requests and eligibility stay server-owned. */
(function(scope){
    'use strict';
    const positiveId=v=>Number.isSafeInteger(v)&&v>0;
    function enabled(c){return c.is_correction===true&&c.correction_presentation===true;}
    function freeze(request){const copy=structuredClone(request);function lock(v){if(v&&typeof v==='object'){Object.values(v).forEach(lock);Object.freeze(v);}return v;}return lock(copy);}
    function result(r,c){
        const bad=()=>{throw Object.assign(new Error('The correction bundle identity could not be verified. Check the same request.'),{uncertain:true});};
        if(!r||!['id','correction_id','target_journal_id','reversal_journal_id'].every(k=>positiveId(r[k]))||r.target_journal_id!==Number(c.target_journal_id)||typeof r.duplicate!=='boolean')bad();
        const replacing=c.mode==='reverse_replace';
        if(replacing?!positiveId(r.replacement_journal_id):r.replacement_journal_id!==null)bad();
        if(r.id!==(replacing?r.replacement_journal_id:r.reversal_journal_id)||r.reversal_journal_id===r.target_journal_id||r.replacement_journal_id===r.target_journal_id||r.replacement_journal_id===r.reversal_journal_id)bad();
        const op=c.target?.operation;
        if(op){
            if(!positiveId(r.original_advance_id)||r.original_advance_id!==Number(op.advance_id)||!positiveId(r.advance_id))bad();
            if(replacing&&op.operation_kind==='release'){
                if(!positiveId(r.replacement_advance_id)||r.replacement_advance_id===r.original_advance_id||r.advance_id!==r.replacement_advance_id)bad();
            }else if(r.replacement_advance_id!==null||r.advance_id!==r.original_advance_id)bad();
        }
        return r;
    }
    function resumed(d,c){
        const bundle=d.posted_bundle,chain=d.chain;
        if(!bundle?.reversal||!Array.isArray(chain))throw new Error('Posted correction details could not be verified. Retry the saved draft read.');
        const rows=chain.filter(row=>row.target_journal_id===Number(c.target_journal_id)&&row.reversal_journal_id===bundle.reversal.journal_id&&row.mode===c.mode);
        if(rows.length!==1)throw new Error('Posted correction chain could not be verified.');
        const row=rows[0];
        const r={...(d.posted_result||{}),id:d.posted_journal_id,correction_id:row.id,target_journal_id:row.target_journal_id,reversal_journal_id:row.reversal_journal_id,replacement_journal_id:row.replacement_journal_id,duplicate:true};
        if((c.mode==='reverse_replace'&&bundle.replacement?.journal_id!==row.replacement_journal_id)||(c.mode==='reverse_only'&&bundle.replacement))throw new Error('Posted correction bundle is inconsistent.');
        return result(r,c);
    }
    function sameTask(href,current,c){
        const a=new URL(href,current),b=new URL(current);if(a.origin!==b.origin||a.pathname!==b.pathname)return false;
        if(c.draft_id)return a.searchParams.get('draft_id')===String(c.draft_id);
        return !a.searchParams.has('draft_id')&&a.searchParams.get('journal_id')===String(c.target_journal_id)&&(a.searchParams.get('mode')||'reverse_replace')===c.mode&&(a.searchParams.get('book')||c.book)===c.book;
    }
    const api=Object.freeze({positiveId,enabled,freeze,result,resumed,sameTask});
    if(typeof module!=='undefined'&&module.exports)module.exports=api;else scope.CorrectionPresentation=api;
})(globalThis);
