@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Compliance')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Marketplace</p>
            <h1>Seller compliance</h1>
            <p>Products are flagged when they are outside the seller's registered category or mention prohibited items. Warn the seller, hide the product, or suspend the seller. Every action is emailed to the seller.</p>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Compliance views">
            @foreach(['flagged' => 'Flagged', 'all' => 'All products', 'hidden' => 'Hidden', 'warnings' => 'Warnings issued'] as $key => $label)
                <a href="{{ route('admin.compliance', array_filter(['view' => $key === 'flagged' ? null : $key, 'search' => $search])) }}" class="workspace-tab{{ $view === $key ? ' is-active' : '' }}" @if($view === $key) aria-current="true" @endif>{{ $label }} <span>{{ $counts[$key] }}</span></a>
            @endforeach
        </nav>

        @if($view !== 'warnings')
            <form method="GET" action="{{ route('admin.compliance') }}" class="seller-filter-bar" role="search">
                @if($view !== 'flagged')<input type="hidden" name="view" value="{{ $view }}">@endif
                <div class="seller-field seller-field--search">
                    <label class="seller-field__label" for="product-search">Product name</label>
                    <input type="search" id="product-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Search products">
                </div>
                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Search</button>
            </form>

            <div class="seller-table-wrap">
                <table class="seller-table workspace-table compliance-table">
                    <thead>
                        <tr><th>Product</th><th>Seller</th><th>Review notes</th><th><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse($products as $row)
                            @php $product = $row['product']; @endphp
                            <tr>
                                <td>
                                    <div class="compliance-product">
                                        @if($product->image_url)
                                            <img src="{{ Str::startsWith($product->image_url, ['http://', 'https://']) ? $product->image_url : asset($product->image_url) }}" alt="" loading="lazy">
                                        @endif
                                        <div>
                                            <a href="{{ route('products.show', $product) }}" target="_blank" rel="noopener" class="workspace-link"><strong>{{ $product->name }}</strong></a>
                                            <span class="seller-cell-note">{{ $product->category_label }} · ₱{{ number_format((float) $product->price, 2) }}</span>
                                            @unless($product->is_active)<span class="case-pill case-pill--rejected">Hidden</span>@endunless
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($row['owner'])
                                        <a href="{{ route('admin.users.show', $row['owner']) }}" class="workspace-link">{{ $row['shop'] }}</a>
                                        <span class="seller-cell-note">Registered: {{ $row['registered'] ?? 'no category on file' }}</span>
                                    @else
                                        <span class="seller-cell-note">No seller account</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($row['flags'] as [$type, $text])
                                        <span class="flag flag--{{ $type }}"><i class="fas {{ $type === 'prohibited' ? 'fa-ban' : 'fa-tags' }}" aria-hidden="true"></i> {{ $text }}</span>
                                    @empty
                                        <span class="seller-cell-note">Nothing flagged</span>
                                    @endforelse
                                    @if($row['warnings'])
                                        <span class="seller-cell-note">{{ $row['warnings'] }} {{ Str::plural('warning', $row['warnings']) }} already issued</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="review-actions">
                                        @if($row['owner'])
                                            @php $errorHere = $errors->any() && old('product') == $product->id; @endphp
                                            <details class="review-reason" data-smooth-disclosure @if($errorHere) open @endif>
                                                <summary class="seller-button seller-button--small seller-button--danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Warn</summary>
                                                <div data-disclosure-content>
                                                    <form method="POST" action="{{ route('admin.compliance.warn', $product) }}" class="review-reason__form">
                                                        @csrf
                                                        <input type="hidden" name="product" value="{{ $product->id }}">
                                                        <label for="reason-{{ $product->id }}">Reason</label>
                                                        <select id="reason-{{ $product->id }}" name="reason" required>
                                                            @foreach($reasons as $key => $label)
                                                                <option value="{{ $key }}" @selected(($errorHere ? old('reason') : ($row['flags'][0][0] ?? null)) === $key)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <label for="details-{{ $product->id }}">Message to the seller</label>
                                                        <textarea id="details-{{ $product->id }}" name="details" minlength="10" maxlength="1000" required placeholder="What is wrong and how to fix it.">{{ $errorHere ? old('details') : ($row['flags'][0][1] ?? '') }}</textarea>
                                                        <label for="action-{{ $product->id }}">Action</label>
                                                        <select id="action-{{ $product->id }}" name="action">
                                                            @foreach($actions as $key => $label)
                                                                <option value="{{ $key }}" @selected($errorHere && old('action') === $key)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @if($errorHere)<p class="seller-field__error" role="alert">{{ $errors->first() }}</p>@endif
                                                        <button type="submit" class="seller-button seller-button--small seller-button--danger">Send warning</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                        <form method="POST" action="{{ route('admin.compliance.toggle', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="seller-button seller-button--small seller-button--secondary">
                                                <i class="fas {{ $product->is_active ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i> {{ $product->is_active ? 'Hide' : 'Restore' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">
                                <div class="seller-empty">
                                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                                    <strong>{{ $view === 'flagged' ? 'No products flagged' : 'No products here' }}</strong>
                                    <span>{{ $view === 'flagged' ? 'Every listed product matches its seller\'s category and mentions no prohibited items.' : 'Try another search.' }}</span>
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
                <div class="seller-pagination">{{ $products->links() }}</div>
            @endif
        @else
            <div class="seller-table-wrap">
                <table class="seller-table workspace-table">
                    <thead>
                        <tr><th>Seller</th><th>Reason</th><th>Action</th><th>Issued</th><th>Seller read it</th></tr>
                    </thead>
                    <tbody>
                        @forelse($warnings as $warning)
                            <tr>
                                <td>
                                    @if($warning->seller)
                                        <a href="{{ route('admin.users.show', $warning->seller) }}" class="workspace-link">{{ $warning->seller->name }}</a>
                                    @endif
                                    <span class="seller-cell-note">{{ $warning->product?->name ?? 'Whole shop' }}</span>
                                </td>
                                <td><strong>{{ $warning->reason_label }}</strong><span class="seller-cell-note">{{ Str::limit($warning->details, 120) }}</span></td>
                                <td>{{ $warning->action_label }}</td>
                                <td>{{ $warning->created_at?->timezone('Asia/Manila')->format('M j, Y') }}<span class="seller-cell-note">by {{ $warning->issuer?->name }}</span></td>
                                <td>{{ $warning->acknowledged_at ? $warning->acknowledged_at->timezone('Asia/Manila')->format('M j, Y') : 'Not yet' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="seller-empty"><i class="fas fa-circle-check" aria-hidden="true"></i><strong>No warnings issued</strong><span>Warnings you send from the product list are kept here.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($warnings->hasPages())
                <div class="seller-pagination">{{ $warnings->links() }}</div>
            @endif
        @endif
    </section>
@endsection
