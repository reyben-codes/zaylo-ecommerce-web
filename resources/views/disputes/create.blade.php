@extends($layout)

@section('title', 'Report a problem')

@push('styles')
    @include('disputes.partials.styles', ['layout' => $layout])
@endpush

@section('content')
    <div class="case-page">
        <section class="seller-page-heading">
            <div>
                <p class="seller-eyebrow"><a href="{{ route('buyer.orders') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> My Orders</a></p>
                <h1>Report a problem</h1>
                <p>Order {{ $order->order_number }}. Tell us what went wrong and attach photos if you have them. The ZAYLO team reviews every complaint with the seller and the courier.</p>
            </div>
        </section>

        <section class="seller-panel">
            <form method="POST" action="{{ route('buyer.disputes.store', $order) }}" enctype="multipart/form-data" class="admin-form case-form">
                @csrf
                <fieldset class="case-choices">
                    <legend class="seller-field__label">What went wrong?</legend>
                    @foreach($categories as $key => $label)
                        <label class="case-choice">
                            <input type="radio" name="category" value="{{ $key }}" @checked(old('category') === $key) required>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    @error('category')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                <div class="seller-field">
                    <label class="seller-field__label" for="details">Details</label>
                    <textarea id="details" name="details" rows="6" minlength="20" maxlength="2000" required placeholder="What happened, which item, and what you would like: a replacement, a refund, or something else.">{{ old('details') }}</textarea>
                    @error('details')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="seller-field">
                    <label class="seller-field__label" for="evidence">Photos or documents (optional)</label>
                    <input type="file" id="evidence" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                    <span class="seller-cell-note">Up to {{ \App\Models\Dispute::MAX_EVIDENCE }} files, JPG, PNG, WebP or PDF, 5 MB each. Only the ZAYLO team, the seller and the courier can see them.</span>
                    @error('evidence')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    @error('evidence.*')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </div>

                <fieldset class="case-choices">
                    <legend class="seller-field__label">Preferred Solution</legend>
                    <label class="case-choice">
                        <input type="radio" name="return_type" value="return_refund" @checked(old('return_type', 'return_refund') === 'return_refund') required>
                        <span><strong>Return &amp; Refund</strong> &middot; Courier collects item, full refund released once received</span>
                    </label>
                    <label class="case-choice">
                        <input type="radio" name="return_type" value="refund_only" @checked(old('return_type') === 'refund_only')>
                        <span><strong>Refund Only</strong> &middot; Request refund without returning item (e.g. missing parcel, spoiled perishable)</span>
                    </label>
                    <label class="case-choice">
                        <input type="radio" name="return_type" value="exchange" @checked(old('return_type') === 'exchange')>
                        <span><strong>Exchange / Replacement</strong> &middot; Swap for another size, color, or replacement unit</span>
                    </label>
                    @error('return_type')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="case-choices">
                    <legend class="seller-field__label">Return Method</legend>
                    <label class="case-choice">
                        <input type="radio" name="return_method" value="pickup" @checked(old('return_method', 'pickup') === 'pickup') required>
                        <span><i class="fas fa-truck-pickup" aria-hidden="true"></i> <strong>Pick up at Home</strong> &middot; A delivery truck courier picks up from your doorstep</span>
                    </label>
                    <label class="case-choice">
                        <input type="radio" name="return_method" value="dropoff" @checked(old('return_method') === 'dropoff')>
                        <span><i class="fas fa-warehouse" aria-hidden="true"></i> <strong>Drop off at Hub</strong> &middot; Bring package to the nearest courier drop-off branch</span>
                    </label>
                    @error('return_method')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                <div class="return-method-section" data-return-pickup-section>
                    <div class="seller-field">
                        <label class="seller-field__label">Choose Delivery Truck Courier to Pick Up at Home</label>
                        <div class="case-choices">
                            <label class="case-choice">
                                <input type="radio" name="return_courier" value="zaylo_express" @checked(old('return_courier', 'zaylo_express') === 'zaylo_express')>
                                <span><i class="fas fa-truck-fast" aria-hidden="true"></i> <strong>ZAYLO Express Truck</strong> &middot; Door-to-door home pickup &amp; instant barcode scan</span>
                            </label>
                            <label class="case-choice">
                                <input type="radio" name="return_courier" value="jnt_express" @checked(old('return_courier') === 'jnt_express')>
                                <span><i class="fas fa-truck" aria-hidden="true"></i> <strong>J&amp;T Express Truck</strong> &middot; Door-to-door home pickup &amp; handheld scan</span>
                            </label>
                            <label class="case-choice">
                                <input type="radio" name="return_courier" value="flash_express" @checked(old('return_courier') === 'flash_express')>
                                <span><i class="fas fa-bolt" aria-hidden="true"></i> <strong>Flash Express Truck</strong> &middot; Door-to-door home pickup &amp; live tracking</span>
                            </label>
                        </div>
                        @error('return_courier')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                        <div class="seller-field">
                            <label class="seller-field__label" for="pickup_date">Pickup Schedule Date</label>
                            <input type="date" id="pickup_date" name="pickup_date" value="{{ old('pickup_date', today()->addDay()->toDateString()) }}" min="{{ today()->toDateString() }}">
                            @error('pickup_date')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div class="seller-field">
                            <label class="seller-field__label" for="pickup_slot">Time Slot</label>
                            <select id="pickup_slot" name="pickup_slot">
                                <option value="morning" @selected(old('pickup_slot', 'morning') === 'morning')>Morning (9:00 AM &ndash; 1:00 PM)</option>
                                <option value="afternoon" @selected(old('pickup_slot') === 'afternoon')>Afternoon (1:00 PM &ndash; 6:00 PM)</option>
                            </select>
                            @error('pickup_slot')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="return-waiting-time" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; margin-top:8px; font-size:.85rem; color:#166534;">
                        <p style="margin:0 0 4px; font-weight:600;"><i class="fas fa-clock" aria-hidden="true"></i> Doorstep Pickup Waiting Time &amp; Process</p>
                        <p style="margin:0;">The courier delivery truck will arrive at your home address (<strong>{{ $order->formattedShippingAddress() }}</strong>) during your scheduled slot. When the driver arrives, they will scan your return tracking barcode directly from your phone.</p>
                    </div>
                </div>

                <div class="return-method-section" data-return-dropoff-section hidden>
                    <div class="seller-field">
                        <label class="seller-field__label" for="dropoff_location">Drop-off Hub Partner</label>
                        <select id="dropoff_location" name="dropoff_location">
                            <option value="ZAYLO Central Sorting Hub" @selected(old('dropoff_location') === 'ZAYLO Central Sorting Hub')>ZAYLO Central Sorting Hub</option>
                            <option value="J&T Express Drop-off Branch" @selected(old('dropoff_location') === 'J&T Express Drop-off Branch')>J&amp;T Express Drop-off Branch (Nationwide)</option>
                            <option value="Flash Express Drop Point" @selected(old('dropoff_location') === 'Flash Express Drop Point')>Flash Express Drop Point (Nationwide)</option>
                        </select>
                        @error('dropoff_location')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="return-waiting-time" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-top:8px; font-size:.85rem; color:#1e40af;">
                        <p style="margin:0 0 4px; font-weight:600;"><i class="fas fa-calendar-check" aria-hidden="true"></i> Drop-off Process &amp; Deadline</p>
                        <p style="margin:0;">You have <strong>7 days</strong> to drop off the package at your chosen branch. Present your return QR/barcode to the hub agent to scan and receive a drop-off receipt.</p>
                    </div>
                </div>

                <div class="case-order">
                    <strong>Items in this order</strong>
                    <ul>
                        @foreach($order->items as $item)
                            <li>{{ $item->quantity }} × {{ $item->product_name }}</li>
                        @endforeach
                    </ul>
                </div>

                <button type="submit" class="seller-button"><i class="fas fa-flag" aria-hidden="true"></i> Submit return / refund request</button>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/return-options.js') }}?v={{ filemtime(public_path('js/return-options.js')) }}" defer></script>
@endpush
