(function () {
    'use strict';
    const form = document.querySelector('[data-compose-form]');
    if (!form) return;
    const file = document.getElementById('email-attachment');
    const trigger = document.querySelector('[data-attachment-trigger]');
    const filename = document.getElementById('attachment-name');
    trigger.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            file.click();
        }
    });
    file.addEventListener('change', function () {
        filename.textContent = file.files.length ? file.files[0].name : 'No attachment selected';
    });
    form.addEventListener('submit', function (event) {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        const button = form.querySelector('[data-send-button]');
        button.disabled = true;
        button.textContent = 'Sending...';
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted && form.dataset.submitting === 'true') {
            // Fetch fresh server state rather than restoring a stale submission.
            window.location.reload();
        }
    });
})();
