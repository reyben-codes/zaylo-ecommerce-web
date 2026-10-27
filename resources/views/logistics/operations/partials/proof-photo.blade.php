{{-- A proof photo (delivery or return) as a small thumbnail that opens the full photo. Expects $url, $label, $alt. --}}
<a href="{{ $url }}" class="proof-photo" target="_blank" rel="noopener" aria-label="{{ $label }} (opens in a new tab)">
    <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" width="56" height="56">
    <span><i class="fas fa-camera" aria-hidden="true"></i> {{ $label }}</span>
</a>
