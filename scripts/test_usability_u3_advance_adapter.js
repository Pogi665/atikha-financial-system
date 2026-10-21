'use strict';
const assert=require('node:assert/strict'),A=require('../assets/js/advance_presentation.js');let checks=0;
function check(label,fn){fn();checks++;console.log('PASS: '+label);}
const review={input:{entry_date:'2026-10-20'},advance_effect:{accounting_date:'2026-10-20',balance_as_of:'2026-10-21',before_outstanding:null,after_outstanding:null,new_advance_outstanding:'10000.00'}};
for(const kind of A.kinds){
    check(kind+' explicitly opts in',()=>assert.equal(A.enabled({advance_presentation:true,workflow_kind:kind}),true));
    check(kind+' cannot opt corrections in',()=>assert.equal(A.enabled({advance_presentation:true,workflow_kind:kind,is_correction:true}),false));
    check(kind+' does not opt in by book alone',()=>assert.equal(A.enabled({book:'CDB',workflow_kind:kind}),false));
}
for(const config of [{ordinary_workspace:true,book:'CDB'},{advance_presentation:true,workflow_kind:'mystery'},{advance_presentation:true,is_correction:true}])check('Unsupported/ordinary branch remains excluded '+JSON.stringify(config),()=>assert.equal(A.enabled(config),false));
for(const [v,want] of [['0.00','PHP 0.00'],['10000.00','PHP 10,000.00'],['9999999999999.99','PHP 9,999,999,999,999.99']])check('Exact string money '+v,()=>assert.equal(A.money(v),want));
for(const v of [null,100,'1','1.001','-1.00','1e3','NaN','99999999999999.99'])check('Reject malformed money '+String(v),()=>assert.throws(()=>A.money(v)));
check('Release effect is exact and nonmutating',()=>{const before=JSON.stringify(review);assert.deepEqual(A.effect(review,'advance_release'),review.advance_effect);assert.equal(JSON.stringify(review),before);});
for(const kind of ['advance_liquidation','advance_return'])check('Settlement effect '+kind,()=>{const r=structuredClone(review);Object.assign(r.advance_effect,{before_outstanding:'10000.00',after_outstanding:'2000.00',new_advance_outstanding:null});assert.equal(A.effect(r,kind).after_outstanding,'2000.00');});
for(const [field,value] of [['accounting_date','2026-02-30'],['accounting_date','2026-10-19'],['balance_as_of','2026-13-01'],['new_advance_outstanding',10000],['before_outstanding','0.00'],['new_advance_outstanding',null]])check('Reject invalid release projection '+field+' '+value,()=>{const r=structuredClone(review);r.advance_effect[field]=value;assert.throws(()=>A.effect(r,'advance_release'));});
check('Projection whitelists display fields',()=>{const r=structuredClone(review);r.advance_effect.internal_candidate_id=-9;assert.equal('internal_candidate_id' in A.effect(r,'advance_release'),false);});
for(const id of [0,-1,1.5,'1',null,Number.MAX_SAFE_INTEGER+1])check('Reject invalid recorded ID '+id,()=>assert.equal(A.positiveId(id),false));
check('Positive recorded IDs',()=>assert.equal(A.positiveId(123),true));
const here='http://127.0.0.1/cash_advance_entry.php?workflow_kind=advance_return&advance_id=5',task={workflow_kind:'advance_return',advance_id:'5'};
check('Same unsaved task ignores query order',()=>assert.equal(A.sameTask('?advance_id=5&workflow_kind=advance_return',here,task),true));
for(const url of ['?advance_id=6&workflow_kind=advance_return','?advance_id=5&workflow_kind=advance_liquidation','cash_advances.php','https://example.invalid/cash_advance_entry.php?advance_id=5&workflow_kind=advance_return'])check('Different task '+url,()=>assert.equal(A.sameTask(url,here,task),false));
check('Saved task requires same draft identity',()=>{assert.equal(A.sameTask('?draft_id=9',here,{...task,draft_id:9}),true);assert.equal(A.sameTask('?draft_id=10',here,{...task,draft_id:9}),false);assert.equal(A.sameTask(here,here,{...task,draft_id:9}),false);});
const proof={entry_date:'2026-10-20',cash_account_id:'1',cash_amount:'2000',description:'Old wording',documents:[{receipt_id:'2',purpose:'supporting',reviewed:true,allocations:[]}]};
check('Unrelated wording does not stale proof binding',()=>assert.equal(A.proofKey({...proof,description:'New wording'}),A.proofKey(proof)));
check('Equivalent amount formatting does not stale proof',()=>assert.equal(A.proofKey({...proof,cash_amount:'02000.00'}),A.proofKey(proof)));
for(const [field,value] of [['entry_date','2026-10-19'],['cash_account_id','2'],['cash_amount','1999.99']])check('Bound field stales proof '+field,()=>assert.notEqual(A.proofKey({...proof,[field]:value}),A.proofKey(proof)));
check('Reviewed document change stales proof',()=>{const next=structuredClone(proof);next.documents[0].reviewed=false;assert.notEqual(A.proofKey(next),A.proofKey(proof));});
console.log('Advance adapter checks: '+checks);

const accounts=[{CategoryID:1,Account_Type:'Expense'},{CategoryID:2,Account_Type:'Liability'}];
const cost={client_id:'a',account_id:'1',fund_project_id:'',debit_amount:'4500.00',credit_amount:''};
check('Focused liquidation retains representable costs',()=>assert.equal(A.liquidationDetailed({lines:[cost]},accounts),false));
for(const line of [{...cost,debit_amount:'bad'},{...cost,credit_amount:'1.00'},{...cost,mystery:'preserve'},{...cost,account_id:'9'}])check('Detailed liquidation preserves unsupported values '+JSON.stringify(line),()=>{const p={lines:[cost,line]},before=JSON.stringify(p);assert.equal(A.liquidationDetailed(p,accounts),true);assert.equal(JSON.stringify(p),before)});
check('Liability debit cannot be hidden',()=>assert.equal(A.liquidationDetailed({lines:[{...cost,account_id:'2'}]},accounts),true));
console.log('Final advance adapter checks: '+checks);
