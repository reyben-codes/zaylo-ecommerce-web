{{-- Logistics company, business registration, office address, service regions and documents.
     Used on sign-up and on the logistics application page. Expects optional $provider (LogisticsProvider being resubmitted).
     Behaviour lives in public/js/seller-application.js and public/js/address-selector.js. --}}
@php
    $provider = $provider ?? null;
    $value = fn (string $field) => old($field, $provider?->{$field});
    $companyName = old('company_name', $provider?->name);
    $chosenAreas = (array) old('service_areas', $provider?->service_areas ?? []);
    $documentNotes = [
        'sec_certificate' => 'SEC Certificate of Registration.',
        'bir_2303' => 'BIR Certificate of Registration (Form 2303).',
        'business_permit' => "Current Mayor's or Business Permit.",
        'representative_id' => 'Government-issued ID of the contact person named above.',
    ];
@endphp

<div class="seller-application" data-business-application>
    <fieldset class="seller-application__group">
        <legend>Your company</legend>
        <div class="form-group">
            <label for="company_name">Company name</label>
            <input id="company_name" name="company_name" value="{{ $companyName }}" autocomplete="organization" minlength="2" maxlength="150" required>
            <p class="field-note">As registered with the SEC. It must not be used by another logistics partner.</p>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="sec_registration_no">SEC registration no.</label>
                <input id="sec_registration_no" name="sec_registration_no" value="{{ $value('sec_registration_no') }}" maxlength="11" placeholder="CS202012345"
                       pattern="[A-Za-z]{2}[0-9]{9}" title="2 letters followed by 9 digits, e.g. CS202012345" autocapitalize="characters" data-uppercase required>
            </div>
            <div class="form-group">
                <label for="tin">TIN</label>
                <input id="tin" name="tin" value="{{ $value('tin') }}" inputmode="numeric" maxlength="15" placeholder="000-000-000-000"
                       pattern="[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}" title="Enter 12 digits as 000-000-000-000" data-tin required>
            </div>
        </div>
        <div class="form-group">
            <label for="contact_position">Contact person's position</label>
            <input id="contact_position" name="contact_position" value="{{ $value('contact_position') }}" maxlength="100" autocomplete="organization-title" placeholder="e.g. Operations Manager" required>
            <p class="field-note">The contact person is the name you entered above.</p>
        </div>
    </fieldset>

    <fieldset class="seller-application__group" data-require-one="Choose at least one region you deliver to.">
        <legend>Service areas</legend>
        <p class="field-note">Choose every region you deliver to. Riders see these when choosing a partner.</p>
        <div class="service-areas">
            @foreach(\App\Models\LogisticsProvider::serviceRegions() as $key => $label)
                <label class="service-area">
                    <input type="checkbox" name="service_areas[]" value="{{ $key }}" @checked(in_array($key, $chosenAreas, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="seller-application__group" data-address-form data-locations-url="{{ url('/api/locations') }}" data-location-ready-text="Locations loaded. Choose your office location.">
        <legend>Office address</legend>
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
        <p class="field-note">JPG, PNG or PDF, up to 5 MB each.</p>
        @foreach(\App\Models\LogisticsProvider::DOCUMENTS as $field => $label)
            @php $onFile = $provider?->{$field.'_path'}; @endphp
            <div class="form-group">
                <label for="{{ $field }}">{{ $label }}</label>
                <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                       data-max-bytes="{{ \App\Models\LogisticsProvider::MAX_DOCUMENT_KB * 1024 }}" data-document-label="{{ $label }}"
                       @if($onFile) data-on-file @else required @endif>
                <p class="field-note">{{ $documentNotes[$field] }}@if($onFile) A file is already on record; choose a new one only to replace it.@endif</p>
            </div>
        @endforeach
    </fieldset>
</div>
