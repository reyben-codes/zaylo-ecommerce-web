{{-- Filter options grouped by main category. Expects $selected, the chosen category key or null. --}}
@foreach(config('marketplace.category_tree') as $main => $details)
    <optgroup label="{{ $details['label'] }}">
        <option value="{{ $main }}" @selected($selected === $main)>All {{ $details['label'] }}</option>
        @foreach($details['subcategories'] as $key => $label)
            <option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>
        @endforeach
    </optgroup>
@endforeach
