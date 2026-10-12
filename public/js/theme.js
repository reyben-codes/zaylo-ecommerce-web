/**
 * Light / dark theme switching.
 *
 * The page follows the operating system's appearance until the visitor picks
 * one with a [data-theme-toggle] button. Choosing the theme the system already
 * uses goes back to following the system. partials/theme-head.blade.php sets
 * the initial theme before first paint; this file keeps it up to date.
 */
(function () {
    const STORAGE_KEY = 'zaylo-theme';
    const root = document.documentElement;
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

    function storedPreference() {
        try {
            const value = localStorage.getItem(STORAGE_KEY);
            return value === 'light' || value === 'dark' ? value : 'system';
        } catch (error) {
            return 'system';
        }
    }

    function storePreference(value) {
        try {
            if (value === 'system') {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, value);
            }
        } catch (error) {
            // Private browsing: the choice lasts for this page only.
        }
    }

    function systemTheme() {
        return systemDark.matches ? 'dark' : 'light';
    }

    function resolvedTheme(preference) {
        return preference === 'system' ? systemTheme() : preference;
    }

    function updateToggles(theme) {
        const next = theme === 'dark' ? 'light' : 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const label = `Switch to ${next} mode`;
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(theme === 'dark'));
            const icon = `<i class="fa-solid ${theme === 'dark' ? 'fa-sun' : 'fa-moon'}" aria-hidden="true"></i>`;
            // Sidebar-style toggles also spell out what they do.
            button.innerHTML = button.hasAttribute('data-theme-toggle-label')
                ? `${icon}<span>${next === 'dark' ? 'Dark' : 'Light'} mode</span>`
                : icon;
        });
    }

    function apply() {
        const theme = resolvedTheme(storedPreference());
        root.dataset.theme = theme;
        updateToggles(theme);
    }

    function toggle() {
        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        storePreference(next === systemTheme() ? 'system' : next);
        apply();
    }

    function ensureToggle() {
        if (document.querySelector('[data-theme-toggle]')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'theme-toggle theme-toggle--floating';
        button.setAttribute('data-theme-toggle', '');
        document.body.appendChild(button);
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-theme-toggle]')) toggle();
    });

    systemDark.addEventListener('change', () => {
        if (storedPreference() === 'system') apply();
    });

    // Keep other open tabs in step.
    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY || event.key === null) apply();
    });

    function init() {
        ensureToggle();
        apply();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
