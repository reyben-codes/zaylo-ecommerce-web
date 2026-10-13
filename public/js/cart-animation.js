(() => {
    const notice = document.querySelector('[data-cart-feedback]');
    if (!notice || !window.fetch) return;
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let pending = false, noticeTimer;
    const tell = message => {
        clearTimeout(noticeTimer);
        notice.textContent = message;
        notice.hidden = false;
    };
    const updateCartCount = quantity => {
        document.querySelectorAll('[data-cart-link]').forEach(link => {
            let badge = link.querySelector('[data-cart-count]');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'nav-item-count';
                badge.setAttribute('data-cart-count', '');
                badge.setAttribute('aria-hidden', 'true');
                link.append(badge);
            }
            badge.textContent = String(quantity);
            badge.hidden = quantity === 0;
            link.setAttribute('aria-label', `Shopping cart (${quantity} items)`);
        });
    };
    const animateSuccess = async button => {
        if (reducedMotion.matches || !button?.animate) return;
        const target = document.querySelector('[data-cart-link]');
        const from = button.getBoundingClientRect();
        const to = target?.getBoundingClientRect();
        if (!to || to.width === 0 || to.bottom < 0 || to.top > innerHeight) {
            await button.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.05)' }, { transform: 'scale(1)' }], { duration: 320 }).finished;
            return;
        }
        const token = document.createElement('span');
        token.className = 'cart-animation-flight';
        token.textContent = '✓';
        token.setAttribute('aria-hidden', 'true');
        token.style.left = `${from.left + from.width / 2 - 24}px`;
        token.style.top = `${from.top + from.height / 2 - 24}px`;
        document.body.append(token);
        try {
            await token.animate([
                { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                { transform: `translate(${to.left + to.width / 2 - from.left - from.width / 2}px, ${to.top + to.height / 2 - from.top - from.height / 2}px) scale(.3)`, opacity: .3 },
            ], { duration: 480, easing: 'cubic-bezier(.22, 1, .36, 1)' }).finished;
            await target.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 200 }).finished;
        } finally { token.remove(); }
    };
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('form[data-cart-add]')) return;
        if (pending) { event.preventDefault(); return; }
        if (event.submitter?.value === 'buy_now') return;
        event.preventDefault();
        pending = true;
        const button = event.submitter || form.querySelector('button[type=submit], button:not([type])');
        const data = new FormData(form);
        if (event.submitter?.name) data.set(event.submitter.name, event.submitter.value);
        const buttons = [...form.querySelectorAll('button')].map(el => [el, el.disabled]);
        buttons.forEach(([el]) => { el.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        tell('Adding to your cart…');
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                tell(response.status === 422 ? Object.values(result.errors || {}).flat().join(' ') || 'Please check your product options.' : 'Unable to add this product. Refresh the page and check your cart before trying again.');
                return;
            }
            if (!Number.isInteger(result.cart_quantity) || result.cart_quantity < 0) throw new Error('Unexpected response');
            updateCartCount(result.cart_quantity);
            tell('Added to cart! Keep browsing whenever you’re ready.');
            noticeTimer = setTimeout(() => { notice.hidden = true; }, 4500);
            try { await animateSuccess(button); } catch { /* Animation must not prevent further shopping. */ }
        } catch {
            tell('We could not confirm the update. Check your cart before trying again.');
        } finally {
            pending = false;
            buttons.forEach(([el, disabled]) => { el.disabled = disabled; });
            form.removeAttribute('aria-busy');
        }
    });
})();
