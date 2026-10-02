(function () {
    'use strict';
    const form = document.getElementById('board-compose');
    if (!form) return;
    let input = document.getElementById('attachment');
    const preview = document.getElementById('attachment-preview'), actions = document.getElementById('attachment-actions');
    const error = document.getElementById('attachment-error'), dialog = document.getElementById('attachment-enlarge');
    const enlargeImage = document.getElementById('attachment-enlarge-image');
    let currentUrl = null, pendingPicker = null, sequence = 0, validating = false, enlargeOpener = null;
    const candidateUrls = new Set();
    const types = {jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', pdf: 'application/pdf', doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'};
    const size = bytes => bytes.toLocaleString('en-PH') + ' bytes (' + (bytes / 1048576).toFixed(2) + ' MiB)';
    function showError(message) { error.textContent = message; error.hidden = !message; }
    function releaseCurrent() {
        if (dialog.open) dialog.close();
        enlargeImage.removeAttribute('src');
        if (currentUrl) URL.revokeObjectURL(currentUrl);
        currentUrl = null;
    }
    function releaseCandidate(url) { if (url) { URL.revokeObjectURL(url); candidateUrls.delete(url); } }
    function removePicker() { if (pendingPicker) { pendingPicker.remove(); pendingPicker = null; } }
    async function validate(file) {
        const extension = file.name.split('.').pop().toLowerCase(), expected = types[extension];
        if (!expected) throw new Error('Choose a PDF, JPG, JPEG, PNG, DOC, or DOCX file.');
        if (file.size === 0) throw new Error('That file is empty. Choose a file with content.');
        if (file.size > 8 * 1024 * 1024) throw new Error('That file exceeds 8 MiB (8,388,608 bytes).');
        const mime = file.type.toLowerCase().split(';')[0].trim();
        const inconclusive = !mime || ['application/octet-stream', 'binary/octet-stream', 'application/binary', 'application/x-binary', 'application/x-download'].includes(mime)
            || (extension === 'docx' && ['application/zip', 'application/x-zip-compressed'].includes(mime));
        if (!inconclusive && mime !== expected) throw new Error('The reported file type does not match the selected file extension.');
        let bytes;
        try { bytes = await file.arrayBuffer(); }
        catch (_) { throw new Error('That file could not be read. Please choose it again.'); }
        const url = URL.createObjectURL(new Blob([bytes], {type: expected})); candidateUrls.add(url);
        if (expected.startsWith('image/')) {
            try {
                await new Promise((resolve, reject) => { const image = new Image(); image.onload = resolve; image.onerror = reject; image.src = url; });
            } catch (_) { releaseCandidate(url); throw new Error('That image could not be decoded. Choose a readable JPG or PNG.'); }
        }
        return {url, extension, expected};
    }
    function render(file, result) {
        preview.replaceChildren(); preview.hidden = false; actions.hidden = false;
        const name = document.createElement('p'); name.className = 'attachment-filename'; name.textContent = file.name;
        const metadata = document.createElement('p'); metadata.textContent = result.extension.toUpperCase() + ' · ' + size(file.size);
        preview.append(name, metadata);
        if (result.expected.startsWith('image/')) {
            const image = document.createElement('img'); image.src = result.url; image.alt = 'Selected attachment: ' + file.name;
            const enlarge = document.createElement('button'); enlarge.type = 'button'; enlarge.textContent = 'Enlarge';
            enlarge.addEventListener('click', () => { enlargeOpener = enlarge; enlargeImage.src = currentUrl; enlargeImage.alt = file.name; dialog.showModal(); document.getElementById('attachment-enlarge-close').focus(); });
            preview.append(image, enlarge);
        } else if (result.extension === 'pdf') {
            const object = document.createElement('object'); object.data = result.url; object.type = 'application/pdf'; object.setAttribute('aria-label', 'Selected PDF preview');
            const fallback = document.createElement('p'); fallback.textContent = 'Embedded PDF preview is unavailable in this browser. Use Open PDF below.'; object.append(fallback);
            const link = document.createElement('a'); link.href = result.url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = 'Open PDF';
            const help = document.createElement('p'); help.textContent = 'PDF embedding depends on your browser. Open PDF is always available.';
            preview.append(object, link, help);
        } else {
            const message = document.createElement('p'); message.textContent = 'Content preview unavailable'; preview.append(message);
        }
    }
    async function select(candidate, replacing) {
        const file = candidate.files[0];
        if (!file) { if (replacing) removePicker(); return; }
        const turn = ++sequence; validating = true; showError('');
        try {
            const result = await validate(file);
            if (turn !== sequence) { releaseCandidate(result.url); return; }
            if (replacing) {
                candidate.id = 'attachment'; candidate.name = 'attachment'; candidate.className = input.className;
                candidate.hidden = false; candidate.setAttribute('aria-describedby', 'attachment-error');
                input.replaceWith(candidate); input = candidate; pendingPicker = null;
            }
            releaseCurrent(); currentUrl = result.url; candidateUrls.delete(result.url);
            input.hidden = true; render(file, result);
        } catch (failure) {
            if (turn !== sequence) return;
            if (replacing) removePicker(); else { input.value = ''; input.hidden = false; }
            showError(failure.message);
        } finally { if (turn === sequence) validating = false; }
    }
    input.addEventListener('change', () => select(input, false));
    document.getElementById('attachment-replace').addEventListener('click', () => {
        ++sequence; validating = false; removePicker(); const picker = document.createElement('input');
        picker.type = 'file'; picker.accept = input.accept; picker.hidden = true;
        // Unnamed picker never contributes an attachment to a multipart submission.
        form.append(picker); pendingPicker = picker;
        picker.addEventListener('change', () => select(picker, picker !== input));
        picker.addEventListener('cancel', () => { if (pendingPicker === picker) removePicker(); });
        picker.click();
    });
    document.getElementById('attachment-remove').addEventListener('click', () => {
        ++sequence; validating = false; removePicker(); releaseCurrent();
        candidateUrls.forEach(releaseCandidate); input.value = ''; input.hidden = false;
        preview.replaceChildren(); preview.hidden = true; actions.hidden = true; showError(''); input.focus();
    });
    form.addEventListener('submit', event => {
        if (validating) { event.preventDefault(); showError('Please wait for attachment validation, then send again.'); }
    });
    document.getElementById('attachment-enlarge-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        if (event.key === 'Tab') { event.preventDefault(); document.getElementById('attachment-enlarge-close').focus(); }
    });
    dialog.addEventListener('close', () => { if (enlargeOpener?.isConnected) enlargeOpener.focus(); });
    window.addEventListener('pagehide', () => { ++sequence; removePicker(); releaseCurrent(); candidateUrls.forEach(releaseCandidate); });
    window.addEventListener('pageshow', event => { if (event.persisted && input.files.length) select(input, false); });
}());
