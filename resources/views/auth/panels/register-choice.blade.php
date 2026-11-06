{{-- First sign-up step, shown in the sign-in modal: choose the account type. Each card says what the account is for,
     how long sign-up takes, who approves it and what to have ready, then opens that role's form (auth.panels.register). --}}
@php
    $roles = \App\Support\SignUpRoles::all();
@endphp

<div class="auth-panel" data-auth-panel="register-choice">
    <header class="auth-panel__header">
        <span class="auth-panel__label">JOIN ZAYLO</span>
        <h2 class="auth-panel__title" id="auth-modal-title" tabindex="-1">Create new account</h2>
        <p class="auth-panel__subtitle">How will you use ZAYLO? Pick one to see the sign-up form for it.</p>
    </header>

    <div class="auth-panel__alerts" data-auth-alerts>
        @if(session('status'))<div class="auth-alert auth-alert--success" role="status">{{ session('status') }}</div>@endif
    </div>

    <nav class="role-choices" aria-label="Account types">
        @foreach($roles as $key => $role)
            <a href="{{ route('register', ['role' => $key]) }}" class="role-choice" data-role-choice="{{ $key }}">
                <span class="role-choice__icon" aria-hidden="true"><i class="fas {{ $role['icon'] }}"></i></span>
                <span class="role-choice__body">
                    <span class="role-choice__title">{{ $role['name'] }}</span>
                    <span class="role-choice__text">{{ $role['summary'] }}</span>
                    <span class="role-choice__meta">
                        <span><i class="fas fa-list-ol" aria-hidden="true"></i> {{ $role['steps'] }} steps</span>
                        <span><i class="fas {{ $role['approval'] ? 'fa-user-check' : 'fa-bolt' }}" aria-hidden="true"></i> {{ $role['approval'] ?? 'Ready right away' }}</span>
                        @if($role['needs'])<span><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $role['needs'] }}</span>@endif
                    </span>
                </span>
                <i class="fas fa-chevron-right role-choice__arrow" aria-hidden="true"></i>
            </a>
        @endforeach
    </nav>

    <footer class="auth-panel__footer">
        <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </footer>
</div>
