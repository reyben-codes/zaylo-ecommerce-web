@if(auth()->user()?->role === 'buyer')
    <link rel="stylesheet" href="{{ asset('css/cart-animation.css') }}?v={{ filemtime(public_path('css/cart-animation.css')) }}">
    <div class="cart-animation-notice" data-cart-feedback role="status" aria-live="polite" hidden></div>
    <script src="{{ asset('js/cart-animation.js') }}?v={{ filemtime(public_path('js/cart-animation.js')) }}" defer></script>
@endif
