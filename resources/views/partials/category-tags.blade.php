{{-- A product's categories as tags, main category first. Expects $product; optional $href (key => URL) makes each tag a link. --}}
<ul class="category-tags" aria-label="Categories">
    @foreach($product->category_tags as $key => $label)
        <li>
            @if(isset($href) && array_key_exists($key, config('marketplace.categories')))
                <a class="category-tag" href="{{ $href($key) }}">{{ $label }}</a>
            @else
                <span class="category-tag">{{ $label }}</span>
            @endif
        </li>
    @endforeach
</ul>
