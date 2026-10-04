<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZAYLO | Messages</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/messaging.css') }}?v={{ filemtime(public_path('css/messaging.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/seller-header.css') }}?v={{ filemtime(public_path('css/seller-header.css')) }}">
</head>
<body class="seller-workspace messaging-seller messaging-page">
    @include('partials.seller-header')
    <div class="messaging-seller-layout">
        @include('partials.seller-sidebar')
        <main class="messaging-seller-main">
            @include('partials.messaging')
        </main>
    </div>
    <script src="{{ asset('js/messaging.js') }}?v={{ filemtime(public_path('js/messaging.js')) }}" defer></script>
</body>
</html>
