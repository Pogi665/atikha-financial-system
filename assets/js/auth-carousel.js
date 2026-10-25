/* Presentation only: never submits, validates, reads credentials, or calls auth endpoints. */
(() => {
    'use strict';
    const panel = document.querySelector('[data-auth-carousel]');
    if (!panel) return;
    const photos = [
        ['discuss', 'Collaborative Learning', 'Strengthening knowledge through discussion, reflection, and shared experience.'],
        ['responsive', 'Participatory Planning', 'Working with communities to identify needs and shape meaningful programs.'],
        ['doc_', 'Community Dialogue', 'Creating spaces where experiences and local perspectives can be heard.'],
        ['development', 'Community Engagement', 'Bringing people together to share ideas, experiences, and solutions.'],
        ['image_1', 'Learning Together', 'Connecting people through training, exchange, and collective participation.'],
        ['image_2', 'Community Engagement', 'Bringing people together to share ideas, experiences, and solutions.'],
        ['login_management_board', 'Community Dialogue', 'Creating spaces where experiences and local perspectives can be heard.'],
        ['plants', 'Community Development', 'Supporting locally rooted initiatives for sustainable growth and resilience.'],
        ['stakeholders', 'Partnerships in Action', 'Working alongside local stakeholders to support community development.'],
        ['training', 'Capacity Building', 'Developing practical knowledge and skills that communities can carry forward.'],
        ['tree_planting', 'Working Together', 'Turning shared knowledge and collaboration into meaningful community action.'],
    ];
    const layers = [...panel.querySelectorAll('[data-auth-photo]')];
    const title = panel.querySelector('[data-photo-title]');
    const description = panel.querySelector('[data-photo-description]');
    const toggle = panel.querySelector('[data-photo-pause]');
    const icon = (className, pathData) => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.setAttribute('class', className);
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathData);
        svg.append(path);
        return svg;
    };
    toggle.replaceChildren(
        icon('auth-media__pause-icon', 'M7 5h4v14H7zM14 5h4v14h-4z'),
        icon('auth-media__play-icon', 'M8 5v14l11-7z')
    );
    const labelControl = paused => {
        const label = paused ? 'Resume background photos' : 'Pause background photos';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
    };
    labelControl(false);
    const desktop = window.matchMedia('(min-width: 1024px)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const loaded = new Map();
    let index = 0;
    let active = 0;
    let timer;
    let paused = false;
    let changing = false;
    const src = i => 'assets/img/auth/optimized/' + photos[i][0] + '.webp';
    const running = () => desktop.matches && !reduced.matches && !paused && !document.hidden;
    function load(i) {
        if (!loaded.has(i)) {
            loaded.set(i, new Promise(resolve => {
                const image = new Image();
                image.onload = () => resolve(image);
                image.onerror = () => resolve(null);
                image.src = src(i);
            }));
        }
        return loaded.get(i);
    }
    function schedule() {
        window.clearTimeout(timer);
        if (!running()) return;
        load((index + 1) % photos.length); // Only one photo ahead; never a full-gallery startup download.
        timer = window.setTimeout(advance, 7000);
    }
    async function advance() {
        if (!running() || changing) return;
        changing = true;
        const next = (index + 1) % photos.length;
        const image = await load(next);
        if (image && running()) {
            const incoming = layers[1 - active];
            // The first frame's responsive source is only the no-JS/initial-load fallback.
            const initialSource = incoming.parentElement.querySelector('source');
            if (initialSource) initialSource.remove();
            incoming.src = image.src;
            try { await incoming.decode(); } catch (_) { /* Keep the last verified photo on decode failure. */ }
            if (running() && incoming.naturalWidth > 0) {
                // Keep the old, opaque frame underneath throughout the fade: no white/blank midpoint.
                incoming.style.zIndex = '1';
                layers[active].style.zIndex = '0';
                incoming.classList.add('is-current');
                index = next;
                title.textContent = photos[index][1];
                description.textContent = photos[index][2];
                panel.dataset.photoIndex = String(index);
                await new Promise(resolve => window.setTimeout(resolve, 850));
                layers[active].classList.remove('is-current');
                active = 1 - active;
            }
        }
        changing = false;
        schedule();
    }
    function sync() {
        toggle.hidden = reduced.matches || !desktop.matches;
        if (desktop.matches && !layers[0].getAttribute('src')) layers[0].src = src(0);
        schedule();
    }
    toggle.addEventListener('click', () => {
        paused = !paused;
        labelControl(paused);
        toggle.setAttribute('aria-pressed', String(paused));
        schedule();
    });
    // Observe the existing reset-view state; do not change its handlers, focus, validation, or requests.
    const login = document.getElementById('login-view');
    if (login) {
        const stateText = [...panel.querySelectorAll('[data-auth-state]')];
        const syncState = () => {
            const state = login.getAttribute('aria-hidden') === 'true' ? 'recovery' : 'login';
            stateText.forEach(element => { element.hidden = element.dataset.authState !== state; });
        };
        new MutationObserver(syncState).observe(login, { attributes: true, attributeFilter: ['aria-hidden'] });
        syncState();
    }
    desktop.addEventListener('change', sync);
    reduced.addEventListener('change', sync);
    document.addEventListener('visibilitychange', sync);
    sync();
})();
