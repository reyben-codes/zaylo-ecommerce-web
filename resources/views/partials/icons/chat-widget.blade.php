<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($icon)
        @case('expand')
            <path d="M14 4h6v6M20 4l-7 7M10 20H4v-6M4 20l7-7" />
            @break
        @case('minimize')
            <path d="M5 12h14" />
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @default
            <path d="M7 4h10a4 4 0 0 1 4 4v7a4 4 0 0 1-4 4H8l-5 3V8a4 4 0 0 1 4-4Z" />
            <path d="M8 11h.01M12 11h.01M16 11h.01" stroke-width="3" />
    @endswitch
</svg>
