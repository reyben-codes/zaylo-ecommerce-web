(() => {
    const notice = document.querySelector('[data-wishlist-feedback]');
    if (!notice || !window.fetch) return;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let pending = false, noticeTimer;
    const activeAnimations = new Set();
    const animate = async (element, frames, options) => {
        const animation = element.animate(frames, options);
        activeAnimations.add(animation);
        try { await animation.finished; } catch { /* Motion preference may change. */ }
        finally { activeAnimations.delete(animation); }
    };
    motion.addEventListener('change', () => { if (motion.matches) activeAnimations.forEach(a => a.finish()); });
    const flyHeart = async button => {
        if (motion.matches || !button.animate) return;
        const target = document.querySelector('[data-wishlist-link]');
        const from = button.getBoundingClientRect(), to = target?.getBoundingClientRect();
        const visible = to && to.width > 0 && to.top >= 0 && to.bottom <= innerHeight && to.left >= 0 && to.right <= innerWidth;
        const heart = document.createElement('span');
        heart.className = 'wishlist-flying-heart';
        heart.setAttribute('aria-hidden', 'true');
        heart.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 21S2 15 2 8a5 5 0 0 1 10-1 5 5 0 0 1 10 1c0 7-10 13-10 13Z"/></svg>';
        heart.style.left = `${from.left + from.width / 2 - 18}px`;
        heart.style.top = `${from.top + from.height / 2 - 18}px`;
        document.body.append(heart);
        const dx = visible ? to.left + to.width / 2 - from.left - from.width / 2 : 0;
        const dy = visible ? to.top + to.height / 2 - from.top - from.height / 2 : -90;
        try {
            await animate(heart, [
                { transform: 'translate(0, 0) scale(.7)', opacity: 1 },
                { transform: `translate(${dx * .4}px, ${dy * .4 - 35}px) scale(1.2)`, opacity: 1, offset: .4 },
                { transform: `translate(${dx}px, ${dy}px) scale(.35)`, opacity: 0 },
            ], { duration: 650, easing: 'cubic-bezier(.22, 1, .36, 1)' });
            if (visible && !motion.matches) await animate(target, [{ transform: 'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 240 });
        } finally { heart.remove(); }
    };
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('form[data-wishlist-toggle]')) return;
        event.preventDefault();
        if (pending) return;
        pending = true;
        const button = event.submitter || form.querySelector('button');
        const wasDisabled = button.disabled;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        clearTimeout(noticeTimer);
        notice.hidden = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || typeof data.saved !== 'boolean' || !Number.isInteger(data.count)) throw new Error('Wishlist update failed');
            document.querySelectorAll('[data-wishlist-toggle]').forEach(other => {
                if (other.action !== form.action) return;
                const control = other.querySelector('button');
                control.setAttribute('aria-pressed', String(data.saved));
                control.setAttribute('aria-label', data.saved ? 'Remove product from wishlist' : 'Save product to wishlist');
                const icon = control.querySelector('.fa-heart');
                icon?.classList.toggle('fas', data.saved);
                icon?.classList.toggle('far', !data.saved);
            });
            document.querySelectorAll('[data-wishlist-link]').forEach(link => {
                let badge = link.querySelector('[data-wishlist-count]');
                if (!badge) {
                    badge = document.createElement('span'); badge.className = 'nav-item-count';
                    badge.setAttribute('data-wishlist-count', ''); badge.setAttribute('aria-hidden', 'true'); link.append(badge);
                }
                badge.textContent = String(data.count); badge.hidden = data.count === 0;
                link.setAttribute('aria-label', `Wishlist (${data.count} saved products)`);
            });
            notice.textContent = data.saved ? 'Saved to wishlist!' : 'Removed from wishlist.';
            notice.hidden = false;
            if (data.saved) { try { await flyHeart(button); } catch { /* Saving must not depend on animation. */ } }
            noticeTimer = setTimeout(() => { notice.hidden = true; }, 3500);
        } catch {
            notice.textContent = 'We could not confirm the update. Check your wishlist before trying again.';
            notice.hidden = false;
        } finally {
            pending = false; button.disabled = wasDisabled; form.removeAttribute('aria-busy');
        }
    });
})();
