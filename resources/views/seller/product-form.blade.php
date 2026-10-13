@extends('layouts.seller')

@section('title', $product ? 'Edit '.$product->name : 'Add product')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-products.css') }}?v={{ filemtime(public_path('css/seller-products.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/product-sales.css') }}?v={{ filemtime(public_path('css/product-sales.css')) }}">
@endpush

@section('content')
    @php
        $variants = $product ? $product->variants : collect();
        // Deployed products price and stock every variation separately; newer ones keep a default variation for simple products.
        $multiVariant = $legacy ? $variants->isNotEmpty() : $variants->count() > 1;
        $value = fn (string $field, $default = null) => old($field, $default);
        $saleError = (bool) old('sale_product_id');
        $fieldError = fn (string $field) => ! $saleError && $errors->has($field);
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow"><a href="{{ route('seller.products') }}" class="product-form__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Products</a></p>
            <h1>{{ $product ? $product->name : 'Add product' }}</h1>
            <p>{{ $product ? 'Changes appear in your shop as soon as you save.' : 'Fill in the details buyers see. You can add sales after saving.' }}</p>
        </div>
        @if($product?->is_active)
            <div class="seller-page-heading__actions">
                <a href="{{ route('products.show', $product) }}" class="seller-button seller-button--secondary" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> View in shop</a>
            </div>
        @endif
    </section>

    <div class="seller-panel-grid product-form-grid">
        <form method="POST" action="{{ $product ? route('seller.products.update', $product) : route('seller.products.store') }}" enctype="multipart/form-data" class="product-form" id="productForm">
            @csrf
            @if($product) @method('PUT') @endif

            <section class="seller-panel" aria-labelledby="details-heading">
                <header class="seller-panel__header"><div><h2 id="details-heading">Details</h2><p>Name, SKU, category, and the description shown on the product page.</p></div></header>
                <div class="seller-panel__body seller-form-grid">
                    <div class="seller-field seller-field--full">
                        <label for="productName">Product name</label>
                        <input id="productName" name="name" value="{{ $value('name', $product?->name) }}" maxlength="150" required @if($fieldError('name')) aria-invalid="true" @endif>
                        @if($fieldError('name'))<p class="seller-field__error">{{ $errors->first('name') }}</p>@endif
                    </div>
                    <div class="seller-field">
                        <label for="productSku">SKU</label>
                        <input id="productSku" name="sku" value="{{ $value('sku', $product?->sku) }}" maxlength="80" placeholder="Generated when left blank" @if($fieldError('sku')) aria-invalid="true" @endif>
                        <p class="seller-field__help">Your own stock code. Must be unique.</p>
                        @if($fieldError('sku'))<p class="seller-field__error">{{ $errors->first('sku') }}</p>@endif
                    </div>
                    <div class="seller-field">
                        <label for="productCategory">Category</label>
                        <select id="productCategory" name="category" required @if($fieldError('category')) aria-invalid="true" @endif>
                            <option value="">Select category</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}" @selected($value('category', $product?->category_key) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if($fieldError('category'))<p class="seller-field__error">{{ $errors->first('category') }}</p>@endif
                    </div>
                    <div class="seller-field">
                        <label for="productGender">For</label>
                        <select id="productGender" name="gender">
                            <option value="">Not specified</option>
                            @foreach(['women' => 'Women', 'men' => 'Men', 'unisex' => 'Unisex'] as $key => $label)
                                <option value="{{ $key }}" @selected($value('gender', $product?->gender) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="seller-field">
                        <label for="productBadge">Badge</label>
                        <select id="productBadge" name="badge">
                            <option value="">No badge</option>
                            @foreach(['New', 'Best Seller'] as $badge)
                                <option value="{{ $badge }}" @selected($value('badge', $product?->getRawOriginal('badge')) === $badge)>{{ $badge }}</option>
                            @endforeach
                        </select>
                        <p class="seller-field__help">"Sale" is added automatically during a scheduled sale.</p>
                    </div>
                    <div class="seller-field seller-field--full">
                        <label for="productDescription">Description</label>
                        <textarea id="productDescription" name="description" maxlength="2000" rows="6" @if($fieldError('description')) aria-invalid="true" @endif>{{ $value('description', $product?->description) }}</textarea>
                        <p class="seller-field__help">Materials, sizing, care, and what's in the box. Up to 2,000 characters.</p>
                        @if($fieldError('description'))<p class="seller-field__error">{{ $errors->first('description') }}</p>@endif
                    </div>
                </div>
            </section>

            <section class="seller-panel" aria-labelledby="pricing-heading">
                <header class="seller-panel__header"><div><h2 id="pricing-heading">Price</h2><p>Your everyday price. Discounts are scheduled under Sale.</p></div></header>
                <div class="seller-panel__body seller-form-grid">
                    @if($multiVariant)
                        <p class="product-form__note product-form__note--plain"><i class="fas fa-palette" aria-hidden="true"></i> Each option has its own price. Set them under Colors &amp; sizes.</p>
                    @else
                        <div class="seller-field">
                            <label for="productPrice">Regular price (&#8369;)</label>
                            <input type="number" id="productPrice" name="price" value="{{ $value('price', $product?->regular_price) }}" min="0" max="99999999.99" step="0.01" required @if($fieldError('price')) aria-invalid="true" @endif>
                            @if($fieldError('price'))<p class="seller-field__error">{{ $errors->first('price') }}</p>@endif
                        </div>
                    @endif
                    @if($product?->saleIsActive())
                        <p class="product-form__note"><i class="fas fa-tag" aria-hidden="true"></i> On sale now for &#8369;{{ number_format((float) $product->price, 2) }}.</p>
                    @endif
                </div>
            </section>

            <section class="seller-panel" aria-labelledby="stock-heading">
                <header class="seller-panel__header"><div><h2 id="stock-heading">Stock</h2><p>{{ $multiVariant ? 'Restock each option under Colors & sizes.' : 'How many you can sell, and when to warn you.' }}</p></div></header>
                <div class="seller-panel__body seller-form-grid">
                    @unless($multiVariant)
                        <div class="seller-field">
                            <label for="productStock">Available stock</label>
                            <input type="number" id="productStock" name="stock" value="{{ $value('stock', $product?->stock ?? 0) }}" min="0" max="1000000" required @if($fieldError('stock')) aria-invalid="true" @endif>
                            @if($fieldError('stock'))<p class="seller-field__error">{{ $errors->first('stock') }}</p>@endif
                        </div>
                    @endunless
                    <div class="seller-field">
                        <label for="productThreshold">Low stock alert</label>
                        <input type="number" id="productThreshold" name="low_stock_threshold" value="{{ $value('low_stock_threshold', $product?->low_stock_threshold ?? 5) }}" min="0" max="100000" required @if($fieldError('low_stock_threshold')) aria-invalid="true" @endif>
                        <p class="seller-field__help">Shown as "Low stock" in Inventory at or below this number.</p>
                        @if($fieldError('low_stock_threshold'))<p class="seller-field__error">{{ $errors->first('low_stock_threshold') }}</p>@endif
                    </div>
                    <div class="seller-field">
                        <label for="productWeight">Weight (grams)</label>
                        <input type="number" id="productWeight" name="weight_grams" value="{{ $value('weight_grams', $product?->weight_grams) }}" min="0" max="1000000">
                        <p class="seller-field__help">Optional. Used for shipping.</p>
                    </div>
                </div>
            </section>

            <section class="seller-panel" aria-labelledby="photo-heading">
                <header class="seller-panel__header"><div><h2 id="photo-heading">Photo</h2><p>The main picture on the product page and in search.</p></div></header>
                <div class="seller-panel__body product-form__photo">
                    <img src="{{ $product?->image_url ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="" class="product-form__preview{{ $product?->image_url ? '' : ' is-placeholder' }}" data-image-preview data-fallback-src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}">
                    <div class="seller-field">
                        <label for="productImage">{{ $product?->image_url ? 'Replace photo' : 'Upload photo' }}</label>
                        <input type="file" id="productImage" name="image" accept="image/jpeg,image/png,image/webp" data-image-input @if($fieldError('image')) aria-invalid="true" @endif>
                        <p class="seller-field__help">JPG, PNG, or WebP up to 5 MB.{{ $product ? ' Leave empty to keep the current photo.' : '' }}</p>
                        @if($fieldError('image'))<p class="seller-field__error">{{ $errors->first('image') }}</p>@endif
                    </div>
                </div>
            </section>

            <section class="seller-panel" aria-labelledby="visibility-heading">
                <header class="seller-panel__header"><div><h2 id="visibility-heading">Visibility</h2></div></header>
                <div class="seller-panel__body">
                    <div class="seller-field">
                        <label for="productStatus">Shown in your shop</label>
                        <select id="productStatus" name="is_active">
                            <option value="1" @selected((string) $value('is_active', $product ? (int) $product->is_active : 1) === '1')>Active — visible to buyers</option>
                            <option value="0" @selected((string) $value('is_active', $product ? (int) $product->is_active : 1) === '0')>Hidden — only you can see it</option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="product-form__actions">
                <a href="{{ route('seller.products') }}" class="seller-button seller-button--secondary">Cancel</a>
                <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> {{ $product ? 'Save changes' : 'Create product' }}</button>
            </div>
        </form>

        <aside class="product-form__side">
            @if($product)
                <section class="seller-panel" aria-labelledby="summary-heading">
                    <header class="seller-panel__header"><h2 id="summary-heading">At a glance</h2></header>
                    <dl class="seller-panel__body product-form__summary">
                        <div><dt>Stock</dt><dd><span class="stock-pill stock-pill--{{ $stockState['state'] }}">{{ $stockState['total'] }} available</span></dd></div>
                        <div><dt>Visibility</dt><dd>{{ $product->is_active ? 'Visible to buyers' : 'Hidden' }}</dd></div>
                        <div><dt>Sale</dt><dd>{{ $product->sale_status }}</dd></div>
                        <div><dt>Last updated</dt><dd>{{ $product->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
                    </dl>
                </section>

                @include('seller.partials.product-sizes')

                <section class="seller-panel" aria-labelledby="sale-heading">
                    <header class="seller-panel__header"><div><h2 id="sale-heading">Sale</h2><p>Schedule a discount that starts and ends automatically.</p></div></header>
                    <div class="seller-panel__body">
                        @include('seller.partials.product-sale-form')
                    </div>
                </section>

                <section class="seller-panel product-form__danger" aria-labelledby="delete-heading">
                    <header class="seller-panel__header"><div><h2 id="delete-heading">Delete product</h2><p>Removes it from your shop. Past orders keep their records.</p></div></header>
                    <form method="POST" action="{{ route('seller.products.destroy', $product) }}" class="seller-panel__body" data-confirm="Delete {{ $product->name }} from your shop? This cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="seller-button seller-button--small product-form__delete"><i class="fas fa-trash" aria-hidden="true"></i> Delete product</button>
                    </form>
                </section>
            @else
                <section class="seller-panel">
                    <header class="seller-panel__header"><h2>After you create it</h2></header>
                    <ul class="seller-panel__body product-form__tips">
                        <li>Add sizes, like shoe or shirt sizes, each with its own stock.</li>
                        <li>Schedule sales with a start and end time.</li>
                        <li>Restock anytime from Inventory.</li>
                        <li>Hide it from buyers without deleting it.</li>
                    </ul>
                </section>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/product-sales.js') }}?v={{ filemtime(public_path('js/product-sales.js')) }}" defer></script>
    <script src="{{ asset('js/seller-products.js') }}?v={{ filemtime(public_path('js/seller-products.js')) }}" defer></script>
@endpush
