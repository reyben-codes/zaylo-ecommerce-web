{{-- From/to date filter. Expects $action (URL), $range (from, to), optional $keep (hidden query values) and
     optional $sellers (id => name) with the selected $seller (['id', 'name'] or null) to filter by shop. --}}
<form method="GET" action="{{ $action }}" class="seller-filter-bar admin-range">
    @foreach($keep ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="seller-field">
        <label class="seller-field__label" for="range-from">From</label>
        <input type="date" id="range-from" name="from" value="{{ $range['from'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
    </div>
    <div class="seller-field">
        <label class="seller-field__label" for="range-to">To</label>
        <input type="date" id="range-to" name="to" value="{{ $range['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
    </div>
    @isset($sellers)
        <div class="seller-field">
            <label class="seller-field__label" for="range-seller">Seller</label>
            <select id="range-seller" name="seller">
                <option value="">All sellers</option>
                @foreach($sellers as $id => $name)
                    <option value="{{ $id }}" @selected(($seller['id'] ?? null) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <button type="submit" class="seller-button seller-button--small"><i class="fas fa-calendar-days" aria-hidden="true"></i> Apply</button>
    @error('from')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
</form>
