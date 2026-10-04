<header class="seller-site-header">
    <div class="seller-site-header__shop">
        <i class="fa-solid fa-store" aria-hidden="true"></i>
        <span>Managing <strong>{{ $sellerHeaderStoreName }}</strong></span>
    </div>
    <a href="{{ route('seller.dashboard') }}" class="seller-site-header__logo" aria-label="Seller dashboard">
        <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO">
    </a>
    <div class="seller-site-header__actions">
        <a href="{{ route('seller.account') }}" class="seller-site-header__button" aria-label="Seller account">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="seller-site-header__logout">
            @csrf
            <button type="submit" class="seller-site-header__button" aria-label="Log out">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</header>
@include('partials.chat-widget')
