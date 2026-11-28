@extends('layouts.site')
@section('title', __('Sign up'))
@section('content')
<div class="bg-light py-5">
<div class="container" style="max-width:720px">
    <div class="card p-4 p-md-5 shadow-sm">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold">{{ __('Register your school') }}</h1>
            <p class="small text-muted mb-1">{{ __('Your school gets its own private account. Start the 7-day trial for ₹1 after signing up.') }}</p>
            <p class="text-muted mb-0">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a></p>
        </div>
        @include('site.flash')
        <form method="POST" action="{{ route('register') }}" class="row g-3">
            @csrf
            <div class="col-12"><label class="form-label">{{ __('School name') }} *</label><input name="school_name" value="{{ old('school_name') }}" class="form-control" required autofocus></div>
            <div class="col-md-6"><label class="form-label">{{ __('Your name') }} *</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('Mobile number') }} *</label><input name="phone" value="{{ old('phone') }}" class="form-control" inputmode="tel" required placeholder="98XXXXXXXX"></div>
            <div class="col-md-6"><label class="form-label">{{ __('City') }} *</label><input name="city" value="{{ old('city') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('State') }}</label><input name="state" value="{{ old('state') }}" class="form-control" list="states"></div>
            <datalist id="states">@foreach(['Andhra Pradesh','Assam','Bihar','Chhattisgarh','Delhi','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Odisha','Punjab','Rajasthan','Tamil Nadu','Telangana','Uttar Pradesh','Uttarakhand','West Bengal'] as $st)<option value="{{ $st }}">@endforeach</datalist>
            <div class="col-12"><label class="form-label">{{ __('Email') }} *</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('Password') }} *</label><input type="password" name="password" minlength="8" class="form-control" required><div class="form-text">{{ __('At least 8 characters.') }}</div></div>
            <div class="col-md-6"><label class="form-label">{{ __('Confirm password') }} *</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <div class="col-12 small text-muted">{!! __('By signing up you agree to our :terms and :privacy.', ['terms' => '<a href="'.route('terms').'">'.e(__('Terms')).'</a>', 'privacy' => '<a href="'.route('privacy').'">'.e(__('Privacy Policy')).'</a>']) !!}</div>
            <div class="col-12"><button class="btn btn-amber btn-lg w-100">{{ __('Create school account') }}</button></div>
        </form>
    </div>
</div>
</div>
@endsection
