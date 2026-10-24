// Opens uploaded documents (application files, buyer IDs) in a modal instead of a new tab.
// A link opts in with:
//   data-document-viewer               the link's href is the document
//   data-document-title="Business permit"  heading shown in the modal
//   data-document-type="image|pdf|file"     how to show it (see App\Support\DocumentPreview)
// Links inside the same [data-document-gallery] can be stepped through with Previous/Next or the arrow keys.
// Without JavaScript, or in browsers without <dialog>, the link opens the document in a new tab as before.
(() => {
    'use strict';

    if (typeof HTMLDialogElement !== 'function') return;

    let dialog = null;
    let current = null;
    let returnFocus = null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const build = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'document-viewer';
        dialog.setAttribute('aria-labelledby', 'documentViewerTitle');
        dialog.innerHTML = `
            <div class="document-viewer__panel">
                <header class="document-viewer__header">
                    <div class="document-viewer__heading">
                        <h2 class="document-viewer__title" id="documentViewerTitle"></h2>
                        <p class="document-viewer__count" data-viewer-count hidden></p>
                    </div>
                    <div class="document-viewer__tools">
                        <a class="document-viewer__tool" data-viewer-open target="_blank" rel="noopener">
                            <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i><span>New tab</span>
                        </a>
                        <button type="button" class="document-viewer__tool document-viewer__close" data-viewer-close aria-label="Close">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </header>
                <div class="document-viewer__body" data-viewer-body aria-live="polite"></div>
                <footer class="document-viewer__footer" data-viewer-nav hidden>
                    <button type="button" class="document-viewer__tool" data-viewer-prev>
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span>Previous</span>
                    </button>
                    <button type="button" class="document-viewer__tool" data-viewer-next>
                        <span>Next</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </footer>
            </div>`;

        dialog.querySelector('[data-viewer-close]').addEventListener('click', close);
        dialog.querySelector('[data-viewer-prev]').addEventListener('click', () => step(-1));
        dialog.querySelector('[data-viewer-next]').addEventListener('click', () => step(1));
        // Escape runs close() so the exit animation and focus return still happen.
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) close();
        });
        dialog.addEventListener('keydown', (event) => {
            // Arrow keys step through the gallery, except while the PDF frame or a control has its own use for them.
            if (event.target.closest('iframe')) return;
            if (event.key === 'ArrowLeft') step(-1);
            if (event.key === 'ArrowRight') step(1);
        });
        document.body.appendChild(dialog);
    };

    const siblings = (link) => {
        const gallery = link.closest('[data-document-gallery]');

        return gallery ? Array.from(gallery.querySelectorAll('a[data-document-viewer]')) : [link];
    };

    const message = (icon, text, link) => {
        const box = document.createElement('div');
        box.className = 'document-viewer__message';
        box.innerHTML = `<i class="fa-solid ${icon}" aria-hidden="true"></i><p></p>`;
        box.querySelector('p').textContent = text;
        if (link) {
            const open = document.createElement('a');
            open.href = link.href;
            open.target = '_blank';
            open.rel = 'noopener';
            open.className = 'document-viewer__fallback';
            open.textContent = 'Open in a new tab';
            box.appendChild(open);
        }

        return box;
    };

    const show = (link) => {
        current = link;
        const title = link.dataset.documentTitle || link.textContent.trim() || 'Document';
        const type = link.dataset.documentType || 'file';
        const body = dialog.querySelector('[data-viewer-body]');

        dialog.querySelector('#documentViewerTitle').textContent = title;
        dialog.querySelector('[data-viewer-open]').href = link.href;
        dialog.querySelector('[data-viewer-open]').setAttribute('aria-label', `Open ${title} in a new tab`);
        body.replaceChildren();
        body.classList.add('is-loading');

        if (type === 'image') {
            const image = new Image();
            image.className = 'document-viewer__image';
            image.alt = title;
            image.addEventListener('load', () => body.classList.remove('is-loading'));
            image.addEventListener('error', () => {
                body.classList.remove('is-loading');
                body.replaceChildren(message('fa-triangle-exclamation', 'This document could not be loaded.', link));
            });
            image.src = link.href;
            body.appendChild(image);
        } else if (type === 'pdf') {
            const frame = document.createElement('iframe');
            frame.className = 'document-viewer__frame';
            frame.title = title;
            frame.addEventListener('load', () => body.classList.remove('is-loading'));
            frame.src = link.href;
            body.appendChild(frame);
        } else {
            body.classList.remove('is-loading');
            body.appendChild(message('fa-file-lines', 'This file type cannot be previewed here.', link));
        }

        const all = siblings(link);
        const index = all.indexOf(link);
        const nav = dialog.querySelector('[data-viewer-nav]');
        const count = dialog.querySelector('[data-viewer-count]');
        nav.hidden = all.length < 2;
        count.hidden = all.length < 2;
        count.textContent = `${index + 1} of ${all.length}`;
        dialog.querySelector('[data-viewer-prev]').disabled = index <= 0;
        dialog.querySelector('[data-viewer-next]').disabled = index >= all.length - 1;
    };

    const step = (direction) => {
        if (!current) return;
        const all = siblings(current);
        const next = all[all.indexOf(current) + direction];
        if (next) show(next);
    };

    const open = (link) => {
        if (!dialog) build();
        returnFocus = link;
        dialog.classList.remove('is-closing');
        show(link);
        if (!dialog.open) dialog.showModal();
        dialog.querySelector('[data-viewer-close]').focus();
    };

    function close() {
        if (!dialog || !dialog.open || dialog.classList.contains('is-closing')) return;
        const finish = () => {
            dialog.classList.remove('is-closing');
            dialog.close();
            // Unload the document so a PDF viewer or large image does not stay in memory.
            dialog.querySelector('[data-viewer-body]').replaceChildren();
            current = null;
            if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
            returnFocus = null;
        };

        if (reducedMotion.matches) {
            finish();
            return;
        }
        dialog.classList.add('is-closing');
        let done = false;
        const onEnd = (event) => {
            if (done || (event && event.target !== dialog)) return;
            done = true;
            dialog.removeEventListener('animationend', onEnd);
            finish();
        };
        dialog.addEventListener('animationend', onEnd);
        window.setTimeout(onEnd, 300);
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-document-viewer]');
        // Ctrl/Cmd/Shift/middle-click keep the browser's own "open in new tab/window".
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        open(link);
    });
})();
