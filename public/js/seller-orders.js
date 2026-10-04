(() => {
    const menuToggle = document.querySelector('[data-seller-menu-toggle]');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('[data-seller-sidebar-overlay]');
    const search = document.querySelector('[data-order-search]');
    const rows = [...document.querySelectorAll('[data-order-row]')];
    const emptySearch = document.querySelector('[data-search-empty]');

    const closeMenu = () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('is-visible');
        menuToggle?.setAttribute('aria-expanded', 'false');
    };

    menuToggle?.addEventListener('click', () => {
        const opening = !sidebar?.classList.contains('open');
        sidebar?.classList.toggle('open', opening);
        overlay?.classList.toggle('is-visible', opening);
        menuToggle.setAttribute('aria-expanded', String(opening));
    });
    overlay?.addEventListener('click', closeMenu);
    sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            closeMenu();
        }
    });

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const matches = row.dataset.orderSearchText.includes(query);
            row.hidden = !matches;
            visible += Number(matches);
        });

        if (emptySearch) {
            emptySearch.hidden = visible > 0;
        }
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
})();
