/**
 * Sets the theme before the page first paints, so dark-mode visitors never see
 * a flash of the light theme. Load it in <head> without defer; theme.js
 * (deferred) handles the toggle and later changes.
 */
(function () {
    var preference = null;
    try {
        preference = localStorage.getItem('zaylo-theme');
    } catch (error) {
        // Storage blocked: follow the system setting.
    }
    var dark = preference === 'dark'
        || (preference !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
})();
