(() => {
    const slideDelay = 1800;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const controllers = [];

    document.querySelectorAll('img[data-card-slides]').forEach((image) => {
        const sources = JSON.parse(image.dataset.cardSlides);
        if (sources.length < 2) return;

        const hoverTarget = image.closest('.product-card, .flash-product, .home-product-card') || image.parentElement;
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
            sync() {
                if (hovered && hoverTarget.matches(':hover') && canPlay()) start();
                else {
                    if (!hoverTarget.matches(':hover')) hovered = false;
                    reset();
                }
            },
        });
    });

    document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
        const mainImage = gallery.querySelector('[data-gallery-main]');
        const thumbnails = [...gallery.querySelectorAll('[data-gallery-thumbnail]')];
        if (thumbnails.length < 2) return;

        let current = 0;
        let hovered = false;
        let timer;
        let fadeTimeout;
        let generation = 0;
        let changing = false;

        function setImage(index) {
            current = (index + thumbnails.length) % thumbnails.length;
            const selected = thumbnails[current];
            mainImage.src = selected.dataset.imageSrc;
            mainImage.alt = selected.dataset.imageAlt;

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
            if (timer || !hovered || document.hidden || reducedMotion.matches || gallery.querySelector(':focus-visible')) return;
            timer = window.setInterval(() => {
                if (!changing) showImage(current + 1);
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
