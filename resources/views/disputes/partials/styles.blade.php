{{-- Stylesheets for complaint pages. Buyer and rider pages use the storefront layout, so they also load the workspace components. --}}
@php $sheets = $layout === 'layouts.seller' ? ['disputes'] : ['seller-sidebar', 'seller-workspace', 'disputes']; @endphp
@foreach($sheets as $sheet)
    <link rel="stylesheet" href="{{ asset("css/{$sheet}.css") }}?v={{ filemtime(public_path("css/{$sheet}.css")) }}">
@endforeach
