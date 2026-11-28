<?php

namespace App\Http\Controllers;

use App\Services\SchoolProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email or password is incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        if ($user->is_super_admin && ! $user->school_id) {
            return redirect()->intended(route('super.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    /** A new school signs up: school + admin login + default classes/fees. Everything stays locked until ₹1 is paid. */
    public function register(Request $request, SchoolProvisioner $provisioner)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['email.unique' => __('An account with this email already exists. Please log in.')]);

        $user = $provisioner->create(
            ['name' => $data['school_name'], 'phone' => $data['phone'], 'email' => $data['email'], 'city' => $data['city'], 'state' => $data['state'] ?? null],
            ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password']],
        );
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('billing')->with('success', __('Welcome! Your school account is ready. Start your trial to unlock all features.'));
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        try {
            PasswordBroker::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['email' => __('Email could not be sent right now. Please contact us on WhatsApp.')]);
        }

        return back()->with('success', __('If an account exists for this email, a password reset link has been sent.'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::min(8)]]);

        $status = PasswordBroker::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __('Password changed. Please log in.'))
            : back()->withErrors(['email' => __('This reset link is invalid or has expired.')]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => Hash::make($request->password)]);

        return redirect()->route('dashboard')->with('success', 'Password changed.');
    }
}
