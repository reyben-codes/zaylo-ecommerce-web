{{-- Live announcements from the admin for the viewer's role. Viewers can dismiss each one (remembered in their browser). --}}
@php
    $viewerRole = auth()->user()?->role;
    $notices = $viewerRole === 'admin' ? collect() : \App\Models\Announcement::for($viewerRole);
@endphp
@if($notices->isNotEmpty())
    <link rel="stylesheet" href="{{ asset('css/announcements.css') }}?v={{ filemtime(public_path('css/announcements.css')) }}">
    <div class="announcement-bar" role="region" aria-label="Announcements">
        @foreach($notices as $notice)
            <div class="announcement-bar__item" data-announcement="{{ $notice->id }}-{{ $notice->updated_at?->timestamp }}">
                <i class="fas fa-bullhorn" aria-hidden="true"></i>
                <p><strong>{{ $notice->title }}</strong> {{ $notice->body }}</p>
                <button type="button" class="announcement-bar__close" data-announcement-close aria-label="Dismiss announcement: {{ $notice->title }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
        @endforeach
    </div>
    <script src="{{ asset('js/announcements.js') }}?v={{ filemtime(public_path('js/announcements.js')) }}" defer></script>
@endif
