{{--
    The help bot on every portal: answers common questions for the signed-in account (FaqController, config/faq.php)
    and hands over to Help & support when nothing fits. Behaviour in public/js/faq-bot.js.
--}}
@if(auth()->user()?->isActive() && auth()->user()->hasVerifiedEmail())
    <link rel="stylesheet" href="{{ asset('css/faq-bot.css') }}?v={{ filemtime(public_path('css/faq-bot.css')) }}">
    <div class="faq-bot" data-faq-bot data-faq-url="{{ route('faq') }}">
        <button type="button" class="faq-bot__toggle" data-faq-toggle aria-expanded="false" aria-controls="faq-bot-panel" title="Help">
            <i class="fas fa-circle-question" aria-hidden="true"></i>
            <span class="sr-only">Open ZAYLO help</span>
        </button>

        <section class="faq-bot__panel" id="faq-bot-panel" data-faq-panel role="dialog" aria-labelledby="faq-bot-title" hidden>
            <header class="faq-bot__header">
                <span class="faq-bot__avatar" aria-hidden="true"><i class="fas fa-robot"></i></span>
                <div>
                    <strong id="faq-bot-title">ZAYLO Help</strong>
                    <span>Answers to common questions</span>
                </div>
                <button type="button" class="faq-bot__close" data-faq-close aria-label="Close help">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <ol class="faq-bot__messages" data-faq-messages aria-live="polite"></ol>

            <form class="faq-bot__form" data-faq-form>
                <label class="sr-only" for="faq-bot-question">Ask a question</label>
                <input type="text" id="faq-bot-question" data-faq-input maxlength="200" autocomplete="off" placeholder="Ask a question…">
                <button type="submit" aria-label="Ask"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
            </form>
        </section>
    </div>
    <script src="{{ asset('js/faq-bot.js') }}?v={{ filemtime(public_path('js/faq-bot.js')) }}" defer></script>
@endif
