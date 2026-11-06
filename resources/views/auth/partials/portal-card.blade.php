{{-- One role card on the sign-in panel. data-portal carries everything the form shows for that role. --}}
<a href="{{ $card['url'] }}" @class(['portal-card', 'is-active' => $active]) data-portal-card="{{ $key }}" @if($active) aria-current="page" @endif
   data-portal="{{ json_encode(collect($card)->except('icon')->all()) }}">
    <span class="portal-card__icon" aria-hidden="true"><i class="fas {{ $card['icon'] }}"></i></span>
    <span class="portal-card__name">{{ $card['name'] }}</span>
</a>
