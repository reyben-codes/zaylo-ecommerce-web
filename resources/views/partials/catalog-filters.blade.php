<form method="GET" action="{{ route('products.index') }}" class="catalog-filters" role="search" aria-label="Find products">
    <div class="catalog-search-row">
        <label for="catalog-search">Find your next favorite
            <input id="catalog-search" type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="What are you looking for?">
        </label>
    </div>
    <div class="catalog-filter-fields catalog-primary-fields">
        <label>Category<select name="category"><option value="">All categories</option>
            @foreach(config('marketplace.categories') as $key => $label)<option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>@endforeach
        </select></label>
        <label>Sort by<select name="sort">
            @foreach(['relevance' => 'Best match', 'newest' => 'Newest arrivals', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low'] as $key => $label)<option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>@endforeach
        </select></label>
    </div>
    @php
        $extraFilterCount = collect(['gender', 'min_price', 'max_price'])->filter(fn ($key) => isset($filters[$key]) && $filters[$key] !== '')->count()
            + ($filters['availability'] !== 'in_stock' ? 1 : 0) + (!empty($filters['on_sale']) ? 1 : 0);
    @endphp
    <details class="catalog-more-filters" data-smooth-disclosure>
        <summary>More filters @if($extraFilterCount)<span class="catalog-filter-count">{{ $extraFilterCount }} active</span>@endif<span class="catalog-disclosure-icon" aria-hidden="true">+</span></summary>
        <div class="catalog-extra-fields" data-disclosure-content>
        <div class="catalog-filter-fields">
        <label>Gender<select name="gender"><option value="">All genders</option>
            @foreach(['men', 'women', 'unisex'] as $gender)<option value="{{ $gender }}" @selected(($filters['gender'] ?? '') === $gender)>{{ ucfirst($gender) }}</option>@endforeach
        </select></label>
        <label>Minimum price (₱)<input type="number" name="min_price" min="0" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0" value="{{ $filters['min_price'] ?? '' }}"></label>
        <label>Maximum price (₱)<input type="number" name="max_price" min="0" max="99999999.99" step="0.01" inputmode="decimal" placeholder="No limit" value="{{ $filters['max_price'] ?? '' }}"></label>
        <label>Availability<select name="availability">
            @foreach(['in_stock' => 'In stock', 'all' => 'All products', 'out_of_stock' => 'Sold out'] as $key => $label)<option value="{{ $key }}" @selected($filters['availability'] === $key)>{{ $label }}</option>@endforeach
        </select></label>
    </div>
    <div class="catalog-filter-footer">
        <label class="catalog-sale-toggle"><input type="checkbox" name="on_sale" value="1" @checked(!empty($filters['on_sale']))> On sale only</label>
        <span>Prices include active discounts. Variation prices may differ.</span>
    </div>
        </div>
    </details>
    <div class="catalog-submit-row"><button type="submit" class="market-button">Show products</button></div>
</form>
<div class="catalog-results-heading" id="catalog-results">
    <p role="status"><strong>{{ number_format($products->total()) }}</strong> {{ Str::plural('product', $products->total()) }} found @if($products->count()) <span>· Showing {{ $products->firstItem() }}–{{ $products->lastItem() }}</span>@endif</p>
    <a class="catalog-reset-button" href="{{ route('products.index') }}">Reset search & filters</a>
</div>
@if($filterChips)
    <nav class="catalog-filter-chips" aria-label="Applied filters">
        @foreach($filterChips as $chip)<a href="{{ $chip['url'] }}" aria-label="Remove {{ $chip['label'] }}">{{ $chip['label'] }} <span aria-hidden="true">×</span></a>@endforeach
    </nav>
@endif
