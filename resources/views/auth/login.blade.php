@extends('layouts.guest')
@section('title', 'Login')
@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="card shadow p-4" style="max-width:400px;width:100%">
        <div class="text-center mb-4">
            <div class="small text-uppercase accent fw-semibold" style="letter-spacing:.1em">School Management System</div>
            <h1 class="h4 fw-bold mt-1">{{ school('name') }}</h1>
            <p class="text-muted small mb-0">Sign in to the admin panel</p>
        </div>
        @include('partials.flash')
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <button class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center mt-3 small"><a href="{{ route('apply') }}">Online Admission Form →</a></div>
    </div>
</div>
@endsection
