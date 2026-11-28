@extends('layouts.site')
@section('title', __('Privacy Policy'))
@section('content')
<div class="container py-5" style="max-width:820px">
    <h1 class="fw-bold mb-4">{{ __('Privacy Policy') }}</h1>
    <h2 class="h5 mt-4">{{ __('Your school data') }}</h2>
    <p>{{ __('Student, parent, fee, payment and expense records your school enters are stored on our secure server, separately for each school. Only the logins created by your school can see your school\'s data; other schools can never see it. We do not sell or share your data.') }}</p>
    <h2 class="h5 mt-4">{{ __('Your account') }}</h2>
    <p>{{ __('We store your school name, contact person, mobile number, email, city and state to run your account, send important messages about your plan and give support.') }}</p>
    <h2 class="h5 mt-4">{{ __('Payments') }}</h2>
    <p>{{ __('Online payments are processed by Razorpay. We do not see or store your card, UPI or bank details; we only store the payment reference and amount.') }}</p>
    <h2 class="h5 mt-4">{{ __('Contact') }}</h2>
    <p>{{ __('To update or delete your account details, contact us using the details at the bottom of this page.') }}</p>
</div>
@endsection
