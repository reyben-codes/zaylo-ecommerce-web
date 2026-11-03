{{--
    Brings Zay (partials/faq-bot) back after it was dropped on the hide target. Hidden until public/js/faq-bot.js
    finds Zay hidden in this browser. $variant: 'icon' for a header icon button, 'sidebar' for a sidebar link.
    Pass the same $guests as the page's partials/faq-bot.
--}}
@if(auth()->check() ? auth()->user()->isActive() && auth()->user()->hasVerifiedEmail() : ($guests ?? false))
    @if(($variant ?? 'icon') === 'sidebar')
        <button type="button" class="seller-sidebar__link zay-restore" data-zay-restore hidden>
            <i class="fas fa-robot" aria-hidden="true"></i>
            <span>Show Zay</span>
        </button>
    @else
        <button type="button" class="theme-toggle zay-restore" data-zay-restore aria-label="Show Zay" title="Show Zay" hidden>
            <i class="fas fa-robot" aria-hidden="true"></i>
        </button>
    @endif
@endif
