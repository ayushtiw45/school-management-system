@extends('layouts.site')
@section('title', __('Set new password'))
@section('content')
<div class="bg-light py-5">
<div class="container" style="max-width:460px">
    <div class="card p-4 p-md-5 shadow-sm">
        <h1 class="h4 fw-bold">{{ __('Set new password') }}</h1>
        @include('partials-flash')
        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">{{ __('New password') }}</label><input type="password" name="password" minlength="8" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">{{ __('Confirm password') }}</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <button class="btn btn-navy w-100">{{ __('Save password') }}</button>
        </form>
    </div>
</div>
</div>
@endsection
