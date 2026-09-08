<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Services\EmailVerificationService;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
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
            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

        $request->session()->regenerate();

        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! Auth::user()->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account is pending approval or has been suspended.',
            ]);
        }

        return $this->redirectByRole(Auth::user()->role);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'first_name'            => 'required|string|max:100',
            'last_name'             => 'required|string|max:100',
            'phone'                 => 'nullable|string|max:20',
            'role'                  => 'required|in:buyer,seller,courier',
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name'       => $request->first_name . ' ' . $request->last_name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
                'role'       => $request->role,
                'phone'      => $request->phone,
                'status'     => $request->role === 'buyer' ? 'active' : 'pending',
            ]);

            if ($user->role === 'seller') {
                DB::table('seller_profiles')->insert([
                    'user_id' => $user->id,
                    'store_name' => $user->name."'s Store",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($user->role === 'courier') {
                DB::table('courier_profiles')->insert([
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $user;
        });

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

    public function verificationNotice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->verificationComplete($request);
        }

        return view('auth.verify-email');
    }

    public function verifyEmail(Request $request, EmailVerificationService $verification)
    {
        $request->validate(['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']], [
            'otp.required' => 'Enter the verification code from your email.',
            'otp.regex' => 'Enter the six-digit code from your email.',
        ]);
        $verification->verify($request->user(), $request->input('otp'));

        return $this->verificationComplete($request);
    }

    private function verificationComplete(Request $request)
    {
        if (! $request->user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Email verified. Your marketplace account is still awaiting administrator approval.');
        }

        return $this->redirectByRole($request->user()->role)->with('status', 'Email verified successfully.');
    }

    public function resendVerification(Request $request)
    {
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
            'password' => 'required|string|min:8|confirmed',
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    private function redirectByRole(string $role)
    {
        return match ($role) {
            'buyer'   => redirect()->route('buyer.dashboard'),
            'seller'  => redirect()->route('seller.dashboard'),
            'courier' => redirect()->route('courier.dashboard'),
            'admin'   => redirect()->route('admin.dashboard'),
            default   => redirect()->route('home'),
        };
    }
}
