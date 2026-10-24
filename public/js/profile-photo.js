/**
 * Profile photo picker (partials/profile-photo). Choosing a file opens the crop dialog: the photo can be
 * dragged (mouse, touch or arrow keys) and zoomed (slider, wheel, pinch or + / −) inside the circle.
 * Saving sends the photo with the framed square as fractions of the photo (crop_x, crop_y, crop_width,
 * crop_height); the server does the actual crop and re-checks everything. Without <dialog> support the
 * photo uploads straight away and the server keeps a centered square.
 */
(function () {
    const MAX_BYTES = 5 * 1024 * 1024;
    const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const MAX_ZOOM = 4;
    // Zooming stops before the kept square gets smaller than this many photo pixels (blurry when enlarged).
    const MIN_CROP_PIXELS = 160;
    const KEY_STEP = 12;

    document.querySelectorAll('[data-profile-photo]').forEach((root) => {
        const form = root.querySelector('[data-profile-photo-form]');
        const input = root.querySelector('[data-profile-photo-input]');
        const label = root.querySelector('[data-profile-photo-label]');
        const error = root.querySelector('[data-profile-photo-error]');
        const dialog = root.querySelector('[data-photo-cropper]');
        if (!form || !input) return;

        root.classList.add('is-enhanced');
        const cropper = dialog && typeof dialog.showModal === 'function' ? createCropper(root, dialog) : null;

        const showError = (message) => {
            error.textContent = message;
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
        };

        const upload = (previewSrc) => {
            if (previewSrc) showPreview(root, previewSrc);
            root.classList.add('is-uploading');
            label.textContent = 'Uploading…';
            form.submit();
        };

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;
            if (!TYPES.includes(file.type)) {
                input.value = '';
                showError('Use a JPG, PNG or WebP photo.');
                return;
            }
            if (file.size > MAX_BYTES) {
                input.value = '';
                showError('The photo must be 5 MB or smaller.');
                return;
            }
            error.hidden = true;
            input.removeAttribute('aria-invalid');

            if (!cropper) {
                upload(URL.createObjectURL(file));
                return;
            }
            cropper.open(file, {
                onSave: (crop, previewSrc) => {
                    ['x', 'y', 'width', 'height'].forEach((part) => {
                        const field = form.querySelector(`[data-crop-${part}]`);
                        field.value = crop[part].toFixed(6);
                        field.disabled = false;
                    });
                    upload(previewSrc);
                },
                onCancel: () => {
                    input.value = '';
                    input.focus();
                },
                onError: () => {
                    input.value = '';
                    showError('This photo could not be opened. Try a JPG or PNG.');
                },
            });
        });
    });

    function createCropper(root, dialog) {
        const stage = dialog.querySelector('[data-cropper-stage]');
        const image = dialog.querySelector('[data-cropper-image]');
        const zoomInput = dialog.querySelector('[data-cropper-zoom]');
        // Photo pixel at the circle's centre, and how far the photo is zoomed past "just covers the circle".
        let state = null;
        let handlers = null;
        let objectUrl = null;
        const pointers = new Map();
        let pinchDistance = 0;

        const view = () => stage.clientWidth;
        const scale = () => (view() / Math.min(state.width, state.height)) * state.zoom;

        function clamp() {
            const half = view() / 2 / scale();
            state.cx = Math.min(Math.max(state.cx, half), state.width - half);
            state.cy = Math.min(Math.max(state.cy, half), state.height - half);
        }

        function render() {
            if (!state) return;
            clamp();
            const s = scale();
            const left = view() / 2 - state.cx * s;
            const top = view() / 2 - state.cy * s;
            image.style.transform = `translate(${left}px, ${top}px) scale(${s})`;
            zoomInput.value = String(state.zoom);
            zoomInput.setAttribute('aria-valuetext', `${Math.round(state.zoom * 100)}%`);
        }

        function setZoom(zoom) {
            state.zoom = Math.min(Math.max(zoom, 1), state.maxZoom);
            render();
        }

        function move(dx, dy) {
            const s = scale();
            state.cx -= dx / s;
            state.cy -= dy / s;
            render();
        }

        function close() {
            if (dialog.open) dialog.close();
            pointers.clear();
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            state = null;
        }

        stage.addEventListener('pointerdown', (event) => {
            if (!state) return;
            stage.setPointerCapture(event.pointerId);
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if (pointers.size === 2) pinchDistance = distance();
        });
        stage.addEventListener('pointermove', (event) => {
            const last = pointers.get(event.pointerId);
            if (!state || !last) return;
            const current = { x: event.clientX, y: event.clientY };
            pointers.set(event.pointerId, current);
            if (pointers.size === 2) {
                const next = distance();
                if (pinchDistance > 0) setZoom(state.zoom * (next / pinchDistance));
                pinchDistance = next;
            } else {
                move(current.x - last.x, current.y - last.y);
            }
        });
        const release = (event) => {
            pointers.delete(event.pointerId);
            pinchDistance = pointers.size === 2 ? distance() : 0;
        };
        stage.addEventListener('pointerup', release);
        stage.addEventListener('pointercancel', release);
        stage.addEventListener('wheel', (event) => {
            if (!state) return;
            event.preventDefault();
            setZoom(state.zoom * Math.exp(-event.deltaY * 0.0015));
        }, { passive: false });
        stage.addEventListener('keydown', (event) => {
            if (!state) return;
            const moves = { ArrowLeft: [KEY_STEP, 0], ArrowRight: [-KEY_STEP, 0], ArrowUp: [0, KEY_STEP], ArrowDown: [0, -KEY_STEP] };
            if (moves[event.key]) {
                move(...moves[event.key]);
            } else if (event.key === '+' || event.key === '=') {
                setZoom(state.zoom + 0.1);
            } else if (event.key === '-' || event.key === '_') {
                setZoom(state.zoom - 0.1);
            } else {
                return;
            }
            event.preventDefault();
        });

        function distance() {
            const [a, b] = [...pointers.values()];
            return Math.hypot(a.x - b.x, a.y - b.y);
        }

        zoomInput.addEventListener('input', () => state && setZoom(Number(zoomInput.value)));
        dialog.querySelector('[data-cropper-zoom-in]').addEventListener('click', () => state && setZoom(state.zoom + 0.25));
        dialog.querySelector('[data-cropper-zoom-out]').addEventListener('click', () => state && setZoom(state.zoom - 0.25));
        window.addEventListener('resize', render);

        dialog.querySelector('[data-cropper-cancel]').addEventListener('click', () => {
            close();
            handlers?.onCancel();
        });
        // Escape closes the dialog through its "cancel" event.
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close();
            handlers?.onCancel();
        });
        dialog.querySelector('[data-cropper-save]').addEventListener('click', () => {
            if (!state) return;
            const side = view() / scale();
            const left = state.cx - side / 2;
            const top = state.cy - side / 2;
            const crop = { x: left / state.width, y: top / state.height, width: side / state.width, height: side / state.height };

            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 240;
            canvas.getContext('2d').drawImage(image, left, top, side, side, 0, 0, 240, 240);
            const preview = canvas.toDataURL('image/jpeg', 0.9);

            const done = handlers;
            close();
            done.onSave(crop, preview);
        });

        return {
            open(file, nextHandlers) {
                handlers = nextHandlers;
                objectUrl = URL.createObjectURL(file);
                image.onload = () => {
                    const width = image.naturalWidth;
                    const height = image.naturalHeight;
                    image.style.width = `${width}px`;
                    image.style.height = `${height}px`;
                    const maxZoom = Math.max(1, Math.min(MAX_ZOOM, Math.min(width, height) / MIN_CROP_PIXELS));
                    state = { width, height, cx: width / 2, cy: height / 2, zoom: 1, maxZoom };
                    zoomInput.max = String(maxZoom);
                    zoomInput.disabled = maxZoom === 1;
                    dialog.showModal();
                    stage.focus();
                    render();
                };
                image.onerror = () => {
                    close();
                    handlers.onError();
                };
                image.src = objectUrl;
            },
        };
    }

    // Swap the initials for an <img> (or update the photo) so the new photo shows while it uploads.
    function showPreview(root, src) {
        const current = root.querySelector('.profile-photo__avatar');
        let image = current;
        if (current.tagName !== 'IMG') {
            image = document.createElement('img');
            image.className = current.className.replace('user-avatar--initials', '').trim();
            image.alt = '';
            current.replaceWith(image);
        }
        image.src = src;
    }
})();
