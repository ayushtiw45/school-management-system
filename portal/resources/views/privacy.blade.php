@extends('layouts.site')
@section('title', __('Privacy Policy'))
@section('content')
<div class="container py-5" style="max-width:820px">
    <h1 class="fw-bold mb-4">{{ __('Privacy Policy') }}</h1>
    <h2 class="h5 mt-4">{{ __('Your school data') }}</h2>
    <p>{{ __('TDS School Management Software runs on your own computer. Student, parent, fee, payment and expense records you enter in the software are stored only on that computer. We do not receive, store or have access to this data.') }}</p>
    <h2 class="h5 mt-4">{{ __('Your website account') }}</h2>
    <p>{{ __('When you create an account on this website we store your school name, your name, mobile number, email, city and state, the time you download the software, and the licenses and payments linked to your account. We use these details to give you your license keys, to contact you about your account and to provide support.') }}</p>
    <h2 class="h5 mt-4">{{ __('Payments') }}</h2>
    <p>{{ __('Online payments are processed by Razorpay. We do not see or store your card, UPI or bank details; we only store the payment reference and amount.') }}</p>
    <h2 class="h5 mt-4">{{ __('Contact') }}</h2>
    <p>{{ __('To update or delete your account details, contact us using the details at the bottom of this page.') }}</p>
</div>
@endsection
