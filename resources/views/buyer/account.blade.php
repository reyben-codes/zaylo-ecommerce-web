@extends('layouts.app')

@section('title', 'Profile Settings · ZAYLO')



@section('content')
@php
    $addressTypes = [
        'Home' => 'fa-house',
        'Work' => 'fa-briefcase',
        'School' => 'fa-graduation-cap',
        'Other' => 'fa-location-dot',
    ];
@endphp
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-user-gear" aria-hidden="true"></i>
        <div>
            <h1>Profile Settings</h1>
            <p>Keep your personal details, password, and delivery addresses up to date.</p>
        </div>
    </div>
</div>

<div class="page-content settings-layout">
    <aside class="settings-sidebar" aria-label="Profile settings navigation">
        <div class="profile-summary">
            <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
        </div>
        <nav>
            <a href="#personal-details"><i class="far fa-user"></i> Personal details</a>
            <a href="#delivery-addresses"><i class="fas fa-location-dot"></i> Delivery addresses</a>
            <a href="#security"><i class="fas fa-lock"></i> Password</a>
        </nav>
    </aside>

    <main class="settings-main">
        <section class="settings-card" id="personal-details">
            <div class="settings-card-heading">
                <div><span class="eyebrow">Account</span><h2>Personal details</h2></div>
                <span class="status-pill"><i class="fas fa-circle-check"></i> Verified buyer</span>
            </div>
            <form method="POST" action="{{ route('buyer.account.profile') }}" class="market-form stacked-form">
                @csrf
                @method('PATCH')
                <div class="form-row">
                    <label for="profile-name">Full name
                        <input id="profile-name" name="name" autocomplete="name" maxlength="255" value="{{ old('name', $user->name) }}" data-person-name required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="profile-phone">Mobile number
                        <input id="profile-phone" type="tel" name="phone" autocomplete="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="Enter 11 digits starting with 09" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 09171234567" data-phone-number required>
                        @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label for="profile-email">Email address
                    <input id="profile-email" type="email" name="email" autocomplete="email" maxlength="255" value="{{ old('email', $user->email) }}" @readonly($user->google_id) required>
                    @if($user->google_id)<span>Managed by your connected Google account.</span>@else<span>Changing your email requires verification of the new address.</span>@endif
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </label>
                <div class="form-row">
                    <label for="profile-birthday">Birthday
                        <input id="profile-birthday" type="date" name="date_of_birth" autocomplete="bday" max="{{ today()->toDateString() }}" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}" required>
                        <span>Your birthday is used to keep your age accurate.</span>
                        @error('date_of_birth')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="profile-age">Current age
                        <input id="profile-age" value="{{ $user->age === null ? 'Add your birthday' : $user->age.' years old' }}" readonly aria-describedby="profile-age-note">
                        <span id="profile-age-note">Calculated automatically.</span>
                    </label>
                </div>
                <div><button class="market-button" type="submit">Save profile</button></div>
            </form>
        </section>

        <section class="settings-card" id="delivery-addresses">
            <div class="settings-card-heading">
                <div><span class="eyebrow">Delivery</span><h2>Buyer addresses</h2><p>Save an address by type, then choose your default delivery location.</p></div>
                <a class="market-button small-button" href="{{ route('addresses.create') }}"><i class="fas fa-plus"></i> Add address</a>
            </div>

            <div class="address-type-actions" aria-label="Add an address by type">
                @foreach($addressTypes as $type => $icon)
                    <a href="{{ route('addresses.create', ['type' => $type]) }}"><i class="fas {{ $icon }}"></i><span>Add {{ $type }}</span></a>
                @endforeach
            </div>

            <div class="settings-address-list">
                @forelse($addresses as $address)
                    @php($icon = $addressTypes[$address->label] ?? $addressTypes['Other'])
                    <article class="settings-address-card">
                        <div class="address-card-icon"><i class="fas {{ $icon }}" aria-hidden="true"></i></div>
                        <div class="address-card-copy">
                            <div class="address-card-title"><h3>{{ $address->label }}</h3>@if($address->is_default)<span class="status-pill">Default</span>@endif</div>
                            <strong>{{ $address->recipient_name }}</strong>
                            <p>{{ $address->formatted() }}</p>
                            <small>{{ $address->phone }}</small>
                            @unless($address->isStructured())<p class="field-error">Complete the location details before checkout.</p>@endunless
                        </div>
                        <div class="address-card-actions">
                            <a class="text-button" href="{{ route('addresses.edit', $address) }}"><i class="far fa-pen-to-square" aria-hidden="true"></i> Edit</a>
                            @unless($address->is_default)
                                <form method="POST" action="{{ route('addresses.default', $address) }}">@csrf @method('PATCH')<button class="text-button">Use for delivery</button></form>
                            @endunless
                        </div>
                    </article>
                @empty
                    <div class="address-empty-state"><i class="far fa-map"></i><p>You have no saved delivery address yet.</p><a href="{{ route('addresses.create', ['type' => 'Home']) }}">Add your home address</a></div>
                @endforelse
            </div>
            @if($addresses->isNotEmpty())<a class="manage-address-link" href="{{ route('addresses.index') }}">Manage all saved addresses <i class="fas fa-arrow-right"></i></a>@endif
        </section>

        <section class="settings-card" id="security">
            <div class="settings-card-heading">
                <div><span class="eyebrow">Security</span><h2>{{ filled($user->getAuthPassword()) ? 'Change password' : 'Create a password' }}</h2></div>
            </div>
            <form method="POST" action="{{ route('buyer.account.password') }}" class="market-form stacked-form">
                @csrf
                @method('PUT')
                @if(filled($user->getAuthPassword()))
                    <label for="current-password">Current password
                        <input id="current-password" type="password" name="current_password" autocomplete="current-password" required>
                        @error('current_password')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                @else
                    <p class="empty-note">Add a password if you would also like to sign in with your email address.</p>
                @endif
                <div class="form-row">
                    <label for="new-password">New password
                        <input id="new-password" type="password" name="password" autocomplete="new-password" minlength="8" aria-describedby="profile-password-note" data-new-password required>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="password-confirmation">Confirm new password
                        <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    </label>
                </div>
                <p id="profile-password-note" class="empty-note">Use at least 8 characters, one uppercase letter, one lowercase letter, and one symbol.</p>
                <div><button class="market-button" type="submit">{{ filled($user->getAuthPassword()) ? 'Update password' : 'Create password' }}</button></div>
            </form>
        </section>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/personal-details.js') }}?v={{ filemtime(public_path('js/personal-details.js')) }}" defer></script>
<script src="{{ asset('js/password-requirements.js') }}?v={{ filemtime(public_path('js/password-requirements.js')) }}" defer></script>
<script>
(() => {
    const birthday = document.getElementById('profile-birthday');
    const age = document.getElementById('profile-age');

    if (!birthday || !age) return;

    const updateAge = () => {
        const parts = birthday.value.split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            age.value = 'Add your birthday';
            return;
        }

        const today = new Date();
        let years = today.getFullYear() - parts[0];
        if (today.getMonth() + 1 < parts[1] ||
            (today.getMonth() + 1 === parts[1] && today.getDate() < parts[2])) {
            years--;
        }

        age.value = years >= 0 ? `${years} years old` : 'Invalid birthday';
    };

    birthday.addEventListener('input', updateAge);
    updateAge();
})();
</script>
@endpush
