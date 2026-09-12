@extends('layouts.app')

@section('title', 'ZAYLO · Buyer Dashboard')

@section('nav-links')
@include('partials.store-nav')
@endsection

@section('nav-icons')
<a href="{{ route('buyer.wishlist') }}" style="position:relative;">
    <i class="far fa-heart"></i>
    <span class="badge-count">3</span>
</a>
<a href="{{ route('buyer.cart') }}" style="position:relative;">
    <i class="fas fa-shopping-bag"></i>
    <span class="badge-count" id="cartBadge">2</span>
</a>
<a href="{{ route('buyer.account') }}">
    <i class="far fa-user"></i>
</a>
@endsection

@push('styles')
<style>
    .badge-count { position: absolute; top: -8px; right: -8px; background: #b28b6f; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.55rem; font-weight: 600; min-width: 18px; text-align: center; font-family: 'Inter', sans-serif; }
    .icon-group a { color: #1e1e1e; text-decoration: none; transition: 0.15s; position: relative; }
    .icon-group a:hover { color: #8a7a6b; }
    .hero { position: relative; width: 100%; height: 580px; max-height: 620px; margin: 2px auto 0; overflow: hidden; background: #e8dfd6; }
    .hero-image { width: 100%; height: 100%; object-fit: cover; display: block; }
    .hero-text { position: absolute; top: 50%; left: 6%; transform: translateY(-50%); max-width: 700px; color: #1e1e1e; pointer-events: none; }
    .hero-label { font-size: 0.75rem; letter-spacing: 0.2em; text-transform: uppercase; color: #4a4037; margin-bottom: 16px; font-weight: 400; opacity: 0.8; }
    .hero-headline { font-family: 'Playfair Display', serif; font-size: 4.25rem; font-weight: 600; line-height: 1.1; margin-bottom: 20px; letter-spacing: -0.02em; }
    .hero-headline .line1 { color: #1a1714; display: block; }
    .hero-headline .line2 { color: #b28b6f; display: block; font-style: italic; }
    .hero-desc { font-size: 1rem; font-weight: 350; color: #2a241f; max-width: 420px; line-height: 1.6; margin-bottom: 32px; letter-spacing: 0.01em; opacity: 0.85; }
    .hero-actions { display: flex; align-items: center; gap: 32px; pointer-events: auto; }
    .btn-primary { background: #1a1714; color: white; border: none; padding: 14px 36px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; cursor: pointer; font-family: 'Inter', sans-serif; transition: 0.1s; text-decoration: none; display: inline-block; }
    .btn-primary:hover { background: #2f2721; color: white; }
    .link-lookbook { font-size: 0.85rem; font-weight: 450; letter-spacing: 0.04em; color: #1a1714; text-decoration: none; border-bottom: 1px solid transparent; transition: 0.15s; }
    .link-lookbook:hover { border-bottom-color: #1a1714; }
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
    .flash-badge { background: #c0392b; color: white; padding: 5px 18px; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
    .flash-timer { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: #6b5f54; }
    .flash-timer .time-block { display: flex; align-items: center; gap: 6px; }
    .flash-timer .time-block .number { font-size: 1.6rem; font-weight: 600; color: #1a1714; font-family: 'Playfair Display', serif; min-width: 36px; text-align: center; }
    .flash-timer .time-block .label { font-size: 0.6rem; text-transform: uppercase; color: #6b5f54; }
    .flash-timer .separator { font-size: 1.4rem; color: #b28b6f; font-weight: 300; }
    .flash-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; max-width: 1400px; margin: 0 auto; }
    .flash-product { background: white; border: 2px solid #c0392b; position: relative; overflow: hidden; transition: 0.2s; }
    .flash-product:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(192,57,43,0.1); }
    .flash-product .product-image { position: relative; overflow: hidden; aspect-ratio: 3/4; background: #f5f0ea; }
    .flash-product .product-image img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
    .flash-product:hover .product-image img { transform: scale(1.03); }
    .flash-discount { position: absolute; top: 12px; left: 12px; background: #c0392b; color: white; padding: 4px 12px; font-size: 0.65rem; font-weight: 700; }
    .flash-product .product-wishlist { position: absolute; top: 12px; right: 12px; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.15s; box-shadow: 0 2px 8px rgba(0,0,0,0.06); font-size: 1rem; color: #1a1714; display: flex; align-items: center; justify-content: center; }
    .flash-product .product-wishlist:hover { background: #1a1714; color: white; }
    .flash-product .product-info { padding: 20px 24px 24px; }
    .flash-product .product-name { font-size: 0.95rem; font-weight: 500; color: #1a1714; margin-bottom: 2px; }
    .flash-product .product-category { font-size: 0.7rem; color: #6b5f54; text-transform: uppercase; letter-spacing: 0.04em; }
    .flash-product .product-price { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .flash-product .current-price { font-size: 1.05rem; font-weight: 600; color: #c0392b; }
    .flash-product .original-price { font-size: 0.8rem; color: #6b5f54; text-decoration: line-through; }
    .flash-product .btn-flash { width: 100%; margin-top: 14px; padding: 12px; background: #c0392b; color: white; border: none; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; transition: 0.15s; font-family: 'Inter', sans-serif; }
    .flash-product .btn-flash:hover { background: #a93226; }
    .flash-product .btn-flash.added { background: #2d7d46; }
    .suggested-products { padding: 60px 48px 72px; background: #faf7f2; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; max-width: 1400px; margin-left: auto; margin-right: auto; }
    .section-header h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 600; color: #1a1714; }
    .section-header .section-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: #6b5f54; font-weight: 400; display: block; margin-bottom: 6px; }
    .section-header a { color: #6b5f54; text-decoration: none; font-size: 0.85rem; transition: 0.15s; }
    .section-header a:hover { color: #1a1714; }
    .product-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; max-width: 1400px; margin: 0 auto; }
    .product-card { background: white; border: 1px solid #ece4db; transition: 0.3s; overflow: hidden; animation: floatIn 0.6s ease forwards; opacity: 0; }
    .product-card:nth-child(1){animation-delay:0.05s} .product-card:nth-child(2){animation-delay:0.1s} .product-card:nth-child(3){animation-delay:0.15s} .product-card:nth-child(4){animation-delay:0.2s}
    @keyframes floatIn { 0%{opacity:0;transform:translateY(30px) scale(0.95)} 100%{opacity:1;transform:translateY(0) scale(1)} }
    .product-card:hover { transform: translateY(-6px) scale(1.01); border-color: #b28b6f; box-shadow: 0 8px 32px rgba(0,0,0,0.06); }
    .product-card .product-image { position: relative; overflow: hidden; aspect-ratio: 3/4; background: #f5f0ea; }
    .product-card .product-image img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
    .product-card:hover .product-image img { transform: scale(1.05); }
    .product-card .product-wishlist { position: absolute; top: 12px; right: 12px; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.15s; box-shadow: 0 2px 8px rgba(0,0,0,0.06); font-size: 1rem; color: #1a1714; display: flex; align-items: center; justify-content: center; }
    .product-card .product-wishlist:hover, .product-card .product-wishlist.active { background: #1a1714; color: white; }
    .product-card .product-badge { position: absolute; top: 12px; left: 12px; padding: 4px 14px; font-size: 0.55rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; background: #1a1714; color: white; }
    .product-card .product-badge.sale { background: #b28b6f; }
    .product-card .product-badge.best { background: #d4af37; }
    .product-card .product-info { padding: 20px 24px 24px; }
    .product-card .product-name { font-size: 0.95rem; font-weight: 500; color: #1a1714; margin-bottom: 2px; }
    .product-card .product-category { font-size: 0.7rem; color: #6b5f54; text-transform: uppercase; letter-spacing: 0.04em; }
    .product-card .product-rating { font-size: 0.7rem; color: #d4af37; margin: 8px 0; }
    .product-card .product-rating span { color: #6b5f54; margin-left: 4px; }
    .product-card .product-price { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .product-card .current-price { font-size: 1.05rem; font-weight: 600; color: #1a1714; }
    .product-card .original-price { font-size: 0.8rem; color: #6b5f54; text-decoration: line-through; }
    .product-card .btn-add-cart { width: 100%; margin-top: 14px; padding: 12px; background: #1a1714; color: white; border: none; font-size: 0.7rem; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; transition: 0.15s; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .product-card .btn-add-cart:hover { background: #b28b6f; }
    .product-card .btn-add-cart.added { background: #2d7d46; }
    .loader-container { text-align: center; padding: 48px 0; max-width: 1400px; margin: 0 auto; }
    .loader { display: inline-block; width: 36px; height: 36px; border: 2px solid #ece4db; border-top: 2px solid #1a1714; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    .end-message { text-align: center; padding: 48px 0; color: #6b5f54; font-size: 0.95rem; max-width: 1400px; margin: 0 auto; }
    .end-message span { display: block; font-size: 1.8rem; margin-bottom: 10px; }
    @media (max-width: 1024px) { .product-grid, .flash-grid { grid-template-columns: repeat(2,1fr); gap: 20px; } }
    @media (max-width: 640px) { .flash-grid, .product-grid { grid-template-columns: 1fr 1fr; gap: 12px; } .categories { flex-wrap: nowrap; overflow-x: auto; justify-content: flex-start; gap: 28px; padding: 28px 20px 36px; } .category-item { flex: 0 0 auto; } }
</style>
@endpush

@section('content')
    <!-- Hero -->
    <div class="hero">
        <img class="hero-image" src="https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1400&q=80&auto=format&fit=crop&crop=center" alt="Featured products available on ZAYLO" onerror="this.style.display='none'" />
        <div class="hero-text">
            <div class="hero-label">Welcome back, {{ $user->name }}</div>
            <div class="hero-headline">
                <span class="line1">Everything you need,</span>
                <span class="line2">all in one place.</span>
            </div>
            <p class="hero-desc">Shop tech, home essentials, fashion, beauty, groceries, and more from trusted marketplace sellers.</p>
            <div class="hero-actions">
                <a href="{{ route('products.index') }}" class="btn-primary">Shop the marketplace</a>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" class="link-lookbook">See what's new →</a>
            </div>
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
            <div class="flash-timer">
                <span>Ends in:</span>
                <div class="time-block"><span class="number" id="hours">02</span><span class="label">Hrs</span></div>
                <span class="separator">:</span>
                <div class="time-block"><span class="number" id="minutes">45</span><span class="label">Min</span></div>
                <span class="separator">:</span>
                <div class="time-block"><span class="number" id="seconds">30</span><span class="label">Sec</span></div>
            </div>
        </div>
        <div class="flash-grid" id="flashGrid">
            @foreach($flashProducts as $product)
            @php $discount = round((1 - $product->price / $product->original_price) * 100) . '% OFF'; @endphp
            <div class="flash-product">
                <div class="product-image">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" />
                    <span class="flash-discount">{{ $discount }}</span>
                    <form method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">@csrf<button class="product-wishlist" aria-label="Save {{ $product->name }}"><i class="far fa-heart"></i></button></form>
                </div>
                <div class="product-info">
                    <h3 class="product-name">{{ $product->name }}</h3>
                    <p class="product-category">{{ config('marketplace.categories.'.$product->category, str($product->category)->replace('_', ' ')->title()) }}</p>
                    <div class="product-price">
                        <span class="current-price">₱{{ number_format($product->price, 2) }}</span>
                        <span class="original-price">₱{{ number_format($product->original_price, 2) }}</span>
                    </div>
                    <form method="POST" action="{{ route('buyer.cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="btn-flash"><i class="fas fa-bolt"></i> Grab Now</button></form>
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
            <div class="product-card">
                <div class="product-image">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" />
                    <form method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">@csrf<button class="product-wishlist" aria-label="Save {{ $product->name }}"><i class="far fa-heart"></i></button></form>
                    @if($product->badge)
                        <span class="product-badge {{ $product->badge === 'Sale' ? 'sale' : ($product->badge === 'Best Seller' ? 'best' : '') }}">{{ $product->badge }}</span>
                    @endif
                </div>
                <div class="product-info">
                    <h3 class="product-name">{{ $product->name }}</h3>
                    <p class="product-category">{{ config('marketplace.categories.'.$product->category, str($product->category)->replace('_', ' ')->title()) }}</p>
                    <div class="product-price">
                        <span class="current-price">₱{{ number_format($product->price, 2) }}</span>
                        @if($product->original_price)
                            <span class="original-price">₱{{ number_format($product->original_price, 2) }}</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('buyer.cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="btn-add-cart"><i class="fas fa-shopping-bag"></i> Add to Cart</button></form>
                </div>
            </div>
            @endforeach
        </div>
        <div class="end-message"><span>✨</span>You've reached the end of our suggestions</div>
    </section>
@endsection

@push('scripts')
<script>
// Flash timer
let flashEndTime = new Date();
flashEndTime.setHours(flashEndTime.getHours() + 2);
flashEndTime.setMinutes(flashEndTime.getMinutes() + 45);
function updateFlashTimer() {
    const diff = flashEndTime - new Date();
    if (diff <= 0) return;
    document.getElementById('hours').textContent = String(Math.floor(diff/3600000)).padStart(2,'0');
    document.getElementById('minutes').textContent = String(Math.floor((diff%3600000)/60000)).padStart(2,'0');
    document.getElementById('seconds').textContent = String(Math.floor((diff%60000)/1000)).padStart(2,'0');
}
setInterval(updateFlashTimer, 1000); updateFlashTimer();

function toggleWishlist(btn) {
    btn.classList.toggle('active');
    const icon = btn.querySelector('i');
    icon.className = btn.classList.contains('active') ? 'fas fa-heart' : 'far fa-heart';
    showNotification(btn.classList.contains('active') ? 'Added to wishlist ❤️' : 'Removed from wishlist');
}
function addToCart(btn, id) {
    btn.classList.toggle('added');
    btn.innerHTML = btn.classList.contains('added') ? '✓ Added' : '<i class="fas fa-shopping-bag"></i> Add to Cart';
    if (btn.classList.contains('added')) {
        const badge = document.getElementById('cartBadge');
        if (badge) badge.textContent = parseInt(badge.textContent || 0) + 1;
    }
    showNotification(btn.classList.contains('added') ? 'Added to cart 🛒' : 'Removed from cart');
}
function showNotification(msg) {
    const old = document.querySelector('.zaylo-notif'); if (old) old.remove();
    const n = document.createElement('div');
    n.className = 'zaylo-notif';
    n.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a1714;color:white;padding:14px 24px;font-family:Inter,sans-serif;font-size:0.8rem;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,0.1);animation:slideUp 0.3s ease';
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 2800);
}
const style = document.createElement('style');
style.textContent = '@keyframes slideUp{from{transform:translateY(100px);opacity:0}to{transform:translateY(0);opacity:1}}';
document.head.appendChild(style);
</script>
@endpush
