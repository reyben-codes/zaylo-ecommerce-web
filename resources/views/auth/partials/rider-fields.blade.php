{{-- Desktop rider application: logistics partner, vehicle, driver's license and documents. Behaviour lives in public/js/rider-application.js.
     Expects $logisticsPartners. Bicycle riders need no license, plate or OR/CR, so those fields fold away for them. --}}
@php
    $documentsReady = \Illuminate\Support\Facades\Schema::hasColumn('riders', 'license_document_path');
    $unlicensed = in_array(old('vehicle_type'), \App\Models\Rider::UNLICENSED_VEHICLES, true);
@endphp

<div class="rider-application" data-rider-application data-unlicensed="{{ implode(' ', \App\Models\Rider::UNLICENSED_VEHICLES) }}">
    <fieldset class="rider-application__group">
        <legend>Where you'll deliver</legend>
        <div class="form-group">
            <label for="logistics_provider_id">Logistics partner</label>
            <select id="logistics_provider_id" name="logistics_provider_id" required>
                <option value="">Choose who you'll deliver for</option>
                @foreach($logisticsPartners as $partner)
                    <option value="{{ $partner->id }}" @selected((string) old('logistics_provider_id') === (string) $partner->id)>
                        {{ $partner->name }}{{ $partner->service_area ? ' — '.$partner->service_area : '' }}
                    </option>
                @endforeach
            </select>
            <p class="field-note">
                {{ $logisticsPartners->isEmpty()
                    ? 'No logistics partners are accepting riders yet. Please check back soon.'
                    : 'This partner reviews your application and assigns your deliveries.' }}
            </p>
        </div>
    </fieldset>

    <fieldset class="rider-application__group">
        <legend>Your vehicle</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="vehicle_type">Vehicle type</label>
                <select id="vehicle_type" name="vehicle_type" data-vehicle-type required>
                    <option value="">Select vehicle</option>
                    @foreach(\App\Models\Rider::VEHICLE_TYPES as $vehicle)
                        <option value="{{ $vehicle }}" @selected(old('vehicle_type') === $vehicle)>{{ $vehicle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rider-licensed{{ $unlicensed ? ' is-collapsed' : '' }}" data-rider-licensed @if($unlicensed) inert @endif>
                <div class="rider-licensed__inner">
                    <div class="form-group">
                        <label for="plate_no">Plate number</label>
                        <input id="plate_no" name="plate_no" value="{{ old('plate_no') }}" maxlength="9" autocapitalize="characters" placeholder="123ABC or NBC1234"
                               pattern="[A-Za-z0-9 \-]{5,9}" title="5 to 7 letters and numbers, for example 123ABC or NBC1234" data-plate @unless($unlicensed) required @endunless @if($unlicensed) disabled @endif>
                    </div>
                </div>
            </div>
        </div>
        <p class="field-note">Bicycle riders don't need a plate, driver's license or OR/CR.</p>
    </fieldset>

    <div class="rider-licensed{{ $unlicensed ? ' is-collapsed' : '' }}" data-rider-licensed @if($unlicensed) inert @endif>
        <div class="rider-licensed__inner">
            <fieldset class="rider-application__group">
                <legend>Driver's license</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="license_number">License number</label>
                        <input id="license_number" name="license_number" value="{{ old('license_number') }}" maxlength="13" autocapitalize="characters" placeholder="A00-00-000000"
                               pattern="[A-Za-z][0-9]{2}-?[0-9]{2}-?[0-9]{6}" title="LTO format: a letter, then 2-2-6 digits, for example N01-23-456789" data-license
                               @unless($unlicensed) required @endunless @if($unlicensed) disabled @endif>
                    </div>
                    @if($documentsReady)
                        <div class="form-group">
                            <label for="license_expires_on">Expiry date</label>
                            <input type="date" id="license_expires_on" name="license_expires_on" value="{{ old('license_expires_on') }}" min="{{ today()->toDateString() }}"
                                   data-license-expiry @unless($unlicensed) required @endunless @if($unlicensed) disabled @endif>
                        </div>
                    @endif
                </div>
            </fieldset>

            @if($documentsReady)
                <fieldset class="rider-application__group">
                    <legend>Documents</legend>
                    <p class="field-note">JPG, PNG or PDF, up to 5 MB each. Your logistics partner checks these before approving you.</p>
                    @foreach(\App\Models\Rider::DOCUMENTS as $field => $label)
                        <div class="form-group">
                            <label for="{{ $field }}">{{ $label }}</label>
                            <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                   data-max-bytes="{{ \App\Models\Rider::MAX_DOCUMENT_KB * 1024 }}" data-document-label="{{ $label }}"
                                   @unless($unlicensed) required @endunless @if($unlicensed) disabled @endif>
                            <p class="field-note">{{ $field === 'license_document' ? 'A clear photo of the front of your license.' : 'The Official Receipt and Certificate of Registration of the vehicle you will use.' }}</p>
                        </div>
                    @endforeach
                </fieldset>
            @endif
        </div>
    </div>
</div>
