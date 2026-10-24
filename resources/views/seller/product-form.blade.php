@extends('layouts.seller')

@section('title', $product ? 'Edit '.$product->name : 'Add product')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-products.css') }}?v={{ filemtime(public_path('css/seller-products.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/product-sales.css') }}?v={{ filemtime(public_path('css/product-sales.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/product-review.css') }}?v={{ filemtime(public_path('css/product-review.css')) }}">
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
        @if($product?->is_archived)
            <div class="seller-page-heading__actions">
                <form method="POST" action="{{ route('seller.products.restore', $product) }}"
                      data-confirm="Put {{ $product->name }} back in your shop? Buyers will see it again." data-confirm-title="Restore product?" data-confirm-button="Restore">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="seller-button"><i class="fas fa-rotate-left" aria-hidden="true"></i> Restore to shop</button>
                </form>
            </div>
        @endif
        @if($product && ! $product->is_archived)
            <div class="seller-page-heading__actions">
                <a href="{{ route('products.show', $product) }}" class="seller-button seller-button--secondary" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> {{ $product->is_listed ? 'View in shop' : 'Preview' }}</a>
            </div>
        @endif
    </section>

    @if($product && ! $product->is_archived && ($product->is_pending_review || $product->is_rejected))
        <aside class="product-review-notice product-review-notice--{{ $product->review_status }}" role="status">
            <i class="fas {{ $product->is_rejected ? 'fa-circle-xmark' : 'fa-hourglass-half' }}" aria-hidden="true"></i>
            <div>
                @if($product->is_rejected)
                    <strong>An admin did not approve this product, so buyers can't see it.</strong>
                    @if($product->review_note)<span>Reason: {{ $product->review_note }}</span>@endif
                    <span>Fix it below and save; it goes back for review.</span>
                @else
                    <strong>Waiting for admin approval.</strong>
                    <span>Buyers can't see this product yet. You'll get an email when it's reviewed.</span>
                @endif
            </div>
        </aside>
    @endif

    @if($product?->is_archived)
        <p class="product-form__archived" role="status">
            <i class="fas fa-box-archive" aria-hidden="true"></i>
            Archived {{ $product->archived_at->timezone('Asia/Manila')->format('M j, Y') }}. Buyers can't see or order it; its reviews, photos and order history are kept. You can still edit it before restoring.
        </p>
    @endif
    @error('product')<p class="seller-field__error product-form__archived-error" role="alert">{{ $message }}</p>@enderror

    <div class="seller-panel-grid product-form-grid">
        <form method="POST" action="{{ $product ? route('seller.products.update', $product) : route('seller.products.store') }}" enctype="multipart/form-data" class="product-form" id="productForm"
              data-confirm="The photos you ticked will be deleted when you save." data-confirm-if="input[name='remove_photos[]']:checked"
              data-confirm-title="Remove photos?" data-confirm-button="Save and remove" data-confirm-tone="danger">
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
                    @php
                        $chosenCategories = (array) old('categories', $product?->category_keys ?? []);
                        $categoryError = $fieldError('categories') ? $errors->first('categories') : ($errors->has('categories.*') ? $errors->first('categories.*') : null);
                    @endphp
                    <fieldset class="seller-field seller-field--full product-form__categories" data-categories aria-describedby="productCategoriesHelp"
                              @if($categoryError) aria-invalid="true" @endif>
                        <legend>Categories</legend>
                        <p class="seller-field__help" id="productCategoriesHelp">Tick every category buyers should find this in, e.g. a rice cooker under Electronics and Kitchen &amp; Dining. Choose at least one.</p>
                        <div class="product-form__category-options">
                            @foreach($categories as $key => $label)
                                <label class="product-form__category-option">
                                    <input type="checkbox" name="categories[]" value="{{ $key }}" @checked(in_array($key, $chosenCategories, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="seller-field__error" data-categories-error @if(! $categoryError) hidden @endif>{{ $categoryError }}</p>
                    </fieldset>
                    <div class="seller-field">
                        <label for="productGender">For</label>
                        <select id="productGender" name="gender" data-product-gender aria-describedby="productGenderHelp">
                            <option value="">Not specified</option>
                            <optgroup label="Adults">
                                @foreach(['women', 'men', 'unisex'] as $key)
                                    <option value="{{ $key }}" @selected($value('gender', $product?->gender) === $key)>{{ config('marketplace.genders')[$key] }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Kids">
                                @foreach(['girls', 'boys', 'kids'] as $key)
                                    <option value="{{ $key }}" @selected($value('gender', $product?->gender) === $key)>{{ config('marketplace.genders')[$key] }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <p class="seller-field__help" id="productGenderHelp">Kids items use kids' sizes for Standard sizes. "Kids (boys or girls)" shows under both Girls and Boys.</p>
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
                            <input type="number" id="productPrice" name="price" value="{{ $value('price', $product?->regular_price) }}" min="0" max="99999999.99" step="0.01" required data-main-price @if($fieldError('price')) aria-invalid="true" @endif>
                            @unless($product)<p class="seller-field__help" data-main-price-note hidden>Used for any color or size below that has no price of its own.</p>@endunless
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
                            <input type="number" id="productStock" name="stock" value="{{ $value('stock', $product?->stock ?? 0) }}" min="0" max="1000000" required aria-describedby="productStockHelp" data-main-stock @if($fieldError('stock')) aria-invalid="true" @endif>
                            @unless($product)<p class="seller-field__help" data-main-stock-note hidden>Counted from your colors and sizes below.</p>@endunless
                            <p class="seller-field__help" id="productStockHelp">Shown as "Low stock" at {{ \App\Models\Product::lowStockThreshold() }} or fewer.</p>
                            @if($fieldError('stock'))<p class="seller-field__error">{{ $errors->first('stock') }}</p>@endif
                        </div>
                    @endunless
                    <div class="seller-field">
                        <label for="productWeight">Weight (grams)</label>
                        <input type="number" id="productWeight" name="weight_grams" value="{{ $value('weight_grams', $product?->weight_grams) }}" min="0" max="1000000">
                        <p class="seller-field__help">Optional. Used for shipping.</p>
                    </div>
                </div>
            </section>

            @unless($product)
                @include('seller.partials.new-product-options')
            @endunless

            <section class="seller-panel" aria-labelledby="photo-heading">
                <header class="seller-panel__header"><div><h2 id="photo-heading">Photos</h2><p>The main photo shows first in search and on the product page; buyers swipe through the rest.</p></div></header>
                <div class="seller-panel__body product-form__photo">
                    <img src="{{ $product?->image_url ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="" class="product-form__preview{{ $product?->image_url ? '' : ' is-placeholder' }}" data-image-preview data-fallback-src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}">
                    <div class="seller-field">
                        <label for="productImage">{{ $product?->image_url ? 'Replace main photo' : 'Upload main photo' }}</label>
                        <input type="file" id="productImage" name="image" accept="image/jpeg,image/png,image/webp" data-image-input @if($fieldError('image')) aria-invalid="true" @endif>
                        <p class="seller-field__help">JPG, PNG, or WebP up to 5 MB.{{ $product ? ' Leave empty to keep the current photo.' : '' }}</p>
                        @if($fieldError('image'))<p class="seller-field__error">{{ $errors->first('image') }}</p>@endif
                    </div>
                </div>
                @php
                    $extraPhotos = $product ? $product->extra_photos : collect();
                    $removing = array_map('intval', (array) old('remove_photos', []));
                    $photoError = $fieldError('photos') ? $errors->first('photos') : ($errors->has('photos.*') ? $errors->first('photos.*') : null);
                @endphp
                <div class="seller-panel__body product-form__gallery" data-photo-gallery data-max="9" data-per-upload="6" data-existing="{{ $extraPhotos->count() }}">
                    <div class="seller-field">
                        <label for="productPhotos">More photos <span class="product-form__optional">(optional)</span></label>
                        <p class="seller-field__help" id="productPhotosHelp">Add up to 9 more photos, 6 at a time. JPG, PNG, or WebP up to 5 MB each.</p>
                        @if($extraPhotos->isNotEmpty())
                            <ul class="product-form__photo-grid" aria-label="Current photos">
                                @foreach($extraPhotos as $photo)
                                    <li class="product-form__photo-item">
                                        <img src="{{ $photo->path }}" alt="Photo {{ $loop->iteration + 1 }} of {{ $product->name }}" loading="lazy">
                                        <label class="product-form__photo-remove">
                                            <input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}" data-photo-remove @checked(in_array($photo->id, $removing, true))>
                                            <span>Remove</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <ul class="product-form__photo-grid" data-photo-previews aria-label="Photos to upload" hidden></ul>
                        <input type="file" id="productPhotos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-input aria-describedby="productPhotosHelp" @if($photoError) aria-invalid="true" @endif>
                        <p class="seller-field__error" data-photo-message role="status" @if(! $photoError) hidden @endif>{{ $photoError }}</p>
                    </div>
                </div>
            </section>

            <section class="seller-panel" aria-labelledby="visibility-heading">
                <header class="seller-panel__header"><div><h2 id="visibility-heading">Visibility</h2></div></header>
                <div class="seller-panel__body">
                    <div class="seller-field">
                        <label for="productStatus">Shown in your shop</label>
                        @if($product?->is_archived)
                            <p class="seller-field__help" id="productStatusHelp">Archived products stay hidden. Restore it to show it again.</p>
                        @endif
                        <select id="productStatus" name="is_active" @if($product?->is_archived) disabled aria-describedby="productStatusHelp" @endif>
                            <option value="1" @selected((string) $value('is_active', $product ? (int) $product->is_active : 1) === '1')>{{ $product && $product->review_status === \App\Models\Product::REVIEW_APPROVED ? 'Active — visible to buyers' : 'Active — visible once an admin approves it' }}</option>
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
                        <div><dt>Visibility</dt><dd>{{ match (true) { $product->is_archived => 'Archived', $product->is_pending_review => 'Pending admin review', $product->is_rejected => 'Rejected by admin', $product->is_active => 'Visible to buyers', default => 'Hidden' } }}</dd></div>
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

                @if(! $product->is_archived)
                    <section class="seller-panel" aria-labelledby="archive-heading">
                        <header class="seller-panel__header"><div><h2 id="archive-heading">Archive product</h2><p>Stop selling it without losing anything. Its reviews, photos and order history are kept, and you can restore it anytime.</p></div></header>
                        <form method="POST" action="{{ route('seller.products.archive', $product) }}" class="seller-panel__body"
                              data-confirm="Archive {{ $product->name }}? Buyers won't see it and it is removed from their carts. You can restore it from Archived anytime."
                              data-confirm-title="Archive product?" data-confirm-button="Archive">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="seller-button seller-button--small seller-button--secondary"><i class="fas fa-box-archive" aria-hidden="true"></i> Archive product</button>
                        </form>
                    </section>
                @else
                    <section class="seller-panel product-form__danger" aria-labelledby="delete-heading">
                        @if($product->hasHistory())
                            <header class="seller-panel__header"><div><h2 id="delete-heading">Delete permanently</h2><p>Buyers have ordered or reviewed this product, so it stays archived to keep their order history and reviews.</p></div></header>
                        @else
                            <header class="seller-panel__header"><div><h2 id="delete-heading">Delete permanently</h2><p>No one has ordered or reviewed it, so it can be removed for good, with its photos.</p></div></header>
                            <form method="POST" action="{{ route('seller.products.destroy', $product) }}" class="seller-panel__body"
                                  data-confirm="Delete {{ $product->name }} permanently? Its photos, colors and sizes are removed too. This cannot be undone."
                                  data-confirm-title="Delete permanently?" data-confirm-button="Delete" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="seller-button seller-button--small product-form__delete"><i class="fas fa-trash" aria-hidden="true"></i> Delete permanently</button>
                            </form>
                        @endif
                    </section>
                @endif
            @else
                <section class="seller-panel">
                    <header class="seller-panel__header"><h2>After you create it</h2></header>
                    <ul class="seller-panel__body product-form__tips">
                        <li>Change or add colors and sizes anytime, each with its own price and stock.</li>
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
