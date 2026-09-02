<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'password'              => 'required|min:8|confirmed',
            'first_name'            => 'required|string|max:100',
            'last_name'             => 'required|string|max:100',
            'phone'                 => 'nullable|string|max:20',
            'address'               => 'nullable|string|max:255',
            'role'                  => 'required|in:buyer,seller,courier',
        ]);

        $user = User::create([
            'name'       => $request->first_name . ' ' . $request->last_name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
            'phone'      => $request->phone,
            'address'    => $request->address,
        ]);

        Auth::login($user);

        return $this->redirectByRole($user->role);
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
