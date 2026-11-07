{{-- Sign-up panel for buyers, sellers, riders (role "courier") and logistics partners, shown in the sign-in modal.
     Every [data-wizard-step] becomes one step of the wizard in public/js/auth-modal.js, so partner applications
     go one short section at a time; without JavaScript the sections simply follow each other. --}}
@php
    $requestedRole = old('role', request('role', 'buyer'));
    $selectedRole = in_array($requestedRole, ['buyer', 'seller', 'courier', 'rider', 'logistics'], true) ? $requestedRole : 'buyer';
    $logisticsPartners ??= collect();

    $roleCopy = [
        'buyer' => [
            'title' => 'Create new account',
            'subtitle' => 'Save your favorites, track your orders, and discover everyday finds in one place.',
            'note' => null,
        ],
        'seller' => [
            'title' => 'Sell on ZAYLO',
            'subtitle' => 'Create your account to apply as a seller on ZAYLO.',
            'note' => 'Seller accounts require email verification and administrator approval before you can start selling.',
        ],
        'courier' => [
            'title' => 'Become a pickup courier',
            'subtitle' => "Apply to pick up parcels from sellers for one of ZAYLO's logistics partners.",
            'note' => 'The logistics partner you choose reviews your application. You can sign in and manage seller pickups once approved.',
        ],
        'rider' => [
            'title' => 'Become a delivery rider',
            'subtitle' => "Apply to deliver parcels to buyers for one of ZAYLO's logistics partners.",
            'note' => 'The logistics partner you choose reviews your application. You can sign in on the ZAYLO app to start delivering once approved.',
        ],
        'logistics' => [
            'title' => 'Become a logistics partner',
            'subtitle' => 'Register your delivery company and manage your own couriers and riders on ZAYLO.',
            'note' => 'Logistics partner accounts require email verification and administrator approval. Once approved, couriers and riders can apply to your company.',
        ],
    ][$selectedRole];

    // Drivers must choose a partner and logistics partners must name their company, which Google sign-up cannot collect.
    $allowsGoogle = in_array($selectedRole, ['buyer', 'seller'], true);
    $hasUploads = in_array($selectedRole, ['seller', 'courier', 'rider', 'logistics'], true);
@endphp

<div class="auth-panel auth-panel--register" data-auth-panel="register">
    <header class="auth-panel__header">
        @php $chosen = \App\Support\SignUpRoles::all()[$selectedRole]; @endphp
        {{-- Back to the account-type cards; anything typed here is left behind. --}}
        <a href="{{ route('register') }}" class="role-switch" aria-label="Signing up as {{ $chosen['name'] }}. Change account type">
            <span class="role-switch__icon" aria-hidden="true"><i class="fas {{ $chosen['icon'] }}"></i></span>
            <span>Signing up as <strong>{{ $chosen['name'] }}</strong></span>
            <span class="role-switch__change" aria-hidden="true">Change</span>
        </a>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1">{{ $roleCopy['title'] }}</h2>
        <p class="auth-panel__subtitle">{{ $roleCopy['subtitle'] }}</p>
    </header>

    <div class="auth-panel__alerts" data-auth-alerts>
        @if(session('status'))<div class="auth-alert auth-alert--success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="auth-alert auth-alert--error" role="alert">
                <p>Please check the highlighted details.</p>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
    </div>

    @if($roleCopy['note'])<p class="partner-note">{{ $roleCopy['note'] }}</p>@endif

    {{-- Filled in by the wizard: "Step 2 of 6", the step's name and a bar. --}}
    <div class="auth-wizard__progress" data-wizard-progress hidden>
        <p class="auth-wizard__count" data-wizard-count aria-live="polite"></p>
        <h3 class="auth-wizard__title" data-wizard-title tabindex="-1"></h3>
        <ol class="auth-wizard__bar" data-wizard-bar aria-hidden="true"></ol>
    </div>

    <form method="POST" action="{{ route('register') }}" id="registerForm" class="auth-wizard" data-auth-form data-auth-wizard
          data-errors="{{ json_encode($errors->toArray()) }}" @if($hasUploads) enctype="multipart/form-data" @endif>
        @csrf
        <input type="hidden" name="role" id="selectedRole" value="{{ $selectedRole }}">

        <section class="auth-step" data-wizard-step data-step-title="Your personal details">
            <h3 class="auth-step__heading">Your personal details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" minlength="2" maxlength="50" data-person-name required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" minlength="2" maxlength="50" data-person-name required>
                </div>
            </div>

            <div class="form-group">
                <label for="phone">Mobile number</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="09171234567" autocomplete="tel" maxlength="13" inputmode="tel"
                       pattern="09[0-9]{9}|\+639[0-9]{9}" title="Enter 09XXXXXXXXX or +639XXXXXXXXX" data-phone-number="international" required>
                <p class="field-note">09XXXXXXXXX or +639XXXXXXXXX. Each mobile number can only be used for one account.</p>
            </div>

            @if(in_array($selectedRole, ['buyer', 'courier', 'rider'], true))
                <div class="form-group">
                    <label for="date_of_birth">Birthday</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ today()->subYears(18)->toDateString() }}" autocomplete="bday" data-adult-birthday required>
                    <p class="field-note">{{ in_array($selectedRole, ['courier', 'rider'], true) ? 'Couriers and riders must be at least 18 years old.' : 'You must be at least 18 years old to shop on ZAYLO.' }}</p>
                </div>
            @endif

            @if($selectedRole === 'logistics' && ! \Illuminate\Support\Facades\Schema::hasColumn('logistics_providers', 'sec_registration_no'))
                <div class="form-group">
                    <label for="company_name">Company name</label>
                    <input id="company_name" name="company_name" value="{{ old('company_name') }}" autocomplete="organization" minlength="2" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label for="service_area">Service area (optional)</label>
                    <input id="service_area" name="service_area" value="{{ old('service_area') }}" maxlength="150" placeholder="e.g. Metro Manila, Cavite">
                    <p class="field-note">Riders see this when choosing a partner to apply to.</p>
                </div>
            @endif
        </section>

        @if($selectedRole === 'seller' && \Illuminate\Support\Facades\Schema::hasTable('seller_applications'))
            @include('seller.partials.application-fields')
        @endif

        @if($selectedRole === 'logistics' && \Illuminate\Support\Facades\Schema::hasColumn('logistics_providers', 'sec_registration_no'))
            @include('logistics.partials.application-fields')
        @endif

        @if(in_array($selectedRole, ['courier', 'rider'], true))
            @include('auth.partials.rider-fields')
        @endif

        <section class="auth-step" data-wizard-step data-step-title="Your sign-in details">
            <h3 class="auth-step__heading">Your sign-in details</h3>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email"
                       pattern="[^@\s]+@[^@\s]+\.[^@\s]+" title="Enter an email address such as name@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Create a password" autocomplete="new-password" minlength="8"
                           aria-describedby="password-note password-entry-note" data-manual-password data-new-password required>
                    <button type="button" class="toggle-password" data-password="password" aria-controls="password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
                <p id="password-note" class="field-note">Use at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol.</p>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repeat your password" autocomplete="new-password"
                           aria-describedby="password-entry-note" data-manual-password data-password-confirmation required>
                    <button type="button" class="toggle-password" data-password="password_confirmation" aria-controls="password_confirmation" aria-label="Show confirmation password" aria-pressed="false">Show</button>
                </div>
            </div>

            <p id="password-entry-note" class="field-note" role="status" aria-live="polite">Please type both passwords manually. Copying, cutting, pasting and dropping text are disabled in these fields.</p>

            <div class="terms-consent">
                <label class="terms-consent__label" for="terms">
                    <input type="checkbox" id="terms" name="terms" value="1" @checked(old('terms')) required @if($errors->has('terms')) aria-invalid="true" @endif>
                    <span>I have read and agree to the <a href="{{ route('policies.show', 'terms') }}" target="_blank" rel="noopener">Terms of Service</a> and <a href="{{ route('policies.show', 'privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                </label>
                @error('terms')<p class="terms-consent__error" role="alert">{{ $message }}</p>@enderror
            </div>

            <p class="verification-note">We'll send a six-digit verification code to this email address. You'll need the code to finish creating your ZAYLO account.</p>
        </section>

        <div class="step-actions auth-wizard__nav">
            <button type="button" class="btn-back" data-wizard-back hidden>Back</button>
            <button type="button" class="btn-primary" data-wizard-next hidden>Continue</button>
            <button type="submit" class="btn-primary" id="create-button" data-wizard-submit data-busy-label="Sending verification code…">Send verification code</button>
        </div>
    </form>

    @if($allowsGoogle && Route::has('google.redirect'))
        <div class="auth-divider"><span>or</span></div>
        <a href="{{ route('google.redirect', ['role' => $selectedRole]) }}" class="btn-google" id="google-register-button" data-auth-native>
            @include('auth.partials.google-icon')
            <span>Continue with Google</span>
        </a>
    @endif

    <footer class="auth-panel__footer">
        <p>Already have an account? <a href="{{ route(['seller' => 'seller.login', 'logistics' => 'logistics.login'][$selectedRole] ?? 'login') }}">Sign in</a></p>
    </footer>
</div>
