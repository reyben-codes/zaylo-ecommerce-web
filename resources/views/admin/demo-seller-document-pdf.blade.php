{{-- Mock application document for a demo shop: plainly marked, with no government seals or layouts. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mock {{ $title }} · {{ $shop }}</title>
    <link rel="stylesheet" href="{{ public_path('css/demo-seller-document-pdf.css') }}">
</head>
<body>
    <div class="mock-watermark">SAMPLE - DEMO ONLY</div>
    <p class="mock-banner">SAMPLE: DEMO ONLY, NOT A GOVERNMENT DOCUMENT</p>
    <h1>Mock {{ $title }}</h1>
    <p class="mock-note">Made for the ZAYLO demo to show the seller review step. Not issued by any agency and not valid for any purpose.</p>
    <table class="mock-details">
        @foreach($details as $label => $value)
            <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>
    <p class="mock-banner">SAMPLE: DEMO ONLY, NOT A GOVERNMENT DOCUMENT</p>
</body>
</html>
