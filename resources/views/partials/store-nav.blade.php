<nav class="nav-links" aria-label="Store navigation">
    <a href="{{ route(auth()->user()?->role === 'buyer' ? 'buyer.dashboard' : 'home') }}" class="{{ request()->routeIs('home', 'buyer.dashboard') ? 'active-link' : '' }}">Home</a>
    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.index') && ! request('category') && ! request('gender') ? 'active-link' : '' }}">Shop All</a>
    <div class="category-menu" data-category-menu>
        <button type="button" class="category-menu__toggle {{ request('category') ? 'active-link' : '' }}" aria-expanded="false" aria-controls="category-menu-panel" data-category-menu-toggle>
            Categories
            <span class="category-menu__chevron" aria-hidden="true"></span>
        </button>
        <div class="category-menu__panel" id="category-menu-panel">
            <div class="category-menu__clip">
                <div class="category-menu__columns">
                    @foreach(config('marketplace.category_tree') as $main => $details)
                        <div class="category-menu__column">
                            <a class="category-menu__heading" href="{{ route('products.index', ['category' => $main]) }}" @if(request('category') === $main) aria-current="page" @endif>{{ $details['label'] }}</a>
                            <ul>
                                @foreach($details['subcategories'] as $key => $label)
                                    <li><a class="category-menu__link" href="{{ route('products.index', ['category' => $key]) }}" @if(request('category') === $key) aria-current="page" @endif>{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <a href="{{ route('products.index', ['gender' => 'kids']) }}" class="{{ in_array(request('gender'), ['kids', 'girls', 'boys'], true) ? 'active-link' : '' }}">Kids</a>
</nav>
