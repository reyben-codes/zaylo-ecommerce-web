{{-- Live announcements from the admin for the viewer's role: the announcement section, which viewers can dismiss, and
     pop-ups, which open once. Both are remembered in their browser. --}}
@php
    $viewerRole = auth()->user()?->role;
    $notices = $viewerRole === 'admin' ? collect() : \App\Models\Announcement::for($viewerRole);
    $popups = $viewerRole === 'admin' ? collect() : \App\Models\Announcement::popupsFor($viewerRole);
@endphp
@if($notices->isNotEmpty() || $popups->isNotEmpty())
    <link rel="stylesheet" href="{{ asset('css/announcements.css') }}?v={{ filemtime(public_path('css/announcements.css')) }}">
    @if($notices->isNotEmpty())
    <div class="announcement-bar" role="region" aria-label="Announcements">
        @foreach($notices as $notice)
            <div class="announcement-bar__item" data-announcement="{{ $notice->id }}-{{ $notice->updated_at?->timestamp }}">
                <i class="fas fa-bullhorn" aria-hidden="true"></i>
                <p><strong>{{ $notice->title }}</strong> {{ $notice->body }}</p>
                <button type="button" class="announcement-bar__close" data-announcement-close aria-label="Dismiss announcement: {{ $notice->title }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
        @endforeach
    </div>
    @endif
    @foreach($popups as $popup)
        <dialog class="announcement-modal" data-announcement-modal="{{ $popup->id }}-{{ $popup->updated_at?->timestamp }}" aria-labelledby="announcement-modal-title-{{ $popup->id }}">
            <span class="announcement-modal__icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
            <h2 id="announcement-modal-title-{{ $popup->id }}">{{ $popup->title }}</h2>
            <p>{{ $popup->body }}</p>
            <button type="button" data-announcement-modal-close>Got it</button>
        </dialog>
    @endforeach
    <script src="{{ asset('js/announcements.js') }}?v={{ filemtime(public_path('js/announcements.js')) }}" defer></script>
@endif
