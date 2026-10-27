{{--
    Zay, the help bot's character: a round face whose eyes blink, follow the pointer and change with its mood
    (public/js/faq-bot.js sets data-mood: idle, thinking, happy, confused, waving). Expects $size in pixels.
--}}
<svg class="zay" data-zay data-mood="idle" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
    <ellipse class="zay__shadow" cx="32" cy="61" rx="15" ry="2.2"/>
    <g class="zay__body">
        <path class="zay__antenna" d="M32 10 V4"/>
        <circle class="zay__antenna-tip" cx="32" cy="4" r="2.6"/>
        <rect class="zay__head" x="6" y="10" width="52" height="46" rx="21"/>
        <rect class="zay__face" x="12" y="18" width="40" height="30" rx="14"/>
        @foreach([23, 41] as $eyeX)
            <g transform="translate({{ $eyeX }} 31)">
                <g class="zay__eye">
                    <ellipse class="zay__eye-white" rx="5.6" ry="6.4"/>
                    <g class="zay__look">
                        <circle class="zay__pupil" r="2.9"/>
                        <circle class="zay__glint" cx="1.1" cy="-1.4" r="1"/>
                    </g>
                </g>
            </g>
        @endforeach
        <circle class="zay__cheek" cx="16.5" cy="41" r="2.6"/>
        <circle class="zay__cheek" cx="47.5" cy="41" r="2.6"/>
        <path class="zay__mouth zay__mouth--idle" d="M27 41 Q32 45 37 41"/>
        <path class="zay__mouth zay__mouth--happy" d="M25.5 40 Q32 48 38.5 40 Z"/>
        <path class="zay__mouth zay__mouth--thinking" d="M28 43 H36"/>
        <path class="zay__mouth zay__mouth--confused" d="M27 43.5 Q30 41 32.5 43 T38 42"/>
        <rect class="zay__hand" x="53" y="33" width="8" height="12" rx="4"/>
    </g>
</svg>
