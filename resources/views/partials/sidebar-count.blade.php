{{-- A sidebar link's counter: how many things wait in that section. seller-live-updates.js keeps it current. --}}
@if($key && isset($sidebarCounts[$key]))
    @php [$total, $meaning] = $sidebarCounts[$key]; @endphp
    <span class="seller-sidebar__badge" data-sidebar-count="{{ $key }}" data-sidebar-count-label="{{ $meaning }}" @if($total < 1) hidden @endif>@if($total > 0){{ $total > 99 ? '99+' : $total }}<span class="sr-only"> {{ $meaning }}</span>@endif</span>
@endif
