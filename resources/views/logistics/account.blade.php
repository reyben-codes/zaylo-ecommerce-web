@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Account')

@include('partials.leaflet-assets')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Account</h1>
            <p>Company details riders see when they apply, plus your own profile and password.</p>
        </div>
        <span class="role-pill account-status--{{ $provider->status === 'approved' ? 'active' : $provider->status }}">{{ $provider->status === 'approved' ? 'Approved partner' : Str::headline($provider->status) }}</span>
    </section>

    <div class="seller-panel-grid workspace-account-grid">
        <div class="workspace-column">
            <section class="seller-panel" aria-labelledby="company-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="company-heading">Company details</h2>
                        <p>Riders pick your company by this name when they register.</p>
                    </div>
                </header>
                <form method="POST" action="{{ route('logistics.account.company') }}" class="seller-panel__body seller-form">
                    @csrf
                    @method('PATCH')
                    <div class="seller-form-grid">
                        <div class="seller-field seller-field--full">
                            <label for="company-name">Company name</label>
                            <input id="company-name" name="company_name" value="{{ old('company_name', $provider->name) }}" minlength="2" maxlength="150" required @error('company_name') aria-invalid="true" @enderror>
                            @error('company_name')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="seller-field">
                            <label for="contact-phone">Dispatch phone</label>
                            <input id="contact-phone" type="tel" name="contact_phone" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" value="{{ old('contact_phone', $provider->contact_phone) }}" required @error('contact_phone') aria-invalid="true" @enderror>
                            @error('contact_phone')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="seller-field">
                            <label for="service-area">Service area</label>
                            <input id="service-area" name="service_area" maxlength="150" value="{{ old('service_area', $provider->service_area) }}" placeholder="e.g. Metro Manila, Cavite">
                            @error('service_area')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="seller-field seller-field--full" data-address-form data-locations-url="{{ url('/api/locations') }}">
                            <label>Hub Office Address</label>
                            <p class="seller-field__help">Your physical sorting hub or warehouse address.</p>
                            <div class="seller-address-nested-grid">
                                <div class="seller-field">
                                    <label for="hub-region">Region</label>
                                    <select id="hub-region" name="region_code" data-location="region" data-selected="{{ old('region_code', $provider->region_code) }}">
                                        <option value="">Choose a region</option>
                                    </select>
                                </div>
                                <div class="seller-field">
                                    <label for="hub-province">Province</label>
                                    <select id="hub-province" name="province_code" data-location="province" data-selected="{{ old('province_code', $provider->province_code) }}" disabled>
                                        <option value="">Choose a region first</option>
                                    </select>
                                </div>
                                <div class="seller-field">
                                    <label for="hub-city">City / Municipality</label>
                                    <select id="hub-city" name="city_code" data-location="city" data-selected="{{ old('city_code', $provider->city_code) }}" disabled>
                                        <option value="">Choose a province first</option>
                                    </select>
                                </div>
                                <div class="seller-field">
                                    <label for="hub-barangay">Barangay</label>
                                    <select id="hub-barangay" name="barangay_code" data-location="barangay" data-selected="{{ old('barangay_code', $provider->barangay_code) }}" disabled>
                                        <option value="">Choose a city first</option>
                                    </select>
                                </div>
                                <div class="seller-field seller-field--full">
                                    <label for="hub-street">Street address</label>
                                    <input id="hub-street" name="street_address" value="{{ old('street_address', $provider->street_address) }}" maxlength="255" placeholder="House, unit or lot number and street">
                                    @error('street_address')<p class="seller-field__error">{{ $message }}</p>@enderror
                                </div>
                                <div class="seller-field">
                                    <label for="hub-postal">Postal code</label>
                                    <input id="hub-postal" name="postal_code" value="{{ old('postal_code', $provider->postal_code) }}" inputmode="numeric" maxlength="4" pattern="[0-9]{4}">
                                </div>
                            </div>
                        </div>

                        <fieldset class="map-pin seller-field seller-field--full" data-map-pin data-marker-icon="hub" data-pin-title="Hub sorting location">
                            <legend>Exact Hub Location on Map <span>(optional)</span></legend>
                            <p class="seller-field__help">Pin your exact sorting hub or warehouse location on the map. The exact coordinates pin is used to calculate each rider leg's road distance.</p>
                            <div class="zaylo-map" data-map role="application" aria-label="Map for pinning the exact hub location"></div>
                            <div class="map-pin__actions">
                                <button class="text-button" type="button" data-map-locate><i class="fas fa-location-crosshairs" aria-hidden="true"></i> Use my current location</button>
                                <button class="text-button" type="button" data-map-clear><i class="fas fa-xmark" aria-hidden="true"></i> Remove pin</button>
                            </div>
                            <p role="status" aria-live="polite" data-map-status></p>
                            <input type="hidden" name="hub_latitude" value="{{ old('hub_latitude', $provider->hub_latitude) }}">
                            <input type="hidden" name="hub_longitude" value="{{ old('hub_longitude', $provider->hub_longitude) }}">
                            <noscript><p>Turn on JavaScript to pin your location on the map.</p></noscript>
                        </fieldset>
                    </div>
                    <div class="seller-form__actions">
                        <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> Save company details</button>
                    </div>
                </form>
            </section>

            @if(\Illuminate\Support\Facades\Schema::hasColumn('logistics_providers', 'waybill_layout'))
                {{-- Every label this partner prints uses its own design (App\Support\Waybill::layoutFor). --}}
                @php($currentLayout = \App\Support\Waybill::layoutFor($provider))
                <section class="seller-panel" aria-labelledby="waybill-heading">
                    <header class="seller-panel__header">
                        <div>
                            <h2 id="waybill-heading">Waybill design</h2>
                            <p>Every waybill for parcels assigned to your company prints in this design, whether the seller or your hub prints it. Riders scan the same barcode or QR code on all of them.</p>
                        </div>
                    </header>
                    <form method="POST" action="{{ route('logistics.account.waybill') }}" class="seller-panel__body seller-form">
                        @csrf
                        @method('PATCH')
                        <fieldset class="waybill-designs">
                            <legend class="sr-only">Waybill design</legend>
                            @foreach(\App\Support\Waybill::LAYOUT_LABELS as $layout => $label)
                                @php($logoFile = ['jnt_express' => 'images/couriers/jnt-express.png', 'flash_express' => 'images/couriers/flash-express.png'][$layout] ?? null)
                                <label class="waybill-design{{ $currentLayout === $layout ? ' is-current' : '' }}">
                                    <input type="radio" name="waybill_layout" value="{{ $layout }}" @checked(old('waybill_layout', $currentLayout) === $layout)>
                                    <span class="waybill-design__body">
                                        <span style="display:inline-flex; align-items:center; gap:8px;">
                                            <strong>{{ $label }}</strong>
                                            @if($logoFile && file_exists(public_path($logoFile)))
                                                <img src="{{ asset($logoFile) }}" alt="{{ $label }}" style="height:15px; max-width:70px; object-fit:contain; vertical-align:middle;">
                                            @endif
                                        </span>
                                        <span>{{ ['zaylo_express' => 'ZAYLO label: tracking and QR on top, deliver-to block, COD box.', 'jnt_express' => 'Like J&T: service box, big 3-part sort code, receiver over sender, tear-off copy.', 'flash_express' => 'Like Flash Express: big barcode first, black DST code band, to/from side by side, tear-off copy.'][$layout] }}</span>
                                        @if($currentLayout === $layout)<em>In use</em>@endif
                                    </span>
                                    <a href="{{ route('logistics.waybill.preview', ['layout' => $layout]) }}" target="_blank" rel="noopener" class="workspace-link">Preview<span class="sr-only"> {{ $label }} (opens in a new tab)</span></a>
                                </label>
                            @endforeach
                        </fieldset>
                        @error('waybill_layout')<p class="seller-field__error">{{ $message }}</p>@enderror
                        <div class="seller-form__actions">
                            <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> Use this design</button>
                        </div>
                    </form>
                </section>
            @endif

            @if(\Illuminate\Support\Facades\Schema::hasColumn('logistics_providers', 'rider_rate_minor'))
                <section class="seller-panel" aria-labelledby="rider-pay-heading">
                    <header class="seller-panel__header">
                        <div><h2 id="rider-pay-heading">Rider earnings policy</h2><p>New scanned parcels use each leg's road distance and parcel weight. Saved quotes do not change when rates change.</p></div>
                    </header>
                    <div class="seller-panel__body"><p>Per leg: PHP {{ number_format(config('rider_fees.base_per_leg'), 2) }} + PHP {{ number_format(config('rider_fees.per_km'), 2) }}/km + PHP {{ number_format(config('rider_fees.per_kg'), 2) }}/kg. Combined pickup and delivery earnings are capped at {{ config('rider_fees.shipping_budget_percent') }}% of funded shipping.</p><p>Platform rates are configured by the administrator. Historical parcels retain their saved earnings.</p></div>
                </section>
            @endif

            @include('partials.account-settings-forms', ['user' => $user, 'routePrefix' => 'logistics.account'])
        </div>

        <div class="workspace-column">
            <section class="seller-panel" aria-labelledby="summary-heading">
                <header class="seller-panel__header"><h2 id="summary-heading">Partner summary</h2></header>
                <dl class="seller-panel__body workspace-detail-list">
                    <div><dt>Status</dt><dd>{{ Str::headline($provider->status) }}</dd></div>
                    <div><dt>Approved on</dt><dd>{{ $provider->approved_at?->timezone('Asia/Manila')->format('F j, Y') ?? '—' }}</dd></div>
                    <div><dt>Riders</dt><dd>{{ $provider->riders()->where('status', 'approved')->count() }} active</dd></div>
                </dl>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/address-selector.js') }}?v={{ filemtime(public_path('js/address-selector.js')) }}" defer></script>
    <script src="{{ asset('js/map-pin.js') }}?v={{ filemtime(public_path('js/map-pin.js')) }}" defer></script>
@endpush
