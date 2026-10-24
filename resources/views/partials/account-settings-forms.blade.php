{{-- Profile and password panels. Expects $user and $routePrefix (e.g. "admin.account"). --}}
@php $hasPassword = filled($user->getAuthPassword()); @endphp

<section class="seller-panel" aria-labelledby="photo-heading">
    <header class="seller-panel__header">
        <div>
            <h2 id="photo-heading">Profile photo</h2>
            <p>Shown on your account and in your conversations.</p>
        </div>
    </header>
    <div class="seller-panel__body">@include('partials.profile-photo', ['user' => $user])</div>
</section>

<section class="seller-panel" aria-labelledby="profile-heading">
    <header class="seller-panel__header">
        <div>
            <h2 id="profile-heading">Personal information</h2>
            <p>Changing your email requires verifying the new address.</p>
        </div>
    </header>
    <form method="POST" action="{{ route($routePrefix.'.profile') }}" class="seller-panel__body seller-form">
        @csrf
        @method('PATCH')
        <div class="seller-form-grid">
            <div class="seller-field">
                <label for="profile-name">Full name</label>
                <input id="profile-name" name="name" autocomplete="name" maxlength="255" value="{{ old('name', $user->name) }}" data-person-name required @error('name') aria-invalid="true" @enderror>
                @error('name')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="profile-phone">Mobile number</label>
                <input id="profile-phone" type="tel" name="phone" autocomplete="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="Enter 11 digits starting with 09" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 09171234567" data-phone-number required @error('phone') aria-invalid="true" @enderror>
                @error('phone')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="profile-email">Email address</label>
                <input id="profile-email" type="email" name="email" autocomplete="email" maxlength="255" value="{{ old('email', $user->email) }}" @readonly($user->google_id) required @error('email') aria-invalid="true" @enderror>
                @error('email')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="profile-birthday">Date of birth</label>
                <input id="profile-birthday" type="date" name="date_of_birth" autocomplete="bday" max="{{ today()->toDateString() }}" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}" required @error('date_of_birth') aria-invalid="true" @enderror>
                @error('date_of_birth')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="seller-form__actions">
            <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> Save personal information</button>
        </div>
    </form>
</section>

<section class="seller-panel" aria-labelledby="password-heading">
    <header class="seller-panel__header">
        <div>
            <h2 id="password-heading">{{ $hasPassword ? 'Change password' : 'Create a password' }}</h2>
            <p>At least 8 characters with an uppercase letter, a lowercase letter, a number, and a symbol.</p>
        </div>
    </header>
    <form method="POST" action="{{ route($routePrefix.'.password') }}" class="seller-panel__body seller-form">
        @csrf
        @method('PUT')
        @if($hasPassword)
            <div class="seller-field">
                <label for="current-password">Current password</label>
                <input id="current-password" type="password" name="current_password" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
                @error('current_password')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
        @endif
        <div class="seller-form-grid">
            <div class="seller-field">
                <label for="new-password">New password</label>
                <input id="new-password" type="password" name="password" autocomplete="new-password" minlength="8" data-new-password required @error('password') aria-invalid="true" @enderror>
                @error('password')<p class="seller-field__error">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label for="password-confirmation">Confirm new password</label>
                <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </div>
        </div>
        <div class="seller-form__actions">
            <button type="submit" class="seller-button"><i class="fas fa-lock" aria-hidden="true"></i> {{ $hasPassword ? 'Update password' : 'Create password' }}</button>
        </div>
    </form>
</section>

@push('scripts')
    <script src="{{ asset('js/personal-details.js') }}?v={{ filemtime(public_path('js/personal-details.js')) }}" defer></script>
    <script src="{{ asset('js/password-requirements.js') }}?v={{ filemtime(public_path('js/password-requirements.js')) }}" defer></script>
@endpush
