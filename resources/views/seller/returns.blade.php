@extends('layouts.seller')

@section('auto-refresh', 'on')
@section('title', 'Returns')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-orders.css') }}?v={{ filemtime(public_path('css/seller-orders.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/seller-returns.css') }}?v={{ filemtime(public_path('css/seller-returns.css')) }}">
@endpush

@section('content')
    @php
        $tabs = ['action' => 'Needs action', 'in_progress' => 'In progress', 'closed' => 'Closed', 'all' => 'All'];
        $types = \App\Models\Dispute::RETURN_TYPES;
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Returns &amp; refunds</h1>
            <p>Follow returned items back to your shop, confirm they arrived, and record refunds.</p>
        </div>
    </section>

    <aside class="seller-notice">
        <i class="fas fa-circle-info" aria-hidden="true"></i>
        <div>
            <strong>How returns work</strong>
            <p>The courier collects the item from the buyer (or the buyer drops it off at a hub) and scans it at each step. When it reaches you, confirm you received it, check it, then record the refund. The buyer is emailed at every step and sees it in the case.</p>
        </div>
    </aside>

    @unless($tracksRefunds)
        <aside class="seller-notice seller-notice--danger">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <div><strong>Refunds are being set up</strong><p>You can follow returns now; confirming receipt and refunds turn on after the next update.</p></div>
        </aside>
    @endunless

    <section class="seller-panel">
        <div class="orders-toolbar">
            <nav class="order-tabs" aria-label="Filter returns">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('seller.returns', ['status' => $key]) }}"
                       class="order-tab{{ $filter === $key ? ' is-active' : '' }}"
                       @if($filter === $key) aria-current="true" @endif>
                        {{ $label }} <span>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        @forelse($cases as $case)
            @php
                $orderNumber = $case->order?->order_number ?? $case->order?->reference ?? '#'.$case->order_id;
            @endphp
            <article class="return-case">
                <header class="return-case__head">
                    <div>
                        <h2>Order {{ $orderNumber }}</h2>
                        <p>{{ $types[$case->return_type] ?? 'Complaint' }} · {{ $case->category_label }} · Filed {{ $case->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</p>
                    </div>
                    <span class="return-case__status return-case__status--{{ $case->status }}">{{ $case->status_label }}</span>
                </header>

                <p class="return-case__reason">{{ \Illuminate\Support\Str::limit($case->reason, 220) }}</p>

                @if($case->hasReturnParcel())
                    <p class="seller-cell-note">
                        Return {{ $case->return_tracking_number }} · {{ $case->returnMethodName() }} · {{ $case->returnCourierName() }}
                        @if($case->return_method === 'pickup' && $case->pickup_date)
                            · Pickup {{ $case->pickup_date->format('M j') }}
                        @elseif($case->dropoff_deadline)
                            · Drop-off by {{ $case->dropoff_deadline->format('M j') }}
                        @endif
                    </p>
                @else
                    <p class="seller-cell-note">No item to send back{{ $case->return_type === 'refund_only' ? ': the buyer asked for a refund only.' : '.' }}</p>
                @endif

                {{-- Every step of the return with its dates and proof photos, and what the seller does now. --}}
                <details class="return-track" data-smooth-disclosure @if($loop->first) open @endif>
                    <summary><i class="fas fa-route" aria-hidden="true"></i> Track this return</summary>
                    <div data-disclosure-content>
                        @include('partials.return-process', ['case' => $case, 'role' => 'seller'])
                    </div>
                </details>

                @if($case->refunded_at)
                    <p class="return-case__refunded"><i class="fas fa-circle-check" aria-hidden="true"></i> Refunded ₱{{ number_format((float) $case->refund_amount, 2) }} on {{ $case->refunded_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</p>
                @endif

                <div class="order-actions">
                    @if($tracksRefunds && $case->canConfirmReceipt())
                        <form method="POST" action="{{ route('seller.returns.receive', $case) }}"
                              data-confirm="Confirm that the returned item from order {{ $orderNumber }} reached you?" data-confirm-title="Return received?" data-confirm-button="Confirm received">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="seller-button seller-button--small"><i class="fas fa-box-open" aria-hidden="true"></i> Return received</button>
                        </form>
                    @endif

                    <a href="{{ route('disputes.show', $case) }}" class="seller-button seller-button--small seller-button--secondary"><i class="fas fa-comments" aria-hidden="true"></i> Open case</a>
                </div>

                @if($tracksRefunds && $case->canRefund())
                    @php $retry = (string) old('refund_case') === (string) $case->id; @endphp
                    <details class="return-refund" data-smooth-disclosure @if($retry) open @endif>
                        <summary><i class="fas fa-money-bill-transfer" aria-hidden="true"></i> Issue refund</summary>
                        <div data-disclosure-content>
                            <form method="POST" action="{{ route('seller.returns.refund', $case) }}" class="return-refund__form">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="refund_case" value="{{ $case->id }}">
                                <label>Refund amount (₱)
                                    <input type="number" name="refund_amount" min="0.01" step="0.01" required
                                           value="{{ $retry ? old('refund_amount') : number_format((float) ($case->order?->total ?? (($case->order?->total_minor ?? 0) / 100)), 2, '.', '') }}">
                                </label>
                                <label>Note to the buyer (optional)
                                    <textarea name="refund_note" rows="2" maxlength="500" placeholder="e.g. Sent through GCash to the number on the order.">{{ $retry ? old('refund_note') : '' }}</textarea>
                                </label>
                                <button type="submit" class="seller-button seller-button--small">Record refund</button>
                            </form>
                        </div>
                    </details>
                @endif
            </article>
        @empty
            <div class="seller-empty">
                <i class="fas fa-rotate-left" aria-hidden="true"></i>
                <strong>No returns here</strong>
                <span>Returns and refund requests from buyers appear here.</span>
            </div>
        @endforelse
    </section>
@endsection
