<section class="messaging" data-messaging
         @if($compact ?? false) data-compact="true" @endif
         data-inbox-url="{{ route('messages.inbox') }}"
         data-conversation="{{ $conversation?->id }}"
         @if($conversation)
         data-messages-url="{{ route('messages.list', $conversation) }}"
         data-send-url="{{ route('messages.send', $conversation) }}"
         data-read-url="{{ route('messages.read', $conversation) }}"
         @endif>
    <div class="messaging-heading">
        <div><p class="messaging-eyebrow">Stay connected</p><h1>Messages</h1></div>
        <p>{{ $sellerView ? 'Help your buyers, one conversation at a time.' : 'Ask a question. Get to know your seller.' }}</p>
    </div>
    <noscript>Please enable JavaScript to load conversations and send messages.</noscript>
    <p class="messaging-feedback" data-chat-feedback role="status" hidden></p>
    <div class="messaging-workspace">
        <aside class="messaging-inbox" aria-label="Conversations">
            <div class="messaging-inbox-heading"><h2>Conversations</h2><span data-unread-count></span></div>
            <div class="messaging-inbox-scroll" data-inbox-scroll tabindex="0" aria-label="Scrollable conversations">
                <div data-inbox-list><p class="messaging-empty">Loading conversations…</p></div>
                <p class="messaging-inbox-status" data-inbox-status role="status" hidden></p>
            </div>
            @unless($sellerView)
                <a class="messaging-browse" href="{{ route('products.index') }}" @if($compact ?? false) target="_top" @endif>Browse products to start a chat <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            @endunless
        </aside>
        <section class="messaging-thread" aria-label="Chat">
            @if($conversation)
                @php
                    $other = $sellerView ? $conversation->buyer : $conversation->seller;
                    $otherName = $sellerView ? $other->name : ($other->sellerProfile?->store_name ?? $other->sellerProfile?->name ?? $other->name);
                @endphp
                <header class="messaging-thread-heading">
                    <span class="messaging-avatar" aria-hidden="true">{{ mb_substr($otherName, 0, 1) }}</span>
                    <div><h2>{{ $otherName }}</h2><p>{{ $sellerView ? 'Buyer' : 'Seller' }}</p></div>
                </header>
                @if($conversation->product)
                    <div class="messaging-context"><i class="fas fa-box" aria-hidden="true"></i><span>Conversation started about <strong>{{ $conversation->product->name }}</strong></span></div>
                @endif
                <div class="messaging-history-tools"><button type="button" data-load-older hidden>Load older messages</button></div>
                <div class="messaging-messages" data-message-list role="log" aria-label="Messages" aria-live="polite" aria-relevant="additions" tabindex="0">
                    <p class="messaging-empty" data-message-empty>Loading messages…</p>
                </div>
                <form class="messaging-composer" method="POST" action="{{ route('messages.send', $conversation) }}" data-message-form>
                    @csrf
                    <label class="messaging-label" for="message-body">Your message</label>
                    <div class="messaging-compose-row">
                        <textarea id="message-body" name="body" rows="2" maxlength="2000" placeholder="Write a message…" required disabled></textarea>
                        <button type="submit" disabled><i class="fas fa-paper-plane" aria-hidden="true"></i><span>Send</span></button>
                    </div>
                    <p>Up to 2,000 characters. Keep your conversation about shopping and orders.</p>
                </form>
            @else
                <div class="messaging-welcome"><i class="far fa-comments" aria-hidden="true"></i><h2>A little conversation goes a long way.</h2><p>Select a conversation{{ $sellerView ? ' to reply to a buyer.' : ' or use “Chat with seller” on a product page.' }}</p></div>
            @endif
        </section>
    </div>
</section>
