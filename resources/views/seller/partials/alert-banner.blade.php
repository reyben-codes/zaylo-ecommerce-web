{{-- Unread warnings about the seller's account or products (policy warnings, rejected listings, sold-out products). --}}
@if($sidebarUrgentNotifications->isNotEmpty() && ! request()->routeIs('seller.notifications'))
    @php $first = $sidebarUrgentNotifications->first(); $more = $sidebarUrgentNotifications->count() - 1; @endphp
    <aside class="seller-alert-banner" role="status">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <p><strong>{{ $first['title'] }}</strong> · {{ Str::limit($first['message'], 120) }}@if($more > 0) <span>and {{ $more }} more {{ Str::plural('alert', $more) }}</span>@endif</p>
        <a href="{{ route('seller.notifications') }}">View alerts <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </aside>
@endif
