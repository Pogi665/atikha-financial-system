/* Shared interaction presentation. Never saves, reviews, posts or grants authorization. */
(() => {
  'use strict';
  document.querySelectorAll('[data-ui-nav-group]').forEach(button => {
    button.addEventListener('click', () => {
      const panel = document.getElementById(button.getAttribute('aria-controls'));
      const open = button.getAttribute('aria-expanded') !== 'true';
      document.querySelectorAll('[data-ui-nav-group]').forEach(other => {
        other.setAttribute('aria-expanded', 'false');
        document.getElementById(other.getAttribute('aria-controls')).hidden = true;
      });
      button.setAttribute('aria-expanded', String(open)); panel.hidden = !open;
    });
  });
  const toggle = document.getElementById('ui-nav-toggle');
  toggle?.addEventListener('click', () => {
    const open = document.body.classList.toggle('ui-nav-open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('keydown', event => {
    if(event.key==='Escape')document.querySelectorAll('.ui-help[open]').forEach(help=>{help.open=false;help.querySelector('summary').focus();});
    if (event.key === 'Escape' && document.body.classList.contains('ui-nav-open')) {
      document.body.classList.remove('ui-nav-open');toggle?.setAttribute('aria-expanded', 'false');toggle?.focus();
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
