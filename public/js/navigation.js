document.addEventListener('DOMContentLoaded', () => {
    const desktopNavigation = window.matchMedia('(min-width: 1281px)');

    // The categories dropdown: opens on hover with a mouse and on click or tap everywhere. Registered before the
    // phone menu's Escape handler below, so Escape closes the dropdown first and the menu on the next press.
    document.querySelectorAll('[data-category-menu]').forEach((menu) => {
        const toggle = menu.querySelector('[data-category-menu-toggle]');
        let openedByHover = false;
        let closeTimer = null;

        const isOpen = () => menu.classList.contains('is-open');
        const setOpen = (open) => {
            clearTimeout(closeTimer);
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            openedByHover = false;
        };

        // A click after hovering keeps the panel open instead of flickering it shut.
        toggle.addEventListener('click', () => setOpen(openedByHover || !isOpen()));

        menu.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'mouse' || !desktopNavigation.matches) {
                return;
            }
            clearTimeout(closeTimer);
            if (!isOpen()) {
                setOpen(true);
                openedByHover = true;
            }
        });

        menu.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse' && desktopNavigation.matches) {
                // A short delay lets the pointer cross the gap between the button and the panel.
                closeTimer = setTimeout(() => setOpen(false), 200);
            }
        });

        menu.addEventListener('focusout', (event) => {
            if (event.relatedTarget && !menu.contains(event.relatedTarget)) {
                setOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen()) {
                event.stopImmediatePropagation();
                setOpen(false);
                toggle.focus();
            }
        });

        desktopNavigation.addEventListener('change', () => setOpen(false));
    });

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
