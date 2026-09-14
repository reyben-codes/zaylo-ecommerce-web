@extends('layouts.app')
@section('title', ($address->exists ? 'Edit Address' : 'Add Address').' · ZAYLO')
@section('nav-links')
<nav class="nav-links"><a href="{{ route('addresses.index') }}">Saved addresses</a><a href="{{ route(auth()->user()->role.'.account') }}">My account</a></nav>
@endsection
@push('styles')
<style>
    .address-form { max-width: 720px; margin: auto; }
    .address-form .form-row > label { min-width: 0; }
    .address-form input, .address-form select { width: 100%; min-width: 0; }
    .address-form [hidden] { display: none !important; }
    .address-form .check-label input { width: auto; }
    .address-form button:disabled { opacity: .55; cursor: not-allowed; }
    .address-form [role="status"] { color: #6b5f54; font-size: .85rem; }
    @media (max-width: 560px) {
        .address-page { padding: 24px 20px; }
        .address-form .form-row { grid-template-columns: 1fr; }
    }
</style>
@endpush
@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><div><h1>{{ $address->exists ? 'Edit address' : 'Add address' }}</h1><p>Philippine delivery details</p></div></div></div>
<div class="page-content address-page">
    <form method="POST" action="{{ $address->exists ? route('addresses.update', $address) : route('addresses.store') }}" class="market-form stacked-form form-card address-form" data-address-form data-locations-url="/api/locations">
        @csrf
        @if($address->exists) @method('PUT') @endif
        @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
        @if($errors->any())<ul class="flash-message error" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if($address->exists && !$address->isStructured())<p>Your existing address is preserved below. Select its region, province (if applicable), city, and barangay to complete it.</p><p>{{ $address->formatted() }}</p>@endif
        <div class="form-row">
            <label>Recipient name<input name="recipient_name" autocomplete="name" maxlength="150" value="{{ old('recipient_name', $address->recipient_name) }}" required></label>
            <label>Phone number<input type="tel" name="phone" autocomplete="tel" maxlength="30" value="{{ old('phone', $address->phone) }}" required></label>
        </div>
        <label>Region<select name="region_code" data-location="region" data-selected="{{ old('region_code', $address->region_code) }}" required><option value="">Choose a region</option></select></label>
        <label>Province<select name="province_code" data-location="province" data-selected="{{ old('province_code', $address->province_code) }}" disabled><option value="">Choose a region first</option></select></label>
        <p class="empty-note" data-province-note hidden>NCR and province-free cities do not require a province.</p>
        <label>City / Municipality<select name="city_municipality_code" data-location="city" data-selected="{{ old('city_municipality_code', $address->city_municipality_code) }}" required disabled><option value="">Choose a province first</option></select></label>
        <label>Barangay<select name="barangay_code" data-location="barangay" data-selected="{{ old('barangay_code', $address->barangay_code) }}" required disabled><option value="">Choose a city / municipality first</option></select></label>
        <p role="status" aria-live="polite" data-location-status>Loading locations…</p>
        <button class="text-button" type="button" data-location-retry hidden>Retry loading locations</button>
        <label>House / Unit / Street<input name="line1" autocomplete="address-line1" maxlength="255" value="{{ old('line1', $address->line1) }}" required></label>
        <label>Additional address details <span>(optional)</span><input name="line2" autocomplete="address-line2" maxlength="255" value="{{ old('line2', $address->line2) }}" placeholder="Building, floor, landmark, etc."></label>
        <label>Postal code<input name="postal_code" inputmode="numeric" autocomplete="postal-code" pattern="[0-9]{4}" maxlength="4" value="{{ old('postal_code', $address->postal_code) }}" required><span>Enter the four-digit postal code. PSGC does not supply postal codes.</span></label>
        @php($typeIcons = ['Home' => 'fa-house', 'Work' => 'fa-briefcase', 'School' => 'fa-graduation-cap', 'Other' => 'fa-location-dot'])
        <fieldset class="address-type-picker">
            <legend>Address type</legend>
            <div>
                @foreach($typeIcons as $label => $icon)
                    <label>
                        <input type="radio" name="label" value="{{ $label }}" @checked(old('label', $address->label) === $label) required>
                        <span><i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <input type="hidden" name="is_default" value="0">
        <label class="check-label"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default))>Use as my default address</label>
        <p class="empty-note">Your first address becomes the default. To change an existing default, choose another saved address.</p>
        <noscript><p>Please enable JavaScript to load Philippine locations.</p></noscript>
        <button class="market-button" type="submit" data-address-save disabled>Save address</button>
        <a href="{{ $returnTo === 'checkout' ? route('buyer.cart') : route('addresses.index') }}">Cancel</a>
    </form>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/address-selector.js') }}" defer></script>@endpush
