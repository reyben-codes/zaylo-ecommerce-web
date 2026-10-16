{{-- Logistics chat (sorting center ↔ sellers and riders).
     Expects $available, $threads, $current, $messages, $contacts, $pageRoute (this page's route name),
     and optional $startRoute + $startField (start a new conversation), $showList (default true), $emptyText,
     $fetchRoute / $sendRoute (default: logistics chat; support chat passes its own). --}}
@php
    $showList = $showList ?? true;
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
                                    <span>{{ $thread->latestMessage ? Str::limit($thread->latestMessage->body, 48) : 'No messages yet' }}</span>
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
                            <p>{{ $message['body'] }}</p>
                            <span>{{ $message['mine'] ? 'You' : $message['sender'] }} · {{ $message['time'] }}</span>
                        </li>
                    @empty
                        <li class="logistics-chat__hint" data-empty>Say hello. Messages appear here for both of you.</li>
                    @endforelse
                </ol>

                <form method="POST" action="{{ route($sendRoute, $current) }}" class="logistics-chat__composer" data-logistics-chat-form>
                    @csrf
                    <label class="sr-only" for="chat-body">Message</label>
                    <textarea id="chat-body" name="body" rows="1" maxlength="2000" required placeholder="Write a message…">{{ old('body') }}</textarea>
                    <button type="submit" class="seller-button"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send</button>
                </form>
                @error('body')<p class="seller-field__error logistics-chat__error" role="alert">{{ $message }}</p>@enderror
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
