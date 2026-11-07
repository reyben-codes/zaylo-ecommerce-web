{{-- Waybill label (4 × 6 in). Rendered to PDF by dompdf (files read from disk), or as a print page in the browser when
     $web is set (then $pdfUrl links to the PDF). Expects $waybill. --}}
@php
    $web = $web ?? false;
    $asset = fn (string $path) => $web ? asset($path) : public_path($path);
    $placed = $waybill['placed'] ? \Illuminate\Support\Carbon::parse($waybill['placed'])->timezone('Asia/Manila') : null;
    $weight = $waybill['weight'] === null ? null
        : ($waybill['weight'] >= 1000 ? number_format($waybill['weight'] / 1000, 2).' kg' : $waybill['weight'].' g');
    $shownItems = $waybill['items']->take(4);
    $moreItems = $waybill['items']->count() - $shownItems->count();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waybill {{ $waybill['tracking'] }}</title>
    <link rel="stylesheet" href="{{ $asset('css/waybill-pdf.css') }}{{ $web ? '?v='.filemtime(public_path('css/waybill-pdf.css')) : '' }}">
    @if($web)
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <script src="{{ asset('js/print.js') }}?v={{ filemtime(public_path('js/print.js')) }}" defer></script>
    @endif
</head>
<body class="wb-courier-{{ str_replace('_', '-', $waybill['courier']) }}{{ $web ? ' wb-web' : '' }}" @if($web) data-auto-print @endif>
    @if($web)
        <nav class="wb-toolbar" aria-label="Waybill actions">
            <button type="button" class="wb-toolbar__button wb-toolbar__button--primary" data-print hidden><i class="fas fa-print" aria-hidden="true"></i> Print label</button>
            <a href="{{ $pdfUrl }}" class="wb-toolbar__button"><i class="fas fa-file-pdf" aria-hidden="true"></i> Save as PDF</a>
            <span class="wb-toolbar__hint">Prints on a 4 × 6 in label. On regular paper, choose "Fit to page".</span>
        </nav>
        <div class="wb-sheet">
    @endif
    {{-- One layout per courier (App\Support\Waybill::LAYOUTS), chosen from the order's courier. --}}
    @include('waybill.layouts.'.$waybill['courier'], ['waybill' => $waybill, 'asset' => $asset, 'placed' => $placed, 'weight' => $weight, 'shownItems' => $shownItems, 'moreItems' => $moreItems])

    <p class="wb-foot">Printed {{ $waybill['printed']->format('M j, Y g:i A') }} · Keep this label visible on the parcel.</p>
    @if($web)
        </div>
    @endif
</body>
</html>
