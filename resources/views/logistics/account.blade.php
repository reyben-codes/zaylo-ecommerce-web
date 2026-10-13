@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Account')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Account</h1>
            <p>Company details riders see when they apply, plus your own profile and password.</p>
        </div>
        <span class="role-pill account-status--{{ $provider->status === 'approved' ? 'active' : $provider->status }}">{{ $provider->status === 'approved' ? 'Approved partner' : Str::headline($provider->status) }}</span>
    </section>

    <div class="seller-panel-grid workspace-account-grid">
        <div class="workspace-column">
            <section class="seller-panel" aria-labelledby="company-heading">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="company-heading">Company details</h2>
                        <p>Riders pick your company by this name when they register.</p>
                    </div>
                </header>
                <form method="POST" action="{{ route('logistics.account.company') }}" class="seller-panel__body seller-form">
                    @csrf
                    @method('PATCH')
                    <div class="seller-form-grid">
                        <div class="seller-field seller-field--full">
                            <label for="company-name">Company name</label>
                            <input id="company-name" name="company_name" value="{{ old('company_name', $provider->name) }}" minlength="2" maxlength="150" required @error('company_name') aria-invalid="true" @enderror>
                            @error('company_name')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="seller-field">
                            <label for="contact-phone">Dispatch phone</label>
                            <input id="contact-phone" type="tel" name="contact_phone" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" value="{{ old('contact_phone', $provider->contact_phone) }}" required @error('contact_phone') aria-invalid="true" @enderror>
                            @error('contact_phone')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="seller-field">
                            <label for="service-area">Service area</label>
                            <input id="service-area" name="service_area" maxlength="150" value="{{ old('service_area', $provider->service_area) }}" placeholder="e.g. Metro Manila, Cavite">
                            @error('service_area')<p class="seller-field__error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="seller-form__actions">
                        <button type="submit" class="seller-button"><i class="fas fa-check" aria-hidden="true"></i> Save company details</button>
                    </div>
                </form>
            </section>

            @include('partials.account-settings-forms', ['user' => $user, 'routePrefix' => 'logistics.account'])
        </div>

        <div class="workspace-column">
            <section class="seller-panel" aria-labelledby="summary-heading">
                <header class="seller-panel__header"><h2 id="summary-heading">Partner summary</h2></header>
                <dl class="seller-panel__body workspace-detail-list">
                    <div><dt>Status</dt><dd>{{ Str::headline($provider->status) }}</dd></div>
                    <div><dt>Approved on</dt><dd>{{ $provider->approved_at?->timezone('Asia/Manila')->format('F j, Y') ?? '—' }}</dd></div>
                    <div><dt>Riders</dt><dd>{{ $provider->riders()->where('status', 'approved')->count() }} active</dd></div>
                </dl>
            </section>
        </div>
    </div>
@endsection
