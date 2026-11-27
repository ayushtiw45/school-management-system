@extends('layouts.guest')
@section('title', 'Welcome')
@section('content')
<div class="container py-5" style="max-width:640px">
    <div class="text-center text-white mb-4">
        <div class="small text-uppercase accent fw-semibold" style="letter-spacing:.1em">Welcome to</div>
        <h1 class="h3 fw-bold mt-1">TDS School Management Software</h1>
        <p class="opacity-75 small mb-0">One-time setup. Everything you enter is saved only on this computer.</p>
    </div>
    <div class="card p-4">
        @include('partials.flash')
        <form method="POST" action="{{ route('setup.store') }}" class="row g-3">
            @csrf
            <div class="col-12"><h2 class="h6 fw-semibold mb-0">1. Your school</h2></div>
            <div class="col-12"><label class="form-label required">School Name</label><input name="school_name" value="{{ old('school_name') }}" class="form-control" required autofocus></div>
            <div class="col-md-8"><label class="form-label">Address</label><input name="school_address" value="{{ old('school_address') }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input name="school_phone" value="{{ old('school_phone') }}" class="form-control"></div>

            <div class="col-12 mt-4"><h2 class="h6 fw-semibold mb-0">2. Admin login</h2><div class="small text-muted">You will use this email and password to log in. You can add staff accounts later.</div></div>
            <div class="col-md-6"><label class="form-label required">Your Name</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label required">Password</label><input type="password" name="password" minlength="8" class="form-control" required><div class="form-text">At least 8 characters.</div></div>
            <div class="col-md-6"><label class="form-label required">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <div class="col-12 mt-4"><button class="btn btn-primary btn-lg w-100">Continue</button></div>
        </form>
    </div>
</div>
@endsection
