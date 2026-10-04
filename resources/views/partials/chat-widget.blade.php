@if(auth()->check() && in_array(auth()->user()->role, ['buyer', 'seller'], true) && auth()->user()->isActive() && auth()->user()->hasVerifiedEmail())
@once
    <link rel="stylesheet" href="{{ asset('css/chat-widget.css') }}?v={{ filemtime(public_path('css/chat-widget.css')) }}">
    <div class="chat-widget" data-chat-widget data-chat-url="{{ route(auth()->user()->role.'.chat') }}" data-user-id="{{ auth()->id() }}" hidden>
        <section class="chat-widget-panel" id="chat-widget-panel" data-chat-panel role="dialog" aria-label="Messages" hidden>
            <header class="chat-widget-toolbar">
                <div><strong>Messages</strong><span>A little conversation goes a long way.</span></div>
                <a href="{{ route(auth()->user()->role.'.chat') }}" data-chat-expand aria-label="Open full Messages page" title="Open full Messages page">@include('partials.icons.chat-widget', ['icon' => 'expand'])</a>
                <button type="button" data-chat-minimize aria-label="Minimize chat" title="Minimize">@include('partials.icons.chat-widget', ['icon' => 'minimize'])</button>
                <button type="button" data-chat-dismiss aria-label="Dismiss chat bubble" title="Dismiss bubble">@include('partials.icons.chat-widget', ['icon' => 'close'])</button>
            </header>
            <iframe data-chat-frame title="Buyer and seller conversations"></iframe>
        </section>
        <button class="chat-widget-bubble" type="button" data-chat-bubble aria-label="Open messages" aria-controls="chat-widget-panel" aria-expanded="false" aria-describedby="chat-bubble-help">
            @include('partials.icons.chat-widget', ['icon' => 'chat'])
        </button>
        <span class="chat-widget-sr" id="chat-bubble-help">Drag to move. Drop on the bottom-center dismiss area to hide. You can also press Delete. Open Messages to bring the bubble back.</span>
        <div class="chat-widget-drop" data-chat-drop aria-hidden="true" hidden>
            <span class="chat-widget-drop-label">Drag here to dismiss</span>
            <span class="chat-widget-drop-target" data-chat-drop-target>@include('partials.icons.chat-widget', ['icon' => 'close'])</span>
        </div>
        <p class="chat-widget-notice" data-chat-notice role="status" hidden></p>
    </div>
    <script src="{{ asset('js/chat-widget.js') }}?v={{ filemtime(public_path('js/chat-widget.js')) }}" defer></script>
@endonce
@endif
