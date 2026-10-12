{{-- Include in every page's <head>, before other stylesheets' first paint. --}}
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" />
<script>
    (function () {
        var preference = null;
        try { preference = localStorage.getItem('zaylo-theme'); } catch (error) {}
        var dark = preference === 'dark'
            || (preference !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    })();
</script>
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}" defer></script>
