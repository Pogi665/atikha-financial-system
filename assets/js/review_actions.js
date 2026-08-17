(function () {
    'use strict';

    const root = document.querySelector('.js-review-root');
    if (!root) {
        return;
    }

    const csrfToken = root.dataset.csrf || '';

    function postReview(action, fields) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrfToken);
        Object.keys(fields).forEach(function (key) {
            body.set(key, fields[key]);
        });

        return fetch('review_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .catch(function () { return { ok: false, error: 'Unable to reach the server.' }; });
    }

    function showFlash(message, isError) {
        const flash = document.getElementById('review-flash');
        if (!flash) {
            window.alert(message);
            return;
        }
        flash.textContent = message;
        flash.className = isError
            ? 'rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600 font-medium'
            : 'rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 font-medium';
        flash.classList.remove('hidden');
    }

    document.querySelectorAll('.js-send-review').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!window.confirm('Send this item to Management for review?')) {
                return;
            }

            button.disabled = true;
            postReview('send_for_review', {
                entity_type: button.dataset.entityType,
                entity_id: button.dataset.entityId || '',
                report_month: button.dataset.reportMonth || '',
                report_year: button.dataset.reportYear || ''
            }).then(function (payload) {
                if (!payload.ok) {
                    showFlash(payload.error || 'Unable to send for review.', true);
                    button.disabled = false;
                    return;
                }
                window.location.reload();
            });
        });
    });

    document.querySelectorAll('.js-mark-reviewed').forEach(function (button) {
        button.addEventListener('click', function () {
            const notesField = document.getElementById('review-notes');
            const notes = notesField ? notesField.value.trim() : '';

            if (!window.confirm('Mark this item as reviewed? The original sender will be notified.')) {
                return;
            }

            button.disabled = true;
            postReview('mark_reviewed', {
                entity_type: button.dataset.entityType,
                entity_id: button.dataset.entityId,
                review_notes: notes
            }).then(function (payload) {
                if (!payload.ok) {
                    showFlash(payload.error || 'Unable to mark as reviewed.', true);
                    button.disabled = false;
                    return;
                }
                window.location.reload();
            });
        });
    });
})();
