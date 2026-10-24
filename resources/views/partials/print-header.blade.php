{{-- Shown only on paper above a printed report (styled in print.css): what was printed, when and by whom. Expects $title. --}}
<div class="print-only print-header" hidden>
    <strong class="print-header__brand">ZAYLO</strong>
    <span class="print-header__meta">{{ $title }} · printed {{ now('Asia/Manila')->format('M j, Y g:i A') }} by {{ auth()->user()?->name }}</span>
</div>
