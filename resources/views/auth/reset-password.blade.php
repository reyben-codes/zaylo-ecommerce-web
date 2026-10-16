@extends('layouts.auth')
@section('title', 'Choose Password · ZAYLO')
@push('styles')<style>body{margin:0;background:var(--c-faf7f2);font-family:Inter,sans-serif}.auth-simple{max-width:520px;margin:8vh auto;background:var(--c-ffffff);padding:56px;border:1px solid var(--c-ece4db);border-radius:16px}.auth-simple h1{font-family:'Playfair Display',serif}.auth-simple label{display:block;margin:18px 0}.auth-simple input{width:100%;box-sizing:border-box;padding:14px;border:1px solid var(--c-dddddd);border-radius:10px}.auth-simple button{padding:14px 24px;background:var(--c-1a1714);color:var(--c-ffffff);border:0;border-radius:8px;cursor:pointer}.error{color:var(--c-b42318)}</style>@endpush
@section('content')
<main class="auth-simple">
    <h1>Choose a new password</h1>
    <p id="password-note">Use at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol.</p>
    @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email<input type="email" name="email" value="{{ old('email', $email) }}" required></label>
        <label>New password<input type="password" name="password" autocomplete="new-password" minlength="8" aria-describedby="password-note" data-new-password required></label>
        <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
        <button>Reset password</button>
    </form>
</main>
@endsection
@push('scripts')
<script src="{{ asset('js/password-requirements.js') }}?v={{ filemtime(public_path('js/password-requirements.js')) }}" defer></script>
@endpush
