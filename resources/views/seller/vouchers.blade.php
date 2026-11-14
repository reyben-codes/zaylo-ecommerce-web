@extends('layouts.seller')

@section('title', 'Vouchers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-vouchers.css') }}?v={{ filemtime(public_path('css/seller-vouchers.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Vouchers</h1>
            <p>Create vouchers exclusively for your shop's products, or review and respond to marketplace voucher invitations from ZAYLO.</p>
        </div>
    </section>

    <details class="seller-panel" data-smooth-disclosure {{ $errors->any() ? 'open' : '' }}>
        <summary class="seller-panel__header">
            <div>
                <h2>Create shop voucher</h2>
                <p>Offer discounts on your products paid by your shop. Buyers can use them at checkout.</p>
            </div>
            <div class="order-actions">
                <span class="seller-button seller-button--secondary seller-button--small"><i class="fas fa-plus" aria-hidden="true"></i> New voucher</span>
                <i class="fas fa-chevron-down seller-panel__chevron" aria-hidden="true"></i>
            </div>
        </summary>
        <div data-disclosure-content>
            <div class="seller-voucher-creator-grid">
                <form id="seller-voucher-form" method="POST" action="{{ route('seller.vouchers.store') }}" class="seller-panel__body seller-form voucher-form">
                    @csrf
                    <div class="seller-field">
                        <label for="seller-voucher-code">Code</label>
                        <input id="seller-voucher-code" name="code" value="{{ old('code') }}" maxlength="30" placeholder="e.g. MYSHOP10" required @error('code') aria-invalid="true" @enderror>
                        @error('code')<p class="seller-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-name">Name <span class="seller-field__help">(optional)</span></label>
                        <input id="seller-voucher-name" name="name" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Shop Welcome Voucher">
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-type">Type</label>
                        <select id="seller-voucher-type" name="type" required>
                            <option value="percent" @selected(old('type', 'percent') === 'percent')>% off</option>
                            <option value="fixed" @selected(old('type') === 'fixed')>&#8369; off</option>
                        </select>
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-value">Discount (% or &#8369;)</label>
                        <input id="seller-voucher-value" name="value" type="number" step="0.01" min="1" value="{{ old('value') }}" placeholder="e.g. 10 for 10% or 50 for ₱50" required @error('value') aria-invalid="true" @enderror>
                        @error('value')<p class="seller-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-min">Minimum spend (&#8369;)</label>
                        <input id="seller-voucher-min" name="min_spend" type="number" step="0.01" min="0" value="{{ old('min_spend') }}" placeholder="0 or leave empty">
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-max">Most it can take off (&#8369;, for %)</label>
                        <input id="seller-voucher-max" name="max_discount" type="number" step="0.01" min="1" value="{{ old('max_discount') }}" placeholder="Leave empty for no limit">
                    </div>
                    <div class="seller-field seller-field--full">
                        <label for="seller-voucher-limit">Uses allowed</label>
                        <input id="seller-voucher-limit" name="usage_limit" type="number" min="1" value="{{ old('usage_limit') }}" placeholder="No limit">
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-starts">Starts</label>
                        <input id="seller-voucher-starts" name="starts_at" type="datetime-local" value="{{ old('starts_at') }}">
                    </div>
                    <div class="seller-field">
                        <label for="seller-voucher-ends">Ends</label>
                        <input id="seller-voucher-ends" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" @error('expires_at') aria-invalid="true" @enderror>
                        @error('expires_at')<p class="seller-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="seller-form__actions">
                        <button type="submit" class="seller-button"><i class="fas fa-ticket" aria-hidden="true"></i> Create shop voucher</button>
                    </div>
                </form>

                <aside class="seller-voucher-preview-container" aria-label="Buyer voucher preview">
                    <div class="seller-voucher-preview-heading">
                        <span class="seller-eyebrow"><i class="fas fa-eye" aria-hidden="true"></i> Buyer preview</span>
                        <h3>How buyers will see it</h3>
                        <p>Live preview of your voucher when released in checkout.</p>
                    </div>

                    <div class="seller-voucher-preview-card">
                        <div class="seller-voucher-preview-card__top">
                            <span class="seller-voucher-preview-card__badge" id="preview-badge">
                                <i class="fas fa-tag" aria-hidden="true"></i>
                                <span id="preview-badge-text">10% OFF</span>
                            </span>
                            <span class="seller-voucher-preview-card__quota" id="preview-usage-badge">
                                <i class="fas fa-ticket" aria-hidden="true"></i>
                                <span id="preview-usage-badge-text">Unlimited claims</span>
                            </span>
                        </div>
                        <div class="seller-voucher-preview-card__main">
                            <strong class="seller-voucher-preview-card__code" id="preview-code">MYSHOP10</strong>
                            <p class="seller-voucher-preview-card__name" id="preview-name"></p>
                            <p class="seller-voucher-preview-card__desc" id="preview-desc">10% off</p>
                            <div class="seller-voucher-preview-card__meta">
                                <span><i class="fas fa-store" aria-hidden="true"></i> <span id="preview-shop">{{ $shopName ?? auth()->user()->name }}</span></span>
                                <span id="preview-validity-wrap">· <span id="preview-validity">Valid until ended</span></span>
                            </div>
                        </div>
                        <div class="seller-voucher-preview-card__action">
                            <button type="button" class="btn-voucher-claim seller-voucher-preview-btn" disabled>
                                <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> Claim
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </details>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div>
                <h2>My shop vouchers</h2>
                <p>Vouchers created by your shop for your own products.</p>
            </div>
        </header>
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead><tr><th>Voucher</th><th>Offer</th><th>Validity</th><th>Used</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse($shopVouchers as $voucher)
                        <tr>
                            <td><strong>{{ $voucher->code }}</strong>@if($voucher->name)<span class="seller-cell-note">{{ $voucher->name }}</span>@endif</td>
                            <td>{{ $voucher->describe() }}</td>
                            <td>{{ $voucher->starts_at?->timezone('Asia/Manila')->format('M j') ?? 'Now' }} – {{ $voucher->expires_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'until ended' }}</td>
                            <td>{{ number_format($voucher->used_count) }}{{ $voucher->usage_limit ? ' / '.number_format($voucher->usage_limit) : '' }}</td>
                            <td>
                                <span class="order-status order-status--{{ $voucher->is_active ? 'completed' : 'cancelled' }}">
                                    {{ $voucher->is_active ? 'Active' : 'Paused' }}
                                </span>
                            </td>
                            <td>
                                <div class="order-actions">
                                    <form method="POST" action="{{ route('seller.vouchers.toggle', $voucher) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="seller-button seller-button--small seller-button--secondary">{{ $voucher->is_active ? 'Pause' : 'Activate' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('seller.vouchers.destroy', $voucher) }}"
                                          data-confirm="Delete voucher {{ $voucher->code }}? This cannot be undone." data-confirm-title="Delete voucher?" data-confirm-button="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="seller-button seller-button--small seller-button--secondary" title="Delete voucher" aria-label="Delete voucher {{ $voucher->code }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="seller-empty"><i class="fas fa-ticket" aria-hidden="true"></i><strong>No shop vouchers yet</strong><span>Create a voucher above to offer discounts on your products.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="seller-panel">
        <header class="seller-panel__header">
            <div>
                <h2>Platform invitations</h2>
                <p>Marketplace vouchers created by ZAYLO. If you join, buyers can use the voucher on your products and your shop pays the discount.</p>
            </div>
        </header>
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead><tr><th>Voucher</th><th>Offer</th><th>Runs</th><th>Your answer</th></tr></thead>
                <tbody>
                    @forelse($invitations as $invitation)
                        @php $voucher = $invitation->voucher; @endphp
                        <tr>
                            <td><strong>{{ $voucher->code }}</strong>@if($voucher->name)<span class="seller-cell-note">{{ $voucher->name }}</span>@endif</td>
                            <td>{{ $voucher->describe() }}</td>
                            <td>{{ $voucher->starts_at?->timezone('Asia/Manila')->format('M j') ?? 'Now' }} – {{ $voucher->expires_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'until ended' }}@unless($voucher->is_active)<span class="seller-cell-note">Turned off by ZAYLO</span>@endunless</td>
                            <td>
                                <div class="order-actions">
                                    @if($invitation->status !== 'pending')
                                        <span class="order-status order-status--{{ $invitation->status === 'accepted' ? 'completed' : 'cancelled' }}">{{ $invitation->status === 'accepted' ? 'Joined' : 'Declined' }}</span>
                                    @endif
                                    @if($voucher->is_active)
                                        @if($invitation->status !== 'accepted')
                                            <form method="POST" action="{{ route('seller.vouchers.respond', $voucher) }}"
                                                  data-confirm="Join {{ $voucher->code }} ({{ $voucher->describe() }})? Buyers can use it on your products and your shop pays the discount." data-confirm-title="Join voucher?" data-confirm-button="Join">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="decision" value="join">
                                                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Join</button>
                                            </form>
                                        @endif
                                        @if($invitation->status !== 'declined')
                                            <form method="POST" action="{{ route('seller.vouchers.respond', $voucher) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="decision" value="decline">
                                                <button type="submit" class="seller-button seller-button--small seller-button--secondary">{{ $invitation->status === 'accepted' ? 'Leave' : 'Decline' }}</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="seller-empty"><i class="fas fa-ticket" aria-hidden="true"></i><strong>No voucher invitations yet</strong><span>When ZAYLO starts a voucher, it appears here and you get an email.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/seller-voucher-preview.js') }}" defer></script>
@endpush
