@extends('layouts.auth')

@section('title', 'ZAYLO · Login')

@push('styles')
<style>
    :root {
        --color-primary: #1a1714;
        --color-secondary: #b28b6f;
        --color-cream: #faf7f2;
        --color-off-white: #f5f0ea;
        --color-dark: #0a0a0a;
        --color-gray: #6b5f54;
        --color-light-gray: #e5dfd8;
        --color-border: #ece4db;
        --color-error: #c0392b;
        --color-success: #2d7d46;
        --font-serif: 'Playfair Display', 'Times New Roman', serif;
        --font-sans: 'Inter', 'Helvetica Neue', sans-serif;
        --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: var(--font-sans); background: var(--color-cream); color: var(--color-primary); overflow: hidden; height: 100vh; }
    .auth-container { display: flex; height: 100vh; width: 100%; background: white; }
    .auth-image { flex: 0 0 50%; height: 100vh; position: relative; overflow: hidden; background: url('https://images.unsplash.com/photo-1583394838336-acd977736f90?w=800&q=80&auto=format&fit=crop&crop=center') center/cover no-repeat; border-radius: 0 24px 24px 0; }
    .auth-image-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, transparent 60%, rgba(26, 23, 20, 0.7)); }
    .auth-image-content { position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; z-index: 2; }
    .auth-brand { display: flex; align-items: center; gap: 16px; }
    .auth-brand-line { width: 40px; height: 2px; background: white; opacity: 0.6; }
    .auth-image-text { color: white; margin-bottom: 40px; }
    .auth-image-text p { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 300; line-height: 1.4; opacity: 0.9; }
    .auth-image-text p:last-child { font-style: italic; opacity: 0.7; }
    .auth-form { flex: 1; padding: 52px 64px; overflow-y: auto; display: flex; align-items: center; background: white; position: relative; }
    .auth-close { position: absolute; top: 28px; right: 28px; background: none; border: none; font-size: 1.4rem; color: var(--color-gray); cursor: pointer; transition: var(--transition); padding: 10px; border-radius: 50%; }
    .auth-close:hover { color: var(--color-primary); background: var(--color-cream); }
    .auth-form-content { width: 100%; max-width: 520px; margin: 0 auto; }
    .auth-header { margin-bottom: 36px; }
    .auth-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--color-gray); font-weight: 400; display: block; margin-bottom: 10px; }
    .auth-header h1 { font-family: var(--font-serif); font-size: 2.8rem; font-weight: 600; color: var(--color-primary); margin-bottom: 6px; letter-spacing: -0.01em; }
    .auth-subtitle { font-size: 0.95rem; color: var(--color-gray); font-weight: 400; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 500; color: var(--color-primary); margin-bottom: 8px; letter-spacing: 0.02em; }
    .form-group input { width: 100%; padding: 14px 18px; border: 1px solid var(--color-border); border-radius: 4px; font-size: 0.95rem; font-family: var(--font-sans); transition: var(--transition); outline: none; background: white; color: var(--color-primary); }
    .form-group input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(26, 23, 20, 0.05); }
    .form-group input::placeholder { color: #c5c0b8; }
    .form-group input.error { border-color: var(--color-error); }
    .password-input-wrapper { position: relative; }
    .password-input-wrapper input { padding-right: 48px; }
    .toggle-password { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-gray); cursor: pointer; font-size: 1rem; padding: 4px; transition: var(--transition); }
    .toggle-password:hover { color: var(--color-primary); }
    .form-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .remember-me { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--color-gray); cursor: pointer; }
    .remember-me input[type="checkbox"] { accent-color: var(--color-primary); width: 16px; height: 16px; cursor: pointer; }
    .forgot-password { font-size: 0.78rem; color: var(--color-gray); text-decoration: none; transition: var(--transition); }
    .forgot-password:hover { color: var(--color-primary); }
    .btn-auth-primary { width: 100%; padding: 16px; background: var(--color-primary); color: white; border: none; border-radius: 4px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer; transition: var(--transition); font-family: var(--font-sans); }
    .btn-auth-primary:hover { background: var(--color-secondary); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .auth-divider { text-align: center; margin: 24px 0; position: relative; }
    .auth-divider::before { content: ''; position: absolute; left: 0; top: 50%; width: 100%; height: 1px; background: var(--color-border); }
    .auth-divider span { background: white; padding: 0 18px; font-size: 0.7rem; color: var(--color-gray); position: relative; text-transform: uppercase; letter-spacing: 0.04em; }
    .social-login { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 28px; }
    .btn-social { padding: 13px; background: white; border: 1px solid var(--color-border); border-radius: 4px; font-size: 0.8rem; font-weight: 500; font-family: var(--font-sans); cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 10px; color: var(--color-primary); }
    .btn-social:hover { border-color: var(--color-primary); background: var(--color-cream); }
    .btn-social i { font-size: 1.05rem; }
    .btn-social.btn-google i { color: #ea4335; }
    .auth-footer { text-align: center; font-size: 0.82rem; color: var(--color-gray); }
    .auth-footer a { color: var(--color-primary); text-decoration: none; font-weight: 500; transition: var(--transition); }
    .auth-footer a:hover { color: var(--color-secondary); text-decoration: underline; }
    .alert-error { background: #fdf2f2; border: 1px solid #f5c6c6; color: var(--color-error); padding: 12px 16px; border-radius: 4px; font-size: 0.85rem; margin-bottom: 20px; }
    @media (max-width: 820px) { .auth-container { flex-direction: column; } .auth-image { flex: 0 0 220px; height: 220px; border-radius: 0 0 24px 24px; } .auth-form { padding: 36px 28px; } .social-login { grid-template-columns: 1fr; } }
    @media (max-width: 480px) { .auth-image { flex: 0 0 160px; height: 160px; } .auth-form { padding: 28px 18px; } .auth-header h1 { font-size: 2rem; } .role-options { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="auth-container">
    <!-- Left Side - Image -->
    <div class="auth-image">
        <div class="auth-image-content">
            <div class="auth-brand">
                <span class="auth-brand-line"></span>
                <span class="auth-brand-name">
                    <img src="{{ asset('images/ZAYLO_LOGO_LIGHT.png') }}" alt="ZAYLO" style="height:36px;width:auto;display:block;">
                </span>
            </div>
            <div class="auth-image-text">
                <p>Curated fashion.</p>
                <p>Thoughtfully chosen.</p>
            </div>
        </div>
        <div class="auth-image-overlay"></div>
    </div>

    <!-- Right Side - Form -->
    <div class="auth-form">
        <a href="{{ route('home') }}" class="auth-close">
            <i class="fas fa-times"></i>
        </a>

        <div class="auth-form-content">
            <div class="auth-header">
                <span class="auth-label">ZAYLO ACCOUNT</span>
                <h1 id="welcomeHeading">Welcome Back</h1>
                <p class="auth-subtitle">Sign in to continue to your ZAYLO experience.</p>
            </div>

            @if ($errors->any())
                <div class="alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert-error">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           placeholder="Enter your email address" required />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required />
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" checked />
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-auth-primary">Sign In</button>
            </form>

            <div class="auth-divider">
                <span>or continue with</span>
            </div>

            <div class="social-login">
                <button class="btn-social btn-google">
                    <i class="fab fa-google"></i> Google
                </button>
            </div>

            <div class="auth-footer">
                <p>Don't have an account? <a href="{{ route('register') }}">Create an account</a></p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
@endpush
