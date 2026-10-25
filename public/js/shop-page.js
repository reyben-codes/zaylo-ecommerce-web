// Shop page: follow without reloading (the form still works without JavaScript) and share the shop's link.
(() => {
    const status = document.querySelector('[data-shop-status]');
    const say = (text) => { if (status) status.textContent = text; };

    const form = document.querySelector('[data-follow-form]');
    const button = form?.querySelector('[data-follow-button]');
    const count = document.querySelector('[data-follower-count]');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(String(response.status));
            const { following, followers } = await response.json();
            button.classList.toggle('is-following', following);
            button.setAttribute('aria-pressed', String(following));
            button.querySelector('[data-follow-label]').textContent = following ? 'Following' : 'Follow';
            button.querySelector('i').className = `fas ${following ? 'fa-check' : 'fa-plus'}`;
            if (count) count.textContent = Number(followers).toLocaleString();
            say(following ? 'You are now following this shop.' : 'You unfollowed this shop.');
        } catch {
            form.submit(); // Fall back to a normal page load.
        } finally {
            button.disabled = false;
        }
    });

    const share = document.querySelector('[data-share-url]');
    if (share) {
        share.hidden = false;
        share.addEventListener('click', async () => {
            const url = share.dataset.shareUrl;
            try {
                if (navigator.share) {
                    await navigator.share({ title: share.dataset.shareTitle, url });
                } else {
                    await navigator.clipboard.writeText(url);
                    say('Shop link copied.');
                }
            } catch (error) {
                if (error?.name !== 'AbortError') say(`Copy this link to share: ${url}`);
            }
        });
    }
})();
