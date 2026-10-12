@extends('layouts.app')

@section('title', 'ZAYLO · Admin Chat')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active-link' : '' }}">Users</a>
    <a href="{{ route('admin.registrations') }}" class="{{ request()->routeIs('admin.registrations') ? 'active-link' : '' }}">Registrations</a>
    <a href="{{ route('admin.disputes') }}" class="{{ request()->routeIs('admin.disputes') ? 'active-link' : '' }}">Disputes</a>
    <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'active-link' : '' }}">Reports</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('admin.chat') }}"><i class="fas fa-comment-dots"></i></a>
<a href="{{ route('admin.account') }}"><i class="far fa-user"></i></a>
@endsection

@push('styles')
<style>
    .support-inbox { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 18px; align-items: start; }
    .support-list, .support-thread { background: var(--c-ffffff); border: 1px solid var(--c-ece5dd); border-radius: 14px; overflow: hidden; }
    .support-list a { display: block; padding: 14px 16px; border-bottom: 1px solid var(--c-f1ebe4); color: inherit; text-decoration: none; }
    .support-list a.is-selected { background: var(--c-faf7f2); }
    .support-list .who { display: flex; justify-content: space-between; gap: 8px; font-weight: 600; }
    .support-list .preview { margin-top: 4px; color: var(--c-7a6e63); font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .support-unread { background: var(--c-1a1714); color: var(--c-ffffff); border-radius: 999px; padding: 0 8px; font-size: .75rem; line-height: 20px; }
    .support-thread header { padding: 16px 18px; border-bottom: 1px solid var(--c-f1ebe4); }
    .support-thread header small { color: var(--c-7a6e63); }
    .support-messages { padding: 18px; display: flex; flex-direction: column; gap: 10px; max-height: 55vh; overflow-y: auto; }
    .support-bubble { max-width: 75%; padding: 10px 14px; border-radius: 14px; background: var(--c-f3eee8); white-space: pre-wrap; }
    .support-bubble.staff { align-self: flex-end; background: var(--c-1a1714); color: var(--c-ffffff); }
    .support-bubble time { display: block; margin-top: 4px; font-size: .72rem; opacity: .7; }
    .support-reply { display: flex; gap: 10px; padding: 14px 18px; border-top: 1px solid var(--c-f1ebe4); }
    .support-reply textarea { flex: 1; min-height: 46px; padding: 10px 12px; border: 1px solid var(--c-ddd3c8); border-radius: 10px; font: inherit; resize: vertical; }
    .support-reply button { padding: 0 20px; border: 0; border-radius: 10px; background: var(--c-1a1714); color: var(--c-ffffff); font-weight: 600; cursor: pointer; }
    .support-empty { padding: 28px; text-align: center; color: var(--c-7a6e63); }
    @media (max-width: 820px) { .support-inbox { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-comment-dots"></i>
        <div>
            <h1>Support chat</h1>
            <p>Answer riders who message ZAYLO support from the rider app.</p>
        </div>
    </div>
</div>
<div class="page-content">
    @if(session('status'))
        <p role="status" style="margin-bottom:14px;color:var(--c-2e7d32);">{{ session('status') }}</p>
    @endif

    @if($conversations->isEmpty())
        <div class="placeholder-card">
            <i class="fas fa-comment-dots"></i>
            <h2>No messages yet</h2>
            <p>When a rider writes to support from the app, the conversation appears here.</p>
        </div>
    @else
        <div class="support-inbox">
            <nav class="support-list" aria-label="Rider conversations">
                @foreach($conversations as $conversation)
                    <a href="{{ route('admin.chat', ['conversation' => $conversation->id]) }}"
                       class="{{ $selected?->is($conversation) ? 'is-selected' : '' }}">
                        <div class="who">
                            <span>{{ $conversation->user->name }}</span>
                            @if($conversation->unread_count && ! $selected?->is($conversation))
                                <span class="support-unread">{{ $conversation->unread_count }}</span>
                            @endif
                        </div>
                        <div class="preview">
                            {{ $conversation->latestMessage?->from_staff ? 'You: ' : '' }}{{ $conversation->latestMessage?->body }}
                        </div>
                    </a>
                @endforeach
            </nav>

            @if($selected)
                <section class="support-thread" aria-label="Conversation with {{ $selected->user->name }}">
                    <header>
                        <strong>{{ $selected->user->name }}</strong><br>
                        <small>
                            {{ $selected->user->email }} · {{ $selected->user->phone ?? 'no mobile' }}
                            @if($application = $selected->user->riderApplication)
                                · {{ \App\Models\RiderApplication::VEHICLE_TYPES[$application->vehicle_type] ?? $application->vehicle_type }}
                                {{ $application->plate_number }} · application {{ $application->status }}
                            @endif
                        </small>
                    </header>
                    <div class="support-messages" id="support-messages">
                        @foreach($selected->messages as $message)
                            <div class="support-bubble {{ $message->from_staff ? 'staff' : '' }}">
                                {{ $message->body }}
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">
                                    {{ $message->from_staff ? ($message->sender?->name ?? 'Support') : 'Rider' }} ·
                                    {{ $message->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}
                                </time>
                            </div>
                        @endforeach
                    </div>
                    <form class="support-reply" method="POST" action="{{ route('admin.chat.reply', $selected) }}">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <label for="support-reply-body" class="visually-hidden" style="position:absolute;left:-9999px;">Reply</label>
                        <textarea id="support-reply-body" name="body" maxlength="2000" required placeholder="Write a reply to {{ $selected->user->name }}">{{ old('body') }}</textarea>
                        <button type="submit">Send</button>
                    </form>
                    @error('body')
                        <p style="color:var(--c-c0392b);padding:0 18px 14px;">{{ $message }}</p>
                    @enderror
                </section>
            @endif
        </div>
        <script>
            // Start the thread at the newest message.
            const thread = document.getElementById('support-messages');
            if (thread) thread.scrollTop = thread.scrollHeight;
        </script>
    @endif
</div>
@endsection
