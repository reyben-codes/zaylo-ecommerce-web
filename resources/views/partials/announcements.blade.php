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
                <span class="announcement-bar__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m4 9 15-5v16L4 15zM4 9H2v6h2m3 1 1 5h4l-2-4M22 9v6" stroke-linejoin="round"/></svg></span>
                <div class="announcement-bar__copy">
                    <span class="announcement-bar__eyebrow">Announcement</span>
                    <h2 class="announcement-bar__title">{{ $notice->title }}</h2>
                    <p>{{ $notice->body }}</p>
                </div>
                <button type="button" class="announcement-bar__close" data-announcement-close aria-label="Dismiss announcement: {{ $notice->title }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg></button>
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
