(() => {
    // Ask before submitting destructive or irreversible seller actions.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Product photos that fail to load fall back to the store logo.
    document.querySelectorAll('img[data-fallback-src]').forEach((image) => {
        const useFallback = () => {
            if (image.src !== image.dataset.fallbackSrc) image.src = image.dataset.fallbackSrc;
        };
        if (image.complete && image.naturalWidth === 0) useFallback();
        else image.addEventListener('error', useFallback, { once: true });
    });

    // Filter the rows already on the page: <input data-search-input="key"> + [data-search-item="key"][data-search-text].
    document.querySelectorAll('[data-search-input]').forEach((input) => {
        const key = input.dataset.searchInput;
        const items = [...document.querySelectorAll(`[data-search-item="${key}"]`)];
        const empty = document.querySelector(`[data-search-empty="${key}"]`);
        const count = document.querySelector(`[data-search-count="${key}"]`);

        input.addEventListener('input', () => {
            const query = input.value.trim().toLocaleLowerCase();
            let visible = 0;

            items.forEach((item) => {
                const matches = (item.dataset.searchText || '').includes(query);
                item.hidden = !matches;
                visible += Number(matches);
            });

            if (empty) {
                empty.hidden = visible > 0;
            }
            if (count) {
                count.textContent = query === ''
                    ? count.dataset.searchCountDefault
                    : `${visible} ${visible === 1 ? 'match' : 'matches'} on this page`;
            }
        });
    });
})();
