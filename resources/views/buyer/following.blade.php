@extends('layouts.app')

@section('title', 'Shops you follow · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/shop-page.css') }}?v={{ filemtime(public_path('css/shop-page.css')) }}">
@endpush

@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-store"></i><div><h1>Shops you follow</h1><p>Visit your favorite sellers and see what's new.</p></div></div></div>
<div class="page-content">
    @forelse($shops as $row)
        @php $shop = $row['shop']; @endphp
        <article class="followed-shop">
            <a href="{{ $shop->url() }}" class="followed-shop__identity">
                <span class="shop-avatar shop-avatar--initials shop-avatar--small" aria-hidden="true">{{ $shop->initials() }}</span>
                <span>
                    <strong>{{ $shop->name }}</strong>
                    <small>{{ number_format($row['products']) }} {{ Str::plural('product', $row['products']) }} · {{ number_format($row['followers']) }} {{ Str::plural('follower', $row['followers']) }}</small>
                </span>
            </a>
            <div class="followed-shop__preview" aria-hidden="true">
                @foreach($row['preview'] as $product)
                    <img src="{{ $product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="" loading="lazy">
                @endforeach
            </div>
            <div class="followed-shop__actions">
                <a href="{{ $shop->url() }}" class="market-button">Visit shop</a>
                <form method="POST" action="{{ route('buyer.shops.follow', $shop->id) }}"
                      data-confirm="Unfollow {{ $shop->name }}?" data-confirm-title="Unfollow shop?" data-confirm-button="Unfollow">
                    @csrf
                    <button type="submit" class="market-button market-button--secondary">Unfollow</button>
                </form>
            </div>
        </article>
    @empty
        <div class="placeholder-card">
            <i class="fas fa-store" aria-hidden="true"></i>
            <h2>You're not following any shops yet</h2>
            <p>Open a product and tap the shop's name to visit it, then press Follow.</p>
            <a href="{{ route('products.index') }}" class="market-button">Browse products</a>
        </div>
    @endforelse
</div>
@endsection
