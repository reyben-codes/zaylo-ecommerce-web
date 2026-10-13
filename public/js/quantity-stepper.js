(() => {
    document.querySelectorAll('[data-quantity-stepper]').forEach(stepper => {
        const input = stepper.querySelector('input');
        const minus = stepper.querySelector('[data-quantity-change="-1"]');
        const plus = stepper.querySelector('[data-quantity-change="1"]');
        const updateButton = input.form?.querySelector('[data-quantity-update]');
        const originalQuantity = Number(input.defaultValue);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const actions = input.form?.closest('.cart-item-actions');
        const siblings = actions ? [...actions.children].filter(el => el !== input.form) : [];
        let updateVisible = false, animations = [], revision = 0;
        const cancelAnimations = () => { animations.forEach(animation => animation.cancel()); animations = []; };
        const play = (element, frames, options = {}) => {
            const animation = element.animate(frames, { duration: 300, easing: 'cubic-bezier(.22, 1, .36, 1)', ...options });
            animations.push(animation);
            return animation.finished.catch(() => {});
        };
        const settle = () => {
            revision++;
            cancelAnimations();
            if (updateButton) {
                updateButton.hidden = !updateVisible;
                updateButton.disabled = !updateVisible;
            }
        };
        const showUpdate = async changed => {
            if (!updateButton || changed === updateVisible) return;
            updateVisible = changed;
            const currentRevision = ++revision;
            const positions = siblings.map(el => el.getBoundingClientRect());
            const height = actions?.getBoundingClientRect().height;
            const opacity = updateButton.hidden ? 0 : Number(getComputedStyle(updateButton).opacity);
            cancelAnimations();
            updateButton.disabled = true;
            if (!changed && document.activeElement === updateButton) input.focus({ preventScroll: true });
            if (reducedMotion.matches || !updateButton.animate) { settle(); return; }

            // Commit the new layout, then animate controls from their visible positions.
            // On collapse, fade Update before releasing its space.
            if (!changed && !updateButton.hidden) {
                const holds = siblings.map((el, index) => {
                    const rect = el.getBoundingClientRect();
                    const transform = `translate(${positions[index].left - rect.left}px, ${positions[index].top - rect.top}px)`;
                    return play(el, [{ transform }, { transform }], { duration: 140, fill: 'forwards' });
                });
                await Promise.all([...holds, play(updateButton, [{ opacity }, { opacity: 0 }], { duration: 140, fill: 'forwards' })]);
                if (currentRevision !== revision) return;
            }
            const before = changed ? positions : siblings.map(el => el.getBoundingClientRect());
            const beforeHeight = changed ? height : actions?.getBoundingClientRect().height;
            cancelAnimations();
            updateButton.hidden = !changed;
            const moves = siblings.map((el, index) => {
                const rect = el.getBoundingClientRect();
                return play(el, [
                    { transform: `translate(${before[index].left - rect.left}px, ${before[index].top - rect.top}px)` },
                    { transform: 'translate(0, 0)' },
                ]);
            });
            if (actions) moves.push(play(actions, [{ height: `${beforeHeight}px` }, { height: `${actions.getBoundingClientRect().height}px` }]));
            if (changed) {
                // Stay invisible during the shift, then fade in once its space is ready.
                moves.push(play(updateButton, [{ opacity: 0 }, { opacity: 1 }], { delay: 300, duration: 180, fill: 'backwards' }));
            }
            await Promise.all(moves);
            if (currentRevision === revision) settle();
        };
        reducedMotion.addEventListener('change', () => {
            if (reducedMotion.matches) settle();
        });
        window.addEventListener('resize', settle);
        const bounds = () => ({ min: Number(input.min) || 1, max: Math.min(99, input.max === '' ? 99 : Number(input.max)) });
        const sync = () => {
            const { min, max } = bounds();
            const value = input.valueAsNumber;
            showUpdate(value !== originalQuantity);
            const busy = input.form?.getAttribute('aria-busy') === 'true';
            minus.disabled = input.disabled || busy || max < min || (Number.isFinite(value) && value <= min);
            plus.disabled = input.disabled || busy || max < min || (Number.isFinite(value) && value >= max);
        };
        const normalize = () => {
            const { min, max } = bounds();
            if (max >= min) input.value = Math.min(max, Math.max(min, Math.trunc(input.valueAsNumber || min)));
            sync();
        };
        stepper.querySelectorAll('button').forEach(button => {
            button.hidden = false;
            button.addEventListener('click', () => {
                const { min, max } = bounds();
                const value = input.valueAsNumber;
                input.value = Math.min(max, Math.max(min, Number.isFinite(value) ? Math.trunc(value) + Number(button.dataset.quantityChange) : min));
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        input.addEventListener('input', sync);
        input.addEventListener('change', normalize);
        new MutationObserver(normalize).observe(input, { attributes: true, attributeFilter: ['min', 'max'] });
        new MutationObserver(sync).observe(input, { attributes: true, attributeFilter: ['disabled'] });
        if (input.form) {
            new MutationObserver(sync).observe(input.form, { attributes: true, attributeFilter: ['aria-busy'] });
            input.form.addEventListener('reset', () => setTimeout(sync, 0));
        }
        stepper.classList.add('is-enhanced');
        sync();
    });
})();
