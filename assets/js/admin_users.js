(function () {
    'use strict';

    const page = document.querySelector('.users-page');
    if (!page) return;

    let activeOverlay = null;
    let activeOpener = null;
    let activeClose = null;
    let statusForm = null;

    function focusables(root) {
        return Array.from(root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])'))
            .filter(function (element) { return !element.closest('[hidden]'); });
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
        const summary = overlay.querySelector('[data-form-summary]:not(.hidden)');
        const items = focusables(overlay);
        if (summary) summary.focus();
        else if (items.length) items[0].focus();
    }

    function closeOverlay(force) {
        if (!activeClose) return;
        activeClose(Boolean(force));
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

    function wireTrap(overlay) {
        overlay.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || activeOverlay !== overlay) return;
            const items = focusables(overlay);
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) closeOverlay(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeOverlay) closeOverlay(false);
    });

    const userModal = document.getElementById('user-modal');
    const userForm = document.querySelector('[data-user-form]');
    const addButton = document.getElementById('add-user-button');
    const roleHelp = document.querySelector('[data-role-help]');
    let roleDescriptions = {};
    try { roleDescriptions = JSON.parse(page.dataset.roleDescriptions || '{}'); } catch (error) { roleDescriptions = {}; }

    function cleanModalUrl() {
        const url = new URL(window.location.href);
        url.searchParams.delete('new');
        url.searchParams.delete('edit');
        url.hash = '';
        window.history.replaceState({}, '', url.toString());
    }

    if (userModal && userForm) {
        let initialFormState = new FormData(userForm);
        function isDirty() {
            const current = new FormData(userForm);
            for (const [key, value] of current.entries()) {
                if (key === 'csrf_token' || key === 'password') continue;
                if (value !== initialFormState.get(key)) return true;
            }
            return userForm.querySelector('[name="password"]').value !== '';
        }
        function closeUser(force) {
            if (!force && isDirty() && !window.confirm('Discard the unsaved user changes?')) return;
            cleanModalUrl();
            finishOverlay(userModal, activeOpener);
            userForm.reset();
            initialFormState = new FormData(userForm);
        }
        wireTrap(userModal);
        document.querySelectorAll('[data-close-user-modal]').forEach(function (button) { button.addEventListener('click', function () { closeOverlay(false); }); });
        userForm.addEventListener('input', function () { userForm.dataset.dirty = isDirty() ? 'true' : 'false'; });
        userForm.addEventListener('submit', function () { userForm.dataset.dirty = 'false'; });
        const toggle = document.getElementById('password-toggle');
        const password = document.getElementById('password');
        if (toggle && password) toggle.addEventListener('click', function () {
            const visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            toggle.textContent = visible ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
        });
        const role = document.getElementById('role');
        if (role && roleHelp) role.addEventListener('change', function () { roleHelp.textContent = roleDescriptions[role.value] || 'Select a role to see its current system access.'; });
        if (userModal.dataset.openModal === 'user') openOverlay(userModal, addButton, closeUser);
    }

    const statusModal = document.getElementById('status-modal');
    const statusDescription = document.getElementById('status-modal-description');
    const statusConfirm = document.getElementById('status-confirm');
    if (statusModal) {
        wireTrap(statusModal);
        document.querySelectorAll('[data-status-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                statusForm = form;
                const name = form.dataset.userName || 'this user';
                statusDescription.textContent = form.dataset.statusAction === 'disable'
                    ? 'Disable ' + name + '? Historical records will be preserved, but the account will no longer be able to sign in.'
                    : 'Enable ' + name + '? This will restore the account\'s ability to sign in.';
                openOverlay(statusModal, event.submitter || form.querySelector('button'), function (force) {
                    if (!force) finishOverlay(statusModal, activeOpener);
                    else finishOverlay(statusModal, activeOpener);
                    statusForm = null;
                });
            });
        });
        document.querySelectorAll('[data-close-status-modal]').forEach(function (button) { button.addEventListener('click', function () { closeOverlay(false); }); });
        statusConfirm.addEventListener('click', function () { if (statusForm) statusForm.submit(); });
    }

    document.querySelectorAll('.user-action-menu-button').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            const menu = button.parentElement.querySelector('.user-action-menu');
            document.querySelectorAll('.user-action-menu').forEach(function (item) { if (item !== menu) item.classList.add('hidden'); });
            const open = menu.classList.toggle('hidden');
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (!open) menu.querySelector('button').focus();
        });
        button.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowDown' && event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            button.click();
        });
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.user-action-menu-button') && !event.target.closest('.user-action-menu')) {
            document.querySelectorAll('.user-action-menu').forEach(function (menu) { menu.classList.add('hidden'); });
            document.querySelectorAll('.user-action-menu-button').forEach(function (button) { button.setAttribute('aria-expanded', 'false'); });
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || activeOverlay) return;
        const openMenu = document.querySelector('.user-action-menu:not(.hidden)');
        if (!openMenu) return;
        openMenu.classList.add('hidden');
        const menuButton = openMenu.parentElement.querySelector('.user-action-menu-button');
        if (menuButton) { menuButton.setAttribute('aria-expanded', 'false'); menuButton.focus(); }
    });

    const tabs = Array.from(document.querySelectorAll('[role="tab"]'));
    tabs.forEach(function (tab, index) {
        tab.addEventListener('keydown', function (event) {
            if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            let nextIndex = index;
            if (event.key === 'Home') nextIndex = 0;
            else if (event.key === 'End') nextIndex = tabs.length - 1;
            else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') nextIndex = (index + 1) % tabs.length;
            else nextIndex = (index - 1 + tabs.length) % tabs.length;
            tabs[nextIndex].focus();
        });
    });

    const resetModal = document.getElementById('resolve-modal');
    const resetForm = document.getElementById('resolve-form');
    const resetIdField = document.getElementById('resolve-reset-id');
    const emailLabel = document.getElementById('resolve-email');
    const resetPassword = document.getElementById('resolve-password');
    const resetError = document.getElementById('resolve-error');
    const resetSubmit = document.getElementById('resolve-submit');
    const resetBody = document.getElementById('resets-body');
    const resetCount = document.getElementById('pending-reset-count');
    const banner = document.getElementById('resolve-banner');
    const bannerText = document.getElementById('resolve-banner-text');
    const csrfToken = page.dataset.csrf || '';
    const minLength = Number(page.dataset.passwordMin || 8);

    function resetMessage(message) { resetError.textContent = message; resetError.classList.remove('hidden'); }
    function clearResetMessage() { resetError.textContent = ''; resetError.classList.add('hidden'); }
    function updateResetCount() {
        const count = resetBody ? resetBody.querySelectorAll('[data-reset-row]').length : 0;
        if (resetCount) resetCount.textContent = String(count);
        if (resetBody && count === 0 && !resetBody.querySelector('#resets-empty')) resetBody.innerHTML = '<tr id="resets-empty"><td colspan="4" class="px-6 py-10 text-center"><p class="font-medium text-slate-700">No pending requests</p><p class="mt-1 text-sm text-slate-500">New requests from the login page will appear here.</p></td></tr>';
    }
    function closeReset(force) {
        if (!force && resetPassword.value !== '' && !window.confirm('Discard the temporary password you entered?')) return;
        clearResetMessage(); resetPassword.value = ''; finishOverlay(resetModal, activeOpener);
    }
    if (resetModal && resetForm) {
        wireTrap(resetModal);
        document.querySelectorAll('.js-resolve-reset').forEach(function (button) { button.addEventListener('click', function () { resetIdField.value = button.dataset.resetId; emailLabel.textContent = button.dataset.email; clearResetMessage(); openOverlay(resetModal, button, closeReset); }); });
        document.getElementById('resolve-close').addEventListener('click', function () { closeOverlay(false); });
        document.getElementById('resolve-cancel').addEventListener('click', function () { closeOverlay(false); });
        resetForm.addEventListener('submit', function (event) {
            event.preventDefault(); clearResetMessage();
            if (resetPassword.value.length < minLength) { resetMessage('The temporary password must be at least ' + minLength + ' characters.'); return; }
            const body = new URLSearchParams(); body.set('reset_id', resetIdField.value); body.set('new_password', resetPassword.value); body.set('csrf_token', csrfToken);
            resetSubmit.disabled = true; resetSubmit.textContent = 'Resetting...';
            fetch('resolve_reset.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .catch(function () { return { ok: false, error: 'The request could not be completed. Please try again.' }; })
                .then(function (payload) {
                    resetSubmit.disabled = false; resetSubmit.textContent = 'Reset Password';
                    if (!payload || !payload.ok) { resetMessage((payload && payload.error) || 'The request could not be completed. Please try again.'); return; }
                    const resetId = resetIdField.value; const email = emailLabel.textContent;
                    const row = resetBody && resetBody.querySelector('[data-reset-row="' + resetId + '"]'); if (row) row.remove();
                    updateResetCount(); closeOverlay(true); bannerText.textContent = 'Password reset for ' + email + '. The action was recorded in the audit trail.'; banner.classList.remove('hidden');
                });
        });
    }
})();
