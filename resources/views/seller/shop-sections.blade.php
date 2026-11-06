@extends('layouts.seller')

@section('title', 'Shop sections')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-products.css') }}?v={{ filemtime(public_path('css/seller-products.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/seller-shop-sections.css') }}?v={{ filemtime(public_path('css/seller-shop-sections.css')) }}">
@endpush

@section('content')
    @php
        // Which form a validation error belongs to: a section's editor, or the "Add section" form.
        $erroredSection = (int) old('section_id');
        $openSection = $erroredSection ?: (int) request('open');
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $storeName }}</p>
            <h1>Shop sections</h1>
            <p>Group your products into your own tabs, like New Arrivals or Bundle Deals. They only appear on your shop page.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ $shopUrl }}" class="seller-button seller-button--secondary" target="_blank" rel="noopener"><i class="fas fa-store" aria-hidden="true"></i> View your shop</a>
        </div>
    </section>

    <nav class="product-tabs" aria-label="Product lists">
        <a href="{{ route('seller.products') }}" class="product-tab">Products <span>{{ $counts['active'] }}</span></a>
        <a href="{{ route('seller.products', ['view' => 'archived']) }}" class="product-tab"><i class="fas fa-box-archive" aria-hidden="true"></i> Archived <span>{{ $counts['archived'] }}</span></a>
        <a href="{{ route('seller.products.sections') }}" class="product-tab is-active" aria-current="page"><i class="fas fa-layer-group" aria-hidden="true"></i> Shop sections <span>{{ $sections->count() }}</span></a>
    </nav>
    <p class="product-tabs__note">Buyers browsing the whole marketplace still use the main categories. Sections are extra tabs inside your shop: a product can be in several, and hidden or empty sections don't show.</p>

    <section class="seller-panel" aria-labelledby="add-section-heading">
        <header class="seller-panel__header">
            <div>
                <h2 id="add-section-heading">Add a section</h2>
                <p>{{ $sections->count() }} of {{ $max }} used.</p>
            </div>
        </header>
        <div class="seller-panel__body">
            @php $addError = ! $erroredSection && $errors->has('name') ? $errors->first('name') : null; @endphp
            <form method="POST" action="{{ route('seller.products.sections.store') }}" class="shop-sections__add">
                @csrf
                <div class="seller-field">
                    <label for="new-section-name">Section name</label>
                    <input id="new-section-name" name="name" value="{{ $erroredSection ? '' : old('name') }}" minlength="2" maxlength="{{ \App\Models\ShopSection::NAME_MAX }}"
                           placeholder="e.g. New Arrivals" required @if($addError) aria-invalid="true" aria-describedby="new-section-error" @endif
                           @if($sections->count() >= $max) disabled @endif>
                    @if($addError)<p class="seller-field__error" id="new-section-error">{{ $addError }}</p>@endif
                </div>
                <button type="submit" class="seller-button" @if($sections->count() >= $max) disabled @endif><i class="fas fa-plus" aria-hidden="true"></i> Add section</button>
            </form>
            @if($sections->count() >= $max)
                <p class="seller-field__help">You've reached {{ $max }} sections. Delete one to add another.</p>
            @elseif($sections->isEmpty())
                <p class="seller-field__help">Ideas: New Arrivals, Best Sellers, Bundle Deals, On Sale, Gifts Under ₱500.</p>
            @endif
        </div>
    </section>

    <section class="seller-panel" aria-labelledby="sections-heading">
        <header class="seller-panel__header">
            <div>
                <h2 id="sections-heading">Your sections</h2>
                <p>Shown on your shop in this order.</p>
            </div>
        </header>
        <div class="seller-panel__body">
            @if($sections->isEmpty())
                <div class="seller-empty">
                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                    <p>No sections yet. Your shop shows all products together until you add one.</p>
                </div>
            @else
                <ol class="shop-sections">
                    @foreach($sections as $section)
                        @php
                            $editError = $erroredSection === $section->id;
                            $chosen = $section->products->pluck('id')->all();
                        @endphp
                        <li class="shop-section{{ $section->is_visible ? '' : ' is-hidden' }}" id="section-{{ $section->id }}">
                            <div class="shop-section__row">
                                <span class="shop-section__number" aria-hidden="true">{{ $loop->iteration }}</span>
                                <div class="shop-section__title">
                                    <strong>{{ $section->name }}</strong>
                                    <span>
                                        {{ number_format($section->product_count) }} {{ Str::plural('product', $section->product_count) }}
                                        @unless($section->is_visible)<em class="shop-section__badge"><i class="fas fa-eye-slash" aria-hidden="true"></i> Hidden</em>@endunless
                                        @if($section->is_visible && ! $section->product_count)<em class="shop-section__badge">Not shown until it has products</em>@endif
                                    </span>
                                </div>
                                <div class="shop-section__actions">
                                    <form method="POST" action="{{ route('seller.products.sections.move', $section) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="shop-section__icon" aria-label="Move {{ $section->name }} up" title="Move up" @disabled($loop->first)><i class="fas fa-arrow-up" aria-hidden="true"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('seller.products.sections.move', $section) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="shop-section__icon" aria-label="Move {{ $section->name }} down" title="Move down" @disabled($loop->last)><i class="fas fa-arrow-down" aria-hidden="true"></i></button>
                                    </form>
                                    @if($section->is_visible && $section->product_count)
                                        <a href="{{ $shopUrl }}?section={{ $section->id }}#shop-content" class="shop-section__icon" target="_blank" rel="noopener" aria-label="See {{ $section->name }} in your shop" title="See in your shop"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                    @endif
                                    <form method="POST" action="{{ route('seller.products.sections.destroy', $section) }}"
                                          data-confirm="Delete the section &quot;{{ $section->name }}&quot;? Its products stay in your shop and in any other sections." data-confirm-title="Delete section?" data-confirm-button="Delete" data-confirm-tone="danger">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="shop-section__icon shop-section__icon--danger" aria-label="Delete {{ $section->name }}" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </div>

                            <details class="shop-section__editor" data-smooth-disclosure @if($openSection === $section->id) open @endif>
                                <summary><i class="fas fa-pen" aria-hidden="true"></i> Edit name and products <i class="fas fa-chevron-down shop-section__chevron" aria-hidden="true"></i></summary>
                                <div data-disclosure-content>
                                    <form method="POST" action="{{ route('seller.products.sections.update', $section) }}" class="shop-section__form">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="section_id" value="{{ $section->id }}">
                                        <div class="shop-section__fields">
                                            <div class="seller-field">
                                                <label for="section-name-{{ $section->id }}">Section name</label>
                                                <input id="section-name-{{ $section->id }}" name="name" value="{{ $editError ? old('name') : $section->name }}" minlength="2" maxlength="{{ \App\Models\ShopSection::NAME_MAX }}" required
                                                       @if($editError && $errors->has('name')) aria-invalid="true" aria-describedby="section-error-{{ $section->id }}" @endif>
                                                @if($editError && $errors->has('name'))<p class="seller-field__error" id="section-error-{{ $section->id }}">{{ $errors->first('name') }}</p>@endif
                                            </div>
                                            <label class="shop-section__toggle">
                                                <input type="checkbox" name="is_visible" value="1" @checked($editError ? old('is_visible') : $section->is_visible)>
                                                <span>Show this section in my shop</span>
                                            </label>
                                        </div>

                                        <fieldset class="section-picker" data-section-picker data-selected="{{ json_encode($editError ? array_map('intval', (array) old('product_ids', [])) : $chosen) }}">
                                            <legend>Products in this section</legend>
                                            @if($products->isEmpty())
                                                <p class="seller-field__help">Add products to your shop first, then choose them here.</p>
                                            @else
                                                <div class="section-picker__tools" hidden data-picker-tools>
                                                    <label class="sr-only" for="section-search-{{ $section->id }}">Search your products</label>
                                                    <input type="search" id="section-search-{{ $section->id }}" placeholder="Search your products" data-picker-search>
                                                    <span class="section-picker__count" data-picker-count aria-live="polite"></span>
                                                </div>
                                                <div class="section-picker__list" data-picker-list></div>
                                                <p class="section-picker__empty" data-picker-empty hidden>No products match.</p>
                                                @if($editError && $errors->has('product_ids.*'))<p class="seller-field__error">{{ $errors->first('product_ids.*') }}</p>@endif
                                                <noscript><p class="seller-field__help">Turn on JavaScript to choose products here, or pick sections on each product's edit page.</p></noscript>
                                            @endif
                                        </fieldset>

                                        <div class="shop-section__save">
                                            <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> Save section</button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ol>

                {{-- One copy of the product list; seller-shop-sections.js puts it into a section's editor when it opens. --}}
                <template id="section-product-template">
                    @foreach($products as $product)
                        <label class="section-picker__item" data-name="{{ mb_strtolower($product->name.' '.$product->sku) }}">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}">
                            <img src="{{ $product->image_url ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="" loading="lazy">
                            <span class="section-picker__name">{{ $product->name }}</span>
                            @unless($product->is_active)<small>Hidden</small>@endunless
                        </label>
                    @endforeach
                </template>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/smooth-disclosure.js') }}?v={{ filemtime(public_path('js/smooth-disclosure.js')) }}" defer></script>
    <script src="{{ asset('js/seller-shop-sections.js') }}?v={{ filemtime(public_path('js/seller-shop-sections.js')) }}" defer></script>
@endpush
