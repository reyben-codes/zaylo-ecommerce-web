@push('styles')
<style>
    :root {
        --ink: #1a1714;
        --muted: #6b5f54;
        --accent: #b28b6f;
        --cream: #faf7f2;
        --border: #ece4db;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    [hidden] { display: none !important; }
    body { font-family: 'Inter', sans-serif; color: var(--ink); background: white; }
    .auth-container { display: flex; min-height: 100vh; min-height: 100svh; }
    .auth-image { flex: 0 0 50%; position: sticky; top: 0; align-self: flex-start; height: 100vh; min-height: 720px; background: #39291d url('{{ asset('images/login-marketplace.png') }}') center/cover no-repeat; border-radius: 0 24px 24px 0; overflow: hidden; }
    .auth-image::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 60%, rgba(26,23,20,.7)); }
    .auth-image-content { position: absolute; inset: 0; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; }
    .auth-brand { display: flex; align-items: center; gap: 16px; }
    .auth-brand::before { content: ''; width: 40px; height: 2px; background: #fff9; }
    .auth-brand img { height: 36px; width: auto; display: block; }
    .auth-image-text { color: white; font-family: 'Playfair Display', serif; font-size: 1.6rem; line-height: 1.4; }
    .auth-image-text p:last-child { font-style: italic; opacity: .8; }
    .auth-form { flex: 1; min-width: 0; padding: 100px 64px 48px; position: relative; display: flex; align-items: center; }
    .auth-form-content { width: 100%; max-width: 520px; margin: auto; }
    .continue-shopping { position: absolute; top: 28px; left: 64px; display: inline-flex; gap: 8px; align-items: center; min-height: 44px; color: var(--muted); font-size: .82rem; text-decoration: none; }
    .continue-shopping:hover { color: var(--ink); text-decoration: underline; }
    a:focus-visible, button:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
    .auth-label { display: block; font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
    .auth-header { margin-bottom: 28px; }
    h1 { font-family: 'Playfair Display', serif; font-size: 2.6rem; font-weight: 600; margin-bottom: 8px; line-height: 1.2; }
    .auth-subtitle { color: var(--muted); font-size: .95rem; line-height: 1.65; }
    .step-indicator { display: flex; list-style: none; gap: 20px; margin-bottom: 28px; }
    .step-item { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding-bottom: 14px; border-bottom: 2px solid var(--border); font-size: .75rem; color: var(--muted); }
    .step-item.completed { border-color: var(--accent); }
    .step-item[aria-current="step"] { border-color: var(--ink); color: var(--ink); font-weight: 600; }
    .step-number { border: 1px solid var(--border); border-radius: 50%; display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; font-size: .68rem; }
    .step-item[aria-current="step"] .step-number { background: var(--ink); color: white; border-color: var(--ink); }
    .step-title { font-size: 1rem; font-weight: 600; margin-bottom: 20px; }
    .form-group { margin-bottom: 20px; min-width: 0; }
    label { display: block; font-size: .75rem; font-weight: 500; margin-bottom: 8px; }
    label span { color: var(--muted); font-weight: 400; }
    input, textarea, select { width: 100%; padding: 14px 18px; border: 1px solid var(--border); border-radius: 4px; font: inherit; font-size: .95rem; color: var(--ink); background: white; }
    input:focus, textarea:focus, select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    input::placeholder, textarea::placeholder { color: #9b9188; }
    textarea { min-height: 95px; resize: vertical; line-height: 1.5; }
    .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .password-input-wrapper { position: relative; }
    .password-input-wrapper input { padding-right: 62px; }
    .toggle-password { position: absolute; top: 50%; right: 8px; transform: translateY(-50%); border: 0; background: none; color: var(--muted); font: inherit; font-size: .75rem; min-width: 44px; min-height: 44px; cursor: pointer; }
    .field-note, .verification-note { color: var(--muted); font-size: .75rem; line-height: 1.6; margin-top: 8px; }
    .step-actions { display: flex; gap: 12px; margin-top: 26px; }
    .btn-primary, .btn-back { min-height: 48px; padding: 14px 20px; border-radius: 4px; font: inherit; font-size: .8rem; cursor: pointer; }
    .btn-primary { flex: 1; background: var(--ink); color: white; border: 1px solid var(--ink); font-weight: 600; text-transform: uppercase; letter-spacing: .07em; }
    .btn-primary:hover { background: #46392e; }
    .btn-primary:disabled { opacity: .65; cursor: wait; }
    .btn-back { background: white; border: 1px solid var(--border); color: var(--muted); }
    .auth-footer { border-top: 1px solid var(--border); padding-top: 22px; margin-top: 28px; text-align: center; font-size: .82rem; color: var(--muted); line-height: 1.65; }
    .auth-footer a { color: var(--ink); font-weight: 500; text-decoration: none; }
    .auth-footer a:hover { text-decoration: underline; }
    .partner-links { margin-top: 14px; font-size: .75rem; display: flex; justify-content: center; gap: 8px 18px; flex-wrap: wrap; }
    .partner-links a { color: var(--muted); font-weight: 400; }
    .partner-note { padding: 12px 16px; background: var(--cream); border: 1px solid var(--border); font-size: .8rem; color: var(--muted); line-height: 1.6; margin-bottom: 24px; }
    .alert-error { border: 1px solid #f5c6c6; background: #fdf2f2; color: #a52b23; padding: 14px 18px; border-radius: 4px; margin-bottom: 24px; font-size: .85rem; line-height: 1.6; }
    .alert-error ul { padding-left: 18px; }
    .alert-status { padding: 14px 18px; margin-bottom: 24px; background: var(--cream); border: 1px solid var(--border); font-size: .85rem; line-height: 1.6; }
    .email-address { overflow-wrap: anywhere; font-weight: 600; color: var(--ink); }
    .otp-input { text-align: center; font-size: 1.8rem; letter-spacing: .35em; font-variant-numeric: tabular-nums; }
    .resend-form { margin-top: 20px; text-align: center; }
    .resend-form .btn-back { margin-top: 8px; }
    
.auth-divider {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 24px 0;
    color: #8a8a8a;
    font-size: 13px;
    font-weight: 600;
}

.auth-divider::before,
.auth-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #e5e5e5;
}

.btn-google {
    width: 100%;
    min-height: 48px;

    display: flex;
    justify-content: center;
    align-items: center;

    gap: 12px;

    padding: 12px 18px;

    background: #ffffff;
    color: #222222;

    border: 1px solid #d8d8d8;
    border-radius: 8px;

    font-size: 15px;
    font-weight: 600;

    text-decoration: none;

    cursor: pointer;

    transition:
        border-color 0.2s ease,
        background-color 0.2s ease,
        box-shadow 0.2s ease;
}

.btn-google:hover:not(:disabled) {
    background: #fafafa;
    border-color: #bcbcbc;

    box-shadow:
        0 2px 6px rgba(0, 0, 0, 0.08);
}

.btn-google:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.google-icon {
    flex-shrink: 0;
}

    @media (max-width: 820px) {
        .auth-container { flex-direction: column; }
        .auth-image { flex: none; position: relative; align-self: stretch; height: 260px; min-height: 0; border-radius: 0 0 24px 24px; background-size: 55% auto; background-position: right 65%; }
        .auth-image::before { background: linear-gradient(90deg, #39291d 35%, transparent 85%); }
        .auth-image-content { padding: 28px; }
        .auth-image-text { max-width: 55%; font-size: 1.4rem; }
        .auth-form { padding: 84px 28px 40px; }
        .continue-shopping { top: 20px; left: 28px; }
    }
    @media (max-width: 480px) {
        .auth-image { height: 220px; }
        .auth-image-content { padding: 24px; }
        .auth-image-text { font-size: 1.15rem; }
        .auth-brand::before { display: none; }
        .auth-form { padding: 80px 24px 36px; }
        .continue-shopping { left: 24px; }
        h1 { font-size: 2rem; }
        .form-row { grid-template-columns: 1fr; gap: 0; }
        .step-indicator { gap: 12px; }
        .step-item { gap: 8px; }
    }
</style>
@endpush
