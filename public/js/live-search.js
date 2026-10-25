// Search as you type. The header search shows the best matching products in a dropdown; the catalog page's
// search refreshes its results in place. Both fall back to a normal form submit without JavaScript.
(() => {
    const debounce = (callback, delay) => {
        let timer;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    };

    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);

    // Header dropdown -------------------------------------------------------------------------------------------
    document.querySelectorAll('form[data-live-search]').forEach((form) => {
        const input = form.querySelector('input[name="search"]');
        const list = form.querySelector('.live-search');
        if (!input || !list) return;

        let controller;
        let options = [];
        let active = -1;

        const close = () => {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        };

        const highlight = (index) => {
            options.forEach((option, position) => option.setAttribute('aria-selected', position === index ? 'true' : 'false'));
            active = index;
            if (index < 0) input.removeAttribute('aria-activedescendant');
            else {
                input.setAttribute('aria-activedescendant', options[index].id);
                options[index].scrollIntoView({ block: 'nearest' });
            }
        };

        const render = (term, products) => {
            const allUrl = `${form.action}?search=${encodeURIComponent(term)}`;
            const items = products.map((product, index) => `
                <a class="live-search__item" id="live-search-option-${index}" role="option" aria-selected="false" href="${escapeHtml(product.url)}">
                    <img src="${escapeHtml(product.image)}" alt="" loading="lazy"${product.sold_out ? ' class="is-sold-out"' : ''}>
                    <span class="live-search__name">${escapeHtml(product.name)}</span>
                    <span class="live-search__price">${product.sold_out ? 'Sold out' : escapeHtml(product.price)}</span>
                </a>`).join('');
            list.innerHTML = (items || `<p class="live-search__empty">No products match “${escapeHtml(term)}”.</p>`)
                + `<a class="live-search__all" id="live-search-option-all" role="option" aria-selected="false" href="${escapeHtml(allUrl)}">See all results for “${escapeHtml(term)}”</a>`;
            options = [...list.querySelectorAll('[role="option"]')];
            active = -1;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const suggest = debounce(async () => {
            const term = input.value.trim().replace(/\s+/g, ' ');
            controller?.abort();
            if (!term) {
                close();
                return;
            }
            controller = new AbortController();
            try {
                const response = await fetch(`${form.action}?search=${encodeURIComponent(term)}&suggest=1`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) return;
                const data = await response.json();
                // Ignore a reply that arrives after the box was cleared or left.
                if (input.value.trim() && document.activeElement === input) render(term, data.products || []);
            } catch (error) {
                if (error.name !== 'AbortError') close();
            }
        }, 220);

        input.addEventListener('input', suggest);
        input.addEventListener('focus', () => {
            if (input.value.trim() && options.length) {
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (!list.hidden) {
                    event.preventDefault();
                    close();
                }
                return;
            }
            if (list.hidden || !options.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                // Cycle through the options and back to the text box (-1).
                let next = active + (event.key === 'ArrowDown' ? 1 : -1);
                if (next >= options.length) next = -1;
                if (next < -1) next = options.length - 1;
                highlight(next);
            } else if (event.key === 'Enter' && active >= 0) {
                event.preventDefault();
                window.location.assign(options[active].href);
            }
        });
        // Clicks inside the list land before the input loses focus, so close a moment later.
        input.addEventListener('blur', () => window.setTimeout(() => {
            if (!form.contains(document.activeElement)) close();
        }, 120));
        list.addEventListener('mousedown', (event) => event.preventDefault());
        list.addEventListener('pointermove', (event) => {
            const option = event.target.closest('[role="option"]');
            if (option) highlight(options.indexOf(option));
        });
    });

    // Catalog page ----------------------------------------------------------------------------------------------
    const catalog = document.querySelector('form[data-live-catalog]');
    const results = document.querySelector('[data-live-catalog-results]');
    if (!catalog || !results) return;

    const search = catalog.querySelector('input[name="search"]');
    const sort = catalog.querySelector('select[name="sort"]');
    const status = catalog.querySelector('[data-live-catalog-status]');
    let sortChosen = new URL(window.location.href).searchParams.has('sort');
    let controller;

    sort?.addEventListener('change', () => { sortChosen = true; });

    const refresh = debounce(async () => {
        const params = new URLSearchParams();
        new FormData(catalog).forEach((value, key) => {
            if (String(value).trim() === '') return;
            // Until the buyer picks a sort, let the server choose: best match while searching, otherwise newest.
            if (key === 'sort' && !sortChosen) return;
            params.set(key, String(value).trim());
        });
        if (params.get('availability') === 'all') params.delete('availability');
        const url = `${catalog.action}${params.toString() ? `?${params}` : ''}`;

        controller?.abort();
        controller = new AbortController();
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html' }, signal: controller.signal });
            if (!response.ok) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = page.querySelector('[data-live-catalog-results]');
            if (!fresh) return;
            results.innerHTML = fresh.innerHTML;
            const freshSort = page.querySelector('form[data-live-catalog] select[name="sort"]');
            if (sort && freshSort && !sortChosen) sort.value = freshSort.value;
            if (status) status.textContent = results.querySelector('.catalog-results-heading [role="status"]')?.textContent.replace(/\s+/g, ' ').trim() || '';
            window.history.replaceState(window.history.state, '', url);
            results.dispatchEvent(new CustomEvent('zaylo:products-updated', { bubbles: true }));
        } catch (error) {
            if (error.name !== 'AbortError') results.removeAttribute('aria-busy');
            return;
        }
        results.removeAttribute('aria-busy');
    }, 300);

    search?.addEventListener('input', refresh);
})();
