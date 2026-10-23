/* Presentation order only: exact selected report before submission/decision controls. */
(() => {
 const root=document.querySelector('.finance-reports');if(!root||location.search.includes('format=print'))return;
 const first=root.querySelector('.ui-report-tabs'),state=root.querySelector('#rb-state');
 const detail=root.querySelector('.ui-accounting-details');if(first&&state){state.after(first);first.after(detail);}
 const last=[...root.querySelectorAll('.rb-component')].at(-1);
 if(last){let anchor=last;root.querySelectorAll('.ui-report-decision').forEach(panel=>{anchor.after(panel);anchor=panel;});const period=root.querySelector('[aria-label="Month posting controls"]');if(period)anchor.after(period);}
})();
