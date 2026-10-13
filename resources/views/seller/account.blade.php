@extends('layouts.seller')

@section('title', 'Account')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-account.css') }}?v={{ filemtime(public_path('css/seller-account.css')) }}">
@endpush

@section('content')
    @php
        $hasPassword = filled($user->getAuthPassword());
        $statusLabels = ['approved' => 'Approved', 'pending' => 'Pending approval', 'suspended' => 'Suspended', 'rejected' => 'Rejected'];
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Account</h1>
            <p>Manage your store details, personal information, and sign-in security.</p>
        </div>
        @if($shopStatus)
            <span class="shop-status shop-status--{{ $shopStatus }}">{{ $statusLabels[$shopStatus] ?? Str::headline($shopStatus) }}</span>
        @endif
    </section>

    <div class="seller-panel-grid account-grid">
        <div class="account-column">
            <section class="seller-panel" aria-labelledby="store-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="store-heading">Store details</h2>
                        <p>Buyers see your store name on product pages and orders.</p>
                    </div>
                </header>
                <form method="POST" action="{{ route('seller.account.store') }}" class="seller-panel__body seller-form">
                    @csrf
                    @method('PATCH')
                    <div class="seller-field">
                        <label for="store-name">Store name</label>
                        <input id="store-name" name="store_name" value="{{ old('store_name', $storeName) }}" minlength="3" maxlength="30" required @error('store_name') aria-invalid="true" aria-describedby="store-name-error" @enderror>
                        @error('store_name')<p class="seller-field__error" id="store-name-error">{{ $message }}</p>@enderror
                    </div>
                    @if($supportsStoreDescription)
                        <div class="seller-field">
                            <label for="store-description">About your store</label>
                            <textarea id="store-description" name="store_description" maxlength="1000" aria-describedby="store-description-help">{{ old('store_description', $storeDescription) }}</textarea>
                            <p class="seller-field__help" id="store-description-help">Optional. Up to 1,000 characters.</p>
                            @error('store_description')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="seller-form__actions">
                        <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> Save store details</button>
                    </div>
                </form>
            </section>

            <section class="seller-panel" aria-labelledby="profile-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="profile-heading">Personal information</h2>
                        <p>Changing your email requires verifying the new address.</p>
                    </div>
                </header>
                <form method="POST" action="{{ route('seller.account.profile') }}" class="seller-panel__body seller-form">
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
                            @if($user->google_id)
                                <p class="seller-field__help">Linked to Google sign-in and can't be changed here.</p>
                            @endif
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
        </div>

        <div class="account-column">
            <section class="seller-panel" aria-labelledby="password-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="password-heading">{{ $hasPassword ? 'Change password' : 'Create a password' }}</h2>
                        <p>{{ $hasPassword ? 'You will stay signed in on this device.' : 'Lets you sign in with email as well as Google.' }}</p>
                    </div>
                </header>
                <form method="POST" action="{{ route('seller.account.password') }}" class="seller-panel__body seller-form">
                    @csrf
                    @method('PUT')
                    @if($hasPassword)
                        <div class="seller-field">
                            <label for="current-password">Current password</label>
                            <input id="current-password" type="password" name="current_password" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
                            @error('current_password')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <div class="seller-field">
                        <label for="new-password">New password</label>
                        <input id="new-password" type="password" name="password" autocomplete="new-password" minlength="8" aria-describedby="new-password-help" data-new-password required @error('password') aria-invalid="true" @enderror>
                        <p class="seller-field__help" id="new-password-help">At least 8 characters with an uppercase letter, a lowercase letter, a number, and a symbol.</p>
                        @error('password')<p class="seller-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="seller-field">
                        <label for="password-confirmation">Confirm new password</label>
                        <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="seller-form__actions">
                        <button type="submit" class="seller-button"><i class="fas fa-lock" aria-hidden="true"></i> {{ $hasPassword ? 'Update password' : 'Create password' }}</button>
                    </div>
                </form>
            </section>

            <section class="seller-panel" aria-labelledby="address-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="address-heading">Saved addresses</h2>
                        <p>{{ $addressCount }} {{ Str::plural('address', $addressCount) }} on file</p>
                    </div>
                    <a href="{{ route('addresses.index') }}" class="seller-link">Manage <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </header>
                <div class="seller-panel__body">
                    @if($defaultAddress)
                        <div class="account-address">
                            <i class="fas fa-location-dot" aria-hidden="true"></i>
                            <div>
                                <strong>{{ $defaultAddress->label }}{{ $defaultAddress->is_default ? ' · Default' : '' }}</strong>
                                <p>{{ $defaultAddress->formatted() }}</p>
                            </div>
                        </div>
                    @else
                        <p class="account-muted">No saved address yet.</p>
                        <a href="{{ route('addresses.create') }}" class="seller-button seller-button--secondary seller-button--small"><i class="fas fa-plus" aria-hidden="true"></i> Add address</a>
                    @endif
                </div>
            </section>

            <section class="seller-panel" aria-labelledby="summary-heading">
                <header class="seller-panel__header">
                    <h2 id="summary-heading">Account summary</h2>
                </header>
                <dl class="seller-panel__body account-summary">
                    <div><dt>Email</dt><dd>{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</dd></div>
                    <div><dt>Sign-in</dt><dd>{{ $user->google_id ? ($hasPassword ? 'Google and email' : 'Google') : 'Email and password' }}</dd></div>
                    <div><dt>Seller since</dt><dd>{{ $user->created_at?->timezone('Asia/Manila')->format('F j, Y') ?? '—' }}</dd></div>
                </dl>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/personal-details.js') }}?v={{ filemtime(public_path('js/personal-details.js')) }}" defer></script>
    <script src="{{ asset('js/password-requirements.js') }}?v={{ filemtime(public_path('js/password-requirements.js')) }}" defer></script>
@endpush
