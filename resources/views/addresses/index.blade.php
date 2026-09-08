@extends('layouts.app')
@section('title', 'Saved Addresses · ZAYLO')
@section('nav-links')
<nav class="nav-links"><a href="{{ route(auth()->user()->role.'.account') }}">My account</a><a href="{{ route('products.index') }}">Shop</a></nav>
@endsection
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><div><h1>Saved addresses</h1><p>Keep your delivery details ready for next time.</p></div></div></div>
<div class="page-content">
    <a class="market-button" href="{{ route('addresses.create') }}">Add address</a>
    <div class="delivery-grid">
        @forelse($addresses as $address)
            <article class="delivery-card">
                <h2>{{ $address->label }} @if($address->is_default)<span class="status-pill">Default</span>@endif</h2>
                <p><strong>{{ $address->recipient_name }}</strong><br>{{ $address->phone }}</p>
                <p>{{ $address->formatted() }}</p>
                @unless($address->isStructured())<p class="empty-note">Please edit this address to select its PSGC locations before using it at checkout.</p>@endunless
                <a href="{{ route('addresses.edit', $address) }}">Edit address</a>
                @unless($address->is_default)
                    <form method="POST" action="{{ route('addresses.default', $address) }}">@csrf @method('PATCH')<button class="text-button">Set as default</button></form>
                @endunless
                <form method="POST" action="{{ route('addresses.destroy', $address) }}" data-delete-address>@csrf @method('DELETE')<button class="text-button danger">Delete address</button></form>
            </article>
        @empty
            <p class="empty-note">No saved addresses yet. Add your first address to use at checkout.</p>
        @endforelse
    </div>
    @if(auth()->user()->role === 'buyer')<a href="{{ route('buyer.cart') }}">Return to cart</a>@endif
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-delete-address]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm('Delete this saved address? Existing orders will not change.')) event.preventDefault();
    });
});
</script>
@endpush
