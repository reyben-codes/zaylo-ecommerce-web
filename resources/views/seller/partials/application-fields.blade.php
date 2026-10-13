{{-- Seller shop, business, pickup address and documents. Used on sign-up and on the seller application page.
     Expects optional $application (SellerApplication being resubmitted). Behaviour lives in public/js/seller-application.js. --}}
@php
    $application = $application ?? null;
    $value = fn (string $field) => old($field, $application?->{$field});
    $selectedType = $value('seller_type');
    $required = \App\Models\SellerApplication::REQUIRED_DOCUMENTS;
    $documentNotes = [
        'government_id' => 'All seller types. For corporations, the ID of the authorized representative.',
        'dti_certificate' => 'Sole proprietorships only.',
        'sec_certificate' => 'Corporations only.',
        'bir_2303' => 'Sole proprietorships and corporations.',
    ];
@endphp

<div class="seller-application" data-seller-application>
    <fieldset class="seller-application__group">
        <legend>Your shop</legend>
        <div class="form-group">
            <label for="shop_name">Shop name</label>
            <input id="shop_name" name="shop_name" value="{{ $value('shop_name') }}" minlength="3" maxlength="30" autocomplete="organization" required>
            <p class="field-note">3 to 30 characters. Buyers see this name. It must not be used by another shop.</p>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="shop_category">Shop category</label>
                <select id="shop_category" name="shop_category" required>
                    <option value="">Choose a category</option>
                    @foreach(\App\Models\SellerApplication::categories() as $key => $label)
                        <option value="{{ $key }}" @selected($value('shop_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="seller_type">Seller type</label>
                <select id="seller_type" name="seller_type" data-seller-type required>
                    <option value="">Choose a seller type</option>
                    @foreach(\App\Models\SellerApplication::SELLER_TYPES as $key => $label)
                        <option value="{{ $key }}" @selected($selectedType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="tin">TIN</label>
            <input id="tin" name="tin" value="{{ $value('tin') }}" inputmode="numeric" maxlength="15" placeholder="000-000-000-000"
                   pattern="[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}" title="Enter 12 digits as 000-000-000-000" data-tin required>
            <p class="field-note">12 digits: your 9-digit TIN and 3-digit branch code (000 for the main branch).</p>
        </div>
    </fieldset>

    <fieldset class="seller-application__group" data-address-form data-locations-url="{{ url('/api/locations') }}" data-location-ready-text="Locations loaded. Choose your pickup location.">
        <legend>Pickup address</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="region_code">Region</label>
                <select id="region_code" name="region_code" data-location="region" data-selected="{{ $value('region_code') }}" required><option value="">Choose a region</option></select>
            </div>
            <div class="form-group">
                <label for="province_code">Province</label>
                <select id="province_code" name="province_code" data-location="province" data-selected="{{ $value('province_code') }}" disabled><option value="">Choose a region first</option></select>
            </div>
        </div>
        <p class="field-note" data-province-note hidden>NCR and province-free cities do not need a province.</p>
        <div class="form-row">
            <div class="form-group">
                <label for="city_code">City / Municipality</label>
                <select id="city_code" name="city_code" data-location="city" data-selected="{{ $value('city_code') }}" required disabled><option value="">Choose a province first</option></select>
            </div>
            <div class="form-group">
                <label for="barangay_code">Barangay</label>
                <select id="barangay_code" name="barangay_code" data-location="barangay" data-selected="{{ $value('barangay_code') }}" required disabled><option value="">Choose a city / municipality first</option></select>
            </div>
        </div>
        <p class="field-note" role="status" aria-live="polite" data-location-status>Loading locations…</p>
        <button class="seller-application__retry" type="button" data-location-retry hidden>Retry loading locations</button>
        <noscript><p class="field-note">Please enable JavaScript to load Philippine locations.</p></noscript>
        <div class="form-row">
            <div class="form-group">
                <label for="street_address">Street address</label>
                <input id="street_address" name="street_address" value="{{ $value('street_address') }}" maxlength="255" autocomplete="address-line1" placeholder="House, unit or lot number and street" required>
            </div>
            <div class="form-group seller-application__postal">
                <label for="postal_code">Postal code</label>
                <input id="postal_code" name="postal_code" value="{{ $value('postal_code') }}" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" title="Enter a 4-digit postal code" autocomplete="postal-code" data-digits-only required>
            </div>
        </div>
    </fieldset>

    <fieldset class="seller-application__group">
        <legend>Documents</legend>
        <p class="field-note">JPG, PNG or PDF, up to 5 MB each. Choose your seller type to see which documents you need.</p>
        @foreach(\App\Models\SellerApplication::DOCUMENTS as $field => $label)
            @php
                $types = collect($required)->filter(fn ($documents) => in_array($field, $documents, true))->keys();
                $shown = ! $selectedType || $types->contains($selectedType);
                $onFile = $application?->{$field.'_path'};
            @endphp
            <div class="seller-document{{ $shown ? '' : ' is-collapsed' }}" data-seller-document data-types="{{ $types->implode(' ') }}" @unless($shown) inert @endunless>
                <div class="seller-document__inner">
                    <div class="form-group">
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                               data-max-bytes="{{ \App\Models\SellerApplication::MAX_DOCUMENT_KB * 1024 }}" data-document-label="{{ $label }}"
                               @if($onFile) data-on-file @endif @if($selectedType && $shown && ! $onFile) required @endif @unless($shown) disabled @endunless>
                        <p class="field-note">{{ $documentNotes[$field] }}@if($onFile) A file is already on record; choose a new one only to replace it.@endif</p>
                    </div>
                </div>
            </div>
        @endforeach
    </fieldset>
</div>
