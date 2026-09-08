<nav class="nav-links" aria-label="Store navigation">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active-link' : '' }}">Home</a>
    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.index') && ! request('category') ? 'active-link' : '' }}">Shop All</a>
    @foreach(array_slice(config('marketplace.browse_categories'), 0, 4, true) as $category => $details)
        <a href="{{ route('products.index', ['category' => $category]) }}" class="{{ request('category') === $category ? 'active-link' : '' }}">{{ $details['label'] }}</a>
    @endforeach
</nav>
