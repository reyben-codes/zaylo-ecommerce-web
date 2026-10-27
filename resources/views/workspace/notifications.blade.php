@extends($portal === 'seller' ? 'layouts.seller' : 'layouts.workspace', ['workspace' => $portal])

@section('title', 'Notifications')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/workspace-notifications.css') }}?v={{ filemtime(public_path('css/workspace-notifications.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ ['admin' => 'Admin Center', 'logistics' => 'Logistics Center'][$portal] ?? 'Seller Center' }}</p>
            <h1>Notifications</h1>
            <p>
                {{ [
                    'admin' => 'Applications, ID checks, listings, complaints and support messages waiting for you, and the decisions admins made.',
                    'logistics' => 'Decisions about your account and rider applications waiting for your review.',
                ][$portal] ?? 'New orders, listing reviews, stock alerts, account decisions and warnings about your shop.' }}
                {{ $unreadCount }} unread.
            </p>
        </div>
        @if($unreadCount > 0)
            <div class="seller-page-heading__actions">
                <form method="POST" action="{{ route($portal.'.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="seller-button seller-button--secondary"><i class="fas fa-check-double" aria-hidden="true"></i> Mark all as read</button>
                </form>
            </div>
        @endif
    </section>

    <section class="seller-panel">
        @if($notifications->isEmpty())
            <div class="workspace-notice-empty">
                <i class="far fa-bell" aria-hidden="true"></i>
                <h2>You're all caught up</h2>
                <p>New items that need your attention will appear here.</p>
            </div>
        @else
            <ul class="workspace-notices">
                @foreach($notifications as $notification)
                    <li class="workspace-notice{{ $notification['read'] ? '' : ' is-unread' }}{{ $notification['urgent'] ? ' is-urgent' : '' }}">
                        <span class="workspace-notice__icon workspace-notice__icon--{{ $notification['type'] }}" aria-hidden="true"><i class="fas {{ $notification['icon'] }}"></i></span>
                        <div class="workspace-notice__copy">
                            <h2>
                                {{ $notification['title'] }}
                                @unless($notification['read'])<span class="workspace-notice__dot"><span class="sr-only">Unread</span></span>@endunless
                            </h2>
                            <p>{{ $notification['message'] }}</p>
                            <time datetime="{{ $notification['at']->toIso8601String() }}" title="{{ $notification['at']->copy()->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT">{{ $notification['at']->diffForHumans() }}</time>
                        </div>
                        <form method="POST" action="{{ route($portal.'.notifications.open') }}" class="workspace-notice__action">
                            @csrf
                            <input type="hidden" name="key" value="{{ $notification['key'] }}">
                            <button type="submit" class="seller-button seller-button--small{{ $notification['read'] ? ' seller-button--secondary' : '' }}">{{ $notification['action'] }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
