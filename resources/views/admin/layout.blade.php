<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAYLO Admin - @yield('title', 'Workspace')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
    @stack('styles')
</head>
<body>
    <div class="container">
        @include('admin.partials.header', ['searchId' => 'globalSearch', 'searchPlaceholder' => 'Search dashboard...'])
        <div class="dashboard-wrapper">
            @include('admin.partials.sidebar')
            <main class="main-content">@yield('content')</main>
        </div>
    </div>
    <div id="adminShellToast" class="admin-shell-toast" role="status" hidden></div>
    <script src="{{ asset('js/admin-shell.js') }}"></script>
    @stack('scripts')
</body>
</html>
