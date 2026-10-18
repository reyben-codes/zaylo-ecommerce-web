<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\PendingRegistration;
use App\Models\Rider;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Services\EmailVerificationService;
use App\Services\LogisticsApplicationService;
use App\Services\PendingRegistrationService;
use App\Services\SellerApplicationService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showSellerLogin()
    {
        return view('auth.login', ['portal' => 'seller']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            if ($resume = $this->resumePendingSignUp($request)) {
                return $resume;
            }

            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

        $request->session()->regenerate();

        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($route = Auth::user()->applicationRoute()) {
            return redirect()->route($route);
        }

        if (! Auth::user()->isActive()) {
            $message = $this->riderReviewMessage(Auth::user()) ?? 'This account is pending approval or has been suspended.';
            Auth::logout();

            throw ValidationException::withMessages(['email' => $message]);
        }

        return $this->redirectByRole(Auth::user());
    }

    public function sellerLogin(Request $request)
    {
        return $this->portalLogin($request, 'seller', 'seller.dashboard');
    }

    public function showLogisticsLogin()
    {
        return view('auth.login', ['portal' => 'logistics']);
    }

    public function logisticsLogin(Request $request)
    {
        return $this->portalLogin($request, 'logistics', 'logistics.dashboard');
    }

    public function showAdminLogin()
    {
        return view('auth.login', ['portal' => 'admin']);
    }

    public function adminLogin(Request $request)
    {
        return $this->portalLogin($request, 'admin', 'admin.dashboard');
    }

    /**
     * Partner portals only admit their own role, with the same generic error for wrong credentials and wrong role.
     */
    private function portalLogin(Request $request, string $role, string $dashboardRoute)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $rejected = "Incorrect {$role} email or password.";
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            if ($resume = $this->resumePendingSignUp($request, $role)) {
                return $resume;
            }

            throw ValidationException::withMessages(['email' => $rejected]);
        }

        if (! Auth::user()->hasRole($role)) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => $rejected]);
        }

        $request->session()->regenerate();

        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // Sellers and logistics partners under review or rejected see their application: the status, any reason, and how to resubmit.
        if ($route = Auth::user()->applicationRoute()) {
            return redirect()->route($route);
        }

        if (! Auth::user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => "This {$role} account is pending approval or has been suspended.",
            ]);
        }

        return redirect()->route($dashboardRoute);
    }

    public function showRegister()
    {
        return view('auth.register', [
            'logisticsPartners' => Schema::hasTable('logistics_providers')
                ? LogisticsProvider::acceptingRiders()->get(['id', 'name', 'service_area'])
                : collect(),
        ]);
    }

    public function register(Request $request, SellerApplicationService $sellerApplications, LogisticsApplicationService $logisticsApplications, PendingRegistrationService $registrations)
    {
        // +63 9XX numbers are the same phone as 09XX; store one form so duplicates are caught either way.
        if (preg_match('/^\+639[0-9]{9}$/D', (string) $request->input('phone'))) {
            $request->merge(['phone' => '0'.substr($request->input('phone'), 3)]);
        }

        $sellerApplying = $request->input('role') === 'seller' && $sellerApplications->available();
        // Logistics partners give their SEC and TIN details, office address, service regions and business documents.
        $logisticsApplying = $request->input('role') === 'logistics' && $logisticsApplications->available();
        if ($logisticsApplying) {
            $logisticsApplications->prepare($request);
        }        // Rider applications take a driver's license (LTO format), its expiry, and license and OR/CR documents.
        $riderApplying = $request->input('role') === 'courier';
        $riderDocuments = $riderApplying && Schema::hasColumn('riders', 'license_document_path');
        if ($riderApplying) {
            $request->merge([
                'license_number' => Rider::normalizeLicense($request->input('license_number')) ?: null,
                'plate_no' => Rider::normalizePlate($request->input('plate_no')) ?: null,
            ]);
        }
        $riderOnly = fn (array $rules) => array_merge(['exclude_unless:role,courier', 'nullable', 'required_unless:vehicle_type,'.implode(',', Rider::UNLICENSED_VEHICLES)], $rules);
        $riderRules = $riderDocuments ? [
            'license_expires_on' => $riderOnly(['date', 'after_or_equal:today']),
            'license_document' => $riderOnly(['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.Rider::MAX_DOCUMENT_KB]),
            'or_cr_document' => $riderOnly(['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.Rider::MAX_DOCUMENT_KB]),
        ] : [];

        $validated = $request->validate(array_replace([
            'email'                 => 'required|email:filter|unique:users,email',
            'password'              => ['required', 'string', 'confirmed', new StrongPassword],
            'first_name'            => ['required', 'string', 'min:2', 'max:50', new PersonName],
            'last_name'             => ['required', 'string', 'min:2', 'max:50', new PersonName],
            'phone'                 => ['required', 'string', 'max:11', new PhoneNumber, 'unique:users,phone'],
            'date_of_birth'         => ['required_if:role,buyer,courier', 'nullable', 'bail', 'date', 'before_or_equal:today', function (string $attribute, mixed $value, \Closure $fail) {
                if (Carbon::parse($value)->startOfDay()->gt(today()->subYears(18))) {
                    $fail('You must be at least 18 years old to register.');
                }
            }],
            'terms'                 => 'accepted',
            'role'                  => 'required|in:buyer,seller,courier,logistics',
            'company_name'          => 'exclude_unless:role,logistics|required|string|min:2|max:150',
            'service_area'          => $logisticsApplying ? 'exclude' : 'exclude_unless:role,logistics|nullable|string|max:150',
            'logistics_provider_id' => ['exclude_unless:role,courier', 'required', 'integer', Rule::exists('logistics_providers', 'id')->where('status', 'approved')],
            'vehicle_type'          => ['exclude_unless:role,courier', 'required', Rule::in(Rider::VEHICLE_TYPES)],
            'plate_no'              => $riderOnly(['string', 'regex:'.Rider::PLATE_PATTERN, 'unique:riders,plate_no']),
            'license_number'        => $riderOnly(['string', 'regex:'.Rider::LICENSE_PATTERN, 'unique:riders,license_number']),
        ] + $riderRules + ($sellerApplying ? $sellerApplications->rules($request) : []),
            // The logistics company name rule (with its duplicate check) replaces the basic one above.
            $logisticsApplying ? $logisticsApplications->rules() : []), [
            'email.email' => 'Enter a valid email address, such as name@example.com.',
            'first_name.min' => 'The first name must be 2 to 50 characters.',
            'first_name.max' => 'The first name must be 2 to 50 characters.',
            'last_name.min' => 'The last name must be 2 to 50 characters.',
            'last_name.max' => 'The last name must be 2 to 50 characters.',
            'phone.unique' => 'This mobile number is already in use.',
            'date_of_birth.before_or_equal' => 'Enter a valid date of birth. It cannot be a future date.',
            'date_of_birth.required_if' => 'Enter your birthday.',
            'terms.accepted' => 'Please accept the Terms and Privacy Policy to continue.',
            'logistics_provider_id.required' => 'Choose the logistics partner you are applying to.',
            'logistics_provider_id.exists' => 'That logistics partner is not accepting riders right now.',
            'plate_no.required_unless' => 'Enter your vehicle plate number.',
            'plate_no.regex' => 'Enter the plate as 5 to 7 letters and numbers, for example 123ABC or NBC1234.',
            'plate_no.unique' => 'This plate number is already registered to another rider.',
            'license_number.required_unless' => "Enter your driver's license number.",
            'license_number.regex' => "Enter your driver's license number in the LTO format A00-00-000000.",
            'license_number.unique' => "This driver's license number is already registered.",
            'license_expires_on.required_unless' => "Enter your driver's license expiry date.",
            'license_expires_on.after_or_equal' => "Your driver's license has expired. Renew it before applying.",
            'license_document.required_unless' => "Upload a photo or PDF of your driver's license.",
            'or_cr_document.required_unless' => 'Upload a photo or PDF of your vehicle OR/CR.',
            'license_document.mimes' => "Driver's license: upload a JPG, PNG or PDF file.",
            'or_cr_document.mimes' => 'Vehicle OR/CR: upload a JPG, PNG or PDF file.',
            'license_document.max' => "Driver's license: the file must be 5 MB or smaller.",
            'or_cr_document.max' => 'Vehicle OR/CR: the file must be 5 MB or smaller.',
            'license_document.uploaded' => "Driver's license: the file could not be uploaded. Files must be 5 MB or smaller.",
            'or_cr_document.uploaded' => 'Vehicle OR/CR: the file could not be uploaded. Files must be 5 MB or smaller.',
        ] + ($sellerApplying ? $sellerApplications->messages() : []) + ($logisticsApplying ? $logisticsApplications->messages() : []));

        if ($request->role === 'courier' && ! LogisticsProvider::acceptingRiders()->whereKey($request->logistics_provider_id)->exists()) {
            throw ValidationException::withMessages(['logistics_provider_id' => 'That logistics partner is not accepting riders right now.']);
        }

        // Resolve the pickup address before saving anything, so a location-service outage leaves no half-made account.
        $pickupNames = match (true) {
            $sellerApplying => $sellerApplications->addressNames($validated),
            $logisticsApplying => $logisticsApplications->addressNames($validated),
            default => null,
        };

        if (! $registrations->available()) {
            // Until the pending_registrations migration runs, sign-up saves the account first, as before.
            $user = $registrations->createImmediately($request, $validated, $pickupNames);
            Auth::login($user);
            $request->session()->regenerate();
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->route('verification.notice')->withErrors(['otp' => 'Your account was saved, but we could not send your code. Please try Resend code.']);
            }

            return redirect()->route('verification.notice')->with('status', 'Your verification code has been sent.');
        }

        // Nothing is saved as an account yet: the details wait until the emailed code is entered.
        $pending = $registrations->begin($request, $validated, $pickupNames);
        $request->session()->put(PendingRegistrationService::SESSION_KEY, $pending->token);

        try {
            $registrations->sendCode($pending, first: true);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('verification.notice')->withErrors(['otp' => 'We could not send your code. Your account has not been created yet; tap Resend code to try again.']);
        }

        return redirect()->route('verification.notice')->with('status', 'Your verification code has been sent.');
    }

    public function verificationNotice(Request $request, PendingRegistrationService $registrations)
    {
        if ($request->user()) {
            if ($request->user()->hasVerifiedEmail()) {
                return $this->verificationComplete($request);
            }

            return view('auth.verify-email', [
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'active' => $request->user()->isActive(),
                'pending' => false,
            ]);
        }

        $pending = $registrations->current($request);
        if (! $pending) {
            return redirect()->route('login');
        }

        return view('auth.verify-email', [
            'email' => $pending->email,
            'role' => $pending->role === 'courier' ? 'rider' : $pending->role,
            'active' => $pending->role === 'buyer',
            'pending' => true,
        ]);
    }

    public function verifyEmail(Request $request, EmailVerificationService $verification, PendingRegistrationService $registrations)
    {
        $pending = $request->user() ? null : $registrations->current($request);
        if (! $request->user() && ! $pending) {
            return redirect()->route('login');
        }
        $request->validate(['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']], [
            'otp.required' => 'Enter the verification code from your email.',
            'otp.regex' => 'Enter the six-digit code from your email.',
        ]);

        if ($pending) {
            // The account is created only now, with its email already verified.
            $user = $registrations->complete($pending, $request->input('otp'));
            $request->session()->forget(PendingRegistrationService::SESSION_KEY);
            Auth::login($user);
            $request->session()->regenerate();

            return $this->verificationComplete($request);
        }

        $verification->verify($request->user(), $request->input('otp'));

        return $this->verificationComplete($request);
    }

    /** "Wrong email? Start over": drop the unfinished sign-up and its uploads. */
    public function cancelRegistration(Request $request, PendingRegistrationService $registrations)
    {
        $pending = $registrations->current($request);
        $role = $pending?->role ?? 'buyer';
        if ($pending) {
            $registrations->discard($pending);
        }
        $request->session()->forget(PendingRegistrationService::SESSION_KEY);

        return redirect()->route('register', $role === 'buyer' ? [] : ['role' => $role])
            ->with('status', 'Your sign-up was cancelled. Nothing was saved.');
    }

    private function verificationComplete(Request $request)
    {
        if ($request->user()->awaitsSellerApproval()) {
            return redirect()->route('seller.application')->with('status', 'Email verified. Your seller application is waiting for admin approval.');
        }
        if ($request->user()->awaitsLogisticsApproval()) {
            return redirect()->route('logistics.application')->with('status', 'Email verified. Your logistics application is waiting for admin approval.');
        }

        if (! $request->user()->isActive()) {
            $loginRoute = $request->user()->portalLoginRoute();
            $reviewer = $request->user()->hasRole('rider') ? 'your logistics partner' : 'an administrator';
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($loginRoute)->with('status', "Email verified. Your account is still awaiting approval from {$reviewer}.");
        }

        return $this->redirectByRole($request->user())->with('status', 'Email verified successfully.');
    }

    public function resendVerification(Request $request, PendingRegistrationService $registrations)
    {
        if (! $request->user()) {
            $pending = $registrations->current($request);
            if (! $pending) {
                return redirect()->route('login');
            }
            try {
                $registrations->sendCode($pending);
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['otp' => 'We could not send your code. Please try again shortly.']);
            }

            return back()->with('status', 'A new verification code has been sent. Only the latest code will work.');
        }

        if ($request->user()->hasVerifiedEmail()) {
            return $this->verificationComplete($request);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['otp' => 'We could not send your code. Please try again shortly.']);
        }

        return back()->with('status', 'A new verification code has been sent. Only the latest code will work.');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request)
    {
        $redirectRoute = $request->user()?->hasRole('seller', 'logistics', 'admin') ? $request->user()->portalLoginRoute() : 'home';
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route($redirectRoute);
    }

    /**
     * Signing in before entering the emailed code: there is no account yet, only the waiting sign-up.
     * With the right password, reopen that sign-up's code page instead of reporting a wrong password.
     */
    private function resumePendingSignUp(Request $request, ?string $portalRole = null)
    {
        if (! Schema::hasTable('pending_registrations')) {
            return null;
        }
        $pending = PendingRegistration::whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $request->input('email')))])
            ->where('expires_at', '>', now())->latest('id')->first();
        if (! $pending || ! Hash::check((string) $request->input('password'), $pending->payload['password_hash'] ?? '')) {
            return null;
        }
        $role = $pending->role === 'courier' ? 'rider' : $pending->role;
        if ($portalRole && $role !== $portalRole) {
            return null;
        }

        $request->session()->regenerate();
        $request->session()->put(PendingRegistrationService::SESSION_KEY, $pending->token);

        return redirect()->route('verification.notice')
            ->with('status', 'Please verify your email first. Enter the code we emailed you to finish creating your account, or tap Resend code.');
    }

    /** Tells a rider who is reviewing them, or why their application was rejected. */
    private function riderReviewMessage(User $user): ?string
    {
        if (! $user->hasRole('rider') || ! Schema::hasTable('riders')) {
            return null;
        }
        $rider = Rider::with('provider')->where('user_id', $user->id)->first();
        $partner = $rider?->provider?->name ?? 'your logistics partner';

        return match ($user->status) {
            'pending' => "Your rider application is waiting for {$partner} to review it.",
            'rejected' => "{$partner} did not approve your rider application.".($rider?->rejection_reason ? " Reason: {$rider->rejection_reason}" : ''),
            default => null,
        };
    }

    private function redirectByRole(User $user)
    {
        if ($user->hasRole('admin')) return redirect()->route('admin.dashboard');
        if ($user->hasRole('seller')) return redirect()->route('seller.dashboard');
        if ($user->hasRole('logistics')) return redirect()->route('logistics.dashboard');
        if ($user->hasRole('rider')) return redirect()->route('courier.dashboard');
        if ($user->hasRole('buyer')) return redirect()->route('buyer.dashboard');

        return redirect()->route('home');
    }
}
