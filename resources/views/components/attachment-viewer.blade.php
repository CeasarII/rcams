{{-- One viewer for every attachment link on every page (see <x-attachment-link>). --}}
<div class="modal fade" id="attachmentViewer" tabindex="-1" aria-labelledby="attachmentViewerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-md-down modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                {{-- The title takes the free space so Download and Close sit together on the right
                     (Bootstrap's close button has its own auto margin, which used to push Download to the middle). --}}
                <h5 class="modal-title text-truncate flex-grow-1 me-3" id="attachmentViewerTitle" style="min-width: 0;" data-viewer-title></h5>
                <a href="#" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 flex-shrink-0" data-viewer-download rel="noopener">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M5 21h14"/></svg>
                    Download
                </a>
                <button type="button" class="btn-close ms-3 flex-shrink-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="viewer-toolbar" data-viewer-toolbar hidden>
                <button type="button" class="btn btn-outline-secondary btn-sm viewer-btn" data-zoom="out" aria-label="Zoom out" title="Zoom out (-)">&minus;</button>
                <button type="button" class="btn btn-outline-secondary btn-sm viewer-zoom-label" data-zoom="reset" title="Reset to fit (0)" data-viewer-zoomlabel>100%</button>
                <button type="button" class="btn btn-outline-secondary btn-sm viewer-btn" data-zoom="in" aria-label="Zoom in" title="Zoom in (+)">+</button>
                <span class="viewer-pageinfo" data-viewer-pages></span>
                <a href="#" target="_blank" rel="noopener" class="btn btn-link btn-sm ms-auto py-0" data-viewer-newtab hidden>Open in new tab</a>
            </div>
            <div class="modal-body p-0 bg-light attachment-viewer-body" data-viewer-body></div>
        </div>
    </div>
</div>

<script>
(function () {
    const el = document.getElementById('attachmentViewer');
    if (! el) return;

    const title = el.querySelector('[data-viewer-title]');
    const body = el.querySelector('[data-viewer-body]');
    const download = el.querySelector('[data-viewer-download]');
    const toolbar = el.querySelector('[data-viewer-toolbar]');
    const zoomLabel = el.querySelector('[data-viewer-zoomlabel]');
    const pageInfo = el.querySelector('[data-viewer-pages]');
    const newTab = el.querySelector('[data-viewer-newtab]');

    // PDF.js draws the pages ourselves so the zoom buttons work on every browser and on phones
    // (an embedded PDF frame has no zoom on iOS). It is loaded only when a PDF is opened; if it
    // cannot be loaded, the browser's own PDF viewer is used instead.
    const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
    const STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3];
    const MIN_ZOOM = STEPS[0];
    const MAX_ZOOM = STEPS[STEPS.length - 1];

    let modal = null;
    let session = 0;      // bumped on every open/close so slow loads from an earlier file are ignored
    let viewer = null;    // { zoom, apply(), destroy() } for the file currently shown
    let pdfjsPromise = null;

    function loadPdfJs() {
        if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
        if (! pdfjsPromise) {
            pdfjsPromise = new Promise(function (resolve, reject) {
                const s = document.createElement('script');
                s.src = PDFJS + 'pdf.min.js';
                s.onload = function () {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js';
                    resolve(window.pdfjsLib);
                };
                s.onerror = function () { pdfjsPromise = null; reject(new Error('pdf.js could not be loaded')); };
                document.head.appendChild(s);
            });
        }
        return pdfjsPromise;
    }

    function setToolbar(visible, pdf) {
        toolbar.hidden = ! visible;
        pageInfo.textContent = '';
        newTab.hidden = ! (visible && pdf);
        zoomLabel.textContent = '100%';
    }

    function message(html) {
        const note = document.createElement('div');
        note.className = 'h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted p-4';
        note.innerHTML = html;
        return note;
    }

    function zoomTo(z) {
        if (! viewer) return;
        viewer.zoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, z));
        zoomLabel.textContent = Math.round(viewer.zoom * 100) + '%';
        viewer.apply();
    }

    function stepZoom(direction) {
        if (! viewer) return;
        const z = viewer.zoom;
        let next;
        if (direction > 0) {
            next = STEPS.find(function (s) { return s > z + 0.001; });
            if (next === undefined) next = MAX_ZOOM;
        } else {
            next = STEPS.slice().reverse().find(function (s) { return s < z - 0.001; });
            if (next === undefined) next = MIN_ZOOM;
        }
        zoomTo(next);
    }

    // Keep whatever is in the middle of the screen in the middle while the content grows or shrinks.
    function keepCenter(scroller, change) {
        const w = scroller.scrollWidth || 1;
        const h = scroller.scrollHeight || 1;
        const cx = (scroller.scrollLeft + scroller.clientWidth / 2) / w;
        const cy = (scroller.scrollTop + scroller.clientHeight / 2) / h;
        change();
        scroller.scrollLeft = cx * scroller.scrollWidth - scroller.clientWidth / 2;
        scroller.scrollTop = cy * scroller.scrollHeight - scroller.clientHeight / 2;
    }

    // The scrolling area both viewers sit in: Ctrl/pinch + wheel zooms, and a mouse can drag to pan.
    function makeScroller() {
        const scroller = document.createElement('div');
        scroller.className = 'viewer-scroll';

        scroller.addEventListener('wheel', function (e) {
            if (! (e.ctrlKey || e.metaKey) || ! viewer) return;
            e.preventDefault();
            zoomTo(viewer.zoom * Math.pow(1.0025, -e.deltaY));
        }, { passive: false });

        let drag = null;
        scroller.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            if (scroller.scrollWidth <= scroller.clientWidth && scroller.scrollHeight <= scroller.clientHeight) return;
            drag = { x: e.clientX, y: e.clientY, left: scroller.scrollLeft, top: scroller.scrollTop };
            scroller.setPointerCapture(e.pointerId);
            scroller.classList.add('dragging');
        });
        scroller.addEventListener('pointermove', function (e) {
            if (! drag) return;
            scroller.scrollLeft = drag.left - (e.clientX - drag.x);
            scroller.scrollTop = drag.top - (e.clientY - drag.y);
        });
        function endDrag() { drag = null; scroller.classList.remove('dragging'); }
        scroller.addEventListener('pointerup', endDrag);
        scroller.addEventListener('pointercancel', endDrag);

        return scroller;
    }

    function showImage(url, name) {
        const scroller = makeScroller();
        const img = document.createElement('img');
        img.alt = name;
        img.className = 'viewer-img';
        img.draggable = false;
        scroller.appendChild(img);
        body.replaceChildren(scroller);

        let nw = 0;
        let nh = 0;

        function layout() {
            if (! nw) return;
            // 100% = the whole picture fits the window (never enlarged past its real size).
            const fit = Math.min((scroller.clientWidth - 32) / nw, (scroller.clientHeight - 32) / nh, 1);
            const scale = Math.max(fit, 0.01) * viewer.zoom;
            img.style.width = (nw * scale) + 'px';
            img.style.height = (nh * scale) + 'px';
        }

        viewer = {
            zoom: 1,
            apply: function () { keepCenter(scroller, layout); },
            destroy: function () {},
        };

        img.onload = function () {
            nw = img.naturalWidth;
            nh = img.naturalHeight;
            layout();
        };
        img.onerror = function () {
            viewer = null;
            setToolbar(false);
            body.replaceChildren(message('<p class="mb-1 fw-semibold">The image could not be loaded.</p><p class="mb-0 small">Use Download to get the file.</p>'));
        };
        img.src = url;
        setToolbar(true, false);
    }

    function showPdfNative(url, name) {
        viewer = null;
        setToolbar(false);
        const frame = document.createElement('iframe');
        frame.src = url;
        frame.title = name;
        frame.style.cssText = 'width:100%;height:100%;border:0;';
        body.replaceChildren(frame);
    }

    async function showPdf(url, name, mySession) {
        setToolbar(false);
        body.replaceChildren(message('<div class="spinner-border spinner-border-sm mb-2" role="status"></div><div class="small">Loading document&hellip;</div>'));

        let doc;
        let pdfPages;
        try {
            const lib = await loadPdfJs();
            doc = await lib.getDocument({ url: url, withCredentials: true }).promise;
            pdfPages = await Promise.all(Array.from({ length: doc.numPages }, function (_, i) { return doc.getPage(i + 1); }));
        } catch (e) {
            if (doc) doc.destroy();
            if (mySession === session) showPdfNative(url, name);
            return;
        }
        if (mySession !== session) { doc.destroy(); return; }

        const scroller = makeScroller();
        const pagesBox = document.createElement('div');
        pagesBox.className = 'viewer-pages';
        scroller.appendChild(pagesBox);
        body.replaceChildren(scroller);

        const entries = pdfPages.map(function (page) {
            const wrap = document.createElement('div');
            wrap.className = 'viewer-page';
            const canvas = document.createElement('canvas');
            wrap.appendChild(canvas);
            pagesBox.appendChild(wrap);
            return { page: page, wrap: wrap, canvas: canvas, task: null, rendered: false, vp: null };
        });

        function draw(entry) {
            if (entry.rendered || entry.task || ! entry.vp) return;
            const vp = entry.vp;
            let ratio = window.devicePixelRatio || 1;
            // Keep the bitmap a sane size at high zoom.
            const maxPixels = 16000000;
            if (vp.width * vp.height * ratio * ratio > maxPixels) {
                ratio = Math.sqrt(maxPixels / (vp.width * vp.height));
            }

            const off = document.createElement('canvas');
            off.width = Math.max(1, Math.floor(vp.width * ratio));
            off.height = Math.max(1, Math.floor(vp.height * ratio));

            const task = entry.page.render({
                canvasContext: off.getContext('2d'),
                viewport: vp,
                transform: [ratio, 0, 0, ratio, 0, 0],
            });
            entry.task = task;

            task.promise.then(function () {
                if (entry.task !== task) return;
                entry.task = null;
                entry.rendered = true;
                // Swap in the finished bitmap in one go so the page never flashes blank while zooming.
                entry.canvas.width = off.width;
                entry.canvas.height = off.height;
                entry.canvas.getContext('2d').drawImage(off, 0, 0);
            }).catch(function (err) {
                if (entry.task === task) entry.task = null;
                if (! err || err.name !== 'RenderingCancelledException') console.error(err);
            });
        }

        const observer = new IntersectionObserver(function (items) {
            items.forEach(function (item) {
                if (item.isIntersecting) draw(entries[item.target._index]);
            });
        }, { root: scroller, rootMargin: '800px 0px' });
        entries.forEach(function (entry, i) { entry.wrap._index = i; });

        let redrawTimer = null;
        function layout() {
            // 100% = page width fits the window.
            const available = Math.max(scroller.clientWidth - 32, 100);
            entries.forEach(function (entry) {
                const base = entry.page.getViewport({ scale: 1 });
                entry.vp = entry.page.getViewport({ scale: (available / base.width) * viewer.zoom });
                entry.wrap.style.width = entry.vp.width + 'px';
                entry.wrap.style.height = entry.vp.height + 'px';
                if (entry.task) { entry.task.cancel(); entry.task = null; }
                entry.rendered = false;
            });
            // Resize at once (the old bitmaps stretch as a preview), re-draw once zooming settles.
            clearTimeout(redrawTimer);
            redrawTimer = setTimeout(function () {
                observer.disconnect();
                entries.forEach(function (entry) { observer.observe(entry.wrap); });
            }, 120);
        }

        function updatePageInfo() {
            const line = scroller.scrollTop + scroller.clientHeight / 3;
            let current = 1;
            for (let i = 0; i < entries.length; i++) {
                if (entries[i].wrap.offsetTop <= line) current = i + 1; else break;
            }
            pageInfo.textContent = entries.length > 1 ? 'Page ' + current + ' / ' + entries.length : '';
        }
        let scrollQueued = false;
        scroller.addEventListener('scroll', function () {
            if (scrollQueued) return;
            scrollQueued = true;
            requestAnimationFrame(function () { scrollQueued = false; updatePageInfo(); });
        });

        viewer = {
            zoom: 1,
            apply: function () { keepCenter(scroller, layout); setTimeout(updatePageInfo, 0); },
            destroy: function () {
                clearTimeout(redrawTimer);
                observer.disconnect();
                entries.forEach(function (entry) { if (entry.task) entry.task.cancel(); });
                doc.destroy();
            },
        };

        newTab.href = url;
        setToolbar(true, true);
        layout();
        updatePageInfo();
    }

    function show(link) {
        const name = link.dataset.attachmentName || 'Attachment';
        const mime = (link.dataset.attachmentMime || '').toLowerCase();
        const previewUrl = link.dataset.attachmentPreview;

        session++;
        if (viewer) { viewer.destroy(); viewer = null; }

        title.textContent = name;
        download.href = link.dataset.attachmentDownload;
        download.setAttribute('download', name);
        body.replaceChildren();
        setToolbar(false);

        if (mime === 'application/pdf') {
            showPdf(previewUrl, name, session);
        } else if (/^image\/(png|jpe?g|gif|webp)$/.test(mime)) {
            showImage(previewUrl, name);
        } else {
            body.appendChild(message('<p class="mb-1 fw-semibold">This file type can&rsquo;t be previewed in the browser.</p>'
                + '<p class="mb-0 small">Use Download to open it in its own application.</p>'));
        }

        modal = modal || new bootstrap.Modal(el);
        modal.show();
    }

    toolbar.addEventListener('click', function (event) {
        const button = event.target.closest('[data-zoom]');
        if (! button) return;
        const action = button.dataset.zoom;
        if (action === 'in') stepZoom(1);
        else if (action === 'out') stepZoom(-1);
        else zoomTo(1);
    });

    // Keyboard: + / - to zoom, 0 for fit.
    document.addEventListener('keydown', function (e) {
        if (! viewer || ! el.classList.contains('show') || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key === '+' || e.key === '=') { e.preventDefault(); stepZoom(1); }
        else if (e.key === '-') { e.preventDefault(); stepZoom(-1); }
        else if (e.key === '0') { e.preventDefault(); zoomTo(1); }
    });

    // Rotating a phone or resizing the window changes what "fit" means.
    let resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (viewer && el.classList.contains('show')) viewer.apply();
        }, 150);
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-attachment-preview]');

        // Leave modified clicks alone (open in new tab etc.) and fall back to a plain download if Bootstrap's JS didn't load.
        if (! link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || ! window.bootstrap) {
            return;
        }

        event.preventDefault();
        show(link);
    });

    // Drop the loaded file when the viewer closes so nothing keeps loading or rendering in the background.
    el.addEventListener('hidden.bs.modal', function () {
        session++;
        if (viewer) { viewer.destroy(); viewer = null; }
        body.replaceChildren();
        setToolbar(false);
    });
})();
</script>
