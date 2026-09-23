@extends('layouts.app')

@section('title', 'Notifications · ZAYLO')

@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="far fa-bell" aria-hidden="true"></i>
        <div>
            <h1>Notifications</h1>
            <p>Recent arrivals, voucher reminders, and updates on your orders.</p>
        </div>
    </div>
</div>

<main class="page-content buyer-notifications-page">
    <div class="notification-list-heading">
        <div>
            <h2>Recent updates</h2>
            <p>Updates from the last 30 days <span aria-hidden="true">·</span> {{ $unreadCount }} unread</p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('buyer.notifications.read-all') }}">
                @csrf
                <button type="submit" class="notification-read-all"><i class="fas fa-check-double" aria-hidden="true"></i> Mark all as read</button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="notification-empty">
            <i class="far fa-bell" aria-hidden="true"></i>
            <h2>You're all caught up</h2>
            <p>New products, voucher offers, and order updates will appear here.</p>
            <a href="{{ route('products.index') }}" class="notification-action notification-explore">Explore products <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    @else
        <div class="notification-list">
            @foreach($notifications as $notification)
                <article class="notification-item{{ $notification['read'] ? '' : ' is-unread' }}">
                    <div class="notification-type-icon notification-type-{{ $notification['type'] }}" aria-hidden="true">
                        <i class="fas {{ match($notification['type']) { 'product' => 'fa-bag-shopping', 'voucher' => 'fa-ticket', default => 'fa-box' } }}"></i>
                    </div>
                    <div class="notification-item-copy">
                        <div class="notification-item-top">
                            <h3>{{ $notification['title'] }}</h3>
                            @unless($notification['read'])<span class="notification-unread-dot"><span class="sr-only">Unread</span></span>@endunless
                        </div>
                        <p>{{ $notification['message'] }}</p>
                        <div class="notification-item-footer">
                            <time datetime="{{ $notification['at']->toIso8601String() }}" title="{{ $notification['at']->copy()->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT">{{ $notification['at']->diffForHumans() }}</time>
                            <form method="POST" action="{{ route('buyer.notifications.open') }}">
                                @csrf
                                <input type="hidden" name="key" value="{{ $notification['key'] }}">
                                <button type="submit" class="notification-action">{{ $notification['action'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</main>
@endsection
