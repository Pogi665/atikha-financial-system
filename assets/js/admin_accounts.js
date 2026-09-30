(function () {
    'use strict';

    var page = document.querySelector('.accounts-page');
    if (!page) return;

    var activeOverlay = null;
    var activeOpener = null;
    var activeClose = null;
    var statusForm = null;

    function focusables(root) {
        return Array.from(root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])')).filter(function (element) {
            return !element.closest('[hidden]');
        });
    }

    function finishOverlay(overlay, opener) {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        if (activeOverlay === overlay) {
            activeOverlay = null;
            activeClose = null;
            activeOpener = null;
            document.body.classList.remove('overflow-hidden');
        }
        if (opener && typeof opener.focus === 'function') opener.focus();
    }

    function openOverlay(overlay, opener, onClose) {
        if (!overlay) return;
        if (activeClose) activeClose(true);
        activeOverlay = overlay;
        activeOpener = opener || document.activeElement;
        activeClose = onClose;
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
        var summary = overlay.querySelector('[data-form-summary]:not(.hidden)');
        var items = focusables(overlay);
        if (summary) summary.focus();
        else if (items.length) items[0].focus();
    }

    function closeOverlay(force) {
        if (activeClose) activeClose(Boolean(force));
    }

    function wireTrap(overlay) {
        overlay.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || activeOverlay !== overlay) return;
            var items = focusables(overlay);
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) closeOverlay(false);
        });
    }

    function cleanModalUrl() {
        var url = new URL(window.location.href);
        url.searchParams.delete('new');
        url.searchParams.delete('edit');
        url.hash = '';
        window.history.replaceState({}, '', url.toString());
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeOverlay) closeOverlay(false);
    });

    var accountModal = document.getElementById('account-modal');
    var accountForm = document.querySelector('[data-account-form]');
    if (accountModal && accountForm) {
        var initialFormState = new FormData(accountForm);
        function isDirty() {
            var current = new FormData(accountForm);
            for (var entry of current.entries()) {
                if (entry[0] === 'csrf_token') continue;
                if (entry[1] !== initialFormState.get(entry[0])) return true;
            }
            return false;
        }
        function closeAccount(force) {
            if (!force && isDirty() && !window.confirm('Discard the unsaved account changes?')) return;
            cleanModalUrl();
            finishOverlay(accountModal, activeOpener);
            accountForm.reset();
            initialFormState = new FormData(accountForm);
        }
        wireTrap(accountModal);
        document.querySelectorAll('[data-close-account-modal]').forEach(function (button) {
            button.addEventListener('click', function () { closeOverlay(false); });
        });
        accountForm.addEventListener('submit', function () { accountForm.dataset.dirty = 'false'; });
        if (accountModal.dataset.openModal === 'account') {
            var opener = document.getElementById('add-account-button');
            var editId = new URL(window.location.href).searchParams.get('edit');
            if (editId) opener = document.querySelector('[data-modal-opener="edit"][href*="edit=' + editId + '"]') || opener;
            openOverlay(accountModal, opener, closeAccount);
        }
    }

    var storageKey = 'accounts-expanded:' + (page.dataset.filterSignature || 'default');
    var groups = Array.from(document.querySelectorAll('[data-account-group]'));
    var toggles = Array.from(document.querySelectorAll('[data-group-toggle]'));
    var storageAvailable = true;
    var storedState = null;
    try {
        var stored = window.sessionStorage.getItem(storageKey);
        if (stored) storedState = JSON.parse(stored);
    } catch (error) {
        storageAvailable = false;
    }

    function saveGroupState() {
        if (!storageAvailable) return;
        var state = {};
        groups.forEach(function (group) { state[group.dataset.accountGroup] = !group.hidden; });
        try { window.sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch (error) { storageAvailable = false; }
    }

    function clearGroupStates() {
        if (!storageAvailable) return;
        try {
            var keys = [];
            for (var index = 0; index < window.sessionStorage.length; index++) {
                var key = window.sessionStorage.key(index);
                if (key && key.indexOf('accounts-expanded:') === 0) keys.push(key);
            }
            keys.forEach(function (key) { window.sessionStorage.removeItem(key); });
        } catch (error) { storageAvailable = false; }
    }

    function setGroup(groupType, expanded) {
        var group = document.querySelector('[data-account-group="' + groupType + '"]');
        var toggle = document.querySelector('[data-group-toggle="' + groupType + '"]');
        if (!group || !toggle) return;
        group.hidden = !expanded;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    groups.forEach(function (group) {
        var expanded = storedState && typeof storedState[group.dataset.accountGroup] === 'boolean'
            ? storedState[group.dataset.accountGroup] : true;
        setGroup(group.dataset.accountGroup, expanded);
    });
    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var groupType = toggle.dataset.groupToggle;
            var group = document.querySelector('[data-account-group="' + groupType + '"]');
            setGroup(groupType, group.hidden);
            saveGroupState();
        });
        toggle.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle.click(); }
        });
    });
    var filterForm = document.querySelector('.accounts-filter-toolbar');
    if (filterForm) filterForm.addEventListener('submit', clearGroupStates);
    var resetFilters = document.querySelector('[data-reset-filters]');
    if (resetFilters) resetFilters.addEventListener('click', clearGroupStates);

    var statusModal = document.getElementById('status-modal');
    var statusDescription = document.getElementById('status-modal-description');
    var statusConfirm = document.getElementById('status-confirm');
    if (statusModal && statusConfirm) {
        wireTrap(statusModal);
        document.querySelectorAll('[data-status-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                statusForm = form;
                closeMenu(form.closest('.account-action-menu'), false);
                var name = form.dataset.accountName || 'this account';
                var disabling = form.dataset.statusAction === 'disable';
                statusDescription.textContent = disabling
                    ? 'Disable ' + name + '? It will be removed from future new-entry choices, but historical records will be preserved.'
                    : 'Enable ' + name + '? It will become available for future new entries again; historical records are unchanged.';
                openOverlay(statusModal, event.submitter || form.querySelector('button'), function () {
                    finishOverlay(statusModal, activeOpener);
                    statusForm = null;
                });
            });
        });
        document.querySelectorAll('[data-close-status-modal]').forEach(function (button) {
            button.addEventListener('click', function () { closeOverlay(false); });
        });
        statusConfirm.addEventListener('click', function () { if (statusForm) statusForm.submit(); });
    }

    var menuHomes = new WeakMap();
    var buttonMenus = new WeakMap();
    var openMenu = null;

    function closeMenu(menu, restoreFocus) {
        if (!menu) return;
        var home = menuHomes.get(menu);
        menu.classList.add('hidden');
        menu.classList.remove('account-action-menu-floating');
        menu.style.left = '';
        menu.style.top = '';
        menu.style.width = '';
        if (home && home.parent && menu.parentElement !== home.parent) home.parent.insertBefore(menu, home.nextSibling);
        var menuButton = home && home.parent ? home.parent.querySelector('.account-action-menu-button') : null;
        if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
        if (restoreFocus && menuButton) menuButton.focus();
        if (openMenu === menu) openMenu = null;
    }

    function positionMenu(menu, button) {
        var rect = button.getBoundingClientRect();
        var menuRect = menu.getBoundingClientRect();
        var margin = 8;
        var left = Math.min(Math.max(margin, rect.right - menuRect.width), window.innerWidth - menuRect.width - margin);
        var top = rect.bottom + margin;
        if (top + menuRect.height > window.innerHeight - margin && rect.top - menuRect.height - margin >= margin) top = rect.top - menuRect.height - margin;
        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
    }

    function openMenuFor(button, menu) {
        if (openMenu && openMenu !== menu) closeMenu(openMenu, false);
        if (!menuHomes.has(menu)) menuHomes.set(menu, { parent: menu.parentElement, nextSibling: menu.nextSibling });
        menu.classList.remove('hidden');
        menu.classList.add('account-action-menu-floating');
        document.body.appendChild(menu);
        positionMenu(menu, button);
        button.setAttribute('aria-expanded', 'true');
        openMenu = menu;
        var action = menu.querySelector('button');
        if (action) action.focus();
    }

    document.querySelectorAll('.account-action-menu-button').forEach(function (button) {
        buttonMenus.set(button, button.parentElement.querySelector('.account-action-menu'));
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            var menu = buttonMenus.get(button);
            if (openMenu === menu) closeMenu(menu, false);
            else openMenuFor(button, menu);
        });
        button.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowDown' && event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            button.click();
        });
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.account-action-menu-button') && !event.target.closest('.account-action-menu')) closeMenu(openMenu, true);
    });
    window.addEventListener('resize', function () {
        if (openMenu) {
            var home = menuHomes.get(openMenu);
            var button = home && home.parent ? home.parent.querySelector('.account-action-menu-button') : null;
            if (button) positionMenu(openMenu, button);
        }
    });
    window.addEventListener('scroll', function () {
        if (openMenu) {
            var home = menuHomes.get(openMenu);
            var button = home && home.parent ? home.parent.querySelector('.account-action-menu-button') : null;
            if (button) positionMenu(openMenu, button);
        }
    }, true);
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || activeOverlay) return;
        closeMenu(openMenu, true);
    });
})();
