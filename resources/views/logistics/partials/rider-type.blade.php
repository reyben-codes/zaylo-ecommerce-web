@if($rider->user?->hasRole('rider') && in_array($rider->status, ['pending', 'approved', 'suspended'], true))
    @php $typeErrors = $errors->getBag('riderType'.$rider->id); @endphp
    <form method="POST" action="{{ route('logistics.riders.type', $rider) }}" class="review-reason__form">
        @csrf
        @method('PATCH')
        <label for="rider-type-{{ $rider->id }}">Assign {{ $rider->user->name }} to</label>
        <select id="rider-type-{{ $rider->id }}" name="rider_type" @if($typeErrors->has('rider_type')) aria-invalid="true" aria-describedby="rider-type-error-{{ $rider->id }}" @endif>
            @foreach(\App\Models\Rider::TYPE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(($rider->rider_type ?? 'delivery') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if($typeErrors->has('rider_type'))
            <p id="rider-type-error-{{ $rider->id }}" role="alert">{{ $typeErrors->first('rider_type') }}</p>
        @endif
        <button type="submit" class="seller-button seller-button--small seller-button--secondary">Save assignment type</button>
    </form>
@else
    <span>{{ \App\Models\Rider::TYPE_LABELS[$rider->isPickupRider() ? 'pickup' : 'delivery'] }}</span>
@endif
