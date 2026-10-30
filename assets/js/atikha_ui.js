/* Shared interaction presentation. Never saves, reviews, posts or grants authorization. */
(() => {
  'use strict';
  const UI=window.AtikhaUI=window.AtikhaUI||{};
  UI.date=value=>{const match=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value||''));if(!match)return 'Not recorded';const d=new Date(`${value}T00:00:00Z`);return Number.isNaN(d.valueOf())?'Not recorded':new Intl.DateTimeFormat('en-PH',{month:'short',day:'numeric',year:'numeric',timeZone:'UTC'}).format(d);};
  UI.month=value=>/^\d{4}-(0[1-9]|1[0-2])$/.test(String(value||''))?new Intl.DateTimeFormat('en-PH',{month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(value+'-01T00:00:00Z')):'Choose a month';
  UI.datetime=(value,basis='utc')=>{if(!value)return 'Not recorded';const raw=String(value).replace(/ (?:UTC|Asia\/Manila)$/,'');const d=new Date(raw.replace(' ','T')+(basis==='utc'&&!/[zZ]|[+-]\d\d:\d\d$/.test(raw)?'Z':basis==='local'?'+08:00':''));return Number.isNaN(d.valueOf())?'Not recorded':new Intl.DateTimeFormat('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit',timeZone:'Asia/Manila'}).format(d);};
  let pending=null;
  UI.confirm=({title='Confirm action',message='',confirm='Continue',destructive=false}={})=>{
    if(pending)return Promise.resolve(false);
    return new Promise(resolve=>{
      const opener=document.activeElement,dialog=document.createElement('dialog');dialog.className='ui-confirm';dialog.setAttribute('aria-labelledby','ui-confirm-title');dialog.setAttribute('aria-describedby','ui-confirm-message');
      const heading=document.createElement('h2');heading.id='ui-confirm-title';heading.textContent=title;
      const text=document.createElement('p');text.id='ui-confirm-message';text.textContent=message;
      const actions=document.createElement('div');actions.className='ui-actions';const cancel=document.createElement('button'),ok=document.createElement('button');cancel.type=ok.type='button';cancel.className='ui-button';cancel.textContent='Cancel';ok.className='ui-button '+(destructive?'ui-button-danger':'ui-button-primary');ok.textContent=confirm;actions.append(cancel,ok);dialog.append(heading,text,actions);document.body.append(dialog);
      const previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';let done=false;
      const finish=result=>{if(done)return;done=true;pending=null;dialog.close();dialog.remove();document.body.style.overflow=previousOverflow;if(opener?.isConnected)opener.focus();resolve(result);};pending=finish;
      cancel.addEventListener('click',()=>finish(false));ok.addEventListener('click',()=>finish(true));dialog.addEventListener('cancel',e=>{e.preventDefault();finish(false);e.stopPropagation();});dialog.addEventListener('keydown',e=>{e.stopPropagation();if(e.key==='Tab'&&((e.shiftKey&&document.activeElement===cancel)||(!e.shiftKey&&document.activeElement===ok))){e.preventDefault();(e.shiftKey?ok:cancel).focus();}});dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)finish(false);}});
      dialog.showModal();cancel.focus();
    });
  };
})();
(() => {
  'use strict';
  const sidebar = document.getElementById('ui-sidebar');
  const size = document.getElementById('ui-sidebar-size');
  const desktop = window.matchMedia('(min-width:821px)');
  const groups = [...document.querySelectorAll('[data-ui-nav-group]')];
  const compact = () => desktop.matches && document.body.classList.contains('ui-sidebar-collapsed');
  const panelFor = button => document.getElementById(button.getAttribute('aria-controls'));
  const closeGroups = () => groups.forEach(button => {
    button.setAttribute('aria-expanded', 'false');panelFor(button).hidden = true;
  });
  const placePanel = button => {
    if (!compact()) return;
    const panel = panelFor(button), rect = button.getBoundingClientRect();
    panel.style.top = Math.max(8, Math.min(rect.top, innerHeight - panel.offsetHeight - 8)) + 'px';
  };
  groups.forEach(button => {
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      closeGroups();button.setAttribute('aria-expanded', String(open));panelFor(button).hidden = !open;
      if (open) placePanel(button);
    });
    button.addEventListener('keydown', event => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();if (button.getAttribute('aria-expanded') !== 'true') button.click();
        panelFor(button).querySelector('a')?.focus();
      }
    });
  });
  const tooltip = document.createElement('div');
  tooltip.id = 'ui-nav-tooltip';tooltip.className = 'ui-nav-tooltip';tooltip.role = 'tooltip';tooltip.hidden = true;
  document.body.append(tooltip);
  let tipTarget = null;
  const hideTip = () => {tipTarget?.removeAttribute('aria-describedby');tipTarget = null;tooltip.hidden = true;};
  sidebar?.querySelectorAll('[data-ui-label]').forEach(control => {
    const showTip = () => {
      hideTip();if (!compact() || control.closest('.ui-nav-children')) return;
      tipTarget = control;tooltip.textContent = control.dataset.uiLabel;tooltip.hidden = false;
      const rect = control.getBoundingClientRect();tooltip.style.top = Math.min(rect.top, innerHeight - tooltip.offsetHeight - 8) + 'px';
      control.setAttribute('aria-describedby', tooltip.id);
    };
    control.addEventListener('mouseenter', showTip);control.addEventListener('focus', showTip);
    control.addEventListener('mouseleave', hideTip);control.addEventListener('blur', hideTip);control.addEventListener('click', hideTip);
  });
  const syncSize = () => {
    const collapsed = document.body.classList.contains('ui-sidebar-collapsed');
    size?.setAttribute('aria-expanded', String(!collapsed));
    size?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
  };
  try {document.body.classList.toggle('ui-sidebar-collapsed', localStorage.getItem('atikha.sidebar.compact') === 'true');} catch (_) { /* Storage is optional. */ }
  syncSize();if (compact()) closeGroups();
  size?.addEventListener('click', () => {
    document.body.classList.toggle('ui-sidebar-collapsed');closeGroups();hideTip();syncSize();
    try {localStorage.setItem('atikha.sidebar.compact', String(document.body.classList.contains('ui-sidebar-collapsed')));} catch (_) { /* Keep this session usable. */ }
  });
  const toggle = document.getElementById('ui-nav-toggle');
  const backdrop = document.createElement('button');
  backdrop.type = 'button';backdrop.className = 'ui-nav-backdrop';backdrop.setAttribute('aria-label', 'Close navigation');backdrop.tabIndex = -1;backdrop.hidden = true;
  document.body.append(backdrop);
  const shell = document.querySelector('.ui-shell');
  const syncMobile = () => {
    const open = !desktop.matches && document.body.classList.contains('ui-nav-open');
    if (sidebar) sidebar.inert = !desktop.matches && !open;
    if (shell) shell.inert = open;
    backdrop.hidden = !open;
    toggle?.setAttribute('aria-expanded', String(open));
  };
  const closeMobile = () => {document.body.classList.remove('ui-nav-open');syncMobile();toggle?.focus();};
  toggle?.addEventListener('click', () => {
    document.body.classList.toggle('ui-nav-open');syncMobile();
    if (document.body.classList.contains('ui-nav-open')) sidebar?.querySelector('a,button')?.focus();
  });
  backdrop.addEventListener('click', closeMobile);
  desktop.addEventListener('change', () => {
    document.body.classList.remove('ui-nav-open');closeGroups();hideTip();syncMobile();
  });
  syncMobile();
  window.addEventListener('resize', () => {hideTip();groups.filter(b => b.getAttribute('aria-expanded') === 'true').forEach(placePanel);});
  sidebar?.querySelector('nav')?.addEventListener('scroll', () => {
    if (compact()) {hideTip();groups.filter(b => b.getAttribute('aria-expanded') === 'true').forEach(placePanel);}
  });
  document.addEventListener('click', event => {if (compact() && !sidebar?.contains(event.target)) {closeGroups();hideTip();}});
  document.addEventListener('focusin', event => {
    if (!compact()) return;
    groups.forEach(button => {if (!button.closest('.ui-nav-group').contains(event.target)) {button.setAttribute('aria-expanded', 'false');panelFor(button).hidden = true;}});
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      hideTip();const open = groups.find(button => button.getAttribute('aria-expanded') === 'true' && button.closest('.ui-nav-group').contains(event.target));
      if (open) {event.preventDefault();closeGroups();open.focus();}
    }
    if(event.key==='Escape')document.querySelectorAll('.ui-help[open]').forEach(help=>{help.open=false;help.querySelector('summary').focus();});
    if (event.key === 'Escape' && document.body.classList.contains('ui-nav-open')) {
      closeMobile();
    }
    if (event.key === 'Tab' && !desktop.matches && document.body.classList.contains('ui-nav-open')) {
      const items = [...sidebar.querySelectorAll('a[href],button')].filter(e => e.getClientRects().length && !e.disabled);
      const first = items[0], last = items[items.length-1];
      if (event.shiftKey && event.target === first) {event.preventDefault();last?.focus();}
      else if (!event.shiftKey && event.target === last) {event.preventDefault();first?.focus();}
    }
  });
  document.addEventListener('invalid', event => {
    let parent = event.target.parentElement;
    while (parent) { if (parent.tagName === 'DETAILS') parent.open = true;if(parent.hasAttribute('data-ui-panel')&&parent.hidden)document.querySelector('[data-ui-tab="'+parent.id+'"]')?.click(); parent = parent.parentElement; }
  }, true);
  document.addEventListener('click', event => {
    const tab = event.target.closest('[data-ui-tab]');
    if (!tab) return;
    const group = tab.closest('[data-ui-tabs]');if (!group) return;
    group.querySelectorAll('[data-ui-tab]').forEach(button => {button.setAttribute('aria-selected', String(button === tab));button.tabIndex=button===tab?0:-1;});
    group.querySelectorAll('[data-ui-panel]').forEach(panel => {panel.hidden = panel.id !== tab.dataset.uiTab;});
  });
  document.querySelectorAll('[data-ui-tabs]').forEach(group => {
    group.addEventListener('keydown', event => {
      if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key) || !event.target.matches('[data-ui-tab]')) return;
      const tabs = [...group.querySelectorAll('[data-ui-tab]')];let at = tabs.indexOf(event.target);
      at = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length-1 : (at + (event.key==='ArrowRight'?1:-1) + tabs.length)%tabs.length;
      event.preventDefault();tabs[at].click();tabs[at].focus();
    });
  });
  const tableRegions=document.querySelectorAll('.aw-table-wrap,.gl-table-wrap,.fr-table-wrap,.overflow-x-auto,.accounts-table-wrap,.users-table-wrap');
  tableRegions.forEach(region=>{
    region.tabIndex=0;
    if(!region.hasAttribute('role'))region.setAttribute('role','region');
    if(!region.hasAttribute('aria-label'))region.setAttribute('aria-label',region.querySelector('caption')?.textContent||'Financial Table - scroll horizontally for more columns');
  });
})();

/* Explicit operational tables stack into labelled records on narrow screens. */
(()=>{const label=()=>document.querySelectorAll('table.ui-operational-table').forEach(table=>{const headers=Array.from(table.querySelectorAll('thead th'),th=>th.textContent.trim());for(const body of table.tBodies)for(const row of body.rows)for(let i=0;i<row.cells.length;i++)if(row.cells[i].colSpan===1)row.cells[i].dataset.label=headers[i]||'';});if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',label);else label();})();

/* Keep export menus inside the viewport, including beside the compact rail. */
(()=>{
  const menus=[...document.querySelectorAll('.ui-export-menu')];
  const place=menu=>{if(!menu.open)return;const panel=menu.querySelector(':scope > div'),anchor=menu.querySelector('summary').getBoundingClientRect();if(!panel)return;
    panel.style.maxHeight=Math.max(120,innerHeight-24)+'px';
    const width=panel.getBoundingClientRect().width,height=panel.getBoundingClientRect().height;
    panel.style.left=Math.max(12,Math.min(anchor.left,innerWidth-width-12))+'px';
    panel.style.top=Math.max(12,anchor.bottom+8+height<=innerHeight-12?anchor.bottom+8:anchor.top-height-8)+'px';
  };
  menus.forEach(menu=>menu.addEventListener('toggle',()=>{if(menu.open){menus.forEach(other=>{if(other!==menu)other.open=false;});place(menu);}}));
  document.addEventListener('click',event=>menus.forEach(menu=>{if(!menu.contains(event.target))menu.open=false;}));
  document.addEventListener('keydown',event=>{if(event.key==='Escape')menus.forEach(menu=>{if(menu.open){menu.open=false;menu.querySelector('summary').focus();event.preventDefault();}});});
  addEventListener('resize',()=>menus.forEach(place));document.addEventListener('scroll',()=>menus.forEach(place),true);
})();

/* Print validated read-only server output without navigating away from Reports. */
(()=>{
  let active=null;
  const status=document.createElement('p');status.className='ui-print-status ui-no-print';status.role='status';status.hidden=true;document.body.append(status);
  const finish=()=>{if(!active)return;const {surface,button}=active;active=null;surface.remove();document.body.classList.remove('ui-report-printing');button.disabled=false;button.focus();status.textContent='Print dialog closed. You are still on the report.';};
  addEventListener('afterprint',finish);
  document.addEventListener('click',async event=>{
    const button=event.target.closest('[data-report-print-source]');if(!button||active)return;
    const url=new URL(button.dataset.reportPrintSource,location.href);
    if(url.origin!==location.origin||!['report_bundles.php','financial_statements.php','financial_export.php'].includes(url.pathname.split('/').pop()))return;
    event.preventDefault();button.disabled=true;status.hidden=false;status.textContent='Preparing the selected report for printing…';
    try{
      if(button.hasAttribute('data-print-package')&&document.querySelector('[data-print-appendix]')?.checked)url.searchParams.set('appendix','1');
      const response=await fetch(url,{credentials:'same-origin',headers:{'Accept':'text/html'}});
      if(!response.ok)throw new Error('The report could not be prepared. Refresh the report and try again.');
      const doc=new DOMParser().parseFromString(await response.text(),'text/html'),surface=document.createElement('section');surface.id='ui-report-print-surface';surface.setAttribute('aria-label','Selected report print copy');
      const exported=doc.querySelector('.fx-page'),reports=doc.querySelector('.finance-reports');
      if(exported)surface.append(exported.cloneNode(true));
      else if(reports){for(const selector of ['.fr-heading','#rb-state','.rb-component','.rb-print-footer'])for(const item of reports.querySelectorAll(selector))surface.append(item.cloneNode(true));
        if(!surface.querySelector('.rb-component')){const sheet=reports.querySelector('.fr-sheet');if(sheet)surface.append(sheet.cloneNode(true));}
        if(url.searchParams.get('appendix')==='1'){const appendix=reports.querySelector('[data-report-appendix]');if(appendix){const copy=appendix.cloneNode(true);copy.open=true;surface.append(copy);}}
      }
      if(!surface.querySelector('table'))throw new Error('The report is not available for printing. Check the report period and setup.');
      surface.querySelectorAll('script,button,form,nav,.ui-export-menu,.fr-no-print,.ui-no-print').forEach(item=>item.remove());surface.querySelectorAll('[id]').forEach(item=>item.removeAttribute('id'));
      document.body.append(surface);active={surface,button};document.body.classList.add('ui-report-printing');status.textContent='Print preview is ready.';
      await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
      window.print();
      // Desktop print() blocks until dismissal. afterprint handles browsers with async dialogs.
      if(active&&!matchMedia('print').matches)setTimeout(finish,250);
    }catch(error){button.disabled=false;status.textContent=error.message||'Printing is unavailable. Your report remains unchanged.';finish();}
  });
})();
