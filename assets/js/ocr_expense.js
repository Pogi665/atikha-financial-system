(function () {
    'use strict';
    const form = document.getElementById('receipt-upload');
    const fileInput = document.getElementById('receipt-image');
    let previewUrl = '';
    fileInput?.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        const preview = document.getElementById('ocr-local-preview'), image = document.getElementById('ocr-selected-image'), description = document.getElementById('ocr-selected-file');
        const file = fileInput.files?.[0];
        preview.hidden = true; description.hidden = true; image.removeAttribute('src');
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
            description.textContent = 'Choose a JPG, PNG or WebP image up to 8 MiB.'; description.hidden = false; return;
        }
        previewUrl = URL.createObjectURL(file); image.src = previewUrl; preview.hidden = false;
        description.textContent = file.name + ' · ' + (file.size / 1024).toFixed(1) + ' KiB'; description.hidden = false;
    });
    window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
    if (form && window.fetch) {
        form.addEventListener('submit', async event => {
            if (!form.checkValidity()) return;
            event.preventDefault();
            const button = form.querySelector('button'), progress = document.getElementById('ocr-progress');
            if (button.disabled) return;
            const payload = new FormData(form);
            const file = payload.get('receipt_image');
            if (!file || !file.size || file.size > 8 * 1024 * 1024) { progress.textContent = 'Choose a receipt image up to 8 MiB.'; return; }
            button.disabled = true;
            progress.textContent = 'Storing evidence and reading the image. No journal is being posted.';
            try {
                const response = await fetch('ocr_extract.php', { method: 'POST', body: payload, credentials: 'same-origin' });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    progress.textContent = result.error || 'Receipt intake failed.';
                    if (result.data?.workspace_url) {
                        const recovery = document.createElement('a');
                        recovery.href = result.data.workspace_url; recovery.textContent = ' Open saved receipt for manual review.';
                        progress.append(recovery);
                    }
                    button.disabled = false; return;
                }
                window.location.assign(result.data.workspace_url);
            } catch (error) {
                progress.textContent = error.message + ' Retry with this same form to recover any saved upload.';
                button.disabled = false;
            }
        });
    }
    document.getElementById('receipt-discard')?.addEventListener('submit', event => {
        if (!window.confirm('Discard this unposted receipt and delete its image?')) event.preventDefault();
    });
    document.getElementById('receipt-retry')?.addEventListener('submit', event => {
        const button = event.currentTarget.querySelector('button');
        if (button.disabled) { event.preventDefault(); return; }
        button.disabled = true;
        document.getElementById('ocr-retry-progress').textContent = 'Reading the saved image…';
    });
}());
