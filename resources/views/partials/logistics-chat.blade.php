{{-- Logistics chat (sorting center ↔ sellers and riders).
     Expects $available, $threads, $current, $messages, $contacts, $pageRoute (this page's route name),
     and optional $startRoute + $startField (start a new conversation), $showList (default true), $emptyText,
     $fetchRoute / $sendRoute (default: logistics chat; support chat passes its own), and $photos (default false: whether
     messages can carry a photo; support chat turns it on). --}}
@php
    $showList = $showList ?? true;
    $photos = $photos ?? false;
    $startRoute = $startRoute ?? null;
    $fetchRoute = $fetchRoute ?? 'logistics-chat.messages';
    $sendRoute = $sendRoute ?? 'logistics-chat.send';
@endphp

@if(! $available)
    <section class="seller-panel">
        <div class="seller-empty">
            <i class="fas fa-comments" aria-hidden="true"></i>
            <strong>Messages are almost ready</strong>
            <span>Run <code>php artisan migrate</code> to turn on messages.</span>
        </div>
    </section>
@else
    <section class="seller-panel logistics-chat{{ $showList ? '' : ' logistics-chat--single' }}{{ $current ? ' has-thread' : '' }}">
        @if($showList)
            <aside class="logistics-chat__list" aria-label="Conversations">
                @if($startRoute && $contacts->isNotEmpty())
                    <form method="POST" action="{{ route($startRoute) }}" class="logistics-chat__start">
                        @csrf
                        <label class="seller-field__label" for="chat-start">New conversation</label>
                        <div class="logistics-chat__start-row">
                            <select id="chat-start" name="{{ $startField }}" required>
                                <option value="">Choose…</option>
                                @foreach($contacts->groupBy('group') as $group => $people)
                                    <optgroup label="{{ $group }}">
                                        @foreach($people as $person)
                                            <option value="{{ $person['id'] }}">{{ $person['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button type="submit" class="seller-button seller-button--small" aria-label="Start conversation"><i class="fas fa-pen-to-square" aria-hidden="true"></i></button>
                        </div>
                        @error($startField)<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    </form>
                @endif

                <ul class="logistics-chat__threads">
                    @forelse($threads as $thread)
                        <li>
                            <a href="{{ route($pageRoute, ['thread' => $thread->id]) }}" class="logistics-chat__thread{{ $current?->id === $thread->id ? ' is-active' : '' }}" @if($current?->id === $thread->id) aria-current="true" @endif>
                                <span class="logistics-chat__avatar" aria-hidden="true">{{ Str::upper(Str::substr($thread->title, 0, 1)) }}</span>
                                <span class="logistics-chat__thread-text">
                                    <strong>{{ $thread->title }}</strong>
                                    <span>{{ $thread->latestMessage ? Str::limit($thread->latestMessage->preview ?? $thread->latestMessage->body, 48) : 'No messages yet' }}</span>
                                </span>
                                @if($thread->unread)
                                    <span class="logistics-chat__unread" aria-label="{{ $thread->unread }} unread">{{ $thread->unread }}</span>
                                @endif
                            </a>
                        </li>
                    @empty
                        <li class="logistics-chat__none">{{ $emptyText ?? 'No conversations yet.' }}</li>
                    @endforelse
                </ul>
            </aside>
        @endif

        <div class="logistics-chat__conversation">
            @if($current)
                <header class="logistics-chat__head">
                    @if($showList)
                        <a href="{{ route($pageRoute) }}" class="logistics-chat__back" aria-label="Back to conversations"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
                    @endif
                    <span class="logistics-chat__avatar" aria-hidden="true">{{ Str::upper(Str::substr($current->title, 0, 1)) }}</span>
                    <strong>{{ $current->title }}</strong>
                </header>

                <ol class="logistics-chat__messages" data-logistics-chat
                    data-fetch-url="{{ route($fetchRoute, $current) }}"
                    data-last-id="{{ $messages->last()['id'] ?? 0 }}" aria-live="polite">
                    @forelse($messages as $message)
                        <li class="logistics-chat__message{{ $message['mine'] ? ' is-mine' : '' }}" data-id="{{ $message['id'] }}">
                            @if($message['photo'] ?? null)
                                <a href="{{ $message['photo'] }}" target="_blank" rel="noopener" class="logistics-chat__photo"><img src="{{ $message['photo'] }}" alt="Photo from {{ $message['mine'] ? 'you' : $message['sender'] }}" loading="lazy"></a>
                            @endif
                            @if(filled($message['body']))<p>{{ $message['body'] }}</p>@endif
                            <span>{{ $message['mine'] ? 'You' : $message['sender'] }} · {{ $message['time'] }}</span>
                        </li>
                    @empty
                        <li class="logistics-chat__hint" data-empty>Say hello. Messages appear here for both of you.</li>
                    @endforelse
                </ol>

                <form method="POST" action="{{ route($sendRoute, $current) }}" class="logistics-chat__composer" data-logistics-chat-form @if($photos) enctype="multipart/form-data" @endif>
                    @csrf
                    @if($photos)
                        <label class="logistics-chat__attach" title="Add a photo">
                            <i class="fas fa-image" aria-hidden="true"></i><span class="sr-only">Add a photo</span>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-chat-photo>
                        </label>
                    @endif
                    <label class="sr-only" for="chat-body">Message</label>
                    <textarea id="chat-body" name="body" rows="1" maxlength="2000" @unless($photos) required @endunless placeholder="Write a message…">{{ old('body') }}</textarea>
                    <button type="submit" class="seller-button"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send</button>
                </form>
                @if($photos)
                    <p class="logistics-chat__attached" data-chat-photo-name hidden>
                        <i class="fas fa-image" aria-hidden="true"></i> <span></span>
                        <button type="button" data-chat-photo-clear aria-label="Remove photo"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                    </p>
                @endif
                @error('body')<p class="seller-field__error logistics-chat__error" role="alert">{{ $message }}</p>@enderror
                @error('photo')<p class="seller-field__error logistics-chat__error" role="alert">{{ $message }}</p>@enderror
                <p class="logistics-chat__error" data-chat-error role="alert" hidden></p>
            @else
                <div class="seller-empty logistics-chat__placeholder">
                    <i class="fas fa-comments" aria-hidden="true"></i>
                    <strong>{{ $threads->isEmpty() ? 'No conversations yet' : 'Choose a conversation' }}</strong>
                    <span>{{ $emptyText ?? 'Pick someone from the list, or start a new conversation.' }}</span>
                </div>
            @endif
        </div>
    </section>

    @push('scripts')
        <script src="{{ asset('js/logistics-chat.js') }}?v={{ filemtime(public_path('js/logistics-chat.js')) }}" defer></script>
    @endpush
@endif
