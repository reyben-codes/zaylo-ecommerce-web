<header class="navbar">
    <a href="{{ route('admin.dashboard') }}" class="logo" aria-label="ZAYLO Admin Dashboard">
        <img src="{{ asset('images/logo.png') }}" alt="ZAYLO Logo">
    </a>
    <div class="nav-actions">
        <label class="search-wrapper">
            <i class="fas fa-search search-icon" aria-hidden="true"></i>
            <input type="text" id="{{ $searchId ?? 'globalSearch' }}" placeholder="{{ $searchPlaceholder ?? 'Search dashboard...' }}" aria-label="{{ $searchPlaceholder ?? 'Search dashboard' }}" @if(!empty($searchOnInput)) oninput="{{ $searchOnInput }}" @endif>
        </label>
    </div>
</header>
