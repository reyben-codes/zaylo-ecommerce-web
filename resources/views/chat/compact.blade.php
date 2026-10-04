<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZAYLO · Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/messaging.css') }}?v={{ filemtime(public_path('css/messaging.css')) }}">
</head>
<body class="messaging-page messaging-compact {{ $conversation ? 'has-thread' : '' }}">
    @if($conversation)
        <a class="messaging-back" href="{{ route($sellerView ? 'seller.chat' : 'buyer.chat', ['compact' => 1]) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> All conversations</a>
    @endif
    @include('partials.messaging', ['compact' => true])
    <script src="{{ asset('js/messaging.js') }}?v={{ filemtime(public_path('js/messaging.js')) }}" defer></script>
</body>
</html>
