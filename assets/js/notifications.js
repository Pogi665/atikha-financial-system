(function () {
    'use strict';

    function initNotificationBell(root) {
        if (!root) {
            return;
        }

        const toggle = root.querySelector('.js-notif-toggle');
        const menu = root.querySelector('.js-notif-menu');
        const badge = root.querySelector('.js-notif-badge');
        const list = root.querySelector('.js-notif-list');
        const empty = root.querySelector('.js-notif-empty');
        const csrfToken = root.dataset.csrf || '';

        if (!toggle || !menu || !list || !csrfToken) {
            return;
        }

        function setBadge(count) {
            if (!badge) {
                return;
            }
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('hidden');
            } else {
                badge.textContent = '0';
                badge.classList.add('hidden');
            }
        }

        function closeMenu() {
            menu.classList.add('hidden');
        }

        function openMenu() {
            menu.classList.remove('hidden');
            refreshList();
        }

        function postAction(action, extra) {
            const body = new URLSearchParams();
            body.set('action', action);
            body.set('csrf_token', csrfToken);
            if (extra) {
                Object.keys(extra).forEach(function (key) {
                    body.set(key, extra[key]);
                });
            }

            return fetch('notifications_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .catch(function () { return { ok: false, error: 'Unable to reach the server.' }; });
        }

        function renderItems(items) {
            list.innerHTML = '';

            if (!items || items.length === 0) {
                if (empty) {
                    empty.classList.remove('hidden');
                }
                return;
            }

            if (empty) {
                empty.classList.add('hidden');
            }

            items.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'js-notif-item w-full text-left px-4 py-3 border-b border-slate-100 hover:bg-slate-50 transition';
                button.dataset.id = String(item.id);
                button.dataset.url = item.target_url || '';

                const unreadClass = item.is_read ? 'text-slate-600' : 'text-slate-900 font-medium';
                button.innerHTML =
                    '<p class="text-sm ' + unreadClass + '">' + escapeHtml(item.message) + '</p>' +
                    '<p class="text-xs text-slate-400 mt-1">' + escapeHtml(item.time_label || '') + '</p>';

                list.appendChild(button);
            });
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function refreshList() {
            postAction('list', { limit: '10' }).then(function (payload) {
                if (!payload.ok) {
                    return;
                }
                renderItems(payload.data.items || []);
                setBadge(payload.data.unread_count || 0);
            });
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            if (menu.classList.contains('hidden')) {
                openMenu();
            } else {
                closeMenu();
            }
        });

        list.addEventListener('click', function (event) {
            const item = event.target.closest('.js-notif-item');
            if (!item) {
                return;
            }

            const notificationId = item.dataset.id;
            const targetUrl = item.dataset.url || '';

            postAction('mark_read', { notification_id: notificationId }).then(function (payload) {
                if (payload.ok && payload.data) {
                    setBadge(payload.data.unread_count || 0);
                }
                if (targetUrl) {
                    window.location.href = targetUrl;
                } else {
                    refreshList();
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }

    document.querySelectorAll('.js-notification-bell').forEach(initNotificationBell);
})();
