@php
    $brand = $waybill['carrier'] ?: $waybill['courier_name'];
    $named = \App\Support\Waybill::courierNamed($brand);
    $brandLogo = match ($named) {
        'jnt_express' => 'images/couriers/jnt-express.png',
        'flash_express' => 'images/couriers/flash-express.png',
        default => str_contains(strtolower($brand), 'zaylo') ? 'images/ZAYLO_LOGO_DARK.png' : null,
    };
@endphp
@if($brandLogo && file_exists(public_path($brandLogo)) && extension_loaded('gd'))
    <img src="{{ $asset($brandLogo) }}" alt="{{ $brand }}" class="{{ $logoClass }}">
@else
    <strong class="wb-partner-brand">{{ $brand }}</strong>
@endif
