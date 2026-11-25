@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add User')
@section('content')
<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="row g-3">
            @csrf @if($user->exists) @method('PUT') @endif
            <div class="col-md-6"><label class="form-label required">Name</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Mobile</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label required">Email (login)</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required></div>
            <div class="col-md-6">
                <label class="form-label required">Role</label>
                <select name="role" class="form-select" @disabled($user->is(auth()->user()))>
                    @foreach(\App\Models\User::ROLES as $k => $l)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $l }}</option>@endforeach
                </select>
                @if($user->is(auth()->user()))<input type="hidden" name="role" value="admin">@endif
            </div>
            <div class="col-md-6"><label class="form-label {{ $user->exists ? '' : 'required' }}">Password</label><input type="password" name="password" class="form-control" minlength="8" {{ $user->exists ? '' : 'required' }}>@if($user->exists)<div class="form-text">Leave blank to keep the current password.</div>@endif</div>
            <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control"></div>
            <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="act" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="act">Account active (can log in)</label></div>
            <div class="col-12 d-flex gap-2"><a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save</button></div>
        </form>
    </div>
</div>
@endsection
