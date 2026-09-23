document.addEventListener('DOMContentLoaded', () => {
    const desktopNavigation = window.matchMedia('(min-width: 1181px)');

    document.querySelectorAll('[data-nav-toggle]').forEach((toggle) => {
        const navbar = toggle.closest('.navbar');
        const menu = navbar?.querySelector('[data-nav-menu]');

        if (!navbar || !menu) {
            return;
        }

        const setMenuOpen = (isOpen, returnFocus = false) => {
            menu.inert = !isOpen && !desktopNavigation.matches;
            navbar.classList.toggle('is-menu-open', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');

            if (returnFocus) {
                toggle.focus();
            }
        };

        toggle.addEventListener('click', () => {
            setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        menu.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (!navbar.contains(event.target)) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setMenuOpen(false, true);
            }
        });

        desktopNavigation.addEventListener('change', () => {
            setMenuOpen(false);
        });

        setMenuOpen(false);
    });
});
