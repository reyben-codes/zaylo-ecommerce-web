{{-- The sign-in / sign-up modal used by every storefront page (App\Support\AuthModal, public/js/auth-modal.js).
     Links to the sign-in, sign-up, email-code and forgot-password pages open here instead of loading a page.
     $authModal (set by AuthModal::respond on a direct visit) renders a panel already open, which also works
     without JavaScript. Styles: partials/auth-modal-styles. --}}
@php $authModal ??= null; @endphp
@if($authModal || ! auth()->check() || ! auth()->user()->hasVerifiedEmail())
    <dialog class="auth-modal" id="auth-modal" aria-labelledby="auth-modal-title" data-auth-modal
            data-paths="{{ json_encode(\App\Support\AuthModal::paths()) }}"
            @if($authModal) open data-initial-url="{{ request()->getRequestUri() }}" @endif>
        <div class="auth-modal__frame">
            <div class="auth-modal__main">
                <a href="{{ route('home') }}" class="auth-modal__close" data-auth-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
                <div class="auth-modal__body" data-auth-body>
                    @if($authModal)
                        @include($authModal['view'], $authModal['data'])
                    @endif
                </div>
                <div class="auth-modal__loading" data-auth-loading hidden>
                    <span class="auth-modal__spinner" aria-hidden="true"></span><span class="sr-only" role="status">Loading…</span>
                </div>
            </div>
        </div>
    </dialog>

    @foreach(array_filter(['personal-details', 'password-requirements', 'registration-password-entry', 'address-selector', 'seller-application', 'rider-application', 'email-verification', ($withConfirmDialog ?? false) ? 'confirm-dialog' : null, 'auth-modal']) as $script)
        <script src="{{ asset("js/{$script}.js") }}?v={{ filemtime(public_path("js/{$script}.js")) }}" defer></script>
    @endforeach
@endif
