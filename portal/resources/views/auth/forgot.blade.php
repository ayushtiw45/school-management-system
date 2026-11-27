@extends('layouts.site')
@section('title', __('Forgot password'))
@section('content')
<div class="bg-light py-5">
<div class="container" style="max-width:460px">
    <div class="card p-4 p-md-5 shadow-sm">
        <h1 class="h4 fw-bold">{{ __('Forgot password?') }}</h1>
        <p class="text-muted">{{ __('Enter your email and we will send you a link to set a new password.') }}</p>
        @include('partials-flash')
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-3"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div>
            <button class="btn btn-navy w-100">{{ __('Send reset link') }}</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="{{ route('login') }}">{{ __('Back to login') }}</a></p>
    </div>
</div>
</div>
@endsection
