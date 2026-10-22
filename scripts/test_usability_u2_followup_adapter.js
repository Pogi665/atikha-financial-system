'use strict';
require('../assets/js/payment_presentation.js');
const assert=require('node:assert/strict'),P=globalThis.PaymentPresentation;
let checks=0;
function equal(actual,expected,label){assert.deepEqual(actual,expected,label);checks++;console.log('PASS: '+label);}
for(const [raw,expected] of [['0.00','\u20b10.00'],['0.01','\u20b10.01'],['1000.00','\u20b11,000.00'],['1234567.89','\u20b11,234,567.89'],['9999999999999.99','\u20b19,999,999,999,999.99'],['19999999999999.98','\u20b119,999,999,999,999.98']]){
    equal(P.coverageMoney(raw),expected,'Exact server amount '+raw);
}
for(const raw of [null,undefined,'',0,1,NaN,{},[],' ','0','1.0','1.000','00.00','01.00','-1.00','+1.00','1e3','1,000.00','NaN','Infinity','1000.00x','0.00\n','999999999999999.01','1000000000000000.00']){
    equal(P.coverageMoney(raw),null,'Reject authoritative '+JSON.stringify(raw));
}
const make=(book='GJ',kind='ordinary',status='Partially covered')=>({input:{source_book:book,transaction_kind:kind},coverage:{status,debit:{eligible:'1000.00',covered:'400.00'},credit:{eligible:'200.00',covered:'0.00'}}});
let r=make();equal(P.coverageText(r),'Partially covered — debit: \u20b1400.00 supported of \u20b11,000.00; credit: \u20b10.00 supported of \u20b1200.00','Preserve independent side values');
equal(P.coverageText(make('CDB')),'Partially covered — debit: \u20b1400.00 supported of \u20b11,000.00','Cash payment supports debit side');
equal(P.coverageText(make('CRB')),'Partially covered — credit: \u20b10.00 supported of \u20b1200.00','Cash receipt supports credit side');
r=make('CDB','ordinary','Fully covered');r.coverage.debit.covered='1000.00';equal(P.coverageText(r),'Fully covered — debit: \u20b11,000.00 supported of \u20b11,000.00','Full support preserves server status');
function transfer(){const v=make('GJ','transfer','Not applicable');for(const s of ['debit','credit'])v.coverage[s]={eligible:'0.00',covered:'0.00'};return v;}
const explanation='Not applicable — monetary supporting-document coverage has no eligible noncash line in this internal cash transfer.';
equal(P.coverageText(transfer()),explanation,'Reviewed zero-eligible transfer explains N/A');
for(const [key,value] of [['source_book','CDB'],['source_book','CRB'],['transaction_kind','ordinary']]){r=transfer();r.input[key]=value;equal(P.coverageText(r).includes('debit:')||P.coverageText(r).includes('credit:'),true,'No special explanation for '+key+' '+value);}
r=transfer();r.coverage.status='Fully covered';equal(P.coverageText(r).includes('debit:'),true,'Status disagreement preserves side detail');
for(const side of ['debit','credit']){
    r=transfer();r.coverage[side].eligible='0.01';equal(P.coverageText(r).includes('\u20b10.01'),true,'Nonzero '+side+' eligibility remains visible');
    r=transfer();r.coverage[side].covered='0.01';equal(P.coverageText(r).includes('\u20b10.01'),true,'Inconsistent '+side+' support remains visible');
    for(const field of ['covered','eligible']){r=transfer();r.coverage[side][field]='';const s=P.coverageText(r);equal(s.includes('amount unavailable')&&s.includes('debit:')&&s.includes('credit:'),true,'Invalid '+side+' '+field+' cannot become N/A or zero');}
}
equal(P.coverageText(null).includes('unavailable'),true,'Missing review is unavailable');
r=make();delete r.coverage;equal(P.coverageText(r).includes('unavailable'),true,'Missing coverage is unavailable');
r=make();r.coverage.status=null;equal(P.coverageText(r).startsWith('Coverage status unavailable'),true,'Missing status is unavailable');
r=transfer();const before=JSON.stringify(r);P.coverageText(r);equal(JSON.stringify(r),before,'Formatting leaves reviewed input and values untouched');
equal(P.cents(''),0n,'Editable parser retains its existing separate contract');
console.log('U2 follow-up adapter checks: '+checks);
