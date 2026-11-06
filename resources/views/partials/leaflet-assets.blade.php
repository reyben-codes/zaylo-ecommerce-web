{{-- Leaflet with OpenStreetMap tiles, and the shared map helpers. Scripts are deferred, so they run in this order. --}}
@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/maps.css') }}?v={{ filemtime(public_path('css/maps.css')) }}">
@endpush
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous" defer></script>
    <script src="{{ asset('js/zaylo-map.js') }}?v={{ filemtime(public_path('js/zaylo-map.js')) }}" defer></script>
@endpush
