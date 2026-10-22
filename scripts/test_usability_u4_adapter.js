'use strict';
const assert=require('node:assert/strict'),C=require('../assets/js/correction_presentation.js');let checks=0;
function check(label,fn){fn();checks++;console.log('PASS: '+label);}
const ordinary={is_correction:true,correction_presentation:true,mode:'reverse_replace',target_journal_id:8,target:{operation:null},book:'CDB'};
const bundle={id:11,correction_id:3,target_journal_id:8,reversal_journal_id:10,replacement_journal_id:11,duplicate:false};
check('Correction opt-in excludes ordinary/advance',()=>{assert(C.enabled(ordinary));assert(!C.enabled({ordinary_workspace:true}));assert(!C.enabled({workflow_kind:'advance_release'}));});
check('Complete replacement bundle accepted',()=>assert.equal(C.result(bundle,ordinary),bundle));
for(const key of ['id','correction_id','target_journal_id','reversal_journal_id','replacement_journal_id'])for(const value of [null,0,'11',-1])check('Reject invalid '+key+' '+value,()=>assert.throws(()=>C.result({...bundle,[key]:value},ordinary)));
check('Reject wrong original',()=>assert.throws(()=>C.result({...bundle,target_journal_id:9},ordinary)));
check('Reject primary journal not replacement',()=>assert.throws(()=>C.result({...bundle,id:10},ordinary)));
check('Reject duplicate journal identities',()=>assert.throws(()=>C.result({...bundle,id:10,replacement_journal_id:10},ordinary)));
const reverse={...ordinary,mode:'reverse_only'},reversed={...bundle,id:10,replacement_journal_id:null};
check('Reverse-only bundle accepted without replacement',()=>C.result(reversed,reverse));
check('Reverse-only rejects fabricated replacement',()=>assert.throws(()=>C.result(bundle,reverse)));
const release={...ordinary,target:{operation:{advance_id:4,operation_kind:'release'}}};
const advanced={...bundle,original_advance_id:4,replacement_advance_id:5,advance_id:5};
check('Release preserves original and new advance identities',()=>C.result(advanced,release));
check('Release cannot reuse original identity as replacement',()=>assert.throws(()=>C.result({...advanced,advance_id:4,replacement_advance_id:4},release)));
for(const kind of ['liquidation','return']){
 const config={...release,target:{operation:{advance_id:4,operation_kind:kind}}};
 check(kind+' retains original advance',()=>C.result({...advanced,advance_id:4,replacement_advance_id:null},config));
 check(kind+' rejects another advance',()=>assert.throws(()=>C.result(advanced,config)));
}
check('Advance reversal-only retains original even without advanceConfig',()=>C.result({...reversed,advance_id:4,original_advance_id:4,replacement_advance_id:null},{...release,mode:'reverse_only'}));
const posted={posted_journal_id:11,posted_result:{},chain:[{...bundle,id:3,mode:'reverse_replace'}],posted_bundle:{reversal:{journal_id:10},replacement:{journal_id:11}}};
check('Posted reload derives bundle from public chain',()=>assert.equal(C.resumed(posted,ordinary).correction_id,3));
check('Posted reload rejects missing replacement',()=>assert.throws(()=>C.resumed({...posted,posted_bundle:{reversal:{journal_id:10}}},ordinary)));
check('Posted reload rejects ambiguous chain',()=>assert.throws(()=>C.resumed({...posted,chain:[...posted.chain,...posted.chain]},ordinary)));
check('Frozen request is independent and recursively immutable',()=>{const original={payload:{replacement:{documents:[{receipt_id:'6'}]}},submission_key:'original'};const frozen=C.freeze(original);original.payload.replacement.documents[0].receipt_id='9';assert.equal(frozen.payload.replacement.documents[0].receipt_id,'6');assert.throws(()=>{frozen.payload.replacement.documents[0].receipt_id='8';});});
const url='http://127.0.0.1/journal_correction.php?draft_id=20';
check('Same saved correction is a no-op link',()=>assert(C.sameTask('?draft_id=20',url,{...ordinary,draft_id:20})));
check('Another correction mode is a separate task',()=>assert(!C.sameTask('?journal_id=8&mode=reverse_only',url,{...ordinary,draft_id:20})));
check('Another unsaved target is separate',()=>assert(!C.sameTask('?journal_id=9',url,ordinary)));
console.log('U4 correction adapter: '+checks+' assertions passed.');
