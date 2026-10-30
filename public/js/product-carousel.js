(() => {
    const slideDelay = 1800;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let controllers = [];

    const initCardSlides = (root) => root.querySelectorAll('img[data-card-slides]').forEach((image) => {
        const sources = JSON.parse(image.dataset.cardSlides);
        if (sources.length < 2) return;

        const hoverTarget = image.closest('.shop-card, .product-card') || image.parentElement;
        const count = image.parentElement.querySelector('[data-photo-count]');
        let current = 0;
        let hovered = false;
        let timer;
        let fadeTimeout;
        let generation = 0;
        let changing = false;

        function canPlay() {
            return hovered && !document.hidden && !reducedMotion.matches;
        }

        function stop() {
            window.clearInterval(timer);
            window.clearTimeout(fadeTimeout);
            timer = undefined;
            fadeTimeout = undefined;
            generation++;
            changing = false;
            image.classList.remove('is-changing');
        }

        function reset() {
            stop();
            current = 0;
            image.src = sources[0];
            if (count) count.textContent = `1 / ${sources.length}`;
        }

        function nextImage() {
            if (!canPlay() || changing) return;
            changing = true;
            const next = (current + 1) % sources.length;
            const activeGeneration = generation;
            const preload = new Image();

            preload.onload = () => {
                if (activeGeneration !== generation || !canPlay()) return;

                image.classList.add('is-changing');
                fadeTimeout = window.setTimeout(() => {
                    fadeTimeout = undefined;
                    if (activeGeneration !== generation || !canPlay()) return;

                    image.src = sources[next];
                    current = next;
                    if (count) count.textContent = `${current + 1} / ${sources.length}`;
                    image.classList.remove('is-changing');
                    changing = false;
                }, 180);
            };
            preload.onerror = () => {
                if (activeGeneration === generation) changing = false;
            };
            preload.src = sources[next];
        }

        function start() {
            if (timer || !canPlay()) return;
            timer = window.setInterval(nextImage, slideDelay);
        }

        hoverTarget.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'mouse') return;
            hovered = true;
            start();
        });
        hoverTarget.addEventListener('pointerleave', (event) => {
            if (event.pointerType !== 'mouse') return;
            hovered = false;
            reset();
        });

        controllers.push({
            image,
            sync() {
                if (hovered && hoverTarget.matches(':hover') && canPlay()) start();
                else {
                    if (!hoverTarget.matches(':hover')) hovered = false;
                    reset();
                }
            },
        });
    });

    initCardSlides(document);
    // Live search (live-search.js) replaces the catalog grid; start the new cards and forget the removed ones.
    document.addEventListener('zaylo:products-updated', (event) => {
        controllers = controllers.filter((controller) => !controller.image || controller.image.isConnected);
        initCardSlides(event.target);
    });

    // Shared full-screen zoom lightbox for product detail galleries.
    let lightbox = null;
    const ensureLightbox = () => {
        if (lightbox) return lightbox;
        const root = document.createElement('div');
        root.className = 'product-zoom-lightbox';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', 'Zoomed product photo');
        root.hidden = true;
        root.innerHTML = `
            <header class="product-zoom-lightbox__bar">
                <span class="product-zoom-lightbox__count" data-zoom-count></span>
                <div class="product-zoom-lightbox__controls">
                    <button type="button" class="product-zoom-lightbox__btn" data-zoom-out aria-label="Zoom out" title="Zoom out">
                        <i class="fas fa-minus" aria-hidden="true"></i>
                    </button>
                    <span class="product-zoom-lightbox__level" data-zoom-level>100%</span>
                    <button type="button" class="product-zoom-lightbox__btn" data-zoom-in aria-label="Zoom in" title="Zoom in">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="product-zoom-lightbox__btn" data-zoom-close aria-label="Close zoom" title="Close (Esc)">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </header>
            <div class="product-zoom-lightbox__stage" data-zoom-stage>
                <button type="button" class="product-zoom-lightbox__btn product-zoom-lightbox__nav product-zoom-lightbox__nav--prev" data-zoom-prev aria-label="Previous photo">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <img class="product-zoom-lightbox__img" data-zoom-img alt="">
                <button type="button" class="product-zoom-lightbox__btn product-zoom-lightbox__nav product-zoom-lightbox__nav--next" data-zoom-next aria-label="Next photo">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        `;
        document.body.append(root);

        const img = root.querySelector('[data-zoom-img]');
        const stage = root.querySelector('[data-zoom-stage]');
        const countEl = root.querySelector('[data-zoom-count]');
        const levelEl = root.querySelector('[data-zoom-level]');
        const zoomInBtn = root.querySelector('[data-zoom-in]');
        const zoomOutBtn = root.querySelector('[data-zoom-out]');
        const closeBtn = root.querySelector('[data-zoom-close]');
        const prevBtn = root.querySelector('[data-zoom-prev]');
        const nextBtn = root.querySelector('[data-zoom-next]');

        let items = [];
        let index = 0;
        let scale = 1;
        let panX = 0;
        let panY = 0;
        let dragging = false;
        let moved = false;
        let startX = 0;
        let startY = 0;
        let returnFocus = null;
        let onSelect = null;
        let closeTimer;

        const applyTransform = () => {
            if (scale <= 1) {
                panX = 0;
                panY = 0;
            }
            img.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
            stage.classList.toggle('is-zoomed', scale > 1);
            levelEl.textContent = `${Math.round(scale * 100)}%`;
            zoomOutBtn.disabled = scale <= 1;
            zoomInBtn.disabled = scale >= 4;
        };

        const setScale = (nextScale) => {
            scale = Math.max(1, Math.min(4, Number(nextScale.toFixed(2))));
            applyTransform();
        };

        const renderSlide = () => {
            const item = items[index];
            if (!item) return;
            img.src = item.src;
            img.alt = item.alt || '';
            countEl.textContent = items.length > 1 ? `${index + 1} / ${items.length}` : item.alt || '1 / 1';
            prevBtn.hidden = items.length < 2;
            nextBtn.hidden = items.length < 2;
            scale = 1;
            panX = 0;
            panY = 0;
            applyTransform();
        };

        const step = (delta) => {
            if (items.length < 2) return;
            index = (index + delta + items.length) % items.length;
            renderSlide();
            if (onSelect) onSelect(index);
        };

        const close = () => {
            root.classList.remove('is-open');
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(() => {
                root.hidden = true;
            }, reducedMotion.matches ? 0 : 240);
            if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
        };

        zoomInBtn.addEventListener('click', () => setScale(scale + 0.5));
        zoomOutBtn.addEventListener('click', () => setScale(scale - 0.5));
        closeBtn.addEventListener('click', close);
        prevBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            step(-1);
        });
        nextBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            step(1);
        });

        stage.addEventListener('wheel', (event) => {
            event.preventDefault();
            setScale(scale + (event.deltaY < 0 ? 0.25 : -0.25));
        }, { passive: false });

        stage.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button')) return;
            moved = false;
            if (scale <= 1) return;
            dragging = true;
            startX = event.clientX - panX;
            startY = event.clientY - panY;
            stage.classList.add('is-dragging');
            stage.setPointerCapture?.(event.pointerId);
        });

        stage.addEventListener('pointermove', (event) => {
            if (!dragging) return;
            const nextX = event.clientX - startX;
            const nextY = event.clientY - startY;
            if (Math.hypot(nextX - panX, nextY - panY) > 4) moved = true;
            panX = nextX;
            panY = nextY;
            applyTransform();
        });

        const endDrag = () => {
            if (!dragging) return;
            dragging = false;
            stage.classList.remove('is-dragging');
        };
        stage.addEventListener('pointerup', endDrag);
        stage.addEventListener('pointercancel', endDrag);

        stage.addEventListener('click', (event) => {
            if (event.target.closest('button') || moved) return;
            if (event.target === stage && scale <= 1) {
                close();
                return;
            }
            setScale(scale > 1 ? 1 : 2.25);
        });

        document.addEventListener('keydown', (event) => {
            if (root.hidden) return;
            if (event.key === 'Escape') close();
            else if (event.key === 'ArrowLeft') step(-1);
            else if (event.key === 'ArrowRight') step(1);
            else if (event.key === '+' || event.key === '=') setScale(scale + 0.5);
            else if (event.key === '-') setScale(scale - 0.5);
        });

        lightbox = {
            open({ slides, currentIndex = 0, trigger = null, onChange = null }) {
                window.clearTimeout(closeTimer);
                items = slides;
                index = currentIndex;
                returnFocus = trigger;
                onSelect = onChange;
                renderSlide();
                root.hidden = false;
                window.requestAnimationFrame(() => root.classList.add('is-open'));
                closeBtn.focus();
            },
        };
        return lightbox;
    };

    document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
        const mainImage = gallery.querySelector('[data-gallery-main]');
        const frame = gallery.querySelector('[data-gallery-frame]');
        const zoomBtn = gallery.querySelector('[data-gallery-zoom]');
        const soldOutLabel = gallery.querySelector('[data-sold-out-label]');
        const thumbnails = [...gallery.querySelectorAll('[data-gallery-thumbnail]')];
        if (!mainImage || !frame) return;

        let current = 0;
        let hovered = false;
        let timer;
        let fadeTimeout;
        let generation = 0;
        let changing = false;

        // Cursor-following hover zoom inside the product frame.
        frame.addEventListener('pointermove', (event) => {
            if (event.pointerType !== 'mouse') return;
            if (event.target.closest('button')) {
                frame.classList.remove('is-zooming');
                return;
            }
            const rect = frame.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            const x = ((event.clientX - rect.left) / rect.width) * 100;
            const y = ((event.clientY - rect.top) / rect.height) * 100;
            frame.style.setProperty('--zoom-x', `${Math.max(0, Math.min(100, x)).toFixed(1)}%`);
            frame.style.setProperty('--zoom-y', `${Math.max(0, Math.min(100, y)).toFixed(1)}%`);
            frame.classList.add('is-zooming');
        });

        frame.addEventListener('pointerleave', () => {
            frame.classList.remove('is-zooming');
        });

        const getSlides = () => thumbnails.length
            ? thumbnails.map((thumb) => ({ src: thumb.dataset.imageSrc, alt: thumb.dataset.imageAlt }))
            : [{ src: mainImage.src, alt: mainImage.alt }];

        const openZoom = (trigger) => {
            frame.classList.remove('is-zooming');
            ensureLightbox().open({
                slides: getSlides(),
                currentIndex: current,
                trigger,
                onChange: (nextIndex) => {
                    if (thumbnails.length >= 2) setImage(nextIndex);
                },
            });
        };

        zoomBtn?.addEventListener('click', (event) => {
            event.stopPropagation();
            openZoom(zoomBtn);
        });
        frame.addEventListener('click', (event) => {
            if (event.target.closest('button')) return;
            openZoom(zoomBtn || mainImage);
        });

        if (thumbnails.length < 2) return;

        function setImage(index) {
            current = (index + thumbnails.length) % thumbnails.length;
            const selected = thumbnails[current];
            mainImage.src = selected.dataset.imageSrc;
            mainImage.alt = selected.dataset.imageAlt;
            // Photos of a sold-out option (or a fully sold-out product) are shown grayed with a label.
            const soldOut = frame?.hasAttribute('data-all-sold-out') || selected.hasAttribute('data-sold-out');
            frame?.classList.toggle('is-sold-out', soldOut);
            if (soldOutLabel) soldOutLabel.hidden = !soldOut;

            thumbnails.forEach((thumbnail, position) => {
                const active = position === current;
                thumbnail.classList.toggle('is-active', active);
                thumbnail.setAttribute('aria-pressed', String(active));
            });
        }

        function cancelTransition() {
            window.clearTimeout(fadeTimeout);
            fadeTimeout = undefined;
            generation++;
            changing = false;
            mainImage.classList.remove('is-changing');
        }

        function showImage(index, animate = true) {
            const next = (index + thumbnails.length) % thumbnails.length;
            if (next === current) {
                if (changing) cancelTransition();
                return;
            }
            cancelTransition();

            if (!animate || reducedMotion.matches) {
                setImage(next);
                return;
            }

            changing = true;
            const activeGeneration = generation;
            const preload = new Image();
            preload.onload = () => {
                if (activeGeneration !== generation) return;

                mainImage.classList.add('is-changing');
                fadeTimeout = window.setTimeout(() => {
                    fadeTimeout = undefined;
                    if (activeGeneration !== generation) return;

                    setImage(next);
                    mainImage.classList.remove('is-changing');
                    changing = false;
                }, 180);
            };
            preload.onerror = () => {
                if (activeGeneration === generation) changing = false;
            };
            preload.src = thumbnails[next].dataset.imageSrc;
        }

        function stop() {
            window.clearInterval(timer);
            timer = undefined;
        }

        function reset() {
            stop();
            cancelTransition();
            setImage(0);
        }

        function start() {
            if (timer || !hovered || frame.classList.contains('is-zooming') || document.hidden || reducedMotion.matches || gallery.querySelector(':focus-visible')) return;
            timer = window.setInterval(() => {
                if (!changing && !frame.classList.contains('is-zooming')) showImage(current + 1);
            }, slideDelay);
        }

        function navigate(index) {
            showImage(index);
            stop();
            start();
        }

        gallery.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'mouse') return;
            hovered = true;
            start();
        });
        gallery.addEventListener('pointerleave', (event) => {
            if (event.pointerType !== 'mouse') return;
            hovered = false;
            reset();
        });
        thumbnails.forEach((thumbnail, index) => thumbnail.addEventListener('click', () => navigate(index)));
        gallery.querySelector('[data-gallery-prev]').addEventListener('click', () => navigate(current - 1));
        gallery.querySelector('[data-gallery-next]').addEventListener('click', () => navigate(current + 1));
        gallery.addEventListener('focusin', (event) => {
            if (event.target.matches(':focus-visible')) stop();
        });
        gallery.addEventListener('focusout', () => window.setTimeout(start, 0));

        controllers.push({
            sync() {
                if (hovered && gallery.matches(':hover') && !document.hidden && !reducedMotion.matches) start();
                else {
                    if (!gallery.matches(':hover')) hovered = false;
                    reset();
                }
            },
        });
    });

    document.addEventListener('click', (event) => {
        const card = event.target.closest('[data-product-url]');
        if (!card || event.target.closest('a, button, input, select, textarea, label, form')) return;

        window.location.assign(card.dataset.productUrl);
    });

    document.addEventListener('visibilitychange', () => controllers.forEach((controller) => controller.sync()));
    reducedMotion.addEventListener('change', () => controllers.forEach((controller) => controller.sync()));
})();
