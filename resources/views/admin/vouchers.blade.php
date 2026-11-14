@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Vouchers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Marketplace</p>
            <h1>Vouchers</h1>
            <p>Free shipping applies to every shop and ZAYLO pays it. A % or &#8369; off voucher is offered to every shop by notification and email: shops that join have it on their products and pay the discount; shops that decline are left out.</p>
        </div>
    </section>

    <section class="seller-panel" aria-labelledby="new-voucher">
        <header class="seller-panel__header"><div><h2 id="new-voucher">New voucher</h2></div></header>
        <form method="POST" action="{{ route('admin.vouchers.store') }}" class="seller-panel__body seller-form voucher-form">
            @csrf
            <div class="seller-field">
                <label for="voucher-code">Code</label>
                <input id="voucher-code" name="code" value="{{ old('code') }}" maxlength="30" placeholder="e.g. PAYDAY10" required @error('code') aria-invalid="true" @enderror>
                @error('code')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="voucher-name">Name <span class="seller-field__help">(optional)</span></label>
                <input id="voucher-name" name="name" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Payday Sale">
            </div>
            <div class="seller-field">
                <label id="target-type-label">Target shops</label>
                <div class="target-type-grid" role="radiogroup" aria-labelledby="target-type-label">
                    <label class="target-type-card{{ old('target_type', 'all') === 'all' ? ' is-selected' : '' }}" for="target-type-all">
                        <input type="radio" id="target-type-all" name="target_type" value="all" @checked(old('target_type', 'all') === 'all')>
                        <div class="target-type-card__body">
                            <div class="target-type-card__icon"><i class="fa-solid fa-earth-asia" aria-hidden="true"></i></div>
                            <strong class="target-type-card__title">All shops</strong>
                            <span class="target-type-card__desc">Platform-wide invitation to all approved shops</span>
                        </div>
                    </label>
                    <label class="target-type-card{{ old('target_type') === 'specific' ? ' is-selected' : '' }}" for="target-type-specific">
                        <input type="radio" id="target-type-specific" name="target_type" value="specific" @checked(old('target_type') === 'specific')>
                        <div class="target-type-card__body">
                            <div class="target-type-card__icon"><i class="fa-solid fa-shop" aria-hidden="true"></i></div>
                            <strong class="target-type-card__title">Specific shop</strong>
                            <span class="target-type-card__desc">Target a single chosen seller shop</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="seller-field" id="target-seller-wrap" @if(old('target_type', 'all') !== 'specific') style="display: none;" @endif>
                <label id="target-shops-label">Select target shop <span class="seller-field__help">(choose one)</span></label>
                
                <div class="target-shops-wrap">
                    @if(count($shops) > 4)
                        <div class="target-shops-search">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="text" id="target-shops-search-input" placeholder="Search shops by name or email…" aria-label="Search shops">
                        </div>
                    @endif

                    <div class="target-shops-grid" role="radiogroup" aria-labelledby="target-shops-label" id="target-shops-list">
                        @forelse($shops as $shop)
                            @php $isSelected = (string) old('target_seller_id') === (string) $shop['id']; @endphp
                            <label class="target-shop-tile{{ $isSelected ? ' is-selected' : '' }}" for="target-shop-{{ $shop['id'] }}"
                                   data-shop-name="{{ Str::lower($shop['name']) }}" data-owner="{{ Str::lower($shop['owner']->email ?? $shop['owner']->name ?? '') }}">
                                <input type="radio" id="target-shop-{{ $shop['id'] }}" name="target_seller_id" value="{{ $shop['id'] }}" @checked($isSelected) class="sr-only">
                                <span class="target-shop-tile__indicator"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                <div class="target-shop-tile__avatar">
                                    <i class="fa-solid fa-store" aria-hidden="true"></i>
                                </div>
                                <div class="target-shop-tile__meta">
                                    <strong class="target-shop-tile__name" title="{{ $shop['name'] }}">{{ $shop['name'] }}</strong>
                                    <span class="target-shop-tile__owner" title="{{ $shop['owner']->email ?? $shop['owner']->name }}">{{ $shop['owner']->email ?? $shop['owner']->name }}</span>
                                </div>
                            </label>
                        @empty
                            <p class="admin-empty-inline">No approved shops available yet.</p>
                        @endforelse
                    </div>
                    <p class="target-shops-none" id="target-shops-none" style="display: none;">No matching shops found.</p>
                </div>
                @error('target_seller_id')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="voucher-type">Type</label>
                <select id="voucher-type" name="type" required>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', 'percent') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="seller-field">
                <label for="voucher-value">Discount (% or &#8369;)</label>
                <input id="voucher-value" name="value" type="number" step="0.01" min="1" value="{{ old('value') }}" placeholder="Not needed for free shipping" @error('value') aria-invalid="true" @enderror>
                @error('value')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="voucher-min">Minimum spend per shop (&#8369;)</label>
                <input id="voucher-min" name="min_spend" type="number" step="0.01" min="0" value="{{ old('min_spend') }}">
            </div>
            <div class="seller-field">
                <label for="voucher-max">Most it can take off (&#8369;, for %)</label>
                <input id="voucher-max" name="max_discount" type="number" step="0.01" min="1" value="{{ old('max_discount') }}">
            </div>
            <div class="seller-field">
                <label for="voucher-limit">Uses allowed</label>
                <input id="voucher-limit" name="usage_limit" type="number" min="1" value="{{ old('usage_limit') }}" placeholder="No limit">
            </div>
            <div class="seller-field">
                <label for="voucher-starts">Starts</label>
                <input id="voucher-starts" name="starts_at" type="datetime-local" value="{{ old('starts_at') }}">
            </div>
            <div class="seller-field">
                <label for="voucher-ends">Ends</label>
                <input id="voucher-ends" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" @error('expires_at') aria-invalid="true" @enderror>
                @error('expires_at')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-form__actions">
                <button type="submit" class="seller-button"><i class="fas fa-ticket" aria-hidden="true"></i> Create and invite shops</button>
            </div>
        </form>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header"><div><h2>All vouchers</h2></div></header>
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead><tr><th>Voucher</th><th>Offer</th><th>Paid by</th><th>Target</th><th>Shops</th><th>Used</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse($vouchers as $voucher)
                        <tr>
                            <td><strong>{{ $voucher->code }}</strong>@if($voucher->name)<span class="seller-cell-note">{{ $voucher->name }}</span>@endif
                                <span class="seller-cell-note">{{ $voucher->is_active ? ($voucher->isAvailable() ? 'Running' : 'Scheduled or ended') : 'Off' }}@if($voucher->expires_at) · ends {{ $voucher->expires_at->timezone('Asia/Manila')->format('M j, Y') }}@endif</span></td>
                            <td>{{ $voucher->describe() }}</td>
                            <td>{{ $voucher->funded_by === 'seller' ? 'Seller' : ($voucher->isFreeShipping() ? 'ZAYLO' : 'Shops that join') }}</td>
                            <td>
                                @if($voucher->seller_id)
                                    <span class="seller-cell-note"><strong>Specific:</strong> {{ app(\App\Services\Vouchers::class)->shopName($voucher->seller_id) }}</span>
                                @else
                                    <span>All shops</span>
                                @endif
                            </td>
                            <td>
                                @if($voucher->isFreeShipping())
                                    All shops
                                @else
                                    {{ $voucher->joined_count }} joined<span class="seller-cell-note">{{ $voucher->pending_count }} not answered · {{ $voucher->declined_count }} declined</span>
                                @endif
                            </td>
                            <td>{{ number_format($voucher->used_count) }}{{ $voucher->usage_limit ? ' / '.number_format($voucher->usage_limit) : '' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.vouchers.toggle', $voucher) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="seller-button seller-button--small seller-button--secondary">{{ $voucher->is_active ? 'Turn off' : 'Turn on' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="admin-empty-inline">No vouchers yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vouchers->hasPages())<div class="seller-pagination">{{ $vouchers->links() }}</div>@endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/voucher-admin.js') }}?v={{ filemtime(public_path('js/voucher-admin.js')) }}" defer></script>
@endpush
