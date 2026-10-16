{{-- A complaint's message thread with the reply form. Expects $dispute (messages.sender loaded), $replyRoute, optional $viewerIsAdmin. --}}
@php $viewerId = auth()->id(); @endphp
<ol class="case-thread" aria-label="Messages in this case">
    @forelse($dispute->messages as $message)
        @php $mine = (int) $message->sender_id === (int) $viewerId; @endphp
        <li class="case-message{{ $mine ? ' is-mine' : '' }}{{ $message->sender?->hasRole('admin') ? ' is-admin' : '' }}">
            <span class="case-message__who">{{ $mine ? 'You' : ($message->sender?->name ?? 'Deleted account') }} · {{ $dispute->roleOf($message->sender_id) }}</span>
            <p>{{ $message->body }}</p>
            @if($message->attachment_path)
                <a href="{{ route('disputes.attachment', $message) }}" target="_blank" rel="noopener" class="case-message__file"><i class="fas fa-paperclip" aria-hidden="true"></i> Attachment</a>
            @endif
            <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</time>
        </li>
    @empty
        <li class="case-thread__empty">No messages yet.{{ ($viewerIsAdmin ?? false) ? ' Ask the seller or courier for their side here.' : ' The ZAYLO team will reply here.' }}</li>
    @endforelse
</ol>

@if($dispute->isClosed())
    <p class="case-closed"><i class="fas fa-lock" aria-hidden="true"></i> This case is {{ Str::lower($dispute->status_label) }}. {{ $dispute->resolution }}</p>
@else
    <form method="POST" action="{{ $replyRoute }}" enctype="multipart/form-data" class="case-reply">
        @csrf
        <label class="sr-only" for="case-reply-body">Reply</label>
        <textarea id="case-reply-body" name="body" rows="3" maxlength="2000" required placeholder="Write a reply everyone in the case can read…">{{ old('body') }}</textarea>
        <div class="case-reply__row">
            <label class="case-reply__file">
                <i class="fas fa-paperclip" aria-hidden="true"></i>
                <span>Attach photo or PDF</span>
                <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf">
            </label>
            <button type="submit" class="seller-button"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send</button>
        </div>
        @error('body')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
        @error('attachment')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
    </form>
@endif
