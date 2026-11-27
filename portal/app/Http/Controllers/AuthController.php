<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function showRegister(Request $request)
    {
        if ($request->query('next') === 'download') {
            $request->session()->put('url.intended', route('account', ['start_download' => 1]));
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], ['email.unique' => __('An account with this email already exists. Please log in.')]);

        $user = User::create($data + ['locale' => app()->getLocale()]);
        Auth::login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('account'))->with('success', __('Welcome! Your account is ready.'));
    }

    public function showLogin(Request $request)
    {
        if ($request->query('next') === 'download') {
            $request->session()->put('url.intended', route('account', ['start_download' => 1]));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('Email or password is incorrect.')])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        if ($request->user()->locale) {
            $request->session()->put('locale', $request->user()->locale);
        }

        return redirect()->intended($request->user()->is_admin ? route('admin.dashboard') : route('account'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['email' => __('Email could not be sent right now. Please contact us on WhatsApp.')]);
        }

        // Same message whether or not the email exists (do not reveal accounts)
        return back()->with('success', __('If an account exists for this email, a password reset link has been sent.'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __('Password changed. Please log in.'))
            : back()->withErrors(['email' => __('This reset link is invalid or has expired.')]);
    }
}
