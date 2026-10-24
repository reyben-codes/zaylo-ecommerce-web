@extends('layouts.app')

@section('title', 'ZAYLO · Buyer Dashboard')



@push('styles')
<link rel="stylesheet" href="{{ asset('css/home.css') }}?v={{ filemtime(public_path('css/home.css')) }}">
<style>
    .icon-group a { color: var(--c-1e1e1e); text-decoration: none; transition: 0.15s; position: relative; }
    .icon-group a:hover { color: var(--c-8a7a6b); }
    .categories { background: var(--c-f5f0ea); padding: 60px 48px 72px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; border-top: 1px solid var(--c-ece4db); }
    .category-item { display: flex; flex-direction: column; align-items: center; flex: 0 1 auto; min-width: 90px; text-align: center; position: relative; padding: 0 12px; text-decoration: none; color: var(--c-1e1e1e); transition: 0.15s; }
    .category-item:hover { transform: translateY(-2px); }
    .category-item:not(:last-child)::after { content: ''; position: absolute; right: -12px; top: 20%; height: 55%; width: 1px; background: var(--c-dcd2c7); opacity: 0.5; }
    .circle-img { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; background: var(--c-d6ccc1); margin-bottom: 16px; border: 3px solid var(--c-ffffff); box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    .category-name { font-size: 0.9rem; font-weight: 500; letter-spacing: 0.04em; color: var(--c-1a1714); text-transform: uppercase; margin-bottom: 4px; }
    .item-count { font-size: 0.7rem; font-weight: 350; color: var(--c-6b5f54); letter-spacing: 0.02em; }
    .flash-sales { padding: 60px 48px 52px; background: var(--c-faf7f2); border-bottom: 1px solid var(--c-ece4db); }
    .flash-header { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto 28px; flex-wrap: wrap; gap: 16px; }
    .flash-header-left { display: flex; align-items: center; gap: 20px; }
    .flash-header-left h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 600; color: var(--c-1a1714); }
    .flash-badge { background: var(--c-c0392b); color: var(--c-ffffff); border-radius: 999px; padding: 5px 18px; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
    .flash-timer { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--c-6b5f54); }
    .flash-timer .time-block { display: flex; align-items: center; gap: 6px; }
    .flash-timer .time-block .number { font-size: 1.6rem; font-weight: 600; color: var(--c-1a1714); font-family: 'Playfair Display', serif; min-width: 36px; text-align: center; }
    .flash-timer .time-block .label { font-size: 0.6rem; text-transform: uppercase; color: var(--c-6b5f54); }
    .flash-timer .separator { font-size: 1.4rem; color: var(--c-b28b6f); font-weight: 300; }
    .flash-discount { position: absolute; top: 12px; left: 12px; background: var(--c-c0392b); color: var(--c-ffffff); border-radius: 999px; padding: 4px 12px; font-size: 0.65rem; font-weight: 700; }
    .suggested-products { padding: 60px 48px 72px; background: var(--c-faf7f2); }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; max-width: 1400px; margin-left: auto; margin-right: auto; }
    .section-header h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 600; color: var(--c-1a1714); }
    .section-header .section-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--c-6b5f54); font-weight: 400; display: block; margin-bottom: 6px; }
    .section-header a { color: var(--c-6b5f54); text-decoration: none; font-size: 0.85rem; transition: 0.15s; }
    .section-header a:hover { color: var(--c-1a1714); }
    .loader-container { text-align: center; padding: 48px 0; max-width: 1400px; margin: 0 auto; }
    .loader { display: inline-block; width: 36px; height: 36px; border: 2px solid var(--c-ece4db); border-top: 2px solid var(--c-1a1714); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    .end-message { text-align: center; padding: 48px 0; color: var(--c-6b5f54); font-size: 0.95rem; max-width: 1400px; margin: 0 auto; }
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
                @include('partials.shop-card', ['product' => $product])
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
                @include('partials.shop-card', ['product' => $product])
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
