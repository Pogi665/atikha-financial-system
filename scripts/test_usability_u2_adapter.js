'use strict';
require('../assets/js/payment_presentation.js');
const assert=require('node:assert/strict'),P=globalThis.PaymentPresentation;
let checks=0;function check(ok,label){assert.ok(ok,label);checks++;}
const base={transaction_kind:'ordinary',cash_amount:'1000.00',cash_project_id:'',lines:[{client_id:'cost',account_id:'2',fund_project_id:'',debit_amount:'1000',credit_amount:''}],documents:[]};
function example(change,mode,label){const p=structuredClone(base);change(p);const before=JSON.stringify(p);check(P.mode(p)===mode,label);check(JSON.stringify(p)===before,label+' preserves raw payload');}
example(()=>{},'quick','Equal exact cents');
example(p=>{p.cash_amount=p.lines[0].debit_amount='';},'quick','New incomplete amounts');
example(p=>p.lines[0].credit_amount='0.00','quick','Explicit zero opposite side');
for(const value of ['-1','oops','0.001','1e2','1','-0.00'])example(p=>p.lines[0].credit_amount=value,'advanced','Opposite amount '+value);
for(const value of ['','900','-20','oops'])example(p=>p.lines[0].debit_amount=value,['-20','oops'].includes(value)?'advanced':'split','Loaded debit '+value);
example(p=>p.cash_amount='','split','Missing cash remains visible');
example(p=>p.lines[0].fund_project_id='7','split','Distinct actual projects');
example(p=>p.default_project_id='7','quick','Default context does not overwrite actual tags');
example(p=>p.lines=[],'split','Loaded absent lines are not manufactured');
example(p=>p.lines.push({...p.lines[0],client_id:'another'}),'split','Multiple stable lines');
example(p=>p.lines[0].unknown='keep','advanced','Unknown metadata stays detailed');
for(const index of [0,1])example(p=>{p.lines.push({...p.lines[0],client_id:'second'});p.lines[index].mystery='keep';},'advanced','CDB multi-line unknown metadata on line '+index);
example(p=>p.documents=[{allocations:[{client_id:'missing',amount:'1'}]}],'advanced','Unrepresented evidence link');
example(p=>p.documents=[{allocations:[{client_id:'cost',amount:'1000'}]}],'quick','Represented support retains identity');
// CRB mirrors the accounting sides, while preserving the same representation contract.
function receipt(change,mode,label){const p=structuredClone(base);p.lines[0].credit_amount=p.lines[0].debit_amount;p.lines[0].debit_amount='';change(p);const before=JSON.stringify(p);check(P.mode(p,'CRB')===mode,label);check(JSON.stringify(p)===before,label+' preserves raw payload');}
receipt(()=>{},'quick','Receipt exact cents');
receipt(p=>{p.cash_amount=p.lines[0].credit_amount='';},'quick','Receipt incomplete new amounts');
receipt(p=>p.lines[0].debit_amount='0.00','quick','Receipt zero opposite side');
for(const value of ['-1','bad','0.001','1e2','1','-0.00'])receipt(p=>p.lines[0].debit_amount=value,'advanced','Receipt opposite '+value);
for(const value of ['','900','-20','bad'])receipt(p=>p.lines[0].credit_amount=value,['-20','bad'].includes(value)?'advanced':'split','Receipt loaded credit '+value);
receipt(p=>p.cash_amount='','split','Receipt missing cash');
receipt(p=>p.lines[0].fund_project_id='7','split','Receipt distinct projects');
receipt(p=>p.lines.push({...p.lines[0],client_id:'second'}),'split','Receipt multiple allocations');
for(const index of [0,1])receipt(p=>{p.lines.push({...p.lines[0],client_id:'second'});p.lines[index].mystery='keep';},'advanced','CRB multi-line unknown metadata on line '+index);
receipt(p=>p.documents=[{allocations:[{client_id:'missing',amount:'1'}]}],'advanced','Receipt dangling support');
receipt(p=>p.lines[0].unknown='keep','advanced','Receipt unknown metadata');
for(const kind of ['ordinary','transfer']){const p={...structuredClone(base),transaction_kind:kind};const before=JSON.stringify(p);check(P.mode(p,'GJ')==='advanced','GJ '+kind+' always detailed');check(JSON.stringify(p)===before,'GJ '+kind+' unchanged');}
console.log('U2 adapter checks:',checks);
