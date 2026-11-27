@extends('layouts.site')
@section('title', __('Login'))
@section('content')
<div class="bg-light py-5">
<div class="container" style="max-width:460px">
    <div class="card p-4 p-md-5 shadow-sm">
        <h1 class="h3 fw-bold text-center">{{ __('Log in') }}</h1>
        <p class="text-muted text-center">{{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create a free account') }}</a></p>
        @if(str_contains((string) session('url.intended'), '/activate'))
            <div class="alert alert-warning small"><i class="bi bi-key"></i> {{ __('Log in to activate your software. New here? Create a free account first — you will come back here automatically.') }}</div>
        @endif
        @if(str_contains((string) session('url.intended'), 'start_download'))
            <div class="alert alert-warning small"><i class="bi bi-download"></i> {{ __('Log in to download the software.') }}</div>
        @endif
        @include('partials-flash')
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div>
            <div class="mb-2"><label class="form-label">{{ __('Password') }}</label><input type="password" name="password" class="form-control" required></div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="rem" checked><label class="form-check-label small" for="rem">{{ __('Remember me') }}</label></div>
                <a href="{{ route('password.request') }}" class="small">{{ __('Forgot password?') }}</a>
            </div>
            <button class="btn btn-navy btn-lg w-100">{{ __('Log in') }}</button>
        </form>
    </div>
</div>
</div>
@endsection
