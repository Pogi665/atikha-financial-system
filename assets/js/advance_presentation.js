/* Pure version-3 view helpers. No posting, storage, generated lines or ledger math. */
((scope)=>{
    'use strict';
    const kinds=Object.freeze(['advance_release','advance_liquidation','advance_return']);
    const names=Object.freeze({advance_release:'advance release',advance_liquidation:'liquidation',advance_return:'cash return'});
    const positiveId=v=>Number.isSafeInteger(v)&&v>0;
    const decimal=v=>typeof v==='string'&&/^\d{1,13}\.\d{2}$/.test(v);
    function money(v){
        if(!decimal(v))throw new Error('The reviewed amount could not be verified. Refresh review.');
        const [whole,fraction]=v.split('.');return 'PHP '+whole.replace(/\B(?=(\d{3})+(?!\d))/g,',')+'.'+fraction;
    }
    function enabled(config){return Boolean(config.advance_presentation)&&!config.is_correction&&kinds.includes(config.workflow_kind);}
    function effect(review,kind){
        const e=review?.advance_effect,date=v=>{if(typeof v!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(v)||v<'1000-01-01'||v>'9998-12-31')return false;const d=new Date(v+'T00:00:00Z');return Number.isFinite(d.valueOf())&&d.toISOString().slice(0,10)===v;};
        if(!kinds.includes(kind)||!e||!date(e.accounting_date)||!date(e.balance_as_of)||e.accounting_date!==review.input?.entry_date)throw new Error('The advance balance projection could not be verified. Refresh review.');
        if(kind==='advance_release'?(e.before_outstanding!==null||e.after_outstanding!==null||!decimal(e.new_advance_outstanding)):(!decimal(e.before_outstanding)||!decimal(e.after_outstanding)||e.new_advance_outstanding!==null))throw new Error('The advance balance projection could not be verified. Refresh review.');
        return {accounting_date:e.accounting_date,balance_as_of:e.balance_as_of,before_outstanding:e.before_outstanding,after_outstanding:e.after_outstanding,new_advance_outstanding:e.new_advance_outstanding};
    }
    function sameTask(destination,current,task){
        const dest=new URL(destination,current),here=new URL(current);
        if(dest.origin!==here.origin||dest.pathname!==here.pathname)return false;
        const id=dest.searchParams.get('draft_id');
        if(id!==null)return /^[1-9]\d*$/.test(id)&&String(task.draft_id||'')===id;
        if(task.draft_id)return false;
        const kind=dest.searchParams.get('workflow_kind')||'advance_release',advance=dest.searchParams.get('advance_id')||'';
        return kind===task.workflow_kind&&advance===String(task.advance_id||'')&&[...dest.searchParams.keys()].every(k=>['workflow_kind','advance_id'].includes(k));
    }
    function proofKey(p){
        const amount=v=>/^\d{1,13}(\.\d{1,2})?$/.test(v||'')?String(v).split('.')[0].replace(/^0+(?=\d)/,'')+'.'+(String(v).split('.')[1]||'').padEnd(2,'0'):v;
        return JSON.stringify([p.entry_date,p.cash_account_id,amount(p.cash_amount),(p.documents||[]).map(d=>[d.receipt_id,d.purpose,d.reviewed,d.purpose==='amount'?[d.support_side,d.declared_amount,d.accepted_amount,d.exclusion_reason,d.allocations]:null]).sort((a,b)=>String(a[0]).localeCompare(String(b[0])))]);
    }
    function liquidationDetailed(p,accounts){
        const known=['client_id','account_id','fund_project_id','debit_amount','credit_amount'];
        const amount=v=>v===''||typeof v==='string'&&/^\d{1,13}(\.\d{1,2})?$/.test(v);
        return !Array.isArray(p.lines)||p.lines.some(l=>{
            if(!l||Object.keys(l).some(k=>!known.includes(k))||!amount(l.debit_amount)||!amount(l.credit_amount))return true;
            const a=accounts.find(a=>String(a.CategoryID)===l.account_id);
            if(!a)return Boolean(l.account_id);
            const nonzero=v=>v!==''&&!/^0+(\.0{1,2})?$/.test(v);
            return a.Account_Type==='Liability'?nonzero(l.debit_amount):nonzero(l.credit_amount);
        });
    }
    const api=Object.freeze({kinds,names,positiveId,decimal,money,enabled,effect,sameTask,proofKey,liquidationDetailed});
    if(typeof module!=='undefined'&&module.exports)module.exports=api;else scope.AdvancePresentation=api;
})(globalThis);
