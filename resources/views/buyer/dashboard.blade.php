@extends('layouts.app')

@section('title', 'ZAYLO · Buyer Dashboard')



@push('styles')
<link rel="stylesheet" href="{{ asset('css/home.css') }}?v={{ filemtime(public_path('css/home.css')) }}">
<style>
    .icon-group a { color: #1e1e1e; text-decoration: none; transition: 0.15s; position: relative; }
    .icon-group a:hover { color: #8a7a6b; }
    .categories { background: #f5f0ea; padding: 60px 48px 72px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; border-top: 1px solid #ece4db; }
    .category-item { display: flex; flex-direction: column; align-items: center; flex: 0 1 auto; min-width: 90px; text-align: center; position: relative; padding: 0 12px; text-decoration: none; color: #1e1e1e; transition: 0.15s; }
    .category-item:hover { transform: translateY(-2px); }
    .category-item:not(:last-child)::after { content: ''; position: absolute; right: -12px; top: 20%; height: 55%; width: 1px; background: #dcd2c7; opacity: 0.5; }
    .circle-img { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; background: #d6ccc1; margin-bottom: 16px; border: 3px solid white; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    .category-name { font-size: 0.9rem; font-weight: 500; letter-spacing: 0.04em; color: #1a1714; text-transform: uppercase; margin-bottom: 4px; }
    .item-count { font-size: 0.7rem; font-weight: 350; color: #6b5f54; letter-spacing: 0.02em; }
    .flash-sales { padding: 60px 48px 52px; background: #faf7f2; border-bottom: 1px solid #ece4db; }
    .flash-header { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto 28px; flex-wrap: wrap; gap: 16px; }
    .flash-header-left { display: flex; align-items: center; gap: 20px; }
    .flash-header-left h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 600; color: #1a1714; }
    .flash-badge { background: #c0392b; color: white; border-radius: 999px; padding: 5px 18px; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
    .flash-timer { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: #6b5f54; }
    .flash-timer .time-block { display: flex; align-items: center; gap: 6px; }
    .flash-timer .time-block .number { font-size: 1.6rem; font-weight: 600; color: #1a1714; font-family: 'Playfair Display', serif; min-width: 36px; text-align: center; }
    .flash-timer .time-block .label { font-size: 0.6rem; text-transform: uppercase; color: #6b5f54; }
    .flash-timer .separator { font-size: 1.4rem; color: #b28b6f; font-weight: 300; }
    .flash-product { background: white; border: 1px solid #1a1714; position: relative; overflow: hidden; transition: transform .38s cubic-bezier(.22, 1, .36, 1), box-shadow .38s cubic-bezier(.22, 1, .36, 1); }
    .flash-product:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(192,57,43,0.1); }
    .flash-product:hover .product-image img { transform: scale(1.03); }
    .flash-discount { position: absolute; top: 12px; left: 12px; background: #c0392b; color: white; border-radius: 999px; padding: 4px 12px; font-size: 0.65rem; font-weight: 700; }
    .flash-product .product-wishlist { position: absolute; top: 12px; right: 12px; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.15s; box-shadow: 0 2px 8px rgba(0,0,0,0.06); font-size: 1rem; color: #1a1714; display: flex; align-items: center; justify-content: center; }
    .flash-product .product-wishlist:hover { background: #1a1714; color: white; }
    .flash-product .product-price { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .flash-product .current-price { font-size: 1.05rem; font-weight: 600; color: #c0392b; }
    .flash-product .original-price { font-size: 0.8rem; color: #6b5f54; text-decoration: line-through; }
    .flash-product .btn-flash { width: 100%; margin-top: 4px; padding: 10px 8px; min-height: 42px; background: #c0392b; color: white; border: none; border-radius: 8px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; transition: 0.15s; font-family: 'Inter', sans-serif; text-decoration: none; text-align: center; display: block; }
    .flash-product .btn-flash:hover { background: #a93226; }
    .flash-product .btn-flash.added { background: #2d7d46; }
    .suggested-products { padding: 60px 48px 72px; background: #faf7f2; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; max-width: 1400px; margin-left: auto; margin-right: auto; }
    .section-header h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 600; color: #1a1714; }
    .section-header .section-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: #6b5f54; font-weight: 400; display: block; margin-bottom: 6px; }
    .section-header a { color: #6b5f54; text-decoration: none; font-size: 0.85rem; transition: 0.15s; }
    .section-header a:hover { color: #1a1714; }
    .product-card { background: white; border: 1px solid #1a1714; transition: transform .38s cubic-bezier(.22, 1, .36, 1), box-shadow .38s cubic-bezier(.22, 1, .36, 1); overflow: hidden; animation: floatIn 0.6s ease forwards; opacity: 0; }
    .product-card:nth-child(1){animation-delay:0.05s} .product-card:nth-child(2){animation-delay:0.1s} .product-card:nth-child(3){animation-delay:0.15s} .product-card:nth-child(4){animation-delay:0.2s}
    @keyframes floatIn { 0%{opacity:0;transform:translateY(30px) scale(0.95)} 100%{opacity:1;transform:translateY(0) scale(1)} }
    .product-card:hover { transform: translateY(-6px) scale(1.01); border-color: #1a1714; box-shadow: 0 8px 32px rgba(0,0,0,0.06); }
    .product-card:hover .product-image img { transform: scale(1.05); }
    .product-card .product-wishlist { position: absolute; top: 12px; right: 12px; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.15s; box-shadow: 0 2px 8px rgba(0,0,0,0.06); font-size: 1rem; color: #1a1714; display: flex; align-items: center; justify-content: center; }
    .product-card .product-wishlist:hover, .product-card .product-wishlist.active { background: #1a1714; color: white; }
    .product-card .product-badge { position: absolute; top: 12px; left: 12px; padding: 4px 14px; border-radius: 999px; font-size: 0.55rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; background: #1a1714; color: white; }
    .product-card .product-badge.sale { background: #b28b6f; }
    .product-card .product-badge.best { background: #d4af37; }
    .product-card .product-rating { font-size: 0.7rem; color: #d4af37; margin: 8px 0; }
    .product-card .product-rating span { color: #6b5f54; margin-left: 4px; }
    .product-card .product-price { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .product-card .current-price { font-size: 1.05rem; font-weight: 600; color: #1a1714; }
    .product-card .original-price { font-size: 0.8rem; color: #6b5f54; text-decoration: line-through; }
    .product-card .btn-add-cart:hover { background: #b28b6f; }
    .product-card .btn-add-cart.added { background: #2d7d46; }
    .loader-container { text-align: center; padding: 48px 0; max-width: 1400px; margin: 0 auto; }
    .loader { display: inline-block; width: 36px; height: 36px; border: 2px solid #ece4db; border-top: 2px solid #1a1714; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    .end-message { text-align: center; padding: 48px 0; color: #6b5f54; font-size: 0.95rem; max-width: 1400px; margin: 0 auto; }
    .end-message .end-icon { display: block; font-size: 1.8rem; margin-bottom: 10px; }
    @media (max-width: 640px) { .flash-sales, .suggested-products { padding: 36px 16px; } .section-header { flex-wrap: wrap; gap: 12px; } .flash-header-left { flex-wrap: wrap; } .categories { flex-wrap: nowrap; overflow-x: auto; justify-content: flex-start; gap: 28px; padding: 28px 20px 36px; } .category-item { flex: 0 0 auto; } }
</style>
@endpush

@section('content')
    <!-- Hero -->
    <div class="hero home-hero" id="buyer-hero" role="region" aria-roledescription="carousel" aria-label="Featured marketplace images">
        <div class="hero-slides" aria-hidden="true">
            <div class="hero-slide is-active"><img class="hero-image" src="https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="high" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1445205170230-053b83016050?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1498049794561-7780e7231661?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
            <div class="hero-slide"><img class="hero-image" src="https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=1600&q=80&auto=format&fit=crop" alt="" fetchpriority="low" onerror="this.onerror=null;this.src='{{ asset('images/login-marketplace.png') }}'" /></div>
        </div>
        <div class="hero-text">
            <div class="hero-label">Welcome back, {{ $user->name }}</div>
            <div class="hero-headline">
                <span class="line1">Driven by passion.</span>
                <span class="line2">Defined by your origin</span>
            </div>
            <p class="hero-desc">Shop tech, home essentials, fashion, beauty, groceries, and more from trusted marketplace sellers.</p>
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

    <!-- Categories -->
    <div class="categories">
        @foreach(config('marketplace.browse_categories') as $category => $details)
            <a href="{{ route('products.index', ['category' => $category]) }}" class="category-item">
                <img class="circle-img" src="{{ $details['image'] }}" alt="" onerror="this.onerror=null;this.src='{{ asset('images/ZAYLO_ICON_DARK.png') }}'" />
                <div class="category-name">{{ $details['label'] }}</div><div class="item-count">Explore products</div>
            </a>
        @endforeach
    </div>

    <!-- Flash Sales -->
    <section class="flash-sales">
        <div class="flash-header">
            <div class="flash-header-left">
                <h2>Flash Sales</h2>
                <span class="flash-badge">🔥 Limited Time</span>
            </div>
            <p class="flash-timer">Current seller offers · See each product for its sale period.</p>
        </div>
        <div class="flash-grid" id="flashGrid">
            @foreach($flashProducts as $product)
            @php $discount = round((1 - $product->price / $product->original_price) * 100) . '% OFF'; @endphp
            <div class="flash-product" data-product-url="{{ route('products.show', $product) }}">
                <div class="product-image">
                    @include('partials.product-card-image', ['product' => $product])
                    <span class="flash-discount">{{ $discount }}</span>
                    <form data-wishlist-toggle method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">@csrf<button class="product-wishlist" aria-label="Save {{ $product->name }}"><i class="far fa-heart"></i></button></form>
                </div>
                <div class="product-info">
                    <h3 class="product-name"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                    @include('partials.product-card-description')
                    <p class="product-category">{{ $product->category_label }}</p>
                    @include('partials.product-rating')
                    <div class="product-price">
                        <span class="current-price">₱{{ number_format($product->price, 2) }}</span>
                        <span class="original-price">₱{{ number_format($product->original_price, 2) }}</span>
                    </div>
                    @if($product->active_variants_count)
                        <a class="btn-flash" href="{{ route('products.show', $product) }}">Choose options</a>
                    @else
                        <form data-cart-add method="POST" action="{{ route('buyer.cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="btn-flash"><i class="fas fa-bolt"></i> Grab Now</button></form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </section>

    <!-- Suggested Products -->
    <section class="suggested-products">
        <div class="section-header">
            <div>
                <span class="section-label">Recommended For You</span>
                <h2>Suggested Products</h2>
            </div>
            <a href="{{ route('products.index') }}">View All →</a>
        </div>
        <div class="product-grid" id="productGrid">
            @foreach($suggestedProducts as $product)
            <div class="product-card" data-product-url="{{ route('products.show', $product) }}">
                <div class="product-image">
                    @include('partials.product-card-image', ['product' => $product])
                    <form data-wishlist-toggle method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">@csrf<button class="product-wishlist" aria-label="Save {{ $product->name }}"><i class="far fa-heart"></i></button></form>
                    @if($product->badge)
                        <span class="product-badge {{ $product->badge === 'Sale' ? 'sale' : ($product->badge === 'Best Seller' ? 'best' : '') }}">{{ $product->badge }}</span>
                    @endif
                </div>
                <div class="product-info">
                    <h3 class="product-name"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
                    @include('partials.product-card-description')
                    <p class="product-category">{{ $product->category_label }}</p>
                    @include('partials.product-rating')
                    <div class="product-price">
                        <span class="current-price">₱{{ number_format($product->price, 2) }}</span>
                        @if($product->original_price)
                            <span class="original-price">₱{{ number_format($product->original_price, 2) }}</span>
                        @endif
                    </div>
                    @if($product->active_variants_count)
                        <a class="btn-add-cart" href="{{ route('products.show', $product) }}">Choose options</a>
                    @else
                        <form data-cart-add method="POST" action="{{ route('buyer.cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="btn-add-cart"><i class="fas fa-shopping-bag"></i> Add to Cart</button></form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        <div class="end-message"><i class="far fa-circle-check end-icon" aria-hidden="true"></i>You've reached the end of our suggestions</div>
    </section>
@endsection

@push('scripts')
<script>
(() => {
    const hero = document.getElementById('buyer-hero');
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

// Flash timer

</script>
@endpush
