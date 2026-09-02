<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ZAYLO · Quiet Luxury</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/style.css') }}" />
    <style>
        .role-access { padding: 80px 48px; background: #faf7f2; border-top: 1px solid #ece4db; }
        .role-access-header { text-align: center; margin-bottom: 56px; }
        .role-access-header .section-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: #6b5f54; font-weight: 400; display: block; margin-bottom: 10px; }
        .role-access-header h2 { font-family: 'Playfair Display', serif; font-size: 2.6rem; font-weight: 600; color: #1a1714; }
        .role-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 32px; max-width: 1100px; margin: 0 auto; }
        .role-card { background: white; padding: 48px 32px; text-align: center; border: 1px solid #ece4db; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; color: #1e1e1e; display: block; }
        .role-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.05); border-color: #b28b6f; }
        .role-card i { font-size: 3rem; color: #1a1714; margin-bottom: 16px; display: block; }
        .role-card h3 { font-size: 1.3rem; font-weight: 600; margin-bottom: 10px; color: #1a1714; }
        .role-card p { font-size: 0.9rem; color: #6b5f54; margin-bottom: 20px; }
        .role-card .btn-role { display: inline-block; background: #1a1714; color: white; padding: 10px 28px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
        .role-card:hover .btn-role { background: #b28b6f; }
        @media (max-width: 640px) { .role-cards { grid-template-columns: 1fr 1fr; gap: 16px; } .role-card { padding: 32px 20px; } }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="nav-links">
            <a href="{{ route('home') }}" class="home-link">Home</a>
            <a href="{{ auth()->check() ? route('buyer.products') : route('login') }}">Clothing</a>
            <a href="{{ auth()->check() ? route('buyer.products') : route('login') }}">Bags</a>
            <a href="{{ auth()->check() ? route('buyer.products') : route('login') }}">Shoes</a>
            <a href="{{ auth()->check() ? route('buyer.products') : route('login') }}">Accessories</a>
        </div>
        <a href="{{ route('home') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO" style="height:72px;width:auto;display:block;">
        </a>
        <div class="nav-actions">
            <div class="search-wrapper">
                <span class="search-icon">⌕</span>
                <input type="text" placeholder="Search" />
            </div>
            <div class="icon-group">
                <i class="far fa-heart"></i>
                <i class="fas fa-shopping-bag"></i>
            </div>
            <a href="{{ route('login') }}" class="login-text">Log in</a>
        </div>
    </header>
<div class="container">

    <div class="hero">
        <img class="hero-image"
            src="https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1400&q=80&auto=format&fit=crop&crop=center"
            alt="ZAYLO luxury editorial" />
        <div class="hero-text">
            <div class="hero-label">Best Collection</div>
            <div class="hero-headline">
                <span class="line1">Quiet luxury,</span>
                <span class="line2">loudly considered.</span>
            </div>
            <p class="hero-desc">
                Explore premium clothing and statement accessories curated for every season, every style, and every occasion.
            </p>
            <div class="hero-actions">
                <a href="{{ auth()->check() ? route('buyer.products') : route('login') }}" class="btn-primary">Shop the edit</a>
                <a href="{{ auth()->check() ? route('buyer.products') : route('login') }}" class="link-lookbook">View Lookbook →</a>
            </div>
        </div>
    </div>

    <div class="categories">
        <div class="category-item">
            <img class="circle-img" src="https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=120&h=120&fit=crop&crop=face&auto=format" alt="Men" />
            <div class="category-name">Men</div>
            <div class="item-count">120+ Items</div>
        </div>
        <div class="category-item">
            <img class="circle-img" src="https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=120&h=120&fit=crop&crop=face&auto=format" alt="Women" />
            <div class="category-name">Women</div>
            <div class="item-count">160+ Items</div>
        </div>
        <div class="category-item">
            <img class="circle-img" src="https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=120&h=120&fit=crop&crop=center&auto=format" alt="Bags" />
            <div class="category-name">Bags</div>
            <div class="item-count">220+ Items</div>
        </div>
        <div class="category-item">
            <img class="circle-img" src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&h=120&fit=crop&crop=center&auto=format" alt="Shoes" />
            <div class="category-name">Shoes</div>
            <div class="item-count">140+ Items</div>
        </div>
        <div class="category-item">
            <img class="circle-img" src="https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=120&h=120&fit=crop&crop=center&auto=format" alt="Watches" />
            <div class="category-name">Watches</div>
            <div class="item-count">250+ Items</div>
        </div>
        <div class="category-item">
            <img class="circle-img" src="https://images.unsplash.com/photo-1585123334904-845d60e6b056?w=120&h=120&fit=crop&crop=center&auto=format" alt="Accessories" />
            <div class="category-name">Accessories</div>
            <div class="item-count">320+ Items</div>
        </div>
    </div>

    <section class="role-access">
        <div class="role-access-header">
            <span class="section-label">Join the Community</span>
            <h2>Find Your Role on ZAYLO</h2>
        </div>
        <div class="role-cards">
            <a href="{{ route('login') }}" class="role-card">
                <i class="fas fa-user"></i>
                <h3>Buyer</h3>
                <p>Discover and shop luxury fashion from curated sellers.</p>
                <span class="btn-role">Shop Now</span>
            </a>
            <a href="{{ route('login') }}" class="role-card">
                <i class="fas fa-store"></i>
                <h3>Seller</h3>
                <p>List your products and reach thousands of buyers.</p>
                <span class="btn-role">Start Selling</span>
            </a>
            <a href="{{ route('login') }}" class="role-card">
                <i class="fas fa-motorcycle"></i>
                <h3>Courier</h3>
                <p>Deliver orders and earn competitive commissions.</p>
                <span class="btn-role">Deliver Now</span>
            </a>
        </div>
    </section>
</div>
</body>
</html>
