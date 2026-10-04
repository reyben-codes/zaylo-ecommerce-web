(() => {
    const initializeImageFallbacks = () => {
        document.querySelectorAll('img[data-fallback-src]').forEach((image) => {
            const useFallback = () => {
                const fallbackSource = image.dataset.fallbackSrc;

                if (!fallbackSource || image.src === new URL(fallbackSource, window.location.href).href) {
                    return;
                }

                image.src = fallbackSource;
            };

            image.addEventListener('error', useFallback, { once: true });

            if (image.complete && image.naturalWidth === 0) {
                useFallback();
            }
        });
    };

    const initializeHeroCarousel = () => {
        const hero = document.getElementById('home-hero');

        if (!hero) {
            return;
        }

        const slides = [...hero.querySelectorAll('.hero-slide')];
        const dots = [...hero.querySelectorAll('[data-hero-slide]')];
        const previousButton = hero.querySelector('[data-hero-prev]');
        const nextButton = hero.querySelector('[data-hero-next]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let current = 0;
        let timer;

        if (!slides.length || !previousButton || !nextButton) {
            return;
        }

        const showSlide = (index) => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, position) => slide.classList.toggle('is-active', position === current));
            dots.forEach((dot, position) => dot.setAttribute('aria-pressed', String(position === current)));
        };

        const stopRotation = () => {
            window.clearInterval(timer);
            timer = undefined;
        };

        const startRotation = () => {
            if (timer || reducedMotion.matches || document.hidden || hero.querySelector(':focus-visible')) {
                return;
            }

            timer = window.setInterval(() => showSlide(current + 1), 3500);
        };

        const navigateTo = (index) => {
            showSlide(index);
            stopRotation();
            startRotation();
        };

        previousButton.addEventListener('click', () => navigateTo(current - 1));
        nextButton.addEventListener('click', () => navigateTo(current + 1));
        dots.forEach((dot, index) => dot.addEventListener('click', () => navigateTo(index)));
        hero.addEventListener('focusin', (event) => {
            if (event.target.matches(':focus-visible')) {
                stopRotation();
            }
        });
        hero.addEventListener('focusout', () => window.setTimeout(startRotation, 0));
        document.addEventListener('visibilitychange', () => document.hidden ? stopRotation() : startRotation());
        reducedMotion.addEventListener('change', () => reducedMotion.matches ? stopRotation() : startRotation());
        startRotation();
    };

    initializeImageFallbacks();
    initializeHeroCarousel();
})();
