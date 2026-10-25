@extends('layouts.seller')

@section('title', 'Inventory')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-inventory.css') }}?v={{ filemtime(public_path('css/seller-inventory.css')) }}">
@endpush

@section('content')
    @php
        $stockLabels = ['in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'];
        $summaryCards = [
            'all' => ['label' => 'All products', 'icon' => 'fa-boxes-stacked', 'value' => $summary['total']],
            'in' => ['label' => 'In stock', 'icon' => 'fa-circle-check', 'value' => $summary['in']],
            'low' => ['label' => 'Low stock', 'icon' => 'fa-triangle-exclamation', 'value' => $summary['low']],
            'out' => ['label' => 'Out of stock', 'icon' => 'fa-circle-xmark', 'value' => $summary['out']],
        ];
        $filterQuery = array_filter(['q' => $filters['q'], 'category' => $filters['category']]);
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Inventory</h1>
            <p>Monitor stock levels across your shop. To restock, open the product with Edit.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('seller.products.create') }}" class="seller-button"><i class="fas fa-plus" aria-hidden="true"></i> Add product</a>
        </div>
    </section>

    <nav class="seller-stat-grid" aria-label="Filter by stock level">
        @foreach($summaryCards as $key => $card)
            <a href="{{ route('seller.inventory', $filterQuery + ($key === 'all' ? [] : ['stock' => $key])) }}"
               class="seller-stat{{ $filters['stock'] === $key ? ' is-active' : '' }}"
               @if($filters['stock'] === $key) aria-current="true" @endif>
                <span class="seller-stat__label"><i class="fas {{ $card['icon'] }}" aria-hidden="true"></i> {{ $card['label'] }}</span>
                <strong class="seller-stat__value">{{ number_format($card['value']) }}</strong>
                @if($key === 'low' && $card['value'] > 0)
                    <span class="seller-stat__note seller-stat__note--warning">Restock soon</span>
                @elseif($key === 'out' && $card['value'] > 0)
                    <span class="seller-stat__note seller-stat__note--danger">Buyers can't order these</span>
                @else
                    <span class="seller-stat__note">{{ $key === 'all' ? 'Listed in your shop' : 'Products' }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <section class="seller-panel">
        <form method="GET" action="{{ route('seller.inventory') }}" class="seller-filter-bar" role="search">
            <div class="seller-field seller-field--search">
                <label for="inventory-search">Search</label>
                <input type="search" id="inventory-search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Product name or SKU">
            </div>
            <div class="seller-field">
                <label for="inventory-category">Category</label>
                <select id="inventory-category" name="category">
                    <option value="">All categories</option>
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="seller-field">
                <label for="inventory-stock">Stock level</label>
                <select id="inventory-stock" name="stock">
                    <option value="all">All stock levels</option>
                    @foreach($stockLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['stock'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="inventory-filter-actions">
                <button type="submit" class="seller-button"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                @if($filters['q'] || $filters['category'] || $filters['stock'] !== 'all')
                    <a href="{{ route('seller.inventory') }}" class="seller-button seller-button--secondary">Reset</a>
                @endif
            </div>
        </form>

        <div class="seller-table-wrap">
            <table class="seller-table inventory-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="is-numeric">Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $stock = $stockStates[$product->id];
                            $variants = $product->variants;
                        @endphp
                        <tr>
                            <td>
                                <div class="seller-product-cell">
                                    <img src="{{ $product->image_url ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="" loading="lazy" data-fallback-src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}">
                                    <div>
                                        <strong>{{ $product->name }}</strong>
                                        <span class="seller-cell-note">{{ $product->category_label }} · SKU {{ $product->sku ?: '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="is-numeric inventory-price">
                                <strong>&#8369;{{ number_format((float) $product->price, 2) }}</strong>
                                @if($product->saleIsActive())
                                    <span class="seller-cell-note">On sale</span>
                                @endif
                            </td>
                            <td>
                                <strong class="stock-count">{{ number_format($stock['total']) }}</strong>
                                <span class="seller-cell-note">Alert at {{ $product->low_stock_threshold }}</span>
                                @if($variants->count() > 1)
                                    @include('seller.partials.stock-breakdown', ['product' => $product, 'variants' => $variants])
                                @endif
                            </td>
                            <td>
                                <span class="stock-pill stock-pill--{{ $stock['state'] }}">{{ $stockLabels[$stock['state']] }}</span>
                                <span class="visibility{{ $product->is_listed ? ' is-visible' : '' }}">
                                    <i class="fas {{ $product->is_listed ? 'fa-eye' : ($product->is_pending_review ? 'fa-hourglass-half' : 'fa-eye-slash') }}" aria-hidden="true"></i>
                                    {{ match (true) { $product->is_pending_review => 'Pending review', $product->is_rejected => 'Rejected by admin', $product->is_active => 'Visible to buyers', default => 'Hidden' } }}
                                </span>
                            </td>
                            <td class="is-numeric">
                                <a href="{{ route('seller.products.edit', $product) }}" class="seller-button seller-button--secondary seller-button--small"><i class="fas fa-pen" aria-hidden="true"></i> Edit<span class="sr-only"> {{ $product->name }}</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seller-empty">
                                    <i class="fas fa-warehouse" aria-hidden="true"></i>
                                    @if($summary['total'] === 0)
                                        <strong>No products yet</strong>
                                        <span>Add your first product to start tracking stock.</span>
                                    @else
                                        <strong>No products match these filters</strong>
                                        <span>Try another search, category, or stock level.</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="seller-pagination">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
