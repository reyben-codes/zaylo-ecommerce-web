{{-- Include in every page's <head>, before the other stylesheets. theme-init.js
     runs before first paint so the page never flashes the wrong theme. --}}
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" />
<script src="{{ asset('js/theme-init.js') }}?v={{ filemtime(public_path('js/theme-init.js')) }}"></script>
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}" defer></script>
