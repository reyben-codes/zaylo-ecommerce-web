<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $storeName }} | Products</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, sans-serif; background: #faf7f2; color: #1e1e1e; }
        button, input, select, textarea { font: inherit; }
        .navbar { min-height: 72px; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; border-top: 2px solid #8cb58a; border-bottom: 1px solid #ece4db; position: sticky; top: 0; z-index: 20; }
        .logo img { width: 78px; display: block; }
        .shop-chip { display: flex; align-items: center; gap: 10px; color: #6b5f54; font-size: .82rem; }
        .shop-chip strong { color: #1a1714; }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .icon-button, .logout-button { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #e2d9cf; border-radius: 50%; background: #fff; color: #1e1e1e; cursor: pointer; text-decoration: none; }
        .logout-form { margin: 0; }
        .layout { min-height: calc(100vh - 72px); display: flex; }
        .main-content { min-width: 0; flex: 1; padding: 34px clamp(20px, 4vw, 54px) 60px; }
        .page-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 26px; }
        .eyebrow { margin: 0 0 7px; color: #9a755e; font-size: .7rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 0; font: 600 clamp(2rem, 4vw, 3rem)/1.1 'Playfair Display', serif; }
        .page-subtitle { margin: 9px 0 0; color: #6b5f54; }
        .primary-button, .secondary-button, .action-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: .18s ease; }
        .primary-button { min-height: 44px; padding: 0 18px; border: 1px solid #1a1714; }
        .secondary-button { min-height: 42px; padding: 0 16px; border: 1px solid #d8cec4; background: #fff; color: #403830; }
        .flash { margin-bottom: 20px; padding: 13px 16px; border: 1px solid #bcd6bc; border-radius: 9px; background: #edf7ed; color: #315c35; font-size: .88rem; }
        .flash.error { border-color: #e3beb8; background: #fff0ed; color: #8d403b; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .search-box { width: min(460px, 100%); display: flex; align-items: center; gap: 10px; padding: 0 15px; border: 1px solid #ded5cc; border-radius: 9px; background: #fff; }
        .search-box:focus-within { border-color: #9a755e; box-shadow: 0 0 0 3px rgba(154,117,94,.12); }
        .search-box i { color: #88796c; }
        .search-box input { width: 100%; height: 44px; border: 0; outline: 0; background: transparent; }
        .product-count { margin: 0; color: #6b5f54; font-size: .82rem; white-space: nowrap; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(255px, 1fr)); gap: 20px; }
        .product-card { display: flex; flex-direction: column; overflow: hidden; border: 1px solid #e9e0d7; border-radius: 12px; background: #fff; box-shadow: 0 7px 22px rgba(55,39,26,.05); }
        .product-card[hidden] { display: none; }
        .product-image-wrap { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: #eee8e1; }
        .product-image { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
        .product-card:hover .product-image { transform: scale(1.035); }
        .status-badge { position: absolute; top: 12px; right: 12px; padding: 5px 9px; border-radius: 999px; background: #e6f2e5; color: #39633b; font-size: .68rem; font-weight: 700; }
        .status-badge.inactive { background: #eee9e4; color: #6b5f54; }
        .product-body { flex: 1; display: flex; flex-direction: column; padding: 18px; }
        .product-category { margin: 0 0 7px; color: #9a755e; font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .product-name { margin: 0; font: 600 1.18rem/1.25 'Playfair Display', serif; }
        .product-description { min-height: 42px; margin: 9px 0 16px; display: -webkit-box; overflow: hidden; color: #6b5f54; font-size: .78rem; line-height: 1.55; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .product-meta { display: flex; justify-content: space-between; gap: 12px; margin-top: auto; padding-top: 14px; border-top: 1px solid #eee7df; }
        .price { font-weight: 700; }
        .original-price { margin-left: 5px; color: #9f958c; font-size: .72rem; text-decoration: line-through; }
        .stock { color: #537455; font-size: .75rem; }
        .stock.low { color: #9a6a2f; }
        .stock.out { color: #9b403a; }
        .product-actions { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-top: 15px; }
        .action-button { min-height: 38px; padding: 0 12px; border: 1px solid #dcd3ca; background: #fff; color: #3b342e; font-size: .76rem; }
        .action-button:hover { border-color: #9a755e; color: #8a634d; }
        .action-button.delete { color: #8d403b; }
        .empty-state { grid-column: 1/-1; padding: 70px 20px; border: 1px dashed #d9cec3; border-radius: 12px; text-align: center; color: #6b5f54; }
        .empty-state i { display: block; margin-bottom: 14px; font-size: 2rem; }
        .pagination { margin-top: 28px; }
        .pagination svg { width: 16px; }
        .modal-overlay { position: fixed; inset: 0; z-index: 100; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(25,21,18,.58); overflow-y: auto; }
        .modal-overlay.show { display: flex; }
        .modal { width: min(780px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; border-radius: 14px; background: #fff; box-shadow: 0 20px 60px rgba(0,0,0,.23); }
        .modal-header { position: sticky; top: 0; z-index: 2; display: flex; justify-content: space-between; align-items: center; padding: 21px 24px; border-bottom: 1px solid #eee4db; background: #fff; }
        .modal-title { margin: 0; font: 600 1.55rem 'Playfair Display', serif; }
        .close-modal { width: 38px; height: 38px; border: 0; border-radius: 50%; background: #f5f0ea; cursor: pointer; }
        .product-form { padding: 24px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .form-group { min-width: 0; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 7px; font-size: .76rem; font-weight: 700; color: #4f453c; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; min-height: 43px; padding: 10px 12px; border: 1px solid #dcd2c8; border-radius: 7px; background: #fff; color: #1e1e1e; outline: 0; }
        .form-group textarea { min-height: 105px; resize: vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #9a755e; box-shadow: 0 0 0 3px rgba(154,117,94,.11); }
        .file-help { margin: 6px 0 0; color: #81756b; font-size: .7rem; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 18px; border-top: 1px solid #eee5dc; }
        @media (max-width: 820px) { .navbar { padding: 0 16px; } .shop-chip { display: none; } .main-content { padding: 25px 16px 45px; } .page-header { align-items: flex-start; flex-direction: column; } }
        @media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; } .form-group.full { grid-column: auto; } .products-grid { grid-template-columns: 1fr; } .toolbar { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body class="seller-workspace">
    <header class="navbar">
        <div class="shop-chip"><i class="fa-solid fa-store"></i><span>Managing <strong>{{ $storeName }}</strong></span></div>
        <a href="{{ route('seller.dashboard') }}" class="logo" aria-label="Seller dashboard"><img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO"></a>
        <div class="header-actions">
            <a href="{{ route('seller.account') }}" class="icon-button" aria-label="Seller account"><i class="fa-regular fa-user"></i></a>
            <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="logout-button" aria-label="Log out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form>
        </div>
    </header>

    <div class="layout">
        @include('partials.seller-sidebar')
        <main class="main-content">
            <div class="page-header">
                <div><p class="eyebrow">{{ $storeName }}</p><h1>Product Management</h1><p class="page-subtitle">Update the fashion products shown in the buyer shop.</p></div>
                <button type="button" class="primary-button" onclick="openAddProductModal()"><i class="fa-solid fa-plus"></i> Add Product</button>
            </div>
            @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash error" role="alert">Please check the product details and try again.</div>@endif
            <div class="toolbar">
                <label class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="productSearch" placeholder="Search by name, SKU, category, or status" aria-label="Search products"></label>
                <p class="product-count" id="productCount">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
            </div>
            <section class="products-grid" id="productsGrid">
                @forelse ($products as $product)
                    @php
                        $categoryLabel = $product->category_label;
                        $searchText = Str::lower($product->name.' '.$product->sku.' '.$categoryLabel.' '.($product->is_active ? 'active' : 'inactive'));
                    @endphp
                    <article class="product-card" data-search="{{ $searchText }}">
                        <div class="product-image-wrap">
                            <img class="product-image" src="{{ $product->image_url ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="{{ $product->name }}" onerror="this.src='{{ asset('images/ZAYLO_LOGO_DARK.png') }}'">
                            <span class="status-badge {{ $product->is_active ? '' : 'inactive' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="product-body">
                            <p class="product-category">{{ $categoryLabel }}@if($product->gender) · {{ ucfirst($product->gender) }}@endif</p>
                            <h2 class="product-name">{{ $product->name }}</h2>
                            <p class="product-description">{{ $product->description ?: 'No description has been added yet.' }}</p>
                            <div class="product-meta">
                                <span><span class="price">₱{{ number_format((float) $product->price, 2) }}</span>@if($product->original_price)<span class="original-price">₱{{ number_format((float) $product->original_price, 2) }}</span>@endif</span>
                                <span class="stock {{ $product->stock === 0 ? 'out' : ($product->stock <= $product->low_stock_threshold ? 'low' : '') }}">{{ $product->stock === 0 ? 'Out of stock' : $product->stock.' in stock' }}</span>
                            </div>
                            <div class="product-actions">
                                <button type="button" class="action-button edit-product" data-action="{{ route('seller.products.update', $product) }}" data-name="{{ $product->name }}" data-sku="{{ $product->sku }}" data-category="{{ $product->category_key }}" data-gender="{{ $product->gender }}" data-price="{{ $product->price }}" data-original-price="{{ $product->original_price }}" data-stock="{{ $product->stock }}" data-threshold="{{ $product->low_stock_threshold }}" data-weight="{{ $product->weight_grams }}" data-badge="{{ $product->badge }}" data-active="{{ $product->is_active ? '1' : '0' }}" data-description="{{ $product->description }}"><i class="fa-solid fa-pen"></i> Edit details</button>
                                <form method="POST" action="{{ route('seller.products.destroy', $product) }}" onsubmit="return confirm('Delete this product from the shop?')">@csrf @method('DELETE')<button type="submit" class="action-button delete" aria-label="Delete {{ $product->name }}"><i class="fa-solid fa-trash"></i></button></form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty-state"><i class="fa-solid fa-box-open"></i><strong>No products yet</strong><p>Add the first product for {{ $storeName }}.</p></div>
                @endforelse
                <div class="empty-state" id="searchEmpty" hidden><i class="fa-solid fa-magnifying-glass"></i><strong>No matching products</strong><p>Try another search term.</p></div>
            </section>
            <div class="pagination">{{ $products->links() }}</div>
        </main>
    </div>

    <div class="modal-overlay {{ $errors->any() ? 'show' : '' }}" id="productModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal">
            <div class="modal-header"><h2 class="modal-title" id="modalTitle">Add Product</h2><button type="button" class="close-modal" onclick="closeProductModal()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
            <form method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data" class="product-form" id="productForm">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST" disabled>
                <div class="form-grid">
                    <div class="form-group full"><label for="productName">Product name *</label><input id="productName" name="name" value="{{ old('name') }}" maxlength="150" required></div>
                    <div class="form-group"><label for="productSku">SKU</label><input id="productSku" name="sku" value="{{ old('sku') }}" maxlength="80" placeholder="Generated when left blank"></div>
                    <div class="form-group"><label for="productCategory">Category *</label><select id="productCategory" name="category" required><option value="">Select category</option>@foreach(config('marketplace.fashion_categories') as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ config('marketplace.categories')[$category] }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="productGender">Gender</label><select id="productGender" name="gender"><option value="">Not specified</option><option value="women">Women</option><option value="men">Men</option><option value="unisex">Unisex</option></select></div>
                    <div class="form-group"><label for="productBadge">Badge</label><select id="productBadge" name="badge"><option value="">No badge</option><option value="New">New</option><option value="Sale">Sale</option><option value="Best Seller">Best Seller</option></select></div>
                    <div class="form-group"><label for="productPrice">Selling price (₱) *</label><input type="number" id="productPrice" name="price" value="{{ old('price') }}" min="0" max="99999999.99" step="0.01" required></div>
                    <div class="form-group"><label for="productOriginalPrice">Original price (₱)</label><input type="number" id="productOriginalPrice" name="original_price" value="{{ old('original_price') }}" min="0" max="99999999.99" step="0.01"></div>
                    <div class="form-group"><label for="productStock">Available stock *</label><input type="number" id="productStock" name="stock" value="{{ old('stock', 0) }}" min="0" required></div>
                    <div class="form-group"><label for="productThreshold">Low stock alert *</label><input type="number" id="productThreshold" name="low_stock_threshold" value="{{ old('low_stock_threshold', 5) }}" min="0" required></div>
                    <div class="form-group"><label for="productWeight">Weight (grams)</label><input type="number" id="productWeight" name="weight_grams" value="{{ old('weight_grams') }}" min="0"></div>
                    <div class="form-group"><label for="productStatus">Buyer shop visibility *</label><select id="productStatus" name="is_active"><option value="1">Active — visible to buyers</option><option value="0">Inactive — hidden from buyers</option></select></div>
                    <div class="form-group full"><label for="productImage">Product picture</label><input type="file" id="productImage" name="image" accept="image/jpeg,image/png,image/webp"><p class="file-help" id="imageHelp">JPG, PNG, or WebP up to 5 MB.</p></div>
                    <div class="form-group full"><label for="productDescription">Description</label><textarea id="productDescription" name="description" maxlength="2000">{{ old('description') }}</textarea></div>
                </div>
                @if ($errors->any())<div class="flash error" style="margin-top:18px;margin-bottom:0"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <div class="modal-footer"><button type="button" class="secondary-button" onclick="closeProductModal()">Cancel</button><button type="submit" class="primary-button"><i class="fa-solid fa-check"></i> Save product</button></div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('productModal');
        const form = document.getElementById('productForm');
        const createAction = @json(route('seller.products.store'));
        const field = id => document.getElementById(id);
        function openAddProductModal() {
            form.reset(); form.action = createAction; field('formMethod').disabled = true; field('formMethod').value = 'POST';
            field('modalTitle').textContent = 'Add Product'; field('productStock').value = 0; field('productThreshold').value = 5; field('productStatus').value = '1';
            field('imageHelp').textContent = 'JPG, PNG, or WebP up to 5 MB.'; modal.classList.add('show'); field('productName').focus();
        }
        document.querySelectorAll('.edit-product').forEach(button => button.addEventListener('click', () => {
            const data = button.dataset; form.reset(); form.action = data.action; field('formMethod').disabled = false; field('formMethod').value = 'PUT'; field('modalTitle').textContent = 'Edit Product';
            field('productName').value = data.name || ''; field('productSku').value = data.sku || ''; field('productCategory').value = data.category || ''; field('productGender').value = data.gender || ''; field('productBadge').value = data.badge || '';
            field('productPrice').value = data.price || ''; field('productOriginalPrice').value = data.originalPrice || ''; field('productStock').value = data.stock || '0'; field('productThreshold').value = data.threshold || '0'; field('productWeight').value = data.weight || '';
            field('productStatus').value = data.active || '0'; field('productDescription').value = data.description || ''; field('imageHelp').textContent = 'Leave empty to keep the current picture. JPG, PNG, or WebP up to 5 MB.'; modal.classList.add('show'); field('productName').focus();
        }));
        function closeProductModal() { modal.classList.remove('show'); }
        modal.addEventListener('click', event => { if (event.target === modal) closeProductModal(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeProductModal(); });
        field('productSearch').addEventListener('input', event => {
            const query = event.target.value.trim().toLowerCase(); const cards = [...document.querySelectorAll('.product-card')]; let visible = 0;
            cards.forEach(card => { const match = card.dataset.search.includes(query); card.hidden = !match; if (match) visible++; });
            field('productCount').textContent = `${visible} ${visible === 1 ? 'product' : 'products'} on this page`; field('searchEmpty').hidden = visible !== 0;
        });
    </script>
</body>
</html>
