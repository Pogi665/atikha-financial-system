(() => {
    'use strict';
    const disclosures=[...document.querySelectorAll('.tx-workspace details')];let beforePrint=[];
    for(const d of disclosures){const s=d.querySelector(':scope > summary');if(s){s.setAttribute('aria-expanded',String(d.open));d.addEventListener('toggle',()=>s.setAttribute('aria-expanded',String(d.open)));}}
    window.addEventListener('beforeprint',()=>{beforePrint=disclosures.map(d=>d.open);disclosures.forEach(d=>d.open=true);});
    window.addEventListener('afterprint',()=>disclosures.forEach((d,i)=>d.open=beforePrint[i]));
})();
