{{-- Search box for a parcel list. Expects $action (route) and $search; optional $keep (query values to carry along). --}}
<form method="GET" action="{{ $action }}" class="seller-filter-bar" role="search">
    @foreach($keep ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="seller-field seller-field--search">
        <label class="seller-field__label" for="parcel-search">Search</label>
        <input type="search" id="parcel-search" name="search" value="{{ $search }}" placeholder="Tracking or order number" maxlength="100">
    </div>
    <button type="submit" class="seller-button seller-button--small"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Search</button>
    @if($search !== '')
        <a href="{{ $action }}{{ ($keep ?? []) ? '?'.http_build_query($keep) : '' }}" class="seller-button seller-button--small seller-button--secondary">Clear</a>
    @endif
</form>
