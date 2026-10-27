{{--
    The help bot on every portal: Zay answers common questions for the signed-in account (FaqController,
    config/faq.php) and hands over to Help & support when nothing fits. Behaviour in public/js/faq-bot.js.
--}}
@if(auth()->user()?->isActive() && auth()->user()->hasVerifiedEmail())
    <link rel="stylesheet" href="{{ asset('css/faq-bot.css') }}?v={{ filemtime(public_path('css/faq-bot.css')) }}">
    <div class="faq-bot" data-faq-bot data-faq-url="{{ route('faq') }}">
        <p class="faq-bot__teaser" data-faq-teaser hidden>
            <span>Hi! I'm Zay. Need a hand?</span>
            <button type="button" data-faq-teaser-close aria-label="Dismiss">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </p>

        <button type="button" class="faq-bot__toggle" data-faq-toggle aria-expanded="false" aria-controls="faq-bot-panel" title="Ask Zay">
            @include('partials.faq-bot-mascot', ['size' => 46])
            <span class="sr-only">Open ZAYLO help</span>
        </button>

        <section class="faq-bot__panel" id="faq-bot-panel" data-faq-panel role="dialog" aria-labelledby="faq-bot-title" hidden>
            <header class="faq-bot__header">
                <span class="faq-bot__avatar">@include('partials.faq-bot-mascot', ['size' => 40])</span>
                <div>
                    <strong id="faq-bot-title">Zay · ZAYLO Help</strong>
                    <span data-faq-status>Online · usually answers instantly</span>
                </div>
                <button type="button" class="faq-bot__close" data-faq-close aria-label="Close help">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <ol class="faq-bot__messages" data-faq-messages aria-live="polite"></ol>

            <form class="faq-bot__form" data-faq-form>
                <label class="sr-only" for="faq-bot-question">Ask Zay a question</label>
                <input type="text" id="faq-bot-question" data-faq-input maxlength="200" autocomplete="off" placeholder="Ask Zay anything…">
                <button type="submit" aria-label="Send"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
            </form>
        </section>

        {{-- Copied next to each of Zay's messages by the script. --}}
        <template data-faq-mini>@include('partials.faq-bot-mascot', ['size' => 28])</template>
    </div>
    <script src="{{ asset('js/faq-bot.js') }}?v={{ filemtime(public_path('js/faq-bot.js')) }}" defer></script>
@endif
