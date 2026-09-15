@if(session()->has('status') || session()->has('error') || $errors->any())
    <section class="toast-region" aria-label="Notifications" data-toast-region>
        @if(session()->has('status'))
            <div class="toast-notification toast-notification--success" role="status" data-toast data-timeout="5200">
                <span class="toast-notification__icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                <div class="toast-notification__content">
                    <p class="toast-notification__title">Success</p>
                    <p class="toast-notification__message">{{ session('status') }}</p>
                </div>
                <button class="toast-notification__close" type="button" aria-label="Dismiss notification" data-toast-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session()->has('error'))
            <div class="toast-notification toast-notification--error" role="alert" data-toast data-timeout="7000">
                <span class="toast-notification__icon" aria-hidden="true"><i class="fas fa-exclamation"></i></span>
                <div class="toast-notification__content">
                    <p class="toast-notification__title">Something went wrong</p>
                    <p class="toast-notification__message">{{ session('error') }}</p>
                </div>
                <button class="toast-notification__close" type="button" aria-label="Dismiss notification" data-toast-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if($errors->any())
            <div class="toast-notification toast-notification--error" role="alert" data-toast data-timeout="7000">
                <span class="toast-notification__icon" aria-hidden="true"><i class="fas fa-exclamation"></i></span>
                <div class="toast-notification__content">
                    <p class="toast-notification__title">Please check your details</p>
                    <p class="toast-notification__message">{{ $errors->first() }}</p>
                </div>
                <button class="toast-notification__close" type="button" aria-label="Dismiss notification" data-toast-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
    </section>
@endif
