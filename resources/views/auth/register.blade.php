@extends('layouts.auth')

@section('title', 'ZAYLO · Create new account')

@php
    $requestedRole = old('role', request('role', 'buyer'));

    $selectedRole = in_array(
        $requestedRole,
        ['buyer', 'seller', 'courier'],
        true
    ) ? $requestedRole : 'buyer';

    $isPartner = $selectedRole !== 'buyer';

    $firstErrorStep = $errors->hasAny([
        'first_name',
        'last_name',
        'phone',
        'date_of_birth',
        'role'
    ]) ? 1
        : (
            $errors->hasAny([
                'email',
                'password',
                'password_confirmation'
            ]) ? 2 : 1
        );
@endphp

@include('auth.partials.registration-styles')

@section('content')

<main class="auth-container">

    {{-- LEFT SIDE IMAGE --}}
    <aside class="auth-image" aria-label="ZAYLO marketplace">

        <div class="auth-image-content">

            <a class="auth-brand" href="{{ route('home') }}">
                <img
                    src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}"
                    alt="ZAYLO home"
                >
            </a>

            <div class="auth-image-text">
                <p>Driven by passion.</p>
                <p>Defined by your origin</p>
            </div>

        </div>

    </aside>


    {{-- REGISTRATION FORM --}}
    <section class="auth-form" aria-labelledby="register-title">

        <a
            href="{{ route('products.index') }}"
            class="continue-shopping"
        >
            <span aria-hidden="true">&larr;</span>
            Continue shopping
        </a>


        <div class="auth-form-content">

            {{-- HEADER --}}
            <header class="auth-header">

                <span
                    class="auth-label"
                    id="register-label"
                >
                    {{ $isPartner ? 'ZAYLO PARTNERS' : 'JOIN ZAYLO' }}
                </span>


                <h1 id="register-title">
                    {{
                        $selectedRole === 'seller'
                            ? 'Sell on ZAYLO'
                            : (
                                $selectedRole === 'courier'
                                    ? 'Become a courier'
                                    : 'Create new account'
                            )
                    }}
                </h1>


                <p
                    class="auth-subtitle"
                    id="register-subtitle"
                >
                    {{
                        $isPartner
                            ? 'Create your account to apply as a '
                                . ($selectedRole === 'seller'
                                    ? 'seller'
                                    : 'courier')
                                . ' on ZAYLO.'
                            : 'Save your favorites, track your orders, and discover everyday finds in one place.'
                    }}
                </p>

            </header>


            {{-- VALIDATION ERRORS --}}
            @if($errors->any())

                <div
                    class="alert-error"
                    role="alert"
                >

                    <ul>

                        @foreach($errors->all() as $error)

                            <li>{{ $error }}</li>

                        @endforeach

                    </ul>

                </div>

            @endif


            {{-- PARTNER NOTICE --}}
            <p
                class="partner-note"
                id="partner-note"
                @if(!$isPartner) hidden @endif
            >
                Seller and courier accounts require email verification
                and administrator approval before you can start selling
                or accept deliveries.
            </p>


            {{-- REGISTRATION PROGRESS --}}
            @include(
                'auth.partials.registration-progress',
                ['activeStep' => 1]
            )


            {{-- MANUAL REGISTRATION --}}
            <form
                method="POST"
                action="{{ route('register') }}"
                id="registerForm"
            >

                @csrf


                {{-- ========================================= --}}
                {{-- STEP 1 - PERSONAL DETAILS --}}
                {{-- ========================================= --}}
                <section
                    class="step-content"
                    id="step1"
                    aria-labelledby="step1-title"
                >

                    <h2
                        class="step-title"
                        id="step1-title"
                        tabindex="-1"
                    >
                        Your personal details
                    </h2>


                    <div class="form-row">

                        <div class="form-group">

                            <label for="first_name">
                                First name
                            </label>

                            <input
                                id="first_name"
                                name="first_name"
                                value="{{ old('first_name') }}"
                                autocomplete="given-name"
                                maxlength="100"
                                data-person-name
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="last_name">
                                Last name
                            </label>

                            <input
                                id="last_name"
                                name="last_name"
                                value="{{ old('last_name') }}"
                                autocomplete="family-name"
                                maxlength="100"
                                data-person-name
                                required
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="phone">
                            Mobile number
                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="09171234567"
                            autocomplete="tel"
                            maxlength="11"
                            inputmode="numeric"
                            pattern="09[0-9]{9}"
                            title="Enter 11 digits starting with 09"
                            data-phone-number
                            required
                        >

                    </div>


                    @if($selectedRole === 'buyer')

                        <div class="form-group">

                            <label for="date_of_birth">
                                Birthday
                            </label>

                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                                value="{{ old('date_of_birth') }}"
                                max="{{ today()->toDateString() }}"
                                autocomplete="bday"
                                required
                            >

                            <p class="field-note">
                                Your age is calculated automatically from your birthday.
                            </p>

                        </div>

                    @endif


                    <input type="hidden" name="role" id="selectedRole" value="{{ $selectedRole }}">


                    <div
                        class="step-actions"
                        data-navigation
                        hidden
                    >

                        <button
                            type="button"
                            class="btn-primary"
                            data-next
                        >
                            Continue to sign-in details
                        </button>

                    </div>

                </section>




                {{-- ========================================= --}}
                {{-- STEP 2 - SIGN-IN DETAILS --}}
                {{-- ========================================= --}}
                <section
                    class="step-content"
                    id="step2"
                    aria-labelledby="step2-title"
                >

                    <h2
                        class="step-title"
                        id="step2-title"
                        tabindex="-1"
                    >
                        Your sign-in details
                    </h2>


                    <div class="form-group">

                        <label for="email">
                            Email address
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                        >

                    </div>


                    {{-- PASSWORD --}}
                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>


                        <div class="password-input-wrapper">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                autocomplete="new-password"
                                minlength="8"
                                aria-describedby="password-note"
                                data-new-password
                                required
                            >


                            <button
                                type="button"
                                class="toggle-password"
                                data-password="password"
                                aria-controls="password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                Show
                            </button>

                        </div>


                        <p
                            id="password-note"
                            class="field-note"
                        >
                            Use at least 8 characters, one uppercase letter, one lowercase letter, and one symbol.
                        </p>

                    </div>


                    {{-- CONFIRM PASSWORD --}}
                    <div class="form-group">

                        <label for="password_confirmation">
                            Confirm password
                        </label>


                        <div class="password-input-wrapper">

                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                placeholder="Repeat your password"
                                autocomplete="new-password"
                                required
                            >


                            <button
                                type="button"
                                class="toggle-password"
                                data-password="password_confirmation"
                                aria-controls="password_confirmation"
                                aria-label="Show confirmation password"
                                aria-pressed="false"
                            >
                                Show
                            </button>

                        </div>

                    </div>


                    {{-- EMAIL OTP NOTICE --}}
                    <p class="verification-note">
                        We'll send a six-digit verification code to this
                        email address. You'll need the code to finish
                        creating your ZAYLO account.
                    </p>


                    <div class="step-actions">

                        <button
                            type="button"
                            class="btn-back"
                            data-back
                            hidden
                        >
                            Back
                        </button>


                        <button
                            type="submit"
                            class="btn-primary"
                            id="create-button"
                        >
                            Send verification code
                        </button>

                    </div>

                </section>

            </form>


            {{-- ========================================= --}}
            {{-- GOOGLE REGISTRATION --}}
            {{-- ========================================= --}}

            <div class="auth-divider">
                <span>OR</span>
            </div>


            @if(Route::has('google.redirect'))

                <a
                    href="{{ route('google.redirect', ['role' => $selectedRole]) }}"
                    class="btn-google"
                    id="google-register-button"
                >

                    <svg
                        class="google-icon"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            fill="#4285F4"
                            d="M21.35 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h5.23a4.47 4.47 0 0 1-1.94 2.93v2.79h3.14c1.84-1.69 2.92-4.18 2.92-7.75z"
                        />

                        <path
                            fill="#34A853"
                            d="M12 21.75c2.63 0 4.84-.87 6.45-2.36l-3.14-2.43c-.87.58-1.98.93-3.31.93-2.54 0-4.69-1.72-5.46-4.02H3.3v2.51A9.75 9.75 0 0 0 12 21.75z"
                        />

                        <path
                            fill="#FBBC05"
                            d="M6.54 13.87A5.86 5.86 0 0 1 6.23 12c0-.65.11-1.28.31-1.87V7.62H3.3A9.75 9.75 0 0 0 2.25 12c0 1.57.38 3.05 1.05 4.38l3.24-2.51z"
                        />

                        <path
                            fill="#EA4335"
                            d="M12 6.11c1.43 0 2.72.49 3.73 1.46l2.79-2.79C16.83 3.21 14.63 2.25 12 2.25a9.75 9.75 0 0 0-8.7 5.37l3.24 2.51c.77-2.3 2.92-4.02 5.46-4.02z"
                        />

                    </svg>

                    <span>
                        Continue with Google
                    </span>

                </a>

            @else

                {{-- Temporary button until Google route is created --}}
                <button
                    type="button"
                    class="btn-google"
                    disabled
                    title="Google Sign-In is not configured yet."
                >

                    <svg
                        class="google-icon"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            fill="#4285F4"
                            d="M21.35 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h5.23a4.47 4.47 0 0 1-1.94 2.93v2.79h3.14c1.84-1.69 2.92-4.18 2.92-7.75z"
                        />

                        <path
                            fill="#34A853"
                            d="M12 21.75c2.63 0 4.84-.87 6.45-2.36l-3.14-2.43c-.87.58-1.98.93-3.31.93-2.54 0-4.69-1.72-5.46-4.02H3.3v2.51A9.75 9.75 0 0 0 12 21.75z"
                        />

                        <path
                            fill="#FBBC05"
                            d="M6.54 13.87A5.86 5.86 0 0 1 6.23 12c0-.65.11-1.28.31-1.87V7.62H3.3A9.75 9.75 0 0 0 2.25 12c0 1.57.38 3.05 1.05 4.38l3.24-2.51z"
                        />

                        <path
                            fill="#EA4335"
                            d="M12 6.11c1.43 0 2.72.49 3.73 1.46l2.79-2.79C16.83 3.21 14.63 2.25 12 2.25a9.75 9.75 0 0 0-8.7 5.37l3.24 2.51c.77-2.3 2.92-4.02 5.46-4.02z"
                        />

                    </svg>

                    <span>
                        Continue with Google
                    </span>

                </button>

            @endif


            {{-- FOOTER --}}
            <footer class="auth-footer">

                <p>
                    Already have an account?

                    <a href="{{ $selectedRole === 'seller' ? route('seller.login') : route('login') }}">
                        Sign in
                    </a>
                </p>


                <div class="partner-links">

                    @if($isPartner)

                        <a href="{{ route('register') }}">
                            Create new account
                        </a>

                    @endif


                    @if($selectedRole !== 'seller')

                        <a
                            href="{{ route(
                                'register',
                                ['role' => 'seller']
                            ) }}"
                        >
                            Sell on ZAYLO
                        </a>

                    @endif


                    @if($selectedRole !== 'courier')

                        <a
                            href="{{ route(
                                'register',
                                ['role' => 'courier']
                            ) }}"
                        >
                            Become a courier
                        </a>

                    @endif

                </div>

            </footer>

        </div>

    </section>

</main>

@endsection


@push('scripts')

<script src="{{ asset('js/personal-details.js') }}?v={{ filemtime(public_path('js/personal-details.js')) }}" defer></script>
<script src="{{ asset('js/password-requirements.js') }}?v={{ filemtime(public_path('js/password-requirements.js')) }}" defer></script>

<script>

(() => {

    const form =
        document.getElementById('registerForm');

    const sections =
        [...form.querySelectorAll('.step-content')];

    const password =
        document.getElementById('password');

    const confirmation =
        document.getElementById('password_confirmation');

    const role =
        document.getElementById('selectedRole');

    const createButton =
        document.getElementById('create-button');

    const googleButton =
        document.getElementById('google-register-button');

    let currentStep = 1;


    /*
    |--------------------------------------------------------------------------
    | UPDATE ROLE UI
    |--------------------------------------------------------------------------
    */

    function updateRole()
    {
        const partner =
            role.value !== 'buyer';


        document
            .getElementById('register-label')
            .textContent =
                partner
                    ? 'ZAYLO PARTNERS'
                    : 'JOIN ZAYLO';


        document
            .getElementById('register-title')
            .textContent =
                role.value === 'seller'
                    ? 'Sell on ZAYLO'
                    : (
                        role.value === 'courier'
                            ? 'Become a courier'
                            : 'Create new account'
                    );


        document
            .getElementById('register-subtitle')
            .textContent =
                partner
                    ? 'Create your account to apply as a '
                        + role.value
                        + ' on ZAYLO.'
                    : 'Save your favorites, track your orders, and discover everyday finds in one place.';


        document
            .getElementById('partner-note')
            .hidden =
                !partner;


        createButton.textContent =
            'Send verification code';


        /*
         * Keep the selected role when using Google.
         */
        if (googleButton)
        {
            const url =
                new URL(
                    googleButton.href,
                    window.location.origin
                );

            url.searchParams.set(
                'role',
                role.value
            );

            googleButton.href =
                url.toString();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW STEP
    |--------------------------------------------------------------------------
    */

    function showStep(step, focus = true)
    {
        if (
            step < 1 ||
            step > sections.length
        )
        {
            return;
        }


        currentStep = step;


        sections.forEach(
            (section, index) =>
            {
                section.hidden =
                    index + 1 !== step;
            }
        );


        document
            .querySelectorAll('.step-item')
            .forEach(
                item =>
                {
                    const itemStep =
                        Number(item.dataset.step);


                    if (itemStep === step)
                    {
                        item.setAttribute(
                            'aria-current',
                            'step'
                        );
                    }
                    else
                    {
                        item.removeAttribute(
                            'aria-current'
                        );
                    }


                    item.classList.toggle(
                        'completed',
                        itemStep < step
                    );
                }
            );


        if (focus)
        {
            document
                .getElementById(
                    'step' + step + '-title'
                )
                .focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE STEP
    |--------------------------------------------------------------------------
    */

    function validateStep(step)
    {
        confirmation.setCustomValidity(
            confirmation.value !== password.value
                ? 'Your passwords must match.'
                : ''
        );


        const invalid =
            [
                ...sections[
                    step - 1
                ].querySelectorAll(
                    'input, textarea, select'
                )
            ].find(
                field =>
                    !field.checkValidity()
            );


        if (!invalid)
        {
            return true;
        }


        showStep(step, false);

        invalid.reportValidity();

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL SETUP
    |--------------------------------------------------------------------------
    */

    form.noValidate = true;


    const indicator =
        document.querySelector(
            '.step-indicator'
        );


    if (indicator)
    {
        indicator.hidden = false;
    }


    document
        .querySelectorAll(
            '[data-navigation], [data-back]'
        )
        .forEach(
            element =>
            {
                element.hidden = false;
            }
        );


    updateRole();


    const firstErrorStep =
        @json($firstErrorStep);


    showStep(
        firstErrorStep,
        false
    );


    /*
    |--------------------------------------------------------------------------
    | NEXT BUTTONS
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-next]')
        .forEach(
            button =>
                button.addEventListener(
                    'click',
                    () =>
                    {
                        if (
                            validateStep(
                                currentStep
                            )
                        )
                        {
                            showStep(
                                currentStep + 1
                            );
                        }
                    }
                )
        );


    /*
    |--------------------------------------------------------------------------
    | BACK BUTTONS
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-back]')
        .forEach(
            button =>
                button.addEventListener(
                    'click',
                    () =>
                    {
                        showStep(
                            currentStep - 1
                        );
                    }
                )
        );


    /*
    |--------------------------------------------------------------------------
    | PASSWORD MATCHING
    |--------------------------------------------------------------------------
    */

    [
        password,
        confirmation
    ].forEach(
        field =>
            field.addEventListener(
                'input',
                () =>
                {
                    confirmation
                        .setCustomValidity('');
                }
            )
    );


    /*
    |--------------------------------------------------------------------------
    | SHOW / HIDE PASSWORD
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-password]')
        .forEach(
            button =>
            {
                button.addEventListener(
                    'click',
                    () =>
                    {
                        const input =
                            document.getElementById(
                                button.dataset.password
                            );


                        const visible =
                            input.type === 'password';


                        input.type =
                            visible
                                ? 'text'
                                : 'password';


                        button.textContent =
                            visible
                                ? 'Hide'
                                : 'Show';


                        button.setAttribute(
                            'aria-pressed',
                            String(visible)
                        );


                        button.setAttribute(
                            'aria-label',
                            (
                                visible
                                    ? 'Hide '
                                    : 'Show '
                            )
                            +
                            (
                                input === confirmation
                                    ? 'confirmation password'
                                    : 'password'
                            )
                        );
                    }
                );
            }
        );


    /*
    |--------------------------------------------------------------------------
    | FORM SUBMISSION
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        event =>
        {
            if (
                currentStep <
                sections.length
            )
            {
                event.preventDefault();


                if (
                    validateStep(
                        currentStep
                    )
                )
                {
                    showStep(
                        currentStep + 1
                    );
                }


                return;
            }


            for (
                let step = 1;
                step <= sections.length;
                step++
            )
            {
                if (!validateStep(step))
                {
                    event.preventDefault();

                    return;
                }
            }


            createButton.disabled = true;

            createButton.textContent =
                'Sending verification code…';
        }
    );


    /*
    |--------------------------------------------------------------------------
    | BROWSER BACK CACHE FIX
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'pageshow',
        () =>
        {
            createButton.disabled = false;

            updateRole();
        }
    );

})();

</script>

@endpush
