@extends($layout)

@section('title', 'Case #'.$dispute->id)

@push('styles')
    @include('disputes.partials.styles', ['layout' => $layout])
@endpush

@section('content')
    <div class="case-page">
        <section class="seller-page-heading">
            <div>
                <p class="seller-eyebrow"><a href="{{ route('disputes.index') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Complaints</a></p>
                <h1>Case #{{ $dispute->id }}: {{ $dispute->category_label }}</h1>
                <p>Order {{ $dispute->order?->order_number ?? '#'.$dispute->order_id }} · filed {{ $dispute->created_at?->timezone('Asia/Manila')->format('M j, Y') }} · you are the {{ Str::lower($dispute->roleOf(auth()->id())) }}</p>
            </div>
            <div class="seller-page-heading__actions">
                <span class="case-pill case-pill--{{ $dispute->status }} case-pill--large">{{ $dispute->status_label }}</span>
            </div>
        </section>

        @if(session('status'))
            <p class="case-flash" role="status">{{ session('status') }}</p>
        @endif

        @if($dispute->isClosed() && $dispute->resolution)
            <section class="seller-panel case-decision">
                <h2><i class="fas fa-gavel" aria-hidden="true"></i> Decision</h2>
                <p>{{ $dispute->resolution }}</p>
            </section>
        @endif

        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Complaint</h2><p>Filed by {{ (int) $dispute->opened_by === (int) auth()->id() ? 'you' : 'the buyer' }}.</p></div></header>
            <div class="case-body">
                <p class="case-text">{{ $dispute->reason }}</p>
                @if($dispute->evidence)
                    <ul class="case-files">
                        @foreach($dispute->evidence as $index => $path)
                            <li><a href="{{ route('disputes.evidence', [$dispute, $index]) }}" target="_blank" rel="noopener" class="workspace-link"><i class="fas {{ Str::endsWith(Str::lower($path), '.pdf') ? 'fa-file-pdf' : 'fa-image' }}" aria-hidden="true"></i> File {{ $index + 1 }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Conversation</h2><p>The buyer, seller, courier and the ZAYLO team see these messages.</p></div></header>
            @include('disputes.partials.thread', ['dispute' => $dispute, 'replyRoute' => route('disputes.reply', $dispute)])
        </section>
    </div>
@endsection
