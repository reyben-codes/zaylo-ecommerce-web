@extends('layouts.auth')

@section('title', 'ZAYLO · Create Account')

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
        --color-warning: #e67e22;
        --font-serif: 'Playfair Display', 'Times New Roman', serif;
        --font-sans: 'Inter', 'Helvetica Neue', sans-serif;
        --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: var(--font-sans); background: var(--color-cream); color: var(--color-primary); overflow: hidden; height: 100vh; }
    .auth-container { display: flex; height: 100vh; width: 100%; background: white; }
    .auth-image { flex: 0 0 50%; height: 100vh; position: relative; overflow: hidden; background: url('https://images.unsplash.com/photo-1539008835657-9e8e9680c956?w=800&q=80&auto=format&fit=crop&crop=center') center/cover no-repeat; border-radius: 0 24px 24px 0; }
    .auth-image-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, transparent 60%, rgba(26,23,20,0.7)); }
    .auth-image-content { position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; z-index: 2; }
    .auth-brand { display: flex; align-items: center; gap: 16px; }
    .auth-brand-line { width: 40px; height: 2px; background: white; opacity: 0.6; }
    .auth-image-text { color: white; margin-bottom: 40px; }
    .auth-image-text p { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 300; line-height: 1.4; opacity: 0.9; }
    .auth-image-text p:last-child { font-style: italic; opacity: 0.7; }
    .auth-form { flex: 1; padding: 40px 64px; overflow-y: auto; display: flex; align-items: flex-start; padding-top: 64px; background: white; position: relative; }
    .auth-close { position: absolute; top: 28px; right: 28px; background: none; border: none; font-size: 1.4rem; color: var(--color-gray); cursor: pointer; transition: var(--transition); padding: 10px; border-radius: 50%; text-decoration: none; }
    .auth-close:hover { color: var(--color-primary); background: var(--color-cream); }
    .auth-form-content { width: 100%; max-width: 520px; margin: 0 auto; }
    .auth-header { margin-bottom: 28px; }
    .auth-label { font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--color-gray); font-weight: 400; display: block; margin-bottom: 10px; }
    .auth-header h1 { font-family: var(--font-serif); font-size: 2.6rem; font-weight: 600; color: var(--color-primary); margin-bottom: 6px; }
    .auth-subtitle { font-size: 0.95rem; color: var(--color-gray); }
    /* Step indicator */
    .step-indicator { display: flex; align-items: center; margin-bottom: 32px; }
    .step-item { display: flex; align-items: center; gap: 8px; }
    .step-number { width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid var(--color-border); font-size: 0.65rem; font-weight: 600; display: flex; align-items: center; justify-content: center; color: var(--color-gray); transition: var(--transition); }
    .step-item.active .step-number { border-color: var(--color-primary); background: var(--color-primary); color: white; }
    .step-item.completed .step-number { display: none; }
    .step-check { display: none; width: 32px; height: 32px; border-radius: 50%; background: var(--color-success); color: white; font-size: 0.75rem; align-items: center; justify-content: center; }
    .step-item.completed .step-check { display: flex; }
    .step-label { font-size: 0.7rem; color: var(--color-gray); font-weight: 500; }
    .step-item.active .step-label { color: var(--color-primary); }
    .step-line { flex: 1; height: 1.5px; background: var(--color-border); margin: 0 12px; min-width: 30px; }
    /* Form */
    .step-content { display: none; }
    .step-content.active { display: block; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 500; color: var(--color-primary); margin-bottom: 8px; }
    .form-group label .required { color: var(--color-error); margin-left: 2px; }
    .form-group input, .form-group select { width: 100%; padding: 14px 18px; border: 1px solid var(--color-border); border-radius: 4px; font-size: 0.95rem; font-family: var(--font-sans); transition: var(--transition); outline: none; background: white; color: var(--color-primary); appearance: none; -webkit-appearance: none; }
    .form-group input:focus, .form-group select:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(26,23,20,0.05); }
    .form-group input::placeholder { color: #c5c0b8; }
    .form-group select:disabled { background: var(--color-off-white); color: var(--color-light-gray); cursor: not-allowed; }
    .select-wrapper { position: relative; }
    .select-wrapper::after { content: ''; position: absolute; right: 18px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 6px solid var(--color-gray); pointer-events: none; transition: var(--transition); }
    .select-wrapper.loading::after { display: none; }
    .select-spinner { display: none; position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; border: 2px solid var(--color-border); border-top-color: var(--color-primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
    .select-wrapper.loading .select-spinner { display: block; }
    @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
    .address-error { font-size: 0.75rem; color: var(--color-error); margin-top: 6px; display: none; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .password-input-wrapper { position: relative; }
    .password-input-wrapper input { padding-right: 48px; }
    .toggle-password { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-gray); cursor: pointer; font-size: 1rem; }
    .btn-auth-primary { width: 100%; padding: 16px; background: var(--color-primary); color: white; border: none; border-radius: 4px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer; transition: var(--transition); font-family: var(--font-sans); }
    .btn-auth-primary:hover { background: var(--color-secondary); transform: translateY(-1px); }
    .step-actions { display: flex; gap: 12px; margin-top: 28px; }
    .btn-step-back { padding: 16px 28px; background: transparent; border: 1px solid var(--color-border); border-radius: 4px; font-size: 0.75rem; font-weight: 500; font-family: var(--font-sans); cursor: pointer; transition: var(--transition); color: var(--color-gray); flex: 1; }
    .btn-step-back:hover { border-color: var(--color-primary); color: var(--color-primary); }
    .btn-step-continue { flex: 2; }
    /* Role selector */
    .register-role-selector { margin-bottom: 24px; }
    .register-role-selector .role-option { display: flex; align-items: center; gap: 16px; padding: 16px 20px; border: 1px solid var(--color-border); cursor: pointer; transition: var(--transition); background: white; border-radius: 4px; margin-bottom: 10px; }
    .register-role-selector .role-option:hover { border-color: var(--color-gray); }
    .register-role-selector .role-option.active { border-color: var(--color-primary); background: var(--color-cream); }
    .register-role-selector .role-option .role-icon { width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: var(--color-cream); border-radius: 50%; color: var(--color-gray); flex-shrink: 0; }
    .register-role-selector .role-option.active .role-icon { background: var(--color-primary); color: white; }
    .register-role-selector .role-option .role-name { font-size: 0.85rem; font-weight: 600; display: block; color: var(--color-primary); }
    .register-role-selector .role-option .role-desc { font-size: 0.7rem; color: var(--color-gray); display: block; }
    .register-role-selector .role-option .role-check { display: none; color: var(--color-secondary); font-size: 1.1rem; }
    .register-role-selector .role-option.active .role-check { display: block; }
    /* Success */
    .success-content { text-align: center; padding: 48px 0; }
    .success-icon { font-size: 4.5rem; color: var(--color-success); margin-bottom: 20px; }
    .success-title { font-family: var(--font-serif); font-size: 2.4rem; font-weight: 600; color: var(--color-primary); margin-bottom: 10px; }
    .success-message, .success-submessage { color: var(--color-gray); margin-bottom: 10px; }
    .success-submessage { font-size: 0.9rem; margin-bottom: 36px; }
    .auth-footer { text-align: center; font-size: 0.82rem; color: var(--color-gray); margin-top: 20px; }
    .auth-footer a { color: var(--color-primary); text-decoration: none; font-weight: 500; }
    .alert-error { background: #fdf2f2; border: 1px solid #f5c6c6; color: var(--color-error); padding: 12px 16px; border-radius: 4px; font-size: 0.85rem; margin-bottom: 20px; }
    @media (max-width: 820px) { .auth-container { flex-direction: column; } .auth-image { flex: 0 0 220px; height: 220px; border-radius: 0 0 24px 24px; } .auth-form { padding: 36px 28px; padding-top: 56px; } .form-row { grid-template-columns: 1fr; } }
    @media (max-width: 480px) { .auth-image { flex: 0 0 160px; height: 160px; } .auth-form { padding: 28px 18px; padding-top: 44px; } }
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
                <p>Discover pieces that</p>
                <p>define your style.</p>
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
                <h1>Create Your Account</h1>
                <p class="auth-subtitle">Join ZAYLO and experience fashion made simple.</p>
            </div>

            @if ($errors->any())
                <div class="alert-error">{{ $errors->first() }}</div>
            @endif

            <!-- Step Indicator -->
            <div class="step-indicator" id="stepIndicator">
                <div class="step-item active" data-step="1">
                    <span class="step-number">01</span>
                    <span class="step-label">Account</span>
                    <span class="step-check"><i class="fas fa-check"></i></span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" data-step="2">
                    <span class="step-number">02</span>
                    <span class="step-label">Personal</span>
                    <span class="step-check"><i class="fas fa-check"></i></span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" data-step="3">
                    <span class="step-number">03</span>
                    <span class="step-label">Details</span>
                    <span class="step-check"><i class="fas fa-check"></i></span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" data-step="4">
                    <span class="step-number">04</span>
                    <span class="step-label">Complete</span>
                    <span class="step-check"><i class="fas fa-check"></i></span>
                </div>
            </div>

            <!-- Multi-step Form -->
            <form method="POST" action="{{ route('register') }}" id="registerForm">
                @csrf
                <input type="hidden" name="role" id="selectedRole" value="buyer" />

                <!-- Step 1: Choose Role -->
                <div class="step-content active" id="step1">
                    <div class="register-role-selector">
                        <div class="role-option active" data-role="buyer" onclick="selectRegisterRole(this)">
                            <div class="role-icon"><i class="fas fa-user"></i></div>
                            <div class="role-info">
                                <span class="role-name">Buyer</span>
                                <span class="role-desc">Shop for luxury fashion items</span>
                            </div>
                            <i class="fas fa-check-circle role-check"></i>
                        </div>
                        <div class="role-option" data-role="seller" onclick="selectRegisterRole(this)">
                            <div class="role-icon"><i class="fas fa-store"></i></div>
                            <div class="role-info">
                                <span class="role-name">Seller</span>
                                <span class="role-desc">List and sell your fashion products</span>
                            </div>
                            <i class="fas fa-check-circle role-check"></i>
                        </div>
                        <div class="role-option" data-role="courier" onclick="selectRegisterRole(this)">
                            <div class="role-icon"><i class="fas fa-motorcycle"></i></div>
                            <div class="role-info">
                                <span class="role-name">Courier / Rider</span>
                                <span class="role-desc">Deliver orders and earn commissions</span>
                            </div>
                            <i class="fas fa-check-circle role-check"></i>
                        </div>
                    </div>
                    <div class="step-actions">
                        <button type="button" class="btn-auth-primary btn-step-continue" onclick="nextStep(1)">Continue</button>
                    </div>
                </div>

                <!-- Step 2: Account Details -->
                <div class="step-content" id="step2">
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="your@email.com" required />
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" placeholder="Create a strong password" required minlength="8" />
                            <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repeat your password" required />
                            <button type="button" class="toggle-password" onclick="togglePassword('password_confirmation', this)">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="step-actions">
                        <button type="button" class="btn-step-back" onclick="prevStep(2)">Back</button>
                        <button type="button" class="btn-auth-primary btn-step-continue" onclick="nextStep(2)">Continue</button>
                    </div>
                </div>

                <!-- Step 3: Personal Info -->
                <div class="step-content" id="step3">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="First name" required />
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="Last name" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+63 900 000 0000" />
                    </div>

                    {{-- Hidden field that holds the full combined address for submission --}}
                    <input type="hidden" name="address" id="address" value="{{ old('address') }}" />

                    <div class="form-group">
                        <label for="addr_region">Region <span class="required">*</span></label>
                        <div class="select-wrapper" id="wrap_region">
                            <select id="addr_region" required>
                                <option value="">Select Region</option>
                            </select>
                            <span class="select-spinner"></span>
                        </div>
                        <p class="address-error" id="err_region">Please select a region.</p>
                    </div>

                    <div class="form-group">
                        <label for="addr_province">Province <span class="required">*</span></label>
                        <div class="select-wrapper" id="wrap_province">
                            <select id="addr_province" disabled required>
                                <option value="">Select Province</option>
                            </select>
                            <span class="select-spinner"></span>
                        </div>
                        <p class="address-error" id="err_province">Please select a province.</p>
                    </div>

                    <div class="form-group">
                        <label for="addr_city">City / Municipality <span class="required">*</span></label>
                        <div class="select-wrapper" id="wrap_city">
                            <select id="addr_city" disabled required>
                                <option value="">Select City / Municipality</option>
                            </select>
                            <span class="select-spinner"></span>
                        </div>
                        <p class="address-error" id="err_city">Please select a city or municipality.</p>
                    </div>

                    <div class="form-group">
                        <label for="addr_barangay">Barangay <span class="required">*</span></label>
                        <div class="select-wrapper" id="wrap_barangay">
                            <select id="addr_barangay" disabled required>
                                <option value="">Select Barangay</option>
                            </select>
                            <span class="select-spinner"></span>
                        </div>
                        <p class="address-error" id="err_barangay">Please select a barangay.</p>
                    </div>

                    <div class="form-group">
                        <label for="addr_street">Street / House No. / Unit</label>
                        <input type="text" id="addr_street" placeholder="e.g. 123 Rizal St., Unit 4B" />
                    </div>
                    <div class="step-actions">
                        <button type="button" class="btn-step-back" onclick="prevStep(3)">Back</button>
                        <button type="button" class="btn-auth-primary btn-step-continue" onclick="submitWithAddress()">Create Account</button>
                    </div>
                </div>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ─── Step navigation ────────────────────────────────────────────────────
    let currentStep = 1;

    function selectRegisterRole(element) {
        document.querySelectorAll('.register-role-selector .role-option').forEach(opt => opt.classList.remove('active'));
        element.classList.add('active');
        document.getElementById('selectedRole').value = element.dataset.role;
    }

    function nextStep(from) {
        document.getElementById('step' + from).classList.remove('active');
        document.getElementById('step' + (from + 1)).classList.add('active');
        updateStepIndicator(from, from + 1);
        currentStep = from + 1;
    }

    function prevStep(from) {
        document.getElementById('step' + from).classList.remove('active');
        document.getElementById('step' + (from - 1)).classList.add('active');
        updateStepIndicator(from, from - 1);
        currentStep = from - 1;
    }

    function updateStepIndicator(fromStep, toStep) {
        document.querySelectorAll('.step-item').forEach((item, index) => {
            item.classList.remove('active', 'completed');
            const stepNum = index + 1;
            if (stepNum < toStep) item.classList.add('completed');
            if (stepNum === toStep) item.classList.add('active');
        });
    }

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

    // ─── PSGC API address dropdowns ─────────────────────────────────────────
    const PSGC = 'https://psgc.gitlab.io/api';

    // Cache responses to avoid repeat network calls
    const cache = {};

    async function psgcFetch(url) {
        if (cache[url]) return cache[url];
        const res = await fetch(url);
        if (!res.ok) throw new Error(`PSGC API error: ${res.status}`);
        const data = await res.json();
        cache[url] = data;
        return data;
    }

    function setLoading(wrapperId, isLoading) {
        document.getElementById(wrapperId).classList.toggle('loading', isLoading);
    }

    function showError(errorId, show) {
        document.getElementById(errorId).style.display = show ? 'block' : 'none';
    }

    function populateSelect(selectEl, items, placeholder) {
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        // Sort alphabetically by name
        items.slice().sort((a, b) => a.name.localeCompare(b.name)).forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.code;
            opt.textContent = item.name;
            selectEl.appendChild(opt);
        });
        selectEl.disabled = false;
    }

    function resetSelect(selectEl, placeholder) {
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        selectEl.disabled = true;
    }

    // Load regions on page ready
    document.addEventListener('DOMContentLoaded', async () => {
        const regionSel = document.getElementById('addr_region');
        setLoading('wrap_region', true);
        try {
            const regions = await psgcFetch(`${PSGC}/regions/`);
            populateSelect(regionSel, regions, 'Select Region');
        } catch (e) {
            regionSel.innerHTML = '<option value="">Failed to load regions</option>';
            console.error(e);
        } finally {
            setLoading('wrap_region', false);
        }
    });

    // Region → Province
    document.getElementById('addr_region').addEventListener('change', async function () {
        const regionCode = this.value;
        const regionName = this.options[this.selectedIndex].text;
        showError('err_region', false);

        const provinceSel  = document.getElementById('addr_province');
        const citySel      = document.getElementById('addr_city');
        const barangaySel  = document.getElementById('addr_barangay');

        resetSelect(provinceSel,  'Select Province');
        resetSelect(citySel,      'Select City / Municipality');
        resetSelect(barangaySel,  'Select Barangay');

        if (!regionCode) return;

        setLoading('wrap_province', true);
        try {
            // NCR (code 130000000) has cities/municipalities directly, no provinces
            if (regionCode === '130000000') {
                const cities = await psgcFetch(`${PSGC}/regions/${regionCode}/cities-municipalities/`);
                populateSelect(provinceSel, [{ code: '__ncr__', name: 'Metro Manila (NCR)' }], 'Select Province');
                provinceSel.value = '__ncr__';
                provinceSel.disabled = true;

                resetSelect(citySel, 'Select City / Municipality');
                populateSelect(citySel, cities, 'Select City / Municipality');
            } else {
                const provinces = await psgcFetch(`${PSGC}/regions/${regionCode}/provinces/`);
                populateSelect(provinceSel, provinces, 'Select Province');
            }
        } catch (e) {
            provinceSel.innerHTML = '<option value="">Failed to load provinces</option>';
            console.error(e);
        } finally {
            setLoading('wrap_province', false);
        }
    });

    // Province → City / Municipality
    document.getElementById('addr_province').addEventListener('change', async function () {
        const provinceCode = this.value;
        showError('err_province', false);

        const citySel     = document.getElementById('addr_city');
        const barangaySel = document.getElementById('addr_barangay');

        resetSelect(citySel,      'Select City / Municipality');
        resetSelect(barangaySel,  'Select Barangay');

        if (!provinceCode || provinceCode === '__ncr__') return;

        setLoading('wrap_city', true);
        try {
            const cities = await psgcFetch(`${PSGC}/provinces/${provinceCode}/cities-municipalities/`);
            populateSelect(citySel, cities, 'Select City / Municipality');
        } catch (e) {
            citySel.innerHTML = '<option value="">Failed to load cities</option>';
            console.error(e);
        } finally {
            setLoading('wrap_city', false);
        }
    });

    // City / Municipality → Barangay
    document.getElementById('addr_city').addEventListener('change', async function () {
        const cityCode = this.value;
        showError('err_city', false);

        const barangaySel = document.getElementById('addr_barangay');
        resetSelect(barangaySel, 'Select Barangay');

        if (!cityCode) return;

        setLoading('wrap_barangay', true);
        try {
            const barangays = await psgcFetch(`${PSGC}/cities-municipalities/${cityCode}/barangays/`);
            populateSelect(barangaySel, barangays, 'Select Barangay');
        } catch (e) {
            barangaySel.innerHTML = '<option value="">Failed to load barangays</option>';
            console.error(e);
        } finally {
            setLoading('wrap_barangay', false);
        }
    });

    document.getElementById('addr_barangay').addEventListener('change', function () {
        showError('err_barangay', false);
    });

    // ─── Combine address parts and submit ───────────────────────────────────
    function submitWithAddress() {
        const regionSel   = document.getElementById('addr_region');
        const provinceSel = document.getElementById('addr_province');
        const citySel     = document.getElementById('addr_city');
        const barangaySel = document.getElementById('addr_barangay');
        const street      = document.getElementById('addr_street').value.trim();

        let valid = true;

        if (!regionSel.value) {
            showError('err_region', true);
            valid = false;
        }
        if (!provinceSel.value) {
            showError('err_province', true);
            valid = false;
        }
        if (!citySel.value) {
            showError('err_city', true);
            valid = false;
        }
        if (!barangaySel.value) {
            showError('err_barangay', true);
            valid = false;
        }

        if (!valid) return;

        // Build a readable address string
        const parts = [
            street,
            barangaySel.options[barangaySel.selectedIndex].text,
            citySel.options[citySel.selectedIndex].text,
            provinceSel.options[provinceSel.selectedIndex].text !== 'Metro Manila (NCR)'
                ? provinceSel.options[provinceSel.selectedIndex].text
                : null,
            regionSel.options[regionSel.selectedIndex].text,
        ].filter(Boolean);

        document.getElementById('address').value = parts.join(', ');
        document.getElementById('registerForm').submit();
    }
</script>
@endpush
