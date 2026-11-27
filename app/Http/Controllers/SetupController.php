<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** First-run setup: school details and the first admin account. */
class SetupController extends Controller
{
    public function create()
    {
        return view('setup');
    }

    public function store(Request $request, LicenseService $license)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_address' => ['nullable', 'string', 'max:300'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        foreach (['school_name', 'school_address', 'school_phone'] as $k) {
            Setting::put($k, $data[$k] ?? '');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'admin',
            'is_active' => true,
        ]);

        $license->installationId();
        $license->trialStart();

        Auth::login($user);
        $request->session()->regenerate();

        if ($license->enabled() && $license->status()['plan'] === 'none') {
            return redirect()->route('license')->with('success', 'Welcome! Your school is set up. Now start your trial to unlock the software.');
        }

        return redirect()->route('dashboard')->with('success', 'Welcome! Your school is set up. Next: check classes and set your fee structure.');
    }
}
