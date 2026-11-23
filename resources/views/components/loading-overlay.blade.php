{{-- Loading feedback for the whole app.
     - Buttons that submit a form, and buttons styled as links: a centered "Processing" card on a dimmed page,
       shown only if the action takes longer than a moment (no flash on quick ones). Repeat clicks are blocked.
     - Ordinary links (menu, tables, pagination): a thin progress bar along the top.
     Opt out with data-no-loading on a form or link. --}}

<div id="app-loading" class="app-loading" hidden aria-hidden="true">
    <div class="app-loading-card" role="alertdialog" aria-modal="true" aria-live="polite"
         aria-labelledby="app-loading-title" aria-describedby="app-loading-detail">
        <div class="app-loading-spinner" aria-hidden="true"></div>
        <div class="app-loading-title" id="app-loading-title" data-loading-title>Processing&hellip;</div>
        <div class="app-loading-detail" id="app-loading-detail" data-loading-detail hidden></div>
        <div class="app-loading-bar" aria-hidden="true"><span></span></div>
        <div class="app-loading-hint">This can take a few seconds. Please don&rsquo;t close or refresh the page.</div>
    </div>
</div>

<div id="nav-progress" class="nav-progress" hidden aria-hidden="true"><span></span></div>

<style>
    .app-loading[hidden], .nav-progress[hidden] { display: none !important; }

    .app-loading {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(31, 41, 55, 0.38);
        -webkit-backdrop-filter: blur(2px);
        backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity 0.18s ease;
    }
    .app-loading.is-visible { opacity: 1; }

    .app-loading-card {
        width: min(100%, 22rem);
        padding: 1.75rem 1.75rem 1.25rem;
        text-align: center;
        background: #fff;
        border: 1px solid var(--border, #e7e2d8);
        border-radius: var(--radius-lg, 14px);
        box-shadow: 0 18px 48px rgba(31, 41, 55, 0.22);
        transform: translateY(8px) scale(0.98);
        transition: transform 0.2s ease;
    }
    .app-loading.is-visible .app-loading-card { transform: none; }

    .app-loading-spinner {
        width: 46px;
        height: 46px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        border: 4px solid var(--accent-tint, #e4edfb);
        border-top-color: var(--accent, #2f6feb);
        animation: app-loading-spin 0.85s linear infinite;
    }

    .app-loading-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text, #26241f);
    }

    .app-loading-detail {
        display: inline-block;
        max-width: 100%;
        margin-top: 0.6rem;
        padding: 0.2rem 0.7rem;
        border-radius: 999px;
        background: var(--accent-tint, #e4edfb);
        color: var(--accent, #2f6feb);
        font-size: 0.8rem;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .app-loading-detail[hidden] { display: none; }

    .app-loading-bar {
        height: 4px;
        margin: 1.1rem 0 0.8rem;
        border-radius: 999px;
        background: var(--accent-tint, #e4edfb);
        overflow: hidden;
    }
    .app-loading-bar span {
        display: block;
        width: 38%;
        height: 100%;
        border-radius: 999px;
        background: var(--accent, #2f6feb);
        animation: app-loading-slide 1.25s ease-in-out infinite;
    }

    .app-loading-hint {
        font-size: 0.76rem;
        color: var(--text-muted, #78726a);
    }

    /* Right after a click, before the card appears, ignore further clicks and show a busy cursor. */
    body.is-loading { cursor: progress; }
    body.is-loading .app-main { pointer-events: none; }

    /* Thin bar along the top for ordinary link navigation. */
    .nav-progress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        z-index: 2001;
        pointer-events: none;
    }
    .nav-progress span {
        display: block;
        height: 100%;
        width: 0;
        background: var(--accent, #2f6feb);
        box-shadow: 0 0 8px rgba(47, 111, 235, 0.5);
        animation: nav-progress-run 9s cubic-bezier(0.1, 0.6, 0.2, 1) forwards;
    }

    @keyframes app-loading-spin { to { transform: rotate(360deg); } }
    @keyframes app-loading-slide {
        0% { transform: translateX(-110%); }
        100% { transform: translateX(290%); }
    }
    @keyframes nav-progress-run {
        0% { width: 0; }
        100% { width: 88%; }
    }

    @media (prefers-reduced-motion: reduce) {
        .app-loading, .app-loading-card { transition: none; }
        .app-loading-spinner { animation-duration: 2.2s; }
        .app-loading-bar span { animation: none; width: 60%; }
    }

    @media print { .app-loading, .nav-progress { display: none !important; } }
</style>

<script>
(function () {
    const overlay = document.getElementById('app-loading');
    const bar = document.getElementById('nav-progress');
    if (! overlay || ! bar) return;

    const titleEl = overlay.querySelector('[data-loading-title]');
    const detailEl = overlay.querySelector('[data-loading-detail]');

    const SHOW_DELAY = 300;     // ms before the card appears, so quick actions never flash it
    const BAR_DELAY = 120;      // ms before the top bar appears
    const SAFETY = 90000;       // never leave the page blocked longer than this (e.g. if a download starts)
    const BAR_SAFETY = 15000;

    let showTimer = null;
    let barTimer = null;
    let safetyTimer = null;
    let busy = false;

    function clean(text) {
        return (text || '').replace(/\s+/g, ' ').trim().slice(0, 70);
    }

    function labelOf(el) {
        if (! el) return '';
        return clean(el.getAttribute('data-loading-text') || el.textContent || el.value);
    }

    function hideAll() {
        clearTimeout(showTimer);
        clearTimeout(barTimer);
        clearTimeout(safetyTimer);
        overlay.classList.remove('is-visible');
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        bar.hidden = true;
        document.body.classList.remove('is-loading');
        busy = false;
    }

    function startModal(title, detail) {
        busy = true;
        document.body.classList.add('is-loading');
        clearTimeout(showTimer);
        clearTimeout(safetyTimer);

        showTimer = setTimeout(function () {
            titleEl.textContent = title;
            detailEl.textContent = detail || '';
            detailEl.hidden = ! detail;
            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(function () { overlay.classList.add('is-visible'); });
        }, SHOW_DELAY);

        safetyTimer = setTimeout(hideAll, SAFETY);
    }

    function startBar() {
        clearTimeout(barTimer);
        barTimer = setTimeout(function () {
            // Restart the animation each time.
            bar.hidden = true;
            void bar.offsetWidth;
            bar.hidden = false;
            setTimeout(function () { bar.hidden = true; }, BAR_SAFETY);
        }, BAR_DELAY);
    }

    function trackableForm(form) {
        if (! form || form.hasAttribute('data-no-loading')) return false;
        const target = (form.getAttribute('target') || '').toLowerCase();
        return ! target || target === '_self';
    }

    function describeForm(form, submitter) {
        const label = labelOf(submitter) || labelOf(form.querySelector('[type="submit"]'));
        const hasFiles = Array.prototype.some.call(form.querySelectorAll('input[type="file"]'), function (input) {
            return input.files && input.files.length > 0;
        });

        if (hasFiles) return ['Uploading files\u2026', label];
        if ((form.getAttribute('action') || '').replace(/\/+$/, '').slice(-7) === '/logout') return ['Signing out\u2026', ''];
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return ['Loading\u2026', ''];

        return ['Processing\u2026', label];
    }

    // Every form submitted with a button.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (! (form instanceof HTMLFormElement) || event.defaultPrevented || ! trackableForm(form)) return;

        if (busy) {            // a second click while the first is still being processed
            event.preventDefault();
            return;
        }

        const described = describeForm(form, event.submitter);
        startModal(described[0], described[1]);

        // If something later in the chain cancels the submit (a confirm box, a custom check), undo.
        setTimeout(function () { if (event.defaultPrevented) hideAll(); }, 0);
    });

    // Forms submitted from script, e.g. a drop-down that submits on change, skip the submit event.
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (trackableForm(this) && ! busy) {
            const described = describeForm(this, null);
            startModal(described[0], described[1]);
        }
        return nativeSubmit.apply(this, arguments);
    };

    // Links: a card for buttons that look like buttons, the thin bar for everything else.
    document.addEventListener('click', function (event) {
        const link = event.target.closest ? event.target.closest('a[href]') : null;
        if (! link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        const href = link.getAttribute('href') || '';
        if (! href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
        if ((link.target && link.target !== '_self') || link.hasAttribute('download')
            || link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-attachment-preview')
            || link.hasAttribute('data-no-loading')) return;

        let url;
        try { url = new URL(link.href, window.location.href); } catch (e) { return; }
        if (url.origin !== window.location.origin) return;
        if (/\/(attachments|preview)(\/|$)/.test(url.pathname)) return;   // file downloads leave the page in place
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        setTimeout(function () {
            if (event.defaultPrevented || busy) return;
            if (link.classList.contains('btn')) {
                startModal('Loading\u2026', labelOf(link));
            } else {
                startBar();
            }
        }, 0);
    });

    // Coming back with the browser's Back button can show the page frozen as it was: reset it.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) hideAll();
    });
})();
</script>
