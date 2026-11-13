{{-- Rider choices nearest the parcel first (App\Services\RiderProximity). Expects $riders, optional $area and $pin. --}}
@php($localRiders = app(\App\Services\RiderProximity::class)->serving($riders, $area ?? null))
@if($localRiders->isEmpty())<option value="" disabled>No available riders in this location</option>@endif
@foreach(app(\App\Services\RiderProximity::class)->rank($localRiders, $area ?? null, $pin ?? null) as $choice)
    <option value="{{ $choice['rider']->id }}">{{ $choice['rider']->user?->name }} · {{ $choice['label'] }}</option>
@endforeach
