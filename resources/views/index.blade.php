<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ZAYLO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}" />
    <style>
        .home-hero::after { content: ''; position: absolute; inset: 0; z-index: 1; pointer-events: none; background: linear-gradient(90deg, rgba(250, 247, 242, .96), rgba(250, 247, 242, .83) 32%, rgba(250, 247, 242, .12) 72%); }
        .home-hero .hero-slides, .home-hero .hero-slide { position: absolute; inset: 0; }
        .home-hero .hero-slide { opacity: 0; visibility: hidden; transition: opacity .7s ease, visibility .7s ease; }
        .home-hero .hero-slide.is-active { opacity: 1; visibility: visible; }
        .home-hero .hero-text { z-index: 2; }
        .home-hero .hero-carousel-controls { position: absolute; z-index: 3; right: 5%; bottom: 28px; display: flex; align-items: center; gap: 16px; }
        .home-hero .hero-carousel-arrow { display: grid; place-items: center; width: 42px; height: 42px; border: 1px solid rgba(26, 23, 20, .18); border-radius: 50%; background: rgba(255, 255, 255, .88); color: #1a1714; cursor: pointer; }
        .home-hero .hero-carousel-arrow:hover { background: #fff; }
        .home-hero .hero-carousel-dots { display: flex; align-items: center; gap: 7px; }
        .home-hero .hero-carousel-dot { width: 24px; height: 24px; padding: 0; border: 0; background: transparent; cursor: pointer; display: grid; place-items: center; }
        .home-hero .hero-carousel-dot::before { content: ''; display: block; width: 8px; height: 8px; border-radius: 50%; background: rgba(26, 23, 20, .4); transition: width .2s ease, background-color .2s ease; }
        .home-hero .hero-carousel-dot[aria-pressed="true"]::before { width: 20px; border-radius: 999px; background: #1a1714; }
        .home-hero .hero-carousel-controls button:focus-visible { outline: 2px solid #1a1714; outline-offset: 3px; }
        @media (max-width: 640px) { .home-hero { height: 390px; } .home-hero::after { background: linear-gradient(90deg, rgba(250, 247, 242, .94), rgba(250, 247, 242, .69) 65%, rgba(250, 247, 242, .25)); } .home-hero .hero-carousel-controls { right: 5%; bottom: 12px; gap: 8px; } .home-hero .hero-carousel-arrow { width: 36px; height: 36px; } }
        @media (prefers-reduced-motion: reduce) { .home-hero .hero-slide, .home-hero .hero-carousel-dot::before { transition: none; } }
    </style>
</head>
<body>
    <header class="navbar">
        <button
            class="nav-menu-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="primary-navigation"
            aria-label="Open navigation menu"
            data-nav-toggle
        >
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </button>
        <div class="nav-menu" id="primary-navigation" data-nav-menu>
            <div class="nav-menu-inner">
                @include('partials.store-nav')
            </div>
        </div>
        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" style="height:72px;width:auto;display:block;">
        </a>
        <div class="nav-actions">
            <form class="search-wrapper" method="GET" action="{{ route('products.index') }}" role="search">
                <span class="search-icon">⌕</span>
                <input type="search" name="search" placeholder="Search products" aria-label="Search products" />
            </form>
            <div class="icon-group">
                @if(auth()->user()?->role === 'buyer')
                    @include('partials.buyer-nav-icons')
                @else
                <a href="{{ auth()->check() && auth()->user()->role === 'buyer' ? route('buyer.wishlist') : route('login') }}" aria-label="Wishlist"><i class="far fa-heart"></i></a>
                <a href="{{ auth()->check() && auth()->user()->role === 'buyer' ? route('buyer.cart') : route('login') }}" aria-label="Shopping cart"><i class="fas fa-shopping-bag"></i></a>
                @endif
            </div>
            @auth
                @php($dashboardRoute = auth()->user()->role.'.dashboard')
                <a href="{{ Route::has($dashboardRoute) ? route($dashboardRoute) : route('home') }}" class="login-text">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="login-text">Log in</a>
                <a href="{{ route('register') }}" class="login-text">Create new account</a>
            @endauth
        </div>
    </header>

    @include('partials.notifications')
<div class="container">

    <div class="hero home-hero" id="home-hero" role="region" aria-roledescription="carousel" aria-label="Featured marketplace images">
        <div class="hero-slides" aria-hidden="true">
            <div class="hero-slide is-active"><img class="hero-image" src="https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="high" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1445205170230-053b83016050?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1498049794561-7780e7231661?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
        </div>
        <div class="hero-text">
            <div class="hero-label">One marketplace. Endless finds.</div>
            <div class="hero-headline">
                <span class="line1">Driven by passion.</span>
                <span class="line2">Defined by your origin</span>
            </div>
            <p class="hero-desc">
                Discover the latest tech, home essentials, fashion, beauty, groceries, and more from sellers across the marketplace.
            </p>
            <div class="hero-actions">
                <a href="{{ route('products.index') }}" class="btn-primary">Shop the marketplace</a>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" class="link-lookbook">See what's new →</a>
            </div>
        </div>
        <div class="hero-carousel-controls" aria-label="Carousel controls">
            <button class="hero-carousel-arrow" type="button" data-hero-prev aria-label="Previous image"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <div class="hero-carousel-dots">
                @for($slide = 0; $slide < 5; $slide++)
                    <button class="hero-carousel-dot" type="button" data-hero-slide="{{ $slide }}" aria-label="Show image {{ $slide + 1 }} of 5" aria-pressed="{{ $slide === 0 ? 'true' : 'false' }}"></button>
                @endfor
            </div>
            <button class="hero-carousel-arrow" type="button" data-hero-next aria-label="Next image"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>

    <nav class="categories" aria-label="Shop by category">
        @foreach(config('marketplace.browse_categories') as $category => $details)
            <a href="{{ route('products.index', ['category' => $category]) }}" class="category-item">
                <img class="circle-img" src="{{ $details['image'] }}" alt="" onerror="this.onerror=null;this.src='{{ asset('images/ZAYLO_ICON_DARK.png') }}'" />
                <div class="category-name">{{ $details['label'] }}</div>
                <div class="item-count">Explore products</div>
            </a>
        @endforeach
    </nav>

    <section class="new-arrivals" aria-labelledby="new-arrivals-title">
        <div class="storefront-section-header">
            <div>
                <span class="section-label">Freshly curated</span>
                <h2 id="new-arrivals-title">New Arrivals</h2>
            </div>
            <a href="{{ route('products.index', ['sort' => 'newest']) }}">Shop all new arrivals <span aria-hidden="true">→</span></a>
        </div>

        @if($newArrivals->isNotEmpty())
            <div class="home-product-grid">
                @foreach($newArrivals as $product)
                    <article class="home-product-card" data-product-url="{{ route('products.show', $product) }}">
                        <a href="{{ route('products.show', $product) }}" class="home-product-image">
                            @include('partials.product-card-image', ['product' => $product])
                            @if($product->badge)
                                <span class="home-product-badge">{{ $product->badge }}</span>
                            @endif
                        </a>
                        <div class="home-product-info">
                            <p class="home-product-category">{{ $product->category_label }}</p>
                            <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                            <div class="home-product-price">
                                <span>₱{{ number_format($product->price, 2) }}</span>
                                @if($product->original_price)
                                    <del>₱{{ number_format($product->original_price, 2) }}</del>
                                @endif
                            </div>
                            @if(auth()->check() && auth()->user()->role === 'buyer')
                                @if($product->active_variants_count)
                                    <a href="{{ route('products.show', $product) }}" class="home-add-button">Choose options</a>
                                @else
                                    <form method="POST" action="{{ route('buyer.cart.add', $product) }}">
                                        @csrf
                                        <input type="hidden" name="quantity" value="1" />
                                        <button type="submit" class="home-add-button"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Add to bag</button>
                                    </form>
                                @endif
                            @else
                                <a href="{{ route('products.show', $product) }}" class="home-add-button">View product</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="home-empty-state">
                <p>Our next edit is being prepared.</p>
                <a href="{{ route('products.index') }}" class="btn-primary">Browse the collection</a>
            </div>
        @endif
    </section>

    <section class="trust-strip" aria-label="Shopping benefits">
        <div class="trust-item"><i class="fas fa-shield-halved" aria-hidden="true"></i><div><strong>Secure checkout</strong><span>Your payment details stay protected.</span></div></div>
        <div class="trust-item"><i class="fas fa-truck-fast" aria-hidden="true"></i><div><strong>Reliable delivery</strong><span>Track your order from door to door.</span></div></div>
        <div class="trust-item"><i class="fas fa-rotate-left" aria-hidden="true"></i><div><strong>Easy support</strong><span>Help is available when you need it.</span></div></div>
    </section>

</div>

<footer class="site-footer">
    <div class="footer-main">
        <div class="footer-brand">
            <img src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO" />
            <p>Everyday essentials, exciting finds, and trusted sellers—all together in one marketplace.</p>
        </div>
        <div class="footer-links">
            <div><h2>Shop</h2><a href="{{ route('products.index') }}">All products</a><a href="{{ route('products.index', ['category' => 'electronics']) }}">Electronics</a><a href="{{ route('products.index', ['category' => 'home_living']) }}">Home & Living</a></div>
            <div><h2>Discover</h2><a href="{{ route('products.index', ['sort' => 'newest']) }}">New arrivals</a><a href="{{ route('products.index', ['category' => 'fashion']) }}">Fashion</a><a href="{{ route('products.index', ['category' => 'beauty_health']) }}">Beauty & Health</a></div>
            <div><h2>Account</h2><a href="{{ route('login') }}">Log in</a><a href="{{ route('register') }}">Create new account</a></div>
            <div><h2>Partners</h2><a href="{{ route('register', ['role' => 'seller']) }}">Sell on ZAYLO</a><a href="{{ route('register', ['role' => 'courier']) }}">Become a courier</a></div>
        </div>
    </div>
    <div class="footer-bottom"><span>© {{ date('Y') }} ZAYLO</span><span>Driven by passion. Defined by your origin</span></div>
</footer>
<script src="{{ asset('js/navigation.js') }}?v={{ filemtime(public_path('js/navigation.js')) }}" defer></script>
<script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
<script src="{{ asset('js/product-carousel.js') }}?v={{ filemtime(public_path('js/product-carousel.js')) }}" defer></script>
<script>
(() => {
    const hero = document.getElementById('home-hero');
    const slides = [...hero.querySelectorAll('.hero-slide')];
    const dots = [...hero.querySelectorAll('[data-hero-slide]')];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let timer;

    function showSlide(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => slide.classList.toggle('is-active', position === current));
        dots.forEach((dot, position) => dot.setAttribute('aria-pressed', String(position === current)));
    }

    function stopRotation() {
        window.clearInterval(timer);
        timer = undefined;
    }

    function startRotation() {
        if (timer || reducedMotion.matches || document.hidden || hero.querySelector(':focus-visible')) return;
        timer = window.setInterval(() => showSlide(current + 1), 3500);
    }

    function navigateTo(index) {
        showSlide(index);
        stopRotation();
        startRotation();
    }

    hero.querySelector('[data-hero-prev]').addEventListener('click', () => navigateTo(current - 1));
    hero.querySelector('[data-hero-next]').addEventListener('click', () => navigateTo(current + 1));
    dots.forEach((dot, index) => dot.addEventListener('click', () => navigateTo(index)));
    hero.addEventListener('focusin', (event) => { if (event.target.matches(':focus-visible')) stopRotation(); });
    hero.addEventListener('focusout', () => window.setTimeout(startRotation, 0));
    document.addEventListener('visibilitychange', () => document.hidden ? stopRotation() : startRotation());
    reducedMotion.addEventListener('change', () => reducedMotion.matches ? stopRotation() : startRotation());
    startRotation();
})();
</script>
</body>
</html>
