{{-- One viewer for every attachment link on every page (see <x-attachment-link>). --}}
<div class="modal fade" id="attachmentViewer" tabindex="-1" aria-labelledby="attachmentViewerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
            <div class="modal-body p-0 bg-light" style="height: 75vh;" data-viewer-body></div>
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
    let modal = null;

    function show(link) {
        const name = link.dataset.attachmentName || 'Attachment';
        const mime = (link.dataset.attachmentMime || '').toLowerCase();
        const previewUrl = link.dataset.attachmentPreview;

        title.textContent = name;
        download.href = link.dataset.attachmentDownload;
        download.setAttribute('download', name);
        body.replaceChildren();

        if (mime === 'application/pdf') {
            const frame = document.createElement('iframe');
            frame.src = previewUrl;
            frame.title = name;
            frame.style.cssText = 'width:100%;height:100%;border:0;';
            body.appendChild(frame);
        } else if (/^image\/(png|jpe?g|gif|webp)$/.test(mime)) {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'width:100%;height:100%;overflow:auto;display:flex;align-items:center;justify-content:center;padding:1rem;';
            const img = document.createElement('img');
            img.src = previewUrl;
            img.alt = name;
            img.style.cssText = 'max-width:100%;max-height:100%;object-fit:contain;';
            wrap.appendChild(img);
            body.appendChild(wrap);
        } else {
            const note = document.createElement('div');
            note.className = 'h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted p-4';
            note.innerHTML = '<p class="mb-1 fw-semibold">This file type can&rsquo;t be previewed in the browser.</p>'
                + '<p class="mb-0 small">Use Download to open it in its own application.</p>';
            body.appendChild(note);
        }

        modal = modal || new bootstrap.Modal(el);
        modal.show();
    }

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

    // Drop the loaded file when the viewer closes so a PDF stops loading/playing in the background.
    el.addEventListener('hidden.bs.modal', function () {
        body.replaceChildren();
    });
})();
</script>
