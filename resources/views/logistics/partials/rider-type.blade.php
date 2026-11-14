@if($rider->user?->hasRole('rider') && in_array($rider->status, ['pending', 'approved', 'suspended'], true))
    @php $typeErrors = $errors->getBag('riderType'.$rider->id); @endphp
    <form method="POST" action="{{ route('logistics.riders.type', $rider) }}" class="rider-type-form">
        @csrf
        @method('PATCH')
        <fieldset class="rider-type-options" @if($typeErrors->has('rider_type')) aria-describedby="rider-type-error-{{ $rider->id }}" @endif>
            <legend>Assign {{ $rider->user->name }} to</legend>
            @foreach(\App\Models\Rider::TYPE_LABELS as $value => $label)
                <label class="rider-type-option">
                    <input type="radio" name="rider_type" value="{{ $value }}" @checked(($rider->rider_type ?? 'delivery') === $value) @if($typeErrors->has('rider_type')) aria-invalid="true" @endif>
                    <span class="rider-type-option__card">
                        <i class="fas {{ $value === 'pickup' ? 'fa-box' : 'fa-truck' }} rider-type-option__icon" aria-hidden="true"></i>
                        <span class="rider-type-option__text">
                            <strong>{{ $label }}</strong>
                            <span>{{ $value === 'pickup' ? 'Collect parcels from sellers' : 'Deliver parcels to buyers' }}</span>
                        </span>
                    </span>
                </label>
            @endforeach
        </fieldset>
        @if($typeErrors->has('rider_type'))
            <p id="rider-type-error-{{ $rider->id }}" class="seller-field__error" role="alert">{{ $typeErrors->first('rider_type') }}</p>
        @endif
        <button type="submit" class="seller-button seller-button--small seller-button--secondary">Save assignment type</button>
    </form>
@else
    <span>{{ \App\Models\Rider::TYPE_LABELS[$rider->isPickupRider() ? 'pickup' : 'delivery'] }}</span>
@endif
