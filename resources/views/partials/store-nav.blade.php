<nav class="nav-links" aria-label="Store navigation">
    <a href="{{ route(auth()->user()?->role === 'buyer' ? 'buyer.dashboard' : 'home') }}" class="{{ request()->routeIs('home', 'buyer.dashboard') ? 'active-link' : '' }}">Home</a>
    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.index') && ! request('category') && ! request('gender') ? 'active-link' : '' }}">Shop All</a>
    @foreach(array_slice(config('marketplace.browse_categories'), 0, 4, true) as $category => $details)
        <a href="{{ route('products.index', ['category' => $category]) }}" class="{{ request('category') === $category ? 'active-link' : '' }}">{{ $details['label'] }}</a>
    @endforeach
    <a href="{{ route('products.index', ['gender' => 'kids']) }}" class="{{ in_array(request('gender'), ['kids', 'girls', 'boys'], true) ? 'active-link' : '' }}">Kids</a>
</nav>
