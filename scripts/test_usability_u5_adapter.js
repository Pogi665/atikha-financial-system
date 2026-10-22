'use strict';
require('../assets/js/payment_presentation.js');
const assert = require('node:assert/strict');
let checks=0;
for(const [input,expected] of [['5,000','5000'],['1,000.01','1000.01'],['1,000,000.99','1000000.99'],['9,999,999,999,999.99','9999999999999.99'],['0.01','0.01'],['',''],['50,00','50,00'],['1,00,000','1,00,000'],['0,001','0,001'],['1,000.001','1,000.001'],['10,000,000,000,000.00','10,000,000,000,000.00'],['-1,000','-1,000'],['1e3','1e3'],[' 1,000',' 1,000'],[null,null],[undefined,undefined]]){
    assert.equal(PaymentPresentation.editableAmount(input),expected);checks++;
}
assert.equal(PaymentPresentation.cents('1,000'),null);checks++;
assert.equal(PaymentPresentation.coverageMoney('1,000.00'),null);checks++;
assert.equal(PaymentPresentation.coverageMoney(''),null);checks++;
assert.equal(PaymentPresentation.coverageMoney('1000.00'),'\u20b11,000.00');checks++;
console.log('PASS: '+checks+' U5 editable amount assertions; strict coverage contract retained');
