<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::where('school_id', Tenant::id())->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'accountant', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request) + ['school_id' => Tenant::id()]);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function edit(User $user)
    {
        $this->ownSchool($user);

        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->ownSchool($user);
        $data = $this->validated($request, $user);
        if ($user->is($request->user())) {
            // Admin cannot lock themselves out
            $data['role'] = 'admin';
            $data['is_active'] = true;
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->ownSchool($user);
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    /** Staff accounts of other schools are invisible. */
    private function ownSchool(User $user): void
    {
        abort_unless($user->school_id && $user->school_id === Tenant::id(), 404);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
