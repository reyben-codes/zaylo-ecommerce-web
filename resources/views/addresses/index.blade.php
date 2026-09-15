@extends('layouts.app')
@section('title', 'Saved Addresses · ZAYLO')
@section('nav-links')
<nav class="nav-links"><a href="{{ route(auth()->user()->role.'.account') }}">My account</a><a href="{{ route('products.index') }}">Shop</a></nav>
@endsection
@section('content')
@php
    $addressTypes = ['Home' => 'fa-house', 'Work' => 'fa-briefcase', 'School' => 'fa-graduation-cap', 'Other' => 'fa-location-dot'];
@endphp
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><div><h1>Saved addresses</h1><p>Organize delivery locations by type and choose the one used by default.</p></div></div></div>
<div class="page-content address-book-page">
    <div class="address-book-toolbar">
        <div><span class="eyebrow">Address book</span><h2>{{ $addresses->count() }} saved {{ Str::plural('address', $addresses->count()) }}</h2></div>
        <a class="market-button" href="{{ route('addresses.create') }}"><i class="fas fa-plus"></i> Add address</a>
    </div>
    <div class="address-type-actions" aria-label="Add an address by type">
        @foreach($addressTypes as $type => $icon)
            <a href="{{ route('addresses.create', ['type' => $type]) }}"><i class="fas {{ $icon }}"></i><span>Add {{ $type }}</span></a>
        @endforeach
    </div>

    @foreach($addressTypes as $type => $icon)
        @php($typedAddresses = $addresses->where('label', $type))
        @if($typedAddresses->isNotEmpty())
            <section class="address-type-section" aria-labelledby="address-type-{{ strtolower($type) }}">
                <h2 id="address-type-{{ strtolower($type) }}"><i class="fas {{ $icon }}"></i> {{ $type }} <span>{{ $typedAddresses->count() }}</span></h2>
                <div class="delivery-grid">
                    @foreach($typedAddresses as $address)
                        <article class="delivery-card saved-address-card">
                            <div class="address-card-title"><h3>{{ $address->label }}</h3>@if($address->is_default)<span class="status-pill">Default delivery</span>@endif</div>
                            <p><strong>{{ $address->recipient_name }}</strong><br>{{ $address->phone }}</p>
                            <p>{{ $address->formatted() }}</p>
                            @unless($address->isStructured())<p class="field-error">Complete this address before using it at checkout.</p>@endunless
                            <div class="saved-address-actions">
                                <a href="{{ route('addresses.edit', $address) }}">Edit</a>
                                @unless($address->is_default)
                                    <form method="POST" action="{{ route('addresses.default', $address) }}">@csrf @method('PATCH')<button class="text-button">Set as delivery default</button></form>
                                @endunless
                                <form method="POST" action="{{ route('addresses.destroy', $address) }}" data-delete-address>@csrf @method('DELETE')<button class="text-button danger">Delete</button></form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if($addresses->isEmpty())
        <div class="address-empty-state"><i class="far fa-map"></i><h2>No saved addresses</h2><p>Add Home, Work, School, or another address to make checkout faster.</p><a href="{{ route('addresses.create', ['type' => 'Home']) }}">Add your first address</a></div>
    @endif
    @if(auth()->user()->role === 'buyer')<a class="manage-address-link" href="{{ route('buyer.cart') }}"><i class="fas fa-arrow-left"></i> Return to cart</a>@endif
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
