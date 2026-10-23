/* Financial chart presentation only; canonical decimals remain in the accessible tables. */
(() => {
  const node = document.getElementById('ui-dashboard-data');if (!node) return;
  const data = JSON.parse(node.textContent);
  if (typeof Chart === 'undefined') {document.querySelectorAll('[data-chart-status]').forEach(e=>e.textContent='Chart unavailable. The recorded values are listed below.');return;}
  const canvas = document.getElementById('ui-income-chart');
  if (canvas && data.series.length) new Chart(canvas,{type:'bar',data:{labels:data.series.map(r=>r.month),datasets:[{label:'Income',data:data.series.map(r=>Number(r.income_decimal)),backgroundColor:'#227451'},{label:'Expenses',data:data.series.map(r=>Number(r.expenses_decimal)),backgroundColor:'#64748b'}]},options:{responsive:true,maintainAspectRatio:false,animation:matchMedia('(prefers-reduced-motion: reduce)').matches?false:undefined,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});
  const budget = document.getElementById('ui-budget-chart');
  if (budget && data.budget?.approved_total!==null) new Chart(budget,{type:'bar',data:{labels:['Selected Month'],datasets:[{label:'Approved Budget',data:[Number(data.budget.approved_total)],backgroundColor:'#164b3b'},{label:'Recognized Actual',data:[Number(data.budget.actual_total)],backgroundColor:'#799585'}]},options:{responsive:true,maintainAspectRatio:false,animation:matchMedia('(prefers-reduced-motion: reduce)').matches?false:undefined,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});
})();
