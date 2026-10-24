@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Compliance')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/product-review.css') }}?v={{ filemtime(public_path('css/product-review.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Marketplace</p>
            <h1>Seller compliance</h1>
            <p>New and edited listings wait under Pending review until you approve them; buyers cannot see them before. Products are also flagged when they are outside the seller's registered category or mention prohibited items. Warn the seller, hide the product, or suspend the seller. Every decision is emailed to the seller.</p>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Compliance views">
            @foreach(['pending' => 'Pending review', 'flagged' => 'Flagged', 'all' => 'All products', 'hidden' => 'Hidden', 'rejected' => 'Rejected', 'warnings' => 'Warnings issued'] as $key => $label)
                <a href="{{ route('admin.compliance', array_filter(['view' => $key === $defaultView ? null : $key, 'search' => $search])) }}" class="workspace-tab{{ $view === $key ? ' is-active' : '' }}" @if($view === $key) aria-current="true" @endif>{{ $label }} <span>{{ $counts[$key] }}</span></a>
            @endforeach
        </nav>

        @if($view !== 'warnings')
            <form method="GET" action="{{ route('admin.compliance') }}" class="seller-filter-bar" role="search">
                @if($view !== $defaultView)<input type="hidden" name="view" value="{{ $view }}">@endif
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
                                            @if($product->is_pending_review)
                                                <span class="review-pill review-pill--pending"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Pending review</span>
                                            @elseif($product->is_rejected)
                                                <span class="review-pill review-pill--rejected"><i class="fas fa-circle-xmark" aria-hidden="true"></i> Rejected</span>
                                                @if($product->review_note)<span class="seller-cell-note">{{ Str::limit($product->review_note, 120) }}</span>@endif
                                            @endif
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
                                        @if(! $product->is_archived && ($product->is_pending_review || $product->is_rejected))
                                            <form method="POST" action="{{ route('admin.compliance.review', $product) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="decision" value="approve">
                                                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Approve</button>
                                            </form>
                                        @endif
                                        @if(! $product->is_archived && $product->is_pending_review)
                                            @php $rejectError = $errors->has('reason') && old('review_product') == $product->id; @endphp
                                            <details class="review-reason" data-smooth-disclosure @if($rejectError) open @endif>
                                                <summary class="seller-button seller-button--small seller-button--danger"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</summary>
                                                <div data-disclosure-content>
                                                    <form method="POST" action="{{ route('admin.compliance.review', $product) }}" class="review-reason__form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="decision" value="reject">
                                                        <input type="hidden" name="review_product" value="{{ $product->id }}">
                                                        <label for="review-reason-{{ $product->id }}">Reason for the seller</label>
                                                        <textarea id="review-reason-{{ $product->id }}" name="reason" minlength="10" maxlength="1000" required placeholder="What is not allowed and what to change.">{{ $rejectError ? old('reason') : ($row['flags'][0][1] ?? '') }}</textarea>
                                                        @if($rejectError)<p class="seller-field__error" role="alert">{{ $errors->first('reason') }}</p>@endif
                                                        <button type="submit" class="seller-button seller-button--small seller-button--danger">Reject listing</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                        @if($row['owner'] && ! $product->is_pending_review)
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
                                        @if($product->is_archived)
                                            <span class="seller-cell-note"><i class="fas fa-box-archive" aria-hidden="true"></i> Archived by the seller</span>
                                        @else
                                        <form method="POST" action="{{ route('admin.compliance.toggle', $product) }}"
                                              data-confirm="{{ $product->is_active ? 'Hide '.$product->name.' from buyers? The seller can see it but buyers cannot order it.' : 'Show '.$product->name.' to buyers again?' }}"
                                              data-confirm-title="{{ $product->is_active ? 'Hide product?' : 'Restore product?' }}" data-confirm-button="{{ $product->is_active ? 'Hide' : 'Restore' }}" @if($product->is_active) data-confirm-tone="danger" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="seller-button seller-button--small seller-button--secondary">
                                                <i class="fas {{ $product->is_active ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i> {{ $product->is_active ? 'Hide' : 'Restore' }}
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">
                                <div class="seller-empty">
                                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                                    <strong>{{ match ($view) { 'flagged' => 'No products flagged', 'pending' => 'No listings waiting', default => 'No products here' } }}</strong>
                                    <span>{{ match ($view) {
                                        'flagged' => 'Every listed product matches its seller\'s category and mentions no prohibited items.',
                                        'pending' => 'New and edited seller listings appear here for approval.',
                                        default => 'Try another search.',
                                    } }}</span>
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
