{{-- Rider choices nearest the parcel first (App\Services\RiderProximity). Expects $riders, optional $area and $pin. --}}
@foreach(app(\App\Services\RiderProximity::class)->rank($riders, $area ?? null, $pin ?? null) as $choice)
    <option value="{{ $choice['rider']->id }}">{{ $choice['rider']->user?->name }} · {{ $choice['label'] }}</option>
@endforeach
