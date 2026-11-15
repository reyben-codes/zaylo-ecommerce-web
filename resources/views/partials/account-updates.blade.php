@auth
<span hidden data-account-updates data-url="{{ route('account.updates') }}" data-read-url="{{ route('account.updates.read') }}"></span>
<link rel="stylesheet" href="{{ asset('css/account-updates.css') }}?v={{ filemtime(public_path('css/account-updates.css')) }}">
<script src="{{ asset('js/account-updates.js') }}?v={{ filemtime(public_path('js/account-updates.js')) }}" defer></script>
@endauth
