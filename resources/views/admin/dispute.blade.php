@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Case #'.$dispute->id)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}?v={{ filemtime(public_path('css/disputes.css')) }}">
@endpush

@section('content')
    @php $order = $dispute->order; @endphp
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow"><a href="{{ route('admin.disputes') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Complaints and disputes</a></p>
            <h1>Case #{{ $dispute->id }}: {{ $dispute->category_label }}</h1>
            <p>Order {{ $order?->order_number ?? '#'.$dispute->order_id }} · filed {{ $dispute->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} by {{ $dispute->opener?->name ?? 'a deleted account' }}</p>
        </div>
        <div class="seller-page-heading__actions">
            <span class="case-pill case-pill--{{ $dispute->status }} case-pill--large">{{ $dispute->status_label }}</span>
        </div>
    </section>

    <div class="seller-panel-grid">
        <div class="admin-stack">
            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Complaint</h2><p>What the buyer reported.</p></div></header>
                <div class="case-body">
                    <p class="case-text">{{ $dispute->reason }}</p>
                    @if($dispute->evidence)
                        <h3>Evidence</h3>
                        <ul class="case-files">
                            @foreach($dispute->evidence as $index => $path)
                                <li><a href="{{ route('disputes.evidence', [$dispute, $index]) }}" target="_blank" rel="noopener" class="workspace-link"><i class="fas {{ Str::endsWith(Str::lower($path), '.pdf') ? 'fa-file-pdf' : 'fa-image' }}" aria-hidden="true"></i> File {{ $index + 1 }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="seller-cell-note">No evidence was attached.</p>
                    @endif
                </div>
            </section>

            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Conversation</h2><p>Everyone in the case sees these messages: buyer, seller and courier.</p></div></header>
                @include('disputes.partials.thread', ['dispute' => $dispute, 'replyRoute' => route('admin.disputes.reply', $dispute), 'viewerIsAdmin' => true])
            </section>
        </div>

        <div class="admin-stack">
            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>Decision</h2><p>Resolving or rejecting emails the decision to everyone in the case.</p></div></header>
                <form method="POST" action="{{ route('admin.disputes.update', $dispute) }}" class="admin-form">
                    @csrf
                    @method('PATCH')
                    <div class="seller-field">
                        <label class="seller-field__label" for="case-status">Status</label>
                        <select id="case-status" name="status">
                            @foreach(\App\Models\Dispute::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected(old('status', $dispute->status) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="seller-field">
                        <label class="seller-field__label" for="case-resolution">Decision and next steps</label>
                        <textarea id="case-resolution" name="resolution" rows="5" maxlength="2000" placeholder="e.g. Seller will send a replacement within 3 days; buyer keeps the damaged item.">{{ old('resolution', $dispute->resolution) }}</textarea>
                        @error('resolution')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="seller-button"><i class="fas fa-gavel" aria-hidden="true"></i> Save decision</button>
                    @if($dispute->resolver)
                        <p class="seller-cell-note">Decided by {{ $dispute->resolver->name }} on {{ $dispute->resolved_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}.</p>
                    @endif
                </form>
            </section>

            <section class="seller-panel">
                <header class="seller-panel__header"><div><h2>People in this case</h2></div></header>
                <dl class="workspace-detail-list case-people">
                    @foreach(['buyer' => 'Buyer', 'seller' => 'Seller', 'courier' => 'Courier'] as $key => $label)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if($parties[$key])
                                    <a href="{{ route('admin.users.show', $parties[$key]) }}" class="workspace-link">{{ $parties[$key]->name }}</a>
                                    <span class="seller-cell-note">{{ $parties[$key]->email }}</span>
                                @else
                                    <span class="seller-cell-note">{{ $key === 'courier' ? 'No rider assigned' : 'Not available' }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if($order)
                <section class="seller-panel">
                    <header class="seller-panel__header"><div><h2>Order</h2><p>{{ Str::headline($order->status) }} · {{ strtoupper((string) $order->payment_method) }} · payment {{ $order->payment?->status ?? 'pending' }}</p></div></header>
                    <ul class="case-items">
                        @foreach($order->items as $item)
                            <li><span>{{ $item->quantity }} × {{ $item->product_name }}</span><strong>&#8369;{{ number_format((float) ($item->line_total ?? 0), 2) }}</strong></li>
                        @endforeach
                        <li class="case-items__total"><span>Order total</span><strong>&#8369;{{ number_format($order->total, 2) }}</strong></li>
                    </ul>
                    @if($order->relationLoaded('statusHistory') && $order->statusHistory->isNotEmpty())
                        <ol class="case-history">
                            @foreach($order->statusHistory->sortBy('id') as $entry)
                                <li><strong>{{ Str::headline($entry->status) }}</strong> <span>{{ $entry->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}{{ $entry->note ? ' · '.$entry->note : '' }}</span></li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endif
        </div>
    </div>
@endsection
