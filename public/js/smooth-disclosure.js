(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finishers = [];

    const setup = details => {
        const summary = details.querySelector(':scope > summary');
        const content = details.querySelector(':scope > [data-disclosure-content]');
        // Each element gets one handler, even if init() runs again over it.
        if (!summary || !content || !details.animate || details.dataset.smoothDisclosureReady) return;
        details.dataset.smoothDisclosureReady = 'true';

        let animation = null;
        let expanded = details.open;
        const finish = () => {
            if (animation) {
                animation.onfinish = null;
                animation.cancel();
                animation = null;
            }
            details.open = expanded;
            details.style.overflow = '';
            content.inert = false;
            summary.setAttribute('aria-expanded', String(expanded));
        };
        finish();

        summary.addEventListener('click', event => {
            event.preventDefault();
            // Measure the current rendered height before cancelling a rapid toggle.
            const startHeight = details.getBoundingClientRect().height;
            expanded = !expanded;
            if (animation) {
                animation.onfinish = null;
                animation.cancel();
                animation = null;
            }
            if (reducedMotion.matches) { finish(); return; }

            // Keep the content rendered until the closing animation completes.
            details.open = true;
            const style = getComputedStyle(details);
            const closedHeight = summary.getBoundingClientRect().height
                + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom)
                + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
            const endHeight = expanded ? details.getBoundingClientRect().height : closedHeight;
            details.style.overflow = 'hidden';
            content.inert = !expanded;
            summary.setAttribute('aria-expanded', String(expanded));
            animation = details.animate(
                [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
                { duration: 320, easing: 'cubic-bezier(.22, 1, .36, 1)' },
            );
            animation.onfinish = finish;
        });
        finishers.push(finish);
    };
    const init = (root = document) => root.querySelectorAll('details[data-smooth-disclosure]').forEach(setup);
    init();
    // Lets scripts that add disclosures later (e.g. new color cards) animate them too.
    window.SmoothDisclosure = { init };

    window.addEventListener('resize', () => finishers.forEach(finish => finish()));
    reducedMotion.addEventListener('change', () => finishers.forEach(finish => finish()));
})();
