<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ZAYLO · Everything in One Place</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/style.css') }}" />
</head>
<body>
    <header class="navbar">
        @include('partials.store-nav')
        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" style="height:72px;width:auto;display:block;">
        </a>
        <div class="nav-actions">
            <form class="search-wrapper" method="GET" action="{{ route('products.index') }}" role="search">
                <span class="search-icon">⌕</span>
                <input type="search" name="search" placeholder="Search products" aria-label="Search products" />
            </form>
            <div class="icon-group">
                <a href="{{ auth()->check() && auth()->user()->role === 'buyer' ? route('buyer.wishlist') : route('login') }}" aria-label="Wishlist"><i class="far fa-heart"></i></a>
                <a href="{{ auth()->check() && auth()->user()->role === 'buyer' ? route('buyer.cart') : route('login') }}" aria-label="Shopping cart"><i class="fas fa-shopping-bag"></i></a>
            </div>
            @auth
                @php($dashboardRoute = auth()->user()->role.'.dashboard')
                <a href="{{ Route::has($dashboardRoute) ? route($dashboardRoute) : route('home') }}" class="login-text">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="login-text">Log in</a>
            @endauth
        </div>
    </header>
<div class="container">

    <div class="hero">
        <img class="hero-image"
            src="https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1400&q=80&auto=format&fit=crop&crop=center"
            alt="Featured products available on ZAYLO"
            onerror="this.style.display='none'" />
        <div class="hero-text">
            <div class="hero-label">One marketplace. Endless finds.</div>
            <div class="hero-headline">
                <span class="line1">Everything you need,</span>
                <span class="line2">all in one place.</span>
            </div>
            <p class="hero-desc">
                Discover the latest tech, home essentials, fashion, beauty, groceries, and more from sellers across the marketplace.
            </p>
            <div class="hero-actions">
                <a href="{{ route('products.index') }}" class="btn-primary">Shop the marketplace</a>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" class="link-lookbook">See what's new →</a>
            </div>
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
                    <article class="home-product-card">
                        <a href="{{ route('products.show', $product) }}" class="home-product-image">
                            <img src="{{ $product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $product->name }}" loading="lazy" />
                            @if($product->badge)
                                <span class="home-product-badge">{{ $product->badge }}</span>
                            @endif
                        </a>
                        <div class="home-product-info">
                            <p class="home-product-category">{{ config('marketplace.categories.'.$product->category, str($product->category)->replace('_', ' ')->title()) }}</p>
                            <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                            <div class="home-product-price">
                                <span>₱{{ number_format($product->price, 2) }}</span>
                                @if($product->original_price)
                                    <del>₱{{ number_format($product->original_price, 2) }}</del>
                                @endif
                            </div>
                            @if(auth()->check() && auth()->user()->role === 'buyer')
                                <form method="POST" action="{{ route('buyer.cart.add', $product) }}">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1" />
                                    <button type="submit" class="home-add-button"><i class="fas fa-shopping-bag" aria-hidden="true"></i> Add to bag</button>
                                </form>
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
            <div><h2>Account</h2><a href="{{ route('login') }}">Log in</a><a href="{{ route('register') }}">Create account</a></div>
            <div><h2>Partners</h2><a href="{{ route('register', ['role' => 'seller']) }}">Sell on ZAYLO</a><a href="{{ route('register', ['role' => 'courier']) }}">Become a courier</a></div>
        </div>
    </div>
    <div class="footer-bottom"><span>© {{ date('Y') }} ZAYLO</span><span>Everything you need, all in one place.</span></div>
</footer>
</body>
</html>
