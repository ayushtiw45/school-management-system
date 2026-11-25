@extends('layouts.app')
@section('title', 'Change Password')
@section('content')
<div class="card" style="max-width:480px">
    <div class="card-body">
        <form method="POST" action="{{ route('password.update') }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label required">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label required">New Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
            <div class="mb-3"><label class="form-label required">Confirm New Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <button class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>
@endsection
