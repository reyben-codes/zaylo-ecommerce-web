{{-- Colors & sizes editor. Each color keeps its own sizes, each with its own price and stock; colors can be named, renamed and added.
     Expects $product, $variants, $multiVariant, $stockState. --}}
@php
    $presets = config('marketplace.size_presets')[$product->category_key] ?? [];
    // Products without options yet start with an empty list so the seller can give them sizes.
    $groups = $multiVariant ? $variants->groupBy(fn ($variant) => $variant->color_label ?? '') : collect(['' => collect()]);
    $newIndex = 0;
    $hasSizes = $variants->contains(fn ($variant) => $variant->size_label !== null);
    $named = $groups->keys()->filter()->isNotEmpty();
    // A color's card shows its photo ("<product> — <color>" alt text) or a swatch for common color words.
    $photoFor = fn (string $color) => $color === '' ? null
        : $product->images->first(fn ($image) => Str::endsWith((string) $image->alt_text, ' — '.$color))?->path;
    // With several colors only the first starts open; a single list is always open.
    $openFirst = $groups->count() > 1;
@endphp

<section class="seller-panel" aria-labelledby="variation-heading">
    <header class="seller-panel__header">
        <div>
            <h2 id="variation-heading">Colors &amp; sizes</h2>
            <p>
                @if($multiVariant)
                    {{ $stockState['total'] }} in stock across {{ $variants->count() }} {{ Str::plural('option', $variants->count()) }}. Options at 0 show as "Sold out" to buyers.
                @else
                    Label this product with colors, or sell it in sizes like shoe or shirt sizes. Each option gets its own price and stock.
                @endif
            </p>
        </div>
    </header>
    <form method="POST" action="{{ route('seller.products.stock', $product) }}" class="seller-panel__body product-form__variations" data-size-editor>
        @csrf
        @method('PATCH')
        @if($groups->count() > 1)
            <div class="size-groups__toolbar">
                <span>{{ $groups->count() }} colors</span>
                <button type="button" class="size-groups__toggle" data-size-groups-toggle hidden aria-label="Expand all colors">Expand all</button>
            </div>
        @endif
        @foreach($groups as $color => $group)
            @php
                $defaultPrice = number_format((float) ($group->first()?->regular_price ?? $product->regular_price), 2, '.', '');
                $groupStock = (int) $group->sum('stock');
                $low = $group->contains(fn ($variant) => $variant->stock > 0 && $variant->stock <= $product->low_stock_threshold);
                $groupState = $group->isEmpty() ? null : ($groupStock <= 0 ? ['out', 'Sold out'] : ($low ? ['low', 'Low stock'] : ['in', 'In stock']));
                $photo = $photoFor((string) $color);
                $swatch = $photo || $color === '' ? null : \App\Models\ProductVariant::swatchFor((string) $color);
                $sizeCount = $group->filter(fn ($variant) => $variant->size_label !== null)->count();
            @endphp
            <details class="size-group" data-color-group data-smooth-disclosure @if(! $openFirst || $loop->first) open @endif>
                <summary class="size-group__summary">
                    @if($photo)
                        <img src="{{ $photo }}" alt="" class="size-group__photo" loading="lazy">
                    @elseif($swatch)
                        <span class="size-group__swatch" style="--swatch: {{ $swatch }}" aria-hidden="true"></span>
                    @else
                        <span class="size-group__swatch size-group__swatch--plain" aria-hidden="true"><i class="fas fa-ruler" aria-hidden="true"></i></span>
                    @endif
                    <span class="size-group__title">
                        <strong>{{ $color !== '' ? $color : ($named ? 'Other' : 'Sizes') }}</strong>
                        <small>
                            @if($group->isEmpty())
                                No sizes yet
                            @else
                                {{ $sizeCount ? $sizeCount.' '.Str::plural('size', $sizeCount) : 'No sizes' }} · {{ $groupStock }} in stock
                            @endif
                        </small>
                    </span>
                    @if($groupState)
                        <span class="size-group__state size-group__state--{{ $groupState[0] }}">{{ $groupState[1] }}</span>
                    @endif
                    <i class="fas fa-chevron-down size-group__chevron" aria-hidden="true"></i>
                </summary>
                <div class="size-group__body" data-disclosure-content>
                <div class="size-group__color">
                    <input type="hidden" name="colors[{{ $loop->index }}][from]" value="{{ $color }}">
                    <label for="color-name-{{ $loop->index }}">{{ $color !== '' ? 'Color name' : 'Color' }}</label>
                    <input type="text" id="color-name-{{ $loop->index }}" name="colors[{{ $loop->index }}][to]" value="{{ old('colors.'.$loop->index.'.to', $color) }}"
                           maxlength="40" placeholder="{{ $color !== '' ? 'Color name' : 'No color yet, e.g. Black' }}" list="color-presets" autocomplete="off">
                </div>
                <div class="product-form__variation-head" aria-hidden="true"><span>Size</span><span>Price (&#8369;)</span><span>Stock</span><span></span></div>
                @foreach($group as $variant)
                    @php $label = $variant->name; @endphp
                    <div class="product-form__variation">
                        <span class="product-form__variation-name">
                            <label class="sr-only" for="variant-size-{{ $variant->id }}">Size for {{ $label }}</label>
                            <input type="text" id="variant-size-{{ $variant->id }}" name="sizes[{{ $variant->id }}]" value="{{ old('sizes.'.$variant->id, $variant->size_label) }}"
                                   maxlength="40" placeholder="{{ $hasSizes || $group->count() > 1 ? 'Size' : 'No size' }}" @if($presets) list="size-presets" @endif autocomplete="off">
                            <small title="{{ $variant->sku }}">{{ $variant->sku }}</small>
                            @if($variant->stock <= 0)
                                <em class="is-out" title="Sold out"><span aria-hidden="true">Out</span><span class="sr-only">Sold out</span></em>
                            @elseif($variant->stock <= $product->low_stock_threshold)
                                <em title="Low stock"><span aria-hidden="true">Low</span><span class="sr-only">Low stock</span></em>
                            @endif
                        </span>
                        <label class="sr-only" for="variant-price-{{ $variant->id }}">Price for {{ $label }}</label>
                        <input type="number" id="variant-price-{{ $variant->id }}" name="prices[{{ $variant->id }}]" value="{{ number_format($variant->regular_price, 2, '.', '') }}" min="0" max="99999999.99" step="0.01" required>
                        <label class="sr-only" for="variant-stock-{{ $variant->id }}">Stock for {{ $label }}</label>
                        <input type="number" id="variant-stock-{{ $variant->id }}" name="variants[{{ $variant->id }}]" value="{{ $variant->stock }}" min="0" max="1000000" required>
                        <label class="product-form__remove" title="Remove {{ $label }}">
                            <input type="checkbox" name="remove[]" value="{{ $variant->id }}">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                            <span class="sr-only">Remove {{ $label }}</span>
                        </label>
                    </div>
                @endforeach

                {{-- Blank rows are ignored; JavaScript can add more. --}}
                <div class="product-form__variation product-form__variation--new" data-new-size>
                    <span class="product-form__variation-name">
                        <input type="hidden" name="new[{{ $newIndex }}][color]" value="{{ $color }}">
                        <label class="sr-only" for="new-size-{{ $newIndex }}">New size{{ $color !== '' ? ' for '.$color : '' }}</label>
                        <input type="text" id="new-size-{{ $newIndex }}" name="new[{{ $newIndex }}][size]" maxlength="40" placeholder="Add a size" @if($presets) list="size-presets" @endif autocomplete="off">
                    </span>
                    <label class="sr-only" for="new-price-{{ $newIndex }}">Price for the new size</label>
                    <input type="number" id="new-price-{{ $newIndex }}" name="new[{{ $newIndex }}][price]" value="{{ $defaultPrice }}" min="0" max="99999999.99" step="0.01">
                    <label class="sr-only" for="new-stock-{{ $newIndex }}">Stock for the new size</label>
                    <input type="number" id="new-stock-{{ $newIndex }}" name="new[{{ $newIndex }}][stock]" value="0" min="0" max="1000000">
                    <span></span>
                </div>
                @php $newIndex++; @endphp
                <div class="product-form__size-actions" data-size-actions hidden>
                    <button type="button" class="product-form__size-button" data-add-size><i class="fas fa-plus" aria-hidden="true"></i> Add size</button>
                    @if($presets)
                        <button type="button" class="product-form__size-button" data-fill-sizes="{{ json_encode($presets) }}"><i class="fas fa-ruler" aria-hidden="true"></i> Standard sizes</button>
                    @endif
                </div>
                </div>
            </details>
        @endforeach

        {{-- A new color with its own sizes; a color left without sizes is sold as one option. --}}
        <details class="size-group size-group--new" data-new-color data-smooth-disclosure @if(filled(old('added_color')) || $errors->has('added_color')) open @endif>
            <summary class="size-group__summary">
                <span class="size-group__swatch size-group__swatch--plain" aria-hidden="true"><i class="fas fa-plus" aria-hidden="true"></i></span>
                <span class="size-group__title">
                    <strong>Add a color</strong>
                    <small>With its own sizes, price and stock</small>
                </span>
                <span></span>
                <i class="fas fa-chevron-down size-group__chevron" aria-hidden="true"></i>
            </summary>
            <div class="size-group__body" data-disclosure-content>
                <div class="size-group__color">
                    <label for="added-color">Color name</label>
                    <input type="text" id="added-color" name="added_color" value="{{ old('added_color') }}" maxlength="40" placeholder="e.g. Navy" list="color-presets" autocomplete="off"
                           @error('added_color') aria-invalid="true" aria-describedby="added-color-error" @enderror>
                    @error('added_color')<p class="seller-field__error" id="added-color-error">{{ $message }}</p>@enderror
                </div>
                <div class="product-form__variation-head" aria-hidden="true"><span>Size</span><span>Price (&#8369;)</span><span>Stock</span><span></span></div>
                <div class="product-form__variation product-form__variation--new" data-new-size>
                    <span class="product-form__variation-name">
                        <label class="sr-only" for="added-size-0">Size for the new color</label>
                        <input type="text" id="added-size-0" name="added[0][size]" value="{{ old('added.0.size') }}" maxlength="40" placeholder="Size (optional)" @if($presets) list="size-presets" @endif autocomplete="off">
                    </span>
                    <label class="sr-only" for="added-price-0">Price for the new color</label>
                    <input type="number" id="added-price-0" name="added[0][price]" value="{{ old('added.0.price', number_format((float) $product->regular_price, 2, '.', '')) }}" min="0" max="99999999.99" step="0.01">
                    <label class="sr-only" for="added-stock-0">Stock for the new color</label>
                    <input type="number" id="added-stock-0" name="added[0][stock]" value="{{ old('added.0.stock', 0) }}" min="0" max="1000000">
                    <span></span>
                </div>
                <div class="product-form__size-actions" data-size-actions hidden>
                    <button type="button" class="product-form__size-button" data-add-size><i class="fas fa-plus" aria-hidden="true"></i> Add size</button>
                    @if($presets)
                        <button type="button" class="product-form__size-button" data-fill-sizes="{{ json_encode($presets) }}"><i class="fas fa-ruler" aria-hidden="true"></i> Standard sizes</button>
                    @endif
                </div>
            </div>
        </details>

        <datalist id="color-presets">
            @foreach(config('marketplace.color_presets') as $preset)
                <option value="{{ $preset }}"></option>
            @endforeach
        </datalist>
        @if($presets)
            <datalist id="size-presets">
                @foreach($presets as $preset)
                    <option value="{{ $preset }}"></option>
                @endforeach
            </datalist>
        @endif
        @error('variants')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
        @error('colors')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
        @if($errors->has('new.*') || $errors->has('sizes.*') || $errors->has('colors.*') || $errors->has('added.*'))
            <p class="seller-field__error" role="alert">{{ collect($errors->get('new.*'))->merge($errors->get('sizes.*'))->merge($errors->get('colors.*'))->merge($errors->get('added.*'))->flatten()->first() }}</p>
        @endif
        <p class="product-form__size-hint">Tick <i class="fas fa-trash" aria-hidden="true"></i><span class="sr-only">remove</span> to remove a size; remove all of a color's sizes to remove the color. Sizes with past orders are hidden instead of deleted.</p>
        <button type="submit" class="seller-button seller-button--small">Save colors &amp; sizes</button>
    </form>
</section>
