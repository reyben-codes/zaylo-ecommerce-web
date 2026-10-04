(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finishers = [];

    document.querySelectorAll('.product-sale').forEach(details => {
        const summary = details.querySelector('summary');
        const form = details.querySelector('form');
        if (!summary || !form || !details.animate) return;

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
            form.inert = false;
            summary.setAttribute('aria-expanded', String(expanded));
        };

        summary.addEventListener('click', event => {
            event.preventDefault();
            const startHeight = details.getBoundingClientRect().height;
            expanded = !expanded;
            if (animation) {
                animation.onfinish = null;
                animation.cancel();
                animation = null;
            }
            if (reducedMotion.matches) { finish(); return; }

            // Keep native details open while animating its height in either direction.
            details.open = true;
            const style = getComputedStyle(details);
            const closedHeight = summary.getBoundingClientRect().height
                + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom)
                + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
            const endHeight = expanded ? details.getBoundingClientRect().height : closedHeight;
            details.style.overflow = 'hidden';
            form.inert = !expanded;
            summary.setAttribute('aria-expanded', String(expanded));
            animation = details.animate(
                [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
                { duration: 280, easing: 'cubic-bezier(.22, 1, .36, 1)' },
            );
            animation.onfinish = finish;
        });
        finishers.push(finish);
    });

    // Return to natural height if the layout or motion preference changes mid-animation.
    window.addEventListener('resize', () => finishers.forEach(finish => finish()));
    reducedMotion.addEventListener('change', () => finishers.forEach(finish => finish()));
})();
