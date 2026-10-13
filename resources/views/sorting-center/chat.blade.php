@extends('sorting-center.layout')

@section('page-title', 'Chat & messaging')

@section('module-content')
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Team communication</div><h1>Chat & messaging</h1><p>Conversation interface for coordination with sellers, riders and center staff.</p></div></div>
    <div class="sc-demo-notice"><i class="fa-solid fa-flask" aria-hidden="true"></i><span><strong>Demo conversation:</strong> there is no messaging backend in this project. The conversation and any message you send here are temporary browser-only examples and will not be delivered or saved.</span></div>
    <section class="sc-card sc-chat">
        <aside class="sc-chat-list" aria-label="Sample conversations">
            <div class="sc-chat-contact is-selected"><span class="sc-avatar">MJ</span><span><strong>Marco Jimenez</strong><small>Rider · North route</small></span></div>
            <div class="sc-chat-contact"><span class="sc-avatar" style="background:#e9f6ed;color:#368454">NG</span><span><strong>Northline Goods</strong><small>Seller · Pickup team</small></span></div>
            <div class="sc-chat-contact"><span class="sc-avatar" style="background:#fff1df;color:#9d722b">OP</span><span><strong>Operations desk</strong><small>Sorting center</small></span></div>
        </aside>
        <div class="sc-chat-message-area">
            <div class="sc-chat-thread-head">Marco Jimenez <small style="display:block;margin-top:4px;color:#8995a5;font-size:.68rem;font-weight:400">Sample conversation · Rider</small></div>
            <div class="sc-chat-thread" data-demo-chat-thread aria-live="polite">
                <div class="sc-chat-bubble">Good morning. I’m ready for the North route parcel batch.</div>
                <div class="sc-chat-bubble is-outgoing">Thanks, Marco. Please check in with the dispatch desk before departure.</div>
                <div class="sc-chat-bubble">Understood. I’ll confirm once the parcels are loaded.</div>
            </div>
            <form class="sc-chat-form" data-demo-chat-form>
                <label class="sr-only" for="demo-message">Your message</label>
                <input class="sc-input" id="demo-message" name="message" placeholder="Type a sample message…" autocomplete="off">
                <button class="sc-button" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span class="sr-only">Send sample message</span></button>
            </form>
        </div>
    </section>
@endsection
