<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcel label {{ $code }} · ZAYLO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/parcel-label.css') }}?v={{ filemtime(public_path('css/parcel-label.css')) }}">
    <script src="{{ asset('js/parcel-label.js') }}?v={{ filemtime(public_path('js/parcel-label.js')) }}" defer></script>
</head>
<body>
    <div class="label-toolbar">
        <a href="{{ route('seller.orders') }}">&larr; Back to orders</a>
        <button type="button" data-print-label>Print label</button>
    </div>

    <main class="parcel-label">
        <header class="parcel-label__header">
            <strong class="parcel-label__brand">ZAYLO</strong>
            <span>Order {{ $order->order_number }}</span>
        </header>

        <section class="parcel-label__scan">
            <div class="parcel-label__qr" role="img" aria-label="QR code for parcel {{ $code }}">{!! $qr !!}</div>
            <div>
                <p class="parcel-label__caption">Rider: scan to start and to confirm delivery</p>
                <p class="parcel-label__code">{{ $code }}</p>
            </div>
        </section>

        <section class="parcel-label__block">
            <p class="parcel-label__caption">Deliver to</p>
            <p class="parcel-label__name">{{ $order->recipient_name }}</p>
            <p>{{ $order->phone }}</p>
            <p>{{ $order->shipping_address }}</p>
        </section>

        <section class="parcel-label__block">
            <p class="parcel-label__caption">From</p>
            <p class="parcel-label__name">{{ $store }}</p>
        </section>

        <section class="parcel-label__block parcel-label__row">
            <div>
                <p class="parcel-label__caption">Items</p>
                <p>{{ $order->items->sum('quantity') }} item(s)</p>
            </div>
            <div>
                <p class="parcel-label__caption">{{ $order->payment_method === 'cod' ? 'Collect (COD)' : 'Payment' }}</p>
                <p class="parcel-label__name">{{ $order->payment_method === 'cod' ? '₱'.number_format($order->total, 2) : 'Prepaid' }}</p>
            </div>
        </section>
    </main>
</body>
</html>
