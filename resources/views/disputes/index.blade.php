@extends($layout)

@section('title', 'Complaints')

@push('styles')
    @include('disputes.partials.styles', ['layout' => $layout])
@endpush

@section('content')
    <div class="case-page">
        <section class="seller-page-heading">
            <div>
                <p class="seller-eyebrow">Help</p>
                <h1>Complaints</h1>
                <p>Complaints about orders you bought, sold or delivered. Open one to read the details and reply to the ZAYLO team.</p>
            </div>
        </section>

        <section class="seller-panel">
            @if($disputes->isEmpty())
                <div class="seller-empty">
                    <i class="fas fa-scale-balanced" aria-hidden="true"></i>
                    <strong>No complaints</strong>
                    <span>{{ auth()->user()->hasRole('buyer') ? 'If something is wrong with an order, use "Report a problem" in My Orders.' : 'Complaints about your orders will appear here.' }}</span>
                </div>
            @else
                <ul class="workspace-list">
                    @foreach($disputes as $dispute)
                        <li class="workspace-list__item">
                            <div class="workspace-list__main">
                                <strong><a href="{{ route('disputes.show', $dispute) }}" class="workspace-link">Case #{{ $dispute->id }} · {{ $dispute->category_label }}</a></strong>
                                <span>Order {{ $dispute->order?->order_number ?? '#'.$dispute->order_id }} · you are the {{ Str::lower($dispute->roleOf(auth()->id())) }} · updated {{ $dispute->updated_at?->diffForHumans() }}</span>
                            </div>
                            <span class="case-pill case-pill--{{ $dispute->status }}">{{ $dispute->status_label }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
